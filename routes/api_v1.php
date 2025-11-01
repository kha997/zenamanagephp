<?php

/*
|--------------------------------------------------------------------------
| API v1 Routes (Simplified for Testing)
|--------------------------------------------------------------------------
|
| Simplified API v1 routes for testing architecture fixes
|
*/

use Illuminate\Support\Facades\Route;

// API v1 Routes - Simplified
Route::prefix('v1')->group(function () {
    
    // App API Routes - Tenant-scoped
    Route::prefix('app')->middleware(['auth:sanctum'])->group(function () {
        // Projects API - using existing ProjectManagementController
        Route::apiResource('projects', App\Http\Controllers\Unified\ProjectManagementController::class);
        Route::get('/projects/{project}/documents', [App\Http\Controllers\Unified\ProjectManagementController::class, 'documents']);
        Route::get('/projects/{project}/history', [App\Http\Controllers\Unified\ProjectManagementController::class, 'history']);
        
        // Tasks API
        Route::apiResource('tasks', App\Http\Controllers\Api\TasksController::class);
        Route::post('/tasks/{task}/assign', [App\Http\Controllers\Api\TasksController::class, 'assign']);
        Route::post('/tasks/{task}/unassign', [App\Http\Controllers\Api\TasksController::class, 'unassign']);
        Route::post('/tasks/{task}/progress', [App\Http\Controllers\Api\TasksController::class, 'updateProgress']);
        
        // Clients API
        Route::apiResource('clients', App\Http\Controllers\Api\ClientsController::class);
        Route::patch('/clients/{client}/lifecycle-stage', [App\Http\Controllers\Api\ClientsController::class, 'updateLifecycleStage']);
        
        // Quotes API
        Route::apiResource('quotes', App\Http\Controllers\Api\QuotesController::class);
        Route::post('/quotes/{quote}/send', [App\Http\Controllers\Api\QuotesController::class, 'send']);
        Route::post('/quotes/{quote}/accept', [App\Http\Controllers\Api\QuotesController::class, 'accept']);
        Route::post('/quotes/{quote}/reject', [App\Http\Controllers\Api\QuotesController::class, 'reject']);
        
        // Documents API
        Route::apiResource('documents', App\Http\Controllers\Api\DocumentsController::class);
        Route::get('/documents/approvals', [App\Http\Controllers\Api\DocumentsController::class, 'approvals']);
        
        // Templates API
        Route::apiResource('templates', App\Http\Controllers\Api\TemplatesController::class);
        Route::get('/templates/library', [App\Http\Controllers\Api\TemplatesController::class, 'library']);
        Route::get('/templates/builder', [App\Http\Controllers\Api\TemplatesController::class, 'builder']);
        
        // Dashboard API - using proper middleware
        Route::middleware(['ability:tenant'])->prefix('dashboard')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'index']);
            Route::get('/stats', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getStats']);
            Route::get('/recent-projects', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getRecentProjects']);
            Route::get('/recent-tasks', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getRecentTasks']);
            Route::get('/recent-activity', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getRecentActivity']);
            Route::get('/metrics', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getMetrics']);
            Route::get('/team-status', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getTeamStatus']);
            Route::get('/charts/{type}', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getChartData']);
            Route::get('/alerts', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getAlerts']);
            Route::put('/alerts/{id}/read', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'markAlertAsRead']);
            Route::put('/alerts/read-all', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'markAllAlertsAsRead']);
            Route::get('/widgets', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getAvailableWidgets']);
            Route::get('/widgets/{id}/data', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'getWidgetData']);
            Route::post('/widgets', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'addWidget']);
            Route::delete('/widgets/{id}', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'removeWidget']);
            Route::put('/widgets/{id}', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'updateWidgetConfig']);
            Route::put('/layout', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'updateLayout']);
            Route::post('/preferences', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'saveUserPreferences']);
            Route::post('/reset', [App\Http\Controllers\Api\V1\App\DashboardController::class, 'resetToDefault']);
        });
    });
});