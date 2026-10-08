<?php

namespace App\Http\Controllers;

use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The printable question paper for an offline exam (CLAUDE.md rule 10) —
 * a plain page the teacher turns into a PDF with the browser's own
 * Print → "Save as PDF", in a "questions only" and a "with answers" form.
 * Registered inside the Teacher panel's authenticated routes.
 */
class TeacherExamPrintController extends Controller
{
    public function __invoke(Request $request, Exam $exam): View
    {
        Gate::authorize('print', $exam);

        abort_unless($exam->delivery_mode->includesOffline(), 404);

        $exam->load([
            'classSubject.academicClass',
            'subject',
            'questions.cqParts',
        ]);

        $questionsByType = $exam->questions->groupBy(fn (Question $question) => $question->question_type->value);

        return view('teacher.exam-print', [
            'exam' => $exam,
            'mcqQuestions' => $questionsByType->get(QuestionType::Mcq->value, collect()),
            'cqQuestions' => $questionsByType->get(QuestionType::Cq->value, collect()),
            'totalMarks' => $exam->questions->sum(fn (Question $question) => $question->pivot->marks_override ?? $question->marks),
            'showAnswers' => $request->boolean('answers'),
        ]);
    }
}
