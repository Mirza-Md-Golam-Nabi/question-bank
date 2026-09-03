<?php

namespace App\Observers;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\User;

class QuestionObserver
{
    public function creating(Question $question): void
    {
        if (! $question->created_by) {
            $question->created_by = auth()->id();
        }

        if (! $question->status) {
            $question->status = $this->defaultStatusFor(auth()->user());
        }

        if ($question->status === QuestionStatus::Approved && ! $question->approved_by) {
            $question->approved_by = auth()->id();
        }

        // Eloquent doesn't reflect a column's DB-level ->default() back onto
        // the in-memory instance after create() without an extra refresh, so
        // relying on the migration's defaults alone leaves $question->version
        // / ->is_latest null until the next fetch. Setting them explicitly
        // here keeps every creation path (factories, Filament forms,
        // Question::createRevisionWith()) consistent without a round-trip.
        $question->version ??= 1;
        $question->is_latest ??= true;
    }

    private function defaultStatusFor(?User $user): QuestionStatus
    {
        $isAutoApproved = $user && in_array($user->role, [UserRole::SuperAdmin, UserRole::Admin], strict: true);

        return $isAutoApproved ? QuestionStatus::Approved : QuestionStatus::Pending;
    }
}
