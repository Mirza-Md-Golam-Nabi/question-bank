<?php

namespace App\Models;

use App\Enums\ExamAttemptStatus;
use App\Enums\QuestionType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        return self::query()
            ->where('exam_id', $exam->id)
            ->where('is_guest', true)
            ->where('guest_contact', self::normalizeGuestContact($contact))
            ->where('status', ExamAttemptStatus::Submitted)
            ->orderBy('id')
            ->get()
            ->first(fn (self $attempt) => self::normalizeGuestName((string) $attempt->guest_name) === self::normalizeGuestName($name));
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

        foreach ($answers as $questionId => $studentAnswer) {
            if (! $examQuestionIds->has($questionId) || ! is_string($studentAnswer) || $studentAnswer === '') {
                continue;
            }

            $this->answers()->updateOrCreate(
                ['question_id' => $questionId],
                ['student_answer' => $studentAnswer],
            );
        }
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
        $examQuestionsById = $this->exam->questions->keyBy('id');

        foreach ($this->answers as $answer) {
            $question = $answer->question;

            if ($question->question_type !== QuestionType::Mcq) {
                continue;
            }

            $correctOption = collect($question->options)
                ->first(fn (array $option) => (bool) ($option['is_correct'] ?? false));

            $isCorrect = $correctOption && $answer->student_answer === $correctOption['option'];
            $marks = $examQuestionsById->get($question->id)?->pivot?->marks_override ?? $question->marks;

            $answer->update([
                'is_correct' => $isCorrect,
                'obtained_marks' => $isCorrect ? $marks : 0,
            ]);
        }

        $this->forceFill([
            'status' => ExamAttemptStatus::Submitted,
            'submitted_at' => now(),
            'total_score' => $this->answers()->sum('obtained_marks'),
        ])->save();
    }
}
