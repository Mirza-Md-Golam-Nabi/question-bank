<?php

namespace App\Models;

use App\Enums\EditorMode;
use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One creative question of a past paper: the stimulus here, its four
 * sub-questions (each with an optional answer) in `parts`. Its marks are the
 * parts' sum, kept in step by BoardCqQuestionPart.
 */
#[Fillable(['board_question_paper_id', 'question_text', 'editor_mode', 'question_image', 'marks', 'order_index'])]
class BoardCqQuestion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'editor_mode' => EditorMode::class,
        ];
    }

    public function boardQuestionPaper(): BelongsTo
    {
        return $this->belongsTo(BoardQuestionPaper::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(BoardCqQuestionPart::class)->orderBy('part_order');
    }

    /**
     * The sub-questions under the name a question-bank CQ gives them, so
     * the shared question views read either.
     */
    public function cqParts(): HasMany
    {
        return $this->parts();
    }

    /**
     * Always a CQ — what the shared question views ask.
     */
    protected function questionType(): Attribute
    {
        return Attribute::get(fn (): QuestionType => QuestionType::Cq);
    }
}
