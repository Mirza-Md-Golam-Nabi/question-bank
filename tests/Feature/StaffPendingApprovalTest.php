<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('redirects a pending staff member to the approval notice instead of the dashboard', function () {
    $staff = User::factory()->staff()->pendingApproval()->googleAuthenticated()->create();

    $response = $this->actingAs($staff)->get('/staff');

    $response->assertRedirect(route('filament.staff.pages.pending-approval'));
});

it('lets an active staff member reach the dashboard normally', function () {
    $staff = User::factory()->staff()->googleAuthenticated()->create();

    $response = $this->actingAs($staff)->get('/staff');

    $response->assertOk();
});

it('lets a pending staff member view the approval notice page itself', function () {
    $staff = User::factory()->staff()->pendingApproval()->googleAuthenticated()->create();

    $response = $this->actingAs($staff)->get(route('filament.staff.pages.pending-approval'));

    $response->assertOk();
});
