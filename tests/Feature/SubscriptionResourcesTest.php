<?php

use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Resources\SubscriptionPlans\Pages\ManageSubscriptionPlans;
use App\Filament\Resources\Subscriptions\Pages\ManageSubscriptions;
use App\Filament\Teacher\Pages\MySubscription as TeacherMySubscription;
use App\Models\AcademicClass;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

it('creates a subscription plan with the conditions the admin adds', function () {
    $this->actingAs($this->admin);
    $class = AcademicClass::factory()->create();

    livewire(ManageSubscriptionPlans::class)
        ->callAction('create', data: [
            'name' => 'One subject',
            'target_role' => 'teacher',
            'price' => 149,
            'duration_unit' => 'years',
            'duration_length' => 1,
            'is_active' => true,
            'conditions' => [
                ['type' => 'classes', 'data' => ['class_ids' => [$class->id], 'pick_count' => null]],
                ['type' => 'subjects', 'data' => ['subject_ids' => [], 'pick_count' => 1]],
                ['type' => 'monthly_exam_limit', 'data' => ['limit' => 30]],
            ],
        ])
        ->assertHasNoActionErrors();

    $plan = SubscriptionPlan::sole();
    $conditions = $plan->planConditions();

    expect($plan->name)->toBe('One subject')
        ->and($plan->periodEndFrom(now()->startOfDay())->equalTo(now()->startOfDay()->addYear()))->toBeTrue()
        ->and($conditions->classIds)->toBe([$class->id])
        ->and($conditions->needsClassPick())->toBeFalse()
        ->and($conditions->subjectPickCount)->toBe(1)
        ->and($conditions->monthlyExamLimit)->toBe(30)
        ->and($conditions->dailyExamLimit)->toBeNull();
});

it('lets the admin remove a condition from a plan', function () {
    $this->actingAs($this->admin);
    $plan = SubscriptionPlan::factory()->monthlyLimit(5)->create();

    livewire(ManageSubscriptionPlans::class)
        ->callTableAction('edit', $plan, data: ['conditions' => []])
        ->assertHasNoTableActionErrors();

    expect($plan->fresh()->planConditions()->monthlyExamLimit)->toBeNull();
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

it('offers the admin no way to type in a payment by hand', function () {
    $this->actingAs($this->admin);

    livewire(ManagePayments::class)->assertActionDoesNotExist('create');
});

it('shows a teacher their active subscription on the my-subscription page', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->monthlyLimit(10)->create(['name' => 'Teacher Pro']);
    Subscription::factory()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);

    $this->actingAs($teacher);

    livewire(TeacherMySubscription::class)
        ->assertSee('Teacher Pro')
        ->assertSee('10 exams a month')
        ->assertSee('Used this month: 0 / 10');
});

it('lists every plan on one page with all, student and teacher tabs that filter in the browser', function () {
    $this->actingAs($this->admin);
    $teacherPlans = SubscriptionPlan::factory()->count(16)->create();
    $studentPlan = SubscriptionPlan::factory()->forStudents()->create();

    $page = livewire(ManageSubscriptionPlans::class);

    expect($page->instance()->roleTabs())->toBe(['all' => 'All', 'student' => 'Student', 'teacher' => 'Teacher']);

    $page
        // Not paginated: the tabs only hide rows, so every plan has to be there.
        ->assertCanSeeTableRecords([...$teacherPlans, $studentPlan])
        ->assertSeeHtml('qb-plan-role-teacher')
        ->assertSeeHtml('qb-plan-role-student');
});
