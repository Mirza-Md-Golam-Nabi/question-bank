<?php

namespace App\Models;

use App\Enums\ExamAttemptStatus;
use App\Enums\QuestionType;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[Fillable([
    'exam_id', 'student_id', 'is_guest', 'guest_name', 'guest_contact',
    'started_at', 'submitted_at', 'status', 'total_score',
])]
class ExamAttempt extends Model
{
    use HasFactory;

    /**
     * How long after the deadline answers are still accepted.
     */
    public const ANSWER_GRACE_SECONDS = 30;

    /**
     * Mirrors the column default, so a freshly created (not yet reloaded)
     * attempt already knows it is in progress.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ExamAttemptStatus::InProgress->value,
    ];

    protected function casts(): array
    {
        return [
            'is_guest' => 'boolean',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'status' => ExamAttemptStatus::class,
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * A guest's phone/email in one canonical spelling, so "017 1234-5678"
     * typed at the start and "01712345678" typed when looking the result
     * up later are the same contact. Stored already normalized.
     */
    public static function normalizeGuestContact(string $contact): string
    {
        return mb_strtolower(preg_replace('/[\s\-()]+/u', '', $contact));
    }

    /**
     * A guest's name for comparison: case and extra spaces don't matter.
     */
    public static function normalizeGuestName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
    }

    /**
     * The submitted attempt a guest gets back by giving the same name and
     * phone/email they sat the exam with — a guest has no account, so that
     * pair is the only thing identifying them. When they sat it more than
     * once it is their first attempt that counts.
     */
    public static function findGuestResult(Exam $exam, string $name, string $contact): ?self
    {
        return self::ofGuest($exam, $name, $contact)->first(fn (self $attempt): bool => ! $attempt->isInProgress());
    }

    /**
     * Every attempt one guest has on an exam, oldest first — "one guest"
     * being the same name and phone/email, on whatever device. The contact
     * is matched in the query (it is stored normalized and indexed); the
     * name, which is stored as typed, on the handful of rows that leaves.
     *
     * @return Collection<int, self>
     */
    private static function ofGuest(Exam $exam, string $name, string $contact): Collection
    {
        return self::query()
            ->where('exam_id', $exam->id)
            ->where('is_guest', true)
            ->where('guest_contact', self::normalizeGuestContact($contact))
            ->orderBy('id')
            ->get()
            ->filter(fn (self $attempt): bool => self::normalizeGuestName((string) $attempt->guest_name) === self::normalizeGuestName($name))
            ->values();
    }

    /**
     * Handed in, whichever way — by the participant or by the clock.
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->whereIn('status', [ExamAttemptStatus::Submitted, ExamAttemptStatus::AutoSubmitted]);
    }

    public function isInProgress(): bool
    {
        return $this->status === ExamAttemptStatus::InProgress;
    }

    /**
     * Puts a logged-in student into an exam, by the one rule enter()
     * applies to everyone.
     */
    public static function startFor(Exam $exam, User $student): self
    {
        return self::enter(
            $exam,
            fn (): Collection => self::query()
                ->where('exam_id', $exam->id)
                ->where('student_id', $student->id)
                ->orderBy('id')
                ->get(),
            ['student_id' => $student->id, 'is_guest' => false],
        );
    }

    /**
     * Puts a guest into an exam, by the same rule. A guest has no account,
     * so they are recognised by their name and phone/email — which is what
     * lets them carry on from another device, and what stops a second
     * device from getting a fresh exam with a fresh clock.
     */
    public static function startForGuest(Exam $exam, string $name, string $contact): self
    {
        return self::enter(
            $exam,
            fn (): Collection => self::ofGuest($exam, $name, $contact),
            [
                'student_id' => null,
                'is_guest' => true,
                'guest_name' => trim($name),
                'guest_contact' => self::normalizeGuestContact($contact),
            ],
        );
    }

    /**
     * The one rule for getting into an exam, which a participant may sit
     * only once and against a single clock:
     *
     *  - already handed in → that attempt, to be shown its result;
     *  - one under way     → that same attempt, clock and saved answers and
     *                        all — unless its time has run out, in which
     *                        case it is handed in now with whatever was
     *                        saved, and is their result;
     *  - otherwise         → a new attempt starting now.
     *
     * So the caller only has to look at isInProgress(): into the exam, or
     * to the result. A teacher cancelling the exam deletes its attempts,
     * which is what lets everyone sit it again afterwards.
     *
     * @param  Closure(): Collection<int, self>  $ownAttempts  The participant's attempts on this exam, oldest first.
     * @param  array<string, mixed>  $identity  What marks a new attempt as theirs.
     */
    private static function enter(Exam $exam, Closure $ownAttempts, array $identity): self
    {
        // Locking the exam row makes two "Start" presses arriving together
        // (two devices, a double click) take turns, so the second one finds
        // the attempt the first one created instead of creating another.
        return DB::transaction(function () use ($exam, $ownAttempts, $identity): self {
            Exam::whereKey($exam->id)->lockForUpdate()->value('id');

            $attempts = $ownAttempts()->each->setRelation('exam', $exam);

            $attempt = $attempts->first(fn (self $attempt): bool => ! $attempt->isInProgress())
                ?? $attempts->first();

            if (! $attempt) {
                return self::create(['exam_id' => $exam->id, 'started_at' => now(), ...$identity])
                    ->setRelation('exam', $exam);
            }

            if ($attempt->isInProgress() && $attempt->hasRunOutOfTime()) {
                $attempt->submitAndAutoGrade();
            }

            return $attempt;
        });
    }

    /**
     * The clock has run out and the short grace period for late answers
     * with it: nothing more can be added to this attempt.
     */
    public function hasRunOutOfTime(): bool
    {
        return ! $this->isAcceptingAnswers();
    }

    /**
     * Whose attempt this is, by name: what a guest typed when starting, or
     * the logged-in student's account name.
     */
    public function participantName(): string
    {
        return (string) ($this->is_guest ? $this->guest_name : $this->student?->name);
    }

    /**
     * How to reach them: a guest's phone/email, or the student's email.
     */
    public function participantContact(): ?string
    {
        return $this->is_guest ? $this->guest_contact : $this->student?->email;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'attempt_id');
    }

    /**
     * The exam's online questions in this attempt's own order. Every
     * attempt gets a different order, so students sitting side by side
     * can't trade answers by question number — but the order is derived
     * from the attempt id, so the same attempt always sees the same one
     * (on a page reload, a Livewire re-render, and again on the result).
     * Options are deliberately left in their written order.
     *
     * @return Collection<int, Question>
     */
    public function shuffledQuestions(): Collection
    {
        return new Collection($this->shuffleWithSeed($this->exam->onlineQuestions->all(), $this->id));
    }

    /**
     * @template TValue
     *
     * @param  array<int, TValue>  $items
     * @return array<int, TValue>
     */
    private function shuffleWithSeed(array $items, int $seed): array
    {
        return (new Randomizer(new Mt19937($seed)))->shuffleArray(array_values($items));
    }

    /**
     * When the exam's time runs out for this attempt.
     */
    public function deadline(): CarbonInterface
    {
        return $this->started_at->copy()->addMinutes($this->exam->duration_minutes);
    }

    /**
     * Whole seconds left on the clock — what the on-page countdown starts
     * from, so it follows the server's clock rather than the device's.
     */
    public function secondsRemaining(): int
    {
        return max(0, (int) ceil(now()->diffInSeconds($this->deadline(), absolute: false)));
    }

    /**
     * Whether answers arriving right now are still in time. The short
     * grace period only covers the network delay of a browser that sent
     * its answers exactly as the clock ran out.
     */
    public function isAcceptingAnswers(): bool
    {
        return now()->lessThanOrEqualTo($this->deadline()->addSeconds(self::ANSWER_GRACE_SECONDS));
    }

    /**
     * Stores the answers a browser sent, enforcing the time limit on the
     * server: the page locks itself when the clock runs out, but that lock
     * can be bypassed, so anything arriving late is ignored in favour of
     * what was saved in time.
     *
     * The one exception is an attempt with nothing saved at all — the
     * browser's end-of-time save never arrived (e.g. the connection
     * dropped), and discarding the whole exam would punish that far more
     * than it prevents cheating.
     *
     * Only questions that are actually part of this exam are stored.
     *
     * @param  array<int|string, mixed>  $answers  question id => chosen answer
     */
    public function recordAnswers(array $answers): void
    {
        if (! $this->isAcceptingAnswers() && $this->answers()->exists()) {
            return;
        }

        $examQuestionIds = $this->exam->onlineQuestions->pluck('id')->flip();

        $rows = collect($answers)
            ->filter(fn (mixed $studentAnswer, int|string $questionId): bool => $examQuestionIds->has($questionId)
                && is_string($studentAnswer)
                && $studentAnswer !== '')
            ->map(fn (string $studentAnswer, int|string $questionId): array => [
                'attempt_id' => $this->id,
                'question_id' => (int) $questionId,
                'student_answer' => $studentAnswer,
            ])
            ->values();

        // One statement for the whole paper rather than two per question —
        // a whole class's pages send this at the same instant when the
        // clock runs out.
        if ($rows->isNotEmpty()) {
            AttemptAnswer::upsert($rows->all(), ['attempt_id', 'question_id'], ['student_answer']);
        }

        $this->unsetRelation('answers');
    }

    /**
     * Auto-grades every MCQ answer against whichever of the question's
     * `options` carries `is_correct` and sums the result into total_score.
     * CQ answers are left for a teacher to grade manually (obtained_marks
     * stays whatever it already was — 0 by default) — only the MCQ portion
     * of total_score is ever computed here.
     */
    public function submitAndAutoGrade(): void
    {
        // The exam's questions are already in memory, so no answer has to
        // load its own question.
        $examQuestionsById = $this->exam->questions->keyBy('id');

        /** @var array<string, array{is_correct: bool, obtained_marks: float, ids: array<int, int>}> $outcomes */
        $outcomes = [];

        foreach ($this->answers()->get(['id', 'question_id', 'student_answer']) as $answer) {
            $question = $examQuestionsById->get($answer->question_id);

            if ($question?->question_type !== QuestionType::Mcq) {
                continue;
            }

            $correctOption = collect($question->options)
                ->first(fn (array $option) => (bool) ($option['is_correct'] ?? false));

            $isCorrect = $correctOption && $answer->student_answer === $correctOption['option'];
            $marks = $isCorrect ? (float) ($question->pivot?->marks_override ?? $question->marks) : 0.0;

            $outcomes["{$isCorrect}|{$marks}"] ??= ['is_correct' => (bool) $isCorrect, 'obtained_marks' => $marks, 'ids' => []];
            $outcomes["{$isCorrect}|{$marks}"]['ids'][] = $answer->id;
        }

        // Answers that came out the same are marked together: one update
        // per distinct outcome (usually two — right and wrong) instead of
        // one per answer.
        foreach ($outcomes as $outcome) {
            AttemptAnswer::whereKey($outcome['ids'])->update([
                'is_correct' => $outcome['is_correct'],
                'obtained_marks' => $outcome['obtained_marks'],
            ]);
        }

        $this->unsetRelation('answers');

        $this->forceFill([
            'status' => ExamAttemptStatus::Submitted,
            'submitted_at' => now(),
            'total_score' => $this->answers()->sum('obtained_marks'),
        ])->save();
    }
}
