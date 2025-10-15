<?php

/*
|--------------------------------------------------------------------------
| API v1 Routes (Simplified for Testing)
|--------------------------------------------------------------------------
|
| Simplified API v1 routes for testing architecture fixes
|
*/

use Illuminate\Support\Facades\Route;

// API v1 Routes - Simplified
Route::prefix('v1')->group(function () {
    
    // App API Routes - Tenant-scoped
    Route::prefix('app')->middleware(['auth:sanctum'])->group(function () {
        // Projects API - using existing ProjectManagementController
        Route::apiResource('projects', App\Http\Controllers\Unified\ProjectManagementController::class);
        Route::get('/projects/{project}/documents', [App\Http\Controllers\Unified\ProjectManagementController::class, 'documents']);
        Route::get('/projects/{project}/history', [App\Http\Controllers\Unified\ProjectManagementController::class, 'history']);
        
        // Tasks API
        Route::apiResource('tasks', App\Http\Controllers\Api\TasksController::class);
        Route::post('/tasks/{task}/assign', [App\Http\Controllers\Api\TasksController::class, 'assign']);
        Route::post('/tasks/{task}/unassign', [App\Http\Controllers\Api\TasksController::class, 'unassign']);
        Route::post('/tasks/{task}/progress', [App\Http\Controllers\Api\TasksController::class, 'updateProgress']);
        
        // Clients API
        Route::apiResource('clients', App\Http\Controllers\Api\ClientsController::class);
        Route::patch('/clients/{client}/lifecycle-stage', [App\Http\Controllers\Api\ClientsController::class, 'updateLifecycleStage']);
        
        // Quotes API
        Route::apiResource('quotes', App\Http\Controllers\Api\QuotesController::class);
        Route::post('/quotes/{quote}/send', [App\Http\Controllers\Api\QuotesController::class, 'send']);
        Route::post('/quotes/{quote}/accept', [App\Http\Controllers\Api\QuotesController::class, 'accept']);
        Route::post('/quotes/{quote}/reject', [App\Http\Controllers\Api\QuotesController::class, 'reject']);
        
        // Documents API
        Route::apiResource('documents', App\Http\Controllers\Api\DocumentsController::class);
        Route::get('/documents/approvals', [App\Http\Controllers\Api\DocumentsController::class, 'approvals']);
        
        // Templates API
        Route::apiResource('templates', App\Http\Controllers\Api\TemplatesController::class);
        Route::get('/templates/library', [App\Http\Controllers\Api\TemplatesController::class, 'library']);
        Route::get('/templates/builder', [App\Http\Controllers\Api\TemplatesController::class, 'builder']);
    });
});