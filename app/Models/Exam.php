<?php

namespace App\Models;

use App\Enums\ExamDeliveryMode;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\GenerationMode;
use App\Enums\QuestionType;
use App\Models\Concerns\FiltersByMonth;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

#[Fillable([
    'title', 'created_by', 'exam_type', 'generation_mode', 'delivery_mode', 'subject_id', 'class_subject_id',
    'duration_minutes', 'start_time', 'end_time', 'total_marks', 'status',
    'share_token', 'link_expires_at', 'is_link_active', 'answers_released_at', 'answers_release_at',
])]
class Exam extends Model
{
    use FiltersByMonth;
    use HasFactory;

    /**
     * Mirrors the column defaults, so a freshly created (not yet reloaded)
     * exam already knows its delivery mode and that its link is on.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'delivery_mode' => ExamDeliveryMode::Online->value,
        'is_link_active' => true,
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
            'answers_release_at' => 'datetime',
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
        return $query->createdInMonthBy($user, $examType, now());
    }

    /**
     * One creator's exams of a type made in the calendar month `$month`
     * falls in — what the monthly limit counts.
     */
    public function scopeCreatedInMonthBy(Builder $query, User $user, ExamType $examType, CarbonInterface $month): Builder
    {
        return $query->where('created_by', $user->id)
            ->where('exam_type', $examType)
            ->inMonth($month);
    }

    /**
     * The published exam behind a share link, whether or not the link still
     * takes new attempts — a closed link keeps showing results.
     */
    public static function findPublishedByShareToken(string $shareToken): ?self
    {
        return self::query()
            ->where('share_token', $shareToken)
            ->where('status', ExamStatus::Published)
            ->first();
    }

    /**
     * The link students open to sit this exam — null until it is published,
     * since only then does it get a share token.
     */
    public function shareUrl(): ?string
    {
        return $this->share_token ? route('guest-exam.show', $this->share_token) : null;
    }

    /**
     * Set (or, with null, remove) the exam's end time — the moment after
     * which the share link stops taking new attempts by itself.
     */
    public function closeLinkAt(?CarbonInterface $endsAt): void
    {
        $this->forceFill(['link_expires_at' => $endsAt])->save();
    }

    /**
     * Whether the share link can still be used to start an attempt: not
     * switched off by the teacher and not past its expiry.
     */
    public function isAcceptingAttempts(): bool
    {
        return $this->status === ExamStatus::Published
            && $this->is_link_active
            && ! ($this->link_expires_at && $this->link_expires_at->isPast());
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
        return $this->exam_type === ExamType::SelfPractice
            || $this->answers_released_at !== null
            || $this->isAnswerReleaseDue();
    }

    /**
     * Whether the time the teacher scheduled for the answers has arrived.
     *
     * Checked whenever a result is shown rather than flipped by a scheduled
     * job — so the answers unlock at exactly that moment with nothing to
     * run in the background (and nothing that can fail to run).
     */
    public function isAnswerReleaseDue(): bool
    {
        return $this->answers_release_at !== null && $this->answers_release_at->isPast();
    }

    /**
     * A release time still ahead — the answers are locked but will unlock
     * on their own.
     */
    public function hasPendingAnswerRelease(): bool
    {
        return $this->answers_released_at === null
            && $this->answers_release_at !== null
            && $this->answers_release_at->isFuture();
    }

    /**
     * Unlock the answers right now, whatever was scheduled.
     */
    public function releaseAnswers(): void
    {
        $this->forceFill(['answers_released_at' => now(), 'answers_release_at' => null])->save();
    }

    /**
     * Have the answers unlock by themselves at `$releaseAt`; null cancels a
     * schedule. Leaves a manual release untouched.
     */
    public function scheduleAnswerRelease(?CarbonInterface $releaseAt): void
    {
        $this->forceFill(['answers_release_at' => $releaseAt])->save();
    }

    /**
     * Lock the answers again — including dropping the schedule, or a time
     * that has already passed would simply unlock them straight away.
     */
    public function hideAnswers(): void
    {
        $this->forceFill(['answers_released_at' => null, 'answers_release_at' => null])->save();
    }

    /**
     * Calls the exam off so it can be corrected and held again: every
     * attempt on it — with its answers, marks and so its results, positions
     * and question analysis — is deleted for good, and the exam goes back
     * to a draft with its answers locked.
     *
     * Going back to draft is what makes the correction possible: the share
     * link stops taking attempts, so no student can start (and freeze the
     * paper again) while the teacher is editing. Publishing afterwards
     * reuses the same link.
     *
     * @return int How many attempts were deleted.
     */
    public function cancel(): int
    {
        return DB::transaction(function (): int {
            $attemptIds = $this->attempts()->pluck('id');

            AttemptAnswer::whereIn('attempt_id', $attemptIds)->delete();
            $this->attempts()->delete();

            $this->forceFill([
                'status' => ExamStatus::Draft,
                'answers_released_at' => null,
                'answers_release_at' => null,
            ])->save();

            // There is no getting this back, so leave a trace of who did it.
            Log::info('Exam cancelled: all attempts deleted.', [
                'exam_id' => $this->id,
                'cancelled_by' => auth()->id(),
                'attempts_deleted' => $attemptIds->count(),
            ]);

            return $attemptIds->count();
        });
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
