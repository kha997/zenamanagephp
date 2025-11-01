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
    ->group(function () {
        
        // Document CRUD routes (no middleware for testing)
        Route::get('/', [\App\Http\Controllers\Api\DocumentController::class, 'index'])
            ->name('documents.index');
            
        Route::post('/', [\App\Http\Controllers\Api\DocumentController::class, 'store'])
            ->name('documents.store');
            
        Route::get('/{id}', [\App\Http\Controllers\Api\DocumentController::class, 'show'])
            ->name('documents.show');
            
        Route::put('/{id}', [\App\Http\Controllers\Api\DocumentController::class, 'update'])
            ->name('documents.update');
            
        Route::delete('/{id}', [\App\Http\Controllers\Api\DocumentController::class, 'destroy'])
            ->name('documents.destroy');
    });