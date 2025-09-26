<?php

namespace App\Providers;

use App\Services\AuditService;
use App\Services\CalculationService;
use App\Services\ComponentService;
use App\Services\EventBusService;
use App\Services\NotificationRuleService;
use Illuminate\Support\ServiceProvider;

class CustomServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ComponentService::class, function ($app) {
            return new ComponentService();
        });

        $this->app->singleton(NotificationRuleService::class, function ($app) {
            return new NotificationRuleService();
        });

        $this->app->singleton(AuditService::class, function ($app) {
            return new AuditService();
        });

        $this->app->singleton(CalculationService::class, function ($app) {
            return new CalculationService();
        });

        $this->app->singleton(EventBusService::class, function ($app) {
            return new EventBusService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        
    }
}