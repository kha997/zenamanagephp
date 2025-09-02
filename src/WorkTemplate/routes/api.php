<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\WorkTemplate\Controllers\TemplateController;
use Src\WorkTemplate\Controllers\ProjectTaskController;

/*
|--------------------------------------------------------------------------
| Work Template API Routes (v1)
|--------------------------------------------------------------------------
|
| RESTful API routes cho Work Template system với RBAC middleware
| Tất cả routes được prefix với /api/v1/work-template/ theo chuẩn JSend
|
*/

Route::prefix('v1/work-template')->middleware(['auth:api'])->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Template Management Routes
    |--------------------------------------------------------------------------
    */
    
    // CRUD operations cho templates với RBAC permissions
    Route::get('templates', [TemplateController::class, 'index'])->middleware('rbac:template.view');
    Route::post('templates', [TemplateController::class, 'store'])->middleware('rbac:template.create');
    Route::get('templates/{template}', [TemplateController::class, 'show'])->middleware('rbac:template.view');
    Route::put('templates/{template}', [TemplateController::class, 'update'])->middleware('rbac:template.edit');
    Route::patch('templates/{template}', [TemplateController::class, 'update'])->middleware('rbac:template.edit');
    Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->middleware('rbac:template.delete');
    
    // Template specific actions
    Route::prefix('templates')->group(function () {
        // Áp dụng template vào project
        Route::post('{templateId}/apply', [TemplateController::class, 'applyToProject'])->middleware('rbac:template.apply');
        
        // Duplicate template
        Route::post('{templateId}/duplicate', [TemplateController::class, 'duplicate'])->middleware('rbac:template.create');
        
        // Template versions management
        Route::get('{templateId}/versions', [TemplateController::class, 'getVersions'])->middleware('rbac:template.view');
        Route::post('{templateId}/versions', [TemplateController::class, 'createVersion'])->middleware('rbac:template.edit');
        Route::get('{templateId}/versions/{versionId}', [TemplateController::class, 'getVersion'])->middleware('rbac:template.view');
        Route::put('{templateId}/versions/{versionId}/activate', [TemplateController::class, 'activateVersion'])->middleware('rbac:template.edit');
        
        // Template preview và validation
        Route::post('{templateId}/preview', [TemplateController::class, 'previewApplication'])->middleware('rbac:template.view');
        Route::post('{templateId}/validate', [TemplateController::class, 'validateTemplate'])->middleware('rbac:template.view');
        
        // Import/Export templates
        Route::post('import', [TemplateController::class, 'importTemplate'])->middleware('rbac:template.create');
        Route::get('{templateId}/export', [TemplateController::class, 'exportTemplate'])->middleware('rbac:template.view');
    });
    
    // Template metadata routes
    Route::prefix('templates/meta')->group(function () {
        Route::get('categories', [TemplateController::class, 'getCategories'])->middleware('rbac:template.view');
        Route::get('conditional-tags', [TemplateController::class, 'getConditionalTags'])->middleware('rbac:template.view');
        Route::get('statistics', [TemplateController::class, 'getStatistics'])->middleware('rbac:template.view');
    });
    
    /*
    |--------------------------------------------------------------------------
    | Project Task Management Routes
    |--------------------------------------------------------------------------
    */
    
    // Project tasks với filtering và pagination
    Route::prefix('projects/{projectId}')->group(function () {
        // Lấy danh sách tasks của project
        Route::get('tasks', [ProjectTaskController::class, 'index'])->middleware('rbac:task.view');
        
        // Task operations
        Route::prefix('tasks')->group(function () {
            // CRUD operations
            Route::post('/', [ProjectTaskController::class, 'store'])->middleware('rbac:task.create');
            Route::get('{taskId}', [ProjectTaskController::class, 'show'])->middleware('rbac:task.view');
            Route::put('{taskId}', [ProjectTaskController::class, 'update'])->middleware('rbac:task.edit');
            Route::delete('{taskId}', [ProjectTaskController::class, 'destroy'])->middleware('rbac:task.delete');
            
            // Task specific actions
            Route::put('{taskId}/progress', [ProjectTaskController::class, 'updateProgress'])->middleware('rbac:task.edit');
            Route::put('{taskId}/status', [ProjectTaskController::class, 'updateStatus'])->middleware('rbac:task.edit');
            Route::post('{taskId}/toggle-conditional', [ProjectTaskController::class, 'toggleConditionalVisibility'])->middleware('rbac:task.edit');
            
            // Bulk operations
            Route::post('bulk-update', [ProjectTaskController::class, 'bulkUpdate'])->middleware('rbac:task.edit');
            Route::post('bulk-toggle-conditional', [ProjectTaskController::class, 'bulkToggleConditional'])->middleware('rbac:task.edit');
        });
        
        // Project phases management
        Route::prefix('phases')->group(function () {
            Route::get('/', [ProjectTaskController::class, 'getPhases'])->middleware('rbac:task.view');
            Route::get('{phaseId}/tasks', [ProjectTaskController::class, 'getPhaseTask'])->middleware('rbac:task.view');
            Route::put('{phaseId}/reorder', [ProjectTaskController::class, 'reorderPhase'])->middleware('rbac:task.edit');
        });
        
        // Conditional tags management
        Route::prefix('conditional-tags')->group(function () {
            Route::get('/', [ProjectTaskController::class, 'getConditionalTags'])->middleware('rbac:task.view');
            Route::get('statistics', [ProjectTaskController::class, 'getConditionalTagStats'])->middleware('rbac:task.view');
            Route::post('{tag}/toggle', [ProjectTaskController::class, 'toggleConditionalTag'])->middleware('rbac:task.edit');
            Route::post('bulk-toggle', [ProjectTaskController::class, 'bulkToggleConditionalTags'])->middleware('rbac:task.edit');
        });
        
        // Project template sync
        Route::prefix('template-sync')->group(function () {
            Route::post('partial', [ProjectTaskController::class, 'partialSync'])->middleware('rbac:template.apply');
            Route::get('diff', [ProjectTaskController::class, 'getTemplateDiff'])->middleware('rbac:template.view');
            Route::post('apply-diff', [ProjectTaskController::class, 'applyTemplateDiff'])->middleware('rbac:template.apply');
        });
        
        // Project statistics và reports
        Route::prefix('reports')->group(function () {
            Route::get('progress', [ProjectTaskController::class, 'getProgressReport'])->middleware('rbac:task.view');
            Route::get('tasks-summary', [ProjectTaskController::class, 'getTasksSummary'])->middleware('rbac:task.view');
            Route::get('conditional-usage', [ProjectTaskController::class, 'getConditionalUsageReport'])->middleware('rbac:task.view');
        });
    });
    
    /*
    |--------------------------------------------------------------------------
    | Global Template System Routes
    |--------------------------------------------------------------------------
    */
    
    // Global search và discovery
    Route::prefix('search')->group(function () {
        Route::get('templates', [TemplateController::class, 'searchTemplates'])->middleware('rbac:template.view');
        Route::get('tasks', [ProjectTaskController::class, 'searchTasks'])->middleware('rbac:task.view');
    });
    
    // System health check và monitoring (không cần RBAC)
    Route::prefix('system')->group(function () {
        Route::get('health', function () {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'service' => 'Work Template System',
                    'version' => '1.0.0',
                    'timestamp' => now()->toISOString(),
                    'modules' => [
                        'templates' => 'active',
                        'project_tasks' => 'active',
                        'conditional_tags' => 'active',
                        'template_sync' => 'active'
                    ]
                ]
            ]);
        });
        
        Route::get('statistics', [TemplateController::class, 'getSystemStatistics'])->middleware('rbac:template.view');
    });
    
    /*
    |--------------------------------------------------------------------------
    | API Documentation Routes
    |--------------------------------------------------------------------------
    */
    
    // API info không cần RBAC
    Route::get('api-info', function () {
        return response()->json([
            'status' => 'success',
            'data' => [
                'api_version' => 'v1',
                'module' => 'Work Template System',
                'endpoints' => [
                    'templates' => 'Template CRUD và management',
                    'project_tasks' => 'Project task management với conditional logic',
                    'conditional_tags' => 'Conditional visibility management',
                    'template_sync' => 'Template synchronization với projects',
                    'reports' => 'Progress và usage reports'
                ],
                'authentication' => 'JWT with RBAC',
                'response_format' => 'JSend Specification',
                'features' => [
                    'partial_sync' => 'Đồng bộ một phần template với project',
                    'conditional_tags' => 'Ẩn/hiện tasks theo điều kiện',
                    'version_control' => 'Quản lý phiên bản template',
                    'bulk_operations' => 'Thao tác hàng loạt trên tasks',
                    'progress_tracking' => 'Theo dõi tiến độ chi tiết'
                ]
            ]
        ]);
    });
});