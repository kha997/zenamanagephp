<?php

namespace App\Providers;

use App\Cache\TrackingArrayStore;
use App\Services\AdvancedCacheService;
use App\Services\RateLimitService;
use App\Services\WebSocketService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
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
        $this->app->singleton(RateLimitService::class, RateLimitService::class);
        $this->app->singleton(AdvancedCacheService::class, AdvancedCacheService::class);
        $this->app->singleton(WebSocketService::class, WebSocketService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Cache::extend('array', function ($app, array $config) {
            $serializesValues = $config['serialize'] ?? false;

            if (! $app->environment('testing')) {
                return Cache::repository(new ArrayStore($serializesValues));
            }

            $store = new TrackingArrayStore($serializesValues);

            return Cache::repository($store);
        });

        if (app()->environment('testing')) {
            Redis::shouldReceive('ping')->andReturn('PONG');
        }

    }
}
