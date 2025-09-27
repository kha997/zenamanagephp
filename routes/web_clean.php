<?php

use Illuminate\Support\Facades\Route;

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

// Root redirect
Route::get('/', function () {
    return redirect('/app/dashboard');
});

// Authentication Routes
Route::get('/login', [App\Http\Controllers\AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);
Route::get('/logout', [App\Http\Controllers\AuthController::class, 'logout'])->name('logout');

// Admin Routes - Super Admin only (temporary bypass)
Route::get('/admin', function () {
    return view('admin.simple-dashboard');
})->name('admin.dashboard');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return view('admin.dashboard');
    })->name('dashboard');
    
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
    
    Route::get('/settings', function () {
        return view('admin.settings');
    })->name('settings');
    
    Route::get('/maintenance', function () {
        return view('admin.maintenance');
    })->name('maintenance');
    
    Route::get('/sidebar-builder', function() {
        return view('admin.sidebar-builder');
    })->name('sidebar-builder');
});

// App Routes - Tenant users only
Route::prefix('app')->middleware(['auth', 'tenant.scope'])->name('app.')->group(function () {
    Route::get('/dashboard', function () {
        return view('app.dashboard');
    })->name('dashboard');
    
    Route::get('/projects', [App\Http\Controllers\ProjectController::class, 'index'])->name('projects');
    Route::get('/projects/create', [App\Http\Controllers\ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [App\Http\Controllers\ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [App\Http\Controllers\ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/edit', [App\Http\Controllers\ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('/projects/{project}', [App\Http\Controllers\ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [App\Http\Controllers\ProjectController::class, 'destroy'])->name('projects.destroy');
    
    // Project sub-resources
    Route::get('/projects/{project}/documents', [App\Http\Controllers\ProjectController::class, 'documents'])->name('projects.documents');
    Route::get('/projects/{project}/history', [App\Http\Controllers\ProjectController::class, 'history'])->name('projects.history');
    Route::get('/projects/{project}/design', function ($project) {
        return view('projects.design-project', compact('project'));
    })->name('projects.design');
    Route::get('/projects/{project}/construction', function ($project) {
        return view('projects.construction-project', compact('project'));
    })->name('projects.construction');
    
    // Tasks Routes
    Route::get('/tasks', [App\Http\Controllers\Web\TaskController::class, 'index'])->name('tasks');
    Route::get('/tasks/create', [App\Http\Controllers\Web\TaskController::class, 'create'])->name('tasks.create');
    Route::post('/tasks', [App\Http\Controllers\Web\TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [App\Http\Controllers\Web\TaskController::class, 'show'])->name('tasks.show');
    Route::get('/tasks/{task}/edit', [App\Http\Controllers\Web\TaskController::class, 'edit'])->name('tasks.edit');
    Route::put('/tasks/{task}', [App\Http\Controllers\Web\TaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{task}', [App\Http\Controllers\Web\TaskController::class, 'destroy'])->name('tasks.destroy');
    
    // Task actions (PATCH for state changes)
    Route::patch('/tasks/{task}/move', [App\Http\Controllers\Web\TaskController::class, 'move'])->name('tasks.move');
    Route::patch('/tasks/{task}/archive', [App\Http\Controllers\Web\TaskController::class, 'archive'])->name('tasks.archive');
    
    // Task sub-resources
    Route::get('/tasks/{task}/documents', [App\Http\Controllers\Web\TaskController::class, 'documents'])->name('tasks.documents');
    Route::post('/tasks/{task}/documents', [App\Http\Controllers\Web\TaskController::class, 'storeDocument'])->name('tasks.store-document');
    Route::get('/tasks/{task}/history', [App\Http\Controllers\Web\TaskController::class, 'history'])->name('tasks.history');
    
    // Documents Routes
    Route::get('/documents', [App\Http\Controllers\Web\DocumentController::class, 'index'])->name('documents');
    Route::get('/documents/create', [App\Http\Controllers\Web\DocumentController::class, 'create'])->name('documents.create');
    Route::get('/documents/approvals', [App\Http\Controllers\Web\DocumentController::class, 'approvals'])->name('documents.approvals');
    
    // Team Routes
    Route::get('/team', function () {
        return view('team.index');
    })->name('team');
    Route::get('/team/users', function () {
        return view('team.users');
    })->name('team.users');
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
Route::get('/calendar', function () {
    return view('calendar.index');
})->name('calendar');

// Invitation Routes
Route::prefix('invitations')->name('invitations.')->group(function () {
    Route::get('/accept/{token}', [App\Http\Controllers\InvitationController::class, 'accept'])->name('accept');
    Route::post('/accept/{token}', [App\Http\Controllers\InvitationController::class, 'processAcceptance'])->name('process-acceptance');
});

// Legacy Redirects (for backward compatibility)
Route::get('/dashboard/admin', function () {
    return redirect('/admin');
});

Route::get('/dashboard/{role}', function ($role) {
    return redirect("/app/dashboard/{$role}");
});

Route::get('/users', function () {
    return redirect('/app/users');
});

Route::get('/tenants', function () {
    return redirect('/admin/tenants');
});

// Health check route
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});
