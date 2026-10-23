<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sending of a School exam's MCQ marks to the school management
 * software's results (SchoolResultPusher) — kept as the record of what was
 * sent where, by whom, and what the software did with it.
 */
#[Fillable([
    'exam_id', 'sent_by', 'school_class_id', 'school_class_name', 'school_exam_id', 'school_exam_name',
    'school_subject_id', 'school_subject_name', 'school_full_marks', 'in_proportion', 'overwrite',
    'sent_count', 'saved_count', 'skipped',
])]
class SchoolResultPush extends Model
{
    protected function casts(): array
    {
        return [
            'school_full_marks' => 'float',
            'in_proportion' => 'boolean',
            'overwrite' => 'boolean',
            'skipped' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
