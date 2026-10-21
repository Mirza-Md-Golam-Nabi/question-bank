<?php

namespace App\Services;

use App\Enums\ExamType;
use App\Enums\PerformanceSource;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Database\Query\Builder;

/**
 * Whose exams a student's performance is worked out from, and for whom.
 *
 * - The student's own view: every exam they have sat (or one kind of it),
 *   but a teacher's exam adds to the question-by-question figures only once
 *   its answers are out — otherwise a chapter's rate would hint at them.
 * - A teacher's view of the student: only that teacher's own exams, never
 *   the student's own practice or another teacher's exams. The teacher
 *   already sees every answer on their own exams, so nothing is held back.
 */
final class PerformanceScope
{
    private function __construct(
        public readonly int $studentId,
        public readonly ?int $teacherId,
        public readonly PerformanceSource $source,
    ) {}

    public static function ownView(User $student, PerformanceSource $source = PerformanceSource::All): self
    {
        return new self($student->id, null, $source);
    }

    public static function teacherView(User $student, User $teacher): self
    {
        return new self($student->id, $teacher->id, PerformanceSource::All);
    }

    public function isTeacherView(): bool
    {
        return $this->teacherId !== null;
    }

    /**
     * Narrows a query joined to `exams` to the exams this scope reads.
     */
    public function constrainExams(Builder $exams): Builder
    {
        if ($this->isTeacherView()) {
            return $exams->where('exams.created_by', $this->teacherId)->where('exams.exam_type', ExamType::TeacherExam->value);
        }

        return $this->source->constrain($exams);
    }

    /**
     * Narrows a query joined to `exams` to the exams whose answers may be
     * counted question by question.
     */
    public function constrainAnsweredExams(Builder $exams): Builder
    {
        $exams = $this->constrainExams($exams);

        return $this->isTeacherView() ? $exams : Exam::whereShowsAnswersToStudents($exams);
    }
}
