<?php

namespace App\Filament\Support\Concerns;

use App\Enums\QuestionStatus;
use App\Models\ClassSubject;
use App\Models\Question;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by the "Questions Pending"/"Questions Approved" drill-down pages:
 * how many of this staff member's own questions, at a given status, belong
 * to each class + subject. Grouped by class_subject_id (not just subject
 * name) — the same subject can belong to more than one class, each with its
 * own chapter set (CLAUDE.md: `class_subjects` pivot, not a
 * class-independent subject).
 */
trait GroupsQuestionsByClassSubject
{
    /**
     * @return SupportCollection<int, array{class: string, subject: string, count: int}>
     */
    protected function classSubjectBreakdown(QuestionStatus $status): SupportCollection
    {
        // Counted by the database: the questions themselves (their text
        // and options) are never loaded just to be counted.
        $counts = Question::query()
            ->where('questions.created_by', Auth::id())
            ->where('questions.status', $status)
            ->where('questions.is_latest', true)
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->groupBy('chapters.class_subject_id')
            ->selectRaw('chapters.class_subject_id, count(*) as questions_count')
            ->toBase()
            ->pluck('questions_count', 'class_subject_id')
            ->map(fn (int|string $count): array => ['count' => (int) $count]);

        return ClassSubject::describe($counts);
    }
}
