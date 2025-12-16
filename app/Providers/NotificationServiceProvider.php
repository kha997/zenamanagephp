<?php declare(strict_types=1);

namespace App\Providers;

use App\Services\NotificationService;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService(
                $app->make(\App\Services\NotificationPreferenceService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Register notification event listeners
        $this->registerNotificationListeners();
    }

    /**
     * Register notification event listeners
     */
    private function registerNotificationListeners(): void
    {
        // Event listeners sẽ được đăng ký trong EventServiceProvider
        // hoặc sử dụng Event::listen() trong boot() method
    }
}
