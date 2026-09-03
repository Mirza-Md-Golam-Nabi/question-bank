<?php

namespace App\Policies;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
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

    public function update(User $user, Question $question): bool
    {
        return $this->isAdmin($user) || $this->isOwner($user, $question);
    }

    public function delete(User $user, Question $question): bool
    {
        return $this->isAdmin($user) || $this->isOwner($user, $question);
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
