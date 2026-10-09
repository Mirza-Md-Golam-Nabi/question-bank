<?php

use App\Enums\ReactivationRequestStatus;
use App\Enums\UserStatus;
use App\Filament\Resources\ReactivationRequests\Pages\ManageReactivationRequests;
use App\Filament\Staff\Pages\RequestReactivation as StaffRequestReactivation;
use App\Filament\Teacher\Pages\RequestReactivation as TeacherRequestReactivation;
use App\Models\ReactivationRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('lets a suspended user of either panel send a reactivation request', function (string $role, string $page) {
    $user = User::factory()->{$role}()->suspended()->create();
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel($role));

    livewire($page)
        ->fillForm(['message' => 'I have fixed the issue, please reactivate me.'])
        ->call('submit')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $this->assertDatabaseHas('reactivation_requests', [
        'user_id' => $user->id,
        'message' => 'I have fixed the issue, please reactivate me.',
        'status' => ReactivationRequestStatus::Pending->value,
    ]);
})->with([
    'staff' => ['staff', StaffRequestReactivation::class],
    'teacher' => ['teacher', TeacherRequestReactivation::class],
]);

it('needs a reason to send the request', function () {
    $this->actingAs(User::factory()->staff()->suspended()->create());
    Filament::setCurrentPanel(Filament::getPanel('staff'));

    livewire(StaffRequestReactivation::class)
        ->fillForm(['message' => ''])
        ->call('submit')
        ->assertHasFormErrors(['message' => 'required']);

    expect(ReactivationRequest::count())->toBe(0);
});

it('keeps the request page away from a user who is not suspended', function () {
    $this->actingAs(User::factory()->staff()->create());

    $this->get(StaffRequestReactivation::getUrl(panel: 'staff'))->assertForbidden();
});

it('opens the request page for a suspended user', function () {
    $this->actingAs(User::factory()->teacher()->suspended()->create());

    $this->get(TeacherRequestReactivation::getUrl(panel: 'teacher'))->assertOk();
});

it('allows only one waiting request at a time', function () {
    $staff = User::factory()->staff()->suspended()->create();
    ReactivationRequest::submitFor($staff, 'First request message');

    expect(fn () => ReactivationRequest::submitFor($staff, 'Second request message'))
        ->toThrow(ValidationException::class, 'You cannot send a reactivation request right now.');
    expect(ReactivationRequest::count())->toBe(1);
});

it('lets a user ask again after a request was declined', function () {
    $staff = User::factory()->staff()->suspended()->create();
    ReactivationRequest::submitFor($staff, 'First request message')->decline(User::factory()->admin()->create());

    ReactivationRequest::submitFor($staff, 'Second request message');

    expect(ReactivationRequest::count())->toBe(2);
});

it('refuses a request from a user who is not suspended', function () {
    expect(fn () => ReactivationRequest::submitFor(User::factory()->staff()->create(), 'Please reactivate me'))
        ->toThrow(ValidationException::class);
});

it('reactivates the account when an admin approves the request', function () {
    $staff = User::factory()->staff()->suspended()->create();
    $request = ReactivationRequest::factory()->create(['user_id' => $staff->id]);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    livewire(ManageReactivationRequests::class)
        ->callAction(TestAction::make('approve')->table($request), ['admin_note' => 'Welcome back'])
        ->assertNotified();

    expect($staff->refresh()->status)->toBe(UserStatus::Active);
    expect($request->refresh()->status)->toBe(ReactivationRequestStatus::Approved)
        ->and($request->admin_note)->toBe('Welcome back')
        ->and($request->reviewed_by)->toBe($admin->id);
});

it('leaves the account suspended when an admin declines the request', function () {
    $teacher = User::factory()->teacher()->suspended()->create();
    $request = ReactivationRequest::factory()->create(['user_id' => $teacher->id]);
    $this->actingAs(User::factory()->admin()->create());

    livewire(ManageReactivationRequests::class)
        ->callAction(TestAction::make('decline')->table($request), ['admin_note' => 'Not yet'])
        ->assertNotified();

    expect($teacher->refresh()->status)->toBe(UserStatus::Suspended);
    expect($request->refresh()->status)->toBe(ReactivationRequestStatus::Declined)
        ->and($request->admin_note)->toBe('Not yet');
});

it('does not lift a permanent suspension placed after the request was sent', function () {
    $staff = User::factory()->staff()->suspended()->create();
    $request = ReactivationRequest::factory()->create(['user_id' => $staff->id]);
    $staff->update(['status' => UserStatus::PermanentSuspend]);

    $request->approve(User::factory()->admin()->create());

    expect($staff->refresh()->status)->toBe(UserStatus::PermanentSuspend);
});

it('does nothing when a request that was already decided is decided again', function () {
    $staff = User::factory()->staff()->suspended()->create();
    $request = ReactivationRequest::factory()->create(['user_id' => $staff->id]);
    $admin = User::factory()->admin()->create();
    $request->decline($admin);

    expect($request->approve($admin))->toBeFalse();
    expect($staff->refresh()->status)->toBe(UserStatus::Suspended);
});

it('shows the user what the admin replied', function () {
    $staff = User::factory()->staff()->suspended()->create();
    ReactivationRequest::factory()->create(['user_id' => $staff->id])
        ->decline(User::factory()->admin()->create(), 'Please contact the office first');
    $this->actingAs($staff);
    Filament::setCurrentPanel(Filament::getPanel('staff'));

    livewire(StaffRequestReactivation::class)->assertSee('Please contact the office first');
});
