<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\SchoolProfile;
use App\Services\SchoolRoster;
use App\Support\GuestAttemptSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * The share link of a School's exam (CLAUDE.md rule 12): instead of a
 * login or a guest's name, the School's own form — class and section from
 * its school management software, roll, name. No account and no
 * subscription; once in, the exam itself is the guest's (take, save,
 * submit — GuestExamController). Outside every panel's auth, like the
 * guest flow.
 */
class SchoolExamEntryController extends Controller
{
    public function __construct(private readonly SchoolRoster $roster) {}

    /**
     * The entry form — or, when the school's class list can't be had right
     * now, a page that says so.
     */
    public function show(Exam $exam): View
    {
        try {
            $classes = $this->roster->classes($this->schoolOf($exam));
        } catch (RuntimeException) {
            return view('guest-exam.school-unavailable', ['exam' => $exam]);
        }

        return view('guest-exam.school-join', ['exam' => $exam, 'classes' => $classes]);
    }

    /**
     * The school's classes for the result lookup of a closed link — none
     * when they can't be had right now.
     *
     * @return list<array<string, mixed>>|null
     */
    public function classesOrNull(Exam $exam): ?array
    {
        try {
            return $this->roster->classes($this->schoolOf($exam));
        } catch (RuntimeException) {
            return null;
        }
    }

    public function start(Request $request, string $shareToken): View|RedirectResponse
    {
        $exam = GuestExamController::activeSharedExamOrFail($shareToken);

        abort_unless($exam?->isSchoolExam(), 404);

        $data = $request->validate([
            'class_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'roll' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $picked = $this->pick($exam, $data);

        try {
            $attempt = ExamAttempt::startForSchoolStudent($exam, $picked['class'], $picked['section'], $data['roll'], $data['name']);
        } catch (ValidationException $exception) {
            // The exam is full.
            return back()->withInput()->withErrors($exception->errors());
        }

        if (! $attempt->isInProgress()) {
            return view('guest-exam.result', ['attempt' => $attempt->load('answers.question'), 'alreadyTaken' => true]);
        }

        GuestAttemptSession::remember($request, $attempt);

        return redirect()->route('guest-exam.take', $attempt);
    }

    /**
     * A School's student gets back to their result with their class,
     * section and roll — also after the link has closed.
     */
    public function findResult(Request $request, string $shareToken): View|RedirectResponse
    {
        $exam = Exam::findPublishedByShareToken($shareToken);

        abort_unless($exam?->isSchoolExam(), 404);

        $data = $request->validate([
            'class_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'roll' => ['required', 'string', 'max:20'],
        ]);

        $picked = $this->pick($exam, $data, errorBag: 'result');
        $attempt = ExamAttempt::findSchoolResult($exam, $picked['class']['id'], $picked['section']['id'] ?? null, $data['roll']);

        if (! $attempt) {
            return back()
                ->withInput()
                ->withErrors(['result' => __('No submitted exam was found for this class and roll.')]);
        }

        return view('guest-exam.result', ['attempt' => $attempt->load('exam', 'answers.question')]);
    }

    /**
     * The class and section the form sent, checked against the school's own
     * list.
     *
     * @param  array<string, mixed>  $data
     * @return array{class: array{id: int, name: string}, section: array{id: int, name: string}|null}
     *
     * @throws ValidationException
     */
    private function pick(Exam $exam, array $data, string $errorBag = 'class_id'): array
    {
        try {
            $picked = $this->roster->pick($this->schoolOf($exam), $data['class_id'], $data['section_id'] ?? null);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([$errorBag => $exception->getMessage()]);
        }

        return $picked ?? throw ValidationException::withMessages([$errorBag => __('Choose your class and section.')]);
    }

    private function schoolOf(Exam $exam): SchoolProfile
    {
        return SchoolProfile::for($exam->creator);
    }
}
