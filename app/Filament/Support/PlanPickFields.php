<?php

namespace App\Filament\Support;

use App\Models\AcademicClass;
use App\Models\Subject;
use App\Services\PlanConditions;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * The class(es) and subject(s) a plan asks to be picked when it is bought —
 * on the buyer's "My Subscription" page and in the Super Admin's "Give a
 * subscription" window. Only from what the plan lists, and only subjects
 * taught in every chosen (or the plan's) class; the service checks the
 * same again (`PlanConditions::resolveSelections()`).
 */
final class PlanPickFields
{
    /**
     * The subject options, keyed by the classes they are for — kept for
     * the request, as the field's help text asks again.
     *
     * @var array<string, array<int, string>>
     */
    private array $subjectOptions = [];

    /**
     * @param  Closure(Get): PlanConditions  $conditions  The plan's conditions — fixed, or from the form's chosen plan.
     * @return array<int, Select>
     */
    public function components(Closure $conditions, ?string $classHelperText = null): array
    {
        $pickOne = fn (?int $count): bool => $count === 1;

        return [
            Select::make('class_ids')
                ->label(fn (Get $get): string => trans_choice('Class|Classes', $conditions($get)->classPickCount ?? 1))
                ->options(fn (Get $get): array => AcademicClass::options($conditions($get)->classIds ?: null))
                ->multiple(fn (Get $get): bool => ! $pickOne($conditions($get)->classPickCount))
                ->maxItems(fn (Get $get): ?int => $pickOne($conditions($get)->classPickCount) ? null : $conditions($get)->classPickCount)
                ->helperText($classHelperText)
                ->visible(fn (Get $get): bool => $conditions($get)->needsClassPick())
                ->required(fn (Get $get): bool => $conditions($get)->needsClassPick())
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('subject_ids', null)),

            Select::make('subject_ids')
                ->label(fn (Get $get): string => trans_choice('Subject|Subjects', $conditions($get)->subjectPickCount ?? 1))
                ->options(fn (Get $get): array => $this->subjectOptions($conditions($get), (array) $get('class_ids')))
                ->helperText(function (Get $get) use ($conditions): ?string {
                    $planConditions = $conditions($get);
                    $classScope = $planConditions->classScope((array) $get('class_ids'));

                    return match (true) {
                        $planConditions->needsClassPick() && $classScope === [] => __('Choose the class first.'),
                        $this->subjectOptions($planConditions, (array) $get('class_ids')) === [] => __('No subject is taught in every class of this plan.'),
                        count($classScope) > 1 => __('Only the subjects taught in every class of the plan.'),
                        default => null,
                    };
                })
                ->multiple(fn (Get $get): bool => ! $pickOne($conditions($get)->subjectPickCount))
                ->maxItems(fn (Get $get): ?int => $pickOne($conditions($get)->subjectPickCount) ? null : $conditions($get)->subjectPickCount)
                ->searchable()
                ->visible(fn (Get $get): bool => $conditions($get)->needsSubjectPick())
                ->required(fn (Get $get): bool => $conditions($get)->needsSubjectPick()),
        ];
    }

    /**
     * What the plan lets be picked for the class(es) chosen so far, as
     * select options — nothing before a class is chosen, when the plan asks
     * for one.
     *
     * @param  array<int, mixed>  $pickedClassIds
     * @return array<int, string>
     */
    private function subjectOptions(PlanConditions $conditions, array $pickedClassIds): array
    {
        $classIds = $conditions->classScope($pickedClassIds);

        if ($conditions->needsClassPick() && $classIds === []) {
            return [];
        }

        // The plan's own subject list is in the key: in the Admin's window
        // the plan can change between two asks.
        return $this->subjectOptions[implode(',', $classIds).'|'.implode(',', $conditions->subjectIds)] ??= Subject::query()
            ->whereKey($conditions->pickableSubjectIds($classIds))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Subject $subject): array => [$subject->id => $subject->display_name])
            ->all();
    }
}
