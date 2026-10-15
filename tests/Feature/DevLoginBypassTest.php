<?php

// DEV-LOGIN-BYPASS — delete this file with the rest of it (see DEV-LOGIN-BYPASS.md).

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('does not exist outside a developer\'s machine', function () {
    $teacher = User::factory()->teacher()->create();

    expect(Route::has('dev-login'))->toBeFalse();

    $this->get("/dev-login/{$teacher->id}")->assertNotFound();
    $this->assertGuest();
});

it('refuses to log anyone in even if its route file is loaded on a server that is not local', function () {
    $teacher = User::factory()->teacher()->create();
    Route::middleware('web')->group(base_path('routes/dev.php'));

    $this->get("/dev-login/{$teacher->id}")->assertNotFound();
    $this->assertGuest();
});

it('logs a user into their own panel on a developer\'s machine', function () {
    $teacher = User::factory()->teacher()->create();
    $this->app->detectEnvironment(fn (): string => 'local');
    Route::middleware('web')->group(base_path('routes/dev.php'));

    $this->get("/dev-login/{$teacher->id}")
        ->assertRedirect(Filament::getPanel('teacher')->getUrl());

    $this->assertAuthenticatedAs($teacher);
});
