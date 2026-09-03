<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\BoardQuestionPaper;
use App\Models\User;

class BoardQuestionPaperPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function approve(User $user, BoardQuestionPaper $paper): bool
    {
        return $this->isAdmin($user);
    }

    public function reject(User $user, BoardQuestionPaper $paper): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Admin], strict: true);
    }
}
