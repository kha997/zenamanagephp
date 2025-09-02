<?php declare(strict_types=1);

namespace Src\InteractionLogs\Providers;

use Illuminate\Support\ServiceProvider;
use Src\InteractionLogs\Services\InteractionLogService;
use Src\InteractionLogs\Services\InteractionLogQueryService;
use Src\InteractionLogs\Listeners\InteractionLogEventListener;

/**
 * Service Provider cho module Interaction Logs
 * 
 * Đăng ký:
 * - InteractionLogService và InteractionLogQueryService vào container
 * - Event listeners
 * - Routes
 */
class InteractionLogServiceProvider extends ServiceProvider
{
    /**
     * Đăng ký các services vào container
     *
     * @return void
     */
    public function register(): void
    {
        // Đăng ký InteractionLogService
        $this->app->singleton(InteractionLogService::class, function ($app) {
            return new InteractionLogService();
        });

        // Đăng ký InteractionLogQueryService
        $this->app->singleton(InteractionLogQueryService::class, function ($app) {
            return new InteractionLogQueryService();
        });
    }

    /**
     * Bootstrap các services
     *
     * @return void
     */
    public function boot(): void
    {
        // Load routes cho Interaction Logs
        $this->loadRoutes();
        
        // Đăng ký event listeners
        $this->registerEventListeners();
    }

    /**
     * Load routes cho Interaction Logs
     *
     * @return void
     */
    private function loadRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }

    /**
     * Đăng ký event listeners
     *
     * @return void
     */
    private function registerEventListeners(): void
    {
        // Đăng ký InteractionLogEventListener để xử lý tất cả events của Interaction Logs
        $this->app['events']->subscribe(InteractionLogEventListener::class);
    }

    /**
     * Danh sách các services được cung cấp bởi provider này
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            InteractionLogService::class,
            InteractionLogQueryService::class,
        ];
    }
}