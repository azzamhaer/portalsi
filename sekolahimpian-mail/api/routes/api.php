<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// ── Auth (akun sendiri, terpisah dari Portal SI) ──
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgot']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/resend-verification', [AuthController::class, 'resendVerification']);

// ── Tautan dari email (signed, dibuka di browser) ──
Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')->name('verify.email');
Route::get('/email-change/{id}/{t}', [ProfileController::class, 'confirmEmailChange'])
    ->middleware('signed')->name('email.change');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profil
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/photo', [ProfileController::class, 'photo']);
    Route::post('/profile/email', [ProfileController::class, 'requestEmailChange']);

    Route::prefix('mail')->group(function () {
        Route::get('/status', [MailController::class, 'status']);
        Route::post('/unlock', [MailController::class, 'unlock']);
        Route::get('/account', [MailController::class, 'account']);
        Route::post('/account', [MailController::class, 'createAccount']);
        Route::get('/credentials', [MailController::class, 'credentials']);
        Route::post('/avatars', [MailController::class, 'avatars']);
    });
});
