<?php

use App\Models\BillingSetting;
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

it('falls back to the role\'s default free plan for a user with no subscription', function () {
    SubscriptionPlan::factory()->free()->create(['monthly_exam_limit' => 3]);
    SubscriptionPlan::factory()->free()->forStudents()->create(['monthly_exam_limit' => 7]);
    $teacher = User::factory()->teacher()->create();

    expect($this->service->monthlyExamLimitFor($teacher))->toBe(3);
});

it('falls back to the default free plan once a paid subscription has run out', function () {
    SubscriptionPlan::factory()->free()->create(['monthly_exam_limit' => 3]);
    $teacher = User::factory()->teacher()->create();
    Subscription::factory()->expired()->create([
        'user_id' => $teacher->id,
        'plan_id' => SubscriptionPlan::factory()->pro(),
    ]);

    expect($this->service->monthlyExamLimitFor($teacher))->toBe(3);
});

it('adds the admin\'s bonus exams for a user who has given a phone number', function () {
    BillingSetting::create(['phone_bonus_exams' => 2]);
    SubscriptionPlan::factory()->free()->create(['monthly_exam_limit' => 3]);
    $withPhone = User::factory()->teacher()->create(['phone' => '01712345678']);
    $withoutPhone = User::factory()->teacher()->create();

    expect($this->service->monthlyExamLimitFor($withPhone))->toBe(5)
        ->and($this->service->monthlyExamLimitFor($withoutPhone))->toBe(3);
});

it('keeps an unlimited plan unlimited for a user with a phone number', function () {
    BillingSetting::create(['phone_bonus_exams' => 2]);
    $teacher = User::factory()->teacher()->create(['phone' => '01712345678']);
    Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => SubscriptionPlan::factory()->pro()]);

    expect($this->service->monthlyExamLimitFor($teacher))->toBeNull();
});
