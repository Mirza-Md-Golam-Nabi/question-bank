<?php

namespace App\Policies;

use App\Enums\PlanFeature;
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
     * Backs CLAUDE.md rule 7: sharing the exam online needs a plan that
     * covers its class + subject and includes online exams. It was already
     * counted against the plan's limits when it was saved, so publishing
     * it doesn't count it again.
     */
    public function publish(User $user, Exam $exam): bool
    {
        return $exam->created_by === $user->id
            && $this->subscriptionLimits->allows($user, $exam->classSubject, [PlanFeature::OnlineExam]);
    }

    /**
     * The printable question paper (with or without answers) is only ever
     * for the teacher who built the exam, and only while their plan
     * includes printing for its class + subject.
     */
    public function print(User $user, Exam $exam): bool
    {
        return $exam->created_by === $user->id
            && $this->subscriptionLimits->allows($user, $exam->classSubject, [PlanFeature::Print]);
    }

    /**
     * Students' names, contacts and marks — only for the teacher whose
     * exam it is.
     */
    public function viewResults(User $user, Exam $exam): bool
    {
        return $exam->created_by === $user->id;
    }
}
