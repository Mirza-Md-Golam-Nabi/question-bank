<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\EditorMode;
use App\Enums\QuestionApprovalAction;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\Concerns\HasApprovalStatus;
use App\Observers\QuestionObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'chapter_id', 'topic_id', 'question_type', 'question_text', 'editor_mode', 'question_image', 'options',
    'marks', 'difficulty', 'status', 'created_by', 'approved_by',
    'rejection_reason', 'parent_id', 'version', 'is_latest',
])]
#[ObservedBy(QuestionObserver::class)]
class Question extends Model
{
    use HasApprovalStatus, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'question_type' => QuestionType::class,
            'editor_mode' => EditorMode::class,
            'difficulty' => Difficulty::class,
            'status' => QuestionStatus::class,
            'options' => 'array',
            'is_latest' => 'boolean',
        ];
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function cqParts(): HasMany
    {
        return $this->hasMany(QuestionCqPart::class)->orderBy('part_order');
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(QuestionApprovalLog::class)->latest('created_at');
    }

    public function staffEarnings(): HasMany
    {
        return $this->hasMany(StaffEarning::class);
    }

    /**
     * Owner sees all of their own questions regardless of status; everyone
     * else only sees the latest approved version. Backs CLAUDE.md's
     * "Question Approval Visibility" rule — used identically by the
     * Admin/Teacher/Staff QuestionResource::getEloquentQuery().
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('created_by', $user->id)
            ->orWhere(fn (Builder $q) => $q->approvedPool());
    }

    /**
     * The pool of questions eligible for exam-building / self-practice —
     * approved and the current version. Reused by exam pickers, self-practice
     * auto/manual selection, and exam-attach validation.
     */
    public function scopeApprovedPool(Builder $query): Builder
    {
        return $query->where('status', QuestionStatus::Approved)->where('is_latest', true);
    }

    /**
     * Questions of one subject, in whichever class it is taught.
     */
    public function scopeOfSubject(Builder $query, int|string $subjectId): Builder
    {
        return $query->whereHas('chapter.classSubject', fn (Builder $query) => $query->where('subject_id', $subjectId));
    }

    /**
     * The approved pool of a subject as select options — the plain text of
     * each question, keyed by id.
     *
     * @return array<int, string>
     */
    public static function approvedOptionsForSubject(int|string|null $subjectId): array
    {
        if (blank($subjectId)) {
            return [];
        }

        return self::query()
            ->approvedPool()
            ->ofSubject($subjectId)
            ->get(['id', 'question_text'])
            ->mapWithKeys(fn (self $question) => [$question->id => strip_tags($question->question_text)])
            ->all();
    }

    public function approve(User $approver): void
    {
        DB::transaction(function () use ($approver) {
            $this->markApproved($approver);

            $this->approvalLogs()->create([
                'action' => QuestionApprovalAction::Approved,
                'performed_by' => $approver->id,
            ]);

            $this->recordStaffEarningIfOwnerIsStaff();
        });
    }

    /**
     * CLAUDE.md rule 6: the moment a Staff-owned question is approved, a
     * staff_earnings row is created with the rate snapshotted at approval
     * time (a later rate change must never alter this entry). Teacher-owned
     * questions never earn anything.
     */
    private function recordStaffEarningIfOwnerIsStaff(): void
    {
        if ($this->creator->role !== UserRole::Staff) {
            return;
        }

        $subjectId = $this->chapter->classSubject->subject_id;
        $amount = QuestionRate::rateFor($subjectId);

        $this->staffEarnings()->create([
            'staff_id' => $this->created_by,
            'amount' => $amount,
        ]);

        StaffProfile::firstOrCreateFor($this->creator)->incrementEarnings($amount);
    }

    public function reject(User $rejecter, string $reason): void
    {
        $this->markRejected($reason);

        $this->approvalLogs()->create([
            'action' => QuestionApprovalAction::Rejected,
            'performed_by' => $rejecter->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Editing an approved question never mutates it in place — a new pending
     * revision is created (pointing `parent_id` at the root version), the
     * current row is marked `is_latest = false`, and (for CQ) fresh
     * `question_cq_parts` rows are created for the new revision from
     * `$cqPartsData` — never copied byte-for-byte from the old version,
     * since the whole point is capturing the just-edited content.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>|null  $cqPartsData
     */
    public function createRevisionWith(array $attributes, ?array $cqPartsData = null): self
    {
        return DB::transaction(function () use ($attributes, $cqPartsData) {
            $revision = self::create([
                'created_by' => $this->created_by,
                ...$attributes,
                'parent_id' => $this->parent_id ?? $this->id,
                'version' => ($this->version ?? 1) + 1,
                'status' => QuestionStatus::Pending,
                'approved_by' => null,
                'rejection_reason' => null,
                'is_latest' => true,
            ]);

            $this->newQuery()->whereKey($this->id)->update(['is_latest' => false]);

            if ($revision->question_type === QuestionType::Cq && $cqPartsData) {
                foreach ($cqPartsData as $partData) {
                    $revision->cqParts()->create($partData);
                }
            }

            return $revision->fresh();
        });
    }
}
