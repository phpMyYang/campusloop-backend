<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

// Public Auth Routes (Hindi kailangan ng token)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/resend-verification', [AuthController::class, 'resendVerificationEmail']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);

// Protected Routes (Kailangan naka-login/may token bago ma-access)
Route::middleware('auth:sanctum')->group(function () {
    
    // Kunin ang current logged-in user data
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Logout Route
    Route::post('/logout', [AuthController::class, 'logout']);
    
});