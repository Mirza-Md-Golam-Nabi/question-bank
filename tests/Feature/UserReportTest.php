<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Pages\UserReport;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\UserSubscriptionReport;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('counts the Google users of each role and how many ever subscribed, leaving the admins out', function () {
    $paidPlan = SubscriptionPlan::factory()->pro()->create();
    $trialPlan = SubscriptionPlan::factory()->trial()->create();

    $buyer = User::factory()->teacher()->create();
    Subscription::factory()->expired()->create(['user_id' => $buyer->id, 'plan_id' => $paidPlan->id]);
    Subscription::factory()->create(['user_id' => $buyer->id, 'plan_id' => $paidPlan->id]);

    $onTrial = User::factory()->teacher()->create();
    Subscription::factory()->create(['user_id' => $onTrial->id, 'plan_id' => $trialPlan->id]);

    $trialOver = User::factory()->teacher()->create();
    Subscription::factory()->expired()->create(['user_id' => $trialOver->id, 'plan_id' => $trialPlan->id]);

    $gifted = User::factory()->student()->create();
    Subscription::factory()->create(['user_id' => $gifted->id, 'plan_id' => $paidPlan->id, 'is_gift' => true]);

    User::factory()->student()->count(2)->create();
    User::factory()->staff()->create();
    User::factory()->school()->create();
    User::factory()->admin()->create();

    $totals = UserSubscriptionReport::totals();

    expect($totals['teacher'])->toBe(['total' => 3, 'subscribed' => 3, 'unsubscribed' => 0, 'paid' => 1, 'gifted' => 0, 'on_trial' => 1])
        ->and($totals['student'])->toBe(['total' => 3, 'subscribed' => 1, 'unsubscribed' => 2, 'paid' => 0, 'gifted' => 1, 'on_trial' => 0])
        ->and($totals['staff'])->toBe(['total' => 1, 'subscribed' => 0, 'unsubscribed' => 1, 'paid' => 0, 'gifted' => 0, 'on_trial' => 0])
        ->and($totals['school']['total'])->toBe(1)
        ->and($totals['all'])->toBe(['total' => 8, 'subscribed' => 4, 'unsubscribed' => 4, 'paid' => 1, 'gifted' => 1, 'on_trial' => 1]);
});

it('lists users with the most subscriptions first, sorted by the database', function () {
    $plan = SubscriptionPlan::factory()->pro()->create();
    $once = User::factory()->teacher()->create(['name' => 'Once']);
    $thrice = User::factory()->student()->create(['name' => 'Thrice']);
    User::factory()->staff()->create(['name' => 'Never']);
    Subscription::factory()->create(['user_id' => $once->id, 'plan_id' => $plan->id]);
    Subscription::factory()->count(3)->create(['user_id' => $thrice->id, 'plan_id' => $plan->id]);
    $this->actingAs(User::factory()->admin()->create());

    livewire(UserReport::class)
        ->assertCanSeeTableRecords([$thrice, $once, User::firstWhere('name', 'Never')], inOrder: true)
        ->assertTableColumnStateSet('subscriptions_count', 3, $thrice)
        ->assertTableColumnStateSet('subscriptions_count', 0, User::firstWhere('name', 'Never'));
});

it('shows only the selected role in the table', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    livewire(UserReport::class)
        ->assertCanSeeTableRecords([$teacher, $student])
        ->assertCanNotSeeTableRecords([$admin])
        ->call('selectTab', UserRole::Student->value)
        ->assertCanSeeTableRecords([$student])
        ->assertCanNotSeeTableRecords([$teacher]);
});

it('filters the table by status, by subscription state and by date', function () {
    $paidPlan = SubscriptionPlan::factory()->pro()->create();
    $trialPlan = SubscriptionPlan::factory()->trial()->create();

    $buyer = User::factory()->teacher()->create(['created_at' => '2026-01-10']);
    Subscription::factory()->create(['user_id' => $buyer->id, 'plan_id' => $paidPlan->id, 'created_at' => '2026-03-01']);
    $onTrial = User::factory()->teacher()->create(['created_at' => '2026-05-10']);
    Subscription::factory()->create(['user_id' => $onTrial->id, 'plan_id' => $trialPlan->id, 'created_at' => '2026-06-01']);
    $trialOver = User::factory()->teacher()->create(['created_at' => '2026-05-10']);
    Subscription::factory()->expired()->create(['user_id' => $trialOver->id, 'plan_id' => $trialPlan->id, 'created_at' => '2026-06-01']);
    $gifted = User::factory()->student()->create(['created_at' => '2026-05-10']);
    Subscription::factory()->create(['user_id' => $gifted->id, 'plan_id' => $paidPlan->id, 'is_gift' => true, 'created_at' => '2026-06-01']);
    $never = User::factory()->student()->create(['created_at' => '2026-05-10']);
    $suspended = User::factory()->staff()->suspended()->create(['created_at' => '2026-05-10']);
    $this->actingAs(User::factory()->admin()->create());

    $everyone = [$buyer, $onTrial, $trialOver, $gifted, $never, $suspended];
    $expectations = [
        'subscribed' => [$buyer, $onTrial, $trialOver, $gifted],
        'never' => [$never, $suspended],
        'paid' => [$buyer],
        'gifted' => [$gifted],
        'trial' => [$onTrial, $trialOver],
        'on_trial' => [$onTrial],
    ];

    foreach ($expectations as $state => $shown) {
        livewire(UserReport::class)
            ->filterTable('subscription', $state)
            ->assertCanSeeTableRecords($shown)
            ->assertCanNotSeeTableRecords(array_udiff($everyone, $shown, fn (User $a, User $b): int => $a->id <=> $b->id));
    }

    livewire(UserReport::class)
        ->filterTable('status', UserStatus::Suspended->value)
        ->assertCanSeeTableRecords([$suspended])
        ->assertCanNotSeeTableRecords([$buyer, $never]);

    livewire(UserReport::class)
        ->filterTable('joined', ['from' => '2026-01-01', 'until' => '2026-01-31'])
        ->assertCanSeeTableRecords([$buyer])
        ->assertCanNotSeeTableRecords([$onTrial, $never]);

    livewire(UserReport::class)
        ->filterTable('last_subscribed', ['from' => '2026-05-15'])
        ->assertCanSeeTableRecords([$onTrial, $trialOver, $gifted])
        ->assertCanNotSeeTableRecords([$buyer, $never]);
});

it('counts and lists users in the same number of queries however many there are', function () {
    $plan = SubscriptionPlan::factory()->pro()->create();
    $this->actingAs(User::factory()->admin()->create());
    $queries = [];

    foreach ([2, 12] as $userCount) {
        User::factory()->student()->count($userCount)->create()
            ->each(fn (User $student) => Subscription::factory()->create(['user_id' => $student->id, 'plan_id' => $plan->id]));

        DB::flushQueryLog();
        DB::enableQueryLog();
        livewire(UserReport::class);
        DB::disableQueryLog();
        $queries[$userCount] = count(DB::getQueryLog());
    }

    expect($queries[12])->toBe($queries[2]);
});

it('keeps the report away from staff', function () {
    $this->actingAs(User::factory()->staff()->create());

    $this->get(UserReport::getUrl(panel: 'admin'))->assertForbidden();
});
