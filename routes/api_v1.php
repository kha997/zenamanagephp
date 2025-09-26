<?php

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API v1 routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "api" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Route;

// API v1 Routes
Route::prefix('v1')->group(function () {
    
    // Admin API Routes - Super Admin only
    Route::prefix('admin')->middleware(['auth:sanctum', 'ability:admin', \App\Http\Middleware\ComprehensiveRateLimitMiddleware::class . ':admin'])->group(function () {
        Route::get('/dashboard/stats', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getStats']);
        Route::get('/dashboard/activities', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getActivities']);
        Route::get('/dashboard/alerts', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getAlerts']);
        Route::get('/dashboard/metrics', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getMetrics']);
        
        // Admin Management APIs
        Route::apiResource('users', App\Http\Controllers\Api\Admin\UserController::class);
        Route::apiResource('tenants', App\Http\Controllers\Api\Admin\TenantController::class);
        Route::get('/security/audit', [App\Http\Controllers\Api\Admin\SecurityController::class, 'audit']);
        Route::get('/security/logs', [App\Http\Controllers\Api\Admin\SecurityController::class, 'logs']);
        Route::apiResource('alerts', App\Http\Controllers\Api\Admin\AlertController::class);
        Route::get('/activities/logs', [App\Http\Controllers\Api\Admin\ActivityController::class, 'logs']);
        Route::get('/activities/audit', [App\Http\Controllers\Api\Admin\ActivityController::class, 'audit']);
        
        // Performance & Monitoring API
        Route::get('/perf/metrics', [App\Http\Controllers\PerformanceController::class, 'metrics']);
        Route::get('/perf/health', [App\Http\Controllers\PerformanceController::class, 'health']);
        Route::post('/perf/clear-caches', [App\Http\Controllers\PerformanceController::class, 'clearCaches']);
        
        // Observability & Monitoring API
        Route::get('/monitoring/metrics', [App\Http\Controllers\MonitoringController::class, 'metrics']);
        Route::get('/monitoring/health', [App\Http\Controllers\MonitoringController::class, 'health']);
        Route::get('/monitoring/performance', [App\Http\Controllers\MonitoringController::class, 'performance']);
        Route::get('/monitoring/historical', [App\Http\Controllers\MonitoringController::class, 'historical']);
        Route::get('/monitoring/logs', [App\Http\Controllers\MonitoringController::class, 'logs']);
        
        // Enhanced Health Check API (Admin)
        Route::get('/health/comprehensive', [App\Http\Controllers\HealthCheckController::class, 'comprehensive']);
        Route::get('/health/database', [App\Http\Controllers\HealthCheckController::class, 'database']);
        Route::get('/health/cache', [App\Http\Controllers\HealthCheckController::class, 'cache']);
        Route::get('/health/storage', [App\Http\Controllers\HealthCheckController::class, 'storage']);
        Route::get('/health/system', [App\Http\Controllers\HealthCheckController::class, 'system']);
        
        // Schema Audit API (Admin)
        Route::get('/schema/audit', [App\Http\Controllers\SchemaAuditController::class, 'audit']);
        Route::get('/schema/audit/documents', [App\Http\Controllers\SchemaAuditController::class, 'documents']);
        Route::get('/schema/audit/document-versions', [App\Http\Controllers\SchemaAuditController::class, 'documentVersions']);
        Route::get('/schema/audit/project-activities', [App\Http\Controllers\SchemaAuditController::class, 'projectActivities']);
        Route::get('/schema/audit/audit-logs', [App\Http\Controllers\SchemaAuditController::class, 'auditLogs']);
        Route::get('/schema/audit/recommendations', [App\Http\Controllers\SchemaAuditController::class, 'recommendations']);
        Route::get('/schema/audit/performance', [App\Http\Controllers\SchemaAuditController::class, 'performance']);
        
        // N+1 & Indexing Audit API (Admin)
        Route::get('/audit/n1-indexing', [App\Http\Controllers\N1IndexingAuditController::class, 'audit']);
        Route::get('/audit/n1-indexing/n1-analysis', [App\Http\Controllers\N1IndexingAuditController::class, 'n1Analysis']);
        Route::get('/audit/n1-indexing/indexing-analysis', [App\Http\Controllers\N1IndexingAuditController::class, 'indexingAnalysis']);
        Route::get('/audit/n1-indexing/query-performance', [App\Http\Controllers\N1IndexingAuditController::class, 'queryPerformance']);
        Route::get('/audit/n1-indexing/recommendations', [App\Http\Controllers\N1IndexingAuditController::class, 'recommendations']);
        Route::get('/audit/n1-indexing/optimization-plan', [App\Http\Controllers\N1IndexingAuditController::class, 'optimizationPlan']);
    });
    
    // App API Routes - Tenant users only
    Route::prefix('app')->middleware(['auth:sanctum', 'ability:tenant', 'tenant.scope', \App\Http\Middleware\ComprehensiveRateLimitMiddleware::class . ':app'])->group(function () {
        // Sidebar API
        Route::get('/sidebar/config', [App\Http\Controllers\Api\App\SidebarController::class, 'getConfig']);
        Route::get('/sidebar/badges', [App\Http\Controllers\Api\App\SidebarController::class, 'getBadges']);
        Route::get('/sidebar/default/{role}', [App\Http\Controllers\Api\App\SidebarController::class, 'getDefault']);
        
        // Dashboard API
        Route::get('/dashboard/stats', [App\Http\Controllers\Api\App\DashboardController::class, 'getStats']);
        Route::get('/dashboard/activities', [App\Http\Controllers\Api\App\DashboardController::class, 'getActivities']);
        
        // Projects API
        Route::apiResource('projects', App\Http\Controllers\Api\App\ProjectController::class);
        Route::get('/projects/{project}/documents', [App\Http\Controllers\Api\App\ProjectController::class, 'documents']);
        Route::get('/projects/{project}/history', [App\Http\Controllers\Api\App\ProjectController::class, 'history']);
        Route::get('/projects/{project}/design', [App\Http\Controllers\Api\App\ProjectController::class, 'design']);
        Route::get('/projects/{project}/construction', [App\Http\Controllers\Api\App\ProjectController::class, 'construction']);
        
        // Tasks API with business actions
        Route::apiResource('tasks', App\Http\Controllers\Api\App\TaskController::class);
        Route::patch('tasks/{id}/move', [App\Http\Controllers\Api\App\TaskController::class, 'move']);
        Route::patch('tasks/{id}/archive', [App\Http\Controllers\Api\App\TaskController::class, 'archive']);
        Route::get('tasks/{task}/documents', [App\Http\Controllers\Api\App\TaskController::class, 'documents']);
        Route::get('tasks/{task}/history', [App\Http\Controllers\Api\App\TaskController::class, 'history']);
        
        // Documents API
        Route::apiResource('documents', App\Http\Controllers\Api\App\DocumentController::class);
        Route::get('/documents/approvals', [App\Http\Controllers\Api\App\DocumentController::class, 'approvals']);
        
        // Team API
        Route::apiResource('team', App\Http\Controllers\Api\App\TeamController::class);
        Route::post('/team/invite', [App\Http\Controllers\Api\App\TeamController::class, 'invite']);
        
        // Templates API
        Route::apiResource('templates', App\Http\Controllers\Api\App\TemplateController::class);
        
        // Settings API
        Route::get('/settings', [App\Http\Controllers\Api\App\SettingsController::class, 'index']);
        Route::patch('/settings', [App\Http\Controllers\Api\App\SettingsController::class, 'update']);
        Route::get('/settings/general', [App\Http\Controllers\Api\App\SettingsController::class, 'general']);
        Route::patch('/settings/general', [App\Http\Controllers\Api\App\SettingsController::class, 'updateGeneral']);
        Route::get('/settings/security', [App\Http\Controllers\Api\App\SettingsController::class, 'security']);
        Route::patch('/settings/security', [App\Http\Controllers\Api\App\SettingsController::class, 'updateSecurity']);
        Route::get('/settings/notifications', [App\Http\Controllers\Api\App\SettingsController::class, 'notifications']);
        Route::patch('/settings/notifications', [App\Http\Controllers\Api\App\SettingsController::class, 'updateNotifications']);
    });
    
    // Auth API Routes
    Route::prefix('auth')->middleware([\App\Http\Middleware\ComprehensiveRateLimitMiddleware::class . ':auth'])->group(function () {
        Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
        Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout'])->middleware('auth');
        Route::post('/refresh', [App\Http\Controllers\Api\AuthController::class, 'refresh'])->middleware('auth');
        Route::get('/me', [App\Http\Controllers\Api\AuthController::class, 'me'])->middleware('auth');
    });
    
    // Invitation API Routes
    Route::prefix('invitations')->middleware([\App\Http\Middleware\ComprehensiveRateLimitMiddleware::class . ':invitations'])->group(function () {
        Route::get('/accept/{token}', [App\Http\Controllers\Api\InvitationController::class, 'accept']);
        Route::post('/accept/{token}', [App\Http\Controllers\Api\InvitationController::class, 'processAcceptance']);
    });
    
    // Public API Routes (no authentication required)
    Route::prefix('public')->middleware([\App\Http\Middleware\ComprehensiveRateLimitMiddleware::class . ':public'])->group(function () {
        Route::get('/health', function () {
            return response()->json([
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'version' => '1.0.0'
            ]);
        });
        
        Route::get('/status', function () {
            return response()->json([
                'status' => 'online',
                'environment' => app()->environment(),
                'debug' => config('app.debug'),
                'timestamp' => now()->toISOString()
            ]);
        });
        
        // Enhanced Health Check Endpoints
        Route::get('/health/basic', [App\Http\Controllers\HealthCheckController::class, 'basic']);
        Route::get('/health/readiness', [App\Http\Controllers\HealthCheckController::class, 'readiness']);
        Route::get('/health/liveness', [App\Http\Controllers\HealthCheckController::class, 'liveness']);
        Route::get('/health/status', [App\Http\Controllers\HealthCheckController::class, 'status']);
    });
});
