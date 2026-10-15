<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\BillingSetting;
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
     * How many practisable mistakes the student has in each subject —
     * what the practice page offers to choose from.
     *
     * @return Collection<int, int> subject id => mistakes
     */
    public function countsBySubject(User $student): Collection
    {
        return $this->practisableQuestions($student)
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->join('class_subjects', 'class_subjects.id', '=', 'chapters.class_subject_id')
            ->groupBy('class_subjects.subject_id')
            ->toBase()
            ->selectRaw('class_subjects.subject_id, count(*) as mistakes')
            ->pluck('mistakes', 'subject_id');
    }

    /**
     * Up to `$count` of the student's practisable mistakes in a subject,
     * drawn at random.
     *
     * @return EloquentCollection<int, Question>
     */
    public function drawForPractice(User $student, int $subjectId, int $count): EloquentCollection
    {
        // Drawn over ids first, as the auto-generated practice exam does.
        $ids = $this->practisableQuestions($student)->ofSubject($subjectId)->pluck('questions.id');

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
