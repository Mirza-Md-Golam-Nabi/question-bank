<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BillingSetting;
use App\Models\BoardQuestionPaper;
use App\Models\User;

/**
 * Past papers are entered by an Admin or a Staff member (who is paid per
 * approved paper) and reviewed by an Admin. Which papers a panel lists is
 * its resource's getEloquentQuery(); this decides what may be done to one.
 */
class BoardQuestionPaperPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->isAdmin() || $user->role === UserRole::Staff;
    }

    public function view(User $user, BoardQuestionPaper $paper): bool
    {
        return $user->role->isAdmin() || $paper->created_by === $user->id;
    }

    /**
     * A suspended Staff member keeps their papers but adds no new ones —
     * as with questions.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user) && $user->status === UserStatus::Active;
    }

    public function update(User $user, BoardQuestionPaper $paper): bool
    {
        return $paper->isEditableBy($user);
    }

    /**
     * Never once an exam has been made of it — the exam would lose its
     * questions.
     */
    public function delete(User $user, BoardQuestionPaper $paper): bool
    {
        return $paper->isEditableBy($user) && ! $paper->exams()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return $user->role->isAdmin();
    }

    /**
     * Adding a paper's questions from pasted JSON: an Admin always can; Staff
     * only while the Admin has switched it on for them all — and only into a
     * paper they may change at all.
     */
    public function import(User $user, BoardQuestionPaper $paper): bool
    {
        return $paper->isEditableBy($user)
            && $user->status === UserStatus::Active
            && ($user->role->isAdmin() || ($user->role === UserRole::Staff && BillingSetting::current()->staff_paper_import_enabled));
    }

    public function approve(User $user, BoardQuestionPaper $paper): bool
    {
        return $user->role->isAdmin();
    }

    public function reject(User $user, BoardQuestionPaper $paper): bool
    {
        return $user->role->isAdmin();
    }
}
