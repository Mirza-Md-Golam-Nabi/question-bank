<?php

namespace App\Services;

use App\Enums\ExamDeliveryMode;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\GenerationMode;
use App\Enums\QuestionType;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a Teacher's question selection into a saved exam (CLAUDE.md rule
 * 10). The selection itself lives in the teacher's browser, so nothing
 * about it is trusted: every id is re-checked here against what this
 * teacher may use and the chosen class + subject, and the monthly limit is counted at
 * this one moment. The only place this validation and exam creation live.
 */
class TeacherExamBuilder
{
    /**
     * A technical guard against an oversized request, not a business rule
     * — the teacher sets their own target, and real papers reach ~120.
     */
    public const MAX_QUESTIONS = 500;

    public function __construct(private readonly SubscriptionLimitService $subscriptionLimits) {}

    /**
     * The questions among `$questionIds` that the teacher may put on an
     * exam (Question::usableInExamBy() — the approved pool and their own
     * pending questions) and that belong to the given class + subject.
     * Anything else (edited since, rejected, someone else's pending
     * question, from another subject, or simply made up) is silently left
     * out, which is how the caller finds out a selection has gone stale.
     *
     * @param  array<int, mixed>  $questionIds
     * @return Collection<int, Question>
     */
    public function selectableQuestions(User $teacher, ClassSubject $classSubject, array $questionIds): Collection
    {
        $questionIds = $this->normalizeIds($questionIds);

        if ($questionIds === []) {
            return new Collection;
        }

        return Question::query()
            ->usableInExamBy($teacher)
            ->whereIn('questions.id', $questionIds)
            ->whereHas('chapter', fn (Builder $query) => $query->where('class_subject_id', $classSubject->id))
            ->with(['chapter:id,name,order_index', 'topic:id,name', 'cqParts'])
            ->get()
            // Paper order: chapter by chapter, MCQ before CQ within each.
            ->sortBy([
                fn (Question $a, Question $b) => $a->chapter->order_index <=> $b->chapter->order_index,
                fn (Question $a, Question $b) => $a->chapter_id <=> $b->chapter_id,
                fn (Question $a, Question $b) => ($a->question_type === QuestionType::Cq) <=> ($b->question_type === QuestionType::Cq),
                fn (Question $a, Question $b) => $a->id <=> $b->id,
            ])
            ->values();
    }

    /**
     * @param  array<int, mixed>  $questionIds
     *
     * @throws ValidationException
     */
    public function build(
        User $teacher,
        ClassSubject $classSubject,
        ExamDeliveryMode $deliveryMode,
        array $questionIds,
        string $title,
        int $durationMinutes,
        ?CarbonInterface $endsAt = null,
    ): Exam {
        $questions = $this->validatedQuestions($teacher, $classSubject, $deliveryMode, $questionIds);

        if ($this->subscriptionLimits->hasReachedMonthlyLimit($teacher, ExamType::TeacherExam)) {
            $this->fail(__('You have reached the monthly exam limit. Upgrade your subscription to create more exams.'));
        }

        return DB::transaction(function () use ($teacher, $classSubject, $deliveryMode, $questions, $title, $durationMinutes, $endsAt) {
            $exam = Exam::create([
                'title' => $title,
                'created_by' => $teacher->id,
                'exam_type' => ExamType::TeacherExam,
                'generation_mode' => GenerationMode::Manual,
                'delivery_mode' => $deliveryMode,
                'subject_id' => $classSubject->subject_id,
                'class_subject_id' => $classSubject->id,
                'duration_minutes' => $durationMinutes,
                // Only an exam taken online has a link to close.
                'link_expires_at' => $deliveryMode->includesOnline() ? $endsAt : null,
                'status' => ExamStatus::Draft,
            ]);

            return $this->putQuestionsOn($exam, $questions);
        });
    }

    /**
     * Saves changes to an exam the teacher already has — its questions,
     * title, duration, or how it is taken — with the same checks as
     * building one. It doesn't count against the monthly limit again: the
     * exam was counted when it was first saved.
     *
     * Refused once any student has started the exam: changing the paper
     * after that would make their marks and positions mean something
     * different from what they sat.
     *
     * @param  array<int, mixed>  $questionIds
     *
     * @throws ValidationException
     */
    public function update(
        Exam $exam,
        ClassSubject $classSubject,
        ExamDeliveryMode $deliveryMode,
        array $questionIds,
        string $title,
        int $durationMinutes,
    ): Exam {
        if (! $this->canBeEdited($exam)) {
            $this->fail(__('Students have already taken this exam, so its questions can no longer be changed.'));
        }

        $questions = $this->validatedQuestions($exam->creator, $classSubject, $deliveryMode, $questionIds);

        return DB::transaction(function () use ($exam, $classSubject, $deliveryMode, $questions, $title, $durationMinutes) {
            $exam->update([
                'title' => $title,
                'delivery_mode' => $deliveryMode,
                'subject_id' => $classSubject->subject_id,
                'class_subject_id' => $classSubject->id,
                'duration_minutes' => $durationMinutes,
                // A print-only exam has no link to close; otherwise the end
                // time stays as set from the exams list.
                'link_expires_at' => $deliveryMode->includesOnline() ? $exam->link_expires_at : null,
            ]);

            $exam->questions()->detach();

            return $this->putQuestionsOn($exam, $questions);
        });
    }

    /**
     * An exam's paper can be changed until the first student starts it.
     */
    public function canBeEdited(Exam $exam): bool
    {
        return ! $exam->attempts()->exists();
    }

    /**
     * The checks every saved selection has to pass, whether it becomes a
     * new exam or replaces an existing one's questions.
     *
     * @param  array<int, mixed>  $questionIds
     * @return Collection<int, Question>
     *
     * @throws ValidationException
     */
    private function validatedQuestions(User $teacher, ClassSubject $classSubject, ExamDeliveryMode $deliveryMode, array $questionIds): Collection
    {
        $questionIds = $this->normalizeIds($questionIds);

        if ($questionIds === []) {
            $this->fail(__('Select at least one question first.'));
        }

        if (count($questionIds) > self::MAX_QUESTIONS) {
            $this->fail(__('An exam can have at most :max questions.', ['max' => self::MAX_QUESTIONS]));
        }

        $questions = $this->selectableQuestions($teacher, $classSubject, $questionIds);

        if ($questions->count() !== count($questionIds)) {
            $this->fail(__('Some selected questions are no longer available. They have been removed from your selection — please review it again.'));
        }

        if (! $deliveryMode->allowsCq() && $questions->contains(fn (Question $question) => $question->question_type === QuestionType::Cq)) {
            $this->fail(__('An online exam can only contain MCQ questions.'));
        }

        return $questions;
    }

    /**
     * Attaches the questions in paper order and brings total_marks in line.
     *
     * @param  Collection<int, Question>  $questions
     */
    private function putQuestionsOn(Exam $exam, Collection $questions): Exam
    {
        $exam->questions()->attach(
            $questions->mapWithKeys(fn (Question $question, int $index) => [
                $question->id => ['order_index' => $index + 1, 'marks_override' => null],
            ])->all(),
        );

        $exam->unsetRelation('questions')->unsetRelation('onlineQuestions');
        $exam->recalculateTotalMarks();

        return $exam->refresh();
    }

    /**
     * @param  array<int, mixed>  $questionIds
     * @return array<int, int>
     */
    private function normalizeIds(array $questionIds): array
    {
        return collect($questionIds)
            ->filter(fn (mixed $id) => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id) => (int) $id)
            ->unique()
            // One past the cap is enough for build() to notice and refuse.
            ->take(self::MAX_QUESTIONS + 1)
            ->values()
            ->all();
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['questions' => $message]);
    }
}
