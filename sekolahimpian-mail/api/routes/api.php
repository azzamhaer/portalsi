<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MailController;
use Illuminate\Support\Facades\Route;

// ── Auth (akun sendiri, terpisah dari Portal SI) ──
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgot']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('mail')->group(function () {
        Route::get('/status', [MailController::class, 'status']);
        Route::post('/unlock', [MailController::class, 'unlock']);
        Route::get('/account', [MailController::class, 'account']);
        Route::post('/account', [MailController::class, 'createAccount']);
        Route::get('/credentials', [MailController::class, 'credentials']);
    });
});
