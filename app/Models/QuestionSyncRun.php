<?php

namespace App\Models;

use App\Enums\QuestionSyncRunKind;
use App\Enums\QuestionSyncRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One fetch from the live server on the test server
 * (App\Services\QuestionSyncImporter): what was asked for, where it goes,
 * and — as it runs and once it is over — how many questions it added,
 * updated or left as they were.
 */
#[Fillable([
    'kind', 'remote_id', 'remote_label', 'chapter_id', 'class_subject_id', 'board_question_paper_id',
    'status', 'counts', 'message', 'started_by', 'finished_at',
])]
class QuestionSyncRun extends Model
{
    /**
     * What a run counts, all at 0 to begin with.
     *
     * @var array<string, int>
     */
    public const EMPTY_COUNTS = [
        'added' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'skipped' => 0,
        'new_topics' => 0,
        'images' => 0,
        'missing_images' => 0,
    ];

    protected function casts(): array
    {
        return [
            'kind' => QuestionSyncRunKind::class,
            'status' => QuestionSyncRunStatus::class,
            'counts' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function boardQuestionPaper(): BelongsTo
    {
        return $this->belongsTo(BoardQuestionPaper::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * How many of one thing so far.
     */
    public function countOf(string $key): int
    {
        return (int) ($this->counts[$key] ?? 0);
    }
}
