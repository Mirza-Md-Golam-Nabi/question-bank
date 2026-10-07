<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\GuestExamController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');

Route::prefix('auth/google')->name('auth.google.')->group(function () {
    Route::get('redirect/{role}', [GoogleAuthController::class, 'redirect'])->name('redirect');
    Route::get('callback', [GoogleAuthController::class, 'callback'])->name('callback');
});

Route::prefix('exam')->name('guest-exam.')->group(function () {
    Route::get('{shareToken}', [GuestExamController::class, 'show'])->name('show');
    Route::post('{shareToken}/start', [GuestExamController::class, 'startGuestAttempt'])
        ->middleware('throttle:5,1')
        ->name('start');
    Route::post('attempt/{attempt}/submit', [GuestExamController::class, 'submit'])
        ->middleware('throttle:5,1')
        ->name('submit');
});
