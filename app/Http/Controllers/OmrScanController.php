<?php

namespace App\Http\Controllers;

use App\Filament\Support\CurrentPanel;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Filament\Teacher\Resources\Exams\Pages\ExamResults;
use App\Models\Exam;
use App\Models\SchoolProfile;
use App\Services\OmrResultRecorder;
use App\Services\SchoolRoster;
use App\Support\OmrSheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * An exam's OMR page (CLAUDE.md rules 10.11, 10.12) — the one page every
 * OMR sheet is reached from, a printed exam's and an OMR check's alike:
 * which number tells the students apart, the sheet (and answer key) to
 * print, and scanning the filled-in sheets — the teacher points the phone's
 * camera at them one after another (or picks photos/scans), checks what
 * was read, and saves each as a graded paper. The reading happens in the
 * browser (resources/js/omr-scan.js) — only the answers reach the server,
 * which marks them (OmrResultRecorder).
 *
 * For whoever may print the exam (ExamPolicy::print()); a School scans one
 * class + section of its students at a time. Registered inside the Teacher
 * and School panels' authenticated routes, like the printed paper.
 */
class OmrScanController extends Controller
{
    public function __construct(private readonly SchoolRoster $roster) {}

    public function show(Exam $exam): View
    {
        $this->authorizeScanning($exam);

        $classes = null;
        $rosterError = null;

        if ($exam->isSchoolExam()) {
            try {
                $classes = collect($this->roster->classes($this->schoolOf($exam)))
                    ->map(fn (array $class): array => [
                        'id' => (int) $class['id'],
                        'name' => SchoolRoster::className($class),
                        'sections' => array_values($class['sections'] ?? []),
                    ])
                    ->values()
                    ->all();
            } catch (RuntimeException $exception) {
                $rosterError = $exception->getMessage();
            }
        }

        return view('teacher.omr-scan', [
            'exam' => $exam,
            'layout' => OmrSheet::for($exam)->scannerLayout(),
            'identity' => OmrSheet::for($exam)->identity(),
            'canChangeIdentity' => ! $exam->attempts()->where('is_omr', true)->exists(),
            'savedCount' => $exam->attempts()->where('is_omr', true)->count(),
            'classes' => $classes,
            'rosterError' => $rosterError,
            'saveUrl' => CurrentPanel::route('exams.omr-scan.store', $exam),
            'identityUrl' => CurrentPanel::route('exams.omr-identity', $exam),
            'printUrl' => CurrentPanel::route('exams.print', ['exam' => $exam, 'answers' => 'omr']),
            'keyUrl' => CurrentPanel::route('exams.print', ['exam' => $exam, 'answers' => 'key']),
            'resultsUrl' => ExamResults::getUrl(['record' => $exam]),
            'examsUrl' => ExamResource::listUrlFor($exam),
        ]);
    }

    public function store(Request $request, Exam $exam, OmrResultRecorder $recorder): JsonResponse
    {
        $this->authorizeScanning($exam);

        $data = $request->validate([
            'roll' => ['required', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:100'],
            'answers' => ['present', 'array'],
            'answers.*' => ['nullable'],
            'class_id' => [$exam->isSchoolExam() ? 'required' : 'prohibited', 'integer'],
            'section_id' => ['nullable', 'integer'],
        ]);

        $marks = collect($data['answers'])
            ->map(fn (mixed $mark): int|string|null => match (true) {
                $mark === 'several' => 'several',
                is_int($mark), is_string($mark) && ctype_digit($mark) => (int) $mark,
                default => null,
            })
            ->all();

        $attempt = $recorder->record(
            $exam,
            $data['roll'],
            $data['name'] ?? null,
            $marks,
            $exam->isSchoolExam() ? $this->pickClass($exam, $data) : null,
        );

        return response()->json([
            'attempt_id' => $attempt->id,
            'roll' => $attempt->school_roll,
            'score' => (float) $attempt->total_score,
            'out_of' => $exam->scoredMarks(),
        ]);
    }

    /**
     * How many questions the printed sheet has rows for (OmrSheet::saveQuestionCount()),
     * chosen beside the printed sheet — which then shows again with them.
     */
    public function saveQuestionCount(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeScanning($exam);

        // An OMR check's sheet has a row per question of its answer key.
        abort_if($exam->isOmrCheck(), 404);

        OmrSheet::saveQuestionCount($exam, $request->input('questions'));

        return redirect(CurrentPanel::route('exams.print', ['exam' => $exam, 'answers' => 'omr']));
    }

    /**
     * Which number tells the students apart (OmrSheet::saveIdentity()),
     * chosen on this page before any sheet is saved.
     */
    public function saveIdentity(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeScanning($exam);

        OmrSheet::saveIdentity($exam, $request->input('identity'));

        return redirect(CurrentPanel::route('exams.omr-scan', $exam));
    }

    private function authorizeScanning(Exam $exam): void
    {
        Gate::authorize('print', $exam);

        abort_unless($exam->takesOmrSheets(), 404);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{class: array{id: int, name: string}, section: array{id: int, name: string}|null}
     */
    private function pickClass(Exam $exam, array $data): array
    {
        return $this->roster->pickOrFail($this->schoolOf($exam), $data['class_id'], $data['section_id'] ?? null, 'class_id', __('Choose the class and section.'));
    }

    private function schoolOf(Exam $exam): SchoolProfile
    {
        return SchoolProfile::for($exam->creator);
    }
}
