<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Services\ExamResultSheet;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The printable result sheet of an exam — a plain page the teacher saves as
 * a PDF with the browser's own Print → "Save as PDF", or downloads as an
 * image (drawn in the browser, see resources/js/result-sheet-image.js).
 * Registered inside the Teacher panel's authenticated routes.
 */
class TeacherExamResultsPrintController extends Controller
{
    public function __invoke(Exam $exam, ExamResultSheet $resultSheet): View
    {
        Gate::authorize('viewResults', $exam);

        $exam->load(['classSubject.academicClass', 'subject']);

        return view('teacher.exam-results-print', [
            'exam' => $exam,
            'rows' => $resultSheet->rowsFor($exam),
        ]);
    }
}
