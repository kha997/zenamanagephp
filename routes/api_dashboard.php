<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;

/*
|--------------------------------------------------------------------------
| Dashboard API Routes
|--------------------------------------------------------------------------
|
| API routes cho Dashboard System
| Tất cả routes đều yêu cầu authentication và tenant isolation
|
*/

Route::middleware(['auth:sanctum', 'tenant.isolation', 'rbac'])->group(function () {
    
    // Dashboard chính
    Route::get('/dashboard', [DashboardController::class, 'getUserDashboard']);
    Route::get('/dashboard/template', [DashboardController::class, 'getDashboardTemplate']);
    Route::post('/dashboard/reset', [DashboardController::class, 'resetDashboard']);
    
    // Widgets
    Route::get('/dashboard/widgets', [DashboardController::class, 'getAvailableWidgets']);
    Route::get('/dashboard/widgets/{widgetId}/data', [DashboardController::class, 'getWidgetData']);
    Route::post('/dashboard/widgets', [DashboardController::class, 'addWidget']);
    Route::delete('/dashboard/widgets/{widgetId}', [DashboardController::class, 'removeWidget']);
    Route::put('/dashboard/widgets/{widgetId}/config', [DashboardController::class, 'updateWidgetConfig']);
    
    // Layout
    Route::put('/dashboard/layout', [DashboardController::class, 'updateDashboardLayout']);
    
    // Alerts
    Route::get('/dashboard/alerts', [DashboardController::class, 'getUserAlerts']);
    Route::put('/dashboard/alerts/{alertId}/read', [DashboardController::class, 'markAlertAsRead']);
    Route::put('/dashboard/alerts/read-all', [DashboardController::class, 'markAllAlertsAsRead']);
    
    // Metrics
    Route::get('/dashboard/metrics', [DashboardController::class, 'getDashboardMetrics']);
    
    // Preferences
    Route::post('/dashboard/preferences', [DashboardController::class, 'saveUserPreferences']);
    
});
