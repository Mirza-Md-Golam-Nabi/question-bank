<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Filament\Student\Pages\TakeExamPage;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Login to attempt" on an exam's share link (CLAUDE.md rule 8): takes a
 * student from the link, through Google login if they aren't logged in yet,
 * straight into the exam — without them having to find the link again
 * afterwards.
 *
 * Visited twice in that case: first logged out (it remembers the exam and
 * sends the student to Google), then again after the login callback (it
 * starts the attempt). An already logged-in student only ever makes the
 * second visit.
 */
class StudentSharedExamController extends Controller
{
    /**
     * Session key holding the share token of the exam to open after login;
     * GoogleAuthController reads it once the student is logged in.
     */
    public const SESSION_KEY = 'exam_to_join_after_login';

    public function __invoke(Request $request, string $shareToken): RedirectResponse
    {
        $exam = Exam::findPublishedByShareToken($shareToken);

        // A closed or unknown link: the share page explains it.
        if (! $exam?->isAcceptingAttempts()) {
            return redirect()->route('guest-exam.show', $shareToken);
        }

        $user = $request->user();

        if ($user?->role !== UserRole::Student) {
            $request->session()->put(self::SESSION_KEY, $shareToken);

            return redirect()->route('auth.google.redirect', UserRole::Student->value);
        }

        // Into the exam — or, for a student who has already sat it, to
        // their result: nobody gets a second go.
        return redirect()->to(TakeExamPage::urlFor(ExamAttempt::startFor($exam, $user)));
    }
}
