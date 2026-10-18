<?php

namespace App\Services;

use App\Enums\ExamDeliveryMode;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\GenerationMode;
use App\Enums\QuestionStatus;
use App\Models\BoardQuestionPaper;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a whole approved past paper into an exam — the only way a past
 * paper is ever used (CLAUDE.md rule 5: its questions never join the
 * question pool). A student sits it at once as their own practice, with
 * the answers and explanations shown as soon as they hand it in; a teacher
 * gets a draft exam to publish online, print, or both, like any other.
 *
 * Every exam is first allowed — and counted — by the user's plans
 * (SubscriptionLimitService::authorizePastPaper()), against a past-paper
 * allowance of its own, separate from the self-practice and exam limits.
 */
class PastPaperExamService
{
    public function __construct(private readonly SubscriptionLimitService $subscriptionLimits) {}

    /**
     * A student's practice sitting of the paper, started now — a new one
     * every time, each counted.
     *
     * @throws ValidationException when the paper can't be sat or the student's plans don't allow it
     */
    public function startPractice(User $student, BoardQuestionPaper $paper): ExamAttempt
    {
        $this->ensureCanBeSatOnline($paper);

        $grant = $this->subscriptionLimits->authorizePastPaper($student, $paper);

        return DB::transaction(function () use ($student, $grant, $paper): ExamAttempt {
            $exam = $this->createExam($student, $grant, $paper, [
                'exam_type' => ExamType::SelfPractice,
                'delivery_mode' => ExamDeliveryMode::Online,
                'duration_minutes' => $paper->examDurationMinutes(),
                'negative_marks' => $this->negativeMarks($paper->negative_marks),
            ]);

            $exam->publish();

            return ExamAttempt::create([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'is_guest' => false,
                'started_at' => now(),
            ]);
        });
    }

    /**
     * A teacher's exam of the whole paper, saved as a draft: published from
     * the exams list for an online sitting (its MCQs), printed for the
     * classroom (MCQs and CQs), or both.
     *
     * @throws ValidationException when the paper can't be used this way or the teacher's plans don't allow it
     */
    public function createTeacherExam(
        User $teacher,
        BoardQuestionPaper $paper,
        ExamDeliveryMode $deliveryMode,
        int $durationMinutes,
        ?float $negativeMarks = null,
    ): Exam {
        $deliveryMode->includesOnline() ? $this->ensureCanBeSatOnline($paper) : $this->ensureApproved($paper);

        $grant = $this->subscriptionLimits->authorizePastPaper($teacher, $paper, $deliveryMode->requiredFeatures());

        return DB::transaction(fn (): Exam => $this->createExam($teacher, $grant, $paper, [
            'exam_type' => ExamType::TeacherExam,
            'delivery_mode' => $deliveryMode,
            'duration_minutes' => max(1, $durationMinutes),
            // Only an online sitting is marked automatically.
            'negative_marks' => $deliveryMode->includesOnline() ? $this->negativeMarks($negativeMarks) : null,
            'max_participants' => $grant->maxParticipants,
            'status' => ExamStatus::Draft,
        ]));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createExam(User $creator, PlanGrant $grant, BoardQuestionPaper $paper, array $attributes): Exam
    {
        $exam = Exam::create([
            'title' => $paper->title(),
            'created_by' => $creator->id,
            'subscription_id' => $grant->subscriptionId,
            'generation_mode' => GenerationMode::PastPaper,
            'board_question_paper_id' => $paper->id,
            'class_subject_id' => $paper->class_subject_id,
            'subject_id' => $paper->classSubject?->subject_id,
            'status' => ExamStatus::Draft,
            ...$attributes,
        ]);

        $exam->setRelation('boardQuestionPaper', $paper);
        $exam->recalculateTotalMarks();

        return $exam->refresh();
    }

    /**
     * A wrong answer costs something only when a positive amount is set.
     */
    private function negativeMarks(?float $negativeMarks): ?float
    {
        return $negativeMarks !== null && $negativeMarks > 0 ? round($negativeMarks, 2) : null;
    }

    /**
     * @throws ValidationException
     */
    private function ensureApproved(BoardQuestionPaper $paper): void
    {
        if ($paper->status !== QuestionStatus::Approved) {
            $this->fail(__('This past paper is not available.'));
        }
    }

    /**
     * Online, only the MCQs are sat (a CQ has no answer to mark), so a
     * paper without any can't be.
     *
     * @throws ValidationException
     */
    private function ensureCanBeSatOnline(BoardQuestionPaper $paper): void
    {
        $this->ensureApproved($paper);

        if (! $paper->mcqQuestions()->exists()) {
            $this->fail(__('This past paper has no MCQ questions to sit online.'));
        }
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['paper' => $message]);
    }
}
