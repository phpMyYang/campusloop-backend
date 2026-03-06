<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
// Admin
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\StrandController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\AnnouncementController;

// Public Auth Routes (Hindi kailangan ng token)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/resend-verification', [AuthController::class, 'resendVerificationEmail']);
Route::post('/verify-email', [AuthController::class, 'verifyEmail']);

// Protected Routes (Kailangan naka-login/may token bago ma-access)
Route::middleware('auth:sanctum')->group(function () {

    // User Records API
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::post('/users/bulk-delete', [UserController::class, 'bulkDestroy']);

    // Academic Management - Strands
    Route::get('/strands', [StrandController::class, 'index']);
    Route::post('/strands', [StrandController::class, 'store']);
    Route::put('/strands/{id}', [StrandController::class, 'update']);
    Route::delete('/strands/{id}', [StrandController::class, 'destroy']);

    // System Settings
    Route::get('/settings', [SystemSettingController::class, 'index']);
    Route::post('/settings', [SystemSettingController::class, 'store']);
    Route::post('/settings/reset', [SystemSettingController::class, 'reset']);

    // Academic Management - Subjects
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::post('/subjects', [SubjectController::class, 'store']);
    Route::put('/subjects/{id}', [SubjectController::class, 'update']);
    Route::delete('/subjects/{id}', [SubjectController::class, 'destroy']);
    Route::post('/subjects/bulk-delete', [SubjectController::class, 'bulkDelete']);

    // Content Approval - Announcement
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::post('/announcements/bulk-delete', [AnnouncementController::class, 'bulkDelete']);
    Route::put('/announcements/{id}', [AnnouncementController::class, 'update']);
    Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy']);
    
    // Kunin ang current logged-in user data
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Logout Route
    Route::post('/logout', [AuthController::class, 'logout']);
    
});