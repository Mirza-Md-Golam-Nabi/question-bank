<?php

namespace App\Models;

use App\Models\Concerns\IsStimulus;
use App\Services\QuestionDuplicateScreening;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stimulus of the question bank, over one or more MCQs of one
 * chapter. It has no status of its own: it is seen wherever — and only
 * where — its questions are (CLAUDE.md rule 2), and is approved, rejected
 * and versioned together with them (App\Services\StimulusGroupService).
 */
#[Fillable(['chapter_id', 'text', 'editor_mode', 'image', 'created_by'])]
class QuestionStimulus extends Model
{
    use HasFactory, IsStimulus;

    /**
     * How few MCQs stand under one stimulus — the same for a group typed
     * into the form or pasted as JSON, in the question bank or on a past
     * paper. There is no upper limit.
     */
    public const MIN_QUESTIONS = 1;

    protected $table = 'question_stimuli';

    /**
     * A stimulus is part of each of its questions: changing it has them
     * checked for duplicates again.
     */
    protected static function booted(): void
    {
        static::updated(function (self $stimulus): void {
            if ($stimulus->wasChanged(['text', 'image'])) {
                $stimulus->questions()->where('is_latest', true)->get()->each(QuestionDuplicateScreening::queue(...));
            }
        });
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Every question that has stood under this stimulus, in their order —
     * old versions included; ask for the latest where that matters.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'stimulus_id')->orderBy('stimulus_order');
    }
}
