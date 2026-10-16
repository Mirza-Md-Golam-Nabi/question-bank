<?php

use App\Enums\PlanConditionType;
use App\Filament\Student\Pages\MySubscription as StudentMySubscription;
use App\Models\BillingSetting;
use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\Subscription;
use App\Models\SubscriptionMember;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use App\Services\SubscriptionMemberService;
use Database\Factories\SubscriptionPlanFactory;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->freezeSecond();
    BillingSetting::create(['group_member_change_days' => 30]);

    $this->members = app(SubscriptionMemberService::class);
    $this->limits = app(SubscriptionLimitService::class);
    $this->classSubject = ClassSubject::factory()->create();

    $this->owner = User::factory()->student()->create(['name' => 'Owner O']);
    $plan = SubscriptionPlan::factory()->forStudents()->withConditions([
        SubscriptionPlanFactory::condition(PlanConditionType::Seats, ['count' => 3]),
        SubscriptionPlanFactory::condition(PlanConditionType::DailyExamLimit, ['limit' => 1]),
    ])->create(['name' => 'Three friends', 'price' => 189]);
    $this->subscription = Subscription::factory()->create([
        'user_id' => $this->owner->id,
        'plan_id' => $plan->id,
        'ends_at' => now()->addMonth(),
    ]);
});

it('lets the buyer share the plan with as many people as it has seats, each with their own account', function () {
    $friend = User::factory()->student()->create();

    $this->members->add($this->owner, $this->subscription, strtoupper($friend->email));
    $this->members->add($this->owner, $this->subscription, 'not-signed-up-yet@gmail.com');

    expect(fn () => $this->members->add($this->owner, $this->subscription, 'one-too-many@gmail.com'))
        ->toThrow(ValidationException::class, 'Every seat');

    // The friend now has the plan, shown as shared.
    $grant = collect($this->limits->grantsFor($friend))->firstWhere('subscriptionId', $this->subscription->id);
    expect($grant->sharedBy)->toBe('Owner O');

    // Someone added before they ever signed in gets it as soon as they do.
    $late = User::factory()->student()->create(['email' => 'not-signed-up-yet@gmail.com']);
    expect($this->limits->authorize($late, $this->classSubject)->subscriptionId)->toBe($this->subscription->id);
});

it('only shares with the role the plan is for, and not with the buyer themselves', function () {
    $teacher = User::factory()->teacher()->create();

    expect(fn () => $this->members->add($this->owner, $this->subscription, $teacher->email))->toThrow(ValidationException::class)
        ->and(fn () => $this->members->add($this->owner, $this->subscription, $this->owner->email))->toThrow(ValidationException::class)
        ->and(fn () => $this->members->add(User::factory()->student()->create(), $this->subscription, 'x@gmail.com'))->toThrow(ValidationException::class);
});

it('ends everyone\'s access when the buyer\'s plan ends', function () {
    $friend = User::factory()->student()->create();
    $this->members->add($this->owner, $this->subscription, $friend->email);

    $this->travel(32)->days();

    expect($friend->activeSubscriptions())->toBeEmpty();
});

it('lets the buyer take someone off only once in the admin\'s number of days', function () {
    $first = $this->members->add($this->owner, $this->subscription, 'a@gmail.com');
    $second = $this->members->add($this->owner, $this->subscription, 'b@gmail.com');

    $this->members->remove($this->owner, $first);
    // The seat can be filled again at once…
    $this->members->add($this->owner, $this->subscription, 'c@gmail.com');

    // …but nobody else can be taken off for 30 days.
    expect(fn () => $this->members->remove($this->owner, $second))->toThrow(ValidationException::class);

    $this->travel(30)->days();
    $this->members->remove($this->owner, $second);

    expect(SubscriptionMember::query()->current()->pluck('email')->all())->toBe(['c@gmail.com']);
});

it('counts each person\'s exams on their own', function () {
    $friend = User::factory()->student()->create();
    $this->members->add($this->owner, $this->subscription, $friend->email);

    Exam::factory()->selfPractice()->create(['created_by' => $this->owner->id, 'subscription_id' => $this->subscription->id]);

    // The owner used their one exam today; the friend still has theirs.
    expect(fn () => $this->limits->authorize($this->owner, $this->classSubject))->toThrow(ValidationException::class)
        ->and($this->limits->authorize($friend, $this->classSubject)->subscriptionId)->toBe($this->subscription->id);
});

it('lets the buyer add and remove people from their subscription page, and shows the friend whose plan it is', function () {
    $friend = User::factory()->student()->create();
    $this->actingAs($this->owner);
    Filament::setCurrentPanel(Filament::getPanel('student'));

    livewire(StudentMySubscription::class)
        ->assertSee('1 of 3 seats used')
        ->callAction('addMember', data: ['email' => $friend->email], arguments: ['subscription' => $this->subscription->id])
        ->assertNotified('Added to your plan')
        ->assertSee($friend->email)
        ->callAction('removeMember', arguments: ['member' => SubscriptionMember::sole()->id])
        ->assertNotified('Removed from your plan');

    expect(SubscriptionMember::query()->current()->count())->toBe(0);

    $this->members->add($this->owner, $this->subscription, $friend->email);
    $this->actingAs($friend);

    livewire(StudentMySubscription::class)
        ->assertSee('Three friends')
        ->assertSee('Shared by Owner O');
});
