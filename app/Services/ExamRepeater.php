<?php

namespace App\Services;

use App\Enums\ExamType;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * "Take this exam again" (CLAUDE.md rule 10): a new exam with the same
 * paper for another group of students — its own link, results and answer
 * release, while the old one stays as it is. It is built the way any new
 * exam is (TeacherExamBuilder::build(), PastPaperExamService for a whole
 * past paper), so the plan is checked and the exam counted again.
 *
 * Each question goes on as its current version, if the teacher may still
 * use it; one since rejected, or edited and not yet approved again, is
 * left out.
 */
class ExamRepeater
{
    public function __construct(
        private readonly TeacherExamBuilder $builder,
        private readonly PastPaperExamService $pastPapers,
    ) {}

    /**
     * Whether this exam can be taken again by its teacher.
     */
    public function canRepeat(Exam $exam, User $teacher): bool
    {
        return $exam->created_by === $teacher->id
            && $exam->exam_type === ExamType::TeacherExam
            && ($exam->isPastPaper() || $exam->class_subject_id !== null);
    }

    /**
     * What the new exam would have: the ids of the questions going on it,
     * and how many of the old exam's are left out. A past paper is the
     * whole paper again, with nothing left out.
     *
     * @return array{questionIds: array<int, int>, leftOut: int}
     */
    public function preview(Exam $exam, User $teacher): array
    {
        if ($exam->isPastPaper()) {
            return ['questionIds' => [], 'leftOut' => 0];
        }

        $onExam = $exam->questions()->get(['questions.id', 'questions.parent_id']);
        $current = $this->currentVersions($onExam);
        $usable = $this->builder
            ->selectableQuestions($teacher, $exam->classSubject, $current)
            ->modelKeys();

        return ['questionIds' => $usable, 'leftOut' => $onExam->count() - count($usable)];
    }

    /**
     * @throws ValidationException when it can't be repeated, none of its questions can be used now, or the plan doesn't allow it
     */
    public function repeat(Exam $exam, User $teacher, string $title, int $durationMinutes, ?CarbonInterface $endsAt = null): Exam
    {
        if (! $this->canRepeat($exam, $teacher)) {
            throw ValidationException::withMessages(['exam' => __('This exam cannot be taken again.')]);
        }

        $repeated = DB::transaction(function () use ($exam, $teacher, $title, $durationMinutes, $endsAt): Exam {
            if ($exam->isPastPaper()) {
                $repeated = $this->pastPapers->createTeacherExam($teacher, $exam->boardQuestionPaper, $exam->delivery_mode, $durationMinutes, $exam->negative_marks);
                $repeated->forceFill([
                    'title' => $title,
                    'link_expires_at' => $exam->delivery_mode->includesOnline() ? $endsAt : null,
                ])->save();

                return $repeated;
            }

            $questionIds = $this->preview($exam, $teacher)['questionIds'];

            if ($questionIds === []) {
                throw ValidationException::withMessages(['exam' => __('None of the questions on this exam can be used any more, so it cannot be taken again.')]);
            }

            return $this->builder->build(
                $teacher,
                $exam->classSubject,
                $exam->delivery_mode,
                $questionIds,
                $title,
                $durationMinutes,
                $endsAt,
                $exam->negative_marks,
            );
        });

        Log::info('Exam taken again', ['from_exam_id' => $exam->id, 'exam_id' => $repeated->id, 'by' => $teacher->id]);

        return $repeated;
    }

    /**
     * The current version of each question, in the old exam's order — one
     * query for all of them.
     *
     * @param  Collection<int, Question>  $onExam
     * @return array<int, int>
     */
    private function currentVersions(Collection $onExam): array
    {
        $firstVersions = $onExam->map(fn (Question $question): int => $question->parent_id ?? $question->id)->unique()->values();

        $latest = Question::query()
            ->where('is_latest', true)
            ->where(fn ($query) => $query->whereIn('id', $firstVersions)->orWhereIn('parent_id', $firstVersions))
            ->get(['id', 'parent_id'])
            ->keyBy(fn (Question $question): int => $question->parent_id ?? $question->id);

        return $firstVersions
            ->map(fn (int $firstVersion): ?int => $latest->get($firstVersion)?->id)
            ->filter()
            ->values()
            ->all();
    }
}
