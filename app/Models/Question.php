<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\EditorMode;
use App\Enums\QuestionApprovalAction;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\Concerns\HasApprovalStatus;
use App\Models\Concerns\HasMcqOptions;
use App\Models\Concerns\SharesStimulus;
use App\Observers\QuestionObserver;
use App\Services\QuestionDifficultyTracker;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable([
    'chapter_id', 'topic_id', 'stimulus_id', 'stimulus_order', 'question_type', 'question_text', 'editor_mode', 'question_image', 'options',
    'explanation', 'marks', 'difficulty', 'status', 'created_by', 'approved_by',
    'rejection_reason', 'parent_id', 'version', 'is_latest', 'is_private',
])]
#[ObservedBy(QuestionObserver::class)]
class Question extends Model
{
    use HasApprovalStatus, HasFactory, HasMcqOptions, SharesStimulus, SoftDeletes;

    /**
     * How many matches a question select shows for one search.
     */
    public const SELECT_OPTIONS_LIMIT = 50;

    /**
     * How few and how many options an MCQ may have, and the marks it
     * carries unless stated — the same for a question typed into the form
     * and one arriving through a JSON import.
     */
    public const MIN_MCQ_OPTIONS = 2;

    public const MAX_MCQ_OPTIONS = 6;

    public const DEFAULT_MCQ_MARKS = 1;

    protected function casts(): array
    {
        return [
            'question_type' => QuestionType::class,
            'editor_mode' => EditorMode::class,
            'difficulty' => Difficulty::class,
            'status' => QuestionStatus::class,
            'options' => 'array',
            'is_latest' => 'boolean',
            'is_private' => 'boolean',
            'answer_count' => 'integer',
            'correct_answer_count' => 'integer',
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

    /**
     * The stimulus this MCQ shares with the others under it, if any.
     */
    public function stimulus(): BelongsTo
    {
        return $this->belongsTo(QuestionStimulus::class, 'stimulus_id');
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

    public function exams(): BelongsToMany
    {
        return $this->belongsToMany(Exam::class, 'exam_questions');
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
     * The questions a user wrote themselves, whatever their status — what
     * the Teacher's and the Staff's own question lists show.
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('created_by', $user->id);
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
     * What a question of this chapter is checked against for duplicates
     * (DuplicateQuestionFinder): the current version of every question the
     * user can see — all of them for an Admin — with what the check reads.
     */
    public function scopeDuplicateCandidatesIn(Builder $query, int $chapterId, User $viewer): Builder
    {
        return $query->where('chapter_id', $chapterId)
            ->where('is_latest', true)
            ->when(
                $viewer->role->isAdmin(),
                // An Admin sees every question but a School's own.
                fn (Builder $query) => $query->where('is_private', false),
                fn (Builder $query) => $query->where(fn (Builder $query) => $query->visibleTo($viewer)),
            )
            ->with(['cqParts', 'stimulus']);
    }

    /**
     * Whether two rows are versions of one question.
     */
    public function isVersionOf(self $other): bool
    {
        return ($this->parent_id ?? $this->id) === ($other->parent_id ?? $other->id);
    }

    /**
     * The pool of questions eligible for exam-building / self-practice —
     * approved and the current version. Reused by exam pickers, self-practice
     * auto/manual selection, and exam-attach validation. A School's own
     * question is never approved; it is left out here as well, so it can't
     * reach the pool even by mistake.
     */
    public function scopeApprovedPool(Builder $query): Builder
    {
        return $query->where('status', QuestionStatus::Approved)->where('is_latest', true)->where('is_private', false);
    }

    /**
     * A School's own questions (CLAUDE.md rule 12) that its students may
     * now read in the school management software — the current version of
     * each, once every exam any version of it is on has ended, from the day
     * after; when one of those exams has no end time, or it is on none, the
     * Admin's number of days after it was first written. No job or stored
     * date: worked out when asked.
     */
    public function scopeReleasedToSchoolStudents(Builder $query, User $school): Builder
    {
        $writtenBefore = now()->subDays(BillingSetting::current()->school_question_release_days);
        $examsOfAnyVersion = fn () => DB::table('exam_questions')
            ->join('exams', 'exams.id', '=', 'exam_questions.exam_id')
            ->join('questions as versions', 'versions.id', '=', 'exam_questions.question_id')
            ->whereRaw('coalesce(versions.parent_id, versions.id) = coalesce(questions.parent_id, questions.id)');

        return $query
            ->where('questions.created_by', $school->id)
            ->where('questions.is_private', true)
            ->where('questions.is_latest', true)
            ->whereNotExists($examsOfAnyVersion()->where('exams.link_expires_at', '>=', today()))
            ->where(fn (Builder $query) => $query
                ->whereExists(DB::table('questions as first_versions')
                    ->whereRaw('first_versions.id = coalesce(questions.parent_id, questions.id)')
                    ->where('first_versions.created_at', '<=', $writtenBefore))
                ->orWhere(fn (Builder $query) => $query
                    ->whereExists($examsOfAnyVersion())
                    ->whereNotExists($examsOfAnyVersion()->whereNull('exams.link_expires_at'))));
    }

    /**
     * What a teacher may put on an exam of their own: the approved pool,
     * plus their own questions still waiting for review — so a question
     * written today can be on today's exam. Nobody else sees a pending
     * question, and a rejected one can't go on a new exam at all. The only
     * place this exception to the approved-only rule is written; students'
     * self-practice stays on approvedPool(). A School's own questions are
     * pending for good, so this is also what puts them on its exams.
     */
    public function scopeUsableInExamBy(Builder $query, User $teacher): Builder
    {
        return $query->where('is_latest', true)->where(fn (Builder $query) => $query
            ->where('status', QuestionStatus::Approved)
            ->orWhere(fn (Builder $query) => $query
                ->where('status', QuestionStatus::Pending)
                ->where('created_by', $teacher->id)));
    }

    /**
     * Adds, in the same query, whether each question is on an exam and
     * whether any student has started such an exam — what isOnAnExam() and
     * hasBeenSat() then answer from, instead of a query per question.
     */
    public function scopeWithExamUsage(Builder $query): Builder
    {
        return $query->withExists([
            'exams as is_on_an_exam',
            'exams as has_been_sat' => fn (Builder $query) => $query->whereHas('attempts'),
        ]);
    }

    public function isOnAnExam(): bool
    {
        return (bool) ($this->getAttribute('is_on_an_exam') ?? $this->exams()->exists());
    }

    /**
     * Whether a student has started an exam this question is on.
     */
    public function hasBeenSat(): bool
    {
        return (bool) ($this->getAttribute('has_been_sat') ?? $this->exams()->whereHas('attempts')->exists());
    }

    /**
     * An approved question is never changed in place — editing it makes a
     * new version and leaves this one as it is on every exam. Any other
     * question is edited in place, so once a student has sat it, it must
     * stay as they saw it: their marks were given against this wording and
     * this answer.
     */
    public function isFrozenByAttempts(): bool
    {
        return ! $this->editsMakeNewVersion() && $this->hasBeenSat();
    }

    /**
     * Whether saving an edit makes a new version, leaving this one as it is
     * on every exam: an approved question always; a School's own question
     * (never approved) once a student has sat it — so the School can still
     * correct it.
     */
    public function editsMakeNewVersion(): bool
    {
        return $this->status === QuestionStatus::Approved || ($this->is_private && $this->hasBeenSat());
    }

    /**
     * Questions whose text, shared stimulus or topic contains the search —
     * kept in one group, so it only ever narrows the query it is added to.
     */
    public function scopeMatching(Builder $query, string $search): Builder
    {
        $like = '%'.trim($search).'%';

        return $query->where(fn (Builder $matches) => $matches
            ->where('question_text', 'like', $like)
            ->orWhereHas('stimulus', fn (Builder $stimulus) => $stimulus->where('text', 'like', $like))
            ->orWhereHas('topic', fn (Builder $topic) => $topic->where('name', 'like', $like)));
    }

    /**
     * Questions of one class's subject.
     */
    public function scopeOfClassSubject(Builder $query, int|string $classSubjectId): Builder
    {
        return $query->whereHas('chapter', fn (Builder $query) => $query->where('class_subject_id', $classSubjectId));
    }

    /**
     * Questions from the approved pool of one class's subject as select
     * options — the plain text of each, keyed by id — matching what was
     * typed into the select's search box, and only of the given
     * difficulties when the user's plan limits them.
     *
     * Never the whole pool: a subject has thousands of questions, and
     * sending them all to the browser as options is exactly what a picker
     * must not do. A select built on this searches on the server and gets
     * a short list back.
     *
     * @param  array<int, Difficulty>|null  $difficulties
     * @return array<int, string>
     */
    public static function approvedOptionsForClassSubject(int|string|null $classSubjectId, ?string $search = null, ?array $difficulties = null): array
    {
        if (blank($classSubjectId)) {
            return [];
        }

        return self::asSelectOptions(self::query()
            ->approvedPool()
            ->ofClassSubject($classSubjectId)
            ->when($difficulties !== null, fn (Builder $query) => $query->whereIn('difficulty', $difficulties))
            // What was typed is matched as plain text: its own % and _ are
            // escaped (with "!", which every database accepts as the
            // escape character) rather than acting as wildcards.
            ->when(filled($search), fn (Builder $query) => $query->whereRaw(
                "question_text like ? escape '!'",
                ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%'],
            ))
            ->orderBy('id')
            ->limit(self::SELECT_OPTIONS_LIMIT));
    }

    /**
     * The select labels of questions that are already chosen — still only
     * from the approved pool, so an id typed into the request by hand
     * can't smuggle in a pending or rejected question.
     *
     * @param  array<int, int|string>  $questionIds
     * @return array<int, string>
     */
    public static function approvedOptionLabels(array $questionIds): array
    {
        return self::asSelectOptions(self::query()->approvedPool()->whereKey($questionIds));
    }

    /**
     * @param  Builder<self>  $questions
     * @return array<int, string>
     */
    private static function asSelectOptions(Builder $questions): array
    {
        return $questions
            ->get(['id', 'question_text'])
            ->mapWithKeys(fn (self $question) => [$question->id => strip_tags($question->question_text)])
            ->all();
    }

    /**
     * Approves this question — and, under a shared stimulus, every current
     * question of its group that isn't approved yet: a group is reviewed as
     * one, since its questions mean nothing without each other. Each is
     * approved on its own record (its log, a Staff member's pay per
     * question), never twice.
     */
    public function approve(User $approver): void
    {
        $this->ensureNotPrivate();

        DB::transaction(function () use ($approver) {
            foreach ($this->groupToReview(QuestionStatus::Approved) as $question) {
                $question->approveAlone($approver);
            }
        });
    }

    /**
     * A School's own question is never reviewed: approving it would put it
     * in everyone's pool.
     */
    private function ensureNotPrivate(): void
    {
        if ($this->is_private) {
            throw new LogicException("A School's own question is never approved or rejected.");
        }
    }

    private function approveAlone(User $approver): void
    {
        $this->markApproved($approver);

        $this->approvalLogs()->create([
            'action' => QuestionApprovalAction::Approved,
            'performed_by' => $approver->id,
        ]);

        $this->recordStaffEarningIfOwnerIsStaff();
    }

    /**
     * This question, with — under a shared stimulus — the other current
     * questions of its group not already in `$status`.
     *
     * @return SupportCollection<int, self>
     */
    private function groupToReview(QuestionStatus $status): SupportCollection
    {
        if (! $this->isInStimulusGroup()) {
            return collect([$this]);
        }

        return self::query()
            ->where('stimulus_id', $this->stimulus_id)
            ->where('is_latest', true)
            ->whereKeyNot($this->id)
            ->where('status', '!=', $status)
            ->get()
            ->prepend($this);
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

    /**
     * Rejects this question — with the rest of its stimulus group, as
     * approve() does.
     */
    public function reject(User $rejecter, string $reason): void
    {
        $this->ensureNotPrivate();

        DB::transaction(function () use ($rejecter, $reason) {
            foreach ($this->groupToReview(QuestionStatus::Rejected) as $question) {
                $question->markRejected($reason);

                $question->approvalLogs()->create([
                    'action' => QuestionApprovalAction::Rejected,
                    'performed_by' => $rejecter->id,
                    'reason' => $reason,
                ]);
            }
        });
    }

    /**
     * Whether the difficulty is the one worked out from students' answers
     * (QuestionDifficultyTracker) rather than the one its author picked —
     * from then on it is no longer the author's to set.
     */
    public function hasCalculatedDifficulty(): bool
    {
        return $this->question_type === QuestionType::Mcq
            && QuestionDifficultyTracker::hasEnoughAnswers((int) $this->answer_count);
    }

    /**
     * The share of students who answered correctly, as a whole percentage
     * — null while nobody has answered.
     */
    public function correctAnswerPercent(): ?int
    {
        return $this->answer_count > 0
            ? (int) round($this->correct_answer_count * 100 / $this->answer_count)
            : null;
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
            $revision = new self([
                'created_by' => $this->created_by,
                // The form leaves the difficulty out once it is the
                // calculated one, so the new version starts from this one's.
                'difficulty' => $this->difficulty,
                ...$attributes,
                'parent_id' => $this->parent_id ?? $this->id,
                'version' => ($this->version ?? 1) + 1,
                'is_private' => $this->is_private,
                'status' => QuestionStatus::Pending,
                'approved_by' => null,
                'rejection_reason' => null,
                'is_latest' => true,
            ]);

            // A correction doesn't wipe what students have shown about the
            // question: the new version carries on counting from here.
            $revision->forceFill([
                'answer_count' => (int) $this->answer_count,
                'correct_answer_count' => (int) $this->correct_answer_count,
                ...($this->hasCalculatedDifficulty() ? ['difficulty' => $this->difficulty] : []),
            ])->save();

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
