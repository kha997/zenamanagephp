<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\CoreProject\Controllers\ProjectController;
use Src\CoreProject\Controllers\TaskController;
use Src\CoreProject\Controllers\ComponentController;
use Src\CoreProject\Controllers\WorkTemplateController;
use Src\CoreProject\Controllers\TaskAssignmentController;
use Src\CoreProject\Controllers\BaselineController;

Route::prefix('v1')
    ->middleware(['auth:api'])
    ->group(function () {
        // Project routes với RBAC permissions
        Route::get('projects', [ProjectController::class, 'index'])->middleware('rbac:project.view');
        Route::post('projects', [ProjectController::class, 'store'])->middleware('rbac:project.create');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->middleware('rbac:project.view');
        Route::put('projects/{project}', [ProjectController::class, 'update'])->middleware('rbac:project.edit');
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->middleware('rbac:project.edit');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('rbac:project.delete');
        
        // Project-specific component routes theo yêu cầu
        Route::prefix('projects/{projectId}')
            ->group(function () {
                // POST /projects/{projectId}/components
                Route::post('components', [ComponentController::class, 'store'])->middleware('rbac:component.create');

                // GET /projects/{projectId}/components/tree
                Route::get('components/tree', [ComponentController::class, 'tree'])->middleware('rbac:component.view');

                // GET /projects/{projectId}/components (list components)
                Route::get('components', [ComponentController::class, 'index'])->middleware('rbac:component.view');
                
                // Baseline routes cho project
                // GET /projects/{project_id}/baselines
                Route::get('baselines', [BaselineController::class, 'index'])->middleware('rbac:baseline.view');
                
                // POST /projects/{project_id}/baselines
                Route::post('baselines', [BaselineController::class, 'store'])->middleware('rbac:baseline.create');
                
                // POST /projects/{project_id}/baselines/from-current
                Route::post('baselines/from-current', [BaselineController::class, 'createFromCurrent'])->middleware('rbac:baseline.create');
                
                // GET /projects/{project_id}/baselines/report
                Route::get('baselines/report', [BaselineController::class, 'report'])->middleware('rbac:baseline.view');
                
                // GET /projects/{project_id}/baselines/current
                Route::get('baselines/current', [BaselineController::class, 'getCurrent'])->middleware('rbac:baseline.view');
                
                // GET /projects/{project_id}/variance
                Route::get('variance', [BaselineController::class, 'getVariance'])->middleware('rbac:baseline.view');
            });
        
        // Component routes không phụ thuộc project
        // PATCH /components/{id}
        Route::patch('components/{id}', [ComponentController::class, 'update'])->middleware('rbac:component.edit');
        Route::get('components/{id}', [ComponentController::class, 'show'])->middleware('rbac:component.view');
        Route::delete('components/{id}', [ComponentController::class, 'destroy'])->middleware('rbac:component.delete');
        
        // Baseline routes không phụ thuộc project
        // GET /baselines/{id}
        Route::get('baselines/{id}', [BaselineController::class, 'show'])->middleware('rbac:baseline.view');
        
        // PUT/PATCH /baselines/{id}
        Route::patch('baselines/{id}', [BaselineController::class, 'update'])->middleware('rbac:baseline.edit');
        Route::put('baselines/{id}', [BaselineController::class, 'update'])->middleware('rbac:baseline.edit');
        
        // DELETE /baselines/{id}
        Route::delete('baselines/{id}', [BaselineController::class, 'destroy'])->middleware('rbac:baseline.delete');
        
        // POST /baselines/{id}/rebaseline
        Route::post('baselines/{id}/rebaseline', [BaselineController::class, 'rebaseline'])->middleware('rbac:baseline.edit');
        
        // GET /baselines/{id}/history
        Route::get('baselines/{id}/history', [BaselineController::class, 'getHistory'])->middleware('rbac:baseline.view');
        
        // GET /baselines/{id1}/compare/{id2}
        Route::get('baselines/{id1}/compare/{id2}', [BaselineController::class, 'compare'])->middleware('rbac:baseline.view');
        
        // Task routes với RBAC permissions
        Route::get('tasks', [TaskController::class, 'index'])->middleware('rbac:task.view');
        Route::post('tasks', [TaskController::class, 'store'])->middleware('rbac:task.create');
        Route::get('tasks/{task}', [TaskController::class, 'show'])->middleware('rbac:task.view');
        Route::put('tasks/{task}', [TaskController::class, 'update'])->middleware('rbac:task.edit');
        Route::patch('tasks/{task}', [TaskController::class, 'update'])->middleware('rbac:task.edit');
        Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->middleware('rbac:task.delete');
        
        // Work template routes với RBAC permissions
        Route::get('work-templates', [WorkTemplateController::class, 'index'])->middleware('rbac:template.view');
        Route::post('work-templates', [WorkTemplateController::class, 'store'])->middleware('rbac:template.create');
        Route::get('work-templates/{workTemplate}', [WorkTemplateController::class, 'show'])->middleware('rbac:template.view');
        Route::put('work-templates/{workTemplate}', [WorkTemplateController::class, 'update'])->middleware('rbac:template.edit');
        Route::patch('work-templates/{workTemplate}', [WorkTemplateController::class, 'update'])->middleware('rbac:template.edit');
        Route::delete('work-templates/{workTemplate}', [WorkTemplateController::class, 'destroy'])->middleware('rbac:template.delete');
        
        // Task assignment routes với RBAC permissions
        Route::get('task-assignments', [TaskAssignmentController::class, 'index'])->middleware('rbac:task.assign');
        Route::post('task-assignments', [TaskAssignmentController::class, 'store'])->middleware('rbac:task.assign');
        Route::get('task-assignments/{taskAssignment}', [TaskAssignmentController::class, 'show'])->middleware('rbac:task.view');
        Route::put('task-assignments/{taskAssignment}', [TaskAssignmentController::class, 'update'])->middleware('rbac:task.assign');
        Route::patch('task-assignments/{taskAssignment}', [TaskAssignmentController::class, 'update'])->middleware('rbac:task.assign');
        Route::delete('task-assignments/{taskAssignment}', [TaskAssignmentController::class, 'destroy'])->middleware('rbac:task.assign');
    });
