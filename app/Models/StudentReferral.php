<?php

namespace App\Models;

use App\Enums\ReferralSource;
use Database\Factories\StudentReferralFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stretch of time a student is under one referrer (a Teacher, a Student
 * or a Staff member), from `starts_at` until `ends_at`. Only one is in force
 * for a student at a time; once it ends nothing is deleted — the next code
 * or exam link simply starts a new one. The rules for when one may start or
 * change are StudentReferralService's.
 */
#[Fillable(['student_id', 'referrer_id', 'source', 'exam_id', 'starts_at', 'changeable_until', 'ends_at'])]
class StudentReferral extends Model
{
    /** @use HasFactory<StudentReferralFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'source' => ReferralSource::class,
            'starts_at' => 'datetime',
            'changeable_until' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * In force right now.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    /**
     * Whether the student may still swap this referrer for another code.
     */
    public function isChangeable(): bool
    {
        return $this->changeable_until !== null && $this->changeable_until->isFuture();
    }

    /**
     * Whole days left before the referral ends (the last, partial day
     * counted), for "N days left".
     */
    public function daysLeft(): int
    {
        return max(0, (int) ceil(now()->diffInMinutes($this->ends_at, absolute: false) / (60 * 24)));
    }
}
