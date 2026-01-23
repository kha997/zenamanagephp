<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Quality\Controllers\InspectionController;

/*
|--------------------------------------------------------------------------
| Quality (Inspection) API Routes
|--------------------------------------------------------------------------
|
| Canonical inspection routes live under /api/v1/inspections and reuse
| the existing QcInspection model plus RBAC guard checks for every action.
|
*/

Route::prefix('v1/inspections')
    ->middleware(['auth:sanctum', 'tenant.isolation'])
    ->group(function () {
        Route::get('/', [InspectionController::class, 'index'])
            ->middleware('rbac:inspection.read');

        Route::post('/', [InspectionController::class, 'store'])
            ->middleware('rbac:inspection.create');

        Route::get('/{id}', [InspectionController::class, 'show'])
            ->middleware('rbac:inspection.read');

        Route::put('/{id}', [InspectionController::class, 'update'])
            ->middleware('rbac:inspection.update');

        Route::delete('/{id}', [InspectionController::class, 'destroy'])
            ->middleware('rbac:inspection.delete');

        Route::post('/{id}/schedule', [InspectionController::class, 'schedule'])
            ->middleware('rbac:inspection.schedule');

        Route::post('/{id}/conduct', [InspectionController::class, 'conduct'])
            ->middleware('rbac:inspection.conduct');

        Route::post('/{id}/complete', [InspectionController::class, 'complete'])
            ->middleware('rbac:inspection.complete');
    });
