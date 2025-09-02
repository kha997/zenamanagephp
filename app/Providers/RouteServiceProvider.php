<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

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
            // Main API routes
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // RBAC Module routes (Auth + RBAC management)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('src/RBAC/routes/api.php'));

            // Document Management Module routes
            if (file_exists(base_path('src/DocumentManagement/routes/api.php'))) {
                Route::group([], base_path('src/DocumentManagement/routes/api.php'));
            }

            // Change Request Module routes
            if (file_exists(base_path('src/ChangeRequest/routes/api.php'))) {
                Route::group([], base_path('src/ChangeRequest/routes/api.php'));
            }

            // Web routes
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user('api')?->id ?: $request->ip());  // Sửa từ $request->user()
        });
    }
}
