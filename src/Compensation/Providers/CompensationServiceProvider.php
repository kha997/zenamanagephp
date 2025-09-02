<?php declare(strict_types=1);

namespace Src\Compensation\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Compensation\Services\CompensationService;
use Src\Compensation\Listeners\CompensationEventListener;

/**
 * Service Provider cho module Compensation
 * 
 * Đăng ký:
 * - CompensationService vào container
 * - Event listeners
 * - Routes
 */
class CompensationServiceProvider extends ServiceProvider
{
    /**
     * Đăng ký các services vào container
     *
     * @return void
     */
    public function register(): void
    {
        // Đăng ký CompensationService
        $this->app->singleton(CompensationService::class, function ($app) {
            return new CompensationService();
        });
    }

    /**
     * Bootstrap các services
     *
     * @return void
     */
    public function boot(): void
    {
        // Load routes cho Compensation
        $this->loadRoutes();
        
        // Đăng ký event listeners
        $this->registerEventListeners();
    }

    /**
     * Load routes cho Compensation
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
        // Đăng ký CompensationEventListener để xử lý tất cả events của Compensation
        $this->app['events']->subscribe(CompensationEventListener::class);
    }

    /**
     * Danh sách các services được cung cấp bởi provider này
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            CompensationService::class,
        ];
    }
}