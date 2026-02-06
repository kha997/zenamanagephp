<?php

namespace App\Providers;

use App\Auth\CustomSanctumGuard;
use Illuminate\Auth\RequestGuard;
use Illuminate\Support\Facades\Auth;
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
        
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Auth::resolved(function ($auth) {
            $auth->extend('sanctum', function ($app, $name, array $config) use ($auth) {
                return tap(new RequestGuard(
                    new CustomSanctumGuard($auth, config('sanctum.expiration'), $config['provider'] ?? null),
                    request(),
                    $auth->createUserProvider($config['provider'] ?? null)
                ), function ($guard) {
                    app()->refresh('request', $guard, 'setRequest');
                });
            });
        });
    }
}
