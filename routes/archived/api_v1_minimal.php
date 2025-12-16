<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes (Minimal - Only existing controllers)
|--------------------------------------------------------------------------
*/

// API v1 Routes
Route::prefix('v1')->group(function () {
    
    // Admin API Routes - Super Admin only
    Route::prefix('admin')->middleware(['auth:sanctum', 'ability:admin'])->group(function () {
        // Security API (controllers exist)
        Route::get('/security/audit', [App\Http\Controllers\Api\Admin\SecurityController::class, 'audit']);
        Route::get('/security/logs', [App\Http\Controllers\Api\Admin\SecurityController::class, 'logs']);
    });
    
    // App API Routes - Tenant-scoped
    Route::prefix('app')->middleware(['auth:sanctum', 'ability:tenant'])->group(function () {
        // Templates API (controller exists)
        Route::apiResource('templates', App\Http\Controllers\Api\App\TemplateController::class);
        
        // Projects API (controller exists)
        Route::apiResource('projects', App\Http\Controllers\Api\App\ProjectController::class);
    });
    
    // Public API Routes
    Route::prefix('public')->group(function () {
        // Health check endpoint
        Route::get('/health', function () {
            return response()->json([
                'status' => 'ok',
                'timestamp' => now()->toISOString(),
                'version' => '1.0'
            ]);
        });
    });
});
