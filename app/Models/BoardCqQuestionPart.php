<?php

namespace App\Models;

use App\Enums\CqPartType;
use App\Models\Concerns\SyncsMarksFromParts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['board_cq_question_id', 'part_type', 'part_order', 'part_text', 'part_image', 'marks'])]
class BoardCqQuestionPart extends Model
{
    use HasFactory, SyncsMarksFromParts;

    protected function casts(): array
    {
        return [
            'part_type' => CqPartType::class,
        ];
    }

    public function boardCqQuestion(): BelongsTo
    {
        return $this->belongsTo(BoardCqQuestion::class);
    }

    protected function parentForMarksSync(): ?Model
    {
        return $this->boardCqQuestion;
    }

    protected function partsForMarksSync(): HasMany
    {
        return $this->boardCqQuestion->parts();
    }
}
