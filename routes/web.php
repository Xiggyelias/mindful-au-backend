<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HealthController::class, 'root']);

// Dokploy health endpoint alias (readiness check includes DB/cache/queue/disk).
Route::get('/health', [HealthController::class, 'ready']);
Route::get('/live', [HealthController::class, 'health']);








