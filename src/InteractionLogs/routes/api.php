<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\InteractionLogs\Controllers\InteractionLogController;

/**
 * API Routes cho module InteractionLogs
 * Prefix: /api/v1/interaction-logs
 */
Route::prefix('api/v1')->middleware(['auth:api', 'rbac'])->as('v1.')->group(function () {
    
    // Interaction Logs routes - Global
    Route::prefix('interaction-logs')->group(function () {
        
        // CRUD operations
        Route::get('/', [InteractionLogController::class, 'index'])
            ->name('interaction-logs.index');
            
        Route::post('/', [InteractionLogController::class, 'store'])
            ->name('interaction-logs.store');
            
        Route::get('/{interactionLog}', [InteractionLogController::class, 'show'])
            ->name('interaction-logs.show');
            
        Route::put('/{interactionLog}', [InteractionLogController::class, 'update'])
            ->name('interaction-logs.update');
            
        Route::delete('/{interactionLog}', [InteractionLogController::class, 'destroy'])
            ->name('interaction-logs.destroy');
        
        // Special operations
        Route::post('/{interactionLog}/approve-for-client', [InteractionLogController::class, 'approveForClient'])
            ->name('interaction-logs.approve-for-client');
            
        Route::get('/by-tag-path', [InteractionLogController::class, 'getByTagPath'])
            ->name('interaction-logs.by-tag-path');
    });
    
    // Project-specific Interaction Logs routes
    Route::prefix('projects/{projectId}/interaction-logs')->group(function () {
        
        // CRUD operations với project context
        Route::get('/', [InteractionLogController::class, 'indexByProject'])
            ->name('projects.interaction-logs.index')
            ->where('projectId', '[0-9]+');
            
        Route::post('/', [InteractionLogController::class, 'storeForProject'])
            ->name('projects.interaction-logs.store')
            ->where('projectId', '[0-9]+');
            
        Route::get('/{interactionLog}', [InteractionLogController::class, 'showInProject'])
            ->name('projects.interaction-logs.show')
            ->where('projectId', '[0-9]+');
            
        Route::put('/{interactionLog}', [InteractionLogController::class, 'updateInProject'])
            ->name('projects.interaction-logs.update')
            ->where('projectId', '[0-9]+');
            
        Route::delete('/{interactionLog}', [InteractionLogController::class, 'destroyInProject'])
            ->name('projects.interaction-logs.destroy')
            ->where('projectId', '[0-9]+');
        
        // Project-specific operations
        Route::post('/{interactionLog}/approve-for-client', [InteractionLogController::class, 'approveForClientInProject'])
            ->name('projects.interaction-logs.approve-for-client')
            ->where('projectId', '[0-9]+');
            
        Route::get('/by-tag-path', [InteractionLogController::class, 'getByTagPathInProject'])
            ->name('projects.interaction-logs.by-tag-path')
            ->where('projectId', '[0-9]+');
            
        // Autocomplete endpoint cho tag_path
        Route::get('/autocomplete/tag-path', [InteractionLogController::class, 'autocompleteTagPath'])
            ->name('projects.interaction-logs.autocomplete.tag-path')
            ->where('projectId', '[0-9]+');
            
        // Project statistics
        Route::get('/statistics', [InteractionLogController::class, 'getProjectStatistics'])
            ->name('projects.interaction-logs.statistics')
            ->where('projectId', '[0-9]+');
    });
});
