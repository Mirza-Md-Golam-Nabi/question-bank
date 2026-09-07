<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('lets a suspended teacher still reach the dashboard', function () {
    $teacher = User::factory()->teacher()->suspended()->googleAuthenticated()->create();

    $response = $this->actingAs($teacher)->get('/teacher');

    $response->assertOk();
});

it('blocks a suspended teacher from creating questions', function () {
    $teacher = User::factory()->teacher()->suspended()->googleAuthenticated()->create();

    $response = $this->actingAs($teacher)->get(route('filament.teacher.resources.questions.create'));

    $response->assertForbidden();
});

it('blocks a permanently suspended teacher from the dashboard', function () {
    $teacher = User::factory()->teacher()->permanentlySuspended()->googleAuthenticated()->create();

    $response = $this->actingAs($teacher)->get('/teacher');

    $response->assertForbidden();
});

it('blocks a permanently suspended teacher from creating questions', function () {
    $teacher = User::factory()->teacher()->permanentlySuspended()->googleAuthenticated()->create();

    $response = $this->actingAs($teacher)->get(route('filament.teacher.resources.questions.create'));

    $response->assertForbidden();
});
