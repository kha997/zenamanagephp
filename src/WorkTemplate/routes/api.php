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

Route::prefix('v1/work-template')->middleware(['auth:api', 'rbac'])->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Template Management Routes
    |--------------------------------------------------------------------------
    */
    
    // CRUD operations cho templates
    Route::apiResource('templates', TemplateController::class);
    
    // Template specific actions
    Route::prefix('templates')->group(function () {
        // Áp dụng template vào project
        Route::post('{templateId}/apply', [TemplateController::class, 'applyToProject']);
        
        // Duplicate template
        Route::post('{templateId}/duplicate', [TemplateController::class, 'duplicate']);
        
        // Template versions management
        Route::get('{templateId}/versions', [TemplateController::class, 'getVersions']);
        Route::post('{templateId}/versions', [TemplateController::class, 'createVersion']);
        Route::get('{templateId}/versions/{versionId}', [TemplateController::class, 'getVersion']);
        Route::put('{templateId}/versions/{versionId}/activate', [TemplateController::class, 'activateVersion']);
        
        // Template preview và validation
        Route::post('{templateId}/preview', [TemplateController::class, 'previewApplication']);
        Route::post('{templateId}/validate', [TemplateController::class, 'validateTemplate']);
        
        // Import/Export templates
        Route::post('import', [TemplateController::class, 'importTemplate']);
        Route::get('{templateId}/export', [TemplateController::class, 'exportTemplate']);
    });
    
    // Template metadata routes
    Route::prefix('templates/meta')->group(function () {
        Route::get('categories', [TemplateController::class, 'getCategories']);
        Route::get('conditional-tags', [TemplateController::class, 'getConditionalTags']);
        Route::get('statistics', [TemplateController::class, 'getStatistics']);
    });
    
    /*
    |--------------------------------------------------------------------------
    | Project Task Management Routes
    |--------------------------------------------------------------------------
    */
    
    // Project tasks với filtering và pagination
    Route::prefix('projects/{projectId}')->group(function () {
        // Lấy danh sách tasks của project
        Route::get('tasks', [ProjectTaskController::class, 'index']);
        
        // Task operations
        Route::prefix('tasks')->group(function () {
            // CRUD operations
            Route::post('/', [ProjectTaskController::class, 'store']);
            Route::get('{taskId}', [ProjectTaskController::class, 'show']);
            Route::put('{taskId}', [ProjectTaskController::class, 'update']);
            Route::delete('{taskId}', [ProjectTaskController::class, 'destroy']);
            
            // Task specific actions
            Route::put('{taskId}/progress', [ProjectTaskController::class, 'updateProgress']);
            Route::put('{taskId}/status', [ProjectTaskController::class, 'updateStatus']);
            Route::post('{taskId}/toggle-conditional', [ProjectTaskController::class, 'toggleConditionalVisibility']);
            
            // Bulk operations
            Route::post('bulk-update', [ProjectTaskController::class, 'bulkUpdate']);
            Route::post('bulk-toggle-conditional', [ProjectTaskController::class, 'bulkToggleConditional']);
        });
        
        // Project phases management
        Route::prefix('phases')->group(function () {
            Route::get('/', [ProjectTaskController::class, 'getPhases']);
            Route::get('{phaseId}/tasks', [ProjectTaskController::class, 'getPhaseTask']);
            Route::put('{phaseId}/reorder', [ProjectTaskController::class, 'reorderPhase']);
        });
        
        // Conditional tags management
        Route::prefix('conditional-tags')->group(function () {
            Route::get('/', [ProjectTaskController::class, 'getConditionalTags']);
            Route::get('statistics', [ProjectTaskController::class, 'getConditionalTagStats']);
            Route::post('{tag}/toggle', [ProjectTaskController::class, 'toggleConditionalTag']);
            Route::post('bulk-toggle', [ProjectTaskController::class, 'bulkToggleConditionalTags']);
        });
        
        // Project template sync
        Route::prefix('template-sync')->group(function () {
            Route::post('partial', [ProjectTaskController::class, 'partialSync']);
            Route::get('diff', [ProjectTaskController::class, 'getTemplateDiff']);
            Route::post('apply-diff', [ProjectTaskController::class, 'applyTemplateDiff']);
        });
        
        // Project statistics và reports
        Route::prefix('reports')->group(function () {
            Route::get('progress', [ProjectTaskController::class, 'getProgressReport']);
            Route::get('tasks-summary', [ProjectTaskController::class, 'getTasksSummary']);
            Route::get('conditional-usage', [ProjectTaskController::class, 'getConditionalUsageReport']);
        });
    });
    
    /*
    |--------------------------------------------------------------------------
    | Global Template System Routes
    |--------------------------------------------------------------------------
    */
    
    // Global search và discovery
    Route::prefix('search')->group(function () {
        Route::get('templates', [TemplateController::class, 'searchTemplates']);
        Route::get('tasks', [ProjectTaskController::class, 'searchTasks']);
    });
    
    // System health check và monitoring
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
        
        Route::get('statistics', [TemplateController::class, 'getSystemStatistics']);
    });
    
    /*
    |--------------------------------------------------------------------------
    | API Documentation Routes
    |--------------------------------------------------------------------------
    */
    
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