<?php

namespace App\Models;

use App\Enums\EditorMode;
use App\Enums\QuestionType;
use App\Models\Concerns\HasMcqOptions;
use App\Models\Concerns\SharesStimulus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One MCQ of a past paper. Shaped like a question-bank MCQ (text, image,
 * `options[]` with `is_correct`, marks), so the same exam, result and print
 * views show either — but it is never one of them (CLAUDE.md rule 5).
 */
#[Fillable([
    'board_question_paper_id', 'stimulus_id', 'stimulus_order', 'question_text', 'editor_mode', 'question_image',
    'options', 'explanation', 'explanation_editor_mode', 'marks', 'order_index',
])]
class BoardMcqQuestion extends Model
{
    use HasFactory, HasMcqOptions, SharesStimulus;

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'editor_mode' => EditorMode::class,
            'explanation_editor_mode' => EditorMode::class,
        ];
    }

    public function boardQuestionPaper(): BelongsTo
    {
        return $this->belongsTo(BoardQuestionPaper::class);
    }

    /**
     * The stimulus this MCQ shares with the others under it, if any.
     */
    public function stimulus(): BelongsTo
    {
        return $this->belongsTo(BoardMcqStimulus::class, 'stimulus_id');
    }

    /**
     * The order QuestionBlocks lays a paper's MCQs out in, in the database,
     * for a list that pages through them: a stimulus's questions together,
     * in their own order, at the place the first of them comes.
     */
    public function scopeInPaperOrder(Builder $query): Builder
    {
        return $query->reorder()
            ->orderByRaw('COALESCE((SELECT MIN(siblings.order_index) FROM board_mcq_questions AS siblings WHERE siblings.stimulus_id = board_mcq_questions.stimulus_id), order_index)')
            ->orderBy('stimulus_id')
            ->orderBy('stimulus_order')
            ->orderBy('id');
    }

    /**
     * Always an MCQ — what the shared question views ask.
     */
    protected function questionType(): Attribute
    {
        return Attribute::get(fn (): QuestionType => QuestionType::Mcq);
    }
}
