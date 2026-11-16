<?php

namespace App\Services;

use App\Enums\CqPartType;
use App\Enums\Difficulty;
use App\Enums\EditorMode;
use App\Enums\PaperSourceType;
use App\Enums\QuestionStatus;
use App\Enums\QuestionSyncRunKind;
use App\Enums\QuestionSyncRunStatus;
use App\Enums\QuestionType;
use App\Jobs\FetchFromLiveServer;
use App\Models\Board;
use App\Models\BoardCqQuestion;
use App\Models\BoardMcqStimulus;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\QuestionStimulus;
use App\Models\QuestionSyncRun;
use App\Models\Topic;
use App\Models\User;
use App\Support\QuestionSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Brings questions from the live server to the test server — the one place
 * it is decided what a fetch does (CLAUDE.md rule 16). On the queue, one
 * run (QuestionSyncRun) at a time:
 *
 *  - a chapter: every question the live server shares of it goes into the
 *    chosen test chapter, approved, written by the Super Admin who fetched
 *    (so no Staff member is paid for it); its topic is found by name in that
 *    chapter, made when it isn't there, left empty when it has none;
 *  - a question is known by its first version's id on the live server
 *    (`remote_origin_id`): one not here yet is added; one here at the same
 *    version is left as it is — a change made here stays — with only its
 *    difficulty brought along; one at a newer version there gets a new
 *    version here (CLAUDE.md rule 4: the old one stays on its exams); one
 *    deleted here is not brought back. A stimulus's questions go as one;
 *  - nothing is checked for duplicates again — the live server did that;
 *  - a past paper whole, approved: a second fetch replaces its questions,
 *    unless someone has sat it here; a paper of the same source, year, unit
 *    and class + subject made here is never overwritten.
 *
 * Nothing on the test server is ever sent to the live server.
 */
final class QuestionSyncImporter
{
    /**
     * Topics found or made this run, by chapter and by their name as compared.
     *
     * @var array<int, array<string, int>>
     */
    private array $topics = [];

    /**
     * Topics made since the counts were last added to.
     */
    private int $newTopics = 0;

    public function __construct(private readonly QuestionSyncClient $client) {}

    /**
     * Has a live chapter's questions fetched into a test chapter, on the queue.
     *
     * @throws ValidationException
     */
    public static function startChapter(User $admin, int $remoteChapterId, string $remoteLabel, Chapter $chapter): QuestionSyncRun
    {
        return self::start($admin, [
            'kind' => QuestionSyncRunKind::Chapter,
            'remote_id' => $remoteChapterId,
            'remote_label' => $remoteLabel,
            'chapter_id' => $chapter->id,
        ]);
    }

    /**
     * Has a live past paper fetched whole, on the queue — with the test
     * class + subject it is for, when its kind has one.
     *
     * @throws ValidationException
     */
    public static function startPaper(User $admin, int $remotePaperId, string $remoteLabel, ?int $classSubjectId): QuestionSyncRun
    {
        return self::start($admin, [
            'kind' => QuestionSyncRunKind::Paper,
            'remote_id' => $remotePaperId,
            'remote_label' => $remoteLabel,
            'class_subject_id' => $classSubjectId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    private static function start(User $admin, array $attributes): QuestionSyncRun
    {
        if (! QuestionSync::isConsumer() || ! QuestionSync::canManage($admin)) {
            throw ValidationException::withMessages(['remote' => __('Only the Super Admin of the test server can fetch from the live server.')]);
        }

        $isRunning = QuestionSyncRun::query()
            ->where('kind', $attributes['kind'])
            ->where('remote_id', $attributes['remote_id'])
            ->whereIn('status', [QuestionSyncRunStatus::Queued, QuestionSyncRunStatus::Running])
            ->exists();

        if ($isRunning) {
            throw ValidationException::withMessages(['remote' => __('This is being fetched already — wait for it to finish.')]);
        }

        $run = QuestionSyncRun::create([
            ...$attributes,
            'status' => QuestionSyncRunStatus::Queued,
            'counts' => QuestionSyncRun::EMPTY_COUNTS,
            'started_by' => $admin->id,
        ]);

        Log::info('Fetch from the live server started.', ['run_id' => $run->id, 'kind' => $run->kind->value, 'remote_id' => $run->remote_id, 'by' => $admin->id]);

        FetchFromLiveServer::dispatch($run->id);

        return $run;
    }

    /**
     * Does one run, keeping what it did on it — and why, when it failed.
     * What was saved before a failure stays; fetching again carries on.
     */
    public function run(QuestionSyncRun $run): void
    {
        $run->update(['status' => QuestionSyncRunStatus::Running]);

        try {
            QuestionDuplicateScreening::whilePaused(fn () => $run->kind === QuestionSyncRunKind::Chapter
                ? $this->fetchChapter($run)
                : $this->fetchPaper($run));

            $run->update(['status' => QuestionSyncRunStatus::Done, 'finished_at' => now()]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => QuestionSyncRunStatus::Failed,
                'message' => $exception instanceof RuntimeException || $exception instanceof ValidationException
                    ? $exception->getMessage()
                    : __('Something went wrong while fetching. What was saved before it stays; fetch again to carry on.'),
                'finished_at' => now(),
            ]);

            report($exception);
        }

        Log::info('Fetch from the live server finished.', ['run_id' => $run->id, 'status' => $run->status->value, 'counts' => $run->counts]);
    }

    private function fetchChapter(QuestionSyncRun $run): void
    {
        $chapter = $run->chapter ?? throw new RuntimeException(__('The test chapter was deleted.'));
        $admin = $run->starter;

        foreach (['single', 'group'] as $kind) {
            $after = 0;

            do {
                $page = $this->client->get("chapters/{$run->remote_id}/questions", ['kind' => $kind, 'after' => $after]);

                foreach ($page['units'] as $unit) {
                    $counts = $this->images($unit);
                    $counts += DB::transaction(fn (): array => $unit['stimulus'] === null
                        ? $this->saveQuestion($unit['questions'][0], $chapter, $admin)
                        : $this->saveGroup($unit['stimulus'], $unit['questions'], $chapter, $admin));

                    $this->add($run, $counts);
                }

                $after = $page['next'];
            } while ($after !== null);
        }

        // So that new questions written here are compared with these.
        QuestionDuplicateScreening::fingerprintChapter(DuplicateQuestionFinder::make(), $chapter->id);
    }

    /**
     * A question alone.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, int>
     */
    private function saveQuestion(array $data, Chapter $chapter, User $admin): array
    {
        $lineage = $this->lineageOf($data);

        if ($lineage === null) {
            $this->create($data, $chapter, $admin, null);

            return ['added' => 1];
        }

        if ($lineage['latest']->trashed()) {
            return ['skipped' => 1];
        }

        if (! $lineage['isNewer']) {
            $this->bringDifficulty($lineage['latest'], $data);

            return ['unchanged' => 1];
        }

        $this->revise($lineage['latest'], $data, $chapter, $admin, null);

        return ['updated' => 1];
    }

    /**
     * A stimulus with its questions, as one: when any of them is newer
     * there, the group gets a new version here — a new stimulus, a new
     * version of each — as editing it would (StimulusGroupService).
     *
     * @param  array<string, mixed>  $stimulus
     * @param  list<array<string, mixed>>  $questions
     * @return array<string, int>
     */
    private function saveGroup(array $stimulus, array $questions, Chapter $chapter, User $admin): array
    {
        $lineages = array_map(fn (array $data): ?array => $this->lineageOf($data), $questions);
        $counts = [];

        if (collect($lineages)->filter()->isEmpty()) {
            $stimulusId = $this->createStimulus($stimulus, $chapter, $admin)->id;

            foreach ($questions as $data) {
                $this->create($data, $chapter, $admin, $stimulusId);
            }

            return ['added' => count($questions)];
        }

        $isNewer = collect($lineages)->contains(fn (?array $lineage): bool => $lineage !== null && ! $lineage['latest']->trashed() && $lineage['isNewer']);

        // Nothing newer there: the group stays as it is here; a question
        // that joined it there joins it here.
        $stimulusId = $isNewer
            ? $this->createStimulus($stimulus, $chapter, $admin)->id
            : collect($lineages)->filter(fn (?array $lineage): bool => $lineage !== null && ! $lineage['latest']->trashed())->first()['latest']->stimulus_id
                ?? $this->createStimulus($stimulus, $chapter, $admin)->id;

        $revisions = [];

        foreach ($questions as $index => $data) {
            $lineage = $lineages[$index];

            if ($lineage === null) {
                $this->create($data, $chapter, $admin, $stimulusId);
                $counts['added'] = ($counts['added'] ?? 0) + 1;
            } elseif ($lineage['latest']->trashed()) {
                $counts['skipped'] = ($counts['skipped'] ?? 0) + 1;
            } elseif ($isNewer) {
                $revisions[] = $this->revise($lineage['latest'], $data, $chapter, $admin, $stimulusId, approve: false);
                $counts['updated'] = ($counts['updated'] ?? 0) + 1;
            } else {
                $this->bringDifficulty($lineage['latest'], $data);
                $counts['unchanged'] = ($counts['unchanged'] ?? 0) + 1;
            }
        }

        // Approving one approves the group.
        foreach ($revisions as $revision) {
            if (Question::query()->whereKey($revision->id)->value('status') !== QuestionStatus::Approved) {
                $revision->approve($admin);
            }
        }

        return $counts;
    }

    /**
     * What came here before of the question: the current version here —
     * also one deleted here — and whether the live one is newer than every
     * version fetched so far. Null when it was never fetched.
     *
     * @param  array<string, mixed>  $data
     * @return array{latest: Question, isNewer: bool}|null
     */
    private function lineageOf(array $data): ?array
    {
        $fetched = Question::withTrashed()->where('remote_origin_id', $data['origin_id'])->get(['id', 'parent_id', 'remote_version']);

        if ($fetched->isEmpty()) {
            return null;
        }

        $rootId = $fetched->first()->parent_id ?? $fetched->first()->id;
        $versions = Question::withTrashed()->where(fn ($query) => $query->whereKey($rootId)->orWhere('parent_id', $rootId));
        $latest = (clone $versions)->where('is_latest', true)->latest('version')->first()
            ?? (clone $versions)->latest('version')->first();

        return [
            'latest' => $latest,
            'isNewer' => (int) $data['version'] > (int) $fetched->max('remote_version'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(array $data, Chapter $chapter, User $admin, ?int $stimulusId): Question
    {
        $question = new Question([
            ...$this->attributes($data, $chapter),
            'stimulus_id' => $stimulusId,
            'status' => QuestionStatus::Approved,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'is_private' => false,
        ]);

        $question->forceFill(['remote_origin_id' => $data['origin_id'], 'remote_version' => $data['version']])->save();

        foreach ($this->cqParts($data) as $part) {
            $question->cqParts()->create($part);
        }

        return $question;
    }

    /**
     * A new version of the question here, made from the live one, approved
     * (unless the group is approved together afterwards).
     *
     * @param  array<string, mixed>  $data
     */
    private function revise(Question $latest, array $data, Chapter $chapter, User $admin, ?int $stimulusId, bool $approve = true): Question
    {
        $revision = $latest->createRevisionWith(
            [...$this->attributes($data, $chapter), 'stimulus_id' => $stimulusId],
            $this->cqParts($data) ?: null,
        );

        $revision->forceFill([
            'remote_origin_id' => $data['origin_id'],
            'remote_version' => $data['version'],
            'difficulty' => Difficulty::tryFrom((string) $data['difficulty']) ?? $revision->difficulty,
        ])->save();

        if ($approve) {
            $revision->approve($admin);
        }

        return $revision;
    }

    /**
     * The difficulty is the only thing brought to a question already here at
     * the live version — and only when it is not a version changed here.
     *
     * @param  array<string, mixed>  $data
     */
    private function bringDifficulty(Question $latest, array $data): void
    {
        $difficulty = Difficulty::tryFrom((string) $data['difficulty']);

        if ($difficulty && $latest->remote_origin_id !== null && $latest->difficulty !== $difficulty) {
            $latest->forceFill(['difficulty' => $difficulty])->save();
        }
    }

    /**
     * What a question takes from the live one.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, Chapter $chapter): array
    {
        $type = QuestionType::from($data['question_type']);

        return [
            'chapter_id' => $chapter->id,
            'topic_id' => $this->topicIdFor($chapter, $data['topic'] ?? null),
            'question_type' => $type,
            'question_text' => (string) $data['question_text'],
            'editor_mode' => EditorMode::tryFrom((string) $data['editor_mode']) ?? EditorMode::CkEditor,
            'question_image' => $data['question_image'],
            'difficulty' => Difficulty::tryFrom((string) $data['difficulty']),
            'stimulus_order' => $data['stimulus_order'],
            'marks' => $data['marks'],
            ...($type === QuestionType::Mcq ? [
                'options' => $data['options'],
                'explanation' => $data['explanation'],
                'explanation_editor_mode' => EditorMode::tryFrom((string) $data['explanation_editor_mode']),
            ] : []),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function cqParts(array $data): array
    {
        return array_map(fn (array $part): array => [
            'part_type' => CqPartType::from($part['part_type']),
            'part_order' => $part['part_order'],
            'part_text' => $part['part_text'],
            'part_image' => $part['part_image'],
            'marks' => $part['marks'],
        ], $data['cq_parts'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createStimulus(array $data, Chapter $chapter, User $admin): QuestionStimulus
    {
        $stimulus = new QuestionStimulus([
            ...$this->stimulusAttributes($data),
            'chapter_id' => $chapter->id,
            'created_by' => $admin->id,
        ]);
        $stimulus->forceFill(['remote_id' => $data['id']])->save();

        return $stimulus;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stimulusAttributes(array $data): array
    {
        return [
            'text' => (string) $data['text'],
            'editor_mode' => EditorMode::tryFrom((string) $data['editor_mode']) ?? EditorMode::CkEditor,
            'image' => $data['image'],
        ];
    }

    /**
     * The test chapter's topic of this name — spaces and letter case aside —
     * made at the end of the chapter's topics when there is none; no topic
     * for a question with none.
     */
    private function topicIdFor(Chapter $chapter, ?string $name): ?int
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name));

        if ($name === '') {
            return null;
        }

        $this->topics[$chapter->id] ??= Topic::query()
            ->where('chapter_id', $chapter->id)
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Topic $topic): array => [self::comparable($topic->name) => $topic->id])
            ->all();

        $key = self::comparable($name);

        if (! isset($this->topics[$chapter->id][$key])) {
            $this->topics[$chapter->id][$key] = Topic::create([
                'chapter_id' => $chapter->id,
                'name' => $name,
                'order_index' => (int) Topic::query()->where('chapter_id', $chapter->id)->max('order_index') + 1,
            ])->id;
            $this->newTopics++;
        }

        return $this->topics[$chapter->id][$key];
    }

    private static function comparable(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
    }

    private function fetchPaper(QuestionSyncRun $run): void
    {
        $data = $this->client->get("papers/{$run->remote_id}");
        $admin = $run->starter;
        $board = $this->boardFor($data['source']);
        $classSubjectId = $board->type->hasClassSubject()
            ? ($run->class_subject_id ?? throw new RuntimeException(__('Choose the class and subject of this paper on the test server.')))
            : null;
        $questionCount = count($data['mcqs']) + count($data['cqs']);

        $paper = BoardQuestionPaper::query()->where('remote_id', $data['id'])->first();

        if ($paper?->hasBeenSat()) {
            $run->update(['board_question_paper_id' => $paper->id, 'message' => __('Someone has sat this paper on the test server, so its questions were not changed.')]);
            $this->add($run, ['skipped' => $questionCount]);

            return;
        }

        if (BoardQuestionPaper::isTaken($board->id, $classSubjectId, (int) $data['year'], $data['unit'], $paper?->id)) {
            $run->update(['message' => __('The test server already has a paper of this source, year and unit (and class + subject), made here — it was left as it is.')]);
            $this->add($run, ['skipped' => $questionCount]);

            return;
        }

        $counts = $this->images($data);

        $paper = DB::transaction(function () use ($data, $paper, $board, $classSubjectId, $admin): BoardQuestionPaper {
            $attributes = [
                'board_id' => $board->id,
                'class_subject_id' => $classSubjectId,
                'year' => $data['year'],
                'unit' => (string) $data['unit'],
                'duration_minutes' => $data['duration_minutes'],
                'negative_marks' => $data['negative_marks'],
            ];

            if ($paper) {
                $paper->update($attributes);
                $paper->cqQuestions()->get()->each(function (BoardCqQuestion $question): void {
                    $question->parts()->delete();
                    $question->delete();
                });
                $paper->mcqQuestions()->delete();
                $paper->mcqStimuli()->delete();
            } else {
                $paper = new BoardQuestionPaper([...$attributes, 'status' => QuestionStatus::Approved, 'created_by' => $admin->id, 'approved_by' => $admin->id]);
                $paper->forceFill(['remote_id' => $data['id']])->save();
            }

            $this->savePaperQuestions($paper, $data);

            if ($paper->status !== QuestionStatus::Approved) {
                $paper->approve($admin);
            }

            return $paper;
        });

        $run->update(['board_question_paper_id' => $paper->id]);
        $this->add($run, [...$counts, $paper->wasRecentlyCreated ? 'added' : 'updated' => $questionCount]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function savePaperQuestions(BoardQuestionPaper $paper, array $data): void
    {
        $stimuli = collect($data['stimuli'])->mapWithKeys(fn (array $stimulus): array => [
            $stimulus['id'] => BoardMcqStimulus::create([...$this->stimulusAttributes($stimulus), 'board_question_paper_id' => $paper->id])->id,
        ]);

        foreach ($data['mcqs'] as $mcq) {
            $paper->mcqQuestions()->create([
                'stimulus_id' => $mcq['stimulus_id'] !== null ? $stimuli[$mcq['stimulus_id']] : null,
                'stimulus_order' => $mcq['stimulus_order'],
                'question_text' => (string) $mcq['question_text'],
                'editor_mode' => EditorMode::tryFrom((string) $mcq['editor_mode']) ?? EditorMode::CkEditor,
                'question_image' => $mcq['question_image'],
                'options' => $mcq['options'],
                'explanation' => $mcq['explanation'],
                'explanation_editor_mode' => EditorMode::tryFrom((string) $mcq['explanation_editor_mode']),
                'marks' => $mcq['marks'],
                'order_index' => $mcq['order_index'],
            ]);
        }

        foreach ($data['cqs'] as $cq) {
            $question = $paper->cqQuestions()->create([
                'question_text' => (string) $cq['question_text'],
                'editor_mode' => EditorMode::tryFrom((string) $cq['editor_mode']) ?? EditorMode::CkEditor,
                'question_image' => $cq['question_image'],
                'marks' => collect($cq['parts'])->sum('marks'),
                'order_index' => $cq['order_index'],
            ]);

            foreach ($cq['parts'] as $part) {
                $question->parts()->create([...$part, 'part_type' => CqPartType::from($part['part_type'])]);
            }
        }
    }

    /**
     * The test server's source for the live paper's: the same kind and
     * name (in English, or else in Bangla), made when there is none.
     *
     * @param  array<string, mixed>  $source
     */
    private function boardFor(array $source): Board
    {
        $type = PaperSourceType::from($source['type']);
        $boards = Board::query()->where('type', $type)->get();

        $same = fn (string $column) => $boards->first(fn (Board $board): bool => filled($source[$column] ?? null)
            && self::comparable((string) $board->{$column}) === self::comparable((string) $source[$column]));

        return $same('name') ?? $same('name_bn') ?? Board::create([
            'type' => $type,
            'name' => $source['name'],
            'name_bn' => $source['name_bn'],
            'short_name' => $source['short_name'],
            'short_name_bn' => $source['short_name_bn'],
        ]);
    }

    /**
     * Brings every image the questions have that isn't here yet, to the same
     * place on the public disk; one the live server doesn't have is counted.
     *
     * @param  array<string, mixed>  $data  A unit of a chapter, or a whole paper.
     * @return array{images?: int, missing_images?: int}
     */
    private function images(array $data): array
    {
        $counts = [];
        $disk = Storage::disk('public');

        foreach (array_unique(self::imagePathsIn($data)) as $path) {
            if (QuestionSync::isImagePath($path) && $disk->exists($path)) {
                continue;
            }

            $bytes = QuestionSync::isImagePath($path) ? $this->client->image($path) : null;

            if ($bytes === null || @getimagesizefromstring($bytes) === false) {
                $counts['missing_images'] = ($counts['missing_images'] ?? 0) + 1;

                continue;
            }

            $disk->put($path, $bytes);
            $counts['images'] = ($counts['images'] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Every image named anywhere in what the live server sent — a question's,
     * an option's, a sub-question's, a stimulus's.
     *
     * @return list<string>
     */
    private static function imagePathsIn(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $paths = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['question_image', 'image', 'part_image'], true) && filled($value) && is_string($value)) {
                $paths[] = $value;
            } elseif (is_array($value)) {
                array_push($paths, ...self::imagePathsIn($value));
            }
        }

        return $paths;
    }

    /**
     * Adds what a unit (or the paper) did to the run's counts, so the page
     * shows how far it is.
     *
     * @param  array<string, int>  $counts
     */
    private function add(QuestionSyncRun $run, array $counts): void
    {
        $counts['new_topics'] = ($counts['new_topics'] ?? 0) + $this->newTopics;
        $this->newTopics = 0;

        $run->update(['counts' => collect($run->counts ?? QuestionSyncRun::EMPTY_COUNTS)
            ->map(fn (int $count, string $key): int => $count + ($counts[$key] ?? 0))
            ->all()]);
    }
}
