<?php

namespace App\Filament\Teacher\Pages;

use App\Enums\ExamDeliveryMode;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\ContentHierarchySchema;
use App\Filament\Support\FutureDateTimePicker;
use App\Filament\Support\NavigationGroup;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Topic;
use App\Services\TeacherExamBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

/**
 * The Teacher's exam builder (CLAUDE.md rule 10): filter the approved pool
 * by Class → Subject → Chapter → Topic, tick questions, review the merged
 * selection, then save it as an exam.
 *
 * The selection itself is kept in the browser (see the page's Blade view) —
 * ticking a question never calls the server. This component only serves the
 * paginated question list, the review of the ids the browser sends back,
 * and the final save, where TeacherExamBuilder re-validates everything.
 */
class SelectQuestions extends Page
{
    use TranslatesPageLabels;
    use WithPagination;

    public const TYPE_MCQ = 'mcq';

    public const TYPE_CQ = 'cq';

    public const TYPE_BOTH = 'both';

    public const STEP_SELECT = 'select';

    public const STEP_REVIEW = 'review';

    public const STEP_SAVED = 'saved';

    protected const QUESTIONS_PER_PAGE = 20;

    protected string $view = 'filament.teacher.pages.select-questions';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::QuestionBank;

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Select questions';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public string $step = self::STEP_SELECT;

    /**
     * The ids the browser sent for review — not trusted, only ever read
     * back through TeacherExamBuilder::selectableQuestions().
     *
     * @var array<int, int>
     */
    public array $reviewIds = [];

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
        $this->form->fill();

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
            $this->reviewIds = app(TeacherExamBuilder::class)
                ->selectableQuestions($classSubject, $exam->questions()->pluck('questions.id')->all())
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

        $questions = $this->editingExam->questions()->with('chapter:id,name')->get();

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
     * Where the browser keeps this page's selection. Editing an exam gets
     * a key of its own, so it never disturbs (or is disturbed by) a new
     * exam the teacher is in the middle of building.
     */
    public function selectionStorageKey(): string
    {
        return 'qb:teacher-selection:'.Auth::id().($this->editingExamId ? ':exam:'.$this->editingExamId : '');
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

                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                    ContentHierarchySchema::classSelect()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('class_subject_id', null);
                            $set('chapter_id', null);
                            $set('topic_id', null);
                        }),

                    ContentHierarchySchema::subjectSelect()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('chapter_id', null);
                            $set('topic_id', null);
                        }),

                    ContentHierarchySchema::chapterSelect()
                        ->afterStateUpdated(fn (Set $set) => $set('topic_id', null)),

                    // CQ isn't organised by topic, so this only narrows MCQ.
                    ContentHierarchySchema::topicSelect()
                        ->placeholder(__('All topics'))
                        ->helperText(fn (Get $get) => $get('question_type') === self::TYPE_BOTH ? __('Applies to MCQ only.') : null)
                        ->visible(fn (Get $get) => $get('question_type') !== self::TYPE_CQ)
                        ->live(),
                ]),

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
            ])
            ->statePath('data');
    }

    /**
     * Any filter change starts the question list again from its first page.
     */
    public function updatedData(): void
    {
        $this->resetPage();
    }

    /**
     * Called by the browser to put the filters back — on page load when a
     * saved selection exists, and when the teacher declines to discard it
     * after picking another subject or switching to an online-only exam.
     *
     * @param  array<string, mixed>  $filters
     */
    public function restoreFilters(array $filters): void
    {
        $classSubject = ClassSubject::find($filters['class_subject_id'] ?? null);
        $mode = ExamDeliveryMode::tryFrom((string) ($filters['exam_mode'] ?? '')) ?? $this->deliveryMode();

        // Only the chapter/topic of the subject being restored still apply.
        $keepsChapter = $classSubject && (string) ($this->data['class_subject_id'] ?? '') === (string) $classSubject->id;

        $this->data = [
            ...$this->data ?? [],
            'exam_mode' => $mode->value,
            'academic_class_id' => $classSubject?->academic_class_id,
            'class_subject_id' => $classSubject?->id,
            'chapter_id' => $keepsChapter ? ($this->data['chapter_id'] ?? null) : null,
            'topic_id' => $keepsChapter ? ($this->data['topic_id'] ?? null) : null,
            'question_type' => $mode->allowsCq() ? ($this->data['question_type'] ?? self::TYPE_MCQ) : self::TYPE_MCQ,
        ];

        $this->resetPage();
    }

    public function deliveryMode(): ExamDeliveryMode
    {
        return $this->deliveryModeFrom($this->data['exam_mode'] ?? null);
    }

    public function questionTypeFilter(): string
    {
        if (! $this->deliveryMode()->allowsCq()) {
            return self::TYPE_MCQ;
        }

        $type = $this->data['question_type'] ?? self::TYPE_MCQ;

        return in_array($type, [self::TYPE_MCQ, self::TYPE_CQ, self::TYPE_BOTH], strict: true) ? $type : self::TYPE_MCQ;
    }

    #[Computed]
    public function classSubject(): ?ClassSubject
    {
        $classSubjectId = $this->data['class_subject_id'] ?? null;

        return filled($classSubjectId)
            ? ClassSubject::with('subject', 'academicClass')->find($classSubjectId)
            : null;
    }

    /**
     * The chapter being browsed — only if it really belongs to the chosen
     * class + subject, so a tampered chapter id can't reach another subject.
     */
    #[Computed]
    public function chapter(): ?Chapter
    {
        $chapterId = $this->data['chapter_id'] ?? null;

        if (blank($chapterId) || ! $this->classSubject) {
            return null;
        }

        return Chapter::query()
            ->where('class_subject_id', $this->classSubject->id)
            ->find($chapterId);
    }

    /**
     * One page of the chapter's approved questions. Null until a chapter is
     * chosen — the whole pool is never listed at once.
     *
     * @return LengthAwarePaginator<int, Question>|null
     */
    #[Computed]
    public function questions(): ?LengthAwarePaginator
    {
        if (! $this->chapter) {
            return null;
        }

        $type = $this->questionTypeFilter();
        $topicId = $type === self::TYPE_CQ ? null : ($this->data['topic_id'] ?? null);

        return Question::query()
            ->approvedPool()
            ->where('chapter_id', $this->chapter->id)
            ->when($type !== self::TYPE_BOTH, fn (Builder $query) => $query->where('question_type', $type))
            ->when(filled($topicId), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('topic_id', $topicId)
                // With both types shown, the topic narrows MCQ and leaves
                // every CQ of the chapter in.
                ->when($type === self::TYPE_BOTH, fn (Builder $query) => $query->orWhere('question_type', QuestionType::Cq))))
            ->with(['topic:id,name', 'cqParts'])
            // 'mcq' sorts after 'cq', so descending lists MCQ first.
            ->orderByDesc('question_type')
            ->orderBy('id')
            ->paginate(self::QUESTIONS_PER_PAGE);
    }

    /**
     * Opens the merged final view for the ids held in the browser.
     *
     * @param  array<int, mixed>  $questionIds
     */
    public function review(array $questionIds): void
    {
        if (! $this->classSubject) {
            return;
        }

        $questions = app(TeacherExamBuilder::class)->selectableQuestions($this->classSubject, $questionIds);

        if (! $this->deliveryMode()->allowsCq()) {
            $questions = $questions->reject(fn (Question $question) => $question->question_type === QuestionType::Cq);
        }

        $this->reviewIds = $questions->pluck('id')->all();

        // Tell the browser what survived, so it drops whatever has been
        // edited, rejected or removed since it was ticked.
        $this->dispatch('qb-selection-validated', ids: $this->reviewIds);

        if ($this->reviewIds === []) {
            Notification::make()
                ->title(__('None of the selected questions are available any more.'))
                ->warning()
                ->send();

            return;
        }

        $this->step = self::STEP_REVIEW;
    }

    public function backToSelection(): void
    {
        $this->step = self::STEP_SELECT;
        $this->reviewIds = [];
    }

    public function startNewSelection(): void
    {
        $this->step = self::STEP_SELECT;
        $this->reviewIds = [];
        $this->savedExamId = null;
        $this->resetPage();
    }

    /**
     * The reviewed questions, chapter by chapter in paper order.
     *
     * @return SupportCollection<int, Collection<int, Question>>
     */
    #[Computed]
    public function reviewChapters(): SupportCollection
    {
        if ($this->reviewIds === [] || ! $this->classSubject) {
            return collect();
        }

        return app(TeacherExamBuilder::class)
            ->selectableQuestions($this->classSubject, $this->reviewIds)
            ->groupBy('chapter_id');
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
            ->label(fn (): string => $this->editingExam ? __('Save changes') : __('Save as exam'))
            ->icon(Heroicon::OutlinedCheck)
            ->modalHeading(fn (): string => $this->editingExam ? __('Save changes') : __('Save as exam'))
            // Editing doesn't count again; the exam was counted when first saved.
            ->modalDescription(fn (): ?string => $this->editingExam
                ? null
                : __('Saving counts as one exam towards your monthly limit, whether you publish it online or only print it.'))
            ->modalSubmitActionLabel(fn (): string => $this->editingExam ? __('Save changes') : __('Save exam'))
            ->fillForm(fn (): array => $this->editingExam?->only(['title', 'duration_minutes']) ?? [])
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
                        )
                        : $builder->build(
                            Auth::user(),
                            $this->classSubject,
                            $this->deliveryMode(),
                            $arguments['ids'] ?? [],
                            $data['title'],
                            (int) $data['duration_minutes'],
                            FutureDateTimePicker::parse($data['link_expires_at'] ?? null),
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

    private function deliveryModeFrom(mixed $state): ExamDeliveryMode
    {
        if ($state instanceof ExamDeliveryMode) {
            return $state;
        }

        return ExamDeliveryMode::tryFrom((string) $state) ?? ExamDeliveryMode::Online;
    }
}
