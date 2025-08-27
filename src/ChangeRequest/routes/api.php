<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\ChangeRequest\Controllers\ChangeRequestController;

/*
|--------------------------------------------------------------------------
| Change Request API Routes
|--------------------------------------------------------------------------
|
| Định nghĩa các route API cho module Change Request
| Tất cả routes đều có prefix /api/v1/change-requests và middleware auth:api, rbac
|
*/

Route::prefix('api/v1/change-requests')
    ->middleware(['auth:api', 'rbac'])
    ->group(function () {
        
        // Change Request CRUD routes
        Route::get('/', [ChangeRequestController::class, 'index'])
            ->name('change-requests.index');
            
        Route::post('/', [ChangeRequestController::class, 'store'])
            ->name('change-requests.store');
            
        Route::get('/{id}', [ChangeRequestController::class, 'show'])
            ->name('change-requests.show')
            ->where('id', '[0-9]+');
            
        Route::put('/{id}', [ChangeRequestController::class, 'update'])
            ->name('change-requests.update')
            ->where('id', '[0-9]+');
            
        Route::delete('/{id}', [ChangeRequestController::class, 'destroy'])
            ->name('change-requests.destroy')
            ->where('id', '[0-9]+');
        
        // Change Request workflow routes
        Route::post('/{id}/submit', [ChangeRequestController::class, 'submit'])
            ->name('change-requests.submit')
            ->where('id', '[0-9]+');
            
        Route::post('/{id}/approve', [ChangeRequestController::class, 'approve'])
            ->name('change-requests.approve')
            ->where('id', '[0-9]+');
            
        Route::post('/{id}/reject', [ChangeRequestController::class, 'reject'])
            ->name('change-requests.reject')
            ->where('id', '[0-9]+');
        
        // Statistics and reporting routes
        Route::get('/statistics/{projectId}', [ChangeRequestController::class, 'getStatistics'])
            ->name('change-requests.statistics')
            ->where('projectId', '[0-9]+');
            
        Route::get('/project/{projectId}', [ChangeRequestController::class, 'getByProject'])
            ->name('change-requests.by-project')
            ->where('projectId', '[0-9]+');
            
        Route::get('/pending-approval', [ChangeRequestController::class, 'getPendingApproval'])
            ->name('change-requests.pending-approval');
    });