<?php

// routes/api.php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Monitor\MonitorController;
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

    // Monitors
    Route::apiResource('monitors', MonitorController::class);
    Route::get('monitors/{id}/history', [MonitorController::class, 'history']);
});