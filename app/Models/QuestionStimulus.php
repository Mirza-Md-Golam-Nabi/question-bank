<?php

namespace App\Models;

use App\Models\Concerns\IsStimulus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stimulus of the question bank, shared by two or three MCQs of one
 * chapter. It has no status of its own: it is seen wherever — and only
 * where — its questions are (CLAUDE.md rule 2), and is approved, rejected
 * and versioned together with them (App\Services\StimulusGroupService).
 */
#[Fillable(['chapter_id', 'text', 'editor_mode', 'image', 'created_by'])]
class QuestionStimulus extends Model
{
    use HasFactory, IsStimulus;

    /**
     * How few and how many MCQs share one stimulus — the same for a group
     * typed into the form or pasted as JSON, in the question bank or on a
     * past paper (one question needs no shared stimulus).
     */
    public const MIN_QUESTIONS = 2;

    public const MAX_QUESTIONS = 6;

    protected $table = 'question_stimuli';

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
