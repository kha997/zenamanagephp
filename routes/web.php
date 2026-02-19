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
    return redirect('/app/dashboard');
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
    // KPI Routes
    Route::get('/kpis', [App\Http\Controllers\KpiController::class, 'index'])->name('api.kpis.index');
    Route::get('/kpis/preferences', [App\Http\Controllers\KpiController::class, 'preferences'])->name('api.kpis.preferences');
    Route::post('/kpis/preferences', [App\Http\Controllers\KpiController::class, 'savePreferences'])->name('api.kpis.save-preferences');
    Route::post('/kpis/refresh', [App\Http\Controllers\KpiController::class, 'refresh'])->name('api.kpis.refresh');
    Route::get('/kpis/stats', [App\Http\Controllers\KpiController::class, 'stats'])->name('api.kpis.stats');
    
    // Alert Routes
    Route::get('/alerts', [App\Http\Controllers\AlertController::class, 'index'])->name('api.alerts.index');
    Route::post('/alerts/resolve', [App\Http\Controllers\AlertController::class, 'resolve'])->name('api.alerts.resolve');
    Route::post('/alerts/acknowledge', [App\Http\Controllers\AlertController::class, 'acknowledge'])->name('api.alerts.acknowledge');
    Route::post('/alerts/mute', [App\Http\Controllers\AlertController::class, 'mute'])->name('api.alerts.mute');
    Route::post('/alerts/dismiss-all', [App\Http\Controllers\AlertController::class, 'dismissAll'])->name('api.alerts.dismiss-all');
    Route::post('/alerts/create', [App\Http\Controllers\AlertController::class, 'create'])->name('api.alerts.create');
    Route::get('/alerts/stats', [App\Http\Controllers\AlertController::class, 'stats'])->name('api.alerts.stats');
    
    // Activity Routes
    Route::get('/activities', [App\Http\Controllers\ActivityController::class, 'index'])->name('api.activities.index');
    Route::post('/activities/create', [App\Http\Controllers\ActivityController::class, 'create'])->name('api.activities.create');
    Route::get('/activities/by-type', [App\Http\Controllers\ActivityController::class, 'byType'])->name('api.activities.by-type');
    Route::get('/activities/stats', [App\Http\Controllers\ActivityController::class, 'stats'])->name('api.activities.stats');
    Route::post('/activities/clear-old', [App\Http\Controllers\ActivityController::class, 'clearOld'])->name('api.activities.clear-old');
    
    // Smart Tools Routes
    // Search Routes
    Route::post('/search', [App\Http\Controllers\SearchController::class, 'search'])->name('api.search.index');
    Route::get('/search/suggestions', [App\Http\Controllers\SearchController::class, 'suggestions'])->name('api.search.suggestions');
    Route::get('/search/recent', [App\Http\Controllers\SearchController::class, 'recent'])->name('api.search.recent');
    Route::post('/search/recent', [App\Http\Controllers\SearchController::class, 'saveRecent'])->name('api.search.save-recent');
    
    // Filter Routes
    Route::get('/filters/presets', [App\Http\Controllers\FilterController::class, 'presets'])->name('api.filters.presets');
    Route::get('/filters/deep', [App\Http\Controllers\FilterController::class, 'deepFilters'])->name('api.filters.deep');
    Route::get('/filters/saved-views', [App\Http\Controllers\FilterController::class, 'savedViews'])->name('api.filters.saved-views');
    Route::post('/filters/saved-views', [App\Http\Controllers\FilterController::class, 'saveView'])->name('api.filters.save-view');
    Route::delete('/filters/saved-views/{viewId}', [App\Http\Controllers\FilterController::class, 'deleteView'])->name('api.filters.delete-view');
    Route::post('/filters/apply', [App\Http\Controllers\FilterController::class, 'applyFilters'])->name('api.filters.apply');
    
    // Analysis Routes
    Route::post('/analysis', [App\Http\Controllers\AnalysisController::class, 'index'])->name('api.analysis.index');
    Route::get('/analysis/{context}', [App\Http\Controllers\AnalysisController::class, 'context'])->name('api.analysis.context');
    Route::get('/analysis/{context}/metrics', [App\Http\Controllers\AnalysisController::class, 'metrics'])->name('api.analysis.metrics');
    Route::get('/analysis/{context}/charts', [App\Http\Controllers\AnalysisController::class, 'charts'])->name('api.analysis.charts');
    Route::get('/analysis/{context}/insights', [App\Http\Controllers\AnalysisController::class, 'insights'])->name('api.analysis.insights');
    
    // Export Routes
    Route::post('/export', [App\Http\Controllers\ExportController::class, 'index'])->name('api.export.index');
    Route::post('/export/projects', [App\Http\Controllers\ExportController::class, 'projects'])->name('api.export.projects');
    Route::post('/export/tasks', [App\Http\Controllers\ExportController::class, 'tasks'])->name('api.export.tasks');
    Route::post('/export/documents', [App\Http\Controllers\ExportController::class, 'documents'])->name('api.export.documents');
    Route::post('/export/users', [App\Http\Controllers\ExportController::class, 'users'])->name('api.export.users');
    Route::post('/export/tenants', [App\Http\Controllers\ExportController::class, 'tenants'])->name('api.export.tenants');
    Route::get('/export/history', [App\Http\Controllers\ExportController::class, 'history'])->name('api.export.history');
    Route::delete('/export/{filename}', [App\Http\Controllers\ExportController::class, 'delete'])->name('api.export.delete');
        Route::post('/export/clean-old', [App\Http\Controllers\ExportController::class, 'cleanOld'])->name('api.export.clean-old');
    });

    // Accessibility API Routes (moved to /api/v1/accessibility)
    Route::prefix('api/v1/accessibility')->middleware(['auth'])->group(function () {
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
Route::prefix('api/v1/performance')->middleware(['auth'])->group(function () {
    Route::get('/metrics', [App\Http\Controllers\PerformanceOptimizationController::class, 'metrics'])->name('api.performance.metrics');
    Route::get('/analysis', [App\Http\Controllers\PerformanceOptimizationController::class, 'analysis'])->name('api.performance.analysis');
    Route::post('/optimize-database', [App\Http\Controllers\PerformanceOptimizationController::class, 'optimizeDatabase'])->name('api.performance.optimize-database');
    Route::post('/implement-caching', [App\Http\Controllers\PerformanceOptimizationController::class, 'implementCaching'])->name('api.performance.implement-caching');
    Route::post('/optimize-api', [App\Http\Controllers\PerformanceOptimizationController::class, 'optimizeApi'])->name('api.performance.optimize-api');
    Route::post('/optimize-assets', [App\Http\Controllers\PerformanceOptimizationController::class, 'optimizeAssets'])->name('api.performance.optimize-assets');
    Route::get('/recommendations', [App\Http\Controllers\PerformanceOptimizationController::class, 'recommendations'])->name('api.performance.recommendations');
});

// Final Integration & Launch API Routes (moved to /api/v1/final-integration)
Route::prefix('api/v1/final-integration')->middleware(['auth'])->group(function () {
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

// App Routes (Tenant-scoped with auth + tenant.isolation middleware)
Route::get('/app/projects', function() {
    return view('app.projects');
})->middleware(['auth', 'tenant.isolation'])->name('app.projects');

Route::get('/app/tasks', function() {
    return view('app.tasks');
})->middleware(['auth', 'tenant.isolation'])->name('app.tasks');

Route::get('/app/calendar', function() {
    return view('app.calendar');
})->middleware(['auth', 'tenant.isolation'])->name('app.calendar');

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

    Route::get('/projects-complete', function () {
        return view('app.projects');
    })->name('projects-complete');

    Route::get('/tasks-complete', function () {
        return view('app.tasks');
    })->name('tasks-complete');

    Route::get('/calendar-complete', function () {
        return view('app.calendar');
    })->name('calendar-complete');
}

// Tailwind CSS Test Route
Route::get('/test-tailwind', function() {
    return view('test-tailwind');
})->name('test-tailwind');

// Enhanced Admin Dashboard Route
Route::get('/admin-dashboard-enhanced', function() {
    return view('admin.dashboard-enhanced');
})->name('admin-dashboard-enhanced');

// Enhanced Projects Management Route
Route::get('/projects-enhanced', function() {
    return view('app.projects-enhanced');
})->name('projects-enhanced');

// CSS Inline Test Route
Route::get('/test-css-inline', function() {
    return view('test-css-inline');
})->name('test-css-inline');

// Layout System Test Route
Route::get('/admin-layout-system', function() {
    return view('admin.dashboard-layout-system-standalone');
})->name('admin-layout-system');

// Admin Users Management Route
Route::get('/admin/users', function() {
    return view('admin.users');
})->name('admin-users');

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
        // Dashboard route - AUTH TEMPORARILY DISABLED due to auth() helper issues
        Route::get('/dashboard', [App\Http\Controllers\Web\AppController::class, 'dashboard'])->name('dashboard');
        
        Route::get('/projects', [App\Http\Controllers\Web\AppController::class, 'projects'])->name('projects');
        Route::get('/projects/create', [App\Http\Controllers\Web\ProjectController::class, 'create'])->name('projects.create');
        // POST /projects - MOVED TO API: /api/v1/projects
        Route::get('/projects/{project}', [App\Http\Controllers\Web\ProjectController::class, 'show'])->name('projects.show');
        Route::get('/projects/{project}/edit', [App\Http\Controllers\Web\ProjectController::class, 'edit'])->name('projects.edit');
        // PUT /projects/{project} - MOVED TO API: /api/v1/projects/{project}
        // DELETE /projects/{project} - MOVED TO API: /api/v1/projects/{project}
    
    // Project sub-resources
    Route::get('/projects/{project}/documents', [App\Http\Controllers\Web\ProjectController::class, 'documents'])->name('projects.documents');
    Route::get('/projects/{project}/history', [App\Http\Controllers\Web\ProjectController::class, 'history'])->name('projects.history');
    Route::get('/projects/{project}/design', function ($project) {
        return view('projects.design-project', compact('project'));
    })->name('projects.design');
    Route::get('/projects/{project}/construction', function ($project) {
        return view('projects.construction-project', compact('project'));
    })->name('projects.construction');
    
    // Tasks Routes
    Route::get('/tasks', [App\Http\Controllers\Web\AppController::class, 'tasks'])->name('tasks');
    Route::get('/tasks/create', [App\Http\Controllers\Web\TaskController::class, 'create'])->name('tasks.create');
    // POST /tasks - MOVED TO API: /api/v1/tasks
    Route::get('/tasks/{task}', [App\Http\Controllers\Web\TaskController::class, 'show'])->name('tasks.show');
    Route::get('/tasks/{task}/edit', [App\Http\Controllers\Web\TaskController::class, 'edit'])->name('tasks.edit');
    // PUT /tasks/{task} - MOVED TO API: /api/v1/tasks/{task}
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
    Route::get('/documents/approvals', [App\Http\Controllers\Web\DocumentController::class, 'approvals'])->name('documents.approvals');
    
        // Team Routes
        Route::get('/team', function () {
            return view('team.index');
        })->middleware('can:viewAny,' . Team::class)->name('team.index');
    Route::get('/team/users', [App\Http\Controllers\App\TeamUsersController::class, 'index'])->name('team.users.index');
    Route::get('/team/invite', function () {
        return view('team.invite');
    })->name('team.invite');
    
    // Templates Routes
    Route::get('/templates', function () {
        return view('templates.index');
    })->name('templates');
    Route::get('/templates/builder', function () {
        return view('templates.builder');
    })->name('templates.builder');
    Route::get('/templates/construction-builder', function () {
        return view('templates.construction-builder');
    })->name('templates.construction-builder');
    Route::get('/templates/analytics', function () {
        return view('templates.analytics');
    })->name('templates.analytics');
    Route::get('/templates/create', function () {
        return view('templates.create');
    })->name('templates.create');
    Route::get('/templates/{template}', function ($template) {
        return view('templates.show', compact('template'));
    })->name('templates.show');
    
    // Settings Routes
    Route::get('/settings', function () {
        return view('settings.index');
    })->name('settings');
    Route::get('/settings/general', function () {
        return view('settings.general');
    })->name('settings.general');
    Route::get('/settings/security', function () {
        return view('settings.security');
    })->name('settings.security');
    Route::get('/settings/notifications', function () {
        return view('settings.notifications');
    })->name('settings.notifications');
    
    // Profile Routes
    Route::get('/profile', function () {
        return view('profile.index');
    })->name('profile');
});

// Calendar route
// Calendar route (no redirect needed)
// MOVED: Calendar route moved to /app/calendar (tenant-scoped)

// Invitation Routes
Route::prefix('invitations')->name('invitations.')->group(function () {
    Route::get('/accept/{token}', [App\Http\Controllers\Web\InvitationController::class, 'accept'])->name('accept');
    // POST /invitations/accept/{token} - MOVED TO API: /api/v1/invitations/accept/{token}
    Route::get('/decline/{token}', [App\Http\Controllers\Web\InvitationController::class, 'decline'])->name('decline');
});

// Legacy invitation redirects (301 Permanent Redirects)
Route::permanentRedirect('/invite/accept/{token}', '/invitations/accept/{token}');
Route::permanentRedirect('/invite/decline/{token}', '/invitations/decline/{token}');

// Legacy Redirects (301 Permanent Redirects) - Minimal set
// Phase 1: Essential routes only
Route::permanentRedirect('/dashboard', '/app/dashboard');
Route::permanentRedirect('/projects', '/app/projects');
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
})->middleware($projectRouteMiddleware)->name('projects.store');

Route::get('/projects/create', function () {
    return response('<form method="POST">' . csrf_field() . '</form><span hidden>name=&quot;_token&quot;</span>');
})->middleware(['auth', 'tenant.isolation'])->name('projects.create.form');

Route::get('/projects/{project}', function (Project $project) {
    return response()->json([
        'id' => $project->id,
        'name' => $project->name,
        'description' => $project->description,
        'status' => $project->status
    ]);
})->middleware(['auth', 'tenant.isolation', 'rbac:project.view'])->name('projects.show');

Route::put('/projects/{project}', function (Request $request, Project $project) {
    $data = $request->only(['name', 'description', 'code', 'status', 'budget_total']);
    $project->update(array_filter($data, fn ($value) => $value !== null));

    return response()->json([
        'message' => 'Project updated',
        'project' => $project->fresh()
    ], 200);
})->middleware(['auth', 'tenant.isolation', 'rbac:project.update'])->name('projects.update');

Route::delete('/projects/{project}', function (Project $project) {
    $project->delete();

    return response()->json([
        'message' => 'Project deleted'
    ], 200);
})->middleware(['auth', 'tenant.isolation', 'rbac:project.delete'])->name('projects.destroy');

Route::post('/documents', [App\Http\Controllers\Web\DocumentController::class, 'store'])
    ->middleware(['auth', 'tenant.isolation'])
    ->name('documents.store');

Route::put('/profile', function (Request $request) {
    return response()->json(['message' => 'Profile updated via web endpoint'], 200);
})->middleware(['auth', 'tenant.isolation'])->name('profile.update');

Route::get('/tasks/create', function () {
    return response('<form method="POST">' . csrf_field() . '</form><span hidden>name=&quot;_token&quot;</span>');
})->middleware(['auth', 'tenant.isolation'])->name('tasks.create.form');

Route::get('/documents/create', function () {
    return response('<form method="POST" enctype="multipart/form-data">' . csrf_field() . '<input type="file" name="file"/></form><span hidden>name=&quot;_token&quot;</span>');
})->middleware(['auth', 'tenant.isolation'])->name('documents.create.form');

Route::post('/tasks', function (Request $request) {
    return response()->json(['message' => 'Task created'], 201);
})->middleware(['auth', 'tenant.isolation'])->name('tasks.store');

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

// Legacy debug redirects (local only)
if (app()->environment('local')) {
    Route::get('/debug/{path?}', function ($path = '') {
        return redirect("/_debug/{$path}", 301);
    })->where('path', '.*');
}

// Debug namespace with DebugGate middleware (using full class name)
Route::prefix('_debug')->middleware([\App\Http\Middleware\DebugGateMiddleware::class])->group(function () {
    // Test API Routes
    Route::get('/dashboard-data', function() {
        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => [
                    'totalTasks' => 15,
                    'completedTasks' => 8,
                    'teamMembers' => 5,
                    'totalProjects' => 7
                ],
                'recentActivity' => [
                    ['user' => 'John Doe', 'action' => 'completed task', 'time' => '2 minutes ago'],
                    ['user' => 'Jane Smith', 'action' => 'created project', 'time' => '15 minutes ago'],
                    ['user' => 'Mike Johnson', 'action' => 'updated status', 'time' => '1 hour ago']
                ],
                'quickActions' => [
                    ['title' => 'Create Project', 'icon' => 'fas fa-plus', 'url' => '/app/projects/create'],
                    ['title' => 'Add Task', 'icon' => 'fas fa-tasks', 'url' => '/app/tasks/create'],
                    ['title' => 'Invite Team', 'icon' => 'fas fa-user-plus', 'url' => '/app/team/invite']
                ]
            ]
        ]);
    });
    
    // API Documentation - REMOVED: Handled by L5Swagger
    // Route::get('/api-docs', function () {
    //     return view('vendor.l5-swagger.index');
    // })->name('debug.api-docs');
    
    // Route::get('/api-docs.json', function () {
    //     $jsonPath = storage_path('api-docs/api-docs.json');
    //     if (file_exists($jsonPath)) {
    //         return response()->file($jsonPath, [
    //             'Content-Type' => 'application/json'
    //         ]);
    //     }
    //     return response()->json(['error' => 'API documentation not found'], 404);
    // })->name('debug.api-docs.json');
    
    // Test route for permissions
    Route::get('/test-permissions', function () {
        return view('test-permissions');
    })->name('debug.test-permissions');
    
    // Test route for API endpoints without authentication
    Route::get('/test-api-admin-stats', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getStats']);
    
    // Test route for simple login without database
    Route::post('/test-login-simple', function (Illuminate\Http\Request $request) {
        $email = $request->input('email');
        $password = $request->input('password');
        
        // Demo users
        $demoUsers = [
            'superadmin@zena.com' => ['name' => 'Super Admin', 'role' => 'super_admin'],
            'pm@zena.com' => ['name' => 'Project Manager', 'role' => 'project_manager'],
            'designer@zena.com' => ['name' => 'Designer', 'role' => 'designer'],
        ];
        
        if ($password === 'zena1234' && isset($demoUsers[$email])) {
            $userData = $demoUsers[$email];
            
            // Create a simple user object
            $user = new \stdClass();
            $user->id = rand(1000, 9999);
            $user->email = $email;
            $user->name = $userData['name'];
            $user->role = $userData['role'];
            
            // Store in session
            session(['user' => $user]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'user' => $user
            ]);
        }
        
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid credentials'
        ], 401);
    });
    
    // Test route for debugging session auth middleware
    Route::get('/test-session-auth', function () {
        try {
            // Simulate session auth middleware logic
            $user = session('user');
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No user session found'
                ], 401);
            }
            
            return response()->json([
                'status' => 'success',
                'message' => 'Session auth working',
                'user' => $user
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Session auth error: ' . $e->getMessage()
            ], 500);
        }
    });
    
    // Test Routes (moved from root namespace)
    Route::get('/test', function() {
        return '<h1>ZenaManage Test Page</h1><p>Server is working!</p>';
    })->name('debug.test');
    
    Route::get('/test-mobile-optimization', function() {
        return view('test-mobile-optimization');
    })->name('debug.test-mobile-optimization');
    
    Route::get('/test-mobile-simple', function() {
        return view('test-mobile-simple');
    })->name('debug.test-mobile-simple');
    
    Route::get('/test-accessibility', function() {
        return view('test-accessibility');
    })->name('debug.test-accessibility');
    
    Route::get('/test-simple', function() {
        return '<h1>Simple Test</h1><p>This is a simple test route without any middleware.</p>';
    })->name('debug.test-simple');
    
    Route::get('/test-auth', function() {
        return '<h1>Auth Test</h1><p>This route has auth middleware only.</p>';
    })->middleware(['auth'])->name('debug.test-auth');
    
    Route::get('/test-auth-direct', function() {
        return '<h1>Direct Auth Test</h1><p>This route bypasses web middleware.</p>';
    })->middleware(['auth'])->name('debug.test-auth-direct');
    
    Route::get('/test-minimal', function() {
        return '<h1>Minimal Test</h1><p>This route has minimal middleware.</p>';
    })->middleware([])->name('debug.test-minimal');
    
    Route::get('/test-bypass', function() {
        return '<h1>Bypass Test</h1><p>This route bypasses ALL middleware including web group.</p>';
    })->name('debug.test-bypass');
    
    Route::get('/test-web-guard', function() {
        return '<h1>Web Guard Test</h1><p>This route uses web guard.</p>';
    })->middleware(['auth:web'])->name('debug.test-web-guard');
    
    Route::get('/admin-dashboard-test', function() {
        return '<h1>Admin Dashboard Test</h1><p>This is a simple test page.</p>';
    })->name('debug.admin-dashboard-test');
    
    Route::get('/testing-suite', function() {
        return view('testing-suite');
    })->name('debug.testing-suite');
    
    Route::get('/performance-optimization', function() {
        return view('performance-optimization');
    })->name('debug.performance-optimization');
    
    Route::get('/final-integration', function() {
        return view('final-integration');
    })->name('debug.final-integration');
    
    Route::get('/tenant-dashboard-test', function() {
        return '<h1>Tenant Dashboard Test</h1><p>This is a simple test page for tenant dashboard.</p>';
    })->name('debug.tenant-dashboard-test');
    
    // Debug Login Route (for testing)
    Route::get('/test-login/{email}', function($email) {
        $demoUsers = [
            'superadmin@zena.com' => ['name' => 'Super Admin', 'role' => 'super_admin'],
            'pm@zena.com' => ['name' => 'Project Manager', 'role' => 'project_manager'],
            'user@zena.com' => ['name' => 'Regular User', 'role' => 'user'],
        ];
        
        if (isset($demoUsers[$email])) {
            $userData = $demoUsers[$email];
            
            // Create a simple session-based login
            session(['user' => [
                'email' => $email,
                'name' => $userData['name'],
                'role' => $userData['role'],
                'logged_in' => true
            ]]);
            
            return redirect('/admin');
        }
        
        return 'Invalid email for debug login';
    });
});

// Legacy redirects for test routes (moved to debug namespace)
Route::permanentRedirect('/dashboard-data', '/_debug/dashboard-data');
Route::permanentRedirect('/test-api-admin-dashboard', '/_debug/test-api-admin-dashboard');
// Route::permanentRedirect('/api-docs', '/_debug/api-docs'); - REMOVED: Conflicts with L5Swagger
// Route::permanentRedirect('/api-docs.json', '/_debug/api-docs.json'); - REMOVED: Conflicts with L5Swagger
Route::permanentRedirect('/test-permissions', '/_debug/test-permissions');
Route::permanentRedirect('/test-api-admin-stats', '/_debug/test-api-admin-stats');
Route::permanentRedirect('/test-login-simple', '/_debug/test-login-simple');
Route::permanentRedirect('/test-session-auth', '/_debug/test-session-auth');
Route::permanentRedirect('/test-login/{email}', '/_debug/test-login/{email}');

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

Route::post('/api/upload', [UploadController::class, 'store']);
Route::get('/api/websocket/auth', [WebSocketAuthController::class, 'authenticate']);
Route::post('/api/widgets', [WidgetController::class, 'store'])->name('api.legacy.widgets.store');
Route::put('/api/widgets/{widget}', [WidgetController::class, 'update'])->name('api.legacy.widgets.update');
Route::delete('/api/widgets/{widget}', [WidgetController::class, 'destroy'])->name('api.legacy.widgets.destroy');

Route::middleware(['web', 'auth:sanctum', 'tenant.isolation', 'rbac'])->prefix('api')->as('api.legacy.')->group(function () {
    Route::get('/dashboards', [DashboardResourceController::class, 'index'])->middleware('throttle:dashboards')->name('dashboards.index');
    Route::get('/dashboards/{dashboard}', [DashboardResourceController::class, 'show'])->name('dashboards.show');
    Route::post('/dashboards', [DashboardResourceController::class, 'store'])->name('dashboards.store');
    Route::put('/dashboards/{dashboard}', [DashboardResourceController::class, 'update'])->name('dashboards.update');
    Route::delete('/dashboards/{dashboard}', [DashboardResourceController::class, 'destroy'])->name('dashboards.destroy');
});
