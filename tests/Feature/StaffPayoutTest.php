<?php

use App\Enums\StaffEarningStatus;
use App\Enums\StaffPayoutStatus;
use App\Models\StaffEarning;
use App\Models\StaffPayout;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->staff = User::factory()->staff()->create();
    StaffProfile::factory()->for($this->staff, 'user')->create(['total_paid' => 0]);
});

it('batch-pays every pending earning for a staff member and marks them paid', function () {
    $earnings = StaffEarning::factory()->count(3)->create(['staff_id' => $this->staff->id, 'amount' => 10]);

    $payout = StaffPayout::createFor($this->staff, $this->admin, 'bKash TrxID 123');

    expect($payout->status)->toBe(StaffPayoutStatus::Paid);
    expect((float) $payout->total_amount)->toBe(30.0);

    foreach ($earnings as $earning) {
        expect($earning->refresh()->status)->toBe(StaffEarningStatus::Paid);
        expect($earning->payout_id)->toBe($payout->id);
    }

    expect((float) $this->staff->staffProfile->refresh()->total_paid)->toBe(30.0);
});

it('does not double-pay earnings that are already paid out', function () {
    StaffEarning::factory()->count(2)->create(['staff_id' => $this->staff->id, 'amount' => 10]);

    $firstPayout = StaffPayout::createFor($this->staff, $this->admin);
    expect((float) $firstPayout->total_amount)->toBe(20.0);

    // A new earning arrives after the first payout — only this one should
    // be picked up by a second payout run.
    StaffEarning::factory()->create(['staff_id' => $this->staff->id, 'amount' => 5]);

    $secondPayout = StaffPayout::createFor($this->staff, $this->admin);

    expect((float) $secondPayout->total_amount)->toBe(5.0);
    expect(StaffEarning::where('status', StaffEarningStatus::PendingPayout)->count())->toBe(0);
    expect((float) $this->staff->staffProfile->refresh()->total_paid)->toBe(25.0);
});
