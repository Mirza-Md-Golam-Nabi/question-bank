<?php

namespace App\Models\Concerns;

use App\Enums\QuestionStatus;
use App\Models\User;

/**
 * The approve / reject status change shared by everything an admin reviews
 * (a question, a board question paper): the same columns move together
 * either way — `approved_at` too, the day it joined the question bank
 * (QuestionBankGrowth). Anything a model does on top of that — an approval
 * log, a staff earning — stays in that model.
 */
trait HasApprovalStatus
{
    protected function markApproved(User $approver): void
    {
        $this->forceFill([
            'status' => QuestionStatus::Approved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ])->save();
    }

    protected function markRejected(string $reason): void
    {
        $this->forceFill([
            'status' => QuestionStatus::Rejected,
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => $reason,
        ])->save();
    }
}
