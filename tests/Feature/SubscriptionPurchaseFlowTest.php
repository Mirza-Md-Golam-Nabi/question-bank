<?php

use App\Enums\PaymentStatus;
use App\Filament\Pages\BillingSettings;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Teacher\Pages\MySubscription as TeacherMySubscription;
use App\Models\BillingSetting;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('lets a teacher submit a payment for a plan from the my-subscription page', function () {
    BillingSetting::create(['bkash_number' => '01700000000']);
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->create(['name' => 'Teacher Pro', 'price' => 500]);
    $this->actingAs($teacher);
    Filament::setCurrentPanel(Filament::getPanel('teacher'));

    livewire(TeacherMySubscription::class)
        ->assertSee('Teacher Pro')
        ->callAction(TestAction::make('buy')->arguments(['plan' => $plan->id]), [
            'payer_provider' => 'bkash',
            'payer_number' => '01811111111',
            'transaction_id' => 'TRX-777',
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();

    $this->assertDatabaseHas('payments', [
        'user_id' => $teacher->id,
        'plan_id' => $plan->id,
        'amount' => 500,
        'gateway_transaction_id' => 'TRX-777',
        'status' => PaymentStatus::Pending->value,
    ]);
});

it('does not offer a teacher the student plans', function () {
    $teacher = User::factory()->teacher()->create();
    SubscriptionPlan::factory()->pro()->forStudents()->create(['name' => 'Student Pro']);
    $this->actingAs($teacher);
    Filament::setCurrentPanel(Filament::getPanel('teacher'));

    livewire(TeacherMySubscription::class)->assertDontSee('Student Pro');
});

it('lets an admin approve a pending payment from the admin panel', function () {
    $teacher = User::factory()->teacher()->create();
    $plan = SubscriptionPlan::factory()->pro()->create();
    $payment = Payment::factory()->pending()->create(['user_id' => $teacher->id, 'plan_id' => $plan->id]);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    livewire(ManagePayments::class)
        ->callAction(TestAction::make('approve')->table($payment))
        ->assertNotified();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Success)
        ->and($payment->reviewed_by)->toBe($admin->id);
    expect($teacher->activeSubscription()->plan_id)->toBe($plan->id);
});

it('lets an admin reject a pending payment with a reason', function () {
    $payment = Payment::factory()->pending()->create(['plan_id' => SubscriptionPlan::factory()->pro()]);
    $this->actingAs(User::factory()->admin()->create());

    livewire(ManagePayments::class)
        ->callAction(TestAction::make('reject')->table($payment), ['review_note' => 'No such transaction'])
        ->assertNotified();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($payment->review_note)->toBe('No such transaction');
});

it('lets an admin refund an approved payment', function () {
    $payment = Payment::factory()->refundable()->create(['plan_id' => SubscriptionPlan::factory()->pro()]);
    $this->actingAs(User::factory()->admin()->create());

    livewire(ManagePayments::class)
        ->callAction(TestAction::make('refund')->table($payment))
        ->assertNotified();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded);
});

it('offers no approve action on a payment that is already approved', function () {
    $payment = Payment::factory()->refundable()->create();
    $this->actingAs(User::factory()->admin()->create());

    livewire(ManagePayments::class)
        ->assertActionHidden(TestAction::make('approve')->table($payment))
        ->assertActionVisible(TestAction::make('refund')->table($payment));
});

it('offers no refund action once the refund period has passed', function () {
    $payment = Payment::factory()->refundable()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->travelTo(now()->addDays(8));

    livewire(ManagePayments::class)
        ->assertActionHidden(TestAction::make('refund')->table($payment));
});

it('lets an admin change the billing settings', function () {
    $this->actingAs(User::factory()->admin()->create());

    livewire(BillingSettings::class)
        ->fillForm([
            'referral_enabled' => true,
            'referrer_reward_percent' => 25,
            'staff_reward_percent' => 12,
            'referee_discount_percent' => 15,
            'refund_window_days' => 7,
            'credit_expiry_months' => 12,
            'phone_bonus_exams' => 2,
            'bkash_number' => '01700000000',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $settings = BillingSetting::current();
    expect($settings->referral_enabled)->toBeTrue()
        ->and($settings->referrer_reward_percent)->toBe(25.0)
        ->and($settings->staff_reward_percent)->toBe(12.0)
        ->and($settings->refund_window_days)->toBe(7)
        ->and($settings->referee_discount_percent)->toBe(15.0)
        ->and($settings->credit_expiry_months)->toBe(12)
        ->and($settings->phone_bonus_exams)->toBe(2)
        ->and($settings->receivingNumbers())->toBe(['bkash' => '01700000000']);
    expect(BillingSetting::count())->toBe(1);
});

it('refuses a reward percentage above one hundred', function () {
    $this->actingAs(User::factory()->admin()->create());

    livewire(BillingSettings::class)
        ->fillForm(['referrer_reward_percent' => 150])
        ->call('save')
        ->assertHasFormErrors(['referrer_reward_percent']);
});

it('keeps the billing settings page away from teachers', function () {
    $this->actingAs(User::factory()->teacher()->create());

    $this->get(BillingSettings::getUrl(panel: 'admin'))->assertForbidden();
});
