<?php

namespace App\Models;

use App\Services\DuplicateMatch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A question already in the chapter that a question looked like when it
 * was checked in the background (QuestionDuplicateScreening) — kept for
 * the Admin to compare until they decide.
 */
#[Fillable(['question_id', 'matched_question_id', 'percent', 'is_exact'])]
class QuestionDuplicateMatch extends Model
{
    protected function casts(): array
    {
        return [
            'percent' => 'integer',
            'is_exact' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function matchedQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'matched_question_id')->withTrashed();
    }

    /**
     * As the duplicate warnings show a match.
     */
    public function toDuplicateMatch(): DuplicateMatch
    {
        return new DuplicateMatch($this->matchedQuestion, $this->percent, $this->is_exact);
    }
}
