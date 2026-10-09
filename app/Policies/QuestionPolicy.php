<?php

namespace App\Policies;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Question $question): bool
    {
        return $this->isOwner($user, $question) || $this->isVisibleApproved($question) || $this->isAdmin($user);
    }

    /**
     * A suspended Teacher/Staff keeps read access to their existing
     * questions but may not add new ones.
     */
    public function create(User $user): bool
    {
        return $user->status === UserStatus::Active;
    }

    /**
     * Adding many questions at once from pasted JSON. Staff are paid for
     * every approved question, so they keep adding theirs one at a time.
     */
    public function import(User $user): bool
    {
        return $this->create($user) && $user->role !== UserRole::Staff;
    }

    /**
     * Nobody — an Admin included — edits a question in place once a
     * student has sat it (Question::isFrozenByAttempts()).
     */
    public function update(User $user, Question $question): bool
    {
        return ($this->isAdmin($user) || $this->isOwner($user, $question))
            && ! $question->isFrozenByAttempts();
    }

    /**
     * Its Teacher or Staff owner can remove a question only while nothing
     * depends on it: once approved it belongs to the shared pool, and
     * while it is on an exam — a teacher may put their own pending
     * questions on theirs — removing it would take it off that paper.
     * Only an Admin can remove it then.
     */
    public function delete(User $user, Question $question): bool
    {
        return $this->isAdmin($user) || (
            $this->isOwner($user, $question)
            && $question->status !== QuestionStatus::Approved
            && ! $question->isOnAnExam()
        );
    }

    public function restore(User $user, Question $question): bool
    {
        return $this->isAdmin($user) || $this->isOwner($user, $question);
    }

    public function restoreAny(User $user): bool
    {
        return true;
    }

    /**
     * Permanent, unrecoverable removal — Admin-only regardless of ownership,
     * unlike the soft `delete` above.
     */
    public function forceDelete(User $user, Question $question): bool
    {
        return $this->isAdmin($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function approve(User $user, Question $question): bool
    {
        return $this->isAdmin($user);
    }

    public function reject(User $user, Question $question): bool
    {
        return $this->isAdmin($user);
    }

    private function isOwner(User $user, Question $question): bool
    {
        return $question->created_by === $user->id;
    }

    private function isVisibleApproved(Question $question): bool
    {
        return $question->status === QuestionStatus::Approved && $question->is_latest;
    }

    private function isAdmin(User $user): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Admin], strict: true);
    }
}
