<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use App\Observers\BoardQuestionPaperObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'board_id', 'class_subject_id', 'year', 'status',
    'created_by', 'approved_by', 'rejection_reason',
])]
#[ObservedBy(BoardQuestionPaperObserver::class)]
class BoardQuestionPaper extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => QuestionStatus::class,
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function mcqQuestions(): HasMany
    {
        return $this->hasMany(BoardMcqQuestion::class)->orderBy('order_index');
    }

    public function cqQuestions(): HasMany
    {
        return $this->hasMany(BoardCqQuestion::class)->orderBy('order_index');
    }

    public function approve(User $approver): void
    {
        $this->forceFill([
            'status' => QuestionStatus::Approved,
            'approved_by' => $approver->id,
            'rejection_reason' => null,
        ])->save();
    }

    public function reject(User $rejecter, string $reason): void
    {
        $this->forceFill([
            'status' => QuestionStatus::Rejected,
            'approved_by' => null,
            'rejection_reason' => $reason,
        ])->save();
    }
}
