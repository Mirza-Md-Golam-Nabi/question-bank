<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\EditorMode;
use App\Enums\QuestionType;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\QuestionCqPart;
use App\Models\QuestionStimulus;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Adds many questions to one chapter at once from pasted JSON. Reading and
 * judging the JSON is QuestionJsonReader's; this turns what it read into
 * the chapter's questions.
 *
 * Nothing about a question's fate is decided here: each one is created the
 * ordinary way, so it starts pending or approved exactly as a question
 * typed into the form would (QuestionObserver), and a CQ's marks are still
 * the sum of its parts.
 */
class QuestionJsonImporter
{
    /**
     * The field of the import form the problems are reported against.
     */
    public const ERROR_KEY = QuestionJsonReader::ERROR_KEY;

    /**
     * The JSON as the questions it describes — built but not saved, which is
     * also what the preview shows.
     *
     * @return Collection<int, Question>
     *
     * @throws ValidationException listing every problem found
     */
    public function parse(string $json, Chapter $chapter, int|string|null $topicId, Difficulty $defaultDifficulty): Collection
    {
        $items = self::reader()->read($json);

        if (filled($topicId) && ! Topic::where('chapter_id', $chapter->id)->whereKey($topicId)->exists()) {
            throw ValidationException::withMessages([self::ERROR_KEY => [__('The chosen topic does not belong to this chapter.')]]);
        }

        return ImportedQuestions::build(
            $items,
            fn (array $item): Question => $this->build($item, $chapter, $topicId, $defaultDifficulty),
            fn (string $text): QuestionStimulus => new QuestionStimulus([
                'chapter_id' => $chapter->id,
                'text' => $text,
                'editor_mode' => EditorMode::CkEditor,
                'created_by' => Auth::id(),
            ]),
        );
    }

    /**
     * Saves the questions of the JSON as the signed-in user's own. A
     * question that user can already see in this chapter — or that is in
     * the JSON twice — is left out rather than added again (a stimulus's
     * questions only when all of them are).
     *
     * @return array{added: int, skipped: int}
     *
     * @throws ValidationException
     */
    public function import(string $json, Chapter $chapter, int|string|null $topicId, Difficulty $defaultDifficulty): array
    {
        Gate::authorize('import', Question::class);

        $questions = $this->parse($json, $chapter, $topicId, $defaultDifficulty);
        $author = Auth::user();

        $alreadyThere = Question::query()
            ->where('chapter_id', $chapter->id)
            ->where(fn (Builder $query) => $query->visibleTo($author))
            ->whereIn('question_text', $questions->pluck('question_text'))
            ->pluck('question_text')
            ->flip();

        $added = DB::transaction(fn (): int => ImportedQuestions::save($questions, $alreadyThere, function (Question $question): void {
            $parts = $question->cqParts;
            $question->unsetRelation('cqParts');
            $question->save();
            $question->cqParts()->saveMany($parts);
        }));

        Log::info('Questions imported from JSON.', [
            'imported_by' => $author->id,
            'chapter_id' => $chapter->id,
            'added' => $added,
            'skipped' => $questions->count() - $added,
        ]);

        return ['added' => $added, 'skipped' => $questions->count() - $added];
    }

    /**
     * The guide to copy for the question bank's format.
     */
    public static function formatGuide(): string
    {
        return self::reader()->formatGuide();
    }

    /**
     * The example of the format, one MCQ and one CQ.
     */
    public static function sampleJson(): string
    {
        return self::reader()->sampleJson();
    }

    /**
     * The question bank's format: MCQs and CQs together, each with a
     * difficulty, no explanations.
     */
    private static function reader(): QuestionJsonReader
    {
        return new QuestionJsonReader;
    }

    /**
     * @param  array<string, mixed>  $item  One question as QuestionJsonReader read it.
     */
    private function build(array $item, Chapter $chapter, int|string|null $topicId, Difficulty $defaultDifficulty): Question
    {
        $question = new Question([
            'chapter_id' => $chapter->id,
            'topic_id' => filled($topicId) ? (int) $topicId : null,
            'question_type' => $item['type'],
            'question_text' => $item['question'],
            // The formulas are CKEditor's kind of content, so that is the
            // editor the question opens in when it is edited later.
            'editor_mode' => EditorMode::CkEditor,
            'difficulty' => $item['difficulty'] ?? $defaultDifficulty,
        ]);

        if ($item['type'] === QuestionType::Cq) {
            return $question->setRelation('cqParts', collect($item['parts'])->map(
                fn (array $part): QuestionCqPart => new QuestionCqPart(collect($part)->except('answer')->all()),
            ));
        }

        return $question
            ->fill([
                'options' => $item['options'],
                'marks' => $item['marks'] ?? Question::DEFAULT_MCQ_MARKS,
            ])
            ->setRelation('cqParts', collect());
    }
}
