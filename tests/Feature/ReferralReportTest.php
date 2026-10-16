<?php

use App\Enums\UserRole;
use App\Filament\Pages\ReferralReport as ReferralReportPage;
use App\Models\Payment;
use App\Models\ReferralReward;
use App\Models\StudentReferral;
use App\Models\User;
use App\Services\ReferralReport;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    /**
     * A student under the referrer's referral and, when an amount is given,
     * with one approved payment of that much made under it.
     */
    $this->referred = function (User $referrer, ?float $paid = null): User {
        $student = User::factory()->student()->create();
        StudentReferral::factory()->create(['student_id' => $student->id, 'referrer_id' => $referrer->id]);

        if ($paid !== null) {
            Payment::factory()->create(['user_id' => $student->id, 'referrer_id' => $referrer->id, 'amount' => $paid]);
        }

        return $student;
    };
});

it('counts each referrer\'s sign-ups, buyers and cash, biggest earner first', function () {
    $teacher = User::factory()->teacher()->create(['name' => 'Teacher T']);
    $staff = User::factory()->staff()->create(['name' => 'Staff S']);
    ($this->referred)($teacher, 180);
    ($this->referred)($teacher);
    ($this->referred)($staff, 500);
    User::factory()->teacher()->create(['name' => 'Referred Nobody']);

    $rows = (new ReferralReport)->rows();

    expect($rows->pluck('name')->all())->toBe(['Staff S', 'Teacher T']);
    expect([$rows[1]->referred_count, $rows[1]->buyers_count, (float) $rows[1]->cash_received])->toBe([2, 1, 180.0]);
});

it('counts a referred teacher and each student once, and a student\'s cash for the referrer they paid under', function () {
    $teacher = User::factory()->teacher()->create(['name' => 'Teacher T']);
    $other = User::factory()->teacher()->create(['name' => 'Other O']);
    $student = ($this->referred)($teacher, 100);
    // The same student under the teacher again, later.
    StudentReferral::factory()->create(['student_id' => $student->id, 'referrer_id' => $teacher->id]);
    // Then under someone else, paying them.
    StudentReferral::factory()->create(['student_id' => $student->id, 'referrer_id' => $other->id]);
    Payment::factory()->create(['user_id' => $student->id, 'referrer_id' => $other->id, 'amount' => 40]);
    User::factory()->teacher()->create()->forceFill(['referred_by_id' => $teacher->id])->save();

    $rows = (new ReferralReport)->rows()->keyBy('name');

    expect([$rows['Teacher T']->referred_count, (float) $rows['Teacher T']->cash_received])->toBe([2, 100.0])
        ->and([$rows['Other O']->referred_count, (float) $rows['Other O']->cash_received])->toBe([1, 40.0]);
});

it('leaves refunded and pending payments out of the cash received', function () {
    $teacher = User::factory()->teacher()->create();
    $student = ($this->referred)($teacher, 180);
    Payment::factory()->pending()->create(['user_id' => $student->id, 'referrer_id' => $teacher->id, 'amount' => 999]);
    Payment::factory()->create(['user_id' => $student->id, 'referrer_id' => $teacher->id, 'amount' => 777, 'status' => 'refunded']);

    expect((float) (new ReferralReport)->rows()->first()->cash_received)->toBe(180.0);
});

it('splits a referrer\'s rewards into maturing, matured and cancelled', function () {
    $teacher = User::factory()->teacher()->create();
    ($this->referred)($teacher);
    ReferralReward::factory()->maturing()->create(['referrer_id' => $teacher->id, 'amount' => 10]);
    ReferralReward::factory()->create(['referrer_id' => $teacher->id, 'amount' => 20]);
    ReferralReward::factory()->cancelled()->create(['referrer_id' => $teacher->id, 'amount' => 40]);

    $row = (new ReferralReport)->rows()->first();

    expect([(float) $row->rewards_maturing, (float) $row->rewards_matured, (float) $row->rewards_cancelled])
        ->toBe([10.0, 20.0, 40.0]);
});

it('adds the figures up over every referrer', function () {
    $teacher = User::factory()->teacher()->create();
    $staff = User::factory()->staff()->create();
    ($this->referred)($teacher, 180);
    ($this->referred)($teacher);
    ($this->referred)($staff, 500);
    ReferralReward::factory()->create(['referrer_id' => $teacher->id, 'amount' => 36]);
    ReferralReward::factory()->maturing()->create(['referrer_id' => $staff->id, 'amount' => 50]);

    expect((new ReferralReport)->totals())->toBe([
        'referred' => 3,
        'buyers' => 2,
        'cash_received' => 680.0,
        'rewards_maturing' => 50.0,
        'rewards_matured' => 36.0,
        'rewards_cancelled' => 0.0,
    ]);
});

it('narrows the report to one kind of referrer', function () {
    $teacher = User::factory()->teacher()->create(['name' => 'Teacher T']);
    $staff = User::factory()->staff()->create(['name' => 'Staff S']);
    ($this->referred)($teacher, 180);
    ($this->referred)($staff, 500);

    $report = new ReferralReport(UserRole::Staff);

    expect($report->rows()->pluck('name')->all())->toBe(['Staff S']);
    expect($report->totals()['cash_received'])->toBe(500.0);
});

it('narrows the report to a period', function () {
    $teacher = User::factory()->teacher()->create();
    $this->travelTo('2026-01-10 12:00:00');
    ($this->referred)($teacher, 100);
    $this->travelTo('2026-03-10 12:00:00');
    ($this->referred)($teacher, 300);

    $report = new ReferralReport(from: Carbon::parse('2026-03-01'), until: Carbon::parse('2026-03-31'));
    $row = $report->rows()->first();

    expect([$row->referred_count, $row->buyers_count, (float) $row->cash_received])->toBe([1, 1, 300.0]);
    expect($report->totals()['cash_received'])->toBe(300.0);
});

it('shows the admin the report and filters it by role', function () {
    $teacher = User::factory()->teacher()->create(['name' => 'Teacher T']);
    $staff = User::factory()->staff()->create(['name' => 'Staff S']);
    ($this->referred)($teacher, 180);
    ($this->referred)($staff, 500);
    $this->actingAs(User::factory()->admin()->create());

    livewire(ReferralReportPage::class)
        ->assertSee(['Teacher T', 'Staff S'])
        ->fillForm(['role' => 'staff'])
        ->assertSee('Staff S')
        ->assertDontSee('Teacher T');
});

it('keeps the report away from staff', function () {
    $this->actingAs(User::factory()->staff()->create());

    $this->get(ReferralReportPage::getUrl(panel: 'admin'))->assertForbidden();
});
