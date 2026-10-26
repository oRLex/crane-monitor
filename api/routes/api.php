<?php

use App\Http\Controllers\AlarmController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CraneController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['ok' => true]));
Route::get('/auth/users', [AuthController::class, 'users']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('scope:cranes:read')->group(function () {
        Route::get('/cranes', [CraneController::class, 'index']);
        Route::get('/cranes/{crane}', [CraneController::class, 'show']);
    });
    Route::middleware('scope:telemetry:read')->group(function () {
        Route::get('/cranes/{crane}/telemetry', [CraneController::class, 'telemetry']);
        Route::get('/realtime/snapshot', [CraneController::class, 'snapshot']);
    });

    Route::get('/alarms', [AlarmController::class, 'index'])->middleware('scope:alarms:read');
    Route::post('/alarms/{alarm}/ack', [AlarmController::class, 'acknowledge'])->middleware('scope:alarms:ack');
});
