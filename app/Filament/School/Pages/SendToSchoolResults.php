<?php

namespace App\Filament\School\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\SchoolProfile;
use App\Models\SchoolResultPush;
use App\Services\SchoolInstallationClient;
use App\Services\SchoolResultMatcher;
use App\Services\SchoolResultPusher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use RuntimeException;

/**
 * "Send to school results" for one of the School's online exams (CLAUDE.md
 * rule 12): pick a class the papers came from, then one of the school
 * management software's exams for that class and a subject with MCQ; see
 * every student on the school's list against the papers — matched, a name
 * that doesn't match (to approve), a roll used more than once (to pick
 * one), absent — choose what happens when the full marks differ or marks
 * are already there, and send. Who is whose is SchoolResultMatcher's;
 * the sending SchoolResultPusher's.
 */
class SendToSchoolResults extends Page
{
    use TranslatesPageLabels;

    protected static ?string $slug = 'exams/{exam}/send-to-school-results';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.school.pages.send-to-school-results';

    #[Locked]
    public int $examId;

    public ?int $classId = null;

    public ?int $schoolExamId = null;

    public ?int $subjectId = null;

    /**
     * The school's list for the chosen exam + subject, as its software gave it.
     *
     * @var array{mcq_total: float, is_published: bool, students: list<array<string, mixed>>}|null
     */
    public ?array $sheet = null;

    /**
     * Null until the School has chosen, when the full marks differ.
     */
    public ?bool $inProportion = null;

    public bool $overwrite = false;

    /**
     * What the School decided for each student on the list.
     *
     * @var array<int|string, array{send: bool, attempt: int|null}>
     */
    public array $choices = [];

    public ?int $pushId = null;

    public function mount(int $exam): void
    {
        $record = Exam::query()->where('created_by', Auth::id())->findOrFail($exam);

        abort_unless($record->delivery_mode->includesOnline(), 404);

        $this->examId = $record->id;
    }

    public function getTitle(): string|Htmlable
    {
        return __('Send to school results').' — '.$this->exam->title;
    }

    #[Computed]
    public function exam(): Exam
    {
        return Exam::query()->where('created_by', Auth::id())->findOrFail($this->examId);
    }

    public function examsUrl(): string
    {
        return ExamResource::getUrl('index');
    }

    /**
     * The classes the exam's papers came from.
     *
     * @return Collection<int, string> class id => name
     */
    #[Computed]
    public function classes(): Collection
    {
        return ExamAttempt::query()
            ->where('exam_id', $this->examId)
            ->whereNotNull('school_class_id')
            ->submitted()
            ->distinct()
            ->pluck('school_class_name', 'school_class_id');
    }

    /**
     * The school's exams for the chosen class, with their MCQ subjects.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function schoolExams(): array
    {
        if (! $this->classId) {
            return [];
        }

        try {
            return (array) ($this->client()->get($this->profile(), "/api/question-bank/classes/{$this->classId}/exams")['exams'] ?? []);
        } catch (RuntimeException $exception) {
            $this->addError('classId', $exception->getMessage());

            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function schoolExam(): ?array
    {
        return collect($this->schoolExams)->firstWhere('id', $this->schoolExamId);
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function subject(): ?array
    {
        return collect($this->schoolExam['subjects'] ?? [])->firstWhere('id', $this->subjectId);
    }

    public function updatedClassId(): void
    {
        $this->reset(['schoolExamId', 'subjectId', 'sheet', 'inProportion', 'choices', 'pushId']);
    }

    public function updatedSchoolExamId(): void
    {
        $this->reset(['subjectId', 'sheet', 'inProportion', 'choices', 'pushId']);
    }

    public function updatedSubjectId(): void
    {
        $this->reset(['sheet', 'inProportion', 'choices', 'pushId']);
    }

    /**
     * Fetches the school's list for the chosen exam + subject and lays the
     * papers against it — asking first, when the full marks differ.
     */
    public function loadSheet(): void
    {
        if (! $this->classId || ! $this->schoolExam || ! $this->subject) {
            return;
        }

        try {
            $this->sheet = $this->client()->get($this->profile(), "/api/question-bank/exams/{$this->schoolExamId}/subjects/{$this->subjectId}/students");
        } catch (RuntimeException $exception) {
            Notification::make()->title(__('The list could not be loaded'))->body($exception->getMessage())->danger()->send();

            return;
        }

        $this->pushId = null;
        $this->inProportion = $this->fullMarksDiffer() ? null : false;
        $this->choices = collect($this->rows)->mapWithKeys(fn (array $row): array => [$row['student']['id'] => [
            // What is clear goes by itself; what isn't waits for the School.
            'send' => in_array($row['status'], [SchoolResultMatcher::MATCHED, SchoolResultMatcher::ABSENT], strict: true),
            'attempt' => $row['status'] === SchoolResultMatcher::DUPLICATE ? null : $row['attempts']->first()?->id,
        ]])->all();

        if ($this->fullMarksDiffer()) {
            $this->mountAction('fullMarks');
        }
    }

    /**
     * @return array{rows: list<array<string, mixed>>, unmatched: Collection<int, ExamAttempt>}
     */
    #[Computed]
    public function match(): array
    {
        return $this->sheet
            ? app(SchoolResultMatcher::class)->match($this->exam, (int) $this->classId, $this->sheet['students'] ?? [])
            : ['rows' => [], 'unmatched' => collect()];
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function rows(): array
    {
        return $this->match['rows'];
    }

    public function fullMarksDiffer(): bool
    {
        return $this->sheet !== null && abs((float) $this->sheet['mcq_total'] - (float) $this->exam->total_marks) > 0.001;
    }

    /**
     * What a paper's score becomes in the school's result.
     */
    public function marksOf(?int $attemptId): ?float
    {
        $paper = collect($this->rows)->flatMap(fn (array $row) => $row['attempts'])->firstWhere('id', $attemptId);

        return $paper ? SchoolResultMatcher::marksFor($paper, $this->exam, (float) $this->sheet['mcq_total'], (bool) $this->inProportion) : null;
    }

    /**
     * The marks to send, from what the School chose.
     *
     * @return list<array{student_id: int, mcq_marks: float|null, absent: bool}>
     */
    public function marksToSend(): array
    {
        return collect($this->rows)
            // A roll used more than once goes once the School picks a paper.
            ->filter(fn (array $row): bool => $row['status'] === SchoolResultMatcher::DUPLICATE
                ? filled($this->choices[$row['student']['id']]['attempt'] ?? null)
                : (bool) ($this->choices[$row['student']['id']]['send'] ?? false))
            ->map(function (array $row): ?array {
                $studentId = (int) $row['student']['id'];

                if ($row['status'] === SchoolResultMatcher::ABSENT) {
                    return ['student_id' => $studentId, 'mcq_marks' => null, 'absent' => true];
                }

                $marks = $this->marksOf($this->choices[$studentId]['attempt'] ?? null);

                return $marks === null ? null : ['student_id' => $studentId, 'mcq_marks' => $marks, 'absent' => false];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function fullMarksAction(): Action
    {
        return Action::make('fullMarks')
            ->modalHeading(__('The full marks are not the same'))
            ->modalDescription(fn (): string => __('This exam is out of :ours marks; the MCQ of this subject in the school software is out of :theirs.', [
                'ours' => (float) $this->exam->total_marks,
                'theirs' => (float) ($this->sheet['mcq_total'] ?? 0),
            ]))
            // One of the two answers has to be given: no closing it away.
            ->modalCloseButton(false)
            ->closeModalByClickingAway(false)
            ->modalSubmitActionLabel(__('Put them in proportion'))
            ->modalCancelAction(false)
            ->extraModalFooterActions([
                Action::make('fixInSchoolSoftware')
                    ->label(__('Fix it in the school software'))
                    ->color('gray')
                    ->action(fn () => $this->cancelFullMarks())
                    ->cancelParentActions(),
            ])
            ->action(fn () => $this->inProportion = true);
    }

    /**
     * The School chose to fix the full marks in its software: nothing is sent
     * until it loads the list again.
     */
    public function cancelFullMarks(): void
    {
        $this->sheet = null;

        Notification::make()
            ->title(__('Set the MCQ full marks of this subject in the school software, then load the list again.'))
            ->info()
            ->send();
    }

    public function sendAction(): Action
    {
        return Action::make('send')
            ->label(__('Send to school results'))
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(fn (): string => __('The marks of :count students go to :exam — :subject.', [
                'count' => count($this->marksToSend()),
                'exam' => $this->schoolExam['name'] ?? '',
                'subject' => $this->subject['name'] ?? '',
            ]))
            ->disabled(fn (): bool => $this->sheet === null || $this->inProportion === null || ($this->sheet['is_published'] ?? false) || $this->marksToSend() === [])
            ->action(function (): void {
                try {
                    $push = app(SchoolResultPusher::class)->push($this->exam, Auth::user(), [
                        'class_id' => (int) $this->classId,
                        'class_name' => $this->classes[$this->classId] ?? null,
                        'exam_id' => (int) $this->schoolExamId,
                        'exam_name' => $this->schoolExam['name'] ?? null,
                        'subject_id' => (int) $this->subjectId,
                        'subject_name' => $this->subject['name'] ?? null,
                        'full_marks' => (float) $this->sheet['mcq_total'],
                    ], $this->marksToSend(), (bool) $this->inProportion, $this->overwrite);
                } catch (RuntimeException $exception) {
                    Notification::make()->title(__('The marks were not sent'))->body($exception->getMessage())->danger()->send();

                    return;
                }

                $this->pushId = $push->id;

                Notification::make()
                    ->title(__(':count marks saved in the school results', ['count' => $push->saved_count]))
                    ->success()
                    ->send();
            });
    }

    #[Computed]
    public function push(): ?SchoolResultPush
    {
        return $this->pushId ? SchoolResultPush::query()->where('sent_by', Auth::id())->find($this->pushId) : null;
    }

    /**
     * Why the school's software left a student as they were, in words.
     */
    public static function skipReason(string $reason): string
    {
        return match ($reason) {
            'has_marks' => __('Already has marks (not replaced)'),
            'has_other_marks' => __('Has written or practical marks, so not marked absent'),
            'over_full_marks' => __('More than the full marks'),
            'not_in_subject' => __('Does not take this subject'),
            default => $reason,
        };
    }

    /**
     * A student of the school's list, by id — for the result of a sending.
     *
     * @return array<string, mixed>|null
     */
    public function studentOnList(int $studentId): ?array
    {
        return collect($this->sheet['students'] ?? [])->firstWhere('id', $studentId);
    }

    private function profile(): SchoolProfile
    {
        return SchoolProfile::for(Auth::user());
    }

    private function client(): SchoolInstallationClient
    {
        return app(SchoolInstallationClient::class);
    }
}
