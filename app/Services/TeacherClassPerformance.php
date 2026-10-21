<?php

namespace App\Services;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\BillingSetting;
use App\Models\Chapter;
use App\Models\ExamAttempt;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How a teacher's class is doing, from the teacher's own exams only — the
 * one place its rules live (CLAUDE.md rule 11). A student's own practice
 * and other teachers' exams are never read here; one student's figures in
 * detail are StudentPerformance's, through PerformanceScope::teacherView().
 *
 * - The class figures (scores per exam, weak chapters) count everyone who
 *   sat, guests too, every answer.
 * - The figures per student are of logged-in students only: a guest is a
 *   name and a contact typed afresh on each exam, and two papers under one
 *   contact are not reliably the same person.
 * - A score is the paper's total score as a share of its total marks.
 */
class TeacherClassPerformance
{
    /**
     * The average score and the number who sat each of the teacher's latest
     * exams (BillingSetting::performance_trend_exams of them) that someone
     * has sat — oldest first.
     *
     * @return Collection<int, array{title: string, created_at: CarbonInterface, participants: int, average: float}>
     */
    public function classTrend(User $teacher, ?int $classSubjectId = null): Collection
    {
        return $this->attempts($teacher, $classSubjectId)
            ->where('exams.total_marks', '>', 0)
            ->groupBy('exams.id', 'exams.title', 'exams.created_at')
            ->orderByDesc('exams.created_at')
            ->orderByDesc('exams.id')
            ->limit(BillingSetting::current()->performance_trend_exams)
            ->selectRaw('exams.title, exams.created_at, count(*) as participants, avg('.ExamAttempt::SCORE_PERCENT_SQL.') as average')
            ->get()
            ->reverse()
            ->values()
            ->map(fn (object $row): array => [
                'title' => (string) $row->title,
                'created_at' => Carbon::parse($row->created_at),
                'participants' => (int) $row->participants,
                'average' => round((float) $row->average, 1),
            ]);
    }

    /**
     * The chapters the class answers worst on the teacher's exams, of those
     * with enough answers to say.
     *
     * @return Collection<int, array{chapter: Chapter, rate: PerformanceRate}>
     */
    public function weakChapters(User $teacher, ?int $classSubjectId = null, int $limit = 5): Collection
    {
        $rows = $this->attempts($teacher, $classSubjectId)
            ->whereNull('exams.board_question_paper_id')
            ->join('exam_questions as on_paper', 'on_paper.exam_id', '=', 'exams.id')
            ->join('questions', 'questions.id', '=', 'on_paper.question_id')
            ->leftJoin('attempt_answers as answer', fn (JoinClause $answer) => $answer
                ->on('answer.attempt_id', '=', 'attempt.id')
                ->on('answer.question_id', '=', 'questions.id'))
            ->where('questions.question_type', QuestionType::Mcq->value)
            ->groupBy('questions.chapter_id')
            ->havingRaw('count(*) >= ?', [BillingSetting::current()->performance_min_questions])
            ->orderByRaw('1.0 * sum(case when answer.is_correct = 1 then 1 else 0 end) / count(*)')
            ->limit($limit)
            ->selectRaw('questions.chapter_id, count(*) as questions')
            ->selectRaw('sum(case when answer.is_correct = 1 then 1 else 0 end) as correct, sum(case when answer.id is null then 1 else 0 end) as blank')
            ->get();

        $chapters = Chapter::query()
            ->with('classSubject.subject')
            ->whereKey($rows->pluck('chapter_id'))
            ->get()
            ->keyBy('id');

        return $rows
            ->filter(fn (object $row): bool => $chapters->has($row->chapter_id))
            ->map(fn (object $row): array => ['chapter' => $chapters[$row->chapter_id], 'rate' => PerformanceRate::fromRow($row)])
            ->values();
    }

    /**
     * The logged-in students who have sat the teacher's exams, each with
     * how many (`exams_count`), their average score (`average_score`) and
     * when they last sat one (`last_sat_at`) — worked out in the query, so
     * a page of them takes no extra queries.
     *
     * @return EloquentBuilder<User>
     */
    public function students(User $teacher, ?int $classSubjectId = null): EloquentBuilder
    {
        $theirs = fn (): Builder => $this->attempts($teacher, $classSubjectId)->whereColumn('attempt.student_id', 'users.id');

        return User::query()
            ->where('users.role', UserRole::Student)
            ->whereExists($theirs())
            ->select('users.*')
            ->selectSub($theirs()->selectRaw('count(*)'), 'exams_count')
            ->selectSub($theirs()->where('exams.total_marks', '>', 0)->selectRaw('avg('.ExamAttempt::SCORE_PERCENT_SQL.')'), 'average_score')
            ->selectSub($theirs()->selectRaw('max(attempt.submitted_at)'), 'last_sat_at');
    }

    /**
     * Whether the student has sat any of the teacher's exams — the only
     * students a teacher may look at in detail.
     */
    public function hasStudent(User $teacher, User $student): bool
    {
        return $this->attempts($teacher)->where('attempt.student_id', $student->id)->exists();
    }

    /**
     * Whether each student's score went up or down on their latest of the
     * teacher's exams, against the one before: 1, -1, or 0 (no change, or
     * only one exam). One query for all the students given.
     *
     * @param  array<int, int>  $studentIds
     * @return array<int, int> student id => direction
     */
    public function directions(User $teacher, array $studentIds, ?int $classSubjectId = null): array
    {
        return $this->latestScores($teacher, $classSubjectId, 2, $studentIds)
            ->map(fn (array $scores): int => count($scores) < 2 ? 0 : $scores[0] <=> $scores[1])
            ->all();
    }

    /**
     * The students who need the teacher's attention, of those who have sat
     * the teacher's exams:
     *
     * - `falling`: their score went down on each of their latest exams, as
     *   many in a row as BillingSetting::performance_falling_exams says;
     * - `inactive`: they have sat none for BillingSetting::performance_inactive_days
     *   days, while the teacher has given an exam since.
     *
     * @return Collection<int, array{student: User, falling: bool, inactive: bool, last_sat_at: CarbonInterface}>
     */
    public function needingAttention(User $teacher, ?int $classSubjectId = null): Collection
    {
        $settings = BillingSetting::current();

        $falling = $this->latestScores($teacher, $classSubjectId, $settings->performance_falling_exams + 1)
            ->filter(function (array $scores) use ($settings): bool {
                if (count($scores) <= $settings->performance_falling_exams) {
                    return false;
                }

                // Newest first: each score below the one before it.
                for ($i = 0; $i < $settings->performance_falling_exams; $i++) {
                    if ($scores[$i] >= $scores[$i + 1]) {
                        return false;
                    }
                }

                return true;
            })
            ->keys();

        $lastSat = $this->attempts($teacher, $classSubjectId)
            ->whereNotNull('attempt.student_id')
            ->groupBy('attempt.student_id')
            ->selectRaw('attempt.student_id, max(attempt.submitted_at) as last_sat_at')
            ->pluck('last_sat_at', 'student_id')
            ->map(fn (string $at): CarbonInterface => Carbon::parse($at));

        $latestExamAt = DB::table('exams')
            ->where('created_by', $teacher->id)
            ->where('exam_type', ExamType::TeacherExam->value)
            ->where('status', '!=', ExamStatus::Draft->value)
            ->when($classSubjectId, fn (Builder $exams) => $exams->where('class_subject_id', $classSubjectId))
            ->max('created_at');

        $inactiveSince = now()->subDays($settings->performance_inactive_days);
        $inactive = $latestExamAt === null ? collect() : $lastSat
            ->filter(fn (CarbonInterface $at): bool => $at->lt($inactiveSince) && $at->lt(Carbon::parse($latestExamAt)))
            ->keys();

        $ids = $falling->merge($inactive)->unique()->values();

        return User::query()
            ->whereKey($ids)
            ->where('role', UserRole::Student)
            ->get()
            ->map(fn (User $student): array => [
                'student' => $student,
                'falling' => $falling->contains($student->id),
                'inactive' => $inactive->contains($student->id),
                'last_sat_at' => $lastSat[$student->id],
            ])
            ->sortBy(fn (array $row): int => $row['last_sat_at']->getTimestamp())
            ->values();
    }

    /**
     * Each logged-in student's latest scores on the teacher's exams, newest
     * first, at most `$count` of them — one query, however many students.
     *
     * @param  array<int, int>|null  $studentIds  Null = every student.
     * @return Collection<int, array<int, float>> student id => scores
     */
    private function latestScores(User $teacher, ?int $classSubjectId, int $count, ?array $studentIds = null): Collection
    {
        $ranked = $this->attempts($teacher, $classSubjectId)
            ->where('exams.total_marks', '>', 0)
            ->whereNotNull('attempt.student_id')
            ->when($studentIds !== null, fn (Builder $attempts) => $attempts->whereIn('attempt.student_id', $studentIds))
            ->selectRaw('attempt.student_id, '.ExamAttempt::SCORE_PERCENT_SQL.' as percent')
            ->selectRaw('row_number() over (partition by attempt.student_id order by attempt.submitted_at desc, attempt.id desc) as score_rank');

        return DB::query()
            ->fromSub($ranked, 'scores')
            ->where('score_rank', '<=', $count)
            ->orderBy('score_rank')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $scores): array => $scores->map(fn (object $score): float => round((float) $score->percent, 1))->values()->all());
    }

    /**
     * The handed-in papers of the teacher's exams, joined to their exams —
     * of one class + subject when given.
     */
    private function attempts(User $teacher, ?int $classSubjectId = null): Builder
    {
        return DB::table('exam_attempts as attempt')
            ->join('exams', 'exams.id', '=', 'attempt.exam_id')
            ->where('exams.created_by', $teacher->id)
            ->where('exams.exam_type', ExamType::TeacherExam->value)
            ->when($classSubjectId, fn (Builder $attempts) => $attempts->where('exams.class_subject_id', $classSubjectId))
            ->whereIn('attempt.status', ExamAttempt::submittedStatusValues());
    }
}
