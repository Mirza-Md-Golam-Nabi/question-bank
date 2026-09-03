<?php

namespace App\Http\Controllers;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The share-link entry point (CLAUDE.md rule 8) — deliberately outside every
 * Filament panel's auth boundary, since a guest is by definition
 * unauthenticated. Only ever reachable via the exam's own share_token, never
 * an enumerable ID.
 */
class GuestExamController extends Controller
{
    public function show(string $shareToken): View|RedirectResponse
    {
        $exam = $this->activeSharedExamOrFail($shareToken);

        if (! $exam) {
            return view('guest-exam.inactive');
        }

        return view('guest-exam.join', ['exam' => $exam]);
    }

    public function startGuestAttempt(Request $request, string $shareToken): View
    {
        $exam = $this->activeSharedExamOrFail($shareToken);

        abort_if(! $exam, 404);

        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_contact' => ['nullable', 'string', 'max:255'],
        ]);

        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => null,
            'is_guest' => true,
            'guest_name' => $data['guest_name'],
            'guest_contact' => $data['guest_contact'] ?? null,
            'started_at' => now(),
        ]);

        return view('guest-exam.take', ['attempt' => $attempt->load('exam.questions')]);
    }

    public function submit(Request $request, ExamAttempt $attempt): View
    {
        abort_if(! $attempt->is_guest, 403);
        abort_if($attempt->status !== ExamAttemptStatus::InProgress, 403);

        $answers = $request->input('answers', []);

        foreach ($answers as $questionId => $studentAnswer) {
            $attempt->answers()->updateOrCreate(
                ['question_id' => $questionId],
                ['student_answer' => $studentAnswer],
            );
        }

        $attempt->submitAndAutoGrade();

        return view('guest-exam.result', ['attempt' => $attempt->load('answers.question')]);
    }

    private function activeSharedExamOrFail(string $shareToken): ?Exam
    {
        $exam = Exam::where('share_token', $shareToken)->first();

        if (! $exam || $exam->status !== ExamStatus::Published || ! $exam->is_link_active) {
            return null;
        }

        if ($exam->link_expires_at && $exam->link_expires_at->isPast()) {
            return null;
        }

        return $exam;
    }
}
