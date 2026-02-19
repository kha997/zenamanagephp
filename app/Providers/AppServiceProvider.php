<?php

namespace App\Providers;

use App\Auth\CustomSanctumGuard;
use Illuminate\Auth\RequestGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        if (config('database.default') === 'sqlite') {
            try {
                $connection = DB::connection();
                $grammar = $connection->getQueryGrammar();
                $grammar->macro('compileJsonContains', function ($column, $value) {
                    [$field, $path] = $this->wrapJsonFieldAndPath($column);

                    return sprintf('json_extract(%s%s) LIKE \'%%\' || %s || \'%%\'', $field, $path, $value);
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }

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
