<?php

use App\Http\Controllers\Api\QuestionSyncController;
use App\Http\Controllers\Api\SchoolConnectionController;
use App\Http\Middleware\AuthenticateQuestionSyncRequest;
use App\Http\Middleware\AuthenticateSchoolInstallation;
use App\Support\QuestionSync;
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

/*
 * What the test server asks the live server for when bringing questions
 * over — only on the live server, only while the sharing is open, every
 * request signed with the shared secret. A chapter's images come one by
 * one, so the limit is generous.
 */
Route::middleware(['throttle:600,1', AuthenticateQuestionSyncRequest::class])
    ->prefix(QuestionSync::PREFIX)
    ->name('api.question-sync.')
    ->controller(QuestionSyncController::class)
    ->group(function (): void {
        Route::get('status', 'status')->name('status');
        Route::get('classes', 'classes')->name('classes');
        Route::get('classes/{class}/subjects', 'subjects')->whereNumber('class')->name('subjects');
        Route::get('class-subjects/{classSubject}/chapters', 'chapters')->whereNumber('classSubject')->name('chapters');
        Route::get('chapters/{chapter}/questions', 'questions')->whereNumber('chapter')->name('questions');
        Route::get('paper-sources/{type}', 'paperSources')->name('paper-sources');
        Route::get('boards/{board}/papers', 'papers')->whereNumber('board')->name('papers');
        Route::get('papers/{paper}', 'paper')->whereNumber('paper')->name('paper');
        Route::get('image', 'image')->name('image');
    });
