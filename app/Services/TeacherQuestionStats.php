<?php

namespace App\Services;

use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How a teacher's own students do on each MCQ, added up over all of that
 * teacher's exams — what lets the teacher find the questions their
 * students keep getting wrong and set them again. The one place those
 * numbers are written and read.
 *
 * Counted per teacher, not per class or batch: the system doesn't know
 * which students belong together, only whose exam they sat. Everyone who
 * hands in a paper counts, guests included; a blank is a wrong answer.
 * Like a student's mistakes, the numbers are kept against the question's
 * first version, so they carry over when the question is corrected.
 */
class TeacherQuestionStats
{
    /**
     * Adds a just-graded attempt to its exam's teacher's numbers. Two
     * queries whatever the length of the paper.
     *
     * @param  array<int, int>  $correctQuestionIds  The questions this attempt answered correctly.
     */
    public function recordAttempt(ExamAttempt $attempt, array $correctQuestionIds): void
    {
        $exam = $attempt->exam;

        if ($exam->exam_type !== ExamType::TeacherExam) {
            return;
        }

        $questions = $exam->questions->where('question_type', QuestionType::Mcq);

        $this->add($exam->created_by, $questions->whereIn('id', $correctQuestionIds), wrong: 0);
        $this->add($exam->created_by, $questions->whereNotIn('id', $correctQuestionIds), wrong: 1);
    }

    /**
     * Adds the teacher's numbers to a question query as
     * `students_answered` and `students_wrong` (both null where none of
     * their students has met the question yet).
     *
     * @param  Builder<Question>  $questions
     * @return Builder<Question>
     */
    public function addTo(Builder $questions, User $teacher): Builder
    {
        return $questions
            ->leftJoin('teacher_question_stats as teacher_stats', fn (JoinClause $join) => $join
                ->where('teacher_stats.teacher_id', $teacher->id)
                ->whereRaw('teacher_stats.question_id = coalesce(questions.parent_id, questions.id)'))
            ->select('questions.*')
            ->addSelect([
                'teacher_stats.answer_count as students_answered',
                'teacher_stats.wrong_count as students_wrong',
            ]);
    }

    /**
     * Narrows a query already given the numbers (addTo()) to the questions
     * the teacher's students have got wrong, the most-missed first.
     *
     * @param  Builder<Question>  $questions
     * @return Builder<Question>
     */
    public function mostMissedFirst(Builder $questions): Builder
    {
        return $questions
            ->where('teacher_stats.wrong_count', '>', 0)
            ->orderByRaw('teacher_stats.wrong_count * 1.0 / teacher_stats.answer_count desc')
            ->orderByDesc('teacher_stats.wrong_count');
    }

    /**
     * @param  Collection<int, Question>  $questions
     * @param  int  $wrong  1 when these were answered wrongly, 0 when correctly.
     */
    private function add(int $teacherId, Collection $questions, int $wrong): void
    {
        if ($questions->isEmpty()) {
            return;
        }

        DB::table('teacher_question_stats')->upsert(
            $questions
                ->map(fn (Question $question): array => [
                    'teacher_id' => $teacherId,
                    'question_id' => StudentMistakeTracker::firstVersionId($question),
                    'answer_count' => 1,
                    'wrong_count' => $wrong,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                // Two versions of one question on the same paper would be
                // the same row twice, which an upsert can't take.
                ->unique('question_id')
                ->values()
                ->all(),
            ['teacher_id', 'question_id'],
            [
                'answer_count' => DB::raw('teacher_question_stats.answer_count + 1'),
                'wrong_count' => DB::raw("teacher_question_stats.wrong_count + {$wrong}"),
                'updated_at' => now(),
            ],
        );
    }
}
