<?php

use App\Enums\UserStatus;
use App\Filament\Resources\StaffPayouts\Pages\ManageStaffPayouts;
use App\Filament\Resources\Staffs\Pages\ManageStaffs;
use App\Filament\Resources\Teachers\Pages\ManageTeachers;
use App\Filament\Staff\Pages\MyEarnings;
use App\Models\StaffEarning;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

it('lists only teacher-role users in the teacher resource', function () {
    $teacher = User::factory()->teacher()->create();
    $staff = User::factory()->staff()->create();

    $this->actingAs($this->admin);

    livewire(ManageTeachers::class)
        ->assertCanSeeTableRecords([$teacher])
        ->assertCanNotSeeTableRecords([$staff]);
});

it('lets admin approve a pending staff member from the staff resource', function () {
    $staff = User::factory()->staff()->pendingApproval()->googleAuthenticated()->create();

    $this->actingAs($this->admin);

    livewire(ManageStaffs::class)
        ->callTableAction('approve', $staff)
        ->assertHasNoTableActionErrors();

    expect($staff->refresh()->status)->toBe(UserStatus::Active);
});

it('batch-pays a staff member from the payout resource header action', function () {
    $staff = User::factory()->staff()->create();
    StaffProfile::factory()->for($staff, 'user')->create(['total_paid' => 0]);
    StaffEarning::factory()->count(2)->create(['staff_id' => $staff->id, 'amount' => 10]);

    $this->actingAs($this->admin);

    livewire(ManageStaffPayouts::class)
        ->callAction('createPayout', data: [
            'staff_id' => $staff->id,
            'reference_note' => 'Test payout',
        ])
        ->assertHasNoActionErrors();

    expect((float) $staff->staffProfile->refresh()->total_paid)->toBe(20.0);
});

it('lets a staff member save their bank info and see their earnings summary', function () {
    $staff = User::factory()->staff()->create();
    StaffEarning::factory()->create(['staff_id' => $staff->id, 'amount' => 12]);

    $this->actingAs($staff);

    livewire(MyEarnings::class)
        ->fillForm([
            'account_holder_name' => 'John Doe',
            'bank_name' => 'ABC Bank',
            'bank_account_number' => '1234567890',
        ])
        ->call('saveBankInfo')
        ->assertHasNoFormErrors();

    expect(StaffProfile::where('user_id', $staff->id)->first()->account_holder_name)->toBe('John Doe');
});
