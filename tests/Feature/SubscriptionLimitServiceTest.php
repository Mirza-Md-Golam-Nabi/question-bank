<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->service = new SubscriptionLimitService;
});

it('reports no active subscription for a user who never subscribed', function () {
    $teacher = User::factory()->teacher()->create();

    expect($this->service->hasActiveSubscription($teacher))->toBeFalse();
    expect($this->service->monthlyExamLimitFor($teacher))->toBeNull();
});

it('finds the active subscription and its plan limit', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->create(['monthly_exam_limit' => 5]);
    Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);

    expect($this->service->hasActiveSubscription($teacher))->toBeTrue();
    expect($this->service->monthlyExamLimitFor($teacher))->toBe(5);
});

it('ignores an expired subscription', function () {
    $teacher = User::factory()->teacher()->create();
    Subscription::factory()->expired()->create(['user_id' => $teacher->id]);

    expect($this->service->hasActiveSubscription($teacher))->toBeFalse();
});

it('treats a null monthly_exam_limit as unlimited', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->create();
    Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);

    expect($this->service->monthlyExamLimitFor($teacher))->toBeNull();
});
