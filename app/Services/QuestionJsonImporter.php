<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\QuestionCqPart;
use App\Models\QuestionStimulus;
use App\Models\Topic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Adds many questions to one chapter at once from pasted JSON. Reading and
 * judging the JSON is QuestionJsonReader's, and what both importers do with
 * it ImportedQuestions'; this says only what is the chapter's own: its
 * models, its topic and difficulty, and who has duplicates left to the Admin.
 *
 * Nothing about a question's fate is decided here: each one is created the
 * ordinary way, so it starts pending or approved exactly as a question
 * typed into the form would (QuestionObserver), and a CQ's marks are still
 * the sum of its parts.
 */
class QuestionJsonImporter
{
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
            throw ValidationException::withMessages([QuestionJsonReader::ERROR_KEY => [__('The chosen topic does not belong to this chapter.')]]);
        }

        return ImportedQuestions::build(
            $items,
            fn (array $item): Question => $this->build($item, $chapter, $topicId, $defaultDifficulty),
            fn (string $text, ?string $image): QuestionStimulus => new QuestionStimulus([
                'chapter_id' => $chapter->id,
                'created_by' => Auth::id(),
                ...ImportedQuestions::stimulusAttributes($text, $image),
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
     * Saves the questions of the JSON as the signed-in user's own. A
     * possible duplicate is only added when accepted — a stimulus's
     * questions always together. One already there as it is is added when
     * it goes through the background duplicate check
     * (QuestionDuplicateScreening), which holds it for the Admin; a
     * School's own are not checked there, so for a School it is left out.
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
        $matches = $this->duplicates($questions, $chapter);

        if (QuestionDuplicateScreening::screensQuestionsBy(Auth::user())) {
            $matches = ImportedQuestions::withoutExact($matches);
        }

        $result = ImportedQuestions::save($questions, $matches, $accepted, $images, self::IMAGE_DIRECTORY);

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
            'difficulty' => $item['difficulty'] ?? $defaultDifficulty,
            ...ImportedQuestions::questionAttributes($item),
        ]);

        if ($item['type'] === QuestionType::Cq) {
            return $question->setRelation('cqParts', collect($item['parts'])->map(
                fn (array $part): QuestionCqPart => new QuestionCqPart(collect($part)->except('answer')->all()),
            ));
        }

        return $question
            ->fill(ImportedQuestions::mcqAttributes($item))
            ->setRelation('cqParts', collect());
    }
}
