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

Route::prefix('v1/documents')
    ->as('v1.')
    ->middleware(['auth:sanctum', 'tenant.isolation', 'rbac'])
    ->group(function () {
        
        // Simple Document CRUD routes (no middleware for testing)
        Route::get('/', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'index'])
            ->name('documents.index');
            
        Route::post('/', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'store'])
            ->name('documents.store');
            
        Route::get('/{id}', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'show'])
            ->name('documents.show');

        Route::get('/{id}/download', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'download'])
            ->name('documents.download');

        Route::post('/{id}/versions', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'createVersion'])
            ->name('documents.versions.store');

        Route::get('/{id}/versions', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'getVersions'])
            ->name('documents.versions.index');
        
        Route::put('/{id}', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'update'])
            ->name('documents.update');
            
        Route::patch('/{id}', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'update'])
            ->name('documents.update.patch');
            
        Route::delete('/{id}', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'destroy'])
            ->name('documents.destroy');
    });
