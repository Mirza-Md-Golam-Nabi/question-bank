<?php

namespace Database\Seeders;

use App\Enums\PlanConditionType;
use App\Enums\PlanDuration;
use App\Enums\SubscriptionTargetRole;
use App\Models\AcademicClass;
use App\Models\SubscriptionPlan;
use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Seeder;

/**
 * Local-only starting price list (the one agreed on 2026-10-16), so a
 * fresh local database has plans to try things with. On a live site the
 * Admin builds the price list in the Subscription Plans page instead.
 * Group plans (coaching, several students) come with seats in a later step.
 */
class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $classIds = fn (array $names): array => AcademicClass::query()->whereIn('name', $names)->pluck('id')->all();
        $condition = fn (PlanConditionType $type, array $data): array => SubscriptionPlanFactory::condition($type, $data);
        $pickClass = $condition(PlanConditionType::Classes, ['class_ids' => [], 'pick_count' => 1]);
        $pickSubject = $condition(PlanConditionType::Subjects, ['subject_ids' => [], 'pick_count' => 1]);

        $teacherPlans = [
            ['2-day free trial', 0, PlanDuration::Days, 2, [$pickClass, $pickSubject], ['is_trial' => true]],
            ['1 subject, 1 class', 149, PlanDuration::Years, 1, [$pickClass, $pickSubject]],
            ['1 subject, Class 6–10', 449, PlanDuration::Years, 1, [
                $condition(PlanConditionType::Classes, ['class_ids' => $classIds(['Class 6', 'Class 7', 'Class 8', 'Class 9 / Class 10'])]),
                $pickSubject,
            ]],
            ['Class 6–8, all subjects', 599, PlanDuration::Years, 1, [
                $condition(PlanConditionType::Classes, ['class_ids' => $classIds(['Class 6', 'Class 7', 'Class 8'])]),
            ]],
            ['Class 9–10, all subjects', 899, PlanDuration::Years, 1, [
                $condition(PlanConditionType::Classes, ['class_ids' => $classIds(['Class 9 / Class 10'])]),
            ]],
            ['Class 11–12, all subjects', 799, PlanDuration::Years, 1, [
                $condition(PlanConditionType::Classes, ['class_ids' => $classIds(['Class 11 / Class 12'])]),
            ]],
        ];

        $studentPlans = [
            ['Free', 0, PlanDuration::Unlimited, null, [
                $condition(PlanConditionType::Difficulties, ['difficulties' => ['easy']]),
                $condition(PlanConditionType::DailyExamLimit, ['limit' => 1]),
            ], ['is_default_free' => true]],
            ['1 subject', 29, PlanDuration::Months, 1, [$pickClass, $pickSubject]],
            ['All subjects', 79, PlanDuration::Months, 1, [$pickClass]],
            ['All subjects, 3 months', 199, PlanDuration::Months, 3, [$pickClass]],
            ['All subjects, 1 year', 499, PlanDuration::Years, 1, [$pickClass]],
        ];

        foreach ([SubscriptionTargetRole::Teacher->value => $teacherPlans, SubscriptionTargetRole::Student->value => $studentPlans] as $role => $plans) {
            foreach ($plans as $plan) {
                [$name, $price, $unit, $length, $conditions, $extra] = $plan + [5 => []];

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
}
