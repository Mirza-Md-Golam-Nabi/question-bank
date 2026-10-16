<?php

namespace App\Models;

use App\Enums\PaperSourceType;
use App\Models\Concerns\HasOrderIndex;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A source of past papers: an education board, or — by its `type` — a
 * medical or university admission test, a job exam, or a school/college's
 * test exam. Its papers are BoardQuestionPapers, a subsystem of their own
 * (CLAUDE.md rule 5).
 */
#[Fillable(['name', 'short_name', 'type', 'order_index'])]
class Board extends Model
{
    use HasFactory, HasOrderIndex;

    protected $attributes = [
        'type' => 'board',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaperSourceType::class,
        ];
    }

    public function questionPapers(): HasMany
    {
        return $this->hasMany(BoardQuestionPaper::class);
    }
}
