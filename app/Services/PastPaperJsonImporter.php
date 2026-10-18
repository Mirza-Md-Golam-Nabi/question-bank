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
     * The JSON as the paper's questions it describes — built but not saved,
     * which is also what the preview shows.
     *
     * @return Collection<int, BoardMcqQuestion|BoardCqQuestion>
     *
     * @throws ValidationException listing every problem found
     */
    public function parse(string $json, BoardQuestionPaper $paper, QuestionType $type): Collection
    {
        return ImportedQuestions::build(
            self::reader($type)->read($json),
            fn (array $item): Model => $this->build($item, $paper),
            fn (string $text): BoardMcqStimulus => new BoardMcqStimulus([
                'board_question_paper_id' => $paper->id,
                'text' => $text,
                'editor_mode' => EditorMode::CkEditor,
            ]),
        );
    }

    /**
     * Saves the questions at the end of the paper's list. A question the
     * list already has — or that is in the JSON twice — is left out rather
     * than added again.
     *
     * @return array{added: int, skipped: int}
     *
     * @throws ValidationException
     */
    public function import(string $json, BoardQuestionPaper $paper, QuestionType $type): array
    {
        Gate::authorize('import', $paper);

        $questions = $this->parse($json, $paper, $type);
        $list = $type === QuestionType::Mcq ? $paper->mcqQuestions() : $paper->cqQuestions();

        $alreadyThere = (clone $list)
            ->whereIn('question_text', $questions->pluck('question_text'))
            ->pluck('question_text')
            ->flip();

        $added = DB::transaction(function () use ($questions, $alreadyThere, $list): int {
            $nextOrder = (int) (clone $list)->max('order_index');

            return ImportedQuestions::save($questions, $alreadyThere, function (Model $question) use (&$nextOrder): void {
                $parts = $question->relationLoaded('cqParts') ? $question->cqParts : collect();
                $question->unsetRelation('cqParts');
                $question->order_index = ++$nextOrder;
                $question->save();

                if ($question instanceof BoardCqQuestion) {
                    $question->parts()->saveMany($parts);
                }
            });
        });

        $paper->markEditedBy(Auth::user());

        Log::info('Past paper questions imported from JSON.', [
            'imported_by' => Auth::id(),
            'board_question_paper_id' => $paper->id,
            'type' => $type->value,
            'added' => $added,
            'skipped' => $questions->count() - $added,
        ]);

        return ['added' => $added, 'skipped' => $questions->count() - $added];
    }

    /**
     * A past paper's format for one of its lists: only that kind of
     * question, with explanations/answers, without a difficulty.
     */
    public static function reader(QuestionType $type): QuestionJsonReader
    {
        return new QuestionJsonReader(onlyType: $type, withAnswers: true, withDifficulty: false);
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
