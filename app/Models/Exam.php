<?php

namespace App\Models;

use App\Enums\ExamDeliveryMode;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\GenerationMode;
use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'title', 'created_by', 'exam_type', 'generation_mode', 'delivery_mode', 'subject_id', 'class_subject_id',
    'duration_minutes', 'start_time', 'end_time', 'total_marks', 'status',
    'share_token', 'link_expires_at', 'is_link_active', 'answers_released_at',
])]
class Exam extends Model
{
    use HasFactory;

    /**
     * Mirrors the column default, so a freshly created (not yet reloaded)
     * exam already knows its delivery mode.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'delivery_mode' => ExamDeliveryMode::Online->value,
    ];

    protected function casts(): array
    {
        return [
            'exam_type' => ExamType::class,
            'generation_mode' => GenerationMode::class,
            'delivery_mode' => ExamDeliveryMode::class,
            'status' => ExamStatus::class,
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'link_expires_at' => 'datetime',
            'is_link_active' => 'boolean',
            'answers_released_at' => 'datetime',
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

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot('marks_override', 'order_index')
            ->orderByPivot('order_index');
    }

    /**
     * The questions a student actually sees on the share-link. An
     * "Online + Offline" exam keeps its CQ questions for the printed paper
     * only (CLAUDE.md rule 10), so they are left out here.
     *
     * Depends on this exam's own delivery_mode, so it can't be eager-loaded
     * — read it off a loaded Exam (`$exam->onlineQuestions`).
     */
    public function onlineQuestions(): BelongsToMany
    {
        return $this->questions()->when(
            $this->delivery_mode === ExamDeliveryMode::Both,
            fn (Builder $query) => $query->where('questions.question_type', QuestionType::Mcq),
        );
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

    /**
     * Whether a student may see the questions, the correct answers and
     * their own answers on the result — rather than only their score.
     *
     * A teacher's exam keeps them hidden until the teacher releases them:
     * otherwise anyone could hand in a blank paper, read off the answer
     * key, and sit the same exam again (a guest only needs another name).
     * A self-practice exam belongs to the one student who generated it, so
     * there is nobody to leak the answers to.
     */
    public function showsAnswersToStudents(): bool
    {
        return $this->exam_type === ExamType::SelfPractice || $this->answers_released_at !== null;
    }

    public function releaseAnswers(): void
    {
        $this->forceFill(['answers_released_at' => now()])->save();
    }

    public function hideAnswers(): void
    {
        $this->forceFill(['answers_released_at' => null])->save();
    }

    public function publish(): void
    {
        $this->forceFill([
            'status' => ExamStatus::Published,
            'share_token' => $this->share_token ?? ($this->exam_type === ExamType::TeacherExam ? Str::random(32) : null),
        ])->save();
    }

    /**
     * total_marks is what an online attempt is scored out of, so an exam
     * that is also taken online only counts the questions shown there; a
     * print-only exam counts everything on the paper.
     */
    public function recalculateTotalMarks(): void
    {
        $questions = $this->delivery_mode === ExamDeliveryMode::Offline ? $this->questions : $this->onlineQuestions;

        $total = $questions->sum(fn (Question $question) => $question->pivot->marks_override ?? $question->marks);

        $this->newQuery()->whereKey($this->id)->update(['total_marks' => $total]);
    }
}
