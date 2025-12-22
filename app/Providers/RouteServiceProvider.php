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
    public const HOME = '/app/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        // Configure route model bindings
        Route::model('project', \App\Models\Project::class);
        Route::model('task', \App\Models\Task::class);
        Route::model('user', \App\Models\User::class);
        Route::model('tenant', \App\Models\Tenant::class);
        Route::model('document', \App\Models\Document::class);
        Route::model('client', \App\Models\Client::class);
        Route::model('quote', \App\Models\Quote::class);
        Route::model('template', \App\Models\Template::class);

        $this->routes(function () {
            // Web (tối thiểu: auth, login/register, root redirect) - testing
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // App UI (tất cả /app/*) - testing
            Route::middleware('web.test')
                ->group(base_path('routes/app.php'));

            // Admin UI - testing
            Route::middleware(['web', 'auth:web'])
                ->group(base_path('routes/admin.php'));

            // API routes - testing
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));

            // API v1 routes - testing
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api_v1.php'));

            // Debug - testing
            Route::middleware('web')
                ->group(base_path('routes/debug.php'));

            // Security routes - testing
            Route::middleware('web')
                ->group(base_path('routes/security.php'));

            // Legacy redirects (301) - testing
            Route::middleware('web')
                ->group(base_path('routes/legacy.php'));

            // Test routes - only in testing/development
            if (app()->environment(['testing', 'local', 'development'])) {
                Route::middleware('api')
                    ->group(base_path('routes/test.php'));
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
        // API rate limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user('api')?->id ?: $request->ip());
        });

        // Tenants export rate limiting
        RateLimiter::for('tenants-exports', function (Request $request) {
            return Limit::perMinute(config('security.rate_limit_export_per_min', 10))
                ->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
