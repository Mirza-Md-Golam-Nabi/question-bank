<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\GenerationMode;
use App\Enums\QuestionType;
use App\Models\BillingSetting;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Builds a self-practice exam (Auto-Generate or Manual Selection — CLAUDE.md
 * rule 9 requires both — or one made of the student's own mistakes) and
 * starts the student's attempt on it in one shot, since a self-practice
 * exam only ever exists for that one student's own attempt.
 *
 * Every mode first asks the student's plans (SubscriptionLimitService)
 * whether this class + subject and these difficulties are allowed and
 * whether an exam is left today/this month, then draws only from the
 * approved+latest pool, then falls through to the same
 * createExamAndAttempt() — so none of these rules is written per mode.
 */
class SelfPracticeExamService
{
    public function __construct(
        private readonly StudentMistakeTracker $mistakes,
        private readonly SubscriptionLimitService $subscriptionLimits,
    ) {}

    /**
     * @param  array<int, int>|null  $chapterIds
     *
     * @throws ValidationException when the student's plans don't allow it
     */
    public function generateAuto(User $student, ClassSubject $classSubject, ?Difficulty $difficulty, int $questionCount, ?array $chapterIds = null): ExamAttempt
    {
        $grant = $this->subscriptionLimits->authorize($student, $classSubject, difficulties: array_filter([$difficulty]));

        // The draw is made over ids only, and just the drawn questions are
        // then loaded. ORDER BY RAND() over the full rows would make the
        // database read and sort every question of the subject — text,
        // options and all — to hand back a few of them.
        $eligibleIds = $this->pool($classSubject)
            ->when($chapterIds, fn (Builder $query) => $query->whereIn('chapter_id', $chapterIds))
            ->when($difficulty, fn (Builder $query) => $query->where('difficulty', $difficulty))
            ->when(! $difficulty && $grant->difficulties !== null, fn (Builder $query) => $query->whereIn('difficulty', $grant->difficulties))
            ->pluck('id');

        $questions = Question::approvedPool()
            ->whereKey($eligibleIds->random(min($questionCount, $eligibleIds->count())))
            ->get()
            ->shuffle();

        return $this->createExamAndAttempt($student, $grant, $classSubject, $questions, GenerationMode::Auto);
    }

    /**
     * The questions among `$questionIds` that can go on a practice exam of
     * this class + subject: the approved pool's MCQs. The ids come from the
     * student's browser (the question picker), so anything else — pending,
     * edited since, another subject's, made up — is silently left out.
     * Whether the student's plans allow their difficulties is checked when
     * the exam is started, which says so by name.
     *
     * @param  array<int, mixed>  $questionIds
     * @return Collection<int, Question>
     */
    public function selectableQuestions(ClassSubject $classSubject, array $questionIds): Collection
    {
        $questionIds = array_slice(array_values(array_unique(array_filter(array_map('intval', $questionIds)))), 0, TeacherExamBuilder::MAX_QUESTIONS);

        if ($questionIds === []) {
            return new Collection;
        }

        return $this->pool($classSubject)->whereIn('questions.id', $questionIds)->get();
    }

    /**
     * @param  array<int, mixed>  $questionIds
     *
     * @throws ValidationException when the student's plans don't allow it, or none of the questions can be used
     */
    public function generateManual(User $student, ClassSubject $classSubject, array $questionIds): ExamAttempt
    {
        $questions = $this->selectableQuestions($classSubject, $questionIds);

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'questions' => __('None of the selected questions are available any more.'),
            ]);
        }

        $grant = $this->subscriptionLimits->authorize(
            $student,
            $classSubject,
            difficulties: $questions->pluck('difficulty')->unique()->values()->all(),
        );

        return $this->createExamAndAttempt($student, $grant, $classSubject, $questions, GenerationMode::Manual);
    }

    /**
     * A practice exam drawn from the questions the student keeps getting
     * wrong (StudentMistakeTracker decides which those are, and which of
     * them may be shown yet) — only those of a difficulty their plan allows.
     *
     * @throws ValidationException when the plans don't allow it, or the student has no mistakes to practise in the subject
     */
    public function generateFromMistakes(User $student, ClassSubject $classSubject, int $questionCount): ExamAttempt
    {
        $grant = $this->subscriptionLimits->authorize($student, $classSubject);

        $questions = $this->mistakes->drawForPractice($student, $classSubject, $questionCount, $grant->difficulties);

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'data.class_subject_id' => __('You have no mistakes to practise in this subject right now.'),
            ]);
        }

        return $this->createExamAndAttempt($student, $grant, $classSubject, $questions, GenerationMode::Mistakes);
    }

    /**
     * The approved+latest MCQs of one class's subject. Only MCQ: a practice
     * exam is always taken online, and CQ is never taken online (CLAUDE.md
     * rule 10).
     *
     * @return Builder<Question>
     */
    private function pool(ClassSubject $classSubject): Builder
    {
        return Question::approvedPool()
            ->where('questions.question_type', QuestionType::Mcq)
            ->ofClassSubject($classSubject->id);
    }

    /**
     * @param  Collection<int, Question>  $questions
     */
    private function createExamAndAttempt(User $student, PlanGrant $grant, ClassSubject $classSubject, Collection $questions, GenerationMode $mode): ExamAttempt
    {
        return DB::transaction(function () use ($student, $grant, $classSubject, $questions, $mode) {
            $exam = Exam::create([
                'title' => __('Self Practice').' — '.now()->format('M j, Y H:i'),
                'created_by' => $student->id,
                'subscription_id' => $grant->subscriptionId,
                'exam_type' => ExamType::SelfPractice,
                'generation_mode' => $mode,
                'subject_id' => $classSubject->subject_id,
                'class_subject_id' => $classSubject->id,
                'duration_minutes' => BillingSetting::current()->practiceMinutesFor($questions->count()),
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
