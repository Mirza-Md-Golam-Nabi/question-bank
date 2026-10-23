<?php

namespace App\Services;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\GuidePage;
use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Models\BillingSetting;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\SchoolResultPush;
use App\Models\User;

/**
 * The dashboard's "Get started" list (CLAUDE.md rule 14): the first few
 * things a new user of each role does, each ticked off by itself once done
 * — read from what they have actually done, nothing stored — with where to
 * do it and the page whose guides explain it. Gone once all are done, when
 * the user closes it, or when an Admin switches it off.
 */
class OnboardingChecklist
{
    /**
     * @return array<int, array{label: string, done: bool, url: string, page: GuidePage|null}>|null
     */
    public function for(User $user): ?array
    {
        if ($user->onboarding_dismissed_at || ! BillingSetting::current()->onboarding_checklist_enabled) {
            return null;
        }

        $steps = match ($user->role) {
            UserRole::Student => $this->studentSteps($user),
            UserRole::Teacher => $this->teacherSteps($user),
            UserRole::Staff => $this->staffSteps($user),
            UserRole::School => $this->schoolSteps($user),
            default => [],
        };

        return $steps === [] || collect($steps)->every(fn (array $step): bool => $step['done']) ? null : $steps;
    }

    public function dismiss(User $user): void
    {
        $user->forceFill(['onboarding_dismissed_at' => now()])->save();
    }

    /**
     * @return array<int, array{label: string, done: bool, url: string, page: GuidePage|null}>
     */
    private function studentSteps(User $student): array
    {
        return [
            $this->step(__('Choose your class'), $student->academic_class_id !== null, 'filament.student.pages.my-profile'),
            $this->step(
                __('Take your first practice exam'),
                ExamAttempt::query()->where('student_id', $student->id)->submitted()
                    ->whereHas('exam', fn ($exam) => $exam->where('exam_type', ExamType::SelfPractice))->exists(),
                'filament.student.pages.generate-practice-exam',
                GuidePage::Practice,
            ),
            $this->step(__('Choose a plan'), $this->hasOwnPlan($student), 'filament.student.pages.my-subscription', GuidePage::Subscription),
        ];
    }

    /**
     * @return array<int, array{label: string, done: bool, url: string, page: GuidePage|null}>
     */
    private function teacherSteps(User $teacher): array
    {
        return [
            $this->step(__('Start your trial or choose a plan'), $this->hasOwnPlan($teacher), 'filament.teacher.pages.my-subscription', GuidePage::Subscription),
            $this->step(__('Write your first question'), $this->hasWrittenAQuestion($teacher), 'filament.teacher.resources.questions.index', GuidePage::WriteQuestions),
            $this->step(__('Build your first exam'), $this->hasBuiltAnExam($teacher), 'filament.teacher.pages.select-questions', GuidePage::BuildExam),
            $this->step(__('Share an exam link with your students'), $this->hasPublishedAnExam($teacher), 'filament.teacher.resources.exams.index', GuidePage::MyExams),
        ];
    }

    /**
     * @return array<int, array{label: string, done: bool, url: string, page: GuidePage|null}>
     */
    private function staffSteps(User $staff): array
    {
        $profile = $staff->staffProfile;

        return [
            $this->step(
                __('Add how you want to be paid'),
                $profile !== null && (filled($profile->bank_account_number) || filled($profile->mobile_banking_number)),
                'filament.staff.pages.my-profile',
                GuidePage::StaffEarnings,
            ),
            $this->step(__('Write your first question'), $this->hasWrittenAQuestion($staff), 'filament.staff.resources.questions.index', GuidePage::WriteQuestions),
            $this->step(
                __('Get your first question approved'),
                Question::query()->ownedBy($staff)->where('status', QuestionStatus::Approved)->exists(),
                'filament.staff.pages.my-earnings',
                GuidePage::StaffEarnings,
            ),
        ];
    }

    /**
     * @return array<int, array{label: string, done: bool, url: string, page: GuidePage|null}>
     */
    private function schoolSteps(User $school): array
    {
        return [
            $this->step(__('Connect your school software'), (bool) $school->schoolProfile?->isConnected(), 'filament.school.pages.school-connection', GuidePage::SchoolConnection),
            $this->step(__('Write your first question'), $this->hasWrittenAQuestion($school), 'filament.school.resources.questions.index', GuidePage::WriteQuestions),
            $this->step(__('Build your first exam'), $this->hasBuiltAnExam($school), 'filament.school.pages.select-questions', GuidePage::BuildExam),
            $this->step(
                __('Send marks to the school results'),
                SchoolResultPush::query()->where('sent_by', $school->id)->exists(),
                'filament.school.resources.exams.index',
                GuidePage::SchoolResults,
            ),
        ];
    }

    /**
     * @return array{label: string, done: bool, url: string, page: GuidePage|null}
     */
    private function step(string $label, bool $done, string $route, ?GuidePage $page = null): array
    {
        return ['label' => $label, 'done' => $done, 'url' => route($route), 'page' => $page];
    }

    /**
     * A plan of their own, bought or a trial — the role's free plan isn't one.
     */
    private function hasOwnPlan(User $user): bool
    {
        return $user->activeSubscriptions()->isNotEmpty();
    }

    private function hasWrittenAQuestion(User $user): bool
    {
        return Question::query()->ownedBy($user)->exists();
    }

    private function hasBuiltAnExam(User $user): bool
    {
        return Exam::query()->where('created_by', $user->id)->where('exam_type', ExamType::TeacherExam)->exists();
    }

    private function hasPublishedAnExam(User $user): bool
    {
        return Exam::query()->where('created_by', $user->id)->where('exam_type', ExamType::TeacherExam)->where('status', ExamStatus::Published)->exists();
    }
}
