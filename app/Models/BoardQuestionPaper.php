<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\Concerns\HasApprovalStatus;
use App\Observers\BoardQuestionPaperObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * One past paper: a source (Board) + year (+ an optional unit), and — for a
 * board's or a school's paper — the class + subject it was for. Its MCQ and
 * CQ questions are its own (CLAUDE.md rule 5: never in the question pool);
 * the whole paper is approved or rejected at once.
 *
 * Teachers and students use an approved paper whole, as an exam
 * (App\Services\PastPaperExamService).
 */
#[Fillable([
    'board_id', 'class_subject_id', 'year', 'unit', 'duration_minutes', 'negative_marks', 'status',
    'created_by', 'approved_by', 'rejection_reason',
])]
#[ObservedBy(BoardQuestionPaperObserver::class)]
class BoardQuestionPaper extends Model
{
    use HasApprovalStatus, HasFactory;

    /**
     * Mirrors the column default ("no unit").
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unit' => '',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuestionStatus::class,
            'year' => 'integer',
            'duration_minutes' => 'integer',
            'negative_marks' => 'float',
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
        return $this->hasMany(BoardMcqQuestion::class)->with('stimulus')->orderBy('order_index')->orderBy('id');
    }

    /**
     * How many of this paper's MCQs it says have no correct answer.
     */
    public function mcqsWithoutCorrectAnswerCount(): int
    {
        return $this->mcqQuestions()->reorder()->where('has_correct_answer', false)->count();
    }

    public function mcqStimuli(): HasMany
    {
        return $this->hasMany(BoardMcqStimulus::class);
    }

    public function cqQuestions(): HasMany
    {
        return $this->hasMany(BoardCqQuestion::class)->orderBy('order_index')->orderBy('id');
    }

    /**
     * What a question of one of this paper's lists is checked against for
     * duplicates (DuplicateQuestionFinder): that list, with what the check
     * reads.
     *
     * @return Collection<int, BoardMcqQuestion|BoardCqQuestion>
     */
    public function duplicateCandidates(QuestionType $type): Collection
    {
        return $type === QuestionType::Mcq
            ? $this->mcqQuestions()->get()
            : $this->cqQuestions()->with('cqParts')->get();
    }

    /**
     * The exams teachers and students have made of this paper.
     */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function staffEarning(): HasOne
    {
        return $this->hasOne(StaffEarning::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', QuestionStatus::Approved);
    }

    /**
     * A user's own papers — what a Staff member's panel lists.
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('created_by', $user->id);
    }

    /**
     * Whether another paper already has this source, year, unit and class
     * + subject. The database's unique key can't tell for an admission
     * paper (its class + subject is null, and nulls never clash), so this
     * is what every form asks.
     */
    public static function isTaken(int $boardId, ?int $classSubjectId, int $year, ?string $unit, ?int $exceptId = null): bool
    {
        return self::takenSources([$boardId], $classSubjectId, $year, $unit, $exceptId) !== [];
    }

    /**
     * Which of these sources already have that paper (see isTaken()).
     *
     * @param  array<int, int>  $boardIds
     * @return array<int, int>
     */
    public static function takenSources(array $boardIds, ?int $classSubjectId, int $year, ?string $unit, ?int $exceptId = null): array
    {
        return self::query()
            ->whereIn('board_id', $boardIds)
            ->where('year', $year)
            ->where('unit', trim((string) $unit))
            ->when($classSubjectId, fn (Builder $query) => $query->where('class_subject_id', $classSubjectId), fn (Builder $query) => $query->whereNull('class_subject_id'))
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->pluck('board_id')
            ->all();
    }

    /**
     * One paper for each source, with the same details — each its own paper
     * from then on, with its own questions and its own approval. All of them
     * or none.
     *
     * @param  array<int, int|string>  $boardIds
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, self>
     */
    public static function createForSources(array $boardIds, array $attributes): Collection
    {
        return DB::transaction(fn (): Collection => new Collection(array_map(
            fn (int|string $boardId): self => self::create([...$attributes, 'board_id' => $boardId]),
            array_values($boardIds),
        )));
    }

    /**
     * Where a question came from, short: "ঢাবি 2023 · ক ইউনিট" / "DU 2023 · Ka unit".
     */
    public function label(): string
    {
        return collect([
            "{$this->board->display_short_name} {$this->year}",
            filled($this->unit) ? __(':unit unit', ['unit' => $this->unit]) : null,
        ])->filter()->join(' · ');
    }

    /**
     * The paper in full, for an exam's title or a list: the source's full
     * name, the year, the unit and — on a board's paper — the subject.
     */
    public function title(): string
    {
        return collect([
            "{$this->board->display_name} {$this->year}",
            filled($this->unit) ? __(':unit unit', ['unit' => $this->unit]) : null,
            $this->classSubject?->subject?->display_name,
        ])->filter()->join(' · ');
    }

    /**
     * How long an exam of this paper lasts: the paper's own time, or a
     * minute per MCQ when none was entered.
     */
    public function examDurationMinutes(): int
    {
        return $this->duration_minutes ?: max(1, $this->mcqQuestions()->count());
    }

    /**
     * Whether anyone has sat an exam made of this paper. From then on its
     * questions are fixed: changing them would make those results mean
     * something other than what was sat.
     */
    public function hasBeenSat(): bool
    {
        return (bool) ($this->getAttribute('has_been_sat') ?? $this->exams()->whereHas('attempts')->exists());
    }

    /**
     * Whether an exam has been made of this paper.
     */
    public function isOnAnExam(): bool
    {
        return (bool) ($this->getAttribute('is_on_an_exam') ?? $this->exams()->exists());
    }

    /**
     * Adds, in the same query, whether an exam has been made of each paper
     * and whether anyone has sat one — what isOnAnExam() and hasBeenSat()
     * then answer from, instead of queries per paper.
     */
    public function scopeWithExamUsage(Builder $query): Builder
    {
        return $query->withExists([
            'exams as is_on_an_exam',
            'exams as has_been_sat' => fn (Builder $exams) => $exams->whereHas('attempts'),
        ]);
    }

    /**
     * Whether the user may change this paper or its questions: an admin
     * until it has been sat, its Staff author while it isn't approved.
     */
    public function isEditableBy(User $user): bool
    {
        if ($this->hasBeenSat()) {
            return false;
        }

        return $user->role->isAdmin()
            || ($user->role === UserRole::Staff && $this->created_by === $user->id && $this->status !== QuestionStatus::Approved);
    }

    /**
     * A Staff member changing their rejected paper sends it back for review.
     */
    public function markEditedBy(User $user): void
    {
        if (! $user->role->isAdmin() && $this->status === QuestionStatus::Rejected) {
            $this->forceFill(['status' => QuestionStatus::Pending, 'rejection_reason' => null])->save();
        }
    }

    /**
     * Only a paper with questions in it can be approved — a Staff member
     * starts one empty and fills it in. Answers from the list's counts when
     * they have been read.
     */
    public function canBeApproved(): bool
    {
        return $this->status !== QuestionStatus::Approved
            && (($this->mcq_questions_count ?? $this->mcqQuestions()->count()) + ($this->cq_questions_count ?? $this->cqQuestions()->count())) > 0;
    }

    /**
     * Approves each of these papers that can be, each as approve() does
     * (a Staff member paid once per paper). One already approved is left as
     * it is; one with no questions is counted, so the Admin can be told.
     *
     * @param  Collection<int, self>  $papers
     * @return array{approved: int, without_questions: int}
     */
    public static function approveMany(Collection $papers, User $approver): array
    {
        $papers->loadCount(['mcqQuestions', 'cqQuestions'])->load(['board']);

        $approvable = $papers->filter(fn (self $paper): bool => $paper->canBeApproved());
        $approvable->each(fn (self $paper) => $paper->approve($approver));

        return [
            'approved' => $approvable->count(),
            'without_questions' => $papers->filter(fn (self $paper): bool => $paper->mcq_questions_count + $paper->cq_questions_count === 0)->count(),
        ];
    }

    /**
     * Approving a Staff member's paper earns them the Admin's rate for its
     * kind, as it is right now (CLAUDE.md rule 6) — once per paper, however
     * often it is approved again.
     */
    public function approve(User $approver): void
    {
        DB::transaction(function () use ($approver): void {
            $this->markApproved($approver);

            // Read here, not from a loaded relation: a list loads only the
            // creator's name, and without the role no one would be paid.
            $creator = $this->creator()->first();

            if ($creator?->role !== UserRole::Staff || $this->staffEarning()->exists()) {
                return;
            }

            $amount = BillingSetting::current()->paperRateFor($this->board->type);

            $this->staffEarning()->create(['staff_id' => $this->created_by, 'amount' => $amount]);

            StaffProfile::firstOrCreateFor($creator)->incrementEarnings($amount, questions: 0);
        });
    }

    public function reject(User $rejecter, string $reason): void
    {
        $this->markRejected($reason);
    }
}
