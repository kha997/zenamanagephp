<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;

/*
|--------------------------------------------------------------------------
| Health Check Routes
|--------------------------------------------------------------------------
|
| These routes are used for health monitoring and Kubernetes probes.
| They should be lightweight and not require authentication.
|
*/

// Basic health check
Route::get('/health', [HealthController::class, 'index']);

// Detailed health check with all services
Route::get('/health/detailed', [HealthController::class, 'detailed']);

// Kubernetes readiness probe
Route::get('/health/readiness', [HealthController::class, 'readiness']);

// Kubernetes liveness probe
Route::get('/health/liveness', [HealthController::class, 'liveness']);

// Prometheus metrics endpoint
Route::get('/metrics', [HealthController::class, 'metrics']);

// API health check
Route::get('/api/health', [HealthController::class, 'index']);

// API detailed health check
Route::get('/api/health/detailed', [HealthController::class, 'detailed']);