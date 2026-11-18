<?php

namespace App\Http\Controllers;

use App\Enums\QuestionType;
use App\Models\BillingSetting;
use App\Models\Exam;
use App\Models\InstitutionName;
use App\Models\Question;
use App\Services\InstitutionNameService;
use App\Support\PrintSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The printable question paper for an offline exam (CLAUDE.md rule 10) —
 * a plain page the teacher turns into a PDF with the browser's own
 * Print → "Save as PDF", in four forms: "questions only", "with answers"
 * (`?answers=1`), the answer key alone (`?answers=key` — a table of MCQ
 * numbers and the letters of their right options; CQ has no answers) and
 * an OMR answer sheet for the students (`?answers=omr` — a circle per
 * option of every MCQ, and a roll number grid).
 * The same for an exam made of a past paper, whose questions are the
 * paper's; an OMR check (rule 10.12) has only its sheet and its key. Laid out by the user's own print settings (PrintSettings), with
 * one of their usable institution names (and its logo) on top
 * (InstitutionNameService).
 * Registered inside the Teacher and School panels' authenticated routes.
 */
class TeacherExamPrintController extends Controller
{
    public function __invoke(Request $request, Exam $exam, InstitutionNameService $institutionNames): View
    {
        Gate::authorize('print', $exam);

        abort_unless($exam->delivery_mode->includesOffline(), 404);

        $exam->load(['classSubject.academicClass', 'subject', 'boardQuestionPaper.board']);

        $questions = $exam->printedItems();
        $questionsByType = $questions->groupBy(fn (Model $question) => $question->question_type->value);
        $mcqQuestions = $questionsByType->get(QuestionType::Mcq->value, collect());
        $isAnswerKey = $request->query('answers') === 'key';
        $isOmrSheet = $request->query('answers') === 'omr';

        abort_if(($isAnswerKey || $isOmrSheet) && $mcqQuestions->isEmpty(), 404);
        // An OMR check has no question paper: only its sheet and its key.
        abort_if($exam->isOmrCheck() && ! $isAnswerKey && ! $isOmrSheet, 404);

        $settings = BillingSetting::current();
        $usableNames = $institutionNames->usableNamesFor($request->user());

        return view('teacher.exam-print', [
            'exam' => $exam,
            'mcqQuestions' => $mcqQuestions,
            'cqQuestions' => $questionsByType->get(QuestionType::Cq->value, collect()),
            'totalMarks' => $exam->marksOf($questions),
            'isAnswerKey' => $isAnswerKey,
            'isOmrSheet' => $isOmrSheet,
            'showAnswers' => ! $isAnswerKey && ! $isOmrSheet && $request->boolean('answers'),
            'printSettings' => PrintSettings::for($request->user()),
            'institutionNames' => $usableNames->pluck('name', 'id'),
            'institutionLogos' => $usableNames->filter->logo_path->mapWithKeys(fn (InstitutionName $name): array => [$name->id => $name->logoUrl()]),
            'defaultInstructions' => [
                'mcq' => (string) $settings->print_mcq_instructions,
                'cq' => (string) $settings->print_cq_instructions,
            ],
            'chapterNames' => $this->chapterNamesOf($questions),
        ]);
    }

    /**
     * The chapters the paper's questions come from, in the order they first
     * appear — none for a past paper's, which have no chapter.
     *
     * @param  Collection<int, Model>  $questions
     * @return list<string>
     */
    private function chapterNamesOf(Collection $questions): array
    {
        $bankQuestions = new Collection($questions->whereInstanceOf(Question::class)->all());

        return $bankQuestions->load('chapter:id,name')
            ->pluck('chapter.name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
