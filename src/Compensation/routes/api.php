<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Compensation\Controllers\CompensationController;

/*
|--------------------------------------------------------------------------
| Compensation API Routes
|--------------------------------------------------------------------------
|
| Định nghĩa các route API cho module Compensation
| Tất cả routes đều có prefix /api/v1/compensation và middleware auth:api, rbac
|
*/

Route::prefix('api/v1/compensation')
    ->middleware(['auth:api', 'rbac'])
    ->group(function () {
        
        // Task Compensation CRUD routes
        Route::get('/tasks', [CompensationController::class, 'index'])
            ->name('compensation.tasks.index');
            
        Route::get('/tasks/{taskId}', [CompensationController::class, 'showTaskCompensation'])
            ->name('compensation.tasks.show')
            ->where('taskId', '[0-9]+');
            
        Route::put('/tasks/{taskId}', [CompensationController::class, 'updateTaskCompensation'])
            ->name('compensation.tasks.update')
            ->where('taskId', '[0-9]+');
        
        // Compensation workflow routes
        Route::post('/sync-assignments', [CompensationController::class, 'syncTaskAssignments'])
            ->name('compensation.sync-assignments');
            
        Route::post('/preview', [CompensationController::class, 'previewCompensation'])
            ->name('compensation.preview');
            
        Route::post('/apply-contract', [CompensationController::class, 'applyContract'])
            ->name('compensation.apply-contract');
        
        // Statistics and reporting routes
        Route::get('/stats/{projectId}', [CompensationController::class, 'stats'])
            ->name('compensation.stats')
            ->where('projectId', '[0-9]+');
            
        Route::get('/project/{projectId}', [CompensationController::class, 'index'])
            ->name('compensation.by-project')
            ->where('projectId', '[0-9]+');
    });