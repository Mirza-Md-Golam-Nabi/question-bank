<?php

namespace App\Models;

use App\Models\Concerns\HasOrderIndex;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'short_name', 'order_index'])]
class Board extends Model
{
    use HasFactory, HasOrderIndex;

    public function questionPapers(): HasMany
    {
        return $this->hasMany(BoardQuestionPaper::class);
    }
}
