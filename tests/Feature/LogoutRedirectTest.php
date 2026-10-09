<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('sends a user of a google-login panel to the home page after logging out', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    $this->post(route("filament.{$role}.auth.logout"))->assertRedirect(route('home'));

    $this->assertGuest();
})->with(['teacher', 'student', 'staff']);

it('sends an admin back to the admin login after logging out', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('filament.admin.auth.logout'))->assertRedirect(route('filament.admin.auth.login'));

    $this->assertGuest();
});
