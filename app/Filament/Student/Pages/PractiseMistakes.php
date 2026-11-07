<?php

namespace App\Filament\Student\Pages;

use App\Enums\Locale;
use App\Enums\MistakeSource;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\Pages\SelfPracticeExamPage;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\ExamAttempt;
use App\Models\Topic;
use App\Models\User;
use App\Services\MistakeFilter;
use App\Services\SelfPracticeExamService;
use App\Services\StudentMistakeTracker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

/**
 * The student's mistakes: every question they got wrong on any exam — a
 * teacher's, their own practice, a past paper — listed with what they
 * picked, the correct answer and the explanation, and a practice exam
 * drawn from them, one section at a time (a subject of their class, or
 * the past papers outside them) — narrowed, if the student likes, to the
 * question bank's mistakes of a chapter (and a topic), or the past papers'
 * of a year. Which questions those are, and when one leaves the list, is
 * StudentMistakeTracker's business.
 */
class PractiseMistakes extends SelfPracticeExamPage
{
    use WithPagination;

    /**
     * The section of the past papers outside the subjects of the student's
     * class, as the form's value.
     */
    public const PAST_PAPERS = 'past-papers';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Practice;

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Practise my mistakes';

    protected string $view = 'filament.student.pages.practise-mistakes';

    /**
     * The subjects looked up this request, by id (null: not the class's).
     *
     * @var array<int, ClassSubject|null>
     */
    private array $subjects = [];

    /**
     * The mistakes counted this request, by what they were counted by and
     * the choices they depend on.
     *
     * @var array<string, array<int, int>>
     */
    private array $counts = [];

    public function mount(): void
    {
        parent::mount();

        // The first section with mistakes, unless one came with the link.
        if (! array_key_exists((string) ($this->data['class_subject_id'] ?? ''), $this->sectionsWithMistakes())) {
            $this->data['class_subject_id'] = array_key_first($this->sectionsWithMistakes());
        }

        // Opened from a weak chapter on the dashboard (`?chapter=`): that
        // chapter's question-bank mistakes, if it has any in the subject.
        $chapterId = (int) request()->query('chapter');

        if ($chapterId && array_key_exists($chapterId, $this->chapterOptions())) {
            $this->data['mistake_source'] = MistakeSource::QuestionBank->value;
            $this->data['chapter_id'] = $chapterId;
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('class_subject_id')
                    ->label(__('Subject'))
                    ->options(fn (): array => $this->sectionsWithMistakes())
                    ->helperText(__('Only subjects where you have mistakes are listed, with how many. Mistakes on past papers of other subjects or classes, and on admission and job papers, are under :section.', ['section' => __('Other past papers')]))
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $this->narrowAgain($set, 'mistake_source', 'chapter_id', 'topic_id', 'year'))
                    ->required(),
                Select::make('mistake_source')
                    ->label(__('Which mistakes'))
                    ->options(fn (): array => $this->sourceOptions())
                    ->default(MistakeSource::All->value)
                    ->selectablePlaceholder(false)
                    ->visible(fn (): bool => $this->chosenSubject() !== null)
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $this->narrowAgain($set, 'chapter_id', 'topic_id', 'year')),
                Select::make('chapter_id')
                    ->label(__('Chapter'))
                    ->options(fn (): array => $this->chapterOptions())
                    ->placeholder(__('All chapters'))
                    ->visible(fn (Get $get): bool => $get('mistake_source') === MistakeSource::QuestionBank->value)
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $this->narrowAgain($set, 'topic_id')),
                Select::make('topic_id')
                    ->label(__('Topic'))
                    ->options(fn (): array => $this->topicOptions())
                    ->placeholder(__('All topics'))
                    ->visible(fn (Get $get): bool => $get('mistake_source') === MistakeSource::QuestionBank->value && $this->topicOptions() !== [])
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $this->narrowAgain($set)),
                Select::make('year')
                    ->label(__('Year'))
                    ->options(fn (): array => $this->yearOptions())
                    ->placeholder(__('All years'))
                    ->visible(fn (): bool => $this->isPastPapersOnly() && $this->yearOptions() !== [])
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $this->narrowAgain($set)),
                TextInput::make('question_count')
                    ->label(__('How many questions'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(10)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submitLabel(): string
    {
        return __('Start practising');
    }

    public function hasMistakes(): bool
    {
        return $this->sectionsWithMistakes() !== [];
    }

    /**
     * The chosen section's mistakes, a page at a time.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>|null
     */
    #[Computed]
    public function mistakes(): ?LengthAwarePaginator
    {
        $chosen = $this->data['class_subject_id'] ?? null;

        if (! array_key_exists((string) $chosen, $this->sectionsWithMistakes())) {
            return null;
        }

        return app(StudentMistakeTracker::class)->listFor(Auth::user(), $this->chosenSubject(), $this->mistakeFilter($this->data ?? []));
    }

    protected function startsWithoutSubject(array $data): bool
    {
        return ($data['class_subject_id'] ?? null) === self::PAST_PAPERS;
    }

    protected function createAttempt(User $student, ?ClassSubject $classSubject, array $data): ExamAttempt
    {
        return app(SelfPracticeExamService::class)->generateFromMistakes(
            $student,
            $classSubject,
            (int) $data['question_count'],
            $this->mistakeFilter($data),
        );
    }

    /**
     * The chosen subject of the student's class — null for the past
     * papers outside them, or nothing chosen.
     */
    private function chosenSubject(): ?ClassSubject
    {
        $chosen = $this->data['class_subject_id'] ?? null;

        if ($chosen === self::PAST_PAPERS || blank($chosen)) {
            return null;
        }

        if (! array_key_exists((int) $chosen, $this->subjects)) {
            $this->subjects[(int) $chosen] = $this->classSubject($chosen);
        }

        return $this->subjects[(int) $chosen];
    }

    /**
     * Whether only past paper mistakes are shown: the past papers outside
     * the class's subjects, or a subject's past papers chosen.
     */
    private function isPastPapersOnly(): bool
    {
        return ($this->data['class_subject_id'] ?? null) === self::PAST_PAPERS
            || ($this->data['mistake_source'] ?? null) === MistakeSource::PastPapers->value;
    }

    /**
     * How the form narrows the chosen section — the past papers outside the
     * class's subjects are past papers only, whatever else was sent.
     *
     * @param  array<string, mixed>  $data
     */
    private function mistakeFilter(array $data): MistakeFilter
    {
        if (($data['class_subject_id'] ?? null) === self::PAST_PAPERS) {
            $data['mistake_source'] = MistakeSource::PastPapers->value;
        }

        return MistakeFilter::fromForm($data);
    }

    /**
     * Clears the narrower choices under the one just changed, and goes back
     * to the list's first page.
     */
    private function narrowAgain(Set $set, string ...$fields): void
    {
        foreach ($fields as $field) {
            $set($field, $field === 'mistake_source' ? MistakeSource::All->value : null);
        }

        $this->resetPage();
    }

    /**
     * The kinds of mistakes the chosen subject has, each with how many —
     * "all" always, the others only when there are any.
     *
     * @return array<string, string>
     */
    private function sourceOptions(): array
    {
        $counts = [
            MistakeSource::QuestionBank->value => array_sum($this->countsOf('chapters')),
            MistakeSource::PastPapers->value => array_sum($this->countsOf('years')),
        ];

        return collect([MistakeSource::All->value => MistakeSource::All->getLabel()])
            ->union(collect($counts)
                ->filter()
                ->mapWithKeys(fn (int $count, string $source): array => [$source => MistakeSource::from($source)->getLabel()." ({$count})"]))
            ->all();
    }

    /**
     * The chapters of the chosen subject with question-bank mistakes, in
     * their order, each with how many.
     *
     * @return array<int, string>
     */
    private function chapterOptions(): array
    {
        $counts = $this->countsOf('chapters');

        if ($counts === []) {
            return [];
        }

        return Chapter::query()
            ->whereKey(array_keys($counts))
            ->ordered()
            ->pluck('name', 'id')
            ->map(fn (string $name, int $chapterId): string => "{$name} ({$counts[$chapterId]})")
            ->all();
    }

    /**
     * The topics of the chosen chapter with mistakes, in their order, each
     * with how many.
     *
     * @return array<int, string>
     */
    private function topicOptions(): array
    {
        $counts = $this->countsOf('topics');

        return collect(Topic::optionsFor($this->data['chapter_id'] ?? null))
            ->only(array_keys($counts))
            ->map(fn (string $name, int $topicId): string => "{$name} ({$counts[$topicId]})")
            ->all();
    }

    /**
     * The years of the chosen section's past paper mistakes, the latest
     * first, each with how many.
     *
     * @return array<int, string>
     */
    private function yearOptions(): array
    {
        return collect($this->countsOf('years'))
            ->map(fn (int $count, int $year): string => Locale::current()->writeDigits((string) $year)." ({$count})")
            ->all();
    }

    /**
     * The chosen section's mistakes counted by chapter, topic or year —
     * worked out once per request.
     *
     * @param  'chapters'|'topics'|'years'  $by
     * @return array<int, int>
     */
    private function countsOf(string $by): array
    {
        $section = $this->chosenSubject();
        $chapterId = (int) ($this->data['chapter_id'] ?? 0);
        $tracker = app(StudentMistakeTracker::class);

        // Keyed by the choices it depends on, which can change mid-request.
        return $this->counts["{$by}:".($this->data['class_subject_id'] ?? '').":{$chapterId}"] ??= match ($by) {
            'chapters' => $section ? $tracker->countsByChapter(Auth::user(), $section)->all() : [],
            'topics' => $section && $chapterId ? $tracker->countsByTopic(Auth::user(), $section, $chapterId)->all() : [],
            'years' => $this->hasClass() && ($section || ($this->data['class_subject_id'] ?? null) === self::PAST_PAPERS)
                ? $tracker->countsByYear(Auth::user(), $section)->all()
                : [],
        };
    }

    /**
     * The sections of the student's list with mistakes in them, each
     * labelled with how many — the subjects of their class, then the past
     * papers outside them; worked out once per request.
     *
     * @return array<int|string, string>
     */
    private function sectionsWithMistakes(): array
    {
        return once(function (): array {
            if (! $this->hasClass()) {
                return [];
            }

            $counts = app(StudentMistakeTracker::class)->countsBySection(Auth::user());

            $sections = collect($this->subjectOptions())
                ->only($counts->keys()->filter(fn (int|string $section): bool => $section !== ''))
                ->map(fn (string $name, int $classSubjectId): string => "{$name} ({$counts[$classSubjectId]})")
                ->all();

            if ($counts->has('')) {
                $sections[self::PAST_PAPERS] = __('Other past papers').' ('.$counts[''].')';
            }

            return $sections;
        });
    }
}
