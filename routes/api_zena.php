<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

// Test route to verify this file is being loaded
Route::get('/zena-file-test', function () {
    return response()->json(['message' => 'Z.E.N.A file routes are working!']);
});

/*
|--------------------------------------------------------------------------
| Z.E.N.A API Routes
|--------------------------------------------------------------------------
*/

// Group all Z.E.N.A routes under /zena prefix
Route::group(['prefix' => 'zena'], function () {

    // Main API info route
    Route::get('/', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Z.E.N.A API is running',
            'version' => '1.0.0',
            'endpoints' => [
                'auth' => [
                    'POST /api/zena/auth/login' => 'User login',
                    'POST /api/zena/auth/logout' => 'User logout',
                    'GET /api/zena/auth/me' => 'Get user profile',
                    'POST /api/zena/auth/refresh' => 'Refresh token',
                    'GET /api/zena/auth/dashboard-url' => 'Get dashboard URL',
                    'POST /api/zena/auth/check-permission' => 'Check user permission',
                    'GET /api/zena/auth/notifications' => 'Get user notifications',
                    'POST /api/zena/auth/notifications/{id}/read' => 'Mark notification as read',
                ],
                'dashboard' => [
                    'GET /api/zena/dashboard' => 'Get dashboard overview',
                    'GET /api/zena/dashboard/widgets' => 'Get dashboard widgets',
                    'GET /api/zena/dashboard/metrics' => 'Get dashboard metrics',
                    'GET /api/zena/dashboard/alerts' => 'Get dashboard alerts',
                ],
                'role-specific' => [
                    'GET /api/zena/pm/dashboard' => 'PM Dashboard',
                    'GET /api/zena/designer/dashboard' => 'Designer Dashboard',
                    'GET /api/zena/site-engineer/dashboard' => 'Site Engineer Dashboard',
                ]
            ],
            'timestamp' => now(),
        ]);
    })->name('api.info');

    // Public routes (no authentication required)
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    });

    // Test route completely outside middleware
    Route::get('/simple-test', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Simple test working',
            'timestamp' => now(),
        ]);
    });

    // Test route with minimal auth check
    Route::get('/minimal-auth-test', function () {
        try {
            $user = auth()->user();
            return response()->json([
                'status' => 'success',
                'message' => 'Minimal auth test working',
                'user' => $user ? $user->id : null,
                'timestamp' => now(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Auth error: ' . $e->getMessage(),
                'timestamp' => now(),
            ]);
        }
    });

    // Test route with Sanctum auth check
    Route::get('/sanctum-auth-test', function () {
        try {
            $user = auth('sanctum')->user();
            return response()->json([
                'status' => 'success',
                'message' => 'Sanctum auth test working',
                'user' => $user ? $user->id : null,
                'timestamp' => now(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sanctum auth error: ' . $e->getMessage(),
                'timestamp' => now(),
            ]);
        }
    });

    // Test route that mimics AuthController::me without middleware
    Route::get('/me-test', function () {
        try {
            $user = auth('sanctum')->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                ], 401);
            }
            
            // Get user roles and permissions
            $roles = $user->roles()->pluck('name')->toArray();
            $permissions = $user->roles()->with('permissions')->get()
                ->pluck('permissions')
                ->flatten()
                ->pluck('name')
                ->unique()
                ->toArray();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'avatar' => $user->avatar,
                        'roles' => $roles,
                        'permissions' => $permissions,
                        'last_login_at' => $user->last_login_at,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
                'timestamp' => now(),
            ]);
        }
    });

    // Test route with simple auth middleware
    Route::get('/auth-test', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Auth test working',
            'user' => auth()->user(),
            'timestamp' => now(),
        ]);
    })->middleware('auth:sanctum');

// Protected routes (authentication required) - Using manual auth check instead of middleware
Route::group(['prefix' => 'auth'], function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    Route::get('/dashboard-url', [AuthController::class, 'getDashboardUrl'])->name('auth.dashboard-url');
    Route::post('/check-permission', [AuthController::class, 'checkPermission'])->name('auth.check-permission');
    
    // Notifications
    Route::get('/notifications', [AuthController::class, 'getNotifications'])->name('auth.notifications');
    Route::post('/notifications/{id}/read', [AuthController::class, 'markNotificationAsRead'])->name('auth.notifications.read');
});

    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Z.E.N.A API is running',
            'timestamp' => now(),
        ]);
    })->name('api.health');

    // Test route without authentication
    Route::get('/test', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Test route working',
            'timestamp' => now(),
        ]);
    })->name('api.test');

    // Dashboard routes - DISABLED (Controller not implemented)
    /*
    Route::group(['prefix' => 'dashboard'], function () {
        Route::get('/', [\App\Http\Controllers\Api\ZenaDashboardController::class, 'getDashboard'])->name('dashboard.overview');
        Route::get('/widgets', [\App\Http\Controllers\Api\ZenaDashboardController::class, 'getWidgets'])->name('dashboard.widgets');
        Route::get('/metrics', [\App\Http\Controllers\Api\ZenaDashboardController::class, 'getMetrics'])->name('dashboard.metrics');
        Route::get('/alerts', [\App\Http\Controllers\Api\ZenaDashboardController::class, 'getAlerts'])->name('dashboard.alerts');
        Route::get('/projects', [\App\Http\Controllers\Api\ZenaDashboardController::class, 'getAvailableProjects'])->name('dashboard.projects');
        Route::post('/switch-project', [\App\Http\Controllers\Api\ZenaDashboardController::class, 'switchProjectContext'])->name('dashboard.switch-project');
    });
    */

    // Role-specific dashboard routes
    Route::group(['prefix' => 'pm'], function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\PmDashboardController::class, 'getOverview'])->name('pm.dashboard');
        Route::get('/progress', [\App\Http\Controllers\Api\PmDashboardController::class, 'getProjectProgress'])->name('pm.progress');
        Route::get('/risks', [\App\Http\Controllers\Api\PmDashboardController::class, 'getRiskAssessment'])->name('pm.risks');
        Route::get('/weekly-report', [\App\Http\Controllers\Api\PmDashboardController::class, 'getWeeklyReport'])->name('pm.weekly-report');
    });

    Route::group(['prefix' => 'designer'], function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\DesignerDashboardController::class, 'getOverview'])->name('designer.dashboard');
        Route::get('/tasks', [\App\Http\Controllers\Api\DesignerDashboardController::class, 'getDesignTasks'])->name('designer.tasks');
        Route::get('/drawings', [\App\Http\Controllers\Api\DesignerDashboardController::class, 'getDrawingsStatus'])->name('designer.drawings');
        Route::get('/rfis', [\App\Http\Controllers\Api\DesignerDashboardController::class, 'getRfisToAnswer'])->name('designer.rfis');
        Route::get('/submittals', [\App\Http\Controllers\Api\DesignerDashboardController::class, 'getSubmittalsStatus'])->name('designer.submittals');
        Route::get('/workload', [\App\Http\Controllers\Api\DesignerDashboardController::class, 'getDesignWorkload'])->name('designer.workload');
    });

    Route::group(['prefix' => 'site-engineer'], function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getOverview'])->name('site-engineer.dashboard');
        Route::get('/tasks', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getSiteTasks'])->name('site-engineer.tasks');
        Route::get('/material-requests', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getMaterialRequests'])->name('site-engineer.material-requests');
        Route::get('/rfis', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getSiteRfis'])->name('site-engineer.rfis');
        Route::get('/inspections', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getQcInspections'])->name('site-engineer.inspections');
        Route::get('/safety', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getSiteSafetyStatus'])->name('site-engineer.safety');
        Route::get('/daily-report', [\App\Http\Controllers\Api\SiteEngineerDashboardController::class, 'getDailySiteReport'])->name('site-engineer.daily-report');
    });

    // Project Management routes
    Route::group(['prefix' => 'projects'], function () {
        Route::get('/', [\App\Http\Controllers\Api\ProjectController::class, 'index'])->name('projects.index');
        Route::post('/', [\App\Http\Controllers\Api\ProjectController::class, 'store'])->name('projects.store');
        Route::get('/{id}', [\App\Http\Controllers\Api\ProjectController::class, 'show'])->name('projects.show');
        Route::put('/{id}', [\App\Http\Controllers\Api\ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/{id}', [\App\Http\Controllers\Api\ProjectController::class, 'destroy'])->name('projects.destroy');
    });

    // Tasks Management routes
    Route::group(['prefix' => 'tasks'], function () {
        Route::get('/', [\App\Http\Controllers\Api\TaskController::class, 'index'])->name('tasks.index');
        Route::post('/', [\App\Http\Controllers\Api\TaskController::class, 'store'])->name('tasks.store');
        Route::get('/{id}', [\App\Http\Controllers\Api\TaskController::class, 'show'])->name('tasks.show');
        Route::put('/{id}', [\App\Http\Controllers\Api\TaskController::class, 'update'])->name('tasks.update');
        Route::delete('/{id}', [\App\Http\Controllers\Api\TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::patch('/{id}/status', [\App\Http\Controllers\Api\TaskController::class, 'updateStatus'])->name('tasks.update-status');
        Route::get('/{id}/dependencies', [\App\Http\Controllers\Api\TaskController::class, 'getDependencies'])->name('tasks.dependencies');
        Route::post('/{id}/dependencies', [\App\Http\Controllers\Api\TaskController::class, 'addDependency'])->name('tasks.add-dependency');
        Route::delete('/{id}/dependencies/{dependencyId}', [\App\Http\Controllers\Api\TaskController::class, 'removeDependency'])->name('tasks.remove-dependency');
    });

    // RFI (Request for Information) routes
    Route::group(['prefix' => 'rfis'], function () {
        Route::get('/', [\App\Http\Controllers\Api\RfiController::class, 'index'])->name('rfis.index');
        Route::post('/', [\App\Http\Controllers\Api\RfiController::class, 'store'])->name('rfis.store');
        Route::get('/{id}', [\App\Http\Controllers\Api\RfiController::class, 'show'])->name('rfis.show');
        Route::put('/{id}', [\App\Http\Controllers\Api\RfiController::class, 'update'])->name('rfis.update');
        Route::delete('/{id}', [\App\Http\Controllers\Api\RfiController::class, 'destroy'])->name('rfis.destroy');
        Route::post('/{id}/assign', [\App\Http\Controllers\Api\RfiController::class, 'assign'])->name('rfis.assign');
        Route::post('/{id}/respond', [\App\Http\Controllers\Api\RfiController::class, 'respond'])->name('rfis.respond');
        Route::post('/{id}/close', [\App\Http\Controllers\Api\RfiController::class, 'close'])->name('rfis.close');
        Route::post('/{id}/escalate', [\App\Http\Controllers\Api\RfiController::class, 'escalate'])->name('rfis.escalate');
    });

    // Submittals routes
    Route::group(['prefix' => 'submittals'], function () {
        Route::get('/', [\App\Http\Controllers\Api\SubmittalController::class, 'index'])->name('submittals.index');
        Route::post('/', [\App\Http\Controllers\Api\SubmittalController::class, 'store'])->name('submittals.store');
        Route::get('/{id}', [\App\Http\Controllers\Api\SubmittalController::class, 'show'])->name('submittals.show');
        Route::put('/{id}', [\App\Http\Controllers\Api\SubmittalController::class, 'update'])->name('submittals.update');
        Route::delete('/{id}', [\App\Http\Controllers\Api\SubmittalController::class, 'destroy'])->name('submittals.destroy');
        Route::post('/{id}/submit', [\App\Http\Controllers\Api\SubmittalController::class, 'submit'])->name('submittals.submit');
        Route::post('/{id}/review', [\App\Http\Controllers\Api\SubmittalController::class, 'review'])->name('submittals.review');
        Route::post('/{id}/approve', [\App\Http\Controllers\Api\SubmittalController::class, 'approve'])->name('submittals.approve');
        Route::post('/{id}/reject', [\App\Http\Controllers\Api\SubmittalController::class, 'reject'])->name('submittals.reject');
    });

    // Change Requests routes
    Route::group(['prefix' => 'change-requests'], function () {
        Route::get('/', [\App\Http\Controllers\Api\ChangeRequestController::class, 'index'])->name('change-requests.index');
        Route::post('/', [\App\Http\Controllers\Api\ChangeRequestController::class, 'store'])->name('change-requests.store');
        Route::get('/{id}', [\App\Http\Controllers\Api\ChangeRequestController::class, 'show'])->name('change-requests.show');
        Route::put('/{id}', [\App\Http\Controllers\Api\ChangeRequestController::class, 'update'])->name('change-requests.update');
        Route::delete('/{id}', [\App\Http\Controllers\Api\ChangeRequestController::class, 'destroy'])->name('change-requests.destroy');
        Route::post('/{id}/submit', [\App\Http\Controllers\Api\ChangeRequestController::class, 'submit'])->name('change-requests.submit');
        Route::post('/{id}/approve', [\App\Http\Controllers\Api\ChangeRequestController::class, 'approve'])->name('change-requests.approve');
        Route::post('/{id}/reject', [\App\Http\Controllers\Api\ChangeRequestController::class, 'reject'])->name('change-requests.reject');
        Route::post('/{id}/apply', [\App\Http\Controllers\Api\ChangeRequestController::class, 'apply'])->name('change-requests.apply');
    });

    // Inspections routes
    Route::group(['prefix' => 'inspections'], function () {
        Route::get('/', [\App\Http\Controllers\Api\InspectionController::class, 'index'])->name('inspections.index');
        Route::post('/', [\App\Http\Controllers\Api\InspectionController::class, 'store'])->name('inspections.store');
        Route::get('/{id}', [\App\Http\Controllers\Api\InspectionController::class, 'show'])->name('inspections.show');
        Route::put('/{id}', [\App\Http\Controllers\Api\InspectionController::class, 'update'])->name('inspections.update');
        Route::delete('/{id}', [\App\Http\Controllers\Api\InspectionController::class, 'destroy'])->name('inspections.destroy');
        Route::post('/{id}/schedule', [\App\Http\Controllers\Api\InspectionController::class, 'schedule'])->name('inspections.schedule');
        Route::post('/{id}/conduct', [\App\Http\Controllers\Api\InspectionController::class, 'conduct'])->name('inspections.conduct');
        Route::post('/{id}/complete', [\App\Http\Controllers\Api\InspectionController::class, 'complete'])->name('inspections.complete');
    });

    // Safety Incidents routes - DISABLED (Controller not implemented)
    /*
    Route::group(['prefix' => 'safety-incidents'], function () {
        Route::get('/', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'index'])->name('safety-incidents.index');
        Route::post('/', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'store'])->name('safety-incidents.store');
        Route::get('/{id}', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'show'])->name('safety-incidents.show');
        Route::put('/{id}', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'update'])->name('safety-incidents.update');
        Route::delete('/{id}', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'destroy'])->name('safety-incidents.destroy');
        Route::post('/{id}/report', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'report'])->name('safety-incidents.report');
        Route::post('/{id}/investigate', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'investigate'])->name('safety-incidents.investigate');
        Route::post('/{id}/resolve', [\App\Http\Controllers\Api\SafetyIncidentController::class, 'resolve'])->name('safety-incidents.resolve');
    });
    */

    // Site Diary routes - DISABLED (Controller not implemented)
    /*
    Route::group(['prefix' => 'site-diary'], function () {
        Route::get('/', [\App\Http\Controllers\Api\SiteDiaryController::class, 'index'])->name('site-diary.index');
        Route::post('/', [\App\Http\Controllers\Api\SiteDiaryController::class, 'store'])->name('site-diary.store');
        Route::get('/{id}', [\App\Http\Controllers\Api\SiteDiaryController::class, 'show'])->name('site-diary.show');
        Route::put('/{id}', [\App\Http\Controllers\Api\SiteDiaryController::class, 'update'])->name('site-diary.update');
        Route::delete('/{id}', [\App\Http\Controllers\Api\SiteDiaryController::class, 'destroy'])->name('site-diary.destroy');
        Route::get('/daily/{date}', [\App\Http\Controllers\Api\SiteDiaryController::class, 'getDailyReport'])->name('site-diary.daily');
        Route::get('/weekly/{week}', [\App\Http\Controllers\Api\SiteDiaryController::class, 'getWeeklyReport'])->name('site-diary.weekly');
    });
    */

    // Document Management routes (using DocumentController)
    Route::group(['prefix' => 'documents'], function () {
        Route::get('/', [\App\Http\Controllers\Api\DocumentController::class, 'index'])->name('documents.index');
        Route::post('/', [\App\Http\Controllers\Api\DocumentController::class, 'store'])->name('documents.store');
        Route::get('/{id}', [\App\Http\Controllers\Api\DocumentController::class, 'show'])->name('documents.show');
        Route::put('/{id}', [\App\Http\Controllers\Api\DocumentController::class, 'update'])->name('documents.update');
        Route::delete('/{id}', [\App\Http\Controllers\Api\DocumentController::class, 'destroy'])->name('documents.destroy');
    });

    // Material Requests routes - DISABLED (Controller not implemented)
    /*
    Route::group(['prefix' => 'material-requests'], function () {
        Route::get('/', [\App\Http\Controllers\Api\MaterialRequestController::class, 'index'])->name('material-requests.index');
        Route::post('/', [\App\Http\Controllers\Api\MaterialRequestController::class, 'store'])->name('material-requests.store');
        Route::get('/{id}', [\App\Http\Controllers\Api\MaterialRequestController::class, 'show'])->name('material-requests.show');
        Route::put('/{id}', [\App\Http\Controllers\Api\MaterialRequestController::class, 'update'])->name('material-requests.update');
        Route::delete('/{id}', [\App\Http\Controllers\Api\MaterialRequestController::class, 'destroy'])->name('material-requests.destroy');
        Route::post('/{id}/approve', [\App\Http\Controllers\Api\MaterialRequestController::class, 'approve'])->name('material-requests.approve');
        Route::post('/{id}/reject', [\App\Http\Controllers\Api\MaterialRequestController::class, 'reject'])->name('material-requests.reject');
        Route::post('/{id}/fulfill', [\App\Http\Controllers\Api\MaterialRequestController::class, 'fulfill'])->name('material-requests.fulfill');
    });
    */

    /*
    |--------------------------------------------------------------------------
    | Document Management Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('documents')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\DocumentController::class, 'index'])->name('documents.index');
        Route::post('/', [\App\Http\Controllers\Api\DocumentController::class, 'store'])->name('documents.store');
        Route::get('/{id}', [\App\Http\Controllers\Api\DocumentController::class, 'show'])->name('documents.show');
        Route::put('/{id}', [\App\Http\Controllers\Api\DocumentController::class, 'update'])->name('documents.update');
        Route::delete('/{id}', [\App\Http\Controllers\Api\DocumentController::class, 'destroy'])->name('documents.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Notification Routes
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/', [\App\Http\Controllers\Api\NotificationController::class, 'store'])->name('notifications.store');
        Route::get('/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'show'])->name('notifications.show');
        Route::put('/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::put('/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::delete('/{id}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy'])->name('notifications.destroy');
        Route::get('/stats/count', [\App\Http\Controllers\Api\NotificationController::class, 'getUnreadCount'])->name('notifications.unread-count');
        Route::get('/stats/summary', [\App\Http\Controllers\Api\NotificationController::class, 'getStats'])->name('notifications.stats');
    });

}); // Close the main /zena prefix group
