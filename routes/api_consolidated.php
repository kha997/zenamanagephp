<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Consolidated Dashboard API Routes
|--------------------------------------------------------------------------
|
| This file contains all dashboard-related API routes in a clean,
| non-duplicate structure. All routes follow the /api/v1/ pattern
| with proper middleware and authentication.
|
*/

// Admin Dashboard API Routes (Super Admin only)
Route::prefix('api/v1/admin')->middleware(['auth:sanctum', 'ability:admin', \App\Http\Middleware\ComprehensiveRateLimitMiddleware::class . ':admin'])->group(function () {
    
    // Dashboard Core APIs
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getStats']);
        Route::get('/metrics', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getMetrics']);
        Route::get('/activities', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getActivities']);
        Route::get('/alerts', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getAlerts']);
        Route::get('/performance', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getPerformanceMetrics']);
    });
    
    // Admin Management APIs
    Route::apiResource('users', App\Http\Controllers\Api\Admin\UserController::class);
    Route::apiResource('tenants', App\Http\Controllers\Api\Admin\TenantController::class);
    Route::apiResource('alerts', App\Http\Controllers\Api\Admin\AlertController::class);
    
    // Security & Audit APIs
    Route::prefix('security')->group(function () {
        Route::get('/audit', [App\Http\Controllers\Api\Admin\SecurityController::class, 'audit']);
        Route::get('/logs', [App\Http\Controllers\Api\Admin\SecurityController::class, 'logs']);
    });
    
    // Activity & Monitoring APIs
    Route::prefix('activities')->group(function () {
        Route::get('/logs', [App\Http\Controllers\Api\Admin\ActivityController::class, 'logs']);
        Route::get('/audit', [App\Http\Controllers\Api\Admin\ActivityController::class, 'audit']);
    });
    
    // Performance & Health APIs
    Route::prefix('perf')->group(function () {
        Route::get('/metrics', [App\Http\Controllers\PerformanceController::class, 'metrics']);
        Route::get('/health', [App\Http\Controllers\PerformanceController::class, 'health']);
        Route::post('/clear-caches', [App\Http\Controllers\PerformanceController::class, 'clearCaches']);
    });
    
    // Monitoring APIs
    Route::prefix('monitoring')->group(function () {
        Route::get('/metrics', [App\Http\Controllers\MonitoringController::class, 'metrics']);
        Route::get('/health', [App\Http\Controllers\MonitoringController::class, 'health']);
        Route::get('/performance', [App\Http\Controllers\MonitoringController::class, 'performance']);
        Route::get('/historical', [App\Http\Controllers\MonitoringController::class, 'historical']);
        Route::get('/logs', [App\Http\Controllers\MonitoringController::class, 'logs']);
    });
    
    // Enhanced Health Check APIs
    Route::prefix('health')->group(function () {
        Route::get('/comprehensive', [App\Http\Controllers\HealthCheckController::class, 'comprehensive']);
        Route::get('/database', [App\Http\Controllers\HealthCheckController::class, 'database']);
        Route::get('/cache', [App\Http\Controllers\HealthCheckController::class, 'cache']);
        Route::get('/storage', [App\Http\Controllers\HealthCheckController::class, 'storage']);
        Route::get('/system', [App\Http\Controllers\HealthCheckController::class, 'system']);
    });
    
    // Schema Audit APIs
    Route::prefix('schema')->group(function () {
        Route::get('/audit', [App\Http\Controllers\SchemaAuditController::class, 'audit']);
        Route::get('/audit/documents', [App\Http\Controllers\SchemaAuditController::class, 'documents']);
        Route::get('/audit/document-versions', [App\Http\Controllers\SchemaAuditController::class, 'documentVersions']);
        Route::get('/audit/project-activities', [App\Http\Controllers\SchemaAuditController::class, 'projectActivities']);
        Route::get('/audit/audit-logs', [App\Http\Controllers\SchemaAuditController::class, 'auditLogs']);
        Route::get('/audit/recommendations', [App\Http\Controllers\SchemaAuditController::class, 'recommendations']);
        Route::get('/audit/performance', [App\Http\Controllers\SchemaAuditController::class, 'performance']);
    });
    
    // N+1 & Indexing Audit APIs
    Route::prefix('audit')->group(function () {
        Route::get('/n1-indexing', [App\Http\Controllers\N1IndexingAuditController::class, 'audit']);
        Route::get('/n1-indexing/n1-analysis', [App\Http\Controllers\N1IndexingAuditController::class, 'n1Analysis']);
        Route::get('/n1-indexing/indexing-analysis', [App\Http\Controllers\N1IndexingAuditController::class, 'indexingAnalysis']);
        Route::get('/n1-indexing/query-performance', [App\Http\Controllers\N1IndexingAuditController::class, 'queryPerformance']);
        Route::get('/n1-indexing/recommendations', [App\Http\Controllers\N1IndexingAuditController::class, 'recommendations']);
        Route::get('/n1-indexing/optimization-plan', [App\Http\Controllers\N1IndexingAuditController::class, 'optimizationPlan']);
    });
});

// App Dashboard API Routes (Tenant users only)
Route::prefix('api/v1/app')->middleware(['auth:sanctum', 'ability:tenant', 'tenant.scope', \App\Http\Middleware\ComprehensiveRateLimitMiddleware::class . ':app'])->group(function () {
    
    // Dashboard Core APIs
    Route::prefix('dashboard')->group(function () {
        Route::get('/metrics', [App\Http\Controllers\Api\App\DashboardController::class, 'getMetrics']);
        Route::get('/stats', [App\Http\Controllers\Api\App\DashboardController::class, 'getStats']);
        Route::get('/activities', [App\Http\Controllers\Api\App\DashboardController::class, 'getActivities']);
        Route::get('/alerts', [App\Http\Controllers\Api\App\DashboardController::class, 'getAlerts']);
        Route::get('/notifications', [App\Http\Controllers\Api\App\DashboardController::class, 'getNotifications']);
        Route::put('/preferences', [App\Http\Controllers\Api\App\DashboardController::class, 'updatePreferences']);
        Route::get('/preferences', [App\Http\Controllers\Api\App\DashboardController::class, 'getPreferences']);
    });
    
    // Sidebar APIs
    Route::prefix('sidebar')->group(function () {
        Route::get('/config', [App\Http\Controllers\Api\App\SidebarController::class, 'getConfig']);
        Route::get('/badges', [App\Http\Controllers\Api\App\SidebarController::class, 'getBadges']);
        Route::get('/default/{role}', [App\Http\Controllers\Api\App\SidebarController::class, 'getDefault']);
    });
    
    // Projects APIs
    Route::apiResource('projects', App\Http\Controllers\Api\App\ProjectController::class);
    Route::prefix('projects')->group(function () {
        Route::get('/{project}/documents', [App\Http\Controllers\Api\App\ProjectController::class, 'documents']);
        Route::get('/{project}/history', [App\Http\Controllers\Api\App\ProjectController::class, 'history']);
        Route::get('/{project}/design', [App\Http\Controllers\Api\App\ProjectController::class, 'design']);
        Route::get('/{project}/construction', [App\Http\Controllers\Api\App\ProjectController::class, 'construction']);
    });
    
    // Tasks APIs with business actions
    Route::apiResource('tasks', App\Http\Controllers\Api\App\TaskController::class);
    Route::prefix('tasks')->group(function () {
        Route::patch('/{id}/move', [App\Http\Controllers\Api\App\TaskController::class, 'move']);
        Route::patch('/{id}/archive', [App\Http\Controllers\Api\App\TaskController::class, 'archive']);
        Route::get('/{task}/documents', [App\Http\Controllers\Api\App\TaskController::class, 'documents']);
        Route::get('/{task}/history', [App\Http\Controllers\Api\App\TaskController::class, 'history']);
    });
    
    // Documents APIs
    Route::apiResource('documents', App\Http\Controllers\Api\App\DocumentController::class);
    Route::prefix('documents')->group(function () {
        Route::get('/approvals', [App\Http\Controllers\Api\App\DocumentController::class, 'approvals']);
    });
    
    // Team APIs
    Route::apiResource('team', App\Http\Controllers\Api\App\TeamController::class);
    Route::prefix('team')->group(function () {
        Route::post('/invite', [App\Http\Controllers\Api\App\TeamController::class, 'invite']);
    });
    
    // Templates APIs
    Route::apiResource('templates', App\Http\Controllers\Api\App\TemplateController::class);
    
    // Settings APIs
    Route::prefix('settings')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\App\SettingsController::class, 'index']);
        Route::patch('/', [App\Http\Controllers\Api\App\SettingsController::class, 'update']);
        Route::get('/general', [App\Http\Controllers\Api\App\SettingsController::class, 'general']);
        Route::patch('/general', [App\Http\Controllers\Api\App\SettingsController::class, 'updateGeneral']);
        Route::get('/security', [App\Http\Controllers\Api\App\SettingsController::class, 'security']);
        Route::patch('/security', [App\Http\Controllers\Api\App\SettingsController::class, 'updateSecurity']);
        Route::get('/notifications', [App\Http\Controllers\Api\App\SettingsController::class, 'notifications']);
        Route::patch('/notifications', [App\Http\Controllers\Api\App\SettingsController::class, 'updateNotifications']);
    });
});

// Auth API Routes
Route::prefix('api/v1/auth')->middleware([\App\Http\Middleware\ComprehensiveRateLimitMiddleware::class . ':auth'])->group(function () {
    Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout'])->middleware('auth');
    Route::post('/refresh', [App\Http\Controllers\Api\AuthController::class, 'refresh'])->middleware('auth');
    Route::get('/me', [App\Http\Controllers\Api\AuthController::class, 'me'])->middleware('auth');
});

// Invitation API Routes
Route::prefix('api/v1/invitations')->middleware([\App\Http\Middleware\ComprehensiveRateLimitMiddleware::class . ':invitations'])->group(function () {
    Route::get('/accept/{token}', [App\Http\Controllers\Api\InvitationController::class, 'accept']);
    Route::post('/accept/{token}', [App\Http\Controllers\Api\InvitationController::class, 'processAcceptance']);
});

// Public API Routes (no authentication required)
Route::prefix('api/v1/public')->middleware([\App\Http\Middleware\ComprehensiveRateLimitMiddleware::class . ':public'])->group(function () {
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
