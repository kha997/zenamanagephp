<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes (With Proper Middleware)
|--------------------------------------------------------------------------
*/

// API v1 Routes
Route::prefix('v1')->group(function () {
    
    // Admin API Routes - Protected by auth:sanctum + ability:admin
    Route::prefix('admin')->middleware(['auth:sanctum', 'ability:admin'])->group(function () {
        // Security API
        Route::get('/security/audit', [App\Http\Controllers\Api\Admin\SecurityController::class, 'audit']);
        Route::get('/security/logs', [App\Http\Controllers\Api\Admin\SecurityController::class, 'logs']);
    });
    
    // App API Routes - Protected by auth:sanctum + ability:tenant
    Route::prefix('app')->middleware(['auth:sanctum', 'ability:tenant'])->group(function () {
        // Dashboard API
        Route::get('dashboard/stats', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getStats']);
        Route::get('dashboard/recent-projects', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getRecentProjects']);
        Route::get('dashboard/recent-tasks', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getRecentTasks']);
        Route::get('dashboard/recent-activity', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getRecentActivity']);
        Route::get('dashboard/metrics', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getMetrics']);
        Route::get('dashboard/team-status', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getTeamStatus']);
        Route::get('dashboard/charts/{type}', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getChartData']);
        
        // Calendar API
        Route::apiResource('calendar', App\Http\Controllers\Api\V1\App\CalendarController::class);
        Route::get('calendar/stats', [App\Http\Controllers\Api\V1\App\CalendarController::class, 'getStats']);
        
        // Team API
        Route::apiResource('team', App\Http\Controllers\Api\V1\App\TeamController::class);
        Route::get('team/stats', [App\Http\Controllers\Api\V1\App\TeamController::class, 'getStats']);
        Route::post('team/invite', [App\Http\Controllers\Api\V1\App\TeamController::class, 'invite']);
        
        // Documents API
        Route::apiResource('documents', App\Http\Controllers\Api\V1\App\DocumentsController::class);
        Route::get('documents/{document}/download', [App\Http\Controllers\Api\V1\App\DocumentsController::class, 'download']);
        Route::get('documents/stats', [App\Http\Controllers\Api\V1\App\DocumentsController::class, 'getStats']);
        
        // Settings API
        Route::get('settings', [App\Http\Controllers\Api\V1\App\SettingsController::class, 'index']);
        Route::put('settings/general', [App\Http\Controllers\Api\V1\App\SettingsController::class, 'updateGeneral']);
        Route::put('settings/notifications', [App\Http\Controllers\Api\V1\App\SettingsController::class, 'updateNotifications']);
        Route::put('settings/security', [App\Http\Controllers\Api\V1\App\SettingsController::class, 'updateSecurity']);
        Route::put('settings/privacy', [App\Http\Controllers\Api\V1\App\SettingsController::class, 'updatePrivacy']);
        Route::put('settings/integrations', [App\Http\Controllers\Api\V1\App\SettingsController::class, 'updateIntegrations']);
        Route::get('settings/stats', [App\Http\Controllers\Api\V1\App\SettingsController::class, 'getStats']);
        Route::post('settings/export-data', [App\Http\Controllers\Api\V1\App\SettingsController::class, 'exportData']);
        Route::delete('settings/delete-data', [App\Http\Controllers\Api\V1\App\SettingsController::class, 'deleteData']);
        
        // Templates API
        Route::apiResource('templates', App\Http\Controllers\Api\App\TemplateController::class);
        
        // Projects API
        Route::apiResource('projects', App\Http\Controllers\Api\App\ProjectController::class);
        
        // Clients API
        Route::apiResource('clients', App\Http\Controllers\Api\V1\App\ClientController::class);
        Route::get('clients/{client}/stats', [App\Http\Controllers\Api\V1\App\ClientController::class, 'getStats']);
        Route::patch('clients/{client}/lifecycle-stage', [App\Http\Controllers\Api\V1\App\ClientController::class, 'updateLifecycleStage']);
        
        // Quotes API
        Route::apiResource('quotes', App\Http\Controllers\Api\V1\App\QuoteController::class);
        Route::post('quotes/{quote}/send', [App\Http\Controllers\Api\V1\App\QuoteController::class, 'send']);
        Route::post('quotes/{quote}/accept', [App\Http\Controllers\Api\V1\App\QuoteController::class, 'accept']);
        Route::post('quotes/{quote}/reject', [App\Http\Controllers\Api\V1\App\QuoteController::class, 'reject']);
        Route::get('quotes/stats', [App\Http\Controllers\Api\V1\App\QuoteController::class, 'getStats']);
    });
    
    // Public API Routes - No auth required
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
