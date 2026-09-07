<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('redirects a pending staff member to the approval notice instead of the dashboard', function () {
    $staff = User::factory()->staff()->pending()->googleAuthenticated()->create();

    $response = $this->actingAs($staff)->get('/staff');

    $response->assertRedirect(route('filament.staff.pages.pending-approval'));
});

it('lets an active staff member reach the dashboard normally', function () {
    $staff = User::factory()->staff()->googleAuthenticated()->create();

    $response = $this->actingAs($staff)->get('/staff');

    $response->assertOk();
});

it('lets a pending staff member view the approval notice page itself', function () {
    $staff = User::factory()->staff()->pending()->googleAuthenticated()->create();

    $response = $this->actingAs($staff)->get(route('filament.staff.pages.pending-approval'));

    $response->assertOk();
});

it('lets a suspended staff member still reach the dashboard', function () {
    $staff = User::factory()->staff()->suspended()->googleAuthenticated()->create();

    $response = $this->actingAs($staff)->get('/staff');

    $response->assertOk();
});

it('blocks a suspended staff member from creating questions', function () {
    $staff = User::factory()->staff()->suspended()->googleAuthenticated()->create();

    $response = $this->actingAs($staff)->get(route('filament.staff.resources.questions.create'));

    $response->assertForbidden();
});

it('blocks a permanently suspended staff member from the dashboard', function () {
    $staff = User::factory()->staff()->permanentlySuspended()->googleAuthenticated()->create();

    $response = $this->actingAs($staff)->get('/staff');

    $response->assertForbidden();
});

it('blocks a permanently suspended staff member from creating questions', function () {
    $staff = User::factory()->staff()->permanentlySuspended()->googleAuthenticated()->create();

    $response = $this->actingAs($staff)->get(route('filament.staff.resources.questions.create'));

    $response->assertForbidden();
});
