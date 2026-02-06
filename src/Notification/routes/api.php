<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
// Sửa namespace từ zenamanage thành Src
use Src\Notification\Controllers\NotificationController;
use Src\Notification\Controllers\NotificationRuleController;

/*
|--------------------------------------------------------------------------
| Notification API Routes
|--------------------------------------------------------------------------
|
| Định nghĩa các route API cho module Notification
| Tất cả routes đều có prefix /api/v1/notifications và middleware auth:api, rbac
|
*/

Route::prefix('api/v1/notifications')
    ->middleware(['auth:api', 'rbac'])
    ->group(function () {
        
        // Notification CRUD routes
        Route::get('/', [NotificationController::class, 'index'])
            ->name('notifications.index');
            
        Route::post('/', [NotificationController::class, 'store'])
            ->name('notifications.store');
            
        Route::get('/{id}', [NotificationController::class, 'show'])
            ->name('notifications.show')
            ->where('id', '[0-9A-Za-z]+');
            
        Route::put('/{id}', [NotificationController::class, 'update'])
            ->name('notifications.update')
            ->where('id', '[0-9A-Za-z]+');
            
        Route::delete('/{id}', [NotificationController::class, 'destroy'])
            ->name('notifications.destroy')
            ->where('id', '[0-9A-Za-z]+');
        
        // Notification action routes
        Route::post('/{id}/mark-read', [NotificationController::class, 'markAsRead'])
            ->name('notifications.mark-read')
            ->where('id', '[0-9A-Za-z]+');
        
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead'])
            ->name('notifications.mark-read')
            ->where('id', '[0-9A-Za-z]+');
            
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])
            ->name('notifications.mark-all-read');
        
        // Statistics routes
        Route::get('/statistics', [NotificationController::class, 'getStatistics'])
            ->name('notifications.statistics');
            
        Route::get('/unread-count', [NotificationController::class, 'getUnreadCount'])
            ->name('notifications.unread-count');
    });

// Notification Rules routes
Route::prefix('api/v1/notification-rules')
    ->middleware(['auth:api', 'rbac'])
    ->group(function () {
        
        // Notification Rule CRUD routes
        Route::get('/', [NotificationRuleController::class, 'index'])
            ->name('notification-rules.index');
            
        Route::post('/', [NotificationRuleController::class, 'store'])
            ->name('notification-rules.store');
            
        Route::get('/{id}', [NotificationRuleController::class, 'show'])
            ->name('notification-rules.show')
            ->where('id', '[0-9]+');
            
        Route::put('/{id}', [NotificationRuleController::class, 'update'])
            ->name('notification-rules.update')
            ->where('id', '[0-9]+');
            
        Route::delete('/{id}', [NotificationRuleController::class, 'destroy'])
            ->name('notification-rules.destroy')
            ->where('id', '[0-9]+');
        
        // Notification Rule action routes
        Route::post('/{id}/toggle', [NotificationRuleController::class, 'toggle'])
            ->name('notification-rules.toggle')
            ->where('id', '[0-9]+');
            
        Route::get('/available-events', [NotificationRuleController::class, 'getAvailableEvents'])
            ->name('notification-rules.available-events');
            
        Route::get('/project/{projectId}', [NotificationRuleController::class, 'getByProject'])
            ->name('notification-rules.by-project')
            ->where('projectId', '[0-9A-Za-z]+');
    });
