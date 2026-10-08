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
     * An exam counts against the limit from the moment it is saved, so the
     * question here is whether this exam was one of the month's allowed ones.
     */
    public function publish(User $user, Exam $exam): bool
    {
        return $exam->created_by === $user->id
            && $this->subscriptionLimits->isWithinMonthlyAllowance($user, $exam);
    }

    /**
     * The printable question paper (with or without answers) is only ever
     * for the teacher who built the exam.
     */
    public function print(User $user, Exam $exam): bool
    {
        return $exam->created_by === $user->id;
    }

    /**
     * Students' names, contacts and marks — only for the teacher whose
     * exam it is.
     */
    public function viewResults(User $user, Exam $exam): bool
    {
        return $exam->created_by === $user->id;
    }

    public function createSelfPractice(User $user): bool
    {
        return ! $this->subscriptionLimits->hasReachedMonthlyLimit($user, ExamType::SelfPractice);
    }
}
