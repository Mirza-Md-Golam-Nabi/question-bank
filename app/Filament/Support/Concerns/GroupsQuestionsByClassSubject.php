<?php

namespace App\Filament\Support\Concerns;

use App\Enums\QuestionStatus;
use App\Models\Question;
use Illuminate\Database\Eloquent\Collection;
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
        return Question::where('created_by', Auth::id())
            ->where('status', $status)
            ->where('is_latest', true)
            ->with(['chapter.classSubject.subject', 'chapter.classSubject.academicClass'])
            ->get()
            ->groupBy(fn (Question $question) => $question->chapter->class_subject_id ?? 0)
            ->map(function (Collection $questions) {
                $classSubject = $questions->first()->chapter->classSubject;

                return [
                    'class' => $classSubject?->academicClass->name ?? __('Unknown'),
                    'subject' => $classSubject?->subject->name ?? __('Unknown'),
                    'count' => $questions->count(),
                ];
            })
            ->sortBy(['class', 'subject'])
            ->values();
    }
}
