<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\PlanFeature;
use App\Models\BillingSetting;
use App\Models\ClassSubject;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;

/**
 * What one source of access lets a user do: one active subscription (with
 * the conditions it was bought with and what the buyer picked), or the
 * role's free plan. A user's access is the sum of all of these — see
 * SubscriptionLimitService.
 */
final class PlanGrant
{
    /**
     * @param  array<int, int>|null  $classIds  Null = every class.
     * @param  array<int, int>|null  $subjectIds  Null = every subject.
     * @param  array<int, Difficulty>|null  $difficulties  Null = every difficulty.
     * @param  array<int, PlanFeature>|null  $features  Null = every feature.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?int $subscriptionId,
        public readonly ?Subscription $subscription,
        public readonly ?array $classIds,
        public readonly ?array $subjectIds,
        public readonly ?array $difficulties,
        public readonly ?array $features,
        public readonly ?int $dailyExamLimit,
        public readonly ?int $monthlyExamLimit,
        public readonly ?int $maxParticipants = null,
        public readonly ?string $sharedBy = null,
    ) {}

    /**
     * @param  User  $holder  Who the grant is for — when it isn't the buyer, the plan is shared with them.
     */
    public static function fromSubscription(Subscription $subscription, User $holder): self
    {
        return self::build(
            $holder,
            $subscription->plan?->name ?? __('Subscription'),
            $subscription,
            PlanConditions::fromArray($subscription->conditions),
            $subscription->selections ?? [],
        );
    }

    public static function fromFreePlan(SubscriptionPlan $plan, User $holder): self
    {
        return self::build($holder, $plan->name, null, $plan->planConditions(), []);
    }

    /**
     * A user who gave a phone number gets the Admin's bonus exams on top of
     * a monthly limit; an unlimited month stays unlimited.
     *
     * @param  array{class_ids?: array<int, int>, subject_ids?: array<int, int>}  $selections
     */
    private static function build(User $holder, string $name, ?Subscription $subscription, PlanConditions $conditions, array $selections): self
    {
        $phoneBonus = filled($holder->phone) ? BillingSetting::current()->phone_bonus_exams : 0;

        $classIds = $conditions->needsClassPick() ? ($selections['class_ids'] ?? []) : $conditions->classIds;
        $subjectIds = $conditions->needsSubjectPick() ? ($selections['subject_ids'] ?? []) : $conditions->subjectIds;

        return new self(
            name: $name,
            subscriptionId: $subscription?->id,
            subscription: $subscription,
            // Something to pick that was never picked reaches nothing,
            // rather than everything.
            classIds: $classIds === [] && ! $conditions->needsClassPick() ? null : array_map('intval', $classIds),
            subjectIds: $subjectIds === [] && ! $conditions->needsSubjectPick() ? null : array_map('intval', $subjectIds),
            difficulties: $conditions->difficulties,
            features: $conditions->features,
            dailyExamLimit: $conditions->dailyExamLimit,
            monthlyExamLimit: $conditions->monthlyExamLimit === null ? null : $conditions->monthlyExamLimit + $phoneBonus,
            maxParticipants: $conditions->maxParticipants,
            sharedBy: $subscription && $subscription->user_id !== $holder->id ? $subscription->user?->name : null,
        );
    }

    public function isFree(): bool
    {
        return $this->subscriptionId === null;
    }

    public function covers(ClassSubject $classSubject): bool
    {
        return ($this->classIds === null || in_array((int) $classSubject->academic_class_id, $this->classIds, true))
            && ($this->subjectIds === null || in_array((int) $classSubject->subject_id, $this->subjectIds, true));
    }

    public function coversEverything(): bool
    {
        return $this->classIds === null && $this->subjectIds === null;
    }

    /**
     * @param  array<int, Difficulty>  $difficulties
     */
    public function allowsDifficulties(array $difficulties): bool
    {
        return $this->difficulties === null
            || array_diff(array_map(fn (Difficulty $d) => $d->value, $difficulties), array_map(fn (Difficulty $d) => $d->value, $this->difficulties)) === [];
    }

    /**
     * @param  array<int, PlanFeature>  $features
     */
    public function allowsFeatures(array $features): bool
    {
        return $this->features === null
            || array_diff(array_map(fn (PlanFeature $f) => $f->value, $features), array_map(fn (PlanFeature $f) => $f->value, $this->features)) === [];
    }

    public function hasLimits(): bool
    {
        return $this->dailyExamLimit !== null || $this->monthlyExamLimit !== null;
    }

    /**
     * What this grant reaches and allows, one line each — what the buyer
     * picked shown as fixed, since it is by now.
     *
     * @return array<int, string>
     */
    public function descriptions(): array
    {
        return (new PlanConditions(
            classIds: $this->classIds ?? [],
            subjectIds: $this->subjectIds ?? [],
            difficulties: $this->difficulties,
            features: $this->features,
            dailyExamLimit: $this->dailyExamLimit,
            monthlyExamLimit: $this->monthlyExamLimit,
            maxParticipants: $this->maxParticipants,
        ))->descriptions();
    }
}
