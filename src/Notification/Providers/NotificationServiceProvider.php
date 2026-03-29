<?php declare(strict_types=1);

namespace Src\Notification\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Notification\Services\NotificationService;
use Src\Notification\Services\NotificationRuleService;

/**
 * Service Provider cho module Notification
 * Đăng ký các service và cấu hình routing
 */
class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Đăng ký các service vào container
     */
    public function register(): void
    {
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService();
        });

        $this->app->singleton(NotificationRuleService::class, function ($app) {
            return new NotificationRuleService();
        });
    }

    /**
     * Bootstrap các service sau khi container đã sẵn sàng
     */
    public function boot(): void
    {
        // Routes are mounted explicitly from routes/api.php per routing architecture law.
    }
}
