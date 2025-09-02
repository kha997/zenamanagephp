<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Compensation\Controllers\CompensationController;

/*
|--------------------------------------------------------------------------
| Compensation API Routes
|--------------------------------------------------------------------------
|
| Định nghĩa các route API cho module Compensation
| Tất cả routes đều có prefix /api/v1/compensation và middleware auth:api
|
*/

Route::prefix('api/v1/compensation')
    ->middleware(['auth:api'])
    ->group(function () {
        
        // Task Compensation CRUD routes
        Route::get('/tasks', [CompensationController::class, 'index'])
            ->middleware('rbac:compensation.view')
            ->name('compensation.tasks.index');
            
        Route::get('/tasks/{taskId}', [CompensationController::class, 'showTaskCompensation'])
            ->middleware('rbac:compensation.view')
            ->name('compensation.tasks.show')
            ->where('taskId', '[0-9]+');
            
        Route::put('/tasks/{taskId}', [CompensationController::class, 'updateTaskCompensation'])
            ->middleware('rbac:compensation.edit')
            ->name('compensation.tasks.update')
            ->where('taskId', '[0-9]+');
        
        // Compensation workflow routes
        Route::post('/sync-assignments', [CompensationController::class, 'syncTaskAssignments'])
            ->middleware('rbac:compensation.sync')
            ->name('compensation.sync-assignments');
            
        Route::post('/preview', [CompensationController::class, 'previewCompensation'])
            ->middleware('rbac:compensation.preview')
            ->name('compensation.preview');
            
        Route::post('/apply-contract', [CompensationController::class, 'applyContract'])
            ->middleware('rbac:compensation.apply')
            ->name('compensation.apply-contract');
        
        // Statistics and reporting routes
        Route::get('/stats/{projectId}', [CompensationController::class, 'stats'])
            ->middleware('rbac:compensation.stats')
            ->name('compensation.stats')
            ->where('projectId', '[0-9]+');
            
        Route::get('/project/{projectId}', [CompensationController::class, 'index'])
            ->middleware('rbac:compensation.view')
            ->name('compensation.by-project')
            ->where('projectId', '[0-9]+');
    });