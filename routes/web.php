<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\PerformanceController;

// Debug route for testing (remove in production)
Route::get('/debug/tasks-create', function() {
    $user = \App\Models\User::where('email', 'uat-superadmin@test.com')->first();
    if (!$user) {
        return 'User not found';
    }
    
    Auth::login($user);
    
    $projects = \App\Models\Project::where('tenant_id', $user->tenant_id)->get();
    $users = \App\Models\User::where('tenant_id', $user->tenant_id)->get();
    
    return view('debug.task-create', [
        'projects' => $projects,
        'users' => $users
    ]);
});

// Debug route for dropdown testing
Route::get('/debug/dropdown-test', function() {
    $user = \App\Models\User::where('email', 'uat-superadmin@test.com')->first();
    if (!$user) {
        return 'User not found';
    }
    
    Auth::login($user);
    
    $projects = \App\Models\Project::where('tenant_id', $user->tenant_id)->get();
    
    return view('debug.dropdown-test', [
        'projects' => $projects
    ]);
});

// Debug route for console error checking
Route::get('/debug/console-check', function() {
    $user = \App\Models\User::where('email', 'uat-superadmin@test.com')->first();
    if (!$user) {
        return 'User not found';
    }
    
    Auth::login($user);
    
    $projects = \App\Models\Project::where('tenant_id', $user->tenant_id)->get();
    
    return view('debug.console-check', [
        'projects' => $projects
    ]);
});

// Debug route for direct dropdown testing
Route::get('/debug/direct-dropdown-test', function() {
    $user = \App\Models\User::where('email', 'uat-superadmin@test.com')->first();
    if (!$user) {
        return 'User not found';
    }
    
    Auth::login($user);
    
    $projects = \App\Models\Project::where('tenant_id', $user->tenant_id)->get();
    
    return view('debug.direct-dropdown-test', [
        'projects' => $projects
    ]);
});

// Debug route for CSS conflict checking
Route::get('/debug/css-conflict-check', function() {
    $user = \App\Models\User::where('email', 'uat-superadmin@test.com')->first();
    if (!$user) {
        return 'User not found';
    }
    
    Auth::login($user);
    
    $projects = \App\Models\Project::where('tenant_id', $user->tenant_id)->get();
    
    return view('debug.css-conflict-check', [
        'projects' => $projects
    ]);
});

/*
|--------------------------------------------------------------------------
| Web Routes - Authentication Module (Architecture Compliant)
|--------------------------------------------------------------------------
|
| Web routes only render views and handle UI interactions.
| All business logic is handled via API endpoints.
|
*/

// Root → React App (SPA)
Route::get('/', function() {
    return file_get_contents(public_path('index.html'));
})->name('root');

// Authentication Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Admin Performance Routes
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/admin/performance', function () {
        return view('admin.performance.dashboard');
    })->name('admin.performance');
    Route::get('/admin/performance/metrics', [\App\Http\Controllers\PerformanceController::class, 'getDashboard'])->name('admin.performance.metrics');
    Route::get('/admin/performance/logs', [\App\Http\Controllers\PerformanceController::class, 'getRealTimeMetrics'])->name('admin.performance.logs');
    Route::post('/admin/performance/metrics', [\App\Http\Controllers\PerformanceController::class, 'recordPageLoadTime'])->name('admin.performance.store');
});

// Test routes (no auth required)
Route::get('/test-direct-html', function () {
    return response('<html><body><h1>Direct HTML Test</h1><script>console.log("Direct script works!");</script></body></html>');
});

Route::get('/test-script', function () {
    return view('test-script');
});

Route::get('/test/login', function (Request $request) {
    $email = $request->query('email');
    abort_unless($email, 400, 'Missing email query parameter');

    $user = \App\Models\User::where('email', $email)->firstOrFail();
    Auth::login($user, true);

    $redirectTo = $request->query('redirect', '/app/dashboard');
    return redirect($redirectTo);
})->name('test.login');

Route::get('/test-debug-component', function() {
    $admin = \App\Models\User::where('email', 'admin@zena.local')->first();
    if (!$admin) {
        return response()->json(['error' => 'Admin user not found']);
    }
    
    \Illuminate\Support\Facades\Auth::login($admin);
    
    $request = new \Illuminate\Http\Request();
    $controller = new \App\Http\Controllers\Admin\AdminUsersController();
    
    try {
        $response = $controller->debugComponent($request);
        return $response;
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
})->name('test.debug-component');

Route::get('/test-fixed-component', function() {
    $admin = \App\Models\User::where('email', 'admin@zena.local')->first();
    if (!$admin) {
        return response()->json(['error' => 'Admin user not found']);
    }
    
    \Illuminate\Support\Facades\Auth::login($admin);
    
    $request = new \Illuminate\Http\Request();
    $controller = new \App\Http\Controllers\Admin\AdminUsersController();
    
    try {
        $response = $controller->fixedTest($request);
        return $response;
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
})->name('test.fixed-component');

// Component Demo Routes (Development only)
if (app()->environment('local', 'testing')) {
    Route::middleware(['demo.user'])->group(function () {
        Route::get('/demo/test', fn() => view('_demos.test-demo'))->name('demo.test');
        Route::get('/demo/simple', fn() => view('_demos.simple-demo'))->name('demo.simple');
        Route::get('/demo/header', fn() => view('_demos.header-demo'))->name('demo.header');
        Route::get('/demo/components', fn() => view('_demos.components-demo'))->name('demo.components');
        Route::get('/demo/dashboard', fn() => view('_demos.dashboard-demo'))->name('demo.dashboard');
        Route::get('/demo/projects', fn() => view('_demos.projects-demo'))->name('demo.projects');
        Route::get('/demo/tasks', fn() => view('_demos.tasks-demo'))->name('demo.tasks');
        Route::get('/demo/documents', fn() => view('_demos.documents-demo'))->name('demo.documents');
        Route::get('/demo/admin', fn() => view('_demos.admin-demo'))->name('demo.admin');
    });
}

// ========================================
// PUBLIC AUTHENTICATION ROUTES
// ========================================

// Login routes (render-only) - DISABLED for React frontend
// Route::get('/login', [LoginController::class, 'showLoginForm'])
//     ->name('login')
//     ->middleware(['web', 'guest']);

// Register routes (render-only)
Route::get('/register', [RegisterController::class, 'showRegistrationForm'])
    ->name('register')
    ->middleware(['web', 'guest']);

// Password reset routes (render-only)
Route::get('/password/reset', [PasswordResetController::class, 'showLinkRequestForm'])
    ->name('password.request')
    ->middleware(['web', 'guest']);

Route::get('/password/reset/{token}', [PasswordResetController::class, 'showResetForm'])
    ->name('password.reset')
    ->middleware(['web', 'guest']);

// Login API route (web middleware for session)
// Round 158: Add debug.auth middleware for E2E auth tracing
Route::post('/api/auth/login', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'login'])
    ->middleware(['web', 'debug.auth', 'throttle:5,1']);


// Logout route
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout')
    ->middleware(['web', 'auth']);

// Email verification routes
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->name('verification.notice')->middleware(['web', 'auth']);

Route::get('/email/verify/{id}/{hash}', function ($id, $hash) {
    $user = \App\Models\User::findOrFail($id);
    
    if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        abort(403);
    }
    
    if ($user->hasVerifiedEmail()) {
        return redirect('/app/dashboard')->with('success', 'Email already verified!');
    }
    
    $user->markEmailAsVerified();
    
    return redirect('/app/dashboard')->with('success', 'Email verified successfully!');
})->name('verification.verify')->middleware(['web', 'auth', 'signed']);

Route::post('/email/verification-notification', function () {
    request()->user()->sendEmailVerificationNotification();
    return back()->with('message', 'Verification link sent!');
})->name('verification.send')->middleware(['web', 'auth', 'throttle:6,1']);

// ========================================
// PROTECTED WEB ROUTES
// ========================================

// Dashboard and main app routes (tenant-scoped)
Route::middleware(['web', 'auth:web'])->group(function () {
    // Dashboard - Using Blade template with Unified Page Frame (Active)
    // Handler is in routes/app.php (DashboardController@index)
    // React version: Use '/app/dashboard-react' route if needed
    
    // Projects - DISABLED: Using React Frontend (localhost:5173)
    // Route::get('/app/projects', [\App\Http\Controllers\Web\ProjectController::class, 'index'])->name('app.projects.index');
    // Route::get('/app/projects/create', [\App\Http\Controllers\Web\ProjectController::class, 'create'])->name('app.projects.create');
    
    // ProjectsNext - Test route for new React-based projects page
    Route::get('/app/projects-next', function () {
        return view('app.projects-next');
    })->name('app.projects-next');
    // Routes disabled - using React Frontend
    // Route::post('/app/projects', [\App\Http\Controllers\Web\ProjectController::class, 'store'])->name('app.projects.store');
    // Route::get('/app/projects/{project}', [\App\Http\Controllers\Web\ProjectController::class, 'show'])->name('app.projects.show');
    // Route::get('/app/projects/{project}/edit', [\App\Http\Controllers\Web\ProjectController::class, 'edit'])->name('app.projects.edit');
    // Route::put('/app/projects/{project}', [\App\Http\Controllers\Web\ProjectController::class, 'update'])->name('app.projects.update');
    // Route::delete('/app/projects/{project}', [\App\Http\Controllers\Web\ProjectController::class, 'destroy'])->name('app.projects.destroy');
    // Route::post('/app/projects/view-mode', [\App\Http\Controllers\Web\ProjectController::class, 'setViewMode'])->name('app.projects.view-mode');
    // Route::post('/app/projects/bulk-action', [\App\Http\Controllers\Web\ProjectController::class, 'bulkAction'])->name('app.projects.bulk-action');
    
    // Tasks
    // Tasks - Commented out to allow test routes in app.php to work
    // Route::get('/app/tasks', [\App\Http\Controllers\Web\TaskController::class, 'index'])->name('app.tasks.index');
    // Tasks - Commented out to allow test routes in app.php to work
    // Route::get('/app/tasks/kanban-react', [\App\Http\Controllers\Web\TaskController::class, 'kanbanReact'])->name('app.tasks.kanban-react');
    // Route::get('/app/tasks/create', [\App\Http\Controllers\Web\TaskController::class, 'create'])->name('app.tasks.create');
    // Route::post('/app/tasks', [\App\Http\Controllers\Web\TaskController::class, 'store'])->name('app.tasks.store');
    // Route::get('/app/tasks/{task}', [\App\Http\Controllers\Web\TaskController::class, 'show'])->name('app.tasks.show');
    // Route::get('/app/tasks/{task}/edit', [\App\Http\Controllers\Web\TaskController::class, 'edit'])->name('app.tasks.edit');
    // Route::put('/app/tasks/{task}', [\App\Http\Controllers\Web\TaskController::class, 'update'])->name('app.tasks.update');
    // Route::delete('/app/tasks/{task}', [\App\Http\Controllers\Web\TaskController::class, 'destroy'])->name('app.tasks.destroy');
    Route::post('/app/tasks/bulk-action', [\App\Http\Controllers\Web\TaskController::class, 'bulkAction'])->name('app.tasks.bulk-action');
    
    // Tasks - Simple version for testing (legacy)
    Route::get('/app/tasks/create-simple', [\App\Http\Controllers\Web\SimpleTaskController::class, 'create'])->name('app.tasks.create-simple');
    Route::post('/app/tasks-simple', [\App\Http\Controllers\Web\SimpleTaskController::class, 'store'])->name('app.tasks.store-simple');
    
    // Documents
    Route::get('/app/documents', [\App\Http\Controllers\Web\DocumentsController::class, 'index'])->name('app.documents.index');
    
    // Team
    Route::get('/app/team', function () {
        return view('app.team.index');
    })->name('app.team.index');
    
    // Reports
    Route::get('/app/reports', function () {
        return view('app.reports.index');
    })->name('app.reports.index');
    
    // Profile
    Route::get('/app/profile', function () {
        return view('app.profile');
    })->name('app.profile');
    
    // Settings
    Route::get('/app/settings', function () {
        return view('app.settings');
    })->name('app.settings.index');
    
    // Debug route
    Route::get('/app/debug', function () {
        return response()->json([
            'authenticated' => Auth::check(),
            'user' => Auth::user() ? Auth::user()->only(['id', 'name', 'email']) : null,
            'session_id' => session()->getId(),
            'session_data' => session()->all()
        ]);
    });
    
    // User management
    Route::get('/app/users', [UserController::class, 'index'])
        ->name('app.users.index')
        ->middleware('can:users.view');
    
    Route::get('/app/users/{user}', [UserController::class, 'show'])
        ->name('app.users.show')
        ->middleware('can:users.view');
    
});

// Admin routes (system-wide)
Route::middleware(['web', 'auth:web', \App\Http\Middleware\AdminOnlyMiddleware::class])->group(function () {
    // Admin Dashboard
    Route::get('/admin/dashboard', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('admin.dashboard');
    
    // Admin Profile
    Route::get('/admin/profile', function () {
        return view('admin.profile');
    })->name('admin.profile');
    
    // Admin Settings
    Route::get('/admin/settings', function () {
        return view('admin.settings');
    })->name('admin.settings');
    
    // Admin Users
    Route::get('/admin/users', [\App\Http\Controllers\Admin\AdminUsersController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/debug', [\App\Http\Controllers\Admin\AdminUsersController::class, 'debug'])->name('admin.users.debug');
    Route::get('/admin/users/test-component', [\App\Http\Controllers\Admin\AdminUsersController::class, 'testComponent'])->name('admin.users.test-component');
    Route::get('/admin/users/debug-component', [\App\Http\Controllers\Admin\AdminUsersController::class, 'debugComponent'])->name('admin.users.debug-component');
    
    Route::get('/admin/users/create', function () {
        return view('admin.users.create');
    })->name('admin.users.create');
    
    // Admin Tenants
    Route::get('/admin/tenants', [\App\Http\Controllers\Admin\AdminTenantsController::class, 'index'])->name('admin.tenants.index');
    
    Route::get('/admin/tenants/create', function () {
        return view('admin.tenants.create');
    })->name('admin.tenants.create');
    
    // Admin Projects
    Route::get('/admin/projects', function () {
        return view('admin.projects.index');
    })->name('admin.projects.index');
    
    Route::get('/admin/projects/create', function () {
        return view('admin.projects.create');
    })->name('admin.projects.create');
    
    // Admin Security
    Route::get('/admin/security', function () {
        return view('admin.security.index');
    })->name('admin.security.index');
    
    Route::get('/admin/security/scan', function () {
        return view('admin.security.scan');
    })->name('admin.security.scan');
    
    // Admin Alerts
    Route::get('/admin/alerts', function () {
        return view('admin.alerts.index');
    })->name('admin.alerts.index');
    
    // Admin Activities
    Route::get('/admin/activities', function () {
        return view('admin.activities.index');
    })->name('admin.activities.index');
    
    // Admin Analytics
    Route::get('/admin/analytics', function () {
        return view('admin.analytics.index');
    })->name('admin.analytics.index');
    
    // Admin Maintenance
    Route::get('/admin/maintenance', function () {
        return view('admin.maintenance.index');
    })->name('admin.maintenance.index');
    
    Route::get('/admin/maintenance/backup', function () {
        return view('admin.maintenance.backup');
    })->name('admin.maintenance.backup');
    
    // Admin Settings
    Route::get('/admin/settings', function () {
        return view('admin.settings.index');
    })->name('admin.settings.index');
});

// ========================================
// SPA ROUTES (Public Shell - Auth handled client-side)
// ========================================
// SPA shell is public - client-side JavaScript handles auth checks and redirects
// API endpoints remain protected with Sanctum tokens
Route::get('/app/dashboard', function () {
    return view('app.spa');
})->name('app.dashboard');

// SPA catch-all route - must be last to not override specific routes
// This ensures /app/* always serves the SPA shell (no Ignition errors)
Route::get('/app/{any}', function () {
    return view('app.spa');
})->where('any', '.*')->name('app.spa');

// ========================================
// DEVELOPMENT DEBUG ROUTES (GUARDED)
// ========================================

if (app()->environment('local', 'testing')) {
    // Test route for CSRF verification
    Route::post('/test-csrf-real', function () {
        return response()->json(['success' => true, 'csrf_verified' => true]);
    })->middleware('web');
    
    // Test session route
    Route::get('/test-session', function () {
        return response()->json([
            'session_id' => session()->getId(),
            'session_data' => session()->all(),
            'authenticated' => Auth::check(),
            'user' => Auth::user() ? Auth::user()->only(['id', 'name', 'email']) : null
        ]);
    })->middleware('web');
    
    // REMOVED: Dangerous test routes
    // These routes bypass security and should never be in production
    
    // Test route for Playwright E2E tests (bypasses authentication)
Route::get('/test/tasks/{id}', function (string $id) {
    $user = \App\Models\User::where('email', 'uat-pm@test.com')->first();
    if (!$user) {
        abort(404, 'Test user not found');
    }
    
    auth()->login($user);
    
    // Debug: Check if user is authenticated
    if (!auth()->check()) {
        return 'Authentication failed';
    }
    
    $taskService = app(\App\Services\TaskManagementService::class);
    $projectService = app(\App\Services\ProjectManagementService::class);
    $userService = app(\App\Services\UserManagementService::class);
    
    $tenantId = $user->tenant_id;
    
    $task = $taskService->getTaskById($id, $tenantId);
    if (!$task) {
        abort(404, 'Task not found');
    }
    
    $projects = $projectService->getProjects([], 100, 'name', 'asc', $tenantId);
    $users = $userService->getUsers([], 100, 'name', 'asc', $tenantId);

    return view('app.tasks.show', [
        'task' => $task,
        'projects' => $projects,
        'users' => $users
    ]);
        })->name('test.tasks.show.web');

        // Simple test route for debugging
        Route::get('/test-simple-task/{id}', function (string $id) {
            $user = \App\Models\User::where('email', 'uat-pm@test.com')->first();
            if (!$user) {
                abort(404, 'Test user not found');
            }
            
            auth()->login($user);
            
            $task = \App\Models\Task::with(['project', 'assignee', 'creator'])
                ->where('id', $id)
                ->where('tenant_id', $user->tenant_id)
                ->first();
            
            if (!$task) {
                abort(404, 'Task not found');
            }

            return view('test-task', ['task' => $task]);
        })->name('test.simple.task');
    // This bypasses all authentication and should never be in production
}

// Sandbox routes for Phase 3 E2E tests (only in local/testing environment)
if (app()->environment('local', 'testing')) {
    Route::get('/sandbox/task-view/{task}', function (\App\Models\Task $task) {
        $user = \App\Models\User::firstWhere('email', 'uat-pm@test.com');
        abort_unless($user, 404, 'Seed test user first');

        auth()->login($user);      // khởi tạo session thật
        return view('app.tasks.show', [
            'task' => $task,
            'projects' => \App\Models\Project::where('tenant_id', $user->tenant_id)->get(),
            'users' => \App\Models\User::where('tenant_id', $user->tenant_id)->get(),
        ]);
    })->name('sandbox.task-view');
    
                Route::get('/sandbox/tasks-list', function () {
                    $user = \App\Models\User::firstWhere('email', 'uat-pm@test.com');
                    abort_unless($user, 404, 'Seed test user first');

                    auth()->login($user);      // khởi tạo session thật
                    
                    $tenantId = $user->tenant_id;
                    $taskService = app(\App\Services\TaskService::class);
                    
                    $tasks = $taskService->getTasksList([], $user->id, $tenantId);
                    $projects = \App\Models\Project::where('tenant_id', $tenantId)->get();
                    $users = \App\Models\User::where('tenant_id', $tenantId)->get();
                    
                    return view('app.tasks.index', [
                        'tasks' => $tasks,
                        'projects' => $projects,
                        'users' => $users,
                        'filters' => []
                    ]);
                })->name('sandbox.tasks-list');
                
                Route::get('/sandbox/kanban', function () {
                    $user = \App\Models\User::firstWhere('email', 'uat-pm@test.com');
                    abort_unless($user, 404, 'Seed test user first');

                    auth()->login($user);      // khởi tạo session thật
                    
                    $tenantId = $user->tenant_id;
                    $taskService = app(\App\Services\TaskService::class);
                    
                    $tasks = $taskService->getTasksList([], $user->id, $tenantId);
                    $projects = \App\Models\Project::where('tenant_id', $tenantId)->get();
                    $users = \App\Models\User::where('tenant_id', $tenantId)->get();
                    
                    return view('app.tasks.kanban-react', [
                        'tasks' => $tasks,
                        'projects' => $projects,
                        'users' => $users,
                        'filters' => []
                    ]);
                })->name('sandbox.kanban');
}

// Test route for CSRF protection (remove in production)
Route::middleware('web')->match(['POST', 'PUT', 'PATCH'], '/test-csrf', function() {
    return response()->json(['success' => true, 'csrf_checked' => true]);
})->name('test.csrf');
Route::get('/test-simple', function() { return view('test-simple'); });
