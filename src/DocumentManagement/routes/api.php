<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\DocumentManagement\Controllers\DocumentController;

/*
|--------------------------------------------------------------------------
| Document Management API Routes
|--------------------------------------------------------------------------
|
| Định nghĩa các route API cho module Document Management
| Tất cả routes đều có prefix /api/v1/documents và middleware auth:api, rbac
|
*/

Route::prefix('api/v1/documents')
    ->middleware(['auth:api', 'rbac'])
    ->group(function () {
        
        // Document CRUD routes
        Route::get('/', [DocumentController::class, 'index'])
            ->name('documents.index');
            
        Route::post('/', [DocumentController::class, 'store'])
            ->name('documents.store');
            
        Route::get('/{id}', [DocumentController::class, 'show'])
            ->name('documents.show')
            ->where('id', '[0-9]+');
            
        Route::put('/{id}', [DocumentController::class, 'update'])
            ->name('documents.update')
            ->where('id', '[0-9]+');
            
        Route::delete('/{id}', [DocumentController::class, 'destroy'])
            ->name('documents.destroy')
            ->where('id', '[0-9]+');
        
        // Document version management routes
        Route::post('/{id}/versions', [DocumentController::class, 'createVersion'])
            ->name('documents.versions.create')
            ->where('id', '[0-9]+');
            
        Route::post('/{id}/revert', [DocumentController::class, 'revertVersion'])
            ->name('documents.versions.revert')
            ->where('id', '[0-9]+');
        
        // Document approval routes
        Route::post('/{id}/approve-for-client', [DocumentController::class, 'approveForClient'])
            ->name('documents.approve-for-client')
            ->where('id', '[0-9]+');
        
        // File download routes
        Route::get('/{documentId}/download/{versionNumber?}', [DocumentController::class, 'downloadVersion'])
            ->name('documents.download')
            ->where(['documentId' => '[0-9]+', 'versionNumber' => '[0-9]+']);
        
        // Statistics routes
        Route::get('/statistics/{projectId}', [DocumentController::class, 'getStatistics'])
            ->name('documents.statistics')
            ->where('projectId', '[0-9]+');
    });