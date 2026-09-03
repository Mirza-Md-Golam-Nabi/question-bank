<?php

namespace App\Models;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\GenerationMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'title', 'created_by', 'exam_type', 'generation_mode', 'subject_id',
    'duration_minutes', 'start_time', 'end_time', 'total_marks', 'status',
    'share_token', 'link_expires_at', 'is_link_active',
])]
class Exam extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'exam_type' => ExamType::class,
            'generation_mode' => GenerationMode::class,
            'status' => ExamStatus::class,
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'link_expires_at' => 'datetime',
            'is_link_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot('marks_override', 'order_index')
            ->orderByPivot('order_index');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /**
     * Exams created by a given user this calendar month, scoped to one
     * exam_type — the single query CLAUDE.md rule 7's monthly free-limit
     * check is built on (Teacher exams and Student self-practice exams
     * count separately, but Auto-Generate/Manual self-practice count together
     * since neither filters on generation_mode).
     */
    public function scopeCreatedThisMonthBy(Builder $query, User $user, ExamType $examType): Builder
    {
        return $query->where('created_by', $user->id)
            ->where('exam_type', $examType)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    public function publish(): void
    {
        $this->forceFill([
            'status' => ExamStatus::Published,
            'share_token' => $this->share_token ?? ($this->exam_type === ExamType::TeacherExam ? Str::random(32) : null),
        ])->save();
    }

    public function recalculateTotalMarks(): void
    {
        $total = $this->questions->sum(fn (Question $question) => $question->pivot->marks_override ?? $question->marks);

        $this->newQuery()->whereKey($this->id)->update(['total_marks' => $total]);
    }
}
