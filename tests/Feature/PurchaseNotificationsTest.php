<?php

use App\Enums\AdminNotificationTopic;
use App\Enums\UserStatus;
use App\Enums\WalletTransactionType;
use App\Filament\Pages\BillingSettings;
use App\Models\BillingSetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionPurchaseService;
use App\Services\WalletService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Notifications\DatabaseNotification;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    // As on the real site: the Teacher and Student panels in Bangla, the Admin panel in English.
    config(['app.locale' => 'bn']);
    BillingSetting::create(['bkash_number' => '01700000000', 'refund_window_days' => 7]);

    $this->purchases = app(SubscriptionPurchaseService::class);
    $this->admin = User::factory()->admin()->create(['name' => 'First Admin']);
    $this->superAdmin = User::factory()->superAdmin()->create(['name' => 'The Super Admin']);
    $this->teacher = User::factory()->teacher()->create(['name' => 'Rahim']);
    $this->plan = SubscriptionPlan::factory()->create(['name' => 'Pro', 'price' => 500, 'duration_unit' => 'months', 'duration_length' => 1]);

    $this->buy = fn (array $overrides = []) => $this->purchases->request($this->teacher, $this->plan, [
        'payer_provider' => 'bkash',
        'payer_number' => '01811111111',
        'transaction_id' => 'TRX123',
        ...$overrides,
    ]);

    /** @return list<string> The titles in this user's bell, newest first. */
    $this->bell = fn (User $user): array => $user->notifications()->latest()->get()->map(fn (DatabaseNotification $notification): string => $notification->data['title'])->all();
});

it('puts a notification bell in every panel, looked at once a minute', function (string $panel) {
    expect(Filament::getPanel($panel)->hasDatabaseNotifications())->toBeTrue()
        ->and(Filament::getPanel($panel)->getDatabaseNotificationsPollingInterval())->toBe('60s');
})->with(['admin', 'teacher', 'staff', 'student']);

it('tells every Admin of a purchase to check, and the buyer that it arrived — each in their own panel\'s language', function () {
    ($this->buy)();

    expect(($this->bell)($this->admin))->toBe(['Rahim (Teacher) asks for the Pro plan'])
        ->and(($this->bell)($this->superAdmin))->toHaveCount(1)
        ->and($this->admin->notifications()->sole()->data['body'])->toContain('TRX123')->toContain('01811111111')->toContain('৳500.00');

    // The Teacher's panel opens in Bangla.
    expect(($this->bell)($this->teacher))->toBe([__('Your payment was received', locale: 'bn')]);
});

it('tells only the Admins the settings name, and none who is no longer active', function () {
    $quiet = User::factory()->admin()->create();
    $suspended = User::factory()->admin()->create(['status' => UserStatus::Suspended]);
    BillingSetting::current()->fill(['admin_notification_recipients' => [AdminNotificationTopic::Purchases->value => [$this->admin->id, $suspended->id]]])->save();

    ($this->buy)();

    expect(($this->bell)($this->admin))->toHaveCount(1)
        ->and(($this->bell)($this->superAdmin))->toBe([])
        ->and(($this->bell)($quiet))->toBe([])
        ->and(($this->bell)($suspended))->toBe([]);
});

it('tells the buyer once their plan is active, however often it is approved', function () {
    $payment = ($this->buy)();

    $this->travel(1)->minute();
    $this->purchases->approve($payment, $this->admin);
    $this->purchases->approve($payment, $this->admin);

    expect(($this->bell)($this->teacher))->toHaveCount(2)
        ->and(($this->bell)($this->teacher)[0])->toBe(__('Your :plan plan is active', ['plan' => 'Pro'], 'bn'))
        ->and($this->teacher->notifications()->latest()->first()->data['body'])->toContain(now()->addMonth()->translatedFormat('Y'));
});

it('tells the buyer why their payment was turned down, or given back, and that their credit came back', function () {
    app(WalletService::class)->credit($this->teacher, 100, WalletTransactionType::PurchaseReturn);

    $payment = ($this->buy)(['use_wallet' => true]);
    $this->travel(1)->minute();
    $this->purchases->reject($payment, $this->admin, 'No such transaction.');

    $rejected = $this->teacher->notifications()->latest()->first()->data;

    expect($rejected['title'])->toBe(__('Your payment for the :plan plan was not accepted', ['plan' => 'Pro'], 'bn'))
        ->and($rejected['body'])->toContain('No such transaction.')->toContain('৳100.00');

    $this->travel(1)->minute();
    $payment = ($this->buy)(['transaction_id' => 'TRX999']);
    $this->purchases->approve($payment, $this->admin);
    $this->travel(1)->minute();
    $this->purchases->refund($payment, $this->admin);

    expect($this->teacher->notifications()->latest()->first()->data['title'])->toBe(__('Your :plan plan was refunded', ['plan' => 'Pro'], 'bn'));
});

it('tells the Admins of a plan bought with wallet credit alone, and the buyer only that it is active', function () {
    app(WalletService::class)->credit($this->teacher, 500, WalletTransactionType::PurchaseReturn);

    ($this->buy)(['use_wallet' => true]);

    expect(($this->bell)($this->admin))->toBe(['Rahim (Teacher) bought the Pro plan'])
        ->and(($this->bell)($this->teacher))->toBe([__('Your :plan plan is active', ['plan' => 'Pro'], 'bn')]);
});

it('says nothing of the free trial', function () {
    $trial = SubscriptionPlan::factory()->trial()->create();

    $this->purchases->request($this->teacher, $trial, []);

    expect(DatabaseNotification::count())->toBe(0);
});

it('lets the Admin choose on the settings page who hears of purchases', function () {
    $this->actingAs($this->superAdmin);

    Livewire\Livewire::test(BillingSettings::class)
        ->fillForm(['admin_notification_recipients.purchases' => [$this->admin->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(BillingSetting::current()->adminsToNotify(AdminNotificationTopic::Purchases)->pluck('id')->all())->toBe([$this->admin->id]);
});
