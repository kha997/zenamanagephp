<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Test\TestSeedController;

/*
|--------------------------------------------------------------------------
| Test Routes (Only available in testing/development)
|--------------------------------------------------------------------------
*/

if (app()->environment(['testing', 'local', 'development'])) {
    Route::prefix('__test__')->group(function () {
        Route::prefix('seed')->group(function () {
            Route::post('/user', [TestSeedController::class, 'createUser']);
            Route::get('/user/{email}', [TestSeedController::class, 'getUser']);
            Route::post('/tenant', [TestSeedController::class, 'createTenant']);
            Route::delete('/cleanup', [TestSeedController::class, 'cleanup']);
        });
    });
}

