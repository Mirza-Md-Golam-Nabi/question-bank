<?php

namespace App\Filament\Support\Pages;

use App\Enums\ExamDeliveryMode;
use App\Enums\QuestionType;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\ContentHierarchySchema;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Services\SubscriptionLimitService;
use App\Services\TeacherExamBuilder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Renderless;
use Livewire\WithPagination;

/**
 * The question picker, the same on every panel that builds an exam from
 * the question bank (CLAUDE.md rule 10) — the Teacher's "Select questions"
 * and the Student's own practice exam: filter by Class → Subject → Chapter
 * → Topic, tick questions from as many chapters as wanted, up to the
 * number asked for, then a final view of everything picked.
 *
 * The selection itself is kept in the browser (the shared view
 * `filament.support.pages.question-picker`) — ticking a question never
 * calls the server. This component only serves the paginated question
 * list, the review of the ids the browser sends back, and the final step.
 *
 * A panel's page says only which: which questions may be picked, which
 * classes and subjects, whether the exam can be printed (and so take CQ),
 * and what the final step does — the Teacher saves an exam, the Student
 * starts one.
 */
abstract class QuestionPickerPage extends Page
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

    protected string $view = 'filament.support.pages.question-picker';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public string $step = self::STEP_SELECT;

    /**
     * The ids the browser sent for review — not trusted, only ever read
     * back through selectableQuestions().
     *
     * @var array<int, int>
     */
    public array $reviewIds = [];

    /**
     * The questions the user may pick, before any filter — the chapter,
     * type, topic and difficulty are added by the picker.
     *
     * @return Builder<Question>
     */
    abstract protected function pickableQuestions(): Builder;

    /**
     * The questions among `$questionIds` the user may really put on the
     * exam — what the final view shows and the final step uses.
     *
     * @param  array<int, mixed>  $questionIds
     * @return Collection<int, Question>
     */
    abstract public function selectableQuestions(ClassSubject $classSubject, array $questionIds): Collection;

    /**
     * The class + subject pairs the user may pick from.
     *
     * @return Builder<ClassSubject>
     */
    abstract protected function accessibleClassSubjects(): Builder;

    /**
     * Whether the exam may be printed as well as taken online — and so
     * hold CQ. Without it the exam is online only, MCQ only.
     */
    abstract public function offersDeliveryModes(): bool;

    /**
     * Whether the user picks the class too, or it is fixed (a student's own).
     */
    abstract public function showsClassFilter(): bool;

    /**
     * Whether the cards show the correct answer while picking — not for a
     * student about to sit the questions.
     */
    abstract public function showsAnswersWhilePicking(): bool;

    /**
     * What the browser's selection is kept under, before the user's id.
     */
    abstract protected function selectionStoragePrefix(): string;

    /**
     * The button that ends the final view.
     */
    abstract public function finishLabel(): string;

    /**
     * What the final view's button does with the ids the browser holds.
     *
     * @param  array<int, mixed>  $questionIds
     */
    abstract public function finish(array $questionIds): void;

    /**
     * The line above the final view.
     */
    abstract public function reviewIntro(): string;

    /**
     * What stops the user from picking at all (no plan, no class), as a
     * notice; null when nothing does.
     *
     * @return array{heading: string, message: string, url: string, button: string}|null
     */
    abstract public function blockedNotice(): ?array;

    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * The selection the browser starts from — none, unless a page opens an
     * existing exam.
     *
     * @return array<string, mixed>|null
     */
    public function initialSelection(): ?array
    {
        return null;
    }

    public function selectionStorageKey(): string
    {
        return $this->selectionStoragePrefix().Auth::id();
    }

    /**
     * The view shown after the final step, when it stays on this page.
     */
    public function finishedView(): ?string
    {
        return null;
    }

    /**
     * The Class → Subject → Chapter → Topic filters, each clearing the ones
     * below it when it changes.
     *
     * @return array<int, Grid>
     */
    protected function hierarchyFilters(): array
    {
        $accessible = fn (): array => $this->accessibleClassSubjectIds;

        return [
            Grid::make(['default' => 1, 'sm' => 2, 'lg' => $this->showsClassFilter() ? 4 : 3])->schema(array_values(array_filter([
                $this->showsClassFilter()
                    ? ContentHierarchySchema::classSelect($accessible)
                        ->afterStateUpdated(function (Set $set): void {
                            $set('class_subject_id', null);
                            $set('chapter_id', null);
                            $set('topic_id', null);
                        })
                    : null,

                ContentHierarchySchema::subjectSelect($accessible)
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
            ]))),
        ];
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
     * saved selection exists, and when the user declines to discard it
     * after picking another subject or switching to an online-only exam.
     *
     * @param  array<string, mixed>  $filters
     */
    public function restoreFilters(array $filters): void
    {
        $classSubject = $this->classSubjectQuery()->find($filters['class_subject_id'] ?? null);
        $mode = ExamDeliveryMode::tryFrom((string) ($filters['exam_mode'] ?? '')) ?? $this->deliveryMode();

        // Only the chapter/topic of the subject being restored still apply.
        $keepsChapter = $classSubject && (string) ($this->data['class_subject_id'] ?? '') === (string) $classSubject->id;

        $this->data = [
            ...$this->data ?? [],
            'exam_mode' => $mode->value,
            'academic_class_id' => $classSubject?->academic_class_id ?? ($this->data['academic_class_id'] ?? null),
            'class_subject_id' => $classSubject?->id,
            'chapter_id' => $keepsChapter ? ($this->data['chapter_id'] ?? null) : null,
            'topic_id' => $keepsChapter ? ($this->data['topic_id'] ?? null) : null,
            'question_type' => $mode->allowsCq() ? ($this->data['question_type'] ?? self::TYPE_MCQ) : self::TYPE_MCQ,
        ];

        $this->resetPage();
    }

    public function deliveryMode(): ExamDeliveryMode
    {
        if (! $this->offersDeliveryModes()) {
            return ExamDeliveryMode::Online;
        }

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

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function accessibleClassSubjectIds(): array
    {
        return $this->accessibleClassSubjects()->pluck('class_subjects.id')->all();
    }

    /**
     * Whether the user's plans reach any class + subject here at all.
     */
    public function hasAnyPlan(): bool
    {
        return $this->accessibleClassSubjectIds !== [];
    }

    /**
     * The class + subject being browsed — only one the user may pick from.
     */
    #[Computed]
    public function classSubject(): ?ClassSubject
    {
        $classSubjectId = $this->data['class_subject_id'] ?? null;

        return filled($classSubjectId)
            ? $this->classSubjectQuery()->with('subject', 'academicClass')->find($classSubjectId)
            : null;
    }

    /**
     * What the class + subject filter is looked up in. Every class + subject
     * by default — the plans are checked again at the final step — or only
     * the user's own (a student's class).
     *
     * @return Builder<ClassSubject>
     */
    protected function classSubjectQuery(): Builder
    {
        return ClassSubject::query();
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
     * One page of the chapter's questions the user may pick. Null until a
     * chapter is chosen — the whole pool is never listed at once.
     *
     * @return LengthAwarePaginator<int, Question>|null
     */
    #[Computed]
    public function questions(): ?LengthAwarePaginator
    {
        return $this->filteredQuestions()
            ?->with(['topic:id,name', 'cqParts', 'stimulus'])
            // 'mcq' sorts after 'cq', so descending lists MCQ first.
            ->orderByDesc('question_type')
            // The questions of one stimulus side by side, where the first
            // of them would stand, in their own order.
            ->orderByRaw('coalesce((select min(grouped.id) from questions as grouped where grouped.stimulus_id = questions.stimulus_id), questions.id)')
            ->orderBy('questions.stimulus_order')
            ->orderBy('questions.id')
            ->paginate(self::QUESTIONS_PER_PAGE);
    }

    /**
     * "Select at random": up to the asked-for number of each type, picked
     * at random from everything the filters list (every page, not just the
     * one on screen), leaving out what is already selected. Only the
     * browser's selection changes — the ids are checked again at the end.
     *
     * @param  array<int, mixed>  $selectedIds
     * @param  array<string, mixed>  $wanted  How many more of each type: ['mcq' => 5, 'cq' => 2]
     * @return list<array{id: int, chapter: int, chapterName: string, type: string, marks: float}>
     */
    #[Renderless]
    public function randomQuestions(array $selectedIds, array $wanted): array
    {
        $chapter = $this->chapter;

        if (! $chapter) {
            return [];
        }

        $selectedIds = array_map('intval', array_slice($selectedIds, 0, TeacherExamBuilder::MAX_QUESTIONS));

        return collect([self::TYPE_MCQ, self::TYPE_CQ])
            ->flatMap(fn (string $type) => $this->filteredQuestions()
                ->where('question_type', $type)
                ->whereNotIn('questions.id', $selectedIds)
                // Random even when the page sorts the list its own way.
                ->reorder()
                ->inRandomOrder()
                ->limit(min(max((int) ($wanted[$type] ?? 0), 0), TeacherExamBuilder::MAX_QUESTIONS))
                ->get(['questions.id', 'questions.chapter_id', 'questions.question_type', 'questions.marks']))
            ->map(fn (Question $question): array => [
                'id' => $question->id,
                'chapter' => $question->chapter_id,
                'chapterName' => $chapter->name,
                'type' => $question->question_type->value,
                'marks' => (float) $question->marks,
            ])
            ->values()
            ->all();
    }

    /**
     * The chapter's questions the user may pick, narrowed by the page's
     * filters — what the list shows and what "Select at random" picks from.
     *
     * @return Builder<Question>|null
     */
    protected function filteredQuestions(): ?Builder
    {
        if (! $this->chapter) {
            return null;
        }

        $type = $this->questionTypeFilter();
        $topicId = $type === self::TYPE_CQ ? null : ($this->data['topic_id'] ?? null);
        $difficulties = app(SubscriptionLimitService::class)->allowedDifficultiesFor(Auth::user(), $this->classSubject);

        return $this->pickableQuestions()
            ->where('chapter_id', $this->chapter->id)
            // Only questions of a difficulty the user's plan allows.
            ->when($difficulties !== null, fn (Builder $query) => $query->whereIn('difficulty', $difficulties))
            ->when($type !== self::TYPE_BOTH, fn (Builder $query) => $query->where('question_type', $type))
            ->when(filled($topicId), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('topic_id', $topicId)
                // With both types shown, the topic narrows MCQ and leaves
                // every CQ of the chapter in.
                ->when($type === self::TYPE_BOTH, fn (Builder $query) => $query->orWhere('question_type', QuestionType::Cq))));
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

        $questions = $this->selectableQuestions($this->classSubject, $questionIds);

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

        return $this->selectableQuestions($this->classSubject, $this->reviewIds)->groupBy('chapter_id');
    }

    protected function deliveryModeFrom(mixed $state): ExamDeliveryMode
    {
        if ($state instanceof ExamDeliveryMode) {
            return $state;
        }

        return ExamDeliveryMode::tryFrom((string) $state) ?? ExamDeliveryMode::Online;
    }
}
