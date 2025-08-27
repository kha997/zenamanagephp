<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\CoreProject\Controllers\ProjectController;
use Src\CoreProject\Controllers\TaskController;
use Src\CoreProject\Controllers\ComponentController;
use Src\CoreProject\Controllers\WorkTemplateController;
use Src\CoreProject\Controllers\TaskAssignmentController;
use Src\CoreProject\Controllers\BaselineController;

Route::prefix('api/v1')
    ->middleware(['auth:api', 'rbac'])
    ->group(function () {
        // Project routes
        Route::apiResource('projects', ProjectController::class);
        
        // Project-specific component routes theo yêu cầu
        Route::prefix('projects/{project_id}')
            ->group(function () {
                // POST /projects/{project_id}/components
                Route::post('components', [ComponentController::class, 'store']);
                
                // GET /projects/{project_id}/components/tree
                Route::get('components/tree', [ComponentController::class, 'tree']);
                
                // GET /projects/{project_id}/components (list components)
                Route::get('components', [ComponentController::class, 'index']);
                
                // Baseline routes cho project
                // GET /projects/{project_id}/baselines
                Route::get('baselines', [BaselineController::class, 'index']);
                
                // POST /projects/{project_id}/baselines
                Route::post('baselines', [BaselineController::class, 'store']);
                
                // POST /projects/{project_id}/baselines/from-current
                Route::post('baselines/from-current', [BaselineController::class, 'createFromCurrent']);
                
                // GET /projects/{project_id}/baselines/report
                Route::get('baselines/report', [BaselineController::class, 'report']);
                
                // GET /projects/{project_id}/baselines/current
                Route::get('baselines/current', [BaselineController::class, 'getCurrent']);
                
                // GET /projects/{project_id}/variance
                Route::get('variance', [BaselineController::class, 'getVariance']);
            });
        
        // Component routes không phụ thuộc project
        // PATCH /components/{id}
        Route::patch('components/{id}', [ComponentController::class, 'update']);
        Route::get('components/{id}', [ComponentController::class, 'show']);
        Route::delete('components/{id}', [ComponentController::class, 'destroy']);
        
        // Baseline routes không phụ thuộc project
        // GET /baselines/{id}
        Route::get('baselines/{id}', [BaselineController::class, 'show']);
        
        // PUT/PATCH /baselines/{id}
        Route::patch('baselines/{id}', [BaselineController::class, 'update']);
        Route::put('baselines/{id}', [BaselineController::class, 'update']);
        
        // DELETE /baselines/{id}
        Route::delete('baselines/{id}', [BaselineController::class, 'destroy']);
        
        // POST /baselines/{id}/rebaseline
        Route::post('baselines/{id}/rebaseline', [BaselineController::class, 'rebaseline']);
        
        // GET /baselines/{id}/history
        Route::get('baselines/{id}/history', [BaselineController::class, 'getHistory']);
        
        // GET /baselines/{id1}/compare/{id2}
        Route::get('baselines/{id1}/compare/{id2}', [BaselineController::class, 'compare']);
        
        // Task routes
        Route::apiResource('tasks', TaskController::class);
        
        // Work template routes
        Route::apiResource('work-templates', WorkTemplateController::class);
        
        // Task assignment routes
        Route::apiResource('task-assignments', TaskAssignmentController::class);
    });