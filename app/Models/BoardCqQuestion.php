<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['board_question_paper_id', 'question_text', 'question_image', 'marks', 'order_index'])]
class BoardCqQuestion extends Model
{
    use HasFactory;

    public function boardQuestionPaper(): BelongsTo
    {
        return $this->belongsTo(BoardQuestionPaper::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(BoardCqQuestionPart::class)->orderBy('part_order');
    }
}
