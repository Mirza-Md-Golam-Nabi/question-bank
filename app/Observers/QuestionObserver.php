<?php

namespace App\Observers;

use App\Enums\DuplicateCheckStatus;
use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\User;
use App\Services\QuestionDuplicateScreening;

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

        // What a School writes is its own (CLAUDE.md rule 12) — a new
        // version keeps what its first one was.
        $question->is_private ??= $this->creatorOf($question)?->role === UserRole::School;

        // Checked for duplicates in the background before it can be used
        // (QuestionDuplicateScreening) — but a School's own, never.
        $question->duplicate_status ??= $question->is_private ? DuplicateCheckStatus::Clear : DuplicateCheckStatus::Checking;

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

    public function created(Question $question): void
    {
        if ($question->duplicate_status === DuplicateCheckStatus::Checking) {
            QuestionDuplicateScreening::queue($question);
        }
    }

    /**
     * A change to what the question asks or answers is checked again; its
     * CQ parts and its stimulus ask for it themselves (QuestionCqPart,
     * QuestionStimulus).
     */
    public function updated(Question $question): void
    {
        if ($question->wasChanged(['question_text', 'question_image', 'options', 'question_type', 'chapter_id', 'stimulus_id'])) {
            QuestionDuplicateScreening::queue($question);
        }
    }

    /**
     * Who wrote it — the signed-in user in nearly every case, so no query.
     */
    private function creatorOf(Question $question): ?User
    {
        return $question->created_by === auth()->id() ? auth()->user() : $question->creator;
    }

    private function defaultStatusFor(?User $user): QuestionStatus
    {
        $isAutoApproved = $user && $user->role->isAdmin();

        return $isAutoApproved ? QuestionStatus::Approved : QuestionStatus::Pending;
    }
}
