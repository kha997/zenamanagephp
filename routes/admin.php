<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\TemplateSetController;
use App\Http\Middleware\AdminOnlyMiddleware;

Route::prefix('admin')->name('admin.')->middleware(['web', 'auth', AdminOnlyMiddleware::class])->group(function () {
    Route::prefix('templates')->name('templates.')->middleware([
        'feature.flag:features.tasks.enable_wbs_templates,Task Templates feature is not enabled'
    ])->group(function () {
        Route::get('/', [TemplateSetController::class, 'index'])->name('index');
        Route::get('/import', [TemplateSetController::class, 'importForm'])->name('import');
        Route::post('/import', [TemplateSetController::class, 'import'])->name('import.store');
        Route::get('/{set}', [TemplateSetController::class, 'show'])->name('show');
        Route::post('/', [TemplateSetController::class, 'store'])->name('store');
        Route::put('/{set}', [TemplateSetController::class, 'update'])->name('update');
        Route::delete('/{set}', [TemplateSetController::class, 'destroy'])->name('destroy');
    });
});
