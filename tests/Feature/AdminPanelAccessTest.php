<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('has no public registration route on the admin panel', function () {
    $this->get('/admin/register')->assertNotFound();
});

it('only allows admin and super admin roles into the admin panel', function () {
    $admin = User::factory()->admin()->create();
    $teacher = User::factory()->teacher()->googleAuthenticated()->create();

    $this->actingAs($admin)->get('/admin')->assertOk();
    $this->actingAs($teacher)->get('/admin')->assertForbidden();
});

it('only allows the matching role into each panel', function () {
    $teacher = User::factory()->teacher()->googleAuthenticated()->create();
    $staff = User::factory()->staff()->googleAuthenticated()->create();
    $student = User::factory()->student()->googleAuthenticated()->create();

    $this->actingAs($teacher)->get('/staff')->assertForbidden();
    $this->actingAs($staff)->get('/student')->assertForbidden();
    $this->actingAs($student)->get('/teacher')->assertForbidden();
});
