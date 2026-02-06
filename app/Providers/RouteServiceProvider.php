<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            // Simple API routes for testing middleware
            if ($this->app->environment('local')) {
                Route::prefix('api-simple')
                    ->group(base_path('routes/api-simple.php'));
            }
                
            // Main API routes (consolidated)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
            
            // Debug API routes (local/testing + debug=on)
            if ($this->app->environment(['local', 'testing']) && config('app.debug')) {
                Route::middleware('api')
                    ->prefix('api')
                    ->group(base_path('routes/debug_api.php'));
            }
                
            // Web routes
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
                
            // Debug routes (only in local environment)
            if (app()->environment('local')) {
                Route::middleware('web')
                    ->group(base_path('routes/debug.php'));
            }
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('zena-login', function (Request $request) {
            $identity = strtolower((string) ($request->input('email') ?? $request->input('username') ?? ''));
            return Limit::perMinute(20)->by($request->ip() . '|' . $identity);
        });
    }
}
