<?php

namespace App\Filament\Teacher\Pages;

use App\Enums\ExamDeliveryMode;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Filament\Support\FutureDateTimePicker;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\Pages\QuestionPickerPage;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\BillingSetting;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\Question;
use App\Services\SubscriptionLimitService;
use App\Services\TeacherExamBuilder;
use App\Services\TeacherQuestionStats;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * The Teacher's exam builder (CLAUDE.md rule 10), on the shared question
 * picker: the approved pool and the teacher's own pending questions, of
 * the classes and subjects their plans reach, for an exam taken online,
 * printed, or both — saved as an exam, where TeacherExamBuilder
 * re-validates everything. Also opens an existing exam to edit (`?exam=`).
 */
class SelectQuestions extends QuestionPickerPage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::QuestionBank;

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Select questions';

    public ?int $savedExamId = null;

    /**
     * Set when the page was opened to edit an existing exam (`?exam=`)
     * instead of building a new one. Locked: the browser can't swap it for
     * another exam's id afterwards.
     */
    #[Locked]
    public ?int $editingExamId = null;

    public function mount(): void
    {
        parent::mount();

        if (filled($examId = request()->query('exam'))) {
            $this->startEditing($examId);
        }
    }

    /**
     * Opens one of the teacher's own exams in the picker: its mode, class
     * and subject become the filters, and its questions the starting
     * selection (see initialSelection()).
     */
    private function startEditing(int|string $examId): void
    {
        $exam = Exam::query()
            ->where('created_by', Auth::id())
            ->where('exam_type', ExamType::TeacherExam)
            // A past paper's questions are the paper's, not picked here.
            ->whereNull('board_question_paper_id')
            ->findOrFail($examId);

        // Exams made before the picker existed didn't record their class;
        // their questions still say which class + subject they belong to.
        $classSubject = $exam->classSubject ?? $exam->questions()->with('chapter.classSubject')->first()?->chapter?->classSubject;

        $this->editingExamId = $exam->id;
        $this->data = [
            ...$this->data ?? [],
            'exam_mode' => $exam->delivery_mode->value,
            'academic_class_id' => $classSubject?->academic_class_id,
            'class_subject_id' => $classSubject?->id,
            'question_type' => $exam->delivery_mode->allowsCq() ? self::TYPE_BOTH : self::TYPE_MCQ,
        ];

        // Start on the final view, so the questions the exam already has
        // are on screen straight away — the teacher removes from there, or
        // goes back to the chapters to add more.
        if ($classSubject) {
            $this->reviewIds = $this->selectableQuestions($classSubject, $exam->questions()->pluck('questions.id')->all())
                ->pluck('id')
                ->all();

            if ($this->reviewIds !== []) {
                $this->step = self::STEP_REVIEW;
            }
        }
    }

    #[Computed]
    public function editingExam(): ?Exam
    {
        return $this->editingExamId
            ? Exam::query()->where('created_by', Auth::id())->find($this->editingExamId)
            : null;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->editingExam
            ? __('Edit exam').' — '.$this->editingExam->title
            : parent::getTitle();
    }

    /**
     * The selection the browser starts from when editing: the exam's
     * current questions, in the same compact shape the browser keeps
     * (id => chapter, type, marks), plus targets matching what is there.
     * Null when building a new exam.
     *
     * @return array<string, mixed>|null
     */
    public function initialSelection(): ?array
    {
        if (! $this->editingExam) {
            return null;
        }

        // Only what the browser keeps per question — not each question's
        // text and options, which this summary never reads.
        $questions = $this->editingExam->questions()
            ->with('chapter:id,name')
            ->get(['questions.id', 'questions.chapter_id', 'questions.question_type', 'questions.marks']);

        return [
            'items' => $questions->mapWithKeys(fn (Question $question) => [$question->id => [
                'c' => $question->chapter_id,
                't' => $question->question_type->value,
                'm' => (float) $question->marks,
            ]])->all() ?: new \stdClass,
            'chapters' => $questions->pluck('chapter.name', 'chapter_id')->all() ?: new \stdClass,
            'targets' => [
                'mcq' => $questions->where('question_type', QuestionType::Mcq)->count() ?: null,
                'cq' => $questions->where('question_type', QuestionType::Cq)->count() ?: null,
            ],
            'classSubjectId' => $this->data['class_subject_id'] ?? null,
            'mode' => $this->data['exam_mode'] ?? null,
        ];
    }

    /**
     * Editing an exam gets a key of its own, so it never disturbs (or is
     * disturbed by) a new exam the teacher is in the middle of building.
     */
    public function selectionStorageKey(): string
    {
        return parent::selectionStorageKey().($this->editingExamId ? ':exam:'.$this->editingExamId : '');
    }

    protected function selectionStoragePrefix(): string
    {
        return 'qb:teacher-selection:';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ToggleButtons::make('exam_mode')
                    ->label(__('How will the exam be taken?'))
                    ->options(ExamDeliveryMode::class)
                    ->default(ExamDeliveryMode::Online)
                    ->inline()
                    ->live()
                    ->required()
                    // An online exam has no CQ, so the type filter follows it.
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        if (! $this->deliveryModeFrom($state)->allowsCq()) {
                            $set('question_type', self::TYPE_MCQ);
                            $this->resetPage();
                        }
                    }),

                // Only the classes and subjects the teacher's plans reach.
                ...$this->hierarchyFilters(),

                ToggleButtons::make('question_type')
                    ->label(__('Question type'))
                    ->options([
                        self::TYPE_MCQ => __('MCQ'),
                        self::TYPE_CQ => __('CQ'),
                        self::TYPE_BOTH => __('MCQ + CQ'),
                    ])
                    ->default(self::TYPE_MCQ)
                    ->disableOptionWhen(fn (string $value, Get $get): bool => $value !== self::TYPE_MCQ && ! $this->deliveryModeFrom($get('exam_mode'))->allowsCq())
                    ->inline()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        if ($state === self::TYPE_CQ) {
                            $set('topic_id', null);
                        }
                    }),

                Toggle::make('most_missed_only')
                    ->label(__('Only the questions my students get wrong'))
                    ->helperText(__('From all your exams, the most-missed first — to set them again.'))
                    ->live(),
            ])
            ->statePath('data');
    }

    public function offersDeliveryModes(): bool
    {
        return true;
    }

    public function showsClassFilter(): bool
    {
        return true;
    }

    public function showsAnswersWhilePicking(): bool
    {
        return true;
    }

    /**
     * Whether the list is narrowed to the questions the teacher's own
     * students have got wrong.
     */
    public function showsMostMissedOnly(): bool
    {
        return (bool) ($this->data['most_missed_only'] ?? false);
    }

    protected function accessibleClassSubjects(): Builder
    {
        return app(SubscriptionLimitService::class)->constrainToAccessible(ClassSubject::query(), Auth::user());
    }

    /**
     * What the teacher may use — the approved pool and their own pending
     * questions — with how their students did on each, most-missed first
     * when asked.
     */
    protected function pickableQuestions(): Builder
    {
        $stats = app(TeacherQuestionStats::class);

        return $stats->addTo(Question::query(), Auth::user())
            ->usableInExamBy(Auth::user())
            ->when($this->showsMostMissedOnly(), fn (Builder $query) => $stats->mostMissedFirst($query));
    }

    public function selectableQuestions(ClassSubject $classSubject, array $questionIds): Collection
    {
        return app(TeacherExamBuilder::class)->selectableQuestions(Auth::user(), $classSubject, $questionIds);
    }

    public function blockedNotice(): ?array
    {
        return $this->hasAnyPlan() ? null : [
            'heading' => __('You need a plan to build exams'),
            'message' => __('Your plan does not cover any class or subject yet. Start your free trial or buy a plan to build exams.'),
            'url' => MySubscription::getUrl(),
            'button' => __('See plans'),
        ];
    }

    public function reviewIntro(): string
    {
        return __('This is the final view of everything you selected, chapter by chapter. Remove anything you do not want, then save it as an exam.');
    }

    public function finishLabel(): string
    {
        return $this->editingExam ? __('Save changes') : __('Save as exam');
    }

    public function finish(array $questionIds): void
    {
        $this->mountAction('saveExam', ['ids' => $questionIds]);
    }

    public function finishedView(): ?string
    {
        return 'filament.teacher.pages.partials.exam-saved';
    }

    public function startNewSelection(): void
    {
        $this->step = self::STEP_SELECT;
        $this->reviewIds = [];
        $this->savedExamId = null;
        $this->resetPage();
    }

    #[Computed]
    public function savedExam(): ?Exam
    {
        return $this->savedExamId
            ? Exam::query()->where('created_by', Auth::id())->withCount('questions')->find($this->savedExamId)
            : null;
    }

    public function saveExamAction(): Action
    {
        return Action::make('saveExam')
            ->label(fn (): string => $this->finishLabel())
            ->icon(Heroicon::OutlinedCheck)
            ->modalHeading(fn (): string => $this->finishLabel())
            // Editing doesn't count again; the exam was counted when first saved.
            ->modalDescription(fn (): ?string => $this->editingExam
                ? null
                : __("Saving counts as one exam towards your plan's limit, whether you publish it online or only print it."))
            ->modalSubmitActionLabel(fn (): string => $this->editingExam ? __('Save changes') : __('Save exam'))
            ->fillForm(fn (): array => [
                'negative_marking' => ($this->editingExam?->marksLostPerWrongAnswer() ?? 0) > 0,
                // Starts on the Admin's amount; the teacher can change it.
                'negative_marks' => $this->editingExam?->negative_marks ?: BillingSetting::current()->default_negative_marks,
                ...$this->editingExam?->only(['title', 'duration_minutes']) ?? [],
            ])
            ->schema([
                TextInput::make('title')
                    ->label(__('Exam title'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('duration_minutes')
                    ->label(__('Duration (minutes)'))
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(600)
                    ->required(),
                // Only an exam taken online is marked by the system; a
                // printed paper is marked by hand.
                Toggle::make('negative_marking')
                    ->label(__('Negative marking'))
                    ->helperText(__('Marks are taken off for each wrong answer online. A blank answer loses nothing, and the total never goes below 0.'))
                    ->visible(fn (): bool => $this->deliveryMode()->includesOnline())
                    ->live(),
                TextInput::make('negative_marks')
                    ->label(__('Marks taken off per wrong answer'))
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(100)
                    ->step(0.01)
                    ->visible(fn (Get $get): bool => $this->deliveryMode()->includesOnline() && (bool) $get('negative_marking'))
                    ->required(fn (Get $get): bool => (bool) $get('negative_marking')),
                // Optional, and it can be set or changed later from the
                // exams list — there is no link to close on a print-only exam.
                // When editing, the end time keeps being managed from the list.
                FutureDateTimePicker::make('link_expires_at')
                    ->label(__('Exam ends at'))
                    ->visible(fn (): bool => ! $this->editingExam && $this->deliveryMode()->includesOnline()),
            ])
            ->action(function (array $data, array $arguments, Action $action): void {
                if (! $this->classSubject) {
                    $action->halt();
                }

                try {
                    $builder = app(TeacherExamBuilder::class);

                    $exam = $this->editingExam
                        ? $builder->update(
                            $this->editingExam,
                            $this->classSubject,
                            $this->deliveryMode(),
                            $arguments['ids'] ?? [],
                            $data['title'],
                            (int) $data['duration_minutes'],
                            $this->negativeMarksFrom($data),
                        )
                        : $builder->build(
                            Auth::user(),
                            $this->classSubject,
                            $this->deliveryMode(),
                            $arguments['ids'] ?? [],
                            $data['title'],
                            (int) $data['duration_minutes'],
                            FutureDateTimePicker::parse($data['link_expires_at'] ?? null),
                            $this->negativeMarksFrom($data),
                        );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title(__('The exam could not be saved'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    // Re-run the review so both sides agree on what's left.
                    $this->review($arguments['ids'] ?? []);

                    return;
                }

                $this->savedExamId = $exam->id;
                $this->reviewIds = [];
                $this->step = self::STEP_SAVED;

                $this->dispatch('qb-selection-saved');

                Notification::make()->title(__('Exam saved'))->success()->send();
            });
    }

    public function examsUrl(): string
    {
        return ExamResource::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function negativeMarksFrom(array $data): ?float
    {
        return ($data['negative_marking'] ?? false) ? (float) ($data['negative_marks'] ?? 0) : null;
    }
}
