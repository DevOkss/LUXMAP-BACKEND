<?php

use App\Http\Controllers\Auth\AdminSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\OfficerSessionController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    // Officer portal login (student ID number + password).
    Route::get('login', [OfficerSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [OfficerSessionController::class, 'store']);

    // Admin/head portal login (email + password).
    Route::get('admin/login', [AdminSessionController::class, 'create'])
        ->name('admin.login');

    Route::post('admin/login', [AdminSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::post('logout', [AdminSessionController::class, 'destroy'])
        ->name('logout');
});
