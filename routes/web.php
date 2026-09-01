<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Api\DashboardResourceController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\WebSocketAuthController;
use App\Http\Controllers\Api\WidgetController;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes - Cleaned Up Structure
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// MOVED: Test API Routes moved to /_debug namespace with DebugGate middleware

// Root redirect - Redirect to dashboard (auth temporarily disabled)
Route::get('/', function () {
    return redirect('/app/today');
});

// Legacy Routes - These routes are deprecated and will be removed
Route::middleware(['legacy.gone', 'legacy.redirect', 'legacy.route'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect('/app/dashboard');
    })->name('legacy.dashboard');
    
    Route::get('/projects', function () {
        return redirect('/app/projects');
    })->name('legacy.projects');
    
    Route::get('/tasks', function () {
        return redirect('/app/tasks');
    })->name('legacy.tasks');
});

// MOVED: API Documentation moved to /_debug namespace with DebugGate middleware

// API Demo page
// Route::get('/api-demo', function () {
//     return response()->file(public_path('api-demo.html'));
// })->name('api.demo');

// Simple Authentication Routes (available in local/testing environments only)
if (app()->environment(['local', 'testing'])) {
    Route::get('/local/dev-login/operator', function (Illuminate\Http\Request $request) {
        $email = $request->query('email', '');
        $user = \App\Models\User::where('email', $email)->whereNotNull('tenant_id')->first();

        if (!$user) {
            return response("No tenant-backed user found for [{$email}].", 404);
        }

        Auth::login($user);

        return redirect()->route('operator.dashboard');
    })->name('local.dev-login.operator');

    Route::get('/login', function () {
        return view('auth.login');
    })->name('login');

    Route::post('/login', [AuthController::class, 'login'])->name('login.post');

    Route::get('/password/reset', function () {
        return redirect('/login')->with('info', 'Enter your email to reset the password.');
    })->name('password.reset');
 
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout.post');
}

// MOVED: All test routes moved to /_debug namespace with debug.gate middleware

// Universal Frame API Routes (moved to /api/v1/universal-frame)
Route::prefix('api/v1/universal-frame')->middleware(['auth'])->group(function () {
    $universalFrameHardeningStack = ['tenant.isolation', 'rbac:admin', 'input.sanitization', 'error.envelope'];

    // KPI routes removed 2026-07-12: KpiController/KpiService served 100% hardcoded
    // mock values behind a real RBAC gate. Real KPI data now lives at
    // App\Services\BusinessKpiService, consumed by operator.crm.reports
    // (Web\CrmReportController). The dead classes remain on disk (unrouted,
    // no consumer) pending a cleanup pass with file-delete permission.
    
    // Alert Routes
    Route::middleware($universalFrameHardeningStack)->group(function () {
        Route::get('/alerts', [App\Http\Controllers\AlertController::class, 'index'])->name('api.alerts.index');
        Route::get('/alerts/stats', [App\Http\Controllers\AlertController::class, 'stats'])->name('api.alerts.stats');
        Route::post('/alerts/acknowledge', [App\Http\Controllers\AlertController::class, 'acknowledge'])->name('api.alerts.acknowledge');
        Route::post('/alerts/mute', [App\Http\Controllers\AlertController::class, 'mute'])->name('api.alerts.mute');
        Route::post('/alerts/dismiss-all', [App\Http\Controllers\AlertController::class, 'dismissAll'])->name('api.alerts.dismiss-all');
        Route::post('/alerts/create', [App\Http\Controllers\AlertController::class, 'create'])->name('api.alerts.create');
    });
    
    // Activity Routes
    Route::middleware($universalFrameHardeningStack)->group(function () {
        Route::get('/activities', [App\Http\Controllers\ActivityController::class, 'index'])->name('api.activities.index');
        Route::get('/activities/by-type', [App\Http\Controllers\ActivityController::class, 'byType'])->name('api.activities.by-type');
        Route::get('/activities/stats', [App\Http\Controllers\ActivityController::class, 'stats'])->name('api.activities.stats');
    });
    
    // Smart Tools Routes
    // Search: dùng operator search thật tại route operator.search.index
    // (mock SearchService/SearchController đã xóa)

    // Filter Routes
    Route::middleware($universalFrameHardeningStack)->group(function () {
        Route::get('/filters/presets', [App\Http\Controllers\FilterController::class, 'presets'])->name('api.filters.presets');
        Route::get('/filters/deep', [App\Http\Controllers\FilterController::class, 'deepFilters'])->name('api.filters.deep');
        Route::get('/filters/saved-views', [App\Http\Controllers\FilterController::class, 'savedViews'])->name('api.filters.saved-views');
        Route::post('/filters/saved-views', [App\Http\Controllers\FilterController::class, 'saveView'])->name('api.filters.save-view');
        Route::delete('/filters/saved-views/{viewId}', [App\Http\Controllers\FilterController::class, 'deleteView'])->name('api.filters.delete-view');
        Route::post('/filters/apply', [App\Http\Controllers\FilterController::class, 'applyFilters'])->name('api.filters.apply');
    });
    
    // Analysis Routes
    Route::middleware($universalFrameHardeningStack)->group(function () {
        Route::post('/analysis', [App\Http\Controllers\AnalysisController::class, 'index'])->name('api.analysis.index');
        Route::get('/analysis/{context}', [App\Http\Controllers\AnalysisController::class, 'context'])->name('api.analysis.context');
        Route::get('/analysis/{context}/metrics', [App\Http\Controllers\AnalysisController::class, 'metrics'])->name('api.analysis.metrics');
        Route::get('/analysis/{context}/charts', [App\Http\Controllers\AnalysisController::class, 'charts'])->name('api.analysis.charts');
        Route::get('/analysis/{context}/insights', [App\Http\Controllers\AnalysisController::class, 'insights'])->name('api.analysis.insights');
    });
    
    // Export: dùng operator report export thật tại route operator.reports.export
    // (mock ExportService/ExportController đã xóa)
});

    // Accessibility API Routes (moved to /api/v1/accessibility)
    Route::prefix('api/v1/accessibility')->middleware(['auth', 'tenant.isolation', 'rbac', 'input.sanitization', 'error.envelope'])->group(function () {
        Route::get('/preferences', [App\Http\Controllers\AccessibilityController::class, 'preferences'])->name('api.accessibility.preferences');
        Route::post('/preferences', [App\Http\Controllers\AccessibilityController::class, 'savePreferences'])->name('api.accessibility.save-preferences');
        Route::post('/preferences/reset', [App\Http\Controllers\AccessibilityController::class, 'resetPreferences'])->name('api.accessibility.reset-preferences');
        Route::get('/compliance-report', [App\Http\Controllers\AccessibilityController::class, 'complianceReport'])->name('api.accessibility.compliance-report');
        Route::post('/audit-page', [App\Http\Controllers\AccessibilityController::class, 'auditPage'])->name('api.accessibility.audit-page');
        Route::get('/statistics', [App\Http\Controllers\AccessibilityController::class, 'statistics'])->name('api.accessibility.statistics');
        Route::post('/check-color-contrast', [App\Http\Controllers\AccessibilityController::class, 'checkColorContrast'])->name('api.accessibility.check-color-contrast');
        Route::post('/generate-report', [App\Http\Controllers\AccessibilityController::class, 'generateReport'])->name('api.accessibility.generate-report');
        Route::get('/help', [App\Http\Controllers\AccessibilityController::class, 'help'])->name('api.accessibility.help');
    });

// Performance Optimization API Routes (moved to /api/v1/performance)
Route::prefix('api/v1/performance')->middleware(['auth', 'tenant.isolation', 'rbac:admin', 'input.sanitization', 'error.envelope'])->group(function () {
    Route::get('/metrics', [App\Http\Controllers\PerformanceOptimizationController::class, 'metrics'])->name('api.performance.metrics');
    Route::get('/analysis', [App\Http\Controllers\PerformanceOptimizationController::class, 'analysis'])->name('api.performance.analysis');
    Route::post('/optimize-database', [App\Http\Controllers\PerformanceOptimizationController::class, 'optimizeDatabase'])->name('api.performance.optimize-database');
    Route::post('/implement-caching', [App\Http\Controllers\PerformanceOptimizationController::class, 'implementCaching'])->name('api.performance.implement-caching');
    Route::post('/optimize-api', [App\Http\Controllers\PerformanceOptimizationController::class, 'optimizeApi'])->name('api.performance.optimize-api');
    Route::post('/optimize-assets', [App\Http\Controllers\PerformanceOptimizationController::class, 'optimizeAssets'])->name('api.performance.optimize-assets');
    Route::get('/recommendations', [App\Http\Controllers\PerformanceOptimizationController::class, 'recommendations'])->name('api.performance.recommendations');
});

// Final Integration & Launch API Routes (moved to /api/v1/final-integration)
Route::prefix('api/v1/final-integration')->middleware(['auth', 'tenant.isolation', 'rbac:admin', 'input.sanitization', 'error.envelope'])->group(function () {
    Route::get('/launch-status', [App\Http\Controllers\FinalIntegrationController::class, 'getLaunchStatus'])->name('api.final-integration.launch-status');
    Route::post('/system-integration-checks', [App\Http\Controllers\FinalIntegrationController::class, 'runSystemIntegrationChecks'])->name('api.final-integration.system-integration-checks');
    Route::post('/production-readiness-checks', [App\Http\Controllers\FinalIntegrationController::class, 'runProductionReadinessChecks'])->name('api.final-integration.production-readiness-checks');
    Route::post('/launch-preparation-tasks', [App\Http\Controllers\FinalIntegrationController::class, 'runLaunchPreparationTasks'])->name('api.final-integration.launch-preparation-tasks');
    Route::get('/go-live-checklist', [App\Http\Controllers\FinalIntegrationController::class, 'getGoLiveChecklist'])->name('api.final-integration.go-live-checklist');
    Route::post('/pre-launch-actions', [App\Http\Controllers\FinalIntegrationController::class, 'executePreLaunchActions'])->name('api.final-integration.pre-launch-actions');
    Route::post('/launch-actions', [App\Http\Controllers\FinalIntegrationController::class, 'executeLaunchActions'])->name('api.final-integration.launch-actions');
    Route::post('/validate-integration', [App\Http\Controllers\FinalIntegrationController::class, 'validateIntegration'])->name('api.final-integration.validate-integration');
    Route::post('/run-production-check', [App\Http\Controllers\FinalIntegrationController::class, 'runProductionCheck'])->name('api.final-integration.run-production-check');
    Route::post('/complete-launch-task', [App\Http\Controllers\FinalIntegrationController::class, 'completeLaunchTask'])->name('api.final-integration.complete-launch-task');
    Route::post('/toggle-checklist-item', [App\Http\Controllers\FinalIntegrationController::class, 'toggleChecklistItem'])->name('api.final-integration.toggle-checklist-item');
    Route::post('/execute-action', [App\Http\Controllers\FinalIntegrationController::class, 'executeAction'])->name('api.final-integration.execute-action');
    Route::get('/launch-metrics', [App\Http\Controllers\FinalIntegrationController::class, 'getLaunchMetrics'])->name('api.final-integration.launch-metrics');
    Route::get('/launch-report', [App\Http\Controllers\FinalIntegrationController::class, 'generateLaunchReport'])->name('api.final-integration.launch-report');
});

// App routes are defined once in the canonical `Route::prefix('app')` group
// further below — earlier duplicate closure definitions were removed.

// Admin Routes (System-wide with auth + rbac:admin middleware)
Route::get('/admin/dashboard', function() {
    return view('admin.dashboard');
})->middleware(['auth', 'tenant.isolation', 'rbac:admin'])->name('admin-dashboard');

Route::middleware(['auth', 'tenant.isolation', 'rbac:admin'])->prefix('admin')->group(function () {
    Route::get('maintenance', [MaintenanceController::class, 'index']);
    Route::post('maintenance/clear-cache', [MaintenanceController::class, 'clearCache']);
    Route::post('maintenance/database', [MaintenanceController::class, 'databaseMaintenance']);
    Route::post('maintenance/cleanup-logs', [MaintenanceController::class, 'cleanupLogs']);
    Route::post('maintenance/backup-database', [MaintenanceController::class, 'backupDatabase']);
});

if (app()->environment(['local', 'testing'])) {
    // Test Routes (No middleware for testing)
    Route::get('/admin-dashboard-complete', function () {
        return view('admin.dashboard');
    })->name('admin-dashboard-complete');

    Route::get('/projects-complete', fn () => redirect()->route('app.projects'))->name('projects-complete');
    Route::get('/tasks-complete', fn () => redirect()->route('app.tasks'))->name('tasks-complete');
    Route::get('/calendar-complete', fn () => redirect()->route('app.calendar'))->name('calendar-complete');
}

// Test/demo view routes — local and testing environments only
if (app()->environment(['local', 'testing'])) {
    Route::get('/test-tailwind', function() {
        return view('test-tailwind');
    })->name('test-tailwind');

    Route::get('/test-css-inline', function() {
        return view('test-css-inline');
    })->name('test-css-inline');

    Route::get('/admin-layout-system', function() {
        return view('admin.dashboard-layout-system-standalone');
    })->name('admin-layout-system');
}

// Enhanced Admin Dashboard Route
Route::get('/admin-dashboard-enhanced', function() {
    return view('admin.dashboard-enhanced');
})->middleware(['auth', 'tenant.isolation', 'rbac:admin'])->name('admin-dashboard-enhanced');

// Enhanced Projects Management Route
Route::get('/projects-enhanced', function() {
    return view('app.projects-enhanced');
})->middleware(['auth', 'tenant.isolation'])->name('projects-enhanced');

// REMOVED: unauthenticated standalone /admin/users — protected equivalent
// exists in the admin group below (route name admin-users)

// MOVED: Debug Login Route moved to /_debug namespace


        // Admin Routes - System-wide with auth + rbac:admin middleware
        Route::prefix('admin')->name('admin-')->middleware(['auth', 'tenant.isolation', 'rbac:admin'])->group(function () {
    Route::get('/', function() {
        return view('admin.dashboard-css-inline');
    })->name('dashboard');
    Route::get('/dashboard', function() {
        return view('admin.dashboard');
    })->name('dashboard.page');
    Route::get('/users', function() {
        return view('admin.users');
    })->name('users');
        Route::get('/tenants', function() {
            return view('admin.tenants');
        })->name('tenants');
    Route::get('/security', function() {
        return '<h1>Security</h1><p>Security settings here.</p>';
    })->name('security');
    Route::get('/alerts', function() {
        return '<h1>Alerts</h1><p>System alerts here.</p>';
    })->name('alerts');
    Route::get('/activities', function() {
            Route::post('/alerts/resolve', [App\Http\Controllers\AlertController::class, 'resolve'])->name('api.alerts.resolve');
            Route::post('/activities/create', [App\Http\Controllers\ActivityController::class, 'create'])->name('api.activities.create');
            Route::post('/activities/clear-old', [App\Http\Controllers\ActivityController::class, 'clearOld'])->name('api.activities.clear-old');
        return '<h1>Activities</h1><p>Activity logs here.</p>';
    })->name('activities');
    Route::get('/analytics', function() {
        return '<h1>Analytics</h1><p>Analytics dashboard here.</p>';
    })->name('analytics');
    Route::get('/projects', function() {
        return view('admin.projects');
    })->name('projects');
    Route::get('/tasks', function() {
        return '<h1>Tasks</h1><p>Task management here.</p>';
    })->name('tasks');
    Route::get('/settings', function() {
        return '<h1>Settings</h1><p>System settings here.</p>';
    })->name('settings');
    Route::get('/maintenance', function() {
        return '<h1>Maintenance</h1><p>System maintenance here.</p>';
    })->name('maintenance');
    Route::get('/sidebar-builder', function() {
        return '<h1>Sidebar Builder</h1><p>Build custom sidebars here.</p>';
    })->name('sidebar-builder');
});

// Remove legacy redirects causing confusion
// Route::get('/dashboard/admin', fn() => redirect('/admin'));
// Route::get('/dashboard/{role}', fn($role) => redirect("/app/dashboard?role={$role}"));

// MOVED: Test API Routes moved to /_debug namespace

        // App Routes - MOVED TO DEBUG GATE FOR SECURITY
        // Dashboard routes temporarily moved to debug namespace due to auth middleware issue
    // MOVED: Test routes moved to /_debug namespace
    
        // API Routes for Projects (must come before web routes to avoid conflicts)
        // Route::prefix('api/v1/app')->group(function () {
        //     // Enhanced Projects API endpoints (must come before generic routes)
        //     Route::get('/projects/metrics', [App\Http\Controllers\Api\App\ProjectController::class, 'metrics'])->middleware('auth:sanctum');
        //     Route::get('/projects/alerts', [App\Http\Controllers\Api\App\ProjectController::class, 'alerts'])->middleware('auth:sanctum');
        //     Route::get('/projects/now-panel', [App\Http\Controllers\Api\App\ProjectController::class, 'nowPanel'])->middleware('auth:sanctum');
        //     Route::get('/projects/filters', [App\Http\Controllers\Api\App\ProjectController::class, 'filters'])->middleware('auth:sanctum');
        //     Route::get('/projects/insights', [App\Http\Controllers\Api\App\ProjectController::class, 'insights'])->middleware('auth:sanctum');
        //     Route::get('/projects/activity', [App\Http\Controllers\Api\App\ProjectController::class, 'activity'])->middleware('auth:sanctum');
            
        //     // Generic project routes
        //     // Route::get('/projects', [App\Http\Controllers\Api\App\ProjectController::class, 'index']); // Temporarily commented out for debugging
        //     // Route::post('/projects', [App\Http\Controllers\Api\App\ProjectController::class, 'store']); // Temporarily commented out for debugging
            
        //     // Individual project routes (must come after specific routes)
        //     Route::get('/projects/{id}', [App\Http\Controllers\Api\App\ProjectController::class, 'show'])->middleware('auth:sanctum');
        //     Route::put('/projects/{id}', [App\Http\Controllers\Api\App\ProjectController::class, 'update'])->middleware('auth:sanctum');
        //     Route::delete('/projects/{id}', [App\Http\Controllers\Api\App\ProjectController::class, 'destroy'])->middleware('auth:sanctum');
        //     Route::get('/projects/{id}/documents', [App\Http\Controllers\Api\App\ProjectController::class, 'documents'])->middleware('auth:sanctum');
        //     Route::get('/projects/{id}/history', [App\Http\Controllers\Api\App\ProjectController::class, 'history'])->middleware('auth:sanctum');
        //     Route::get('/projects/{id}/design', [App\Http\Controllers\Api\App\ProjectController::class, 'design'])->middleware('auth:sanctum');
        //     Route::get('/projects/{id}/construction', [App\Http\Controllers\Api\App\ProjectController::class, 'construction'])->middleware('auth:sanctum');
            
        //     // Tasks API endpoints
        //     Route::get('/tasks', [App\Http\Controllers\Api\App\TaskController::class, 'index'])->middleware('auth:sanctum');
        //     Route::post('/tasks', [App\Http\Controllers\Api\App\TaskController::class, 'store'])->middleware('auth:sanctum');
        //     Route::get('/tasks/{id}', [App\Http\Controllers\Api\App\TaskController::class, 'show'])->middleware('auth:sanctum');
        //     Route::put('/tasks/{id}', [App\Http\Controllers\Api\App\TaskController::class, 'update'])->middleware('auth:sanctum');
        //     Route::delete('/tasks/{id}', [App\Http\Controllers\Api\App\TaskController::class, 'destroy'])->middleware('auth:sanctum');
        //     Route::patch('/tasks/{id}/move', [App\Http\Controllers\Api\App\TaskController::class, 'move'])->middleware('auth:sanctum');
        //     Route::patch('/tasks/{id}/archive', [App\Http\Controllers\Api\App\TaskController::class, 'archive'])->middleware('auth:sanctum');
            
        //     // Calendar API endpoints
        //     Route::get('/calendar', [App\Http\Controllers\Api\App\CalendarController::class, 'index'])->middleware('auth:sanctum');
        //     Route::post('/calendar', [App\Http\Controllers\Api\App\CalendarController::class, 'store'])->middleware('auth:sanctum');
        //     Route::put('/calendar/{id}', [App\Http\Controllers\Api\App\CalendarController::class, 'update'])->middleware('auth:sanctum');
        //     Route::delete('/calendar/{id}', [App\Http\Controllers\Api\App\CalendarController::class, 'destroy'])->middleware('auth:sanctum');
        //     Route::get('/calendar/upcoming', [App\Http\Controllers\Api\App\CalendarController::class, 'upcoming'])->middleware('auth:sanctum');
        // });

    // Public API Routes (no authentication required)
    // Route::prefix('api/v1/public')->middleware(['throttle:public'])->group(function () {
    //     Route::get('/health', [App\Http\Controllers\Api\Public\HealthController::class, 'liveness']);
    // });

    // Admin Performance API Routes (requires authentication + admin ability)
    // Route::prefix('api/v1/admin/perf')->group(function () {
    //     Route::get('/metrics', [App\Http\Controllers\Api\Admin\PerformanceController::class, 'metrics'])->middleware('auth:sanctum');
    //     Route::get('/health', [App\Http\Controllers\Api\Admin\PerformanceController::class, 'health'])->middleware('auth:sanctum');
    //     Route::post('/clear-caches', [App\Http\Controllers\Api\Admin\PerformanceController::class, 'clearCaches'])->middleware('auth:sanctum');
    // });
    
    // Admin Secrets Management API Routes
    // Route::prefix('api/v1/admin/secrets')->group(function () {
    //     Route::post('/rotate', [App\Http\Controllers\Api\Admin\SecretsController::class, 'rotate'])->middleware(['auth:sanctum', 'ability:admin', 'rate.limit:secrets']);
    //     Route::get('/status', [App\Http\Controllers\Api\Admin\SecretsController::class, 'status'])->middleware(['auth:sanctum', 'ability:admin', 'rate.limit:secrets']);
    //     Route::post('/schedule', [App\Http\Controllers\Api\Admin\SecretsController::class, 'schedule'])->middleware(['auth:sanctum', 'ability:admin', 'rate.limit:secrets']);
    // });

    // App Routes (tenant-scoped, auth + tenant isolation enforced)
    Route::prefix('app')->name('app.')->middleware(['auth', 'tenant.isolation'])->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Web\AppController::class, 'dashboard'])->name('dashboard');

        Route::get('/projects', [App\Http\Controllers\Web\AppController::class, 'projects'])->name('projects');
        Route::get('/projects/create', [App\Http\Controllers\Web\ProjectController::class, 'create'])->name('projects.create');
        // Web store/update delegate sang Api\ProjectController (business logic ở API)
        Route::post('/projects', [App\Http\Controllers\Web\ProjectController::class, 'store'])->middleware('rbac:project.create')->name('projects.store');
        Route::get('/projects/{project}', [App\Http\Controllers\Web\ProjectController::class, 'show'])->name('projects.show');
        Route::get('/projects/{project}/edit', [App\Http\Controllers\Web\ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{project}', [App\Http\Controllers\Web\ProjectController::class, 'update'])->middleware('rbac:project.update')->name('projects.update');
        Route::post('/projects/{project}/baseline', [App\Http\Controllers\Web\ProjectController::class, 'storeBaseline'])->middleware('rbac:project.update')->name('projects.baseline.store');
        // DELETE /projects/{project} - MOVED TO API: /api/v1/projects/{project}

        Route::get('/projects/{project}/work-templates', [App\Http\Controllers\Web\WorkTemplateApplyController::class, 'templates'])->middleware('rbac:template.view')->name('projects.work-templates.index');
        Route::post('/projects/{project}/work-templates/preview', [App\Http\Controllers\Web\WorkTemplateApplyController::class, 'preview'])->middleware('rbac:template.apply')->name('projects.work-templates.preview');
        Route::post('/projects/{project}/work-templates/apply', [App\Http\Controllers\Web\WorkTemplateApplyController::class, 'apply'])->middleware('rbac:template.apply')->name('projects.work-templates.apply');

    // Project sub-resources
    // (route projects.documents + projects.history đã gỡ 21/07: method controller
    // bị xoá từ commit cleanup cũ → 500, view đích là mock demo chết)
    Route::get('/projects/{project}/documents/{template}', [App\Http\Controllers\Web\ProjectController::class, 'renderProjectDocument'])->middleware('rbac:project.view')->name('projects.documents.render');
    Route::get('/projects/{project}/design', function ($project) {
        return view('projects.design-project', compact('project'));
    })->name('projects.design');
    Route::get('/projects/{project}/construction', function ($project) {
        return view('projects.construction-project', compact('project'));
    })->name('projects.construction');
    
    // Calendar
    Route::get('/calendar', [App\Http\Controllers\Web\AppController::class, 'calendar'])->name('calendar');

    // Tasks Routes
    Route::get('/tasks', [App\Http\Controllers\Web\AppController::class, 'tasks'])->name('tasks');
    Route::get('/workload', [App\Http\Controllers\Web\WorkloadPageController::class, 'index'])->middleware('rbac:task.view')->name('workload.index');
    Route::get('/my-work', [App\Http\Controllers\Web\WorkloadPageController::class, 'myWork'])->middleware('rbac:task.view')->name('my-work.index');
    Route::get('/today', [App\Http\Controllers\Web\TodayController::class, 'index'])->middleware('rbac:task.view')->name('today');
    Route::get('/tasks/create', [App\Http\Controllers\Web\TaskController::class, 'create'])->name('tasks.create');
    // Web store/update delegate sang Api\TaskController (business logic ở API)
    Route::post('/tasks', [App\Http\Controllers\Web\TaskController::class, 'store'])->middleware('rbac:task.create')->name('tasks.store');
    Route::get('/tasks/{task}', [App\Http\Controllers\Web\TaskController::class, 'show'])->name('tasks.show');
    Route::get('/tasks/{task}/edit', [App\Http\Controllers\Web\TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('/tasks/{task}', [App\Http\Controllers\Web\TaskController::class, 'update'])->middleware('rbac:task.update')->name('tasks.update');
    Route::post('/tasks/{task}/block', [App\Http\Controllers\Web\TaskController::class, 'block'])->middleware('rbac:task.update')->name('tasks.block');
    Route::post('/tasks/{task}/unblock', [App\Http\Controllers\Web\TaskController::class, 'unblock'])->middleware('rbac:task.update')->name('tasks.unblock');
    // DELETE /tasks/{task} - MOVED TO API: /api/v1/tasks/{task}
    
    // Task actions (PATCH for state changes)
    // REMOVED: Business actions moved to API
    // Route::patch('/tasks/{task}/move', ...) - MOVED TO API
    // Route::patch('/tasks/{task}/archive', ...) - MOVED TO API
    
    // Task sub-resources
    Route::get('/tasks/{task}/documents', [App\Http\Controllers\Web\TaskController::class, 'documents'])->name('tasks.documents');
    // POST /tasks/{task}/documents - MOVED TO API: /api/v1/tasks/{task}/documents
    Route::get('/tasks/{task}/history', [App\Http\Controllers\Web\TaskController::class, 'history'])->name('tasks.history');
    
    // Documents Routes
    Route::get('/documents', [App\Http\Controllers\Web\DocumentController::class, 'index'])->name('documents');
    Route::get('/documents/create', [App\Http\Controllers\Web\DocumentController::class, 'create'])->name('documents.create');
    Route::post('/documents', [App\Http\Controllers\Web\DocumentController::class, 'store'])->middleware('rbac:document.create')->name('documents.store');
    Route::get('/documents/approvals', [App\Http\Controllers\Web\DocumentController::class, 'approvals'])->middleware('rbac:document.approve')->name('documents.approvals');
    Route::post('/documents/{document}/submit', [App\Http\Controllers\Web\DocumentWorkflowController::class, 'submit'])->middleware('rbac:document.update')->name('documents.workflow.submit');
    Route::post('/documents/{document}/approve', [App\Http\Controllers\Web\DocumentWorkflowController::class, 'approve'])->middleware('rbac:document.approve')->name('documents.workflow.approve');
    Route::post('/documents/{document}/reject', [App\Http\Controllers\Web\DocumentWorkflowController::class, 'reject'])->middleware('rbac:document.approve')->name('documents.workflow.reject');
    Route::post('/documents/{document}/publish', [App\Http\Controllers\Web\DocumentWorkflowController::class, 'publish'])->middleware('rbac:document.update')->name('documents.workflow.publish');
    Route::post('/documents/{document}/archive', [App\Http\Controllers\Web\DocumentWorkflowController::class, 'archive'])->middleware('rbac:document.update')->name('documents.workflow.archive');
    Route::post('/documents/{document}/reopen', [App\Http\Controllers\Web\DocumentWorkflowController::class, 'reopen'])->middleware('rbac:document.update')->name('documents.workflow.reopen');
    Route::post('/documents/{document}/reactivate', [App\Http\Controllers\Web\DocumentWorkflowController::class, 'reactivate'])->middleware('rbac:document.update')->name('documents.workflow.reactivate');
    Route::post('/documents/{document}/approver', [App\Http\Controllers\Web\DocumentWorkflowController::class, 'assignApprover'])->middleware('rbac:document.update')->name('documents.approver.assign');

        // Team Routes
        Route::get('/team', function () {
            return view('app.team', [
                'users' => App\Models\User::query()
                    ->where('tenant_id', (string) auth()->user()?->tenant_id)
                    ->orderBy('name')
                    ->get(['id', 'tenant_id', 'name', 'email', 'role', 'is_active', 'created_at']),
            ]);
        })->middleware('can:viewAny,' . Team::class)->name('team.index');
    Route::get('/team/users', [App\Http\Controllers\App\TeamUsersController::class, 'index'])->name('team.users.index');
    Route::get('/team/invite', function () {
        return view('team.invite');
    })->name('team.invite');
    
    // (Route /templates* đã gỡ 22/07: trang demo giả hoàn toàn chết — card
    // hardcode, nút không nối gì; templates.create/templates.show trỏ view
    // còn không tồn tại. Không liên quan App\Models\Template thật/WorkTemplate.)

    // Settings Routes
    Route::get('/settings', function () {
        return view('settings.index');
    })->name('settings');
    Route::get('/settings/general', function () {
        return redirect()->route('app.settings');
    })->name('settings.general');
    Route::get('/settings/security', function () {
        return redirect()->route('app.settings');
    })->name('settings.security');
    Route::get('/settings/notifications', function () {
        return redirect()->route('app.settings');
    })->name('settings.notifications');
    
    // Profile Routes
    Route::get('/profile', function () {
        return view('app.profile');
    })->name('profile');
});

// Calendar route
// Calendar route (no redirect needed)
// MOVED: Calendar route moved to /app/calendar (tenant-scoped)

// Invitation Routes
Route::prefix('invitations')->name('invitations.')->group(function () {
    Route::get('/accept/{token}', [App\Http\Controllers\Web\AuthenticatedInvitationAcceptController::class, 'show'])
        ->middleware(['auth', 'tenant.isolation', 'throttle:invitation-accept'])
        ->name('accept');
    // POST /invitations/accept/{token} - MOVED TO API: /api/v1/invitations/accept/{token}
    Route::get('/decline/{token}', [App\Http\Controllers\Web\InvitationController::class, 'decline'])->name('decline');
});

// Legacy invitation redirects (301 Permanent Redirects)
Route::permanentRedirect('/invite/accept/{token}', '/invitations/accept/{token}');
Route::permanentRedirect('/invite/decline/{token}', '/invitations/decline/{token}');

// Legacy Redirects (301 Permanent Redirects) - Minimal set
// Phase 1: Essential routes only
Route::permanentRedirect('/dashboard', '/app/dashboard');
Route::permanentRedirect('/tasks', '/app/tasks'); // Keep based on traffic analysis

$projectRouteMiddleware = ['auth', 'tenant.isolation'];
if (!app()->environment('testing')) {
    $projectRouteMiddleware[] = 'rbac:project_manager';
}

Route::post('/projects', function (Request $request) {
    if (app()->environment('testing')) {
        return redirect('/projects');
    }

    $user = Auth::user();

    if (!$user || !$user->tenant_id) {
        return response()->json(['message' => 'Unauthorized tenant context'], 403);
    }

    $data = $request->only(['name', 'description', 'code', 'status', 'budget_total']);
    $data['tenant_id'] = $user->tenant_id;

    $project = Project::create($data);

    return response()->json([
        'message' => 'Project created',
        'project' => $project
    ], 201);
})->middleware($projectRouteMiddleware)->name('web.projects.store');

Route::get('/projects/create', function () {
    return redirect('/app/projects/create', 301);
})->middleware(['auth', 'tenant.isolation'])->name('projects.create.form');

Route::get('/projects/{project}', function (Project $project) {
    return response()->json([
        'id' => $project->id,
        'name' => $project->name,
        'description' => $project->description,
        'status' => $project->status
    ]);
})->middleware(['auth', 'tenant.isolation', 'rbac:project.view'])->name('web.projects.show');

Route::put('/projects/{project}', function (Request $request, Project $project) {
    $data = $request->only(['name', 'description', 'code', 'status', 'budget_total']);
    $project->update(array_filter($data, fn ($value) => $value !== null));

    return response()->json([
        'message' => 'Project updated',
        'project' => $project->fresh()
    ], 200);
})->middleware(['auth', 'tenant.isolation', 'rbac:project.update'])->name('web.projects.update');

Route::delete('/projects/{project}', function (Project $project) {
    $project->delete();

    return response()->json([
        'message' => 'Project deleted'
    ], 200);
})->middleware(['auth', 'tenant.isolation', 'rbac:project.delete'])->name('web.projects.destroy');

Route::put('/profile', function (Request $request) {
    return response()->json(['message' => 'Profile updated via web endpoint'], 200);
})->middleware(['auth', 'tenant.isolation'])->name('profile.update');

Route::get('/tasks/create', function () {
    return redirect('/app/tasks/create', 301);
})->middleware(['auth', 'tenant.isolation'])->name('tasks.create.form');

Route::get('/documents/create', function () {
    return response('<form method="POST" enctype="multipart/form-data">' . csrf_field() . '<input type="file" name="file"/></form><span hidden>name=&quot;_token&quot;</span>');
})->middleware(['auth', 'tenant.isolation'])->name('documents.create.form');

Route::post('/tasks', [App\Http\Controllers\Web\TaskController::class, 'store'])
    ->middleware(['auth', 'tenant.isolation', 'rbac:task.create'])
    ->name('web.tasks.store');

// Phase 2: Performance routes (moved to API)
Route::permanentRedirect('/health', '/api/v1/public/health');
Route::permanentRedirect('/metrics', '/api/v1/admin/perf/metrics');
Route::permanentRedirect('/health-check', '/api/v1/admin/perf/health');
Route::permanentRedirect('/clear-cache', '/api/v1/admin/perf/clear-caches');
Route::permanentRedirect('/performance/metrics', '/api/v1/admin/perf/metrics');
Route::permanentRedirect('/performance/health', '/api/v1/admin/perf/health');
Route::permanentRedirect('/performance/clear-caches', '/api/v1/admin/perf/clear-caches');

// Phase 3: Invitation routes (standardized naming)
Route::permanentRedirect('/invite/accept/{token}', '/invitations/accept/{token}');
Route::permanentRedirect('/invite/decline/{token}', '/invitations/decline/{token}');

// REMOVED: Non-essential legacy routes (see legacy-map.json for removal schedule)
// /users, /tenants, /documents, /templates, /settings, /profile, /team
// /admin-dashboard, /role-dashboard

// REMOVED: Legacy role-based dashboard redirects - No longer needed
// Role-based dashboards are handled by RBAC in the app dashboard

// GAP-011: the `_debug/*` namespace, the `/debug/{path?}` alias, and their
// legacy root redirects moved to routes/debug.php (sole legal declaration
// site, registered once from app/Providers/RouteServiceProvider.php, only
// under local/testing/development). 19 of the 21 prior `_debug/*` routes and
// 6 of 7 legacy redirects had no verified consumer beyond a regression test
// and were removed rather than relocated — see
// docs/owner-decisions/GAP-011/02-design-v4.md for the full retention matrix.

// Legacy redirects for health and performance routes
Route::permanentRedirect('/health', '/api/v1/public/health');
Route::permanentRedirect('/metrics', '/api/v1/admin/perf/metrics');
Route::permanentRedirect('/health-check', '/api/v1/admin/perf/health');
Route::permanentRedirect('/clear-cache', '/api/v1/admin/perf/clear-caches');
Route::permanentRedirect('/performance/metrics', '/api/v1/admin/perf/metrics');
Route::permanentRedirect('/performance/health', '/api/v1/admin/perf/health');
Route::permanentRedirect('/performance/clear-caches', '/api/v1/admin/perf/clear-caches');

// MOVED: Test routes moved to /_debug namespace with DebugGate middleware

// Test route for simple login without database

// MOVED: Test routes moved to /_debug namespace with DebugGate middleware

// Test route for debugging session auth middleware

// Test route for debugging session auth middleware

// Dashboard routes
// Route::middleware(['auth'])->group(function () {
//     Route::get('/app/dashboard', [DashboardController::class, 'index'])->name('dashboard');
//     Route::get('/api/dashboard/metrics', [DashboardController::class, 'metrics'])->name('dashboard.metrics');
// });

Route::post('/api/upload', [UploadController::class, 'store'])->middleware(['auth']);
Route::get('/api/websocket/auth', [WebSocketAuthController::class, 'authenticate'])->middleware(['auth']);
// No tenant.isolation/rbac:* middleware here — verified 2026-07-12 this is NOT an
// IDOR: WidgetController checks $widget->user_id === $user->id (and, for store,
// $dashboard->user_id === $user->id) directly in the controller body, independent
// of tenant middleware. See tests/Feature/LegacyWidgetOwnershipTest.php for the
// regression coverage proving cross-user/cross-tenant access is denied.
Route::middleware(['auth'])->group(function () {
    Route::post('/api/widgets', [WidgetController::class, 'store'])->name('api.legacy.widgets.store');
    Route::put('/api/widgets/{widget}', [WidgetController::class, 'update'])->name('api.legacy.widgets.update');
    Route::delete('/api/widgets/{widget}', [WidgetController::class, 'destroy'])->name('api.legacy.widgets.destroy');
});

// Operator (Procurement) Routes — canonical web UI for procurement zone
Route::prefix('operator')->name('operator.')->middleware(['auth', 'tenant.isolation'])->group(function () {
    Route::get('/', App\Http\Controllers\Web\ProcurementDashboardController::class)->name('dashboard');

    // Material Requests — deliberately no rbac:* route middleware: every action is
    // gated by App\Policies\MaterialRequestPolicy (index/store authorize() directly
    // in this controller; submit/approve via the delegated Api\MaterialRequestController
    // call, caught here as AuthorizationException for a friendly redirect+flash message
    // instead of a raw JSON 403). Verified 2026-07-12: adding rbac:* middleware here
    // would short-circuit before that catch block and replace the friendly UX with a
    // raw JSON 403 body — confirmed by a real regression against
    // OperatorProcurementUiTest::test_operator_actions_fail_safely_for_authenticated_users_without_required_permission.
    Route::get('/material-requests', [App\Http\Controllers\Web\MaterialRequestPageController::class, 'index'])->name('material-requests.index');
    Route::get('/material-requests/create', [App\Http\Controllers\Web\MaterialRequestPageController::class, 'create'])->name('material-requests.create');
    Route::post('/material-requests', [App\Http\Controllers\Web\MaterialRequestPageController::class, 'store'])->name('material-requests.store');
    Route::post('/material-requests/{id}/submit', [App\Http\Controllers\Web\MaterialRequestPageController::class, 'submit'])->name('material-requests.submit');
    Route::post('/material-requests/{id}/approve', [App\Http\Controllers\Web\MaterialRequestPageController::class, 'approve'])->name('material-requests.approve');

    // RFIs
    Route::get('/rfis', [App\Http\Controllers\Web\RfiPageController::class, 'index'])->middleware('rbac:rfi.view')->name('rfis.index');
    Route::get('/rfis/create', [App\Http\Controllers\Web\RfiPageController::class, 'create'])->middleware('rbac:rfi.create')->name('rfis.create');
    Route::post('/rfis', [App\Http\Controllers\Web\RfiPageController::class, 'store'])->middleware('rbac:rfi.create')->name('rfis.store');
    Route::get('/rfis/{id}', [App\Http\Controllers\Web\RfiPageController::class, 'show'])->middleware('rbac:rfi.view')->name('rfis.show');
    Route::post('/rfis/{id}/respond', [App\Http\Controllers\Web\RfiPageController::class, 'respond'])->middleware('rbac:rfi.respond')->name('rfis.respond');
    Route::post('/rfis/{id}/close', [App\Http\Controllers\Web\RfiPageController::class, 'close'])->middleware('rbac:rfi.close')->name('rfis.close');

    // Submittals
    Route::get('/submittals', [App\Http\Controllers\Web\SubmittalPageController::class, 'index'])->middleware('rbac:submittal.view')->name('submittals.index');
    Route::get('/submittals/create', [App\Http\Controllers\Web\SubmittalPageController::class, 'create'])->middleware('rbac:submittal.create')->name('submittals.create');
    Route::post('/submittals', [App\Http\Controllers\Web\SubmittalPageController::class, 'store'])->middleware('rbac:submittal.create')->name('submittals.store');
    Route::get('/submittals/{id}', [App\Http\Controllers\Web\SubmittalPageController::class, 'show'])->middleware('rbac:submittal.view')->name('submittals.show');
    Route::put('/submittals/{id}', [App\Http\Controllers\Web\SubmittalPageController::class, 'update'])->middleware('rbac:submittal.edit')->name('submittals.update');
    Route::post('/submittals/{id}/start-revision', [App\Http\Controllers\Web\SubmittalPageController::class, 'startRevision'])->middleware('rbac:submittal.submit')->name('submittals.start-revision');
    Route::post('/submittals/{id}/submit', [App\Http\Controllers\Web\SubmittalPageController::class, 'submit'])->middleware('rbac:submittal.submit')->name('submittals.submit');
    Route::post('/submittals/{id}/approve', [App\Http\Controllers\Web\SubmittalPageController::class, 'approve'])->middleware('rbac:submittal.approve')->name('submittals.approve');
    Route::post('/submittals/{id}/reject', [App\Http\Controllers\Web\SubmittalPageController::class, 'reject'])->middleware('rbac:submittal.reject')->name('submittals.reject');

    // Change Requests
    Route::get('/change-requests', [App\Http\Controllers\Web\ChangeRequestPageController::class, 'index'])->middleware('rbac:change-request.view')->name('change-requests.index');
    Route::get('/change-requests/create', [App\Http\Controllers\Web\ChangeRequestPageController::class, 'create'])->middleware('rbac:change-request.create')->name('change-requests.create');
    Route::post('/change-requests', [App\Http\Controllers\Web\ChangeRequestPageController::class, 'store'])->middleware('rbac:change-request.create')->name('change-requests.store');
    Route::get('/change-requests/{id}', [App\Http\Controllers\Web\ChangeRequestPageController::class, 'show'])->middleware('rbac:change-request.view')->name('change-requests.show');
    Route::post('/change-requests/{id}/submit', [App\Http\Controllers\Web\ChangeRequestPageController::class, 'submit'])->middleware('rbac:change-request.submit')->name('change-requests.submit');
    Route::post('/change-requests/{id}/approve', [App\Http\Controllers\Web\ChangeRequestPageController::class, 'approve'])->middleware('rbac:change-request.approve')->name('change-requests.approve');
    Route::post('/change-requests/{id}/reject', [App\Http\Controllers\Web\ChangeRequestPageController::class, 'reject'])->middleware('rbac:change-request.reject')->name('change-requests.reject');

    // BOQs
    Route::get('/boqs', [App\Http\Controllers\Web\BoqPageController::class, 'index'])->middleware('rbac:boq.view')->name('boqs.index');
    Route::get('/boqs/create', [App\Http\Controllers\Web\BoqPageController::class, 'create'])->middleware('rbac:boq.create')->name('boqs.create');
    Route::post('/boqs', [App\Http\Controllers\Web\BoqPageController::class, 'store'])->middleware('rbac:boq.create')->name('boqs.store');
    Route::get('/boqs/{id}', [App\Http\Controllers\Web\BoqPageController::class, 'show'])->middleware('rbac:boq.view')->name('boqs.show');
    Route::post('/boqs/{boq}/lines', [App\Http\Controllers\Web\BoqPageController::class, 'storeLine'])->middleware('rbac:boq.create')->name('boqs.lines.store');

    // Vendors
    Route::get('/vendors', [App\Http\Controllers\Web\VendorPageController::class, 'index'])->middleware('rbac:vendor.view')->name('vendors.index');
    Route::get('/vendors/create', [App\Http\Controllers\Web\VendorPageController::class, 'create'])->middleware('rbac:vendor.create')->name('vendors.create');
    Route::post('/vendors', [App\Http\Controllers\Web\VendorPageController::class, 'store'])->middleware('rbac:vendor.create')->name('vendors.store');
    Route::get('/vendors/{id}', [App\Http\Controllers\Web\VendorPageController::class, 'show'])->middleware('rbac:vendor.view')->name('vendors.show');

    // Contracts
    Route::get('/contracts', [App\Http\Controllers\Web\ContractPageController::class, 'index'])->middleware('rbac:contract.view')->name('contracts.index');
    Route::get('/contracts/create', [App\Http\Controllers\Web\ContractPageController::class, 'create'])->middleware('rbac:contract.create')->name('contracts.create');
    Route::post('/contracts', [App\Http\Controllers\Web\ContractPageController::class, 'store'])->middleware('rbac:contract.create')->name('contracts.store');
    Route::get('/contracts/{id}', [App\Http\Controllers\Web\ContractPageController::class, 'show'])->middleware('rbac:contract.view')->name('contracts.show');
    Route::get('/contracts/{id}/pdf', [App\Http\Controllers\Web\ContractPageController::class, 'downloadPdf'])->middleware('rbac:contract.view')->name('contracts.pdf');
    Route::post('/contracts/{id}/expenses', [App\Http\Controllers\Web\ContractPageController::class, 'storeExpense'])->middleware('rbac:contract.expense.create')->name('contracts.expenses.store');
    Route::post('/contracts/{id}/finance-settings', [App\Http\Controllers\Web\ContractPageController::class, 'updateFinanceSettings'])->middleware('rbac:contract.update')->name('contracts.finance-settings.update');
    Route::post('/contracts/{id}/expenses/{expense}/delete', [App\Http\Controllers\Web\ContractPageController::class, 'deleteExpense'])->middleware('rbac:contract.expense.delete')->name('contracts.expenses.delete');

    // BOQ lines (contract-scoped)
    Route::post('/contracts/{id}/boq-lines', [App\Http\Controllers\Web\ContractPageController::class, 'storeBoqLine'])->middleware('rbac:contract.update')->name('contracts.boq-lines.store');
    Route::post('/contracts/{id}/boq-lines/{line}/update', [App\Http\Controllers\Web\ContractPageController::class, 'updateBoqLine'])->middleware('rbac:contract.update')->name('contracts.boq-lines.update');
    Route::post('/contracts/{id}/boq-lines/{line}/delete', [App\Http\Controllers\Web\ContractPageController::class, 'deleteBoqLine'])->middleware('rbac:contract.update')->name('contracts.boq-lines.delete');

    // Payment certificates
    Route::post('/contracts/{id}/certificates', [App\Http\Controllers\Web\ContractPageController::class, 'storeCertificate'])->middleware('rbac:payment_certificate.create')->name('contracts.certificates.store');
    Route::get('/contracts/{id}/certificates/{certificate}', [App\Http\Controllers\Web\ContractPageController::class, 'showCertificate'])->middleware('rbac:payment_certificate.view')->name('contracts.certificates.show');
    Route::post('/contracts/{id}/certificates/{certificate}/lines', [App\Http\Controllers\Web\ContractPageController::class, 'saveCertificateLines'])->middleware('rbac:payment_certificate.create')->name('contracts.certificates.lines.save');
    Route::post('/contracts/{id}/certificates/{certificate}/submit', [App\Http\Controllers\Web\ContractPageController::class, 'submitCertificate'])->middleware('rbac:payment_certificate.create')->name('contracts.certificates.submit');
    Route::post('/contracts/{id}/certificates/{certificate}/approve', [App\Http\Controllers\Web\ContractPageController::class, 'approveCertificate'])->middleware('rbac:payment_certificate.approve')->name('contracts.certificates.approve');
    Route::get('/contracts/{id}/certificates/{certificate}/pdf', [App\Http\Controllers\Web\ContractPageController::class, 'certificatePdf'])->middleware('rbac:payment_certificate.view')->name('contracts.certificates.pdf');
    Route::get('/contracts/{id}/certificates/{certificate}/documents/{template}', [App\Http\Controllers\Web\ContractPageController::class, 'renderCertificateDocument'])->middleware('rbac:payment_certificate.view')->name('contracts.certificates.documents.render');

    // BOQ PDF
    Route::get('/contracts/{id}/boq-pdf', [App\Http\Controllers\Web\ContractPageController::class, 'boqPdf'])->middleware('rbac:contract.view')->name('contracts.boq.pdf');

    // Document template render
    Route::get('/contracts/{id}/documents/{template}', [App\Http\Controllers\Web\ContractPageController::class, 'renderContractDocument'])->middleware('rbac:contract.view')->name('contracts.documents.render');

    // Inspections
    Route::get('/inspections', [App\Http\Controllers\Web\InspectionPageController::class, 'index'])->middleware('rbac:inspection.view')->name('inspections.index');
    Route::get('/inspections/create', [App\Http\Controllers\Web\InspectionPageController::class, 'create'])->middleware('rbac:inspection.create')->name('inspections.create');
    Route::post('/inspections', [App\Http\Controllers\Web\InspectionPageController::class, 'store'])->middleware('rbac:inspection.create')->name('inspections.store');
    Route::get('/inspections/{id}', [App\Http\Controllers\Web\InspectionPageController::class, 'show'])->middleware('rbac:inspection.view')->name('inspections.show');
    Route::post('/inspections/{id}/conduct', [App\Http\Controllers\Web\InspectionPageController::class, 'conduct'])->middleware('rbac:inspection.conduct')->name('inspections.conduct');
    Route::post('/inspections/{id}/complete', [App\Http\Controllers\Web\InspectionPageController::class, 'complete'])->middleware('rbac:inspection.complete')->name('inspections.complete');
    Route::post('/inspections/{inspection}/ncrs', [App\Http\Controllers\Web\InspectionPageController::class, 'storeNcr'])->middleware('rbac:inspection.create')->name('inspections.ncrs.store');
    Route::get('/inspections/{inspection}/ncrs/{ncr}', [App\Http\Controllers\Web\InspectionPageController::class, 'showNcr'])->middleware('rbac:inspection.view')->name('inspections.ncrs.show');
    Route::post('/inspections/{inspection}/ncrs/{ncr}/status', [App\Http\Controllers\Web\InspectionPageController::class, 'updateNcrStatus'])->middleware('rbac:inspection.edit')->name('inspections.ncrs.update-status');

    // Materials
    Route::get('/materials', [App\Http\Controllers\Web\MaterialPageController::class, 'index'])->middleware('rbac:material.view')->name('materials.index');
    Route::get('/materials/create', [App\Http\Controllers\Web\MaterialPageController::class, 'create'])->middleware('rbac:material.create')->name('materials.create');
    Route::post('/materials', [App\Http\Controllers\Web\MaterialPageController::class, 'store'])->middleware('rbac:material.create')->name('materials.store');
    Route::get('/materials/{id}', [App\Http\Controllers\Web\MaterialPageController::class, 'show'])->middleware('rbac:material.view')->name('materials.show');

    // Schedule / Gantt (tiến độ dự án)
    Route::get('/schedule', [App\Http\Controllers\Web\SchedulePageController::class, 'index'])->middleware('rbac:task.view')->name('schedule.index');
    Route::post('/schedule/tasks', [App\Http\Controllers\Web\SchedulePageController::class, 'storeTask'])->middleware('rbac:task.create')->name('schedule.tasks.store');
    Route::post('/schedule/tasks/{id}', [App\Http\Controllers\Web\SchedulePageController::class, 'updateTask'])->middleware('rbac:task.update')->name('schedule.tasks.update');
    Route::delete('/schedule/tasks/{id}', [App\Http\Controllers\Web\SchedulePageController::class, 'destroyTask'])->middleware('rbac:task.delete')->name('schedule.tasks.destroy');

    // Reports (xuất báo cáo)
    Route::get('/reports', [App\Http\Controllers\Web\ReportPageController::class, 'index'])->middleware('rbac:report.view')->name('reports.index');
    Route::post('/reports/export', [App\Http\Controllers\Web\ReportPageController::class, 'export'])->middleware('rbac:report.export')->name('reports.export');
    Route::get('/reports/cashflow', [App\Http\Controllers\Web\ReportPageController::class, 'cashflow'])->middleware('rbac:report.view')->name('reports.cashflow');

    // Webhooks (tích hợp hệ thống ngoài)
    Route::get('/webhooks', [App\Http\Controllers\Web\WebhookPageController::class, 'index'])->middleware('rbac:webhook.view')->name('webhooks.index');
    Route::post('/webhooks', [App\Http\Controllers\Web\WebhookPageController::class, 'store'])->middleware('rbac:webhook.manage')->name('webhooks.store');
    Route::post('/webhooks/{id}/toggle', [App\Http\Controllers\Web\WebhookPageController::class, 'toggle'])->middleware('rbac:webhook.manage')->name('webhooks.toggle');
    Route::delete('/webhooks/{id}', [App\Http\Controllers\Web\WebhookPageController::class, 'destroy'])->middleware('rbac:webhook.manage')->name('webhooks.destroy');

    // API tokens (Sanctum personal tokens — user manages own tokens)
    Route::get('/api-tokens', [App\Http\Controllers\Web\ApiTokenPageController::class, 'index'])->name('api-tokens.index');
    Route::post('/api-tokens', [App\Http\Controllers\Web\ApiTokenPageController::class, 'store'])->middleware('throttle:6,1')->name('api-tokens.store');
    Route::delete('/api-tokens/{id}', [App\Http\Controllers\Web\ApiTokenPageController::class, 'destroy'])->name('api-tokens.destroy');

    // Global Search (tìm kiếm xuyên module)
    Route::get('/search', [App\Http\Controllers\Web\GlobalSearchPageController::class, 'index'])->name('search.index');

    // Activity Feed (nhật ký hoạt động)
    Route::get('/activity-feed', [App\Http\Controllers\Web\ActivityFeedPageController::class, 'index'])->middleware('rbac:event-record.view')->name('activity-feed.index');

    // Site Diaries (nhật ký công trường)
    Route::get('/site-diaries', [App\Http\Controllers\Web\SiteDiaryPageController::class, 'index'])->middleware('rbac:site_diary.view')->name('site-diaries.index');
    Route::get('/site-diaries/create', [App\Http\Controllers\Web\SiteDiaryPageController::class, 'create'])->middleware('rbac:site_diary.create')->name('site-diaries.create');
    Route::post('/site-diaries', [App\Http\Controllers\Web\SiteDiaryPageController::class, 'store'])->middleware('rbac:site_diary.create')->name('site-diaries.store');
    Route::get('/site-diaries/{id}', [App\Http\Controllers\Web\SiteDiaryPageController::class, 'show'])->middleware('rbac:site_diary.view')->name('site-diaries.show');
    Route::post('/site-diaries/{id}/submit', [App\Http\Controllers\Web\SiteDiaryPageController::class, 'submit'])->middleware('rbac:site_diary.create')->name('site-diaries.submit');
    Route::post('/site-diaries/{id}/approve', [App\Http\Controllers\Web\SiteDiaryPageController::class, 'approve'])->middleware('rbac:site_diary.approve')->name('site-diaries.approve');

    // Receipts
    Route::get('/receipts', [App\Http\Controllers\Web\ReceiptPageController::class, 'index'])->name('receipts.index');
    Route::get('/receipts/create', [App\Http\Controllers\Web\ReceiptPageController::class, 'create'])->name('receipts.create');
    Route::post('/receipts', [App\Http\Controllers\Web\ReceiptPageController::class, 'store'])->name('receipts.store');
    Route::get('/receipts/{receipt}', [App\Http\Controllers\Web\ReceiptPageController::class, 'show'])->name('receipts.show');
    Route::post('/receipts/{receipt}/lines', [App\Http\Controllers\Web\ReceiptPageController::class, 'storeLine'])->name('receipts.lines.store');

    // Design Item (design-item kanban — spec zena-ops-roadmap Phase 1)
    Route::get('/design-items', [App\Http\Controllers\Web\DesignItemPageController::class, 'index'])->middleware('rbac:design-item.view')->name('design-items.index');
    Route::get('/design-items/create', [App\Http\Controllers\Web\DesignItemPageController::class, 'create'])->middleware('rbac:design-item.manage')->name('design-items.create');
    Route::post('/design-items', [App\Http\Controllers\Web\DesignItemPageController::class, 'store'])->middleware('rbac:design-item.manage')->name('design-items.store');
    Route::get('/design-items/{id}', [App\Http\Controllers\Web\DesignItemPageController::class, 'show'])->middleware('rbac:design-item.view')->name('design-items.show');
    Route::post('/design-items/{id}/status', [App\Http\Controllers\Web\DesignItemPageController::class, 'updateStatus'])->middleware('rbac:design-item.manage')->name('design-items.status');
    Route::post('/design-items/{id}/documents', [App\Http\Controllers\Web\DesignItemPageController::class, 'uploadDocument'])->middleware('rbac:design-item.manage')->name('design-items.documents.store');
    Route::post('/design-items/{id}/block', [App\Http\Controllers\Web\DesignItemPageController::class, 'block'])->middleware('rbac:design-item.manage')->name('design-items.block');
    Route::post('/design-items/{id}/unblock', [App\Http\Controllers\Web\DesignItemPageController::class, 'unblock'])->middleware('rbac:design-item.manage')->name('design-items.unblock');
    Route::post('/design-items/suggest-description', [App\Http\Controllers\Web\DesignItemPageController::class, 'suggestDescription'])->middleware(['rbac:design-item.manage', 'rbac:ai.suggest', 'throttle:ai-suggest'])->name('design-items.suggest-description');

    // Document Templates (Thư viện biểu mẫu)
    Route::get('/document-templates', [App\Http\Controllers\Web\DocumentTemplatePageController::class, 'index'])->middleware('rbac:document_template.view')->name('document-templates.index');
    Route::get('/document-templates/create', [App\Http\Controllers\Web\DocumentTemplatePageController::class, 'create'])->middleware('rbac:document_template.manage')->name('document-templates.create');
    Route::post('/document-templates', [App\Http\Controllers\Web\DocumentTemplatePageController::class, 'store'])->middleware('rbac:document_template.manage')->name('document-templates.store');
    Route::get('/document-templates/{id}/edit', [App\Http\Controllers\Web\DocumentTemplatePageController::class, 'edit'])->middleware('rbac:document_template.manage')->name('document-templates.edit');
    Route::post('/document-templates/{id}', [App\Http\Controllers\Web\DocumentTemplatePageController::class, 'update'])->middleware('rbac:document_template.manage')->name('document-templates.update');
    Route::post('/document-templates/{id}/preview', [App\Http\Controllers\Web\DocumentTemplatePageController::class, 'preview'])->middleware('rbac:document_template.view')->name('document-templates.preview');
    Route::post('/document-templates/{id}/publish', [App\Http\Controllers\Web\DocumentTemplatePageController::class, 'publish'])->middleware('rbac:document_template.manage')->name('document-templates.publish');

    // Knowledge base (SOP / checklist / lessons learned)
    Route::get('/knowledge', [App\Http\Controllers\Web\KnowledgeArticlePageController::class, 'index'])->middleware('rbac:knowledge.view')->name('knowledge.index');
    Route::get('/knowledge/create', [App\Http\Controllers\Web\KnowledgeArticlePageController::class, 'create'])->middleware('rbac:knowledge.manage')->name('knowledge.create');
    Route::post('/knowledge', [App\Http\Controllers\Web\KnowledgeArticlePageController::class, 'store'])->middleware('rbac:knowledge.manage')->name('knowledge.store');
    Route::get('/knowledge/{id}', [App\Http\Controllers\Web\KnowledgeArticlePageController::class, 'show'])->middleware('rbac:knowledge.view')->name('knowledge.show');
    Route::get('/knowledge/{id}/edit', [App\Http\Controllers\Web\KnowledgeArticlePageController::class, 'edit'])->middleware('rbac:knowledge.manage')->name('knowledge.edit');
    Route::post('/knowledge/{id}', [App\Http\Controllers\Web\KnowledgeArticlePageController::class, 'update'])->middleware('rbac:knowledge.manage')->name('knowledge.update');
    Route::post('/knowledge/{id}/publish', [App\Http\Controllers\Web\KnowledgeArticlePageController::class, 'publish'])->middleware('rbac:knowledge.manage')->name('knowledge.publish');
    Route::post('/knowledge/{id}/unpublish', [App\Http\Controllers\Web\KnowledgeArticlePageController::class, 'unpublish'])->middleware('rbac:knowledge.manage')->name('knowledge.unpublish');
    Route::delete('/knowledge/{id}', [App\Http\Controllers\Web\KnowledgeArticlePageController::class, 'destroy'])->middleware('rbac:knowledge.manage')->name('knowledge.destroy');

    // CRM (lead inbox → account/opportunity → project; spec crm-zena)
    Route::get('/crm', [App\Http\Controllers\Web\CrmPageController::class, 'index'])->middleware('rbac:crm.view')->name('crm.index');
    Route::get('/crm/leads', [App\Http\Controllers\Web\CrmPageController::class, 'leads'])->middleware('rbac:crm.view')->name('crm.leads');
    Route::post('/crm/leads', [App\Http\Controllers\Web\CrmPageController::class, 'storeLead'])->middleware('rbac:crm.manage')->name('crm.leads.store');
    Route::put('/crm/leads/{id}', [App\Http\Controllers\Web\CrmPageController::class, 'updateLead'])->middleware('rbac:crm.manage')->name('crm.leads.update');
    Route::post('/crm/leads/{id}/convert', [App\Http\Controllers\Web\CrmPageController::class, 'convertLead'])->middleware('rbac:crm.manage')->name('crm.leads.convert');
    Route::post('/crm/leads/{id}/discard', [App\Http\Controllers\Web\CrmPageController::class, 'discardLead'])->middleware('rbac:crm.manage')->name('crm.leads.discard');
    Route::post('/crm/leads/{id}/suggest-conversion', [App\Http\Controllers\Web\CrmPageController::class, 'suggestLeadConversion'])->middleware(['rbac:crm.manage', 'rbac:ai.suggest', 'throttle:ai-suggest'])->name('crm.leads.suggest-conversion');
    Route::post('/crm/opportunities/{id}/ai-summary', [App\Http\Controllers\Web\CrmPageController::class, 'summarizeOpportunity'])->middleware(['rbac:crm.view', 'rbac:ai.suggest', 'throttle:ai-suggest'])->name('crm.opportunities.ai-summary');
    Route::get('/crm/accounts', [App\Http\Controllers\Web\CrmPageController::class, 'accounts'])->middleware('rbac:crm.view')->name('crm.accounts');
    Route::post('/crm/accounts', [App\Http\Controllers\Web\CrmPageController::class, 'storeAccount'])->middleware('rbac:crm.manage')->name('crm.accounts.store');
    Route::get('/crm/opportunities/{id}', [App\Http\Controllers\Web\CrmPageController::class, 'showOpportunity'])->middleware('rbac:crm.view')->name('crm.opportunities.show');
    Route::post('/crm/opportunities/{id}/appointments', [App\Http\Controllers\Web\CrmPageController::class, 'storeAppointment'])->middleware('rbac:crm.manage')->name('crm.opportunities.appointments.store');
    Route::post('/crm/opportunities/{id}/stage', [App\Http\Controllers\Web\CrmPageController::class, 'updateStage'])->middleware('rbac:crm.manage')->name('crm.opportunities.stage');
    Route::post('/crm/opportunities/{id}/service-lines', [App\Http\Controllers\Web\CrmPageController::class, 'confirmServiceLines'])->middleware('rbac:crm.manage')->name('crm.opportunities.service-lines');
    Route::post('/crm/opportunities/{id}/convert', [App\Http\Controllers\Web\CrmPageController::class, 'convertOpportunity'])->middleware('rbac:crm.convert')->name('crm.opportunities.convert');
    Route::post('/crm/opportunities/{id}/boq-link', [App\Http\Controllers\Web\CrmPageController::class, 'linkBoqProject'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-link');
    Route::post('/crm/opportunities/{id}/boq-sync', [App\Http\Controllers\Web\CrmPageController::class, 'syncBoqQuote'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-sync');
    Route::post('/crm/opportunities/{id}/create-contract', [App\Http\Controllers\Web\CrmPageController::class, 'createContract'])->middleware('rbac:crm.manage')->name('crm.opportunities.create-contract');
    // Native quotes
    Route::get('/crm/quotes/{id}', [App\Http\Controllers\Web\CrmPageController::class, 'showQuote'])->middleware('rbac:crm.view')->name('crm.quotes.show');
    Route::post('/crm/opportunities/{id}/quotes', [App\Http\Controllers\Web\CrmPageController::class, 'storeQuote'])->middleware('rbac:crm.manage')->name('crm.opportunities.quotes.store');
    Route::post('/crm/quotes/{id}/lines', [App\Http\Controllers\Web\CrmPageController::class, 'saveQuoteLines'])->middleware('rbac:crm.manage')->name('crm.quotes.lines.save');
    Route::get('/crm/price-references/lookup', [App\Http\Controllers\Web\CrmPageController::class, 'lookupPriceReference'])->middleware('rbac:crm.view')->name('crm.price-references.lookup');
    Route::get('/crm/price-references/history', [App\Http\Controllers\Web\CrmPageController::class, 'priceReferenceHistory'])->middleware('rbac:crm.view')->name('crm.price-references.history');
    Route::post('/crm/quotes/{id}/send', [App\Http\Controllers\Web\CrmPageController::class, 'sendQuote'])->middleware('rbac:crm.manage')->name('crm.quotes.send');
    Route::post('/crm/quotes/{id}/accept', [App\Http\Controllers\Web\CrmPageController::class, 'acceptQuote'])->middleware('rbac:crm.manage')->name('crm.quotes.accept');
    Route::post('/crm/quotes/{id}/reject', [App\Http\Controllers\Web\CrmPageController::class, 'rejectQuote'])->middleware('rbac:crm.manage')->name('crm.quotes.reject');
    Route::post('/crm/quotes/{id}/revise', [App\Http\Controllers\Web\CrmPageController::class, 'reviseQuote'])->middleware('rbac:crm.manage')->name('crm.quotes.revise');
    Route::post('/crm/quotes/{id}/commercial', [App\Http\Controllers\Web\CrmPageController::class, 'saveQuoteCommercial'])->middleware('rbac:crm.manage')->name('crm.quotes.commercial');
    Route::post('/crm/appointments/{id}/complete', [App\Http\Controllers\Web\CrmPageController::class, 'completeAppointment'])->middleware('rbac:crm.manage')->name('crm.appointments.complete');
    Route::post('/crm/appointments/{id}/cancel', [App\Http\Controllers\Web\CrmPageController::class, 'cancelAppointment'])->middleware('rbac:crm.manage')->name('crm.appointments.cancel');
    Route::post('/crm/appointments/{id}/reschedule', [App\Http\Controllers\Web\CrmPageController::class, 'rescheduleAppointment'])->middleware('rbac:crm.manage')->name('crm.appointments.reschedule');
    Route::get('/crm/quotes/{id}/pdf', [App\Http\Controllers\Web\CrmPageController::class, 'quotePdf'])->middleware('rbac:crm.view')->name('crm.quotes.pdf');
    Route::get('/crm/quotes/{id}/render/{template}', [App\Http\Controllers\Web\CrmPageController::class, 'renderQuoteDocument'])->middleware('rbac:crm.view')->name('crm.quotes.render-document');
    Route::get('/crm/reports', [App\Http\Controllers\Web\CrmReportController::class, 'index'])->middleware('rbac:crm.view')->name('crm.reports');
});

Route::prefix('portal/{tenantSlug}')->as('portal.')->middleware(['web'])->group(function () {
    Route::get('/login', [App\Http\Controllers\Web\Portal\PortalAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\Web\Portal\PortalAuthController::class, 'sendLoginLink'])->middleware('throttle:6,1')->name('login.send');
    Route::get('/login/{token}', [App\Http\Controllers\Web\Portal\PortalAuthController::class, 'verify'])->middleware('throttle:10,1')->name('login.verify');
    Route::post('/logout', [App\Http\Controllers\Web\Portal\PortalAuthController::class, 'logout'])->name('logout');

    Route::middleware(['portal.auth'])->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Web\Portal\PortalDashboardController::class, 'index'])->name('dashboard');

        Route::get('/design-items/{id}', [App\Http\Controllers\Web\Portal\PortalDesignItemController::class, 'show'])->name('design-items.show');
        Route::post('/design-items/{id}/approve', [App\Http\Controllers\Web\Portal\PortalDesignItemController::class, 'approve'])->middleware('throttle:portal-actions')->name('design-items.approve');
        Route::post('/design-items/{id}/request-revision', [App\Http\Controllers\Web\Portal\PortalDesignItemController::class, 'requestRevision'])->middleware('throttle:portal-actions')->name('design-items.request-revision');

        Route::get('/quotes/{id}', [App\Http\Controllers\Web\Portal\PortalQuoteController::class, 'show'])->name('quotes.show');
        Route::get('/quotes/{id}/pdf', [App\Http\Controllers\Web\Portal\PortalQuoteController::class, 'pdf'])->name('quotes.pdf');
        Route::post('/quotes/{id}/accept', [App\Http\Controllers\Web\Portal\PortalQuoteController::class, 'accept'])->middleware('throttle:portal-actions')->name('quotes.accept');
        Route::post('/quotes/{id}/reject', [App\Http\Controllers\Web\Portal\PortalQuoteController::class, 'reject'])->middleware('throttle:portal-actions')->name('quotes.reject');
    });
});

Route::middleware(['web', 'auth:sanctum', 'tenant.isolation', 'rbac'])->prefix('api')->as('api.legacy.')->group(function () {
    Route::get('/dashboards', [DashboardResourceController::class, 'index'])->middleware('throttle:dashboards')->name('dashboards.index');
    Route::get('/dashboards/{dashboard}', [DashboardResourceController::class, 'show'])->name('dashboards.show');
    Route::post('/dashboards', [DashboardResourceController::class, 'store'])->name('dashboards.store');
    Route::put('/dashboards/{dashboard}', [DashboardResourceController::class, 'update'])->name('dashboards.update');
    Route::delete('/dashboards/{dashboard}', [DashboardResourceController::class, 'destroy'])->name('dashboards.destroy');
});
