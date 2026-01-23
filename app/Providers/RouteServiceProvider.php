<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Src\CoreProject\Models\Project as CoreProject;
use Src\CoreProject\Models\Task as CoreTask;

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

        Route::bind('task', function ($value) {
            return \App\Models\Task::find($value) ?? CoreTask::find($value);
        });

        Route::bind('project', function ($value) {
            return \App\Models\Project::find($value) ?? CoreProject::find($value);
        });

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
        // Temporarily disable rate limiting to fix cache issues
        /*
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user('api')?->id ?: $request->ip());
        });
        */
    }
}
