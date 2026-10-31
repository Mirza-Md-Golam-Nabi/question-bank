<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\BoardCqQuestion;
use App\Models\BoardCqQuestionPart;
use App\Models\BoardMcqQuestion;
use App\Models\BoardMcqStimulus;
use App\Models\BoardQuestionPaper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Adds many questions to a past paper at once from pasted JSON — into its
 * MCQ list or its CQ list, one kind at a time. Reading and judging the
 * JSON is QuestionJsonReader's (the same format as the question bank's,
 * with optional explanations and CQ answers and no difficulty), and what
 * both importers do with it ImportedQuestions'; this says only what is the
 * paper's own: its models, and that they go after the questions already there.
 */
class PastPaperJsonImporter
{
    /**
     * Where the images of imported past-paper questions are kept.
     */
    public const IMAGE_DIRECTORY = 'board-questions';

    /**
     * The JSON as the paper's questions it describes — built but not saved,
     * which is also what the preview shows.
     *
     * @return Collection<int, BoardMcqQuestion|BoardCqQuestion>
     *
     * @throws ValidationException listing every problem found
     */
    public function parse(string $json, BoardQuestionPaper $paper, QuestionType $type, ?ImportedImages $images = null): Collection
    {
        return ImportedQuestions::build(
            self::reader($type)->read($json),
            fn (array $item): Model => $this->build($item, $paper),
            fn (string $text, ?string $image): BoardMcqStimulus => new BoardMcqStimulus([
                'board_question_paper_id' => $paper->id,
                ...ImportedQuestions::stimulusAttributes($text, $image),
            ]),
            $images ?? ImportedImages::none(),
        );
    }

    /**
     * Which of the built questions duplicate one already on this list of
     * the paper, or one earlier in the JSON (see
     * ImportedQuestions::duplicates()).
     *
     * @param  Collection<int, Model>  $questions  What parse() built.
     * @return array<int, DuplicateMatch>
     */
    public function duplicates(Collection $questions, BoardQuestionPaper $paper, QuestionType $type): array
    {
        return ImportedQuestions::duplicates($questions, $paper->duplicateCandidates($type), DuplicateQuestionFinder::make());
    }

    /**
     * Saves the questions at the end of the paper's list. One the list
     * already has as it is is left out; a possible duplicate only added
     * when the user accepted it.
     *
     * @param  array<int, int>  $accepted  The possible duplicates to add anyway, by the place of their (unit's first) question.
     * @return array{added: int, skipped: int, possible_duplicates: int}
     *
     * @throws ValidationException
     */
    public function import(string $json, BoardQuestionPaper $paper, QuestionType $type, array $accepted = [], ?ImportedImages $images = null): array
    {
        Gate::authorize('import', $paper);

        $images ??= ImportedImages::none();
        $questions = $this->parse($json, $paper, $type, $images);
        $matches = $this->duplicates($questions, $paper, $type);
        $list = $type === QuestionType::Mcq ? $paper->mcqQuestions() : $paper->cqQuestions();
        $lastOrder = null;

        $result = ImportedQuestions::save($questions, $matches, $accepted, $images, self::IMAGE_DIRECTORY, function (Model $question) use ($list, &$lastOrder): void {
            $lastOrder ??= (int) (clone $list)->max('order_index');
            $question->order_index = ++$lastOrder;
        });

        $paper->markEditedBy(Auth::user());

        Log::info('Past paper questions imported from JSON.', [
            'imported_by' => Auth::id(),
            'board_question_paper_id' => $paper->id,
            'type' => $type->value,
            ...$result,
        ]);

        return $result;
    }

    /**
     * A past paper's format for one of its lists: only that kind of
     * question, with CQ answers, without a difficulty.
     */
    public static function reader(QuestionType $type): QuestionJsonReader
    {
        return new QuestionJsonReader(onlyType: $type, withCqAnswers: true, withDifficulty: false);
    }

    /**
     * @param  array<string, mixed>  $item  One question as QuestionJsonReader read it.
     */
    private function build(array $item, BoardQuestionPaper $paper): BoardMcqQuestion|BoardCqQuestion
    {
        $common = [
            'board_question_paper_id' => $paper->id,
            ...ImportedQuestions::questionAttributes($item),
        ];

        if ($item['type'] === QuestionType::Cq) {
            return (new BoardCqQuestion($common))->setRelation('cqParts', collect($item['parts'])->map(
                fn (array $part): BoardCqQuestionPart => new BoardCqQuestionPart($part),
            ));
        }

        return new BoardMcqQuestion([...$common, ...ImportedQuestions::mcqAttributes($item)]);
    }
}
