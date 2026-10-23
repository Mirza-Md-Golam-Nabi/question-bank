<?php

use App\Http\Controllers\Api\SchoolConnectionController;
use App\Http\Middleware\AuthenticateSchoolInstallation;
use Illuminate\Support\Facades\Route;

/*
 * What a school's installation of the school management software calls —
 * every request signed with its School's secret.
 */
Route::middleware(['throttle:30,1', AuthenticateSchoolInstallation::class])
    ->prefix('school')
    ->name('api.school.')
    ->group(function (): void {
        Route::post('connect', SchoolConnectionController::class)->name('connect');
    });
