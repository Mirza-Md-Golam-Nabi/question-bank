<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('creates a new active teacher on first google login', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-teacher-1',
        'email' => 'teacher@example.com',
        'name' => 'New Teacher',
    ]));

    $this->get('/auth/google/redirect/teacher');
    $response = $this->get('/auth/google/callback');

    $user = User::where('email', 'teacher@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe(UserRole::Teacher);
    expect($user->status)->toBe(UserStatus::Active);
    expect($user->google_id)->toBe('google-teacher-1');
    expect($user->hasRole(UserRole::Teacher->value))->toBeTrue();
    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('filament.teacher.pages.dashboard'));
});

it('creates a new staff account as pending approval on first google login', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-staff-1',
        'email' => 'staff@example.com',
        'name' => 'New Staff',
    ]));

    $this->get('/auth/google/redirect/staff');
    $this->get('/auth/google/callback');

    $user = User::where('email', 'staff@example.com')->first();

    expect($user->role)->toBe(UserRole::Staff);
    expect($user->status)->toBe(UserStatus::Pending);
});

it('logs in an existing user by google_id without creating a duplicate', function () {
    $existing = User::factory()->teacher()->googleAuthenticated()->create([
        'google_id' => 'google-teacher-2',
        'email' => 'existing-teacher@example.com',
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-teacher-2',
        'email' => 'existing-teacher@example.com',
        'name' => 'Existing Teacher',
    ]));

    $this->get('/auth/google/redirect/teacher');
    $this->get('/auth/google/callback');

    expect(User::where('email', 'existing-teacher@example.com')->count())->toBe(1);
    $this->assertAuthenticatedAs($existing);
});

it('blocks login when the google account role does not match the intended panel role', function () {
    User::factory()->student()->googleAuthenticated()->create([
        'google_id' => 'google-student-1',
        'email' => 'mismatch@example.com',
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-student-1',
        'email' => 'mismatch@example.com',
        'name' => 'Mismatch User',
    ]));

    $this->get('/auth/google/redirect/teacher');
    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('filament.teacher.auth.login'));
    $this->assertGuest();
});

it('returns 404 for a google redirect targeting a password-authenticated role', function () {
    $this->get('/auth/google/redirect/admin')->assertNotFound();
    $this->get('/auth/google/redirect/super_admin')->assertNotFound();
    $this->get('/auth/google/redirect/not-a-role')->assertNotFound();
});
