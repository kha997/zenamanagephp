<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register FeatureFlagService as singleton
        $this->app->singleton(\App\Services\FeatureFlagService::class, function ($app) {
            return new \App\Services\FeatureFlagService();
        });

        // Register Cache Services as singletons
        $this->app->singleton(\App\Services\KpiCacheService::class, function ($app) {
            return new \App\Services\KpiCacheService();
        });

        $this->app->singleton(\App\Services\SessionCacheService::class, function ($app) {
            return new \App\Services\SessionCacheService();
        });

        $this->app->singleton(\App\Services\ApiResponseCacheService::class, function ($app) {
            return new \App\Services\ApiResponseCacheService();
        });

        // Register QueryOptimizationService as singleton
        $this->app->singleton(\App\Services\QueryOptimizationService::class, function ($app) {
            return new \App\Services\QueryOptimizationService();
        });

        // Register Logging Services as singletons
        $this->app->singleton(\App\Services\ComprehensiveLoggingService::class, function ($app) {
            return new \App\Services\ComprehensiveLoggingService();
        });

        $this->app->singleton(\App\Services\LoggingConfigurationService::class, function ($app) {
            return new \App\Services\LoggingConfigurationService();
        });

        // Register Enhanced Validation Services as singletons
        $this->app->singleton(\App\Services\EnhancedValidationService::class, function ($app) {
            return new \App\Services\EnhancedValidationService(
                $app->make(\App\Services\InputSanitizationService::class)
            );
        });

        $this->app->singleton(\App\Services\ValidationConfigurationService::class, function ($app) {
            return new \App\Services\ValidationConfigurationService();
        });

        // Register Rate Limiting Services as singletons
        $this->app->singleton(\App\Services\RateLimitService::class, function ($app) {
            return new \App\Services\RateLimitService(
                $app->make(\App\Services\ComprehensiveLoggingService::class),
                $app->make(\App\Services\RateLimitConfigurationService::class)
            );
        });

        $this->app->singleton(\App\Services\RateLimitConfigurationService::class, function ($app) {
            return new \App\Services\RateLimitConfigurationService();
        });

        // Register Security Headers Services as singletons
        $this->app->singleton(\App\Services\SecurityHeadersService::class, function ($app) {
            return new \App\Services\SecurityHeadersService(
                $app->make(\App\Services\ComprehensiveLoggingService::class)
            );
        });

        // Register Error Handling Services as singletons
        $this->app->singleton(\App\Services\RequestCorrelationService::class, function ($app) {
            return new \App\Services\RequestCorrelationService();
        });

        $this->app->singleton(\App\Services\ErrorHandlingService::class, function ($app) {
            return new \App\Services\ErrorHandlingService(
                $app->make(\App\Services\ComprehensiveLoggingService::class),
                $app->make(\App\Services\RequestCorrelationService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        
    }
}