<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answer on an attempt: to a question-bank question, to a past paper's
 * MCQ — on an exam made of the whole paper, or on a practice exam of
 * mistakes that holds both kinds — or to an OMR check's question, by its
 * number (columnFor()).
 */
#[Fillable(['attempt_id', 'question_id', 'board_mcq_question_id', 'answer_key_number', 'student_answer', 'is_correct', 'obtained_marks'])]
class AttemptAnswer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    /**
     * What marks a past paper's MCQ among an exam's answers: question ids of
     * the two tables can be the same number, so one of them carries this.
     */
    public const PAPER_KEY_PREFIX = 'p';

    /**
     * What marks an OMR check's question (AnswerKeyQuestion), which is known
     * only by its number.
     */
    public const ANSWER_KEY_PREFIX = 'k';

    /**
     * The column an answer to this question goes in.
     */
    public static function columnFor(Model $question): string
    {
        return match (true) {
            $question instanceof BoardMcqQuestion => 'board_mcq_question_id',
            $question instanceof AnswerKeyQuestion => 'answer_key_number',
            default => 'question_id',
        };
    }

    /**
     * The key an exam's answer to this question goes by — on the paper's
     * fields, in the answers sent and kept, and on the result: "12" for a
     * question-bank question, "p12" for a past paper's MCQ, "k12" for an
     * OMR check's question 12.
     */
    public static function keyFor(Model $question): string
    {
        return match (true) {
            $question instanceof BoardMcqQuestion => self::PAPER_KEY_PREFIX,
            $question instanceof AnswerKeyQuestion => self::ANSWER_KEY_PREFIX,
            default => '',
        }.$question->getKey();
    }

    /**
     * The same key, read off this answer.
     */
    public function key(): string
    {
        return match (true) {
            $this->board_mcq_question_id !== null => self::PAPER_KEY_PREFIX.$this->board_mcq_question_id,
            $this->answer_key_number !== null => self::ANSWER_KEY_PREFIX.$this->answer_key_number,
            default => (string) $this->question_id,
        };
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function boardMcqQuestion(): BelongsTo
    {
        return $this->belongsTo(BoardMcqQuestion::class);
    }
}
