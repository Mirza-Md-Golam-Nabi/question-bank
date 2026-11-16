<?php

namespace App\Services;

use App\Enums\PaperSourceType;
use App\Enums\QuestionType;
use App\Models\AcademicClass;
use App\Models\BillingSetting;
use App\Models\Board;
use App\Models\BoardCqQuestion;
use App\Models\BoardCqQuestionPart;
use App\Models\BoardMcqQuestion;
use App\Models\BoardMcqStimulus;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Models\QuestionCqPart;
use App\Models\QuestionStimulus;
use App\Models\User;
use App\Support\QuestionSync;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * What the live server shares with the test server — the one place it is
 * decided (CLAUDE.md rule 16), read by the API (QuestionSyncController):
 *
 *  - only while the Super Admin has the sharing open, for the hours they
 *    chose (`BillingSetting.question_sync_open_until`), and only on the
 *    server that is the source (QuestionSync);
 *  - only the approved pool (Question::approvedPool(): approved, latest,
 *    never a School's own, never one held by the duplicate check), and
 *    only approved past papers;
 *  - each question with its answer, explanation, sub-questions, stimulus,
 *    topic's name and difficulty; what identifies it on both servers is its
 *    first version's id and its version.
 */
final class QuestionSyncSource
{
    /**
     * How many questions alone, and how many stimuli with their questions,
     * one page of a chapter carries — technical limits.
     */
    public const SINGLES_PER_PAGE = 100;

    public const GROUPS_PER_PAGE = 25;

    /**
     * The longest the sharing can be opened for at once, in hours.
     */
    public const MAX_OPEN_HOURS = 72;

    public static function isOpen(): bool
    {
        return QuestionSync::isSource() && self::openUntil() !== null;
    }

    /**
     * Until when the sharing is open — null when it is closed.
     */
    public static function openUntil(): ?CarbonInterface
    {
        $until = BillingSetting::current()->question_sync_open_until;

        return $until && $until->isFuture() ? $until : null;
    }

    /**
     * Opens the sharing for so many hours; it closes by itself after them.
     *
     * @throws ValidationException
     */
    public static function open(User $admin, int $hours): void
    {
        self::ensureCanManage($admin);

        if ($hours < 1 || $hours > self::MAX_OPEN_HOURS) {
            throw ValidationException::withMessages(['hours' => __('Between 1 and :max hours.', ['max' => self::MAX_OPEN_HOURS])]);
        }

        BillingSetting::current()->fill([
            'question_sync_open_hours' => $hours,
            'question_sync_open_until' => now()->addHours($hours),
        ])->save();

        Log::info('Question sharing with the test server opened.', ['by' => $admin->id, 'hours' => $hours]);
    }

    /**
     * @throws ValidationException
     */
    public static function close(User $admin): void
    {
        self::ensureCanManage($admin);

        BillingSetting::current()->fill(['question_sync_open_until' => null])->save();

        Log::info('Question sharing with the test server closed.', ['by' => $admin->id]);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function classes(): array
    {
        return self::asList(AcademicClass::options());
    }

    /**
     * A class's subjects, by their class_subject id.
     *
     * @return list<array{id: int, name: string}>
     */
    public function subjects(int $classId): array
    {
        return self::asList(ClassSubject::subjectOptionsFor($classId));
    }

    /**
     * A class's subject's chapters, each with how many of its questions can
     * be fetched.
     *
     * @return list<array{id: int, name: string, questions: int}>
     */
    public function chapters(int $classSubjectId): array
    {
        return Chapter::query()
            ->where('class_subject_id', $classSubjectId)
            ->withCount(['questions' => fn (Builder $questions) => $questions->approvedPool()])
            ->ordered()
            ->get()
            ->map(fn (Chapter $chapter): array => ['id' => $chapter->id, 'name' => $chapter->name, 'questions' => $chapter->questions_count])
            ->all();
    }

    /**
     * One page of a chapter's shared questions, a unit at a time — the
     * questions alone (`single`), or the stimuli with their questions
     * (`group`) — after the id `$after` of that kind.
     *
     * @return array{units: list<array<string, mixed>>, next: int|null}
     */
    public function units(int $chapterId, string $kind, int $after): array
    {
        if ($kind === 'group') {
            $stimuli = QuestionStimulus::query()
                ->where('chapter_id', $chapterId)
                ->where('id', '>', $after)
                ->whereHas('questions', fn (Builder $questions) => $questions->approvedPool())
                ->with(['questions' => fn ($questions) => $questions->approvedPool()->with(['topic'])])
                ->orderBy('id')
                ->limit(self::GROUPS_PER_PAGE)
                ->get();

            return [
                'units' => $stimuli->map(fn (QuestionStimulus $stimulus): array => [
                    'stimulus' => ['id' => $stimulus->id, ...self::stimulusData($stimulus)],
                    'questions' => $stimulus->questions->map(fn (Question $question): array => $this->bankQuestionData($question))->all(),
                ])->all(),
                'next' => $stimuli->count() === self::GROUPS_PER_PAGE ? $stimuli->last()->id : null,
            ];
        }

        $questions = Question::query()
            ->approvedPool()
            ->where('chapter_id', $chapterId)
            ->whereNull('stimulus_id')
            ->where('id', '>', $after)
            ->with(['cqParts', 'topic'])
            ->orderBy('id')
            ->limit(self::SINGLES_PER_PAGE)
            ->get();

        return [
            'units' => $questions->map(fn (Question $question): array => ['stimulus' => null, 'questions' => [$this->bankQuestionData($question)]])->all(),
            'next' => $questions->count() === self::SINGLES_PER_PAGE ? $questions->last()->id : null,
        ];
    }

    /**
     * The sources of one kind that have an approved paper.
     *
     * @return list<array{id: int, name: string}>
     */
    public function paperSources(PaperSourceType $type): array
    {
        return Board::query()
            ->where('type', $type)
            ->whereHas('questionPapers', fn (Builder $papers) => $papers->approved())
            ->orderedByDisplayName()
            ->get()
            ->map(fn (Board $board): array => ['id' => $board->id, 'name' => "{$board->display_name} ({$board->display_short_name})"])
            ->all();
    }

    /**
     * A source's approved papers, the latest year first.
     *
     * @return list<array{id: int, name: string, mcqs: int, cqs: int}>
     */
    public function papers(int $boardId): array
    {
        return BoardQuestionPaper::query()
            ->approved()
            ->where('board_id', $boardId)
            ->with(['board', 'classSubject.academicClass', 'classSubject.subject'])
            ->withCount(['mcqQuestions', 'cqQuestions'])
            ->orderByDesc('year')
            ->orderBy('unit')
            ->get()
            ->map(fn (BoardQuestionPaper $paper): array => [
                'id' => $paper->id,
                'name' => collect([$paper->title(), $paper->classSubject?->label()])->filter()->unique()->join(' — '),
                'mcqs' => $paper->mcq_questions_count,
                'cqs' => $paper->cq_questions_count,
            ])
            ->all();
    }

    /**
     * One approved paper whole — null when there is no such paper.
     *
     * @return array<string, mixed>|null
     */
    public function paper(int $paperId): ?array
    {
        $paper = BoardQuestionPaper::query()
            ->approved()
            ->with(['board', 'mcqStimuli', 'mcqQuestions', 'cqQuestions.parts'])
            ->find($paperId);

        if (! $paper) {
            return null;
        }

        return [
            'id' => $paper->id,
            'source' => $paper->board->only(['name', 'name_bn', 'short_name', 'short_name_bn']) + ['type' => $paper->board->type->value],
            'year' => $paper->year,
            'unit' => $paper->unit,
            'duration_minutes' => $paper->duration_minutes,
            'negative_marks' => $paper->negative_marks,
            'stimuli' => $paper->mcqStimuli->map(fn (BoardMcqStimulus $stimulus): array => ['id' => $stimulus->id, ...self::stimulusData($stimulus)])->all(),
            'mcqs' => $paper->mcqQuestions->map(fn (BoardMcqQuestion $question): array => [
                ...self::mcqData($question),
                'stimulus_id' => $question->stimulus_id,
                'stimulus_order' => $question->stimulus_order,
                'order_index' => $question->order_index,
            ])->all(),
            'cqs' => $paper->cqQuestions->map(fn (BoardCqQuestion $question): array => [
                'question_text' => $question->question_text,
                'editor_mode' => $question->editor_mode?->value,
                'question_image' => $question->question_image,
                'order_index' => $question->order_index,
                'parts' => $question->parts->map(fn (BoardCqQuestionPart $part): array => [
                    ...$part->only(['part_order', 'part_text', 'part_image', 'answer', 'marks']),
                    'part_type' => $part->part_type->value,
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * Where an image asked for is on the public disk — null unless it is an
     * image file that is there.
     */
    public function imagePath(mixed $path): ?string
    {
        return QuestionSync::isImagePath($path) && Storage::disk('public')->exists($path) ? $path : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function bankQuestionData(Question $question): array
    {
        return [
            'origin_id' => $question->parent_id ?? $question->id,
            'version' => (int) $question->version,
            'question_type' => $question->question_type->value,
            'question_text' => $question->question_text,
            'editor_mode' => $question->editor_mode?->value,
            'question_image' => $question->question_image,
            'difficulty' => $question->difficulty?->value,
            'topic' => $question->topic?->name,
            'stimulus_order' => $question->stimulus_order,
            ...($question->question_type === QuestionType::Mcq
                ? self::mcqData($question)
                : [
                    'marks' => $question->marks,
                    'cq_parts' => $question->cqParts->map(fn (QuestionCqPart $part): array => [
                        ...$part->only(['part_order', 'part_text', 'part_image', 'marks']),
                        'part_type' => $part->part_type->value,
                    ])->all(),
                ]),
        ];
    }

    /**
     * What an MCQ of either kind shares: its options with the right ones
     * marked, its explanation and its marks — and, for a past paper's, its
     * own text.
     *
     * @return array<string, mixed>
     */
    private static function mcqData(Question|BoardMcqQuestion $question): array
    {
        return [
            'question_text' => $question->question_text,
            'editor_mode' => $question->editor_mode?->value,
            'question_image' => $question->question_image,
            'options' => $question->options,
            'explanation' => $question->explanation,
            'explanation_editor_mode' => $question->explanation_editor_mode?->value,
            'marks' => $question->marks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function stimulusData(QuestionStimulus|BoardMcqStimulus $stimulus): array
    {
        return [
            'text' => $stimulus->text,
            'editor_mode' => $stimulus->editor_mode?->value,
            'image' => $stimulus->image,
        ];
    }

    /**
     * @param  array<int, string>  $options
     * @return list<array{id: int, name: string}>
     */
    private static function asList(array $options): array
    {
        return collect($options)->map(fn (string $name, int $id): array => ['id' => $id, 'name' => $name])->values()->all();
    }

    /**
     * @throws ValidationException
     */
    private static function ensureCanManage(User $admin): void
    {
        if (! QuestionSync::isSource() || ! QuestionSync::canManage($admin)) {
            throw ValidationException::withMessages(['hours' => __('Only the Super Admin of the live server can open or close the sharing.')]);
        }
    }
}
