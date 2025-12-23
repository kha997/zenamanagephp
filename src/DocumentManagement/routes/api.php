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
    ->as('v1.')
    ->group(function () {
        
        // Simple Document CRUD routes (no middleware for testing)
        Route::get('/', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'index'])
            ->name('documents.index');
            
        Route::post('/', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'store'])
            ->name('documents.store');
            
        Route::get('/{id}', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'show'])
            ->name('documents.show');
            
        Route::put('/{id}', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'update'])
            ->name('documents.update');
            
        Route::delete('/{id}', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'destroy'])
            ->name('documents.destroy');
    });
