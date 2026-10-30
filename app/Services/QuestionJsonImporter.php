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
use Illuminate\Database\Eloquent\Model;
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
     * Where the images of imported questions are kept.
     */
    public const IMAGE_DIRECTORY = 'questions';

    /**
     * The JSON as the questions it describes — built but not saved, which is
     * also what the preview shows — with the images given with it.
     *
     * @return Collection<int, Question>
     *
     * @throws ValidationException listing every problem found
     */
    public function parse(string $json, Chapter $chapter, int|string|null $topicId, Difficulty $defaultDifficulty, ?ImportedImages $images = null): Collection
    {
        $items = self::reader()->read($json);

        if (filled($topicId) && ! Topic::where('chapter_id', $chapter->id)->whereKey($topicId)->exists()) {
            throw ValidationException::withMessages([self::ERROR_KEY => [__('The chosen topic does not belong to this chapter.')]]);
        }

        return ImportedQuestions::build(
            $items,
            fn (array $item): Question => $this->build($item, $chapter, $topicId, $defaultDifficulty),
            fn (string $text, ?string $image): QuestionStimulus => new QuestionStimulus([
                'chapter_id' => $chapter->id,
                'text' => $text,
                'image' => $image,
                'editor_mode' => EditorMode::CkEditor,
                'created_by' => Auth::id(),
            ]),
            $images ?? ImportedImages::none(),
        );
    }

    /**
     * Which of the built questions duplicate a question the signed-in user
     * can already see in this chapter, or one earlier in the JSON (see
     * ImportedQuestions::duplicates()).
     *
     * @param  Collection<int, Question>  $questions  What parse() built.
     * @return array<int, DuplicateMatch>
     */
    public function duplicates(Collection $questions, Chapter $chapter): array
    {
        return ImportedQuestions::duplicates(
            $questions,
            Question::query()->duplicateCandidatesIn($chapter->id, Auth::user())->get(),
            DuplicateQuestionFinder::make(),
        );
    }

    /**
     * Saves the questions of the JSON as the signed-in user's own — every
     * one of them when they go through the background duplicate check
     * (QuestionDuplicateScreening), which holds what looks like a question
     * already there for the Admin. A School's own are not checked there, so
     * for a School one already there as it is is left out, and a possible
     * duplicate only added when accepted — a stimulus's questions always
     * together.
     *
     * @param  array<int, int>  $accepted  The possible duplicates to add anyway, by the place of their (unit's first) question.
     * @return array{added: int, skipped: int, possible_duplicates: int}
     *
     * @throws ValidationException
     */
    public function import(string $json, Chapter $chapter, int|string|null $topicId, Difficulty $defaultDifficulty, array $accepted = [], ?ImportedImages $images = null): array
    {
        Gate::authorize('import', Question::class);

        $images ??= ImportedImages::none();
        $questions = $this->parse($json, $chapter, $topicId, $defaultDifficulty, $images);
        $matches = QuestionDuplicateScreening::screensQuestionsBy(Auth::user()) ? [] : $this->duplicates($questions, $chapter);

        $result = DB::transaction(fn (): array => ImportedQuestions::save($questions, $matches, $accepted, function (Question $question): void {
            $parts = $question->cqParts;
            $question->unsetRelation('cqParts');
            $question->save();
            $question->cqParts()->saveMany($parts);
        }, fn (Model $model) => $images->publish($model, self::IMAGE_DIRECTORY)));

        $images->discard();

        Log::info('Questions imported from JSON.', [
            'imported_by' => Auth::id(),
            'chapter_id' => $chapter->id,
            ...$result,
        ]);

        return $result;
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
     * difficulty; no CQ answers.
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
            'question_image' => $item['image'],
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
                'explanation' => $item['explanation'],
                // Its formulas (and tables) are CKEditor's kind of content too.
                'explanation_editor_mode' => EditorMode::CkEditor,
                'marks' => $item['marks'] ?? Question::DEFAULT_MCQ_MARKS,
            ])
            ->setRelation('cqParts', collect());
    }
}
