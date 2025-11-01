<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HealthController;

Route::prefix('_debug')->name('debug.')->middleware('web')->group(function () {
    // Health check endpoints
    Route::get('/health', [HealthController::class, 'health'])->name('health');
    Route::get('/ping', [HealthController::class, 'ping'])->name('ping');
    Route::get('/info', [HealthController::class, 'info'])->name('info');
    
    // Performance monitoring
    Route::get('/performance', function () {
        return response()->json([
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - LARAVEL_START,
        ]);
    })->name('performance');
    
    // Cache management
    Route::get('/clear-cache', function () {
        \Artisan::call('cache:clear');
        \Artisan::call('config:clear');
        \Artisan::call('route:clear');
        \Artisan::call('view:clear');
        
        return response()->json([
            'status' => 'success',
            'message' => 'All caches cleared',
            'timestamp' => now()->toISOString(),
        ]);
    })->name('clear-cache');
    
    // Simple test route
    Route::get('/test-simple', function () {
        return 'Debug route works!';
    })->name('simple');
});