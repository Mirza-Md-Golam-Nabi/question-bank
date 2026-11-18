<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Services\ExamResultSheet;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An exam's result sheet as a CSV file — to open in Excel or put in another
 * program (a Teacher has no school software to send the marks to). The same
 * ranked rows as the results page (ExamResultSheet::csvRows()). Registered
 * inside the Teacher and School panels' authenticated routes.
 */
class TeacherExamResultsCsvController extends Controller
{
    public function __invoke(Exam $exam, ExamResultSheet $resultSheet): StreamedResponse
    {
        Gate::authorize('viewResults', $exam);

        $rows = $resultSheet->csvRows($exam);

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            // Excel reads Bangla right only with the UTF-8 mark first.
            fwrite($output, "\u{FEFF}");

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, (Str::slug($exam->title) ?: 'exam-'.$exam->id).'-results.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
