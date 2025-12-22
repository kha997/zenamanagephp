<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Mobile\MobileController;

/*
|--------------------------------------------------------------------------
| Mobile App Optimization Routes
|--------------------------------------------------------------------------
|
| These routes handle mobile app optimization features including:
| - Mobile-optimized data endpoints
| - PWA support
| - Push notifications
| - Offline functionality
| - Mobile performance metrics
| - Mobile settings management
|
*/

Route::prefix('mobile')->group(function () {
    // Mobile-optimized data endpoints
    Route::get('/data', [MobileController::class, 'getMobileData'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('mobile.data');

    // PWA support
    Route::get('/manifest', [MobileController::class, 'getPWAManifest'])
        ->name('mobile.manifest');
    
    Route::get('/service-worker', [MobileController::class, 'getServiceWorkerScript'])
        ->name('mobile.service-worker');

    // Offline functionality
    Route::get('/offline-data', [MobileController::class, 'getOfflineData'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('mobile.offline-data');

    // Push notifications
    Route::post('/push-notification', [MobileController::class, 'sendPushNotification'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('mobile.push-notification');
    
    Route::post('/push-subscription', [MobileController::class, 'registerPushSubscription'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('mobile.push-subscription');

    // Mobile performance metrics
    Route::get('/performance-metrics', [MobileController::class, 'getMobilePerformanceMetrics'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('mobile.performance-metrics');

    // Mobile settings
    Route::get('/settings', [MobileController::class, 'getMobileSettings'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('mobile.settings');
    
    Route::put('/settings', [MobileController::class, 'updateMobileSettings'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('mobile.settings.update');

    // Mobile app info
    Route::get('/app-info', [MobileController::class, 'getMobileAppInfo'])
        ->name('mobile.app-info');

    // Mobile usage statistics
    Route::get('/usage-statistics', [MobileController::class, 'getMobileUsageStatistics'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('mobile.usage-statistics');

    // Mobile connectivity test
    Route::get('/connectivity-test', [MobileController::class, 'testMobileConnectivity'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('mobile.connectivity-test');

    // Mobile help and support
    Route::get('/help', [MobileController::class, 'getMobileHelp'])
        ->name('mobile.help');
});
