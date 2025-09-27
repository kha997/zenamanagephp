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
            // Test route without any middleware
            Route::get('/test-simple', function () {
                return 'Simple test route works!';
            });
            
            // Admin routes (no middleware for now - will add later)
            Route::prefix('admin')->name('admin.')->group(function () {
                Route::view('/dashboard', 'admin.dashboard.index')->name('dashboard');
                Route::view('/tenants', 'admin.tenants.index')->name('tenants.index');
                Route::view('/users', 'admin.users.index')->name('users.index');
                Route::view('/security', 'admin.security.index')->name('security.index');
                Route::view('/settings', 'admin.settings.index')->name('settings.index');
                Route::view('/billing', 'admin.billing.index')->name('billing.index');
                Route::view('/maintenance', 'admin.maintenance.index')->name('maintenance.index');
                Route::view('/alerts', 'admin.alerts.index')->name('alerts.index');
            });
            
            // App routes (no middleware for now)
            Route::get('/app', function () {
                return view('app.dashboard.index');
            })->name('app.dashboard');
            
            Route::get('/app/tasks', function () {
                return view('app.tasks.index');
            })->name('app.tasks');
                
            // Temporarily disable other routes to isolate the issue
            /*
            // Simple API routes for testing middleware
            Route::prefix('api-simple')
                ->group(base_path('routes/api-simple.php'));
                
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
            */
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