<?php

namespace App\Services;

use App\Enums\EditorMode;
use App\Enums\QuestionType;
use App\Models\BoardCqQuestion;
use App\Models\BoardCqQuestionPart;
use App\Models\BoardMcqQuestion;
use App\Models\BoardMcqStimulus;
use App\Models\BoardQuestionPaper;
use App\Models\Question;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Adds many questions to a past paper at once from pasted JSON — into its
 * MCQ list or its CQ list, one kind at a time. Reading and judging the
 * JSON is QuestionJsonReader's (the same format as the question bank's,
 * with optional explanations and CQ answers and no difficulty); this turns
 * what it read into the paper's own questions, after those already there.
 *
 * Images are added afterwards, by editing a question.
 */
class PastPaperJsonImporter
{
    /**
     * The field of the import form the problems are reported against.
     */
    public const ERROR_KEY = QuestionJsonReader::ERROR_KEY;

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
                'text' => $text,
                'image' => $image,
                'editor_mode' => EditorMode::CkEditor,
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

        $result = DB::transaction(function () use ($questions, $matches, $accepted, $list, $images): array {
            $nextOrder = (int) (clone $list)->max('order_index');

            return ImportedQuestions::save($questions, $matches, $accepted, function (Model $question) use (&$nextOrder): void {
                $parts = $question->relationLoaded('cqParts') ? $question->cqParts : collect();
                $question->unsetRelation('cqParts');
                $question->order_index = ++$nextOrder;
                $question->save();

                if ($question instanceof BoardCqQuestion) {
                    $question->parts()->saveMany($parts);
                }
            }, fn (Model $model) => $images->publish($model, self::IMAGE_DIRECTORY));
        });

        $images->discard();
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
            'question_text' => $item['question'],
            // The formulas are CKEditor's kind of content, so that is the
            // editor the question opens in when it is edited later.
            'editor_mode' => EditorMode::CkEditor,
            'question_image' => $item['image'],
        ];

        if ($item['type'] === QuestionType::Cq) {
            return (new BoardCqQuestion($common))->setRelation('cqParts', collect($item['parts'])->map(
                fn (array $part): BoardCqQuestionPart => new BoardCqQuestionPart($part),
            ));
        }

        return new BoardMcqQuestion([
            ...$common,
            'options' => $item['options'],
            'explanation' => $item['explanation'],
            'marks' => $item['marks'] ?? Question::DEFAULT_MCQ_MARKS,
        ]);
    }
}
