<?php

namespace App\Models;

use App\Enums\ExamType;
use App\Enums\SubscriptionStatus;
use App\Services\PlanConditions;
use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One period of access bought from a plan. `conditions` is the plan's list
 * of conditions as it stood at purchase and `selections` the classes and
 * subjects the buyer picked, so editing the plan later never changes what
 * was already paid for.
 */
#[Fillable(['user_id', 'plan_id', 'conditions', 'selections', 'status', 'starts_at', 'ends_at', 'auto_renew'])]
class Subscription extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'conditions' => 'array',
            'selections' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * The people the buyer shares a group plan with.
     */
    public function members(): HasMany
    {
        return $this->hasMany(SubscriptionMember::class);
    }

    /**
     * How many people may use it, the buyer included: the plan's seats as
     * bought, or just the buyer.
     */
    public function seats(): int
    {
        return PlanConditions::fromArray($this->conditions)->seats ?? 1;
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The exams counted against it — by the buyer and by anyone they
     * share a group plan with.
     */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /**
     * The kinds of use counted: Teacher exams made, self-practice exams,
     * and past papers sat or set as an exam — as `withCount()` constraints
     * on an exams relation, so a whole list can be counted in its own query.
     *
     * @return array<string, Closure(Builder): Builder>
     */
    public static function usageCounts(string $examsRelation = 'exams'): array
    {
        return [
            "{$examsRelation} as teacher_exams_count" => fn (Builder $exams) => $exams->whereNull('board_question_paper_id')->where('exam_type', ExamType::TeacherExam),
            "{$examsRelation} as self_practice_count" => fn (Builder $exams) => $exams->whereNull('board_question_paper_id')->where('exam_type', ExamType::SelfPractice),
            "{$examsRelation} as past_papers_count" => fn (Builder $exams) => $exams->whereNotNull('board_question_paper_id'),
        ];
    }

    /**
     * The counts `usageCounts()` put on a model.
     *
     * @return array{teacher_exams: int, self_practice: int, past_papers: int}
     */
    public static function usageFrom(Model $counted): array
    {
        return [
            'teacher_exams' => (int) $counted->getAttribute('teacher_exams_count'),
            'self_practice' => (int) $counted->getAttribute('self_practice_count'),
            'past_papers' => (int) $counted->getAttribute('past_papers_count'),
        ];
    }

    /**
     * What has been done with it so far, in one query.
     *
     * @return array{teacher_exams: int, self_practice: int, past_papers: int}
     */
    public function usage(): array
    {
        return self::usageFrom($this->loadCount(self::usageCounts()));
    }

    /**
     * Anything at all done with it — once it has been used, its money
     * can't be refunded.
     */
    public function hasBeenUsed(): bool
    {
        return $this->exams()->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Active)
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
