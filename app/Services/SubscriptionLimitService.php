<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\ExamType;
use App\Enums\PlanFeature;
use App\Enums\UserRole;
use App\Models\BoardQuestionPaper;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The single place that answers "what may this user do" (CLAUDE.md rule
 * 7): which classes and subjects they reach, which difficulties, whether
 * they may print or share online, and how many exams they have left today
 * and this month. Used by Teacher exam saving, publishing and printing and
 * by every Student self-practice mode, so none of them writes its own check.
 *
 * A user's access is the sum of their grants: every active subscription,
 * plus their role's free plan. With neither, they may do nothing that needs
 * a plan. Each exam is counted against the one grant that allowed it
 * (exams.subscription_id; empty = the free plan), so a paid plan's exams
 * never use up the free plan's daily allowance, and the other way round.
 */
class SubscriptionLimitService
{
    /**
     * Paid subscriptions first, so an exam is counted against what the user
     * paid for before it touches the free allowance.
     *
     * @return array<int, PlanGrant>
     */
    public function grantsFor(User $user): array
    {
        $grants = $user->activeSubscriptions()
            ->map(fn ($subscription) => PlanGrant::fromSubscription($subscription, $user))
            ->all();

        if ($freePlan = $this->freePlanFor($user)) {
            $grants[] = PlanGrant::fromFreePlan($freePlan, $user);
        }

        return $grants;
    }

    public function freePlanFor(User $user): ?SubscriptionPlan
    {
        $role = $user->role->subscriptionTargetRole();

        return $role ? SubscriptionPlan::defaultFreeFor($role)->first() : null;
    }

    /**
     * The grant that lets the user make an exam of this class + subject
     * with these features and question difficulties — the one the exam is
     * then counted against. With `$countsAgainstLimits` off (editing,
     * publishing or printing an exam that was already counted) only the
     * scope is checked.
     *
     * @param  array<int, PlanFeature>  $features
     * @param  array<int, Difficulty>  $difficulties
     *
     * @throws ValidationException naming what the user's plans are missing
     */
    public function authorize(
        User $user,
        ClassSubject $classSubject,
        array $features = [],
        array $difficulties = [],
        bool $countsAgainstLimits = true,
    ): PlanGrant {
        $grants = array_filter($this->grantsFor($user), fn (PlanGrant $grant) => $grant->covers($classSubject));

        if ($grants === []) {
            $this->deny(__('Your plan does not include this class and subject. Buy a plan that covers it to continue.'));
        }

        $grants = array_filter($grants, fn (PlanGrant $grant) => $grant->allowsFeatures($features));

        if ($grants === []) {
            $this->deny(__('Your plan does not include :features.', [
                'features' => collect($features)->map(fn (PlanFeature $feature) => $feature->getLabel())->join(', '),
            ]));
        }

        $grants = array_filter($grants, fn (PlanGrant $grant) => $grant->allowsDifficulties($difficulties));

        if ($grants === []) {
            $this->deny(__('Your plan does not include :difficulties questions. Upgrade to use them.', [
                'difficulties' => collect($difficulties)->unique()->map(fn (Difficulty $difficulty) => $difficulty->getLabel())->join(', '),
            ]));
        }

        if (! $countsAgainstLimits) {
            return reset($grants);
        }

        // An unlimited grant first: nothing to count, and it spares a
        // limited one's allowance.
        usort($grants, fn (PlanGrant $a, PlanGrant $b) => $a->hasLimits() <=> $b->hasLimits());

        foreach ($grants as $grant) {
            if (! $this->hasReachedLimit($user, $grant)) {
                return $grant;
            }
        }

        $this->deny(__('You have used all the exams your plan allows for now. Upgrade your subscription to create more.'));
    }

    /**
     * Whether the user may use these features on this class + subject at
     * all — for an exam that already exists and was counted when saved.
     *
     * @param  array<int, PlanFeature>  $features
     */
    public function allows(User $user, ?ClassSubject $classSubject, array $features = []): bool
    {
        foreach ($this->grantsFor($user) as $grant) {
            if (($classSubject === null || $grant->covers($classSubject)) && $grant->allowsFeatures($features)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The difficulties the user may use in this class + subject, across all
     * their grants: null when every one is allowed, empty when none.
     *
     * @return array<int, Difficulty>|null
     */
    public function allowedDifficultiesFor(User $user, ClassSubject $classSubject): ?array
    {
        $allowed = [];

        foreach ($this->grantsFor($user) as $grant) {
            if (! $grant->covers($classSubject)) {
                continue;
            }

            if ($grant->difficulties === null) {
                return null;
            }

            foreach ($grant->difficulties as $difficulty) {
                $allowed[$difficulty->value] = $difficulty;
            }
        }

        return array_values($allowed);
    }

    /**
     * The class + subject pairs the user's plans reach, as a query
     * constraint on class_subjects — for narrowing a class or subject
     * picker to what can actually be used.
     *
     * @param  array<int, PlanFeature>  $features
     */
    public function constrainToAccessible(Builder $classSubjects, User $user, array $features = []): Builder
    {
        $grants = array_filter($this->grantsFor($user), fn (PlanGrant $grant) => $grant->allowsFeatures($features));

        if ($grants === []) {
            return $classSubjects->whereRaw('1 = 0');
        }

        foreach ($grants as $grant) {
            if ($grant->coversEverything()) {
                return $classSubjects;
            }
        }

        return $classSubjects->where(function (Builder $query) use ($grants): void {
            foreach ($grants as $grant) {
                $query->orWhere(fn (Builder $scope) => $scope
                    ->when($grant->classIds !== null, fn (Builder $q) => $q->whereIn('academic_class_id', $grant->classIds))
                    ->when($grant->subjectIds !== null, fn (Builder $q) => $q->whereIn('subject_id', $grant->subjectIds)));
            }
        });
    }

    /**
     * How many exams have been counted against a grant, today and this
     * month.
     *
     * @return array{today: int, month: int}
     */
    public function usageOf(User $user, PlanGrant $grant): array
    {
        // A past paper is counted by its own allowance (pastPaperUsageOf()).
        $exams = fn (): Builder => Exam::query()
            ->where('created_by', $user->id)
            ->where('exam_type', $this->examTypeFor($user))
            ->where('subscription_id', $grant->subscriptionId)
            ->whereNull('board_question_paper_id');

        return [
            'today' => $exams()->where('created_at', '>=', now()->startOfDay())->count(),
            'month' => $exams()->inMonth()->count(),
        ];
    }

    /**
     * Every grant with what is left of it, for a "your plan" page.
     *
     * @return array<int, array{grant: PlanGrant, today: int, month: int, past_papers: int, past_papers_again_at: CarbonInterface|null}>
     */
    public function summaryFor(User $user): array
    {
        return array_map(
            fn (PlanGrant $grant): array => [
                'grant' => $grant,
                ...($grant->hasLimits() ? $this->usageOf($user, $grant) : ['today' => 0, 'month' => 0]),
                'past_papers' => $grant->pastPapers->hasLimit() ? $this->pastPaperUsageOf($user, $grant) : 0,
                'past_papers_again_at' => $grant->pastPapers->hasLimit() ? $this->nextPastPaperAt($user, $grant) : null,
            ],
            $this->grantsFor($user),
        );
    }

    /**
     * The grant that lets the user sit (a student) or set (a teacher) this
     * past paper, with these features — the one the exam is then counted
     * against. With `$countsAgainstLimits` off (publishing or printing a
     * paper exam already counted) only what the plan reaches is checked.
     *
     * @param  array<int, PlanFeature>  $features
     *
     * @throws ValidationException naming what is missing, or when the next paper is allowed
     */
    public function authorizePastPaper(User $user, BoardQuestionPaper $paper, array $features = [], bool $countsAgainstLimits = true): PlanGrant
    {
        $grants = array_filter($this->grantsFor($user), fn (PlanGrant $grant) => $grant->allowsPastPaper($paper, $features));

        if ($grants === []) {
            $this->deny(__('Your plan does not include this past paper. Buy a plan that covers it to continue.'));
        }

        if (! $countsAgainstLimits) {
            return reset($grants);
        }

        // An unlimited grant first: nothing to count, and it spares a
        // limited one's allowance.
        usort($grants, fn (PlanGrant $a, PlanGrant $b) => $a->pastPapers->hasLimit() <=> $b->pastPapers->hasLimit());

        foreach ($grants as $grant) {
            if (! $grant->pastPapers->hasLimit() || $this->pastPaperUsageOf($user, $grant) < $grant->pastPapers->limit) {
                return $grant;
            }
        }

        $nextAt = collect($grants)->map(fn (PlanGrant $grant) => $this->nextPastPaperAt($user, $grant))->filter()->min();

        $this->deny($nextAt
            ? __('You have used all the past papers your plan allows for now. You can take the next one from :date.', ['date' => $nextAt->translatedFormat('j M Y, g:i A')])
            : __('You have used all the past papers your plan allows for now.'));
    }

    /**
     * Whether any of the grants lets this past paper be sat (or set) with
     * these features — for marking which papers in a list are open to the
     * user, from grants read once for the whole list.
     *
     * @param  array<int, PlanGrant>  $grants
     * @param  array<int, PlanFeature>  $features
     */
    public static function grantsAllowPastPaper(array $grants, BoardQuestionPaper $paper, array $features = []): bool
    {
        foreach ($grants as $grant) {
            if ($grant->allowsPastPaper($paper, $features)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the user may still use these features on an exam they already
     * have — a past paper's by what the plan reaches of past papers, any
     * other by its class + subject.
     *
     * @param  array<int, PlanFeature>  $features
     */
    public function allowsExam(User $user, Exam $exam, array $features = []): bool
    {
        return $exam->isPastPaper()
            ? self::grantsAllowPastPaper($this->grantsFor($user), $exam->boardQuestionPaper, $features)
            : $this->allows($user, $exam->classSubject, $features);
    }

    /**
     * How many past papers have been counted against a grant within its
     * rolling period (the last N days).
     */
    public function pastPaperUsageOf(User $user, PlanGrant $grant): int
    {
        return $this->countedPastPapers($user, $grant)->count();
    }

    /**
     * When the oldest paper in the period stops counting — the moment a
     * grant that has run out allows another. Null with no limit.
     */
    public function nextPastPaperAt(User $user, PlanGrant $grant): ?CarbonInterface
    {
        if (! $grant->pastPapers->hasLimit()) {
            return null;
        }

        $oldest = $this->countedPastPapers($user, $grant)->min('created_at');

        return $oldest ? Carbon::parse($oldest)->addDays($grant->pastPapers->limitDays) : null;
    }

    /**
     * The past-paper exams a grant's period counts: made by the user under
     * that grant within its last N days.
     */
    private function countedPastPapers(User $user, PlanGrant $grant): Builder
    {
        return Exam::query()
            ->where('created_by', $user->id)
            ->whereNotNull('board_question_paper_id')
            ->where('subscription_id', $grant->subscriptionId)
            ->where('created_at', '>', now()->subDays((int) $grant->pastPapers->limitDays));
    }

    /**
     * How many exams the user's plans allow this month in total: null when
     * any of them has no monthly cap, 0 with no plan at all.
     */
    public function monthlyAllowanceFor(User $user): ?int
    {
        $total = 0;

        foreach ($this->grantsFor($user) as $grant) {
            if ($grant->monthlyExamLimit === null) {
                return null;
            }

            $total += $grant->monthlyExamLimit;
        }

        return $total;
    }

    /**
     * The kind of exam a user's plan is spent on.
     */
    public function examTypeFor(User $user): ExamType
    {
        return $user->role === UserRole::Teacher ? ExamType::TeacherExam : ExamType::SelfPractice;
    }

    private function hasReachedLimit(User $user, PlanGrant $grant): bool
    {
        if (! $grant->hasLimits()) {
            return false;
        }

        $usage = $this->usageOf($user, $grant);

        return ($grant->dailyExamLimit !== null && $usage['today'] >= $grant->dailyExamLimit)
            || ($grant->monthlyExamLimit !== null && $usage['month'] >= $grant->monthlyExamLimit);
    }

    /**
     * @throws ValidationException
     */
    private function deny(string $message): never
    {
        throw ValidationException::withMessages(['plan' => $message]);
    }
}
