<?php

use App\Enums\SubscriptionTargetRole;
use App\Models\AcademicClass;
use App\Models\SubscriptionPlan;
use Database\Seeders\SubscriptionPlanSeeder;

function createSeederClasses(array $names): void
{
    foreach ($names as $name) {
        AcademicClass::factory()->create(['name' => $name]);
    }
}

it('seeds the starting price list outside the local environment too', function () {
    createSeederClasses(['Class 6', 'Class 7', 'Class 8', 'Class 9 / Class 10', 'Class 11 / Class 12']);

    $this->seed(SubscriptionPlanSeeder::class);

    expect(SubscriptionPlan::where('target_role', SubscriptionTargetRole::Teacher)->count())->toBe(6)
        ->and(SubscriptionPlan::where('target_role', SubscriptionTargetRole::Student)->count())->toBe(5)
        ->and(SubscriptionPlan::where('name', 'Class 6–8, all subjects')->first()->conditions[0]['data']['class_ids'])
        ->toEqualCanonicalizing(AcademicClass::whereIn('name', ['Class 6', 'Class 7', 'Class 8'])->pluck('id')->all());
});

it('skips a plan whose classes are not all on the site instead of opening every class', function () {
    createSeederClasses(['Class 6', 'Class 7']);

    $this->seed(SubscriptionPlanSeeder::class);

    expect(SubscriptionPlan::where('name', 'Class 6–8, all subjects')->exists())->toBeFalse()
        ->and(SubscriptionPlan::where('name', 'Class 9–10, all subjects')->exists())->toBeFalse()
        ->and(SubscriptionPlan::where('name', '1 subject, 1 class')->exists())->toBeTrue();
});

it('never overwrites, doubles or adds a second free plan or trial', function () {
    $adminsFree = SubscriptionPlan::factory()->create([
        'name' => 'Admin free', 'target_role' => SubscriptionTargetRole::Student, 'price' => 0, 'is_default_free' => true,
    ]);
    $adminsEdit = SubscriptionPlan::factory()->create([
        'name' => '1 subject', 'target_role' => SubscriptionTargetRole::Student, 'price' => 35,
    ]);

    $this->seed(SubscriptionPlanSeeder::class);
    $this->seed(SubscriptionPlanSeeder::class);

    expect(SubscriptionPlan::where('target_role', SubscriptionTargetRole::Student)->where('is_default_free', true)->pluck('id')->all())->toBe([$adminsFree->id])
        ->and($adminsEdit->fresh()->price)->toEqual(35)
        ->and(SubscriptionPlan::where('name', '1 subject')->count())->toBe(1)
        ->and(SubscriptionPlan::where('is_trial', true)->count())->toBe(1);
});
