<?php

namespace App\Policies;

use App\Enums\ExamType;
use App\Models\Exam;
use App\Models\User;
use App\Services\SubscriptionLimitService;

class ExamPolicy
{
    public function __construct(private readonly SubscriptionLimitService $subscriptionLimits) {}

    public function update(User $user, Exam $exam): bool
    {
        return $exam->created_by === $user->id;
    }

    public function delete(User $user, Exam $exam): bool
    {
        return $exam->created_by === $user->id;
    }

    /**
     * Backs CLAUDE.md rule 7: publishing (Teacher) or generating (Student
     * self-practice) is blocked once the active plan's monthly free-limit is
     * reached — a fresh count every time, never a cached/stored counter.
     */
    public function publish(User $user, Exam $exam): bool
    {
        return $exam->created_by === $user->id
            && ! $this->subscriptionLimits->hasReachedMonthlyLimit($user, $exam->exam_type);
    }

    public function createSelfPractice(User $user): bool
    {
        return ! $this->subscriptionLimits->hasReachedMonthlyLimit($user, ExamType::SelfPractice);
    }
}
