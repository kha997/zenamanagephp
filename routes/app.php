<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\ProjectController;
use App\Http\Controllers\Web\TaskController;
use App\Http\Controllers\Web\ClientController;
use App\Http\Controllers\Web\QuoteController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\TemplateController;
use App\Http\Controllers\App\DashboardController;

// Test routes without authentication for Playwright E2E tests
Route::get('/test-tasks', function () {
    $tenantId = '01K83FPK5XGPXF3V7ANJQRGX5X';
    $taskService = app(\App\Services\TaskService::class);
    $projectService = app(\App\Services\ProjectService::class);
    $userService = app(\App\Services\UserService::class);
    
    $tasks = $taskService->getTasks([], 50, 'created_at', 'desc', $tenantId);
    $projects = $projectService->getProjects([], 100, 'name', 'asc', $tenantId);
    $users = $userService->getUsers([], 100, 'name', 'asc', $tenantId);
    
    return view('app.tasks.index', [
        'tasks' => $tasks,
        'projects' => $projects,
        'users' => $users,
        'filters' => []
    ]);
})->name('test.tasks')->withoutMiddleware(['web']);

// Test route for Kanban board without authentication
Route::get('/test-kanban', function () {
    $tenantId = '01K83FPK5XGPXF3V7ANJQRGX5X';
    $taskService = app(\App\Services\TaskService::class);
    
    $tasks = $taskService->getTasksList([], '01K83FPK5XGPXF3V7ANJQRGX5X', $tenantId);
    $projects = \App\Models\Project::where('tenant_id', $tenantId)->get();
    $users = \App\Models\User::where('tenant_id', $tenantId)->get();
    
    return view('app.tasks.kanban-react', [
        'tasks' => $tasks,
        'projects' => $projects,
        'users' => $users,
        'filters' => []
    ]);
})->name('test.kanban')->withoutMiddleware(['web']);

// Test route for task detail page without authentication
Route::get('/test-tasks/{taskId}', function ($taskId) {
    $tenantId = '01K83FPK5XGPXF3V7ANJQRGX5X';
    $taskService = app(\App\Services\TaskService::class);
    
    // Get the task
    $task = $taskService->getTaskById($taskId, $tenantId);
    if (!$task) {
        abort(404, 'Task not found');
    }
    
    return view('app.tasks.show', [
        'task' => $task
    ]);
})->name('test.tasks.show')->withoutMiddleware(['web']);

Route::prefix('app')->name('app.')->middleware(['web.test'])->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ========================================
    // PROJECTS - READ ONLY (UI renders only)
    // ========================================
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::get('/projects/{project}/documents', [ProjectController::class, 'documents'])->name('projects.documents');
    Route::get('/projects/{project}/history', [ProjectController::class, 'history'])->name('projects.history');

    // ========================================
    // TASKS - READ ONLY (UI renders only)
    // ========================================
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/kanban', [TaskController::class, 'kanban'])->name('tasks.kanban');
    Route::get('/tasks/create', [TaskController::class, 'create'])->name('tasks.create');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::get('/tasks/{task}/documents', [TaskController::class, 'documents'])->name('tasks.documents');
    Route::get('/tasks/{task}/history', [TaskController::class, 'history'])->name('tasks.history');

    // ========================================
    // CLIENTS - READ ONLY (UI renders only)
    // ========================================
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');

    // ========================================
    // QUOTES - READ ONLY (UI renders only)
    // ========================================
    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/create', [QuoteController::class, 'create'])->name('quotes.create');
    Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::get('/quotes/{quote}/edit', [QuoteController::class, 'edit'])->name('quotes.edit');

    // ========================================
    // DOCUMENTS - READ ONLY (UI renders only)
    // ========================================
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::get('/documents/approvals', [DocumentController::class, 'approvals'])->name('documents.approvals');

    // ========================================
    // TEMPLATES - READ ONLY (UI renders only)
    // ========================================
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/library', [TemplateController::class, 'library'])->name('templates.library');
    Route::get('/templates/builder', [TemplateController::class, 'builder'])->name('templates.builder');
    Route::get('/templates/create', [TemplateController::class, 'create'])->name('templates.create');
    Route::get('/templates/{template}', [TemplateController::class, 'show'])->name('templates.show');
    Route::get('/templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');

    // ========================================
    // TEAM / CALENDAR / SETTINGS - READ ONLY
    // ========================================
    Route::get('/team', [\App\Http\Controllers\Web\TeamController::class, 'index'])->name('team.index');

    Route::get('/calendar', function () {
        $kpis = []; // Placeholder
        return view('app.calendar.index', compact('kpis'));
    })->name('calendar.index');

    Route::get('/settings', function () {
        return view('app.settings.index');
    })->name('settings.index');
});
