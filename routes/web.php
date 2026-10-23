<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\GuestExamController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OfferDismissalController;
use App\Http\Controllers\OnboardingDismissalController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\ReferralLinkController;
use App\Http\Controllers\SchoolExamEntryController;
use App\Http\Controllers\SchoolQuestionsController;
use App\Http\Controllers\StudentSharedExamController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');

// The installable app: its manifest, and where it opens.
Route::get('manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('app', [PwaController::class, 'start'])->name('pwa.start');

// A signed-in user's device switching push notifications on or off, and
// closing an offer or the "Get started" list on their dashboard.
Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    Route::post('offers/{offer}/dismiss', OfferDismissalController::class)->name('offers.dismiss');
    Route::post('onboarding/dismiss', OnboardingDismissalController::class)->name('onboarding.dismiss');
});

Route::get('r/{code}', ReferralLinkController::class)
    ->middleware('throttle:30,1')
    ->name('referral.visit');

Route::prefix('auth/google')->name('auth.google.')->group(function () {
    Route::get('redirect/{role}', [GoogleAuthController::class, 'redirect'])->name('redirect');
    Route::get('callback', [GoogleAuthController::class, 'callback'])->name('callback');
});

// The pages a share link opens start in the site's own language (Bangla) and
// keep their own language choice, apart from the panels' in the same browser.
Route::prefix('exam')->name('guest-exam.')->middleware(SetLocale::class.':,exam')->group(function () {
    Route::get('{shareToken}', [GuestExamController::class, 'show'])->name('show');
    Route::post('{shareToken}/start', [GuestExamController::class, 'startGuestAttempt'])
        ->middleware('throttle:5,1')
        ->name('start');
    // A School's exam: its own form (class, section, roll, name) instead.
    Route::post('{shareToken}/school-start', [SchoolExamEntryController::class, 'start'])
        ->middleware('throttle:5,1')
        ->name('school-start');
    // Class + roll is all that guards a School student's result.
    Route::post('{shareToken}/school-result', [SchoolExamEntryController::class, 'findResult'])
        ->middleware('throttle:10,1')
        ->name('school-result');
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

// A School's own questions, read by its students inside the school's
// software through a link it signs; a whole school may be behind one address.
Route::get('school-questions/{code}', SchoolQuestionsController::class)
    ->middleware('throttle:120,1')
    ->name('school-questions.show');

// DEV-LOGIN-BYPASS — a login without Google, on a developer's machine only.
// What it is and how to remove it: DEV-LOGIN-BYPASS.md.
if (app()->environment('local')) {
    require __DIR__.'/dev.php';
}
