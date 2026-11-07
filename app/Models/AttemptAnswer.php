<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answer on an attempt: to a question-bank question, or to a past
 * paper's MCQ — on an exam made of the whole paper, or on a practice exam
 * of mistakes that holds both kinds (columnFor()).
 */
#[Fillable(['attempt_id', 'question_id', 'board_mcq_question_id', 'student_answer', 'is_correct', 'obtained_marks'])]
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
     * The column an answer to this question goes in.
     */
    public static function columnFor(Model $question): string
    {
        return $question instanceof BoardMcqQuestion ? 'board_mcq_question_id' : 'question_id';
    }

    /**
     * The key an exam's answer to this question goes by — on the paper's
     * fields, in the answers sent and kept, and on the result: "12" for a
     * question-bank question, "p12" for a past paper's MCQ.
     */
    public static function keyFor(Model $question): string
    {
        return ($question instanceof BoardMcqQuestion ? self::PAPER_KEY_PREFIX : '').$question->getKey();
    }

    /**
     * The same key, read off this answer.
     */
    public function key(): string
    {
        return $this->board_mcq_question_id !== null
            ? self::PAPER_KEY_PREFIX.$this->board_mcq_question_id
            : (string) $this->question_id;
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
