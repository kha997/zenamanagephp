<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    // Redirect to dashboard
    return redirect('/dashboard');
});

// Navigation demo route
Route::get('/navigation-demo', function () {
    return view('navigation-demo');
});

Route::get('/dashboard', function () {
    return view('dashboards.admin');
})->name('dashboard'); // Temporarily removed middleware for testing

Route::get('/dashboard/admin', function () {
    return view('dashboards.admin');
})->name('dashboard.admin')->middleware(['auth', 'tenant', 'role:admin']);




Route::get('/profile', function () {
    return view('profile.index');
})->name('profile');

// Test route without middleware
Route::get('/test', function () {
    return 'Test route working!';
});

// Admin Dashboard API Routes (temporary for testing)
Route::prefix('api/admin/dashboard')->group(function () {
    Route::get('/stats', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getStats']);
    Route::get('/activities', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getActivities']);
    Route::get('/alerts', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getAlerts']);
    Route::get('/metrics', [App\Http\Controllers\Api\Admin\DashboardController::class, 'getMetrics']);
});

// Test route for task update debugging
Route::post('/test-task-update', function (Request $request) {
    \Log::info('Test task update route hit', [
        'request_data' => $request->all(),
        'method' => $request->method(),
        'url' => $request->url()
    ]);
    return response()->json([
        'success' => true,
        'message' => 'Test route working',
        'data' => $request->all()
    ]);
});

// Authentication Routes
Route::get('/login', [App\Http\Controllers\AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);

// Simple login route for testing (no CSRF)
Route::post('/simple-login', function(\Illuminate\Http\Request $request) {
    $email = $request->input('email');
    $password = $request->input('password');
    
    // Demo users
    $demoUsers = [
        'superadmin@zena.com' => ['name' => 'Super Admin', 'role' => 'super_admin'],
        'pm@zena.com' => ['name' => 'Project Manager', 'role' => 'project_manager'],
        'designer@zena.com' => ['name' => 'Designer', 'role' => 'designer'],
        'site@zena.com' => ['name' => 'Site Engineer', 'role' => 'site_engineer'],
        'qc@zena.com' => ['name' => 'QC Engineer', 'role' => 'qc_engineer'],
        'procurement@zena.com' => ['name' => 'Procurement', 'role' => 'procurement'],
        'finance@zena.com' => ['name' => 'Finance', 'role' => 'finance'],
        'client@zena.com' => ['name' => 'Client', 'role' => 'client'],
    ];
    
    if ($password === 'zena1234' && isset($demoUsers[$email])) {
        $userData = $demoUsers[$email];
        
        // Create a simple user object for session
        $user = new \stdClass();
        $user->id = rand(1000, 9999);
        $user->name = $userData['name'];
        $user->email = $email;
        $user->role = $userData['role'];
        
        // Store user data in session
        session(['user' => $user]);
        
        return redirect('/dashboard');
    }
    
    return back()->withErrors([
        'email' => 'Email hoặc mật khẩu không đúng',
    ]);
})->withoutMiddleware(['web']);

Route::get('/logout', [App\Http\Controllers\AuthController::class, 'logout'])->name('logout');

// Test login route (GET for easy testing)
Route::get('/test-login/{email}', function($email) {
    $demoUsers = [
        'superadmin@zena.com' => ['name' => 'Super Admin', 'role' => 'super_admin'],
        'pm@zena.com' => ['name' => 'Project Manager', 'role' => 'project_manager'],
        'designer@zena.com' => ['name' => 'Designer', 'role' => 'designer'],
        'site@zena.com' => ['name' => 'Site Engineer', 'role' => 'site_engineer'],
        'qc@zena.com' => ['name' => 'QC Engineer', 'role' => 'qc_engineer'],
        'procurement@zena.com' => ['name' => 'Procurement', 'role' => 'procurement'],
        'finance@zena.com' => ['name' => 'Finance', 'role' => 'finance'],
        'client@zena.com' => ['name' => 'Client', 'role' => 'client'],
    ];
    
    if (isset($demoUsers[$email])) {
        $userData = $demoUsers[$email];
        
        // Create a simple user object for session
        $user = new \stdClass();
        $user->id = rand(1000, 9999);
        $user->name = $userData['name'];
        $user->email = $email;
        $user->role = $userData['role'];
        
        // Store user data in session
        session(['user' => $user]);
        
        return redirect('/dashboard');
    }
    
    return 'Invalid email';
});

// Simple API login for testing (no CSRF)
Route::post('/api/login', function(\Illuminate\Http\Request $request) {
    $email = $request->input('email');
    $password = $request->input('password');
    
    // Demo users
    $demoUsers = [
        'superadmin@zena.com' => ['name' => 'Super Admin', 'role' => 'super_admin'],
        'pm@zena.com' => ['name' => 'Project Manager', 'role' => 'project_manager'],
        'designer@zena.com' => ['name' => 'Designer', 'role' => 'designer'],
        'site@zena.com' => ['name' => 'Site Engineer', 'role' => 'site_engineer'],
        'qc@zena.com' => ['name' => 'QC Engineer', 'role' => 'qc_engineer'],
        'procurement@zena.com' => ['name' => 'Procurement', 'role' => 'procurement'],
        'finance@zena.com' => ['name' => 'Finance', 'role' => 'finance'],
        'client@zena.com' => ['name' => 'Client', 'role' => 'client'],
    ];
    
    if ($password === 'zena1234' && isset($demoUsers[$email])) {
        $userData = $demoUsers[$email];
        
        // Create a simple user object for session
        $user = new \stdClass();
        $user->id = rand(1000, 9999);
        $user->name = $userData['name'];
        $user->email = $email;
        $user->role = $userData['role'];
        
        // Store user data in session
        session(['user' => $user]);
        
        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công!',
            'redirect' => '/dashboard',
            'user' => $user
        ]);
    }
    
    return response()->json([
        'success' => false,
        'message' => 'Email hoặc mật khẩu không đúng'
    ], 401);
})->withoutMiddleware(['web']);

// Dashboard Routes (removed duplicate - using the one above)

// Sidebar API Routes
Route::get('/api/sidebar/config', [App\Http\Controllers\SidebarController::class, 'getSidebarConfig']);
Route::get('/api/sidebar/badges', [App\Http\Controllers\Api\BadgeController::class, 'getBadges']);
Route::get('/api/sidebar/default/{role}', [App\Http\Controllers\SidebarController::class, 'getDefaultSidebarConfig']);

// Admin Routes
Route::get('/admin/sidebar-builder', function() {
    return view('admin.sidebar-builder');
})->name('admin.sidebar-builder');

// Role Testing Routes (for demo purposes) - Using demo data instead of database
// Role-specific Dashboard Routes (continued)
Route::prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/pm', function() {
        return view('dashboards.pm');
    })->name('pm')->middleware(['auth', 'tenant', 'role:pm']);
    
    Route::get('/finance', function() {
        return view('dashboards.finance');
    })->name('finance')->middleware(['auth', 'tenant', 'role:finance']);
    
    Route::get('/client', function() {
        return view('dashboards.client');
    })->name('client')->middleware(['auth', 'tenant', 'role:client']);
    
    Route::get('/designer', function() {
        return view('dashboards.designer');
    })->name('designer')->middleware(['auth', 'tenant', 'role:designer']);
    
    Route::get('/site', function() {
        return view('dashboards.site-engineer');
    })->name('site')->middleware(['auth', 'tenant', 'role:engineer']);
    
    Route::get('/qc-inspector', function() {
        return view('dashboards.qc-inspector');
    })->name('qc-inspector')->middleware(['auth', 'tenant', 'role:qc_inspector']);
    
    Route::get('/subcontractor-lead', function() {
        return view('dashboards.subcontractor-lead');
    })->name('subcontractor-lead')->middleware(['auth', 'tenant', 'role:subcontractor_lead']);
    
    Route::get('/sales', function () {
        return view('dashboards.sales');
    })->name('sales');
    
    Route::get('/users', function () {
        return view('dashboards.users');
    })->name('users');
    
    Route::get('/performance', function () {
        return view('dashboards.performance');
    })->name('performance');
    
    Route::get('/marketing', function () {
        return view('dashboards.marketing');
    })->name('marketing');
    
    Route::get('/projects', function () {
        return view('dashboards.projects');
    })->name('projects');
});

// Test route for debugging
Route::get('/projects-test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Test route working',
        'data' => ['test' => 'data']
    ])->header('Access-Control-Allow-Origin', '*')
      ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
      ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
});

// Projects Routes (without middleware for testing)
Route::prefix('projects')->name('projects.')->group(function () {
    // Handle OPTIONS request for CORS
    Route::options('/', function () {
        return response('', 200)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
            ->header('Access-Control-Max-Age', '86400');
    });
    
    Route::get('/', [App\Http\Controllers\ProjectController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\ProjectController::class, 'create'])->name('create');
    Route::post('/', [App\Http\Controllers\ProjectController::class, 'store'])->name('store');
    Route::get('/{project}', [App\Http\Controllers\ProjectController::class, 'show'])->name('show');
    Route::get('/{project}/edit', [App\Http\Controllers\ProjectController::class, 'edit'])->name('edit');
    Route::put('/{project}', [App\Http\Controllers\ProjectController::class, 'update'])->name('update');
    Route::delete('/{project}', [App\Http\Controllers\ProjectController::class, 'destroy'])->name('destroy');
    
    // Project detail routes
    Route::get('/{project}/documents', [App\Http\Controllers\ProjectController::class, 'documents'])->name('documents');
    Route::get('/{project}/history', [App\Http\Controllers\ProjectController::class, 'history'])->name('history');
    
    // Project detail routes
    Route::get('/design/{project}', function ($project) {
        return view('projects.design-project', compact('project'));
    })->name('design-project');
    Route::get('/construction/{project}', function ($project) {
        return view('projects.construction-project', compact('project'));
    })->name('construction-project');
});

// Template Management Routes
Route::prefix('templates')->name('templates.')->group(function () {
    Route::get('/', function () {
        return view('templates.index');
    })->name('index');
    Route::get('/builder', function () {
        return view('templates.builder');
    })->name('builder');
    Route::get('/construction-builder', function () {
        return view('templates.construction-builder');
    })->name('construction-builder');
    Route::get('/analytics', function () {
        return view('templates.analytics');
    })->name('analytics');
    Route::get('/create', function () {
        return view('templates.create');
    })->name('create');
    Route::get('/{template}', function ($template) {
        return view('templates.show', compact('template'));
    })->name('show');
});

// Tasks Routes
Route::prefix('tasks')->name('tasks.')->group(function () {
    Route::get('/', [App\Http\Controllers\Web\TaskController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\Web\TaskController::class, 'create'])->name('create');
    Route::get('/new', function () {
        return redirect()->route('tasks.create');
    })->name('new');
    Route::post('/', [App\Http\Controllers\Web\TaskController::class, 'store'])->name('store');
    Route::get('/{task}', [App\Http\Controllers\Web\TaskController::class, 'show'])->name('show');
    Route::get('/{task}/edit', [App\Http\Controllers\Web\TaskController::class, 'edit'])->name('edit');
    Route::get('/{task}/edit-debug', [App\Http\Controllers\Web\TaskController::class, 'editDebug'])->name('edit-debug');
    Route::get('/{task}/edit-simple-debug', [App\Http\Controllers\Web\TaskController::class, 'editSimpleDebug'])->name('edit-simple-debug');
    Route::put('/{task}', [App\Http\Controllers\Web\TaskController::class, 'update'])->name('update');
    Route::delete('/{task}', [App\Http\Controllers\Web\TaskController::class, 'destroy'])->name('destroy');
    
    // Additional task actions
    Route::post('/{task}/archive', [App\Http\Controllers\Web\TaskController::class, 'archive'])->name('archive');
    Route::post('/{task}/move', [App\Http\Controllers\Web\TaskController::class, 'move'])->name('move');
    Route::get('/{task}/documents', [App\Http\Controllers\Web\TaskController::class, 'documents'])->name('documents');
    Route::post('/{task}/documents', [App\Http\Controllers\Web\TaskController::class, 'storeDocument'])->name('store-document');
    Route::get('/{task}/history', [App\Http\Controllers\Web\TaskController::class, 'history'])->name('history');
});

// Team Routes
Route::prefix('team')->name('team.')->group(function () {
    Route::get('/', function () {
        return view('team.index');
    })->name('index');
    Route::get('/users', function () {
        return view('team.users');
    })->name('users');
    Route::get('/invite', function () {
        return view('team.invite');
    })->name('invite');
    Route::get('/new', function () {
        return view('team.invite');
    })->name('new'); // Alias for invite
});

// Documents Routes
Route::prefix('documents')->name('documents.')->group(function () {
    Route::get('/', [App\Http\Controllers\Web\DocumentController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\Web\DocumentController::class, 'create'])->name('create');
    Route::get('/approvals', [App\Http\Controllers\Web\DocumentController::class, 'approvals'])->name('approvals');
});

// Users Routes
Route::prefix('users')->name('users.')->group(function () {
    Route::get('/', function () {
        return view('users.index');
    })->name('index');
    Route::get('/create', function () {
        return view('users.create');
    })->name('create');
    Route::get('/{user}', function ($user) {
        return view('users.show', compact('user'));
    })->name('show');
    Route::get('/{user}/edit', function ($user) {
        return view('users.edit', compact('user'));
    })->name('edit');
});

// Tenants Routes
Route::prefix('tenants')->name('tenants.')->group(function () {
    Route::get('/', function () {
        return view('tenants.index');
    })->name('index');
    Route::get('/create', function () {
        return view('tenants.create');
    })->name('create');
    Route::get('/{tenant}', function ($tenant) {
        return view('tenants.show', compact('tenant'));
    })->name('show');
    Route::get('/{tenant}/edit', function ($tenant) {
        return view('tenants.edit', compact('tenant'));
    })->name('edit');
});

// Security Routes
Route::prefix('security')->name('security.')->group(function () {
    Route::get('/', function () {
        return view('security.index');
    })->name('index');
    Route::get('/audit', function () {
        return view('security.audit');
    })->name('audit');
    Route::get('/logs', function () {
        return view('security.logs');
    })->name('logs');
});

// Alerts Routes
Route::prefix('alerts')->name('alerts.')->group(function () {
    Route::get('/', function () {
        return view('alerts.index');
    })->name('index');
    Route::get('/create', function () {
        return view('alerts.create');
    })->name('create');
    Route::get('/{alert}', function ($alert) {
        return view('alerts.show', compact('alert'));
    })->name('show');
});

// Activities Routes
Route::prefix('activities')->name('activities.')->group(function () {
    Route::get('/', function () {
        return view('activities.index');
    })->name('index');
    Route::get('/logs', function () {
        return view('activities.logs');
    })->name('logs');
    Route::get('/audit', function () {
        return view('activities.audit');
    })->name('audit');
});

// Settings Routes
Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', function () {
        return view('settings.index');
    })->name('index');
    Route::get('/general', function () {
        return view('settings.general');
    })->name('general');
    Route::get('/security', function () {
        return view('settings.security');
    })->name('security');
    Route::get('/notifications', function () {
        return view('settings.notifications');
    })->name('notifications');
});

// Admin Routes
Route::prefix('admin')->name('admin.')->group(function () {
    // Main Super Admin Dashboard
    Route::get('/', function () {
        return view('admin.super-admin-dashboard-new');
    })->name('dashboard');
    
    // System Settings
    Route::get('/settings', function () {
        return view('admin.settings');
    })->name('settings');
    
    // Dashboard Index (Selection Page)
    Route::get('/dashboard-index', function () {
        return view('admin.dashboard-index');
    })->name('dashboard-index');
    
    // Super Admin Management Routes
    Route::get('/users', function () {
        return view('admin.users');
    })->name('users');
    Route::get('/tenants', function () {
        return view('admin.tenants');
    })->name('tenants');
    Route::get('/security', function () {
        return view('admin.security');
    })->name('security');
    Route::get('/alerts', [App\Http\Controllers\Web\AlertController::class, 'index'])->name('alerts');
    Route::get('/activities', function () {
        return view('admin.activities');
    })->name('activities');
    Route::get('/projects', function () {
        return view('admin.projects');
    })->name('projects');
});

// Calendar route
Route::get('/calendar', function () {
    return view('calendar.index');
})->name('calendar');

Route::get('/admin/maintenance', function () {
    return view('admin.maintenance');
})->name('admin.maintenance');

// Invitation Routes
Route::prefix('invitations')->name('invitations.')->group(function () {
    // Public routes (no authentication required)
    Route::get('/accept/{token}', [App\Http\Controllers\InvitationController::class, 'accept'])->name('accept');
    Route::post('/accept/{token}', [App\Http\Controllers\InvitationController::class, 'processAcceptance'])->name('process-acceptance');
    
    // Protected routes (authentication required) - temporarily without middleware
    Route::get('/', [App\Http\Controllers\InvitationController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\InvitationController::class, 'create'])->name('create');
    Route::post('/', [App\Http\Controllers\InvitationController::class, 'store'])->name('store');
    
    // Admin-only routes (super_admin, admin, project_manager roles) - temporarily without middleware
    Route::get('/manage', [App\Http\Controllers\InvitationController::class, 'manage'])->name('manage');
    Route::post('/bulk-create', [App\Http\Controllers\InvitationController::class, 'bulkCreate'])->name('bulk-create');
    Route::post('/{invitation}/resend', [App\Http\Controllers\InvitationController::class, 'resend'])->name('resend');
    Route::post('/{invitation}/cancel', [App\Http\Controllers\InvitationController::class, 'cancel'])->name('cancel');
});

// Email Tracking Routes
Route::prefix('email-tracking')->name('email-tracking.')->group(function () {
    // Public tracking routes (no authentication required)
    Route::get('/open/{trackingId}', [App\Http\Controllers\EmailTrackingController::class, 'trackOpen'])->name('open');
    Route::get('/click/{trackingId}/{linkUrl}', [App\Http\Controllers\EmailTrackingController::class, 'trackClick'])->name('click');
    
    // Protected analytics routes (authentication required)
    Route::middleware(['invitation.auth'])->group(function () {
        Route::get('/analytics', [App\Http\Controllers\EmailTrackingController::class, 'getAnalytics'])->name('analytics');
        Route::get('/data', [App\Http\Controllers\EmailTrackingController::class, 'getTrackingData'])->name('data');
    });
});

// Email Configuration Routes
Route::prefix('admin/email-config')->name('admin.email-config.')->group(function () {
    Route::get('/', [App\Http\Controllers\EmailConfigController::class, 'index'])->name('index');
    Route::post('/update', [App\Http\Controllers\EmailConfigController::class, 'update'])->name('update');
    Route::post('/test', [App\Http\Controllers\EmailConfigController::class, 'test'])->name('test');
    Route::get('/statistics', [App\Http\Controllers\EmailConfigController::class, 'statistics'])->name('statistics');
    Route::post('/clear-cache', [App\Http\Controllers\EmailConfigController::class, 'clearCache'])->name('clear-cache');
});

// Organization Routes
Route::prefix('organizations')->name('organizations.')->group(function () {
    Route::get('/', [App\Http\Controllers\OrganizationController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\OrganizationController::class, 'create'])->name('create');
    Route::post('/', [App\Http\Controllers\OrganizationController::class, 'store'])->name('store');
    Route::get('/{organization}', [App\Http\Controllers\OrganizationController::class, 'show'])->name('show');
    Route::get('/{organization}/edit', [App\Http\Controllers\OrganizationController::class, 'edit'])->name('edit');
    Route::put('/{organization}', [App\Http\Controllers\OrganizationController::class, 'update'])->name('update');
});

// Health Check Routes
Route::get('/health', [App\Http\Controllers\HealthController::class, 'index']);
Route::get('/health/detailed', [App\Http\Controllers\HealthController::class, 'detailed']);
Route::get('/health/readiness', [App\Http\Controllers\HealthController::class, 'readiness']);
Route::get('/health/liveness', [App\Http\Controllers\HealthController::class, 'liveness']);
Route::get('/metrics', [App\Http\Controllers\HealthController::class, 'metrics']);

// API Routes
Route::get('/api/health', [App\Http\Controllers\HealthController::class, 'index']);
Route::get('/api/health/detailed', [App\Http\Controllers\HealthController::class, 'detailed']);
Route::get('/api/metrics', [App\Http\Controllers\HealthController::class, 'metrics']);
Route::get('/api/metrics/summary', function() {
    $metricsService = app(\App\Services\MetricsService::class);
    return response()->json($metricsService->getMetricsSummary());
});

        // Admin Routes
        Route::prefix('admin')->group(function () {
            Route::get('/sidebar-builder', [App\Http\Controllers\Admin\BasicSidebarController::class, 'index'])->name('admin.sidebar-builder');
            Route::get('/sidebar-builder/{role}', [App\Http\Controllers\Admin\BasicSidebarController::class, 'show'])->name('admin.sidebar-builder.edit');
            Route::get('/sidebar-builder/{role}/preview', [App\Http\Controllers\Admin\BasicSidebarController::class, 'preview'])->name('admin.sidebar-builder.preview');
            
            // Sidebar Builder Actions (using original controller for advanced features)
            Route::post('/sidebar-builder/clone', [App\Http\Controllers\Admin\SidebarBuilderController::class, 'clone'])->name('admin.sidebar-builder.clone');
            Route::put('/sidebar-builder/{role}/reset', [App\Http\Controllers\Admin\SidebarBuilderController::class, 'reset'])->name('admin.sidebar-builder.reset');
            Route::get('/sidebar-builder/{role}/export', [App\Http\Controllers\Admin\SidebarBuilderController::class, 'export'])->name('admin.sidebar-builder.export');
            Route::post('/sidebar-builder/{role}/import', [App\Http\Controllers\Admin\SidebarBuilderController::class, 'import'])->name('admin.sidebar-builder.import');
            
            // Preset Actions
            Route::get('/sidebar-builder/presets', [App\Http\Controllers\Admin\SidebarBuilderController::class, 'getPresets'])->name('admin.sidebar-builder.presets');
            Route::post('/sidebar-builder/{role}/preset', [App\Http\Controllers\Admin\SidebarBuilderController::class, 'applyPreset'])->name('admin.sidebar-builder.apply-preset');
        });

// Test Routes
Route::get('/test', function () {
    return response()->json([
        'message' => 'ZenaManage Dashboard System is working!',
        'status' => 'success',
        'timestamp' => now()
    ]);
});

// Bulk Operations Routes
Route::prefix('api/tasks/bulk')->name('tasks.bulk.')->group(function () {
    Route::post('/export', [App\Http\Controllers\Web\TaskBulkController::class, 'bulkExport'])->name('export');
    Route::post('/status-change', [App\Http\Controllers\Web\TaskBulkController::class, 'bulkStatusChange'])->name('status-change');
    Route::post('/assign', [App\Http\Controllers\Web\TaskBulkController::class, 'bulkAssign'])->name('assign');
    Route::post('/archive', [App\Http\Controllers\Web\TaskBulkController::class, 'bulkArchive'])->name('archive');
    Route::post('/delete', [App\Http\Controllers\Web\TaskBulkController::class, 'bulkDelete'])->name('delete');
    Route::post('/duplicate', [App\Http\Controllers\Web\TaskBulkController::class, 'duplicate'])->name('duplicate');
    Route::get('/download/{filename}', [App\Http\Controllers\Web\TaskBulkController::class, 'downloadExport'])->name('download');
});

Route::prefix('api/projects/bulk')->name('projects.bulk.')->group(function () {
    Route::post('/export', [App\Http\Controllers\Web\ProjectBulkController::class, 'bulkExport'])->name('export');
    Route::post('/status-change', [App\Http\Controllers\Web\ProjectBulkController::class, 'bulkStatusChange'])->name('status-change');
    Route::post('/assign', [App\Http\Controllers\Web\ProjectBulkController::class, 'bulkAssign'])->name('assign');
    Route::post('/archive', [App\Http\Controllers\Web\ProjectBulkController::class, 'bulkArchive'])->name('archive');
    Route::post('/delete', [App\Http\Controllers\Web\ProjectBulkController::class, 'bulkDelete'])->name('delete');
    Route::post('/duplicate', [App\Http\Controllers\Web\ProjectBulkController::class, 'duplicate'])->name('duplicate');
    Route::get('/download/{filename}', [App\Http\Controllers\Web\ProjectBulkController::class, 'downloadExport'])->name('download');
});

// Analytics Routes
Route::prefix('api/analytics')->name('analytics.')->group(function () {
    Route::get('/tasks', [App\Http\Controllers\Web\AnalyticsController::class, 'taskAnalytics'])->name('tasks');
    Route::get('/projects', [App\Http\Controllers\Web\AnalyticsController::class, 'projectAnalytics'])->name('projects');
    Route::get('/dashboard', [App\Http\Controllers\Web\AnalyticsController::class, 'dashboardAnalytics'])->name('dashboard');
    Route::get('/productivity', [App\Http\Controllers\Web\AnalyticsController::class, 'productivityMetrics'])->name('productivity');
});

// Document Management Routes
Route::prefix('api/documents')->name('documents.')->group(function () {
    Route::post('/upload/task', [App\Http\Controllers\Web\DocumentManagementController::class, 'uploadTaskDocument'])->name('upload-task');
    Route::post('/upload/project', [App\Http\Controllers\Web\DocumentManagementController::class, 'uploadProjectDocument'])->name('upload-project');
    Route::get('/task/{taskId}', [App\Http\Controllers\Web\DocumentManagementController::class, 'getTaskDocuments'])->name('get-task');
    Route::get('/project/{projectId}', [App\Http\Controllers\Web\DocumentManagementController::class, 'getProjectDocuments'])->name('get-project');
    Route::delete('/delete', [App\Http\Controllers\Web\DocumentManagementController::class, 'deleteDocument'])->name('delete');
    Route::get('/download/{filename}', [App\Http\Controllers\Web\DocumentManagementController::class, 'downloadDocument'])->name('download');
    Route::get('/categories', [App\Http\Controllers\Web\DocumentManagementController::class, 'getDocumentCategories'])->name('categories');
});

// Alert Management Routes
Route::prefix('api/alerts')->name('alerts.')->group(function () {
    Route::get('/', [App\Http\Controllers\Web\AlertController::class, 'getAlerts'])->name('index');
    Route::post('/create', [App\Http\Controllers\Web\AlertController::class, 'createAlert'])->name('create');
    Route::put('/status', [App\Http\Controllers\Web\AlertController::class, 'updateStatus'])->name('update-status');
    Route::delete('/delete', [App\Http\Controllers\Web\AlertController::class, 'deleteAlert'])->name('delete');
    Route::get('/statistics', [App\Http\Controllers\Web\AlertController::class, 'getStatistics'])->name('statistics');
});

// Tasks API Routes
Route::prefix('api/tasks')->name('tasks.api.')->group(function () {
    Route::get('/', [App\Http\Controllers\Web\TaskController::class, 'apiIndex'])->name('index');
});
