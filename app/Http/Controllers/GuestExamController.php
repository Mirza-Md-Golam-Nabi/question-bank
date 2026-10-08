<?php

namespace App\Http\Controllers;

use App\Enums\ExamAttemptStatus;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The share-link entry point (CLAUDE.md rule 8) — deliberately outside every
 * Filament panel's auth boundary, since a guest is by definition
 * unauthenticated. Only ever reachable via the exam's own share_token, never
 * an enumerable ID.
 */
class GuestExamController extends Controller
{
    /**
     * Session list of the guest attempt ids started from this browser.
     */
    private const SESSION_KEY = 'guest_exam_attempts';

    public function show(string $shareToken): View|RedirectResponse
    {
        $exam = $this->activeSharedExamOrFail($shareToken);

        if (! $exam) {
            // A closed link no longer starts attempts, but those who sat
            // the exam can still come back to it for their result.
            return view('guest-exam.inactive', ['exam' => Exam::findPublishedByShareToken($shareToken)]);
        }

        return view('guest-exam.join', ['exam' => $exam]);
    }

    /**
     * How a guest gets back to their result (CLAUDE.md rule 8): with no
     * account, the name and phone/email they sat the exam with are what
     * identify them. Works after the link has closed too — that is exactly
     * when a teacher tends to release the answers.
     */
    public function findResult(Request $request, string $shareToken): View|RedirectResponse
    {
        $exam = Exam::findPublishedByShareToken($shareToken);

        abort_if(! $exam, 404);

        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_contact' => ['required', 'string', 'max:255'],
        ]);

        $attempt = ExamAttempt::findGuestResult($exam, $data['guest_name'], $data['guest_contact']);

        if (! $attempt) {
            return back()
                ->withInput()
                ->withErrors(['result' => __('No submitted exam was found for this name and phone/email. Enter them exactly as you did when starting the exam.')]);
        }

        return view('guest-exam.result', ['attempt' => $attempt->load('exam', 'answers.question')]);
    }

    /**
     * Starts the attempt and redirects to its own page, rather than
     * rendering the exam straight from this POST — otherwise a page refresh
     * would re-send the form, start a brand-new attempt and restart the
     * clock.
     */
    public function startGuestAttempt(Request $request, string $shareToken): RedirectResponse
    {
        $exam = $this->activeSharedExamOrFail($shareToken);

        abort_if(! $exam, 404);

        // Going back and pressing "Start" again resumes the attempt already
        // under way in this browser, clock and all.
        $attemptInProgress = $this->guestAttemptsOfThisBrowser($request)
            ->where('exam_id', $exam->id)
            ->where('status', ExamAttemptStatus::InProgress)
            ->latest('id')
            ->first();

        if ($attemptInProgress) {
            return redirect()->route('guest-exam.take', $attemptInProgress);
        }

        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:255'],
            // Required: together with the name it is the guest's only way
            // back to their result once the answers are released.
            'guest_contact' => ['required', 'string', 'max:255'],
        ]);

        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => null,
            'is_guest' => true,
            'guest_name' => trim($data['guest_name']),
            'guest_contact' => ExamAttempt::normalizeGuestContact($data['guest_contact']),
            'started_at' => now(),
        ]);

        $request->session()->push(self::SESSION_KEY, $attempt->id);

        return redirect()->route('guest-exam.take', $attempt);
    }

    /**
     * The exam page of one guest attempt. A guest has no account, so the
     * attempt belongs to whichever browser started it — its id alone (which
     * is guessable) is never enough to open someone else's exam.
     */
    public function take(Request $request, ExamAttempt $attempt): View|RedirectResponse
    {
        abort_unless($this->guestAttemptsOfThisBrowser($request)->whereKey($attempt->id)->exists(), 403);

        $attempt->load('exam');

        if ($attempt->status !== ExamAttemptStatus::InProgress) {
            return redirect()->route('guest-exam.show', $attempt->exam->share_token);
        }

        return view('guest-exam.take', [
            'attempt' => $attempt,
            // Whatever is already on record (saved as the clock ran out).
            'savedAnswers' => $attempt->answers()->pluck('student_answer', 'question_id'),
        ]);
    }

    public function submit(Request $request, ExamAttempt $attempt): View
    {
        abort_if(! $attempt->is_guest, 403);
        abort_if($attempt->status !== ExamAttemptStatus::InProgress, 403);

        $attempt->recordAnswers((array) $request->input('answers', []));

        $attempt->submitAndAutoGrade();

        return view('guest-exam.result', ['attempt' => $attempt->load('answers.question')]);
    }

    /**
     * Called by the exam page itself the moment the clock runs out, to put
     * the answers on record before the page locks — so a late submit has
     * nothing left to change (see ExamAttempt::recordAnswers()).
     */
    public function saveAnswers(Request $request, ExamAttempt $attempt): Response
    {
        abort_if(! $attempt->is_guest, 403);
        abort_if($attempt->status !== ExamAttemptStatus::InProgress, 403);

        $attempt->recordAnswers((array) $request->input('answers', []));

        return response()->noContent();
    }

    /**
     * @return Builder<ExamAttempt>
     */
    private function guestAttemptsOfThisBrowser(Request $request): Builder
    {
        return ExamAttempt::query()
            ->where('is_guest', true)
            ->whereIn('id', (array) $request->session()->get(self::SESSION_KEY, []));
    }

    /**
     * The exam behind a share token, only while its link still takes new
     * attempts.
     */
    private function activeSharedExamOrFail(string $shareToken): ?Exam
    {
        $exam = Exam::findPublishedByShareToken($shareToken);

        return $exam?->isAcceptingAttempts() ? $exam : null;
    }
}
