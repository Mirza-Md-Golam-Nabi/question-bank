<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\PlanConditionType;
use App\Enums\PlanFeature;
use App\Models\AcademicClass;
use App\Models\ClassSubject;
use App\Models\Subject;
use Illuminate\Validation\ValidationException;

/**
 * A plan's conditions, as the Admin added them in the plan form (a list of
 * `{type, data}` blocks), read into one object. The only place that knows
 * what each kind of condition's data looks like.
 *
 * Classes and subjects can either be fixed by the plan ("Class 10, every
 * subject") or left for the buyer to pick ("any one subject"): a pick count
 * means the buyer chooses that many from the listed ones (or from all, when
 * none are listed) at the moment of purchase.
 */
final class PlanConditions
{
    /**
     * @param  array<int, int>  $classIds  Empty = every class.
     * @param  array<int, int>  $subjectIds  Empty = every subject.
     * @param  array<int, Difficulty>|null  $difficulties  Null = every difficulty.
     * @param  array<int, PlanFeature>|null  $features  Null = every feature.
     */
    public function __construct(
        public readonly array $classIds = [],
        public readonly ?int $classPickCount = null,
        public readonly array $subjectIds = [],
        public readonly ?int $subjectPickCount = null,
        public readonly ?array $difficulties = null,
        public readonly ?array $features = null,
        public readonly ?int $dailyExamLimit = null,
        public readonly ?int $monthlyExamLimit = null,
        public readonly ?int $maxParticipants = null,
        public readonly ?int $seats = null,
    ) {}

    /**
     * @param  array<int, array{type?: string, data?: array<string, mixed>}>|null  $blocks
     */
    public static function fromArray(?array $blocks): self
    {
        $values = [];

        foreach ($blocks ?? [] as $block) {
            $data = $block['data'] ?? [];

            match (PlanConditionType::tryFrom((string) ($block['type'] ?? ''))) {
                PlanConditionType::Classes => $values += [
                    'classIds' => self::ids($data['class_ids'] ?? []),
                    'classPickCount' => self::positiveOrNull($data['pick_count'] ?? null),
                ],
                PlanConditionType::Subjects => $values += [
                    'subjectIds' => self::ids($data['subject_ids'] ?? []),
                    'subjectPickCount' => self::positiveOrNull($data['pick_count'] ?? null),
                ],
                PlanConditionType::Difficulties => $values += [
                    'difficulties' => array_values(array_filter(array_map(
                        fn (mixed $value): ?Difficulty => $value instanceof Difficulty ? $value : Difficulty::tryFrom((string) $value),
                        (array) ($data['difficulties'] ?? []),
                    ))),
                ],
                PlanConditionType::Features => $values += [
                    'features' => array_values(array_filter(array_map(
                        fn (mixed $value): ?PlanFeature => $value instanceof PlanFeature ? $value : PlanFeature::tryFrom((string) $value),
                        (array) ($data['features'] ?? []),
                    ))),
                ],
                PlanConditionType::DailyExamLimit => $values += ['dailyExamLimit' => max(0, (int) ($data['limit'] ?? 0))],
                PlanConditionType::MonthlyExamLimit => $values += ['monthlyExamLimit' => max(0, (int) ($data['limit'] ?? 0))],
                PlanConditionType::MaxParticipants => $values += ['maxParticipants' => max(1, (int) ($data['limit'] ?? 1))],
                PlanConditionType::Seats => $values += ['seats' => max(1, (int) ($data['count'] ?? 1))],
                default => null,
            };
        }

        return new self(...$values);
    }

    public function needsClassPick(): bool
    {
        return $this->classPickCount !== null;
    }

    public function needsSubjectPick(): bool
    {
        return $this->subjectPickCount !== null;
    }

    /**
     * Checks what a buyer picked against the plan and returns it in the
     * shape a subscription stores. Only what the plan asks to be picked is
     * kept; the rest of a plan's scope is fixed and needs no picking.
     *
     * @param  array{class_ids?: mixed, subject_ids?: mixed}  $selections
     * @return array{class_ids: array<int, int>, subject_ids: array<int, int>}
     *
     * @throws ValidationException
     */
    public function resolveSelections(array $selections): array
    {
        $classIds = $this->needsClassPick() ? self::ids($selections['class_ids'] ?? []) : [];
        $subjectIds = $this->needsSubjectPick() ? self::ids($selections['subject_ids'] ?? []) : [];

        if ($this->needsClassPick()) {
            if (count($classIds) !== $this->classPickCount) {
                throw ValidationException::withMessages(['class_ids' => trans_choice('Choose :count class.|Choose :count classes.', $this->classPickCount)]);
            }

            if ($this->classIds !== [] && array_diff($classIds, $this->classIds) !== []) {
                throw ValidationException::withMessages(['class_ids' => __('This class is not part of the plan.')]);
            }
        }

        if ($this->needsSubjectPick()) {
            if (count($subjectIds) !== $this->subjectPickCount) {
                throw ValidationException::withMessages(['subject_ids' => trans_choice('Choose :count subject.|Choose :count subjects.', $this->subjectPickCount)]);
            }

            if ($this->subjectIds !== [] && array_diff($subjectIds, $this->subjectIds) !== []) {
                throw ValidationException::withMessages(['subject_ids' => __('This subject is not part of the plan.')]);
            }

            $classScope = $this->classScope($classIds);

            if (array_diff($subjectIds, $this->pickableSubjectIds($classScope)) !== []) {
                throw ValidationException::withMessages(['subject_ids' => count($classScope) > 1
                    ? __('This subject is not taught in every class of the plan.')
                    : __('This subject is not taught in the chosen class.')]);
            }
        }

        return ['class_ids' => $classIds, 'subject_ids' => $subjectIds];
    }

    /**
     * The classes a subject pick is for: the ones the buyer picked when the
     * plan asks for that, otherwise the plan's own (empty = every class).
     *
     * @param  array<int, mixed>  $pickedClassIds
     * @return array<int, int>
     */
    public function classScope(array $pickedClassIds): array
    {
        return $this->needsClassPick() ? self::ids($pickedClassIds) : $this->classIds;
    }

    /**
     * The subjects a buyer may pick for these classes: the ones the plan
     * lists (or any), taught in every one of the classes. A plan for
     * several classes sells a subject for all of them, so one that only some
     * of them teach — a group subject of Class 9–10 in a Class 6–10 plan —
     * is not on offer. No classes = taught in any class.
     *
     * @param  array<int, int>  $classIds
     * @return array<int, int>
     */
    public function pickableSubjectIds(array $classIds): array
    {
        return ClassSubject::query()
            ->when($classIds !== [], fn ($query) => $query->whereIn('academic_class_id', $classIds))
            ->when($this->subjectIds !== [], fn ($query) => $query->whereIn('subject_id', $this->subjectIds))
            ->groupBy('subject_id')
            ->when($classIds !== [], fn ($query) => $query->havingRaw('COUNT(DISTINCT academic_class_id) = ?', [count($classIds)]))
            ->pluck('subject_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * One line per condition, for a plan card, the plans table or a
     * "your plans" summary.
     *
     * @return array<int, string>
     */
    public function descriptions(): array
    {
        [$classNames, $subjectNames] = self::names();

        $names = fn (array $ids, array $all): string => collect($ids)->map(fn (int $id) => $all[$id] ?? '?')->join(', ');

        return array_values(array_filter([
            match (true) {
                $this->needsClassPick() && $this->classIds !== [] => trans_choice('Any :count class of: :classes|Any :count classes of: :classes', $this->classPickCount, ['classes' => $names($this->classIds, $classNames)]),
                $this->needsClassPick() => trans_choice('Any :count class of your choice|Any :count classes of your choice', $this->classPickCount),
                $this->classIds !== [] => __('Classes: :classes', ['classes' => $names($this->classIds, $classNames)]),
                default => null,
            },
            match (true) {
                $this->needsSubjectPick() && $this->subjectIds !== [] => trans_choice('Any :count subject of: :subjects|Any :count subjects of: :subjects', $this->subjectPickCount, ['subjects' => $names($this->subjectIds, $subjectNames)]),
                $this->needsSubjectPick() => trans_choice('Any :count subject of your choice|Any :count subjects of your choice', $this->subjectPickCount),
                $this->subjectIds !== [] => __('Subjects: :subjects', ['subjects' => $names($this->subjectIds, $subjectNames)]),
                default => __('All subjects'),
            },
            $this->difficulties === null ? null : __('Difficulty: :levels', [
                'levels' => collect($this->difficulties)->map(fn (Difficulty $difficulty) => $difficulty->getLabel())->join(', '),
            ]),
            $this->features === null ? null : __('Includes: :features', [
                'features' => collect($this->features)->map(fn (PlanFeature $feature) => $feature->getLabel())->join(', ') ?: __('nothing extra'),
            ]),
            $this->dailyExamLimit === null ? null : trans_choice(':count exam a day|:count exams a day', $this->dailyExamLimit),
            $this->monthlyExamLimit === null ? null : trans_choice(':count exam a month|:count exams a month', $this->monthlyExamLimit),
            $this->maxParticipants === null ? null : trans_choice('Up to :count participant per online exam|Up to :count participants per online exam', $this->maxParticipants),
            $this->seats === null || $this->seats < 2 ? null : __('Shared by up to :count people, each with their own Google account', ['count' => $this->seats]),
        ]));
    }

    /**
     * Every class's and subject's name, keyed by id. Both are short
     * reference lists, read once per request however many plans are
     * described (a static call, so the cache is shared by all of them).
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private static function names(): array
    {
        return once(fn (): array => [
            AcademicClass::options(),
            Subject::query()->get(['id', 'name', 'name_bn'])->mapWithKeys(fn (Subject $subject) => [$subject->id => $subject->display_name])->all(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private static function ids(mixed $ids): array
    {
        return collect((array) $ids)
            ->filter(fn (mixed $id) => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private static function positiveOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
