<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\ChangeRequest\Controllers\ChangeRequestController;

/*
|--------------------------------------------------------------------------
| Change Request API Routes
|--------------------------------------------------------------------------
|
| Định nghĩa các route API cho module Change Request
| Các route này được gắn vào prefix /api/v1 trong routes/api.php và hưởng middleware bảo mật chung.
|
*/

Route::prefix('change-requests')->group(function () {
        
        // Change Request CRUD routes
        Route::get('/', [ChangeRequestController::class, 'index'])
            ->middleware('rbac:change_request.view')
            ->name('change-requests.index');
            
        Route::post('/', [ChangeRequestController::class, 'store'])
            ->middleware('rbac:change_request.create')
            ->name('change-requests.store');
            
        Route::get('/{id}', [ChangeRequestController::class, 'show'])
            ->middleware('rbac:change_request.view')
            ->name('change-requests.show')
            ->where('id', '[0-9]+');
            
        Route::put('/{id}', [ChangeRequestController::class, 'update'])
            ->middleware('rbac:change_request.edit')
            ->name('change-requests.update')
            ->where('id', '[0-9]+');
            
        Route::delete('/{id}', [ChangeRequestController::class, 'destroy'])
            ->middleware('rbac:change_request.delete')
            ->name('change-requests.destroy')
            ->where('id', '[0-9]+');
        
        // Change Request workflow routes
        Route::post('/{id}/submit', [ChangeRequestController::class, 'submit'])
            ->middleware('rbac:change_request.submit')
            ->name('change-requests.submit')
            ->where('id', '[0-9]+');
            
        Route::post('/{id}/approve', [ChangeRequestController::class, 'approve'])
            ->middleware('rbac:change_request.approve')
            ->name('change-requests.approve')
            ->where('id', '[0-9]+');
            
        Route::post('/{id}/reject', [ChangeRequestController::class, 'reject'])
            ->middleware('rbac:change_request.reject')
            ->name('change-requests.reject')
            ->where('id', '[0-9]+');
        
        // Statistics and reporting routes
        Route::get('/statistics/{projectId}', [ChangeRequestController::class, 'getStatistics'])
            ->middleware('rbac:change_request.stats')
            ->name('change-requests.statistics')
            ->where('projectId', '[0-9]+');
            
        Route::get('/project/{projectId}', [ChangeRequestController::class, 'getByProject'])
            ->middleware('rbac:change_request.view')
            ->name('change-requests.by-project')
            ->where('projectId', '[0-9]+');
            
        Route::get('/pending-approval', [ChangeRequestController::class, 'getPendingApproval'])
            ->middleware('rbac:change_request.approve')
            ->name('change-requests.pending-approval');
    });
