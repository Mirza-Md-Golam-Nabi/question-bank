<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'board_question_paper_id', 'question_text', 'question_image',
    'options', 'correct_answer', 'marks', 'order_index',
])]
class BoardMcqQuestion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }

    public function boardQuestionPaper(): BelongsTo
    {
        return $this->belongsTo(BoardQuestionPaper::class);
    }
}
