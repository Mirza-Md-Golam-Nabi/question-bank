<?php

namespace App\Services;

use App\Enums\ExamDeliveryMode;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\OmrIdentity;
use App\Enums\PlanFeature;
use App\Models\AnswerKeyQuestion;
use App\Models\Exam;
use App\Models\User;
use App\Support\OmrSheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * OMR checks (CLAUDE.md rule 10.12): checking students' OMR sheets of a
 * paper made outside the system against an answer key the Teacher or
 * School types in — the one place one is made or changed. It is an exam
 * of its own type (ExamType::OmrCheck) with no questions, only the key
 * (Exam::answerKey()); its sheet, scanning, marking and results are the
 * same as any printed exam's.
 *
 * It needs the plan's "OMR check" feature and is never counted against the
 * plan's exam limits. Its key can be changed only until a sheet is saved —
 * papers already marked must not be marked differently afterwards.
 */
class OmrCheckService
{
    public const MIN_OPTIONS = 2;

    public function __construct(private readonly SubscriptionLimitService $subscriptionLimits) {}

    /**
     * Whether this user may make OMR checks at all — one who sets exams, on
     * a plan with the feature.
     */
    public function canCreate(User $user): bool
    {
        return $user->role->setsExams() && $this->subscriptionLimits->allows($user, null, [PlanFeature::OmrCheck]);
    }

    /**
     * Whether the key can still be changed: no sheet has been saved on it.
     */
    public function canChangeKey(Exam $exam): bool
    {
        return $exam->isOmrCheck() && ! ($exam->attempts_exists ?? $exam->attempts()->exists());
    }

    /**
     * Makes a new OMR check, or changes `$exam`'s.
     *
     * @param  array<string, mixed>  $data  title, questions, options, marks, negative_marks (null = none), omr_identity and answers (a list: question 1's right option first, 0 = the first option)
     *
     * @throws ValidationException naming what is wrong, or that the plan doesn't include it
     */
    public function save(User $user, array $data, ?Exam $exam = null): Exam
    {
        if (! $this->canCreate($user)) {
            throw ValidationException::withMessages([
                'title' => __('Your plan does not include :features.', ['features' => PlanFeature::OmrCheck->getLabel()]),
            ]);
        }

        if ($exam && ($exam->created_by !== $user->id || ! $this->canChangeKey($exam))) {
            throw ValidationException::withMessages([
                'answers' => __('Sheets have already been saved on this OMR check, so its answer key can no longer be changed.'),
            ]);
        }

        $valid = $this->validate($data);

        return DB::transaction(function () use ($user, $valid, $exam): Exam {
            $exam ??= new Exam([
                'created_by' => $user->id,
                'exam_type' => ExamType::OmrCheck,
                'delivery_mode' => ExamDeliveryMode::Offline,
                'status' => ExamStatus::Draft,
                'duration_minutes' => 0,
            ]);

            $exam->fill([
                'title' => $valid['title'],
                'negative_marks' => Exam::storedNegativeMarks($valid['negative_marks']),
                'omr_identity' => $valid['omr_identity'],
                'answer_key' => [
                    'options' => $valid['options'],
                    'marks' => round($valid['marks'], 2),
                    'answers' => $valid['answers'],
                ],
            ])->save();

            $exam->recalculateTotalMarks();

            return $exam;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{title: string, options: int, marks: float, negative_marks: float|null, omr_identity: OmrIdentity, answers: list<int>}
     *
     * @throws ValidationException
     */
    private function validate(array $data): array
    {
        $options = (int) ($data['options'] ?? 0);

        $valid = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'questions' => ['required', 'integer', 'between:1,'.OmrSheet::MAX_QUESTIONS],
            'options' => ['required', 'integer', 'between:'.self::MIN_OPTIONS.','.count(AnswerKeyQuestion::OPTION_VALUES)],
            'marks' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'negative_marks' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'omr_identity' => ['required', Rule::enum(OmrIdentity::class)],
            'answers' => ['required', 'array', 'size:'.(int) ($data['questions'] ?? 0)],
            'answers.*' => ['required', 'integer', 'between:0,'.max(0, $options - 1)],
        ], [
            'answers.size' => __('Give the right answer of every question.'),
            'answers.*' => __('Give the right answer of every question.'),
        ], [
            'title' => __('Title'),
            'questions' => __('Number of questions'),
            'options' => __('Options per question'),
            'marks' => __('Marks per right answer'),
            'negative_marks' => __('Marks taken off per wrong answer'),
        ])->validate();

        return [
            'title' => trim($valid['title']),
            'options' => (int) $valid['options'],
            'marks' => (float) $valid['marks'],
            'negative_marks' => isset($valid['negative_marks']) ? (float) $valid['negative_marks'] : null,
            'omr_identity' => OmrIdentity::from($valid['omr_identity'] instanceof OmrIdentity ? $valid['omr_identity']->value : $valid['omr_identity']),
            'answers' => array_map('intval', array_values($valid['answers'])),
        ];
    }
}
