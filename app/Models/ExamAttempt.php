<?php

namespace App\Models;

use App\Enums\ExamAttemptStatus;
use App\Enums\Locale;
use App\Enums\NoAnswerMarking;
use App\Enums\QuestionType;
use App\Filament\Support\QuestionBlocks;
use App\Services\QuestionDifficultyTracker;
use App\Services\ReferralService;
use App\Services\StudentMistakeTracker;
use App\Services\TeacherQuestionStats;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[Fillable([
    'exam_id', 'student_id', 'is_guest', 'guest_name', 'guest_contact',
    'school_class_id', 'school_class_name', 'school_section_id', 'school_section_name', 'school_roll',
    'started_at', 'submitted_at', 'status', 'total_score',
])]
class ExamAttempt extends Model
{
    use HasFactory;

    /**
     * The statuses of a handed-in paper — what scopeSubmitted() matches, for
     * queries over the raw table too.
     */
    public const SUBMITTED_STATUSES = [ExamAttemptStatus::Submitted, ExamAttemptStatus::AutoSubmitted];

    /**
     * A handed-in paper's score as a share of its exam's total marks, in
     * SQL over `exam_attempts as attempt` joined to `exams` (whose total
     * marks must be above 0) — how every performance figure reads a score.
     */
    public const SCORE_PERCENT_SQL = '100.0 * attempt.total_score / exams.total_marks';

    /**
     * SUBMITTED_STATUSES as stored, for queries over the raw table.
     *
     * @return array<int, string>
     */
    public static function submittedStatusValues(): array
    {
        return array_map(fn (ExamAttemptStatus $status): string => $status->value, self::SUBMITTED_STATUSES);
    }

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
        return $query->whereIn($query->qualifyColumn('status'), self::SUBMITTED_STATUSES);
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
        // Opening a teacher's exam puts the student under that teacher's
        // referral (ReferralService decides whether it may).
        app(ReferralService::class)->attributeFromExam($student, $exam);

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
     * Puts a School's student into one of its exams, by the same rule. They
     * have no account here: the School's entry form takes their class,
     * section (where the class has them), roll and name — and those four
     * together are who they are, so the same student on another device
     * carries on with the same exam. Someone giving a taken roll under
     * another name gets an attempt of their own, which the School sorts out
     * when it sends the marks to its results.
     *
     * @param  array{id: int, name: string}  $class  As the school's software lists it.
     * @param  array{id: int, name: string}|null  $section
     */
    public static function startForSchoolStudent(Exam $exam, array $class, ?array $section, string $roll, string $name): self
    {
        $roll = self::normalizeRoll($roll);

        return self::enter(
            $exam,
            fn (): Collection => self::ofSchoolRoll($exam, $class['id'], $section['id'] ?? null, $roll)
                ->filter(fn (self $attempt): bool => self::normalizeGuestName((string) $attempt->guest_name) === self::normalizeGuestName($name))
                ->values(),
            [
                'student_id' => null,
                'is_guest' => true,
                'guest_name' => trim($name),
                'school_class_id' => $class['id'],
                'school_class_name' => $class['name'],
                'school_section_id' => $section['id'] ?? null,
                'school_section_name' => $section['name'] ?? null,
                'school_roll' => $roll,
            ],
        );
    }

    /**
     * The submitted attempt a School's student gets back with their class,
     * section and roll — the first, when the roll was used more than once.
     */
    public static function findSchoolResult(Exam $exam, int $classId, ?int $sectionId, string $roll): ?self
    {
        return self::ofSchoolRoll($exam, $classId, $sectionId, self::normalizeRoll($roll))
            ->first(fn (self $attempt): bool => ! $attempt->isInProgress());
    }

    /**
     * A roll number in one spelling: Bangla digits as English ones, no
     * spaces, no leading zeros — "০৫", " 5" and "05" are all roll 5.
     */
    public static function normalizeRoll(string $roll): string
    {
        $roll = Locale::toEnglishDigits(trim($roll));
        $roll = preg_replace('/\s+/u', '', $roll);

        return ctype_digit($roll) ? (ltrim($roll, '0') ?: '0') : mb_strtoupper($roll);
    }

    /**
     * Whether a School's entry form let this attempt in.
     */
    public function isSchoolAttempt(): bool
    {
        return $this->school_class_id !== null;
    }

    /**
     * Every attempt under one class + section + roll of a School's exam,
     * oldest first.
     *
     * @return Collection<int, self>
     */
    private static function ofSchoolRoll(Exam $exam, int $classId, ?int $sectionId, string $roll): Collection
    {
        return self::query()
            ->where('exam_id', $exam->id)
            ->where('school_class_id', $classId)
            ->where('school_section_id', $sectionId)
            ->where('school_roll', $roll)
            ->orderBy('id')
            ->get();
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
                // Counted under the lock above, so two people can't both
                // take the last place.
                if ($exam->isFull()) {
                    throw ValidationException::withMessages([
                        'exam' => __('This exam is full: as many people as it allows have already started it.'),
                    ]);
                }

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
     * How to tell them apart: a School student's class, section and roll; a
     * guest's phone/email; or the student's email.
     */
    public function participantContact(): ?string
    {
        if ($this->isSchoolAttempt()) {
            return collect([$this->school_class_name, $this->school_section_name, __('Roll :roll', ['roll' => $this->school_roll])])->filter()->join(' · ');
        }

        return $this->is_guest ? $this->guest_contact : $this->student?->email;
    }

    /**
     * What participantContact() is, as a label.
     */
    public function participantContactLabel(): string
    {
        return $this->isSchoolAttempt() ? __('Class / Roll') : __('Phone/Email');
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
     * @return Collection<int, Question|BoardMcqQuestion>
     */
    public function shuffledQuestions(): Collection
    {
        // The questions under one stimulus move as one block and keep their
        // own order (QuestionBlocks::units()); a paper without any shuffles
        // exactly as it always has.
        $units = $this->shuffleWithSeed(QuestionBlocks::units($this->exam->onlineItems()), $this->id);

        return new Collection(array_merge(...$units));
    }

    /**
     * The answers on record, keyed by the question each answers
     * (AttemptAnswer::keyFor() — "12" for a question-bank question, "p12"
     * for a past paper's MCQ).
     *
     * @return array<string, string>
     */
    public function savedAnswers(): array
    {
        return $this->answers()
            ->get(['question_id', 'board_mcq_question_id', 'student_answer'])
            ->mapWithKeys(fn (AttemptAnswer $answer): array => [$answer->key() => $answer->student_answer])
            ->all();
    }

    /**
     * The graded answers keyed the same way, for showing a result.
     *
     * @return Collection<string, AttemptAnswer>
     */
    public function answersByQuestion(): Collection
    {
        return $this->answers->keyBy(fn (AttemptAnswer $answer): string => $answer->key());
    }

    /**
     * Writes answer rows: each kind of question against its own column's
     * unique key, so a paper of both kinds is still one statement per kind.
     *
     * @param  SupportCollection<int, array{question: Model, values: array<string, mixed>}>  $rows
     * @param  array<int, string>  $update
     */
    private function upsertAnswers(SupportCollection $rows, array $update): void
    {
        $rows->groupBy(fn (array $row): string => AttemptAnswer::columnFor($row['question']))
            ->each(fn (SupportCollection $rowsOfKind, string $column) => AttemptAnswer::upsert(
                $rowsOfKind->map(fn (array $row): array => [
                    'attempt_id' => $this->id,
                    $column => $row['question']->getKey(),
                    ...$row['values'],
                ])->values()->all(),
                ['attempt_id', $column],
                $update,
            ));
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

        $examQuestions = $this->exam->onlineItems()->keyBy(fn (Model $question): string => AttemptAnswer::keyFor($question));

        $rows = collect($answers)
            ->filter(fn (mixed $studentAnswer, int|string $key): bool => $examQuestions->has((string) $key)
                && is_string($studentAnswer)
                && $studentAnswer !== '')
            ->map(fn (string $studentAnswer, int|string $key): array => [
                'question' => $examQuestions->get((string) $key),
                'values' => ['student_answer' => $studentAnswer],
            ])
            ->values();

        // One statement for the whole paper rather than two per question —
        // a whole class's pages send this at the same instant when the
        // clock runs out.
        $this->upsertAnswers($rows, ['student_answer']);

        $this->unsetRelation('answers');
    }

    /**
     * Auto-grades every MCQ answer against whichever of the question's
     * `options` carries `is_correct` and sums the result into total_score.
     * With negative marking (Exam::marksLostPerWrongAnswer()) a wrong answer
     * scores minus that much; a blank one scores 0; the total never goes
     * below 0. CQ answers are left for a teacher to grade manually
     * (obtained_marks stays whatever it already was — 0 by default) — only
     * the MCQ portion of total_score is ever computed here. A past paper's
     * MCQ without a correct answer is judged neither right nor wrong
     * (is_correct stays null); it only carries the marks the exam gives
     * everyone for it (awardNoAnswerMarks()).
     */
    public function submitAndAutoGrade(): void
    {
        // The exam's questions are already in memory, so no answer has to
        // load its own question.
        $exam = $this->exam;
        // Every question of the exam, the printed-only CQs too: their answers
        // are left as they are below.
        $examQuestions = ($exam->isPastPaper() || $exam->holdsPaperQuestions() ? $exam->onlineItems() : $exam->questions)
            ->keyBy(fn (Model $question): string => AttemptAnswer::keyFor($question));
        $penalty = $exam->marksLostPerWrongAnswer();

        /** @var array<string, array{is_correct: bool, obtained_marks: float, ids: array<int, int>}> $outcomes */
        $outcomes = [];
        $correctQuestionIds = [];
        $correctPaperQuestionIds = [];

        foreach ($this->answers()->get(['id', 'question_id', 'board_mcq_question_id', 'student_answer']) as $answer) {
            $question = $examQuestions->get($answer->key());

            if ($question?->question_type !== QuestionType::Mcq || $exam->isWithoutCorrectAnswer($question)) {
                continue;
            }

            $isCorrect = $question->isCorrectAnswer($answer->student_answer);
            $marks = match (true) {
                (bool) $isCorrect => (float) ($question->pivot?->marks_override ?? $question->marks),
                blank($answer->student_answer) => 0.0,
                default => -$penalty,
            };

            $outcomes["{$isCorrect}|{$marks}"] ??= ['is_correct' => (bool) $isCorrect, 'obtained_marks' => $marks, 'ids' => []];
            $outcomes["{$isCorrect}|{$marks}"]['ids'][] = $answer->id;

            if ($isCorrect && $question instanceof BoardMcqQuestion) {
                $correctPaperQuestionIds[] = $question->id;
            } elseif ($isCorrect) {
                $correctQuestionIds[] = $question->id;
            }
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

        $this->awardNoAnswerMarks($examQuestions);

        $this->unsetRelation('answers');

        $this->forceFill([
            'status' => ExamAttemptStatus::Submitted,
            'submitted_at' => now(),
            'total_score' => max(0, (float) $this->answers()->sum('obtained_marks')),
        ])->save();

        // How the paper went feeds each question's difficulty — a question
        // of the pool's, which a past paper's are not.
        if (! $exam->isPastPaper()) {
            app(QuestionDifficultyTracker::class)->recordAttempt($this, $correctQuestionIds);
        }

        $this->recordStatistics($correctQuestionIds, $correctPaperQuestionIds);
    }

    /**
     * When the exam gives everyone the marks of a question without a correct
     * answer: everyone, so a row of its own even where the question was left
     * blank. What the student picked, if anything, stays as it is.
     *
     * @param  Collection<int, Model>  $examQuestions
     */
    private function awardNoAnswerMarks(Collection $examQuestions): void
    {
        $exam = $this->exam;

        if ($exam->no_answer_marking !== NoAnswerMarking::FullMarks) {
            return;
        }

        $rows = $examQuestions
            ->filter(fn (Model $question): bool => $exam->isWithoutCorrectAnswer($question))
            ->map(fn (Model $question): array => ['question' => $question, 'values' => ['obtained_marks' => $question->marks]])
            ->values();

        $this->upsertAnswers($rows, ['obtained_marks']);
    }

    /**
     * Passes a graded paper on to what is kept about answers beyond the
     * paper itself: the student's own mistakes list (every kind of exam,
     * past papers too), and their teacher's numbers for each question (the
     * question bank's only). Does nothing the second time for the same
     * attempt.
     *
     * @param  array<int, int>  $correctQuestionIds  The question-bank questions this attempt answered correctly.
     * @param  array<int, int>  $correctPaperQuestionIds  The past paper MCQs it answered correctly.
     */
    public function recordStatistics(array $correctQuestionIds, array $correctPaperQuestionIds = []): void
    {
        // Claiming the attempt and finding it already claimed are one
        // statement, so two submissions arriving together record it once.
        $claimed = DB::table('exam_attempts')
            ->where('id', $this->id)
            ->whereNull('statistics_recorded_at')
            ->update(['statistics_recorded_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        app(StudentMistakeTracker::class)->recordAttempt($this, $correctQuestionIds, $correctPaperQuestionIds);

        // A past paper's questions are not the pool's, so the teacher's
        // question numbers don't take them.
        if (! $this->exam->isPastPaper()) {
            app(TeacherQuestionStats::class)->recordAttempt($this, $correctQuestionIds);
        }
    }

    /**
     * Records every handed-in attempt that hasn't been yet, oldest first —
     * what brings in the exams sat before these records existed, by the
     * very same path a paper handed in today takes. Safe to run again.
     */
    public static function recordUnrecordedStatistics(): void
    {
        self::query()
            ->submitted()
            ->whereNull('statistics_recorded_at')
            ->with(self::withCorrectAnswers())
            ->chunkById(200, function (Collection $attempts): void {
                foreach ($attempts as $attempt) {
                    if ($attempt->exam) {
                        $attempt->recordStatistics(...$attempt->correctlyAnsweredIds());
                    }
                }
            });
    }

    /**
     * What replaying a handed-in attempt needs loaded: its exam's questions
     * and only its correct answers.
     *
     * @return array<string|int, mixed>
     */
    public static function withCorrectAnswers(): array
    {
        return [
            'exam.questions',
            'answers' => fn (HasMany $answers) => $answers->where('is_correct', true)->select(['id', 'attempt_id', 'question_id', 'board_mcq_question_id', 'is_correct']),
        ];
    }

    /**
     * The questions a graded attempt got right, from its loaded answers
     * (withCorrectAnswers()) — the question bank's and the past papers'.
     *
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    public function correctlyAnsweredIds(): array
    {
        $correct = $this->answers->where('is_correct', true);

        return [
            $correct->pluck('question_id')->filter()->values()->all(),
            $correct->pluck('board_mcq_question_id')->filter()->values()->all(),
        ];
    }
}
