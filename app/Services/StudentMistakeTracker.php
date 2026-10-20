<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\BillingSetting;
use App\Models\ClassSubject;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A student's mistakes list: the MCQs they got wrong, kept until they have
 * answered each correctly a number of times in a row (the Admin's number —
 * BillingSetting::mistake_clear_streak). The one place that list is
 * written and read.
 *
 * Every paper a logged-in student hands in feeds it, whatever kind of exam
 * it was: a wrong answer (or a blank) puts the question on the list and
 * starts its run of correct answers again; a correct answer adds to the
 * run, and a long enough run takes the question off.
 *
 * A mistake is recorded against the question's first version, so a
 * question corrected since is still the same mistake, practised in its
 * current wording.
 *
 * One thing must never happen: a mistake made on a teacher's exam being
 * practised — answer shown — while that exam's answers are still hidden.
 * So such a mistake stays locked until the exam releases its answers.
 */
class StudentMistakeTracker
{
    /**
     * Brings the list up to date with a just-graded attempt. A handful of
     * queries whatever the length of the paper.
     *
     * @param  array<int, int>  $correctQuestionIds  The questions this attempt answered correctly.
     */
    public function recordAttempt(ExamAttempt $attempt, array $correctQuestionIds): void
    {
        if (! $attempt->student_id) {
            return;
        }

        $exam = $attempt->exam;
        $questions = $exam->questions->where('question_type', QuestionType::Mcq);

        $firstVersionIds = fn (Collection $questions): Collection => $questions
            ->map(fn (Question $question): int => self::firstVersionId($question))
            ->unique()
            ->values();

        $right = $firstVersionIds($questions->whereIn('id', $correctQuestionIds));
        $wrong = $firstVersionIds($questions->whereNotIn('id', $correctQuestionIds));

        if ($wrong->isNotEmpty()) {
            $locksUntilAnswersAreOut = ! $exam->showsAnswersToStudents();

            DB::table('student_question_mistakes')->upsert(
                $wrong->map(fn (int $questionId): array => [
                    'student_id' => $attempt->student_id,
                    'question_id' => $questionId,
                    'correct_streak' => 0,
                    'locked_by_exam_id' => $locksUntilAnswersAreOut ? $exam->id : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all(),
                ['student_id', 'question_id'],
                // A mistake already locked by another exam stays locked by
                // it unless this exam locks it too.
                ['correct_streak', 'updated_at', ...($locksUntilAnswersAreOut ? ['locked_by_exam_id'] : [])],
            );
        }

        if ($right->isNotEmpty()) {
            $onTheList = fn (): QueryBuilder => DB::table('student_question_mistakes')
                ->where('student_id', $attempt->student_id)
                ->whereIn('question_id', $right);

            $onTheList()->update(['correct_streak' => DB::raw('correct_streak + 1'), 'updated_at' => now()]);
            $onTheList()->where('correct_streak', '>=', BillingSetting::current()->mistake_clear_streak)->delete();
        }
    }

    /**
     * The questions a student can practise their mistakes on right now:
     * the current approved version of each mistake that isn't locked.
     * Self-practice shows the answer, so only approved questions qualify —
     * a teacher's own pending question waits until it is approved.
     *
     * Given a question query of the caller's own (a chapter's questions,
     * say), it narrows that one instead of starting a new one.
     *
     * @param  Builder<Question>|null  $questions
     * @return Builder<Question>
     */
    public function practisableQuestions(User $student, ?Builder $questions = null): Builder
    {
        return ($questions ?? Question::query())
            ->approvedPool()
            ->where('questions.question_type', QuestionType::Mcq)
            ->whereExists(fn (QueryBuilder $mistake) => $mistake
                ->from('student_question_mistakes as mistake')
                ->where('mistake.student_id', $student->id)
                ->whereRaw('mistake.question_id = coalesce(questions.parent_id, questions.id)')
                ->where(fn (QueryBuilder $unlocked) => $unlocked
                    ->whereNull('mistake.locked_by_exam_id')
                    ->orWhereExists(fn (QueryBuilder $exam) => $exam
                        ->from('exams')
                        ->whereColumn('exams.id', 'mistake.locked_by_exam_id')
                        ->where(fn (QueryBuilder $released) => self::answersAreOut($released)))));
    }

    /**
     * The questions a student has had on a paper that fed their mistakes
     * list (answered or left blank — a blank is a mistake too): what their
     * mistakes in a chapter are a share of. Read like practisableQuestions():
     * the current approved MCQ of each, matched across versions.
     *
     * @param  Builder<Question>|null  $questions
     * @return Builder<Question>
     */
    public function questionsSat(User $student, ?Builder $questions = null): Builder
    {
        return ($questions ?? Question::query())
            ->approvedPool()
            ->where('questions.question_type', QuestionType::Mcq)
            ->whereExists(fn (QueryBuilder $sat) => $sat
                ->from('exam_attempts as attempt')
                ->join('exam_questions as on_paper', 'on_paper.exam_id', '=', 'attempt.exam_id')
                ->join('questions as asked', 'asked.id', '=', 'on_paper.question_id')
                ->where('attempt.student_id', $student->id)
                ->whereNotNull('attempt.statistics_recorded_at')
                ->whereRaw('coalesce(asked.parent_id, asked.id) = coalesce(questions.parent_id, questions.id)'));
    }

    /**
     * How large a share of the questions sat the mistakes are, in whole
     * percent: 3 mistakes of 5 questions is 60.
     */
    public static function mistakePercent(int $mistakes, int $sat): int
    {
        return $sat > 0 ? (int) min(100, round($mistakes / $sat * 100)) : 0;
    }

    /**
     * How many practisable mistakes the student has in each subject of a
     * class — what the practice page offers to choose from.
     *
     * @return Collection<int, int> class_subject id => mistakes
     */
    public function countsByClassSubject(User $student, int $academicClassId): Collection
    {
        return $this->practisableQuestions($student)
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->join('class_subjects', 'class_subjects.id', '=', 'chapters.class_subject_id')
            ->where('class_subjects.academic_class_id', $academicClassId)
            ->groupBy('class_subjects.id')
            ->toBase()
            ->selectRaw('class_subjects.id as class_subject_id, count(*) as mistakes')
            ->pluck('mistakes', 'class_subject_id');
    }

    /**
     * Up to `$count` of the student's practisable mistakes in one class's
     * subject, drawn at random — of the given difficulties only, when the
     * student's plan limits them.
     *
     * @param  array<int, Difficulty>|null  $difficulties
     * @return EloquentCollection<int, Question>
     */
    public function drawForPractice(User $student, ClassSubject $classSubject, int $count, ?array $difficulties = null): EloquentCollection
    {
        // Drawn over ids first, as the auto-generated practice exam does.
        $ids = $this->practisableQuestions($student)
            ->ofClassSubject($classSubject->id)
            ->when($difficulties !== null, fn (Builder $query) => $query->whereIn('questions.difficulty', $difficulties))
            ->pluck('questions.id');

        return Question::query()
            ->whereKey($ids->random(min($count, $ids->count())))
            ->get()
            ->shuffle();
    }

    /**
     * The one statement of "this exam's answers can be seen", in SQL — the
     * same rule as Exam::showsAnswersToStudents() for a teacher's exam.
     */
    private static function answersAreOut(QueryBuilder $exams): QueryBuilder
    {
        return $exams
            ->whereNotNull('exams.answers_released_at')
            ->orWhere('exams.answers_release_at', '<=', now());
    }

    /**
     * The id every version of a question shares: that of its first version.
     */
    public static function firstVersionId(Question $question): int
    {
        return $question->parent_id ?? $question->id;
    }
}
