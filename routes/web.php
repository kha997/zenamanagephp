<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Web\UserController;

/*
|--------------------------------------------------------------------------
| Web Routes - Authentication Module (Architecture Compliant)
|--------------------------------------------------------------------------
|
| Web routes only render views and handle UI interactions.
| All business logic is handled via API endpoints.
|
*/

// Root → Login
Route::get('/', fn() => redirect('/login'))->name('root');

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

// Login routes (render-only)
Route::get('/login', [LoginController::class, 'showLoginForm'])
    ->name('login')
    ->middleware(['web', 'guest']);

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
Route::post('/api/auth/login', [\App\Http\Controllers\Api\Auth\AuthenticationController::class, 'login'])
    ->middleware(['web', 'throttle:5,1']);

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
    // Dashboard
    Route::get('/app/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('app.dashboard');
    
    // Projects
    Route::get('/app/projects', [\App\Http\Controllers\Web\ProjectController::class, 'index'])->name('app.projects.index');
    
    // Tasks
    Route::get('/app/tasks', [\App\Http\Controllers\Web\TaskController::class, 'index'])->name('app.tasks.index');
    
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
    
    // REMOVED: Dangerous direct login route
    // This bypasses all authentication and should never be in production
}
