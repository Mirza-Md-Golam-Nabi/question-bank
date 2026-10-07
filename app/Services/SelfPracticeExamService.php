<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\GenerationMode;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds a self-practice exam (Auto-Generate or Manual Selection — CLAUDE.md
 * rule 9 requires both) and starts the student's attempt on it in one shot,
 * since a self-practice exam only ever exists for that one student's own
 * attempt. Both modes fall through to the same createExamAndAttempt() so the
 * "approved+latest pool only" rule and the exam/attempt creation logic are
 * never duplicated per mode.
 */
class SelfPracticeExamService
{
    public function generateAuto(User $student, int $subjectId, ?Difficulty $difficulty, int $questionCount, ?array $chapterIds = null): ExamAttempt
    {
        $questions = Question::approvedPool()
            ->whereHas('chapter.classSubject', fn ($q) => $q
                ->where('subject_id', $subjectId)
                ->when($chapterIds, fn ($q) => $q->whereIn('id', $chapterIds)))
            ->when($difficulty, fn ($q) => $q->where('difficulty', $difficulty))
            ->inRandomOrder()
            ->limit($questionCount)
            ->get();

        return $this->createExamAndAttempt($student, $subjectId, $questions, GenerationMode::Auto);
    }

    /**
     * @param  array<int>  $questionIds
     */
    public function generateManual(User $student, int $subjectId, array $questionIds): ExamAttempt
    {
        $questions = Question::approvedPool()->whereIn('id', $questionIds)->get();

        return $this->createExamAndAttempt($student, $subjectId, $questions, GenerationMode::Manual);
    }

    /**
     * @param  Collection<int, Question>  $questions
     */
    private function createExamAndAttempt(User $student, int $subjectId, Collection $questions, GenerationMode $mode): ExamAttempt
    {
        return DB::transaction(function () use ($student, $subjectId, $questions, $mode) {
            $exam = Exam::create([
                'title' => __('Self Practice').' — '.now()->format('M j, Y H:i'),
                'created_by' => $student->id,
                'exam_type' => ExamType::SelfPractice,
                'generation_mode' => $mode,
                'subject_id' => $subjectId,
                'duration_minutes' => 30,
                'status' => ExamStatus::Draft,
            ]);

            foreach ($questions->values() as $index => $question) {
                $exam->questions()->attach($question->id, ['order_index' => $index + 1, 'marks_override' => null]);
            }

            $exam->recalculateTotalMarks();
            $exam->publish();

            return ExamAttempt::create([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'is_guest' => false,
                'started_at' => now(),
            ]);
        });
    }
}
