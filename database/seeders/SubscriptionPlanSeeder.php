<?php

namespace Database\Seeders;

use App\Enums\PlanConditionType;
use App\Enums\PlanDuration;
use App\Enums\SubscriptionTargetRole;
use App\Models\AcademicClass;
use App\Models\SubscriptionPlan;
use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * The starting price list (the one agreed on 2026-10-16), locally and on
 * the live site — there the migration `seed_starting_subscription_plans`
 * runs it once on deploy. After that the Admin owns the price list:
 *
 * - a plan is created once by its name + role, so seeding again never
 *   overwrites or doubles a plan the Admin has changed;
 * - a plan whose named classes are not all on the site is skipped (an
 *   empty class list would mean "every class" and give away too much);
 * - the free plan and the trial are skipped when the role already has one.
 */
class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $condition = fn (PlanConditionType $type, array $data): array => SubscriptionPlanFactory::condition($type, $data);
        $classes = fn (array $names): array => ['class_names' => $names];
        $pickClass = $condition(PlanConditionType::Classes, ['class_ids' => [], 'pick_count' => 1]);
        $pickSubject = $condition(PlanConditionType::Subjects, ['subject_ids' => [], 'pick_count' => 1]);

        $teacherPlans = [
            ['2-day free trial', 0, PlanDuration::Days, 2, [$pickClass, $pickSubject], ['is_trial' => true]],
            ['1 subject, 1 class', 149, PlanDuration::Years, 1, [$pickClass, $pickSubject]],
            ['1 subject, Class 6–10', 449, PlanDuration::Years, 1, [$classes(['Class 6', 'Class 7', 'Class 8', 'Class 9 / Class 10']), $pickSubject]],
            ['Class 6–8, all subjects', 599, PlanDuration::Years, 1, [$classes(['Class 6', 'Class 7', 'Class 8'])]],
            ['Class 9–10, all subjects', 899, PlanDuration::Years, 1, [$classes(['Class 9 / Class 10'])]],
            ['Class 11–12, all subjects', 799, PlanDuration::Years, 1, [$classes(['Class 11 / Class 12'])]],
        ];

        $studentPlans = [
            ['Free', 0, PlanDuration::Unlimited, null, [
                $condition(PlanConditionType::Difficulties, ['difficulties' => ['easy']]),
                $condition(PlanConditionType::DailyExamLimit, ['limit' => 1]),
                $condition(PlanConditionType::PastPapers, ['min_age_years' => 5, 'limit' => 1, 'per_days' => 30]),
            ], ['is_default_free' => true]],
            ['1 subject', 29, PlanDuration::Months, 1, [$pickClass, $pickSubject]],
            ['All subjects', 79, PlanDuration::Months, 1, [$pickClass]],
            ['All subjects, 3 months', 199, PlanDuration::Months, 3, [$pickClass]],
            ['All subjects, 1 year', 499, PlanDuration::Years, 1, [$pickClass]],
        ];

        foreach ([SubscriptionTargetRole::Teacher->value => $teacherPlans, SubscriptionTargetRole::Student->value => $studentPlans] as $role => $plans) {
            foreach ($plans as $plan) {
                [$name, $price, $unit, $length, $conditions, $extra] = $plan + [5 => []];

                if ($this->roleAlreadyHas($role, $extra)) {
                    continue;
                }

                $conditions = $this->withClassIds($conditions);

                if ($conditions === null) {
                    $this->warn("Subscription plan \"{$name}\" ({$role}) skipped: its classes are not all on this site.");

                    continue;
                }

                SubscriptionPlan::firstOrCreate(['name' => $name, 'target_role' => $role], [
                    'price' => $price,
                    'duration_unit' => $unit,
                    'duration_length' => $length,
                    'conditions' => $conditions,
                    ...$extra,
                ]);
            }
        }
    }

    /**
     * Whether the role already has the free plan / a trial this plan would
     * be another of (one the Admin made, or this seeder earlier).
     *
     * @param  array<string, bool>  $extra
     */
    private function roleAlreadyHas(string $role, array $extra): bool
    {
        $flag = collect(['is_default_free', 'is_trial'])->first(fn (string $flag): bool => $extra[$flag] ?? false);

        return $flag !== null && SubscriptionPlan::query()->where('target_role', $role)->where($flag, true)->exists();
    }

    /**
     * Turns each fixed class list (written by name) into the class ids of
     * this site, or null when one of the names is missing.
     *
     * @param  array<int, array<string, mixed>>  $conditions
     * @return array<int, array{type: string, data: array<string, mixed>}>|null
     */
    private function withClassIds(array $conditions): ?array
    {
        foreach ($conditions as $index => $block) {
            if (! isset($block['class_names'])) {
                continue;
            }

            $classIds = AcademicClass::query()->whereIn('name', $block['class_names'])->pluck('id')->all();

            if (count($classIds) !== count($block['class_names'])) {
                return null;
            }

            $conditions[$index] = SubscriptionPlanFactory::condition(PlanConditionType::Classes, ['class_ids' => $classIds]);
        }

        return $conditions;
    }

    private function warn(string $message): void
    {
        Log::warning($message);
        $this->command?->warn($message);
    }
}
