<?php

namespace App\Providers;

use App\Services\AdvancedCacheService;
use App\Services\RateLimitService;
use App\Services\WebSocketService;
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
        $this->app->singleton(RateLimitService::class, function () {
            return new RateLimitService();
        });

        $this->app->singleton(AdvancedCacheService::class, function () {
            return new AdvancedCacheService();
        });

        $this->app->singleton(WebSocketService::class, function () {
            return new WebSocketService();
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
