<?php

// routes/api.php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Monitor\MonitorController;
use App\Http\Controllers\NotificationChannel\NotificationChannelController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Public Routes
// ─────────────────────────────────────────────

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// ─────────────────────────────────────────────
// Protected Routes
// ─────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Monitors
    Route::apiResource('monitors', MonitorController::class);
    Route::get('monitors/{id}/history', [MonitorController::class, 'history']);
    Route::get('monitors/{id}/incidents', [MonitorController::class, 'incidents']);
    Route::get('monitors/{id}/response-times', [MonitorController::class, 'responseTimes']);
    Route::post('monitors/{id}/pause', [MonitorController::class, 'pause']);
    Route::post('monitors/{id}/resume', [MonitorController::class, 'resume']);

    // Notification Channels
    Route::get('monitors/{monitorId}/channels', [NotificationChannelController::class, 'index']);
    Route::post('monitors/{monitorId}/channels', [NotificationChannelController::class, 'store']);
    Route::delete('monitors/{monitorId}/channels/{channelId}', [NotificationChannelController::class, 'destroy']);
});