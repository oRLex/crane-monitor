<?php

use App\Http\Controllers\AiUsageController;
use App\Http\Controllers\AlarmController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CraneController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['ok' => true]));
Route::get('/auth/users', [AuthController::class, 'users']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');

Route::middleware(['auth:sanctum', 'tenant', 'throttle:api'])->group(function () {
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

    Route::middleware('scope:ai:use')->prefix('assistant')->group(function () {
        Route::get('/conversations', [AssistantController::class, 'index']);
        Route::post('/conversations', [AssistantController::class, 'store']);
        Route::get('/conversations/{id}', [AssistantController::class, 'show']);
        Route::post('/conversations/{id}/messages', [AssistantController::class, 'message'])->middleware('throttle:ai');
    });

    Route::middleware('scope:docs:read')->prefix('knowledge')->group(function () {
        Route::get('/documents', [KnowledgeController::class, 'index']);
        Route::get('/documents/{document}', [KnowledgeController::class, 'show']);
        Route::get('/search', [KnowledgeController::class, 'search']);
        Route::post('/documents', [KnowledgeController::class, 'store'])->middleware('scope:docs:write');
        Route::delete('/documents/{document}', [KnowledgeController::class, 'destroy'])->middleware('scope:docs:write');
    });

    Route::middleware('scope:telemetry:read')->prefix('reports')->group(function () {
        Route::get('/', [ReportController::class, 'index']);
        Route::get('/{report}', [ReportController::class, 'show']);
        Route::post('/', [ReportController::class, 'store'])->middleware(['scope:reports:write', 'throttle:ai']);
    });

    Route::get('/ai/usage', AiUsageController::class)->middleware('scope:ai:admin');
});
