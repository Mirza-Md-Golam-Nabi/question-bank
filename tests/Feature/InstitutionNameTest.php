<?php

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\WalletTransactionType;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\School\Pages\InstitutionNames as SchoolInstitutionNames;
use App\Filament\Teacher\Pages\InstitutionNames as TeacherInstitutionNames;
use App\Models\BillingSetting;
use App\Models\InstitutionName;
use App\Models\Payment;
use App\Models\SchoolProfile;
use App\Models\User;
use App\Services\InstitutionNameService;
use App\Services\SubscriptionPurchaseService;
use App\Services\WalletService;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    BillingSetting::create([
        'bkash_number' => '01700000000',
        'institution_name_price' => 100,
        'institution_name_days' => 30,
        'institution_name_change_days' => 30,
    ]);

    $this->names = app(InstitutionNameService::class);
    $this->teacher = User::factory()->teacher()->create();
    $this->paidBy = fn (string $transactionId = 'TRX-NAME'): array => [
        'payer_provider' => 'bkash',
        'payer_number' => '01811111111',
        'transaction_id' => $transactionId,
    ];
});

describe('the free name', function () {
    it('is saved free and can always be printed', function () {
        $name = $this->names->saveFreeName($this->teacher, '  Rupdia   Welfare  School ');

        expect($name->name)->toBe('Rupdia Welfare School')
            ->and($name->is_free)->toBeTrue()
            ->and($this->names->usableNamesFor($this->teacher)->pluck('name')->all())->toBe(['Rupdia Welfare School']);
    });

    it('can be changed only once every so many days', function () {
        $this->names->saveFreeName($this->teacher, 'First School');
        $this->names->saveFreeName($this->teacher, 'Second School');

        expect(fn () => $this->names->saveFreeName($this->teacher, 'Third School'))
            ->toThrow(ValidationException::class);

        $this->travel(31)->days();

        expect($this->names->saveFreeName($this->teacher, 'Third School')->name)->toBe('Third School')
            ->and($this->teacher->institutionNames()->count())->toBe(1);
    });

    it('starts as the name on a school profile', function () {
        $school = User::factory()->school()->create();
        SchoolProfile::for($school)->update(['name' => 'Ideal High School']);

        expect($this->names->usableNamesFor($school)->pluck('name')->all())->toBe(['Ideal High School']);
    });

    it('is only for those who print question papers', function () {
        expect(fn () => $this->names->saveFreeName(User::factory()->student()->create(), 'My School'))
            ->toThrow(ValidationException::class);
    });
});

describe('an extra name', function () {
    beforeEach(function () {
        $this->names->saveFreeName($this->teacher, 'Free School');
    });

    it('waits for the admin before it can be printed, then runs for the admin\'s days', function () {
        $payment = $this->names->requestNew($this->teacher, 'Paid Coaching', ($this->paidBy)());

        expect($payment->type)->toBe(PaymentType::InstitutionName)
            ->and($payment->status)->toBe(PaymentStatus::Pending)
            ->and((float) $payment->amount)->toBe(100.0)
            ->and($payment->period_days)->toBe(30)
            ->and($this->names->usableNamesFor($this->teacher)->pluck('name')->all())->toBe(['Free School']);

        app(SubscriptionPurchaseService::class)->approve($payment);

        $paidName = $payment->institutionName->refresh();

        expect($paidName->paid_until->isSameDay(now()->addDays(30)))->toBeTrue()
            ->and($this->names->usableNamesFor($this->teacher)->pluck('name')->all())->toBe(['Free School', 'Paid Coaching']);
    });

    it('stops being printable when its days run out, until it is paid for again', function () {
        $payment = $this->names->requestNew($this->teacher, 'Paid Coaching', ($this->paidBy)());
        app(SubscriptionPurchaseService::class)->approve($payment);

        $this->travel(31)->days();

        expect($this->names->usableNamesFor($this->teacher)->pluck('name')->all())->toBe(['Free School']);

        $renewal = $this->names->requestRenewal($this->teacher, $payment->institutionName, ($this->paidBy)('TRX-AGAIN'));
        app(SubscriptionPurchaseService::class)->approve($renewal);

        expect($this->names->usableNamesFor($this->teacher)->pluck('name')->all())->toBe(['Free School', 'Paid Coaching']);
    });

    it('adds the days of an early renewal after the days still left', function () {
        $name = InstitutionName::factory()->paid(10)->create(['user_id' => $this->teacher->id]);
        $paidUntil = $name->paid_until;

        app(SubscriptionPurchaseService::class)->approve($this->names->requestRenewal($this->teacher, $name, ($this->paidBy)()));

        expect($name->refresh()->paid_until->toDateTimeString())->toBe($paidUntil->copy()->addDays(30)->toDateTimeString());
    });

    it('is not on sale while the admin has set no price', function () {
        BillingSetting::current()->update(['institution_name_price' => 0]);

        expect(fn () => $this->names->requestNew($this->teacher, 'Paid Coaching', ($this->paidBy)()))
            ->toThrow(ValidationException::class);
    });

    it('stays unprintable and can be removed when its payment is turned down, with any wallet credit given back', function () {
        app(WalletService::class)->credit($this->teacher, 40, WalletTransactionType::PlanChangeRefund);

        $payment = $this->names->requestNew($this->teacher, 'Paid Coaching', [...($this->paidBy)(), 'use_wallet' => true]);

        expect((float) $payment->wallet_amount)->toBe(40.0)
            ->and((float) $payment->amount)->toBe(60.0);

        app(SubscriptionPurchaseService::class)->reject($payment, User::factory()->admin()->create());

        $name = $payment->institutionName;

        expect($name->refresh()->isUsable())->toBeFalse()
            ->and(app(WalletService::class)->balanceFor($this->teacher))->toBe(40.0)
            ->and($this->names->canDelete($name))->toBeTrue();

        $this->names->delete($this->teacher, $name);

        expect(InstitutionName::find($name->id))->toBeNull();
    });

    it('is usable at once when wallet credit covers the price', function () {
        app(WalletService::class)->credit($this->teacher, 150, WalletTransactionType::PlanChangeRefund);

        $payment = $this->names->requestNew($this->teacher, 'Paid Coaching', ['use_wallet' => true]);

        expect($payment->status)->toBe(PaymentStatus::Success)
            ->and($payment->institutionName->isUsable())->toBeTrue();
    });

    it('cannot take a name the user already has', function () {
        expect(fn () => $this->names->requestNew($this->teacher, 'free school', ($this->paidBy)()))
            ->toThrow(ValidationException::class);
    });

    it('is never counted as having paid for a subscription', function () {
        app(SubscriptionPurchaseService::class)->approve($this->names->requestNew($this->teacher, 'Paid Coaching', ($this->paidBy)()));

        expect($this->teacher->hasSuccessfulPayment())->toBeFalse()
            ->and(Payment::query()->forSubscriptions()->count())->toBe(0);
    });

    it('cannot be renewed by anyone else', function () {
        $name = InstitutionName::factory()->expired()->create(['user_id' => $this->teacher->id]);

        expect(fn () => $this->names->requestRenewal(User::factory()->teacher()->create(), $name, ($this->paidBy)()))
            ->toThrow(ValidationException::class);
    });
});

describe('the pages', function () {
    it('lets a teacher add their free name', function () {
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        livewire(TeacherInstitutionNames::class)
            ->callAction('addFreeName', ['name' => 'Rupdia Welfare School'])
            ->assertNotified();

        expect($this->teacher->institutionNames()->value('name'))->toBe('Rupdia Welfare School');
    });

    it('lets a teacher ask for another name', function () {
        $this->names->saveFreeName($this->teacher, 'Free School');
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        livewire(TeacherInstitutionNames::class)
            ->assertSee('Free School')
            ->callAction('addPaidName', ['name' => 'Paid Coaching', ...($this->paidBy)()])
            ->assertHasNoActionErrors();

        expect(Payment::where('type', PaymentType::InstitutionName)->where('status', PaymentStatus::Pending)->count())->toBe(1);
    });

    it('shows a school its profile name', function () {
        $school = User::factory()->school()->create();
        SchoolProfile::for($school)->update(['name' => 'Ideal High School']);
        $this->actingAs($school);
        Filament::setCurrentPanel(Filament::getPanel('school'));

        livewire(SchoolInstitutionNames::class)->assertSee('Ideal High School');
    });

    it('lets the admin approve the payment for a name', function () {
        $this->names->saveFreeName($this->teacher, 'Free School');
        $payment = $this->names->requestNew($this->teacher, 'Paid Coaching', ($this->paidBy)());

        $this->actingAs(User::factory()->admin()->create());

        livewire(ManagePayments::class)
            ->assertSee('Paid Coaching')
            ->callAction(TestAction::make('approve')->table($payment))
            ->assertNotified();

        expect($payment->institutionName->refresh()->isUsable())->toBeTrue()
            ->and($payment->refresh()->isWithinRefundPeriod())->toBeFalse();
    });
});
