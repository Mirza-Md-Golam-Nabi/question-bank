<?php

/*
|--------------------------------------------------------------------------
| DEV-LOGIN-BYPASS — local machine only, safe to delete
|--------------------------------------------------------------------------
|
| Logs in as any user without Google, so the Teacher / Staff / Student
| panels (Google-only by CLAUDE.md rule 1) can be opened in a browser on a
| developer's machine to check a change. It does not exist anywhere but on
| APP_ENV=local: routes/web.php only loads this file there, and the route
| checks again itself.
|
| Every part of it, inside and outside this repository, and how to remove
| all of it, is listed in one place: DEV-LOGIN-BYPASS.md at the repository
| root. Keep that list the only one.
|
*/

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('dev-login/{user}', function (User $user): RedirectResponse {
    abort_unless(app()->environment('local'), 404);

    // A fresh session, so nothing of whoever was logged in before (such as
    // the admin's password check kept by AuthenticateSession) logs the new
    // user straight back out.
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    Auth::login($user);

    return redirect()->to(Filament::getPanel($user->role->panelId())->getUrl());
})->name('dev-login');
