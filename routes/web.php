<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\GuestExamController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\StudentSharedExamController;
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
    // "Login to attempt": through Google login if needed, then into the exam.
    Route::get('{shareToken}/join', StudentSharedExamController::class)
        ->middleware('throttle:30,1')
        ->name('join');
    // Name + phone/email is all that guards a guest's result, so guessing
    // at it is kept slow.
    Route::post('{shareToken}/result', [GuestExamController::class, 'findResult'])
        ->middleware('throttle:10,1')
        ->name('result');
    Route::get('attempt/{attempt}', [GuestExamController::class, 'take'])
        ->middleware('throttle:60,1')
        ->name('take');
    // Fired by every exam page at the same instant its clock runs out, so a
    // whole classroom behind one IP address has to fit under this limit.
    Route::post('attempt/{attempt}/answers', [GuestExamController::class, 'saveAnswers'])
        ->middleware('throttle:60,1')
        ->name('answers');
    Route::post('attempt/{attempt}/submit', [GuestExamController::class, 'submit'])
        ->middleware('throttle:5,1')
        ->name('submit');
});
