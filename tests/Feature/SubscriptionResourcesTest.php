<?php

use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Resources\SubscriptionPlans\Pages\ManageSubscriptionPlans;
use App\Filament\Resources\Subscriptions\Pages\ManageSubscriptions;
use App\Filament\Teacher\Pages\MySubscription as TeacherMySubscription;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

it('creates a subscription plan from the admin panel', function () {
    $this->actingAs($this->admin);

    livewire(ManageSubscriptionPlans::class)
        ->callAction('create', data: [
            'name' => 'Teacher Free',
            'target_role' => 'teacher',
            'billing_cycle' => 'free',
            'price' => 0,
            'monthly_exam_limit' => 3,
            'is_default_free' => true,
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('subscription_plans', ['name' => 'Teacher Free', 'monthly_exam_limit' => 3]);
});

it('creates a subscription for a user from the admin panel', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->create();

    $this->actingAs($this->admin);

    livewire(ManageSubscriptions::class)
        ->callAction('create', data: [
            'user_id' => $teacher->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('subscriptions', ['user_id' => $teacher->id, 'plan_id' => $plan->id]);
});

it('records a manual payment from the admin panel', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($this->admin);

    livewire(ManagePayments::class)
        ->callAction('create', data: [
            'user_id' => $teacher->id,
            'amount' => 500,
            'gateway' => 'manual',
            'status' => 'success',
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('payments', ['user_id' => $teacher->id, 'amount' => 500]);
});

it('shows a teacher their active subscription on the my-subscription page', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->create(['name' => 'Teacher Pro', 'monthly_exam_limit' => 10]);
    Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);

    $this->actingAs($teacher);

    livewire(TeacherMySubscription::class)
        ->assertSee('Teacher Pro')
        ->assertSee('10');
});
