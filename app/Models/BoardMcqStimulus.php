<?php

namespace App\Models;

use App\Models\Concerns\IsStimulus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stimulus of a past paper, shared by two or three of its MCQs — part of
 * the paper, approved with it, and removed with its questions.
 */
#[Fillable(['board_question_paper_id', 'text', 'editor_mode', 'image'])]
class BoardMcqStimulus extends Model
{
    use HasFactory, IsStimulus;

    protected $table = 'board_mcq_stimuli';

    public function boardQuestionPaper(): BelongsTo
    {
        return $this->belongsTo(BoardQuestionPaper::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(BoardMcqQuestion::class, 'stimulus_id')->orderBy('stimulus_order');
    }
}
