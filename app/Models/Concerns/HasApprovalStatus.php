<?php

namespace App\Models\Concerns;

use App\Enums\QuestionStatus;
use App\Models\User;

/**
 * The approve / reject status change shared by everything an admin reviews
 * (a question, a board question paper): the same three columns move
 * together either way. Anything a model does on top of that — an approval
 * log, a staff earning — stays in that model.
 */
trait HasApprovalStatus
{
    protected function markApproved(User $approver): void
    {
        $this->forceFill([
            'status' => QuestionStatus::Approved,
            'approved_by' => $approver->id,
            'rejection_reason' => null,
        ])->save();
    }

    protected function markRejected(string $reason): void
    {
        $this->forceFill([
            'status' => QuestionStatus::Rejected,
            'approved_by' => null,
            'rejection_reason' => $reason,
        ])->save();
    }
}
