<?php

namespace App\Filament\Pages;

use App\Enums\Locale;
use App\Enums\PaperSourceType;
use App\Enums\QuestionSyncRunKind;
use App\Enums\QuestionSyncRunStatus;
use App\Filament\Support\Concerns\NotifiesValidationFailures;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\ContentHierarchySchema;
use App\Filament\Support\NavigationGroup;
use App\Models\Chapter;
use App\Models\QuestionSyncRun;
use App\Services\QuestionSyncClient;
use App\Services\QuestionSyncImporter;
use App\Support\QuestionSync;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * The test server's page for bringing questions from the live server
 * (CLAUDE.md rule 16) — only there, only for the Super Admin. On the left
 * the test server's class → subject → chapter, on the right the live
 * server's; "Fetch" brings that chapter's questions over on the queue. A
 * past paper is fetched whole the same way. Below, every fetch with what it
 * did. What a fetch does is QuestionSyncImporter's.
 */
class FetchQuestionsFromLive extends Page implements HasTable
{
    use InteractsWithTable;
    use NotifiesValidationFailures;
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.fetch-questions-from-live';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::QuestionBank;

    protected static ?string $title = 'Fetch from the live server';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $chapterData = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $paperData = [];

    /**
     * Why the live server can't be asked right now, when it can't.
     */
    public ?string $connectionProblem = null;

    public ?string $openUntil = null;

    private ?QuestionSyncClient $client = null;

    public static function canAccess(): bool
    {
        return QuestionSync::isConsumer() && QuestionSync::canManage(Auth::user());
    }

    public function mount(): void
    {
        $this->chapterForm->fill();
        $this->paperForm->fill();
        $this->checkConnection();
    }

    /**
     * Asks the live server whether its sharing is open.
     */
    public function checkConnection(): void
    {
        $this->connectionProblem = null;

        try {
            $until = $this->client()->get('status')['open_until'] ?? null;
            $this->openUntil = $until ? QuestionSync::displayTime(CarbonImmutable::parse($until)) : null;
        } catch (RuntimeException $exception) {
            $this->connectionProblem = $exception->getMessage();
        }
    }

    public function chapterForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('chapterData')
            ->components([
                Grid::make(['default' => 1, 'lg' => 2])->schema([
                    Section::make(__('Test server (here)'))
                        ->description(__('Where the questions go.'))
                        ->schema([
                            ContentHierarchySchema::classSelect()->afterStateUpdated(fn (Set $set) => $set('class_subject_id', null))->required(),
                            ContentHierarchySchema::subjectSelect()->afterStateUpdated(fn (Set $set) => $set('chapter_id', null))->required(),
                            ContentHierarchySchema::chapterSelect()->required(),
                        ]),
                    Section::make(__('Live server'))
                        ->description(__('Where the questions come from: its approved questions, with answers, explanations and images.'))
                        ->schema([
                            Select::make('remote_class_id')
                                ->label(__('Class'))
                                ->options(fn (): array => $this->remoteOptions('classes'))
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('remote_class_subject_id', null))
                                ->required(),
                            Select::make('remote_class_subject_id')
                                ->label(__('Subject'))
                                ->options(fn (Get $get): array => filled($get('remote_class_id')) ? $this->remoteOptions("classes/{$get('remote_class_id')}/subjects") : [])
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('remote_chapter_id', null))
                                ->required(),
                            Select::make('remote_chapter_id')
                                ->label(__('Chapter'))
                                ->options(fn (Get $get): array => filled($get('remote_class_subject_id')) ? $this->remoteChapterOptions((int) $get('remote_class_subject_id')) : [])
                                ->required(),
                        ]),
                ]),
            ]);
    }

    public function paperForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('paperData')
            ->components([
                Grid::make(['default' => 1, 'lg' => 2])->schema([
                    Section::make(__('Test server (here)'))
                        ->description(__("The class and subject the paper is for — only for a board's or a school's paper."))
                        ->schema([
                            ContentHierarchySchema::classSelect()
                                ->afterStateUpdated(fn (Set $set) => $set('class_subject_id', null))
                                ->required(fn (Get $get): bool => $this->paperHasClassSubject($get))
                                ->disabled(fn (Get $get): bool => ! $this->paperHasClassSubject($get)),
                            ContentHierarchySchema::subjectSelect()
                                ->required(fn (Get $get): bool => $this->paperHasClassSubject($get))
                                ->disabled(fn (Get $get): bool => ! $this->paperHasClassSubject($get)),
                        ]),
                    Section::make(__('Live server'))
                        ->description(__('An approved past paper, whole.'))
                        ->schema([
                            Select::make('type')
                                ->label(__('Kind of paper'))
                                ->options(PaperSourceType::class)
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('remote_board_id', null))
                                ->required(),
                            Select::make('remote_board_id')
                                ->label(__('Source'))
                                ->options(fn (Get $get): array => filled($get('type')) ? $this->remoteOptions('paper-sources/'.self::typeOf($get)->value) : [])
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('remote_paper_id', null))
                                ->required(),
                            Select::make('remote_paper_id')
                                ->label(__('Paper'))
                                ->options(fn (Get $get): array => filled($get('remote_board_id')) ? $this->remotePaperOptions((int) $get('remote_board_id')) : [])
                                ->searchable()
                                ->required(),
                        ]),
                ]),
            ]);
    }

    public function fetchChapter(): void
    {
        $data = $this->chapterForm->getState();

        $label = collect([
            $this->remoteOptions('classes')[$data['remote_class_id']] ?? null,
            $this->remoteOptions("classes/{$data['remote_class_id']}/subjects")[$data['remote_class_subject_id']] ?? null,
            $this->remoteChapterOptions((int) $data['remote_class_subject_id'])[$data['remote_chapter_id']] ?? null,
        ])->filter()->join(' · ');

        $run = $this->rescueWithNotification(fn () => QuestionSyncImporter::startChapter(
            Auth::user(),
            (int) $data['remote_chapter_id'],
            $label,
            Chapter::query()->findOrFail($data['chapter_id']),
        ));

        $run && $this->started();
    }

    public function fetchPaper(): void
    {
        $data = $this->paperForm->getState();

        $run = $this->rescueWithNotification(fn () => QuestionSyncImporter::startPaper(
            Auth::user(),
            (int) $data['remote_paper_id'],
            $this->remotePaperOptions((int) $data['remote_board_id'])[$data['remote_paper_id']] ?? (string) $data['remote_paper_id'],
            self::typeOf(fn (string $key) => $data[$key] ?? null)->hasClassSubject() ? (int) $data['class_subject_id'] : null,
        ));

        $run && $this->started();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(QuestionSyncRun::query()->with(['chapter.classSubject.academicClass', 'chapter.classSubject.subject', 'boardQuestionPaper.board', 'starter']))
            ->defaultSort('id', 'desc')
            ->poll(fn (): ?string => QuestionSyncRun::query()->whereIn('status', [QuestionSyncRunStatus::Queued, QuestionSyncRunStatus::Running])->exists() ? '5s' : null)
            ->heading(__('Fetches'))
            ->emptyStateHeading(__('Nothing has been fetched yet'))
            ->columns([
                TextColumn::make('created_at')->label(__('Started'))->localDateTime(),
                TextColumn::make('kind')->label(__('What'))->badge(),
                TextColumn::make('remote_label')->label(__('From the live server'))->wrap(),
                TextColumn::make('target')
                    ->label(__('To the test server'))
                    ->state(fn (QuestionSyncRun $record): ?string => $record->kind === QuestionSyncRunKind::Chapter
                        ? ($record->chapter ? "{$record->chapter->classSubject->label()} · {$record->chapter->name}" : null)
                        : $record->boardQuestionPaper?->title())
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('status')->label(__('Status'))->badge()->color(fn (QuestionSyncRunStatus $state): string => $state->getColor()),
                TextColumn::make('counts')
                    ->label(__('Questions'))
                    ->state(fn (QuestionSyncRun $record): string => Locale::current()->writeDigits(__(':added new, :updated updated, :unchanged unchanged, :skipped left out', [
                        'added' => $record->countOf('added'),
                        'updated' => $record->countOf('updated'),
                        'unchanged' => $record->countOf('unchanged'),
                        'skipped' => $record->countOf('skipped'),
                    ])))
                    ->description(fn (QuestionSyncRun $record): string => Locale::current()->writeDigits(__(':topics new topics · :images images, :missing not found', [
                        'topics' => $record->countOf('new_topics'),
                        'images' => $record->countOf('images'),
                        'missing' => $record->countOf('missing_images'),
                    ])))
                    ->wrap(),
                TextColumn::make('message')->label(__('Note'))->placeholder('—')->wrap(),
            ]);
    }

    private function started(): void
    {
        Notification::make()->title(__('Fetching in the background — the list below shows how far it is.'))->success()->send();
    }

    /**
     * A list the live server gives, as select options (each item's name,
     * or `$label` of it) — empty, with the reason shown above, when it can't
     * be asked.
     *
     * @param  (\Closure(array<string, mixed>): string)|null  $label
     * @return array<int, string>
     */
    private function remoteOptions(string $path, ?\Closure $label = null): array
    {
        try {
            return collect($this->client()->get($path))
                ->mapWithKeys(fn (array $item): array => [$item['id'] => $label ? $label($item) : $item['name']])
                ->all();
        } catch (RuntimeException $exception) {
            $this->connectionProblem = $exception->getMessage();

            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function remoteChapterOptions(int $classSubjectId): array
    {
        return $this->remoteOptions(
            "class-subjects/{$classSubjectId}/chapters",
            fn (array $chapter): string => __(':name (:count questions)', ['name' => $chapter['name'], 'count' => Locale::current()->writeDigits((string) $chapter['questions'])]),
        );
    }

    /**
     * @return array<int, string>
     */
    private function remotePaperOptions(int $boardId): array
    {
        return $this->remoteOptions(
            "boards/{$boardId}/papers",
            fn (array $paper): string => __(':name (:mcqs MCQ, :cqs CQ)', ['name' => $paper['name'], 'mcqs' => Locale::current()->writeDigits((string) $paper['mcqs']), 'cqs' => Locale::current()->writeDigits((string) $paper['cqs'])]),
        );
    }

    private function paperHasClassSubject(Get $get): bool
    {
        return filled($get('type')) && self::typeOf($get)->hasClassSubject();
    }

    /**
     * The kind picked — the select gives it back as the enum (or its value).
     *
     * @param  Get|\Closure(string): mixed  $get
     */
    private static function typeOf(Get|\Closure $get): PaperSourceType
    {
        $type = $get('type');

        return $type instanceof PaperSourceType ? $type : PaperSourceType::from($type);
    }

    private function client(): QuestionSyncClient
    {
        return $this->client ??= app(QuestionSyncClient::class);
    }
}
