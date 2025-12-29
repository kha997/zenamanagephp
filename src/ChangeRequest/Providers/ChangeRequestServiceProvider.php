<?php declare(strict_types=1);

namespace Src\ChangeRequest\Providers;

use Illuminate\Support\ServiceProvider;
use Src\ChangeRequest\Services\ChangeRequestService;
use Src\ChangeRequest\Listeners\ChangeRequestEventListener;

/**
 * Service Provider cho module Change Request
 * 
 * Đăng ký:
 * - ChangeRequestService vào container
 * - Event listeners
 */
class ChangeRequestServiceProvider extends ServiceProvider
{
    /**
     * Đăng ký các services vào container
     *
     * @return void
     */
    public function register(): void
    {
        // Đăng ký ChangeRequestService
        $this->app->singleton(ChangeRequestService::class, function ($app) {
            return new ChangeRequestService();
        });
    }

    /**
     * Bootstrap các services
     *
     * @return void
     */
    public function boot(): void
    {
        // Đăng ký event listeners
        $this->registerEventListeners();
    }

    /**
     * Đăng ký event listeners
     *
     * @return void
     */
    private function registerEventListeners(): void
    {
        // Đăng ký ChangeRequestEventListener để xử lý tất cả events của Change Request
        $this->app['events']->subscribe(ChangeRequestEventListener::class);
    }

    /**
     * Danh sách các services được cung cấp bởi provider này
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            ChangeRequestService::class,
        ];
    }
}
