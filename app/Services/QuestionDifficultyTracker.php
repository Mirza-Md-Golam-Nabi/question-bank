<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Models\BillingSetting;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps an MCQ's difficulty in step with how students actually do on it:
 * each question carries how many have answered it and how many got it
 * right, and once enough have, its difficulty follows from that share —
 * few right is Hard, most right is Easy. The author's own choice only
 * stands until then. The one place those counts and that rule live.
 *
 * What counts is a student's handed-in paper on a teacher's exam — a
 * question left blank counts as wrong. Self-practice doesn't count: the
 * same question comes round again there, with its answer already shown.
 * CQ has no stored answer, so its difficulty stays the author's.
 *
 * The counts are kept on the question, not worked out from the answers
 * each time, so handing in a paper costs the same few queries however
 * long the paper is — and old attempts can be deleted without losing them.
 * Every number of the rule is the Admin's (BillingSetting).
 */
class QuestionDifficultyTracker
{
    /**
     * Adds a just-graded attempt to the counts of the questions it was
     * sat on. Does nothing the second time for the same attempt.
     *
     * @param  array<int, int>  $correctQuestionIds  The questions this attempt answered correctly.
     */
    public function recordAttempt(ExamAttempt $attempt, array $correctQuestionIds): void
    {
        $exam = $attempt->exam;

        if ($exam->exam_type !== ExamType::TeacherExam) {
            return;
        }

        // Claiming the attempt and finding it already claimed are one
        // statement, so two submissions arriving together count it once.
        $claimed = DB::table('exam_attempts')
            ->where('id', $attempt->id)
            ->whereNull('difficulty_counted_at')
            ->update(['difficulty_counted_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        // Every MCQ of the paper, answered or not: a blank is a wrong answer.
        $questionIds = $exam->questions
            ->where('question_type', QuestionType::Mcq)
            ->pluck('id');

        $this->add('answer_count', $questionIds, 1);
        $this->add('correct_answer_count', $questionIds->intersect($correctQuestionIds), 1);
        $this->recalculate($questionIds);
    }

    /**
     * Takes an exam's attempts back out of the counts, for when the exam
     * is cancelled and everyone sits it again. Call it before the attempts
     * and their answers are deleted.
     *
     * Not for clearing out old history: attempts deleted only because
     * they are old stay counted.
     */
    public function forgetAttemptsOf(Exam $exam): void
    {
        $counted = fn (): Builder => DB::table('exam_attempts')
            ->where('exam_id', $exam->id)
            ->whereNotNull('difficulty_counted_at');

        $attemptCount = $counted()->count();

        if ($attemptCount === 0) {
            return;
        }

        $questionIds = $exam->questions()
            ->where('questions.question_type', QuestionType::Mcq)
            ->pluck('questions.id');

        $this->add('answer_count', $questionIds, -$attemptCount);
        $this->addEach('correct_answer_count', $this->correctCounts($counted()->select('id')), -1);
        $this->recalculate($questionIds);
    }

    /**
     * Counts every handed-in attempt on a teacher's exam that hasn't been
     * counted yet — what brings in the exams sat before the counts
     * existed. Safe to run again: an attempt is only ever counted once.
     *
     * Where the old data holds several attempts by one participant on an
     * exam, only their first counts, as it does for their result.
     */
    public function countUncountedAttempts(): void
    {
        $handedIn = [ExamAttemptStatus::Submitted->value, ExamAttemptStatus::AutoSubmitted->value];

        $attemptIds = DB::table('exam_attempts as attempt')
            ->join('exams', 'exams.id', '=', 'attempt.exam_id')
            ->where('exams.exam_type', ExamType::TeacherExam->value)
            ->whereIn('attempt.status', $handedIn)
            ->whereNull('attempt.difficulty_counted_at')
            ->whereNotExists(fn (Builder $earlier) => $earlier
                ->from('exam_attempts as earlier')
                ->whereColumn('earlier.exam_id', 'attempt.exam_id')
                ->whereColumn('earlier.id', '<', 'attempt.id')
                ->whereIn('earlier.status', $handedIn)
                ->where(fn (Builder $samePerson) => $samePerson
                    ->where(fn (Builder $student) => $student
                        ->whereNotNull('attempt.student_id')
                        ->whereColumn('earlier.student_id', 'attempt.student_id'))
                    ->orWhere(fn (Builder $guest) => $guest
                        ->whereNull('attempt.student_id')
                        ->whereNull('earlier.student_id')
                        ->whereColumn('earlier.guest_contact', 'attempt.guest_contact'))))
            ->orderBy('attempt.id')
            ->pluck('attempt.id');

        foreach ($attemptIds->chunk(500) as $chunk) {
            $paperCounts = DB::table('exam_attempts')
                ->join('exam_questions', 'exam_questions.exam_id', '=', 'exam_attempts.exam_id')
                ->join('questions', 'questions.id', '=', 'exam_questions.question_id')
                ->whereIn('exam_attempts.id', $chunk)
                ->where('questions.question_type', QuestionType::Mcq->value)
                ->groupBy('exam_questions.question_id')
                ->selectRaw('exam_questions.question_id, count(*) as total')
                ->pluck('total', 'question_id');

            $this->addEach('answer_count', $paperCounts, 1);
            $this->addEach('correct_answer_count', $this->correctCounts($chunk->all()), 1);

            DB::table('exam_attempts')->whereIn('id', $chunk)->update(['difficulty_counted_at' => now()]);
        }

        if ($attemptIds->isNotEmpty()) {
            $this->recalculate();
        }
    }

    /**
     * Whether enough students have answered for the difficulty to be the
     * calculated one rather than the author's.
     */
    public static function hasEnoughAnswers(int $answerCount): bool
    {
        return $answerCount >= BillingSetting::current()->difficulty_min_answers;
    }

    /**
     * Sets the difficulty of the given questions (or of all of them) from
     * their counts — only where enough students have answered. The rule
     * itself: up to the "hard" share right is Hard, up to the "medium"
     * share is Medium, anything above is Easy.
     *
     * @param  Collection<int, int>|null  $questionIds
     */
    private function recalculate(?Collection $questionIds = null): void
    {
        if ($questionIds?->isEmpty()) {
            return;
        }

        $settings = BillingSetting::current();

        // The limits are numbers of ours, written out with a fixed decimal
        // point; nothing typed by a user reaches this expression.
        $difficulty = sprintf(
            "case when correct_answer_count * 100 <= answer_count * %F then '%s' when correct_answer_count * 100 <= answer_count * %F then '%s' else '%s' end",
            $settings->difficulty_hard_max_percent,
            Difficulty::Hard->value,
            $settings->difficulty_medium_max_percent,
            Difficulty::Medium->value,
            Difficulty::Easy->value,
        );

        DB::table('questions')
            ->when($questionIds, fn (Builder $query) => $query->whereIn('id', $questionIds))
            ->where('answer_count', '>=', $settings->difficulty_min_answers)
            ->update(['difficulty' => DB::raw($difficulty)]);
    }

    /**
     * How many of the given attempts answered each question correctly.
     *
     * @param  Builder|array<int, int>  $attemptIds
     * @return Collection<int, int> question id => correct answers
     */
    private function correctCounts(Builder|array $attemptIds): Collection
    {
        return DB::table('attempt_answers')
            ->join('questions', 'questions.id', '=', 'attempt_answers.question_id')
            ->whereIn('attempt_answers.attempt_id', $attemptIds)
            ->where('attempt_answers.is_correct', true)
            ->where('questions.question_type', QuestionType::Mcq->value)
            ->groupBy('attempt_answers.question_id')
            ->selectRaw('attempt_answers.question_id, count(*) as total')
            ->pluck('total', 'question_id');
    }

    /**
     * Adds a different amount to each question — questions due the same
     * amount go in one statement together.
     *
     * @param  Collection<int, int>  $amountByQuestion  question id => amount
     * @param  int  $sign  1 to add the amounts, -1 to take them away.
     */
    private function addEach(string $column, Collection $amountByQuestion, int $sign): void
    {
        $amountByQuestion
            ->keys()
            ->groupBy(fn (int $questionId): int => (int) $amountByQuestion[$questionId])
            ->each(fn (Collection $questionIds, int $amount) => $this->add($column, $questionIds, $sign * $amount));
    }

    /**
     * Straight on the table: counting an answer is not an edit of the
     * question, so it must not touch its updated_at. A count never goes
     * below zero.
     *
     * @param  Collection<int, int>  $questionIds
     */
    private function add(string $column, Collection $questionIds, int $amount): void
    {
        if ($questionIds->isEmpty() || $amount === 0) {
            return;
        }

        $expression = $amount > 0
            ? sprintf('%s + %d', $column, $amount)
            : sprintf('case when %1$s >= %2$d then %1$s - %2$d else 0 end', $column, -$amount);

        DB::table('questions')->whereIn('id', $questionIds)->update([$column => DB::raw($expression)]);
    }
}
