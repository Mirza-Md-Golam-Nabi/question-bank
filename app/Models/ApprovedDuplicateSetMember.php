<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One approved question of a set of alike ones (ApprovedDuplicateSet), and
 * how alike it is to the question of the set it is most like.
 */
#[Fillable(['approved_duplicate_set_id', 'question_id', 'percent', 'is_exact'])]
class ApprovedDuplicateSetMember extends Model
{
    protected function casts(): array
    {
        return [
            'percent' => 'integer',
            'is_exact' => 'boolean',
        ];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(ApprovedDuplicateSet::class, 'approved_duplicate_set_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
