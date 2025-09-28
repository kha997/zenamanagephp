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
                Route::view('/tenants/{id}', 'admin.tenants.show')->name('tenants.show');
                Route::view('/users', 'admin.users.index')->name('users.index');
                Route::view('/security', 'admin.security.index')->name('security.index');
                Route::view('/settings', 'admin.settings.index')->name('settings.index');
                Route::view('/billing', 'admin.billing.index')->name('billing.index');
                Route::view('/maintenance', 'admin.maintenance.index')->name('maintenance.index');
                Route::view('/alerts', 'admin.alerts.index')->name('alerts.index');
            });
            
            // API routes
            Route::prefix('api/admin')->name('api.admin.')->group(function () {
                // Tenants API
                Route::get('/tenants', [\App\Http\Controllers\Admin\TenantsApiController::class, 'index'])->name('tenants.index');
                Route::post('/tenants', [\App\Http\Controllers\Admin\TenantsApiController::class, 'store'])->name('tenants.store');
                
                // Export (must be before {id} route)
                Route::get('/tenants/export', [\App\Http\Controllers\Admin\TenantsApiController::class, 'export'])->name('tenants.export');
                
                // Bulk actions
                Route::post('/tenants:bulk', [\App\Http\Controllers\Admin\TenantsApiController::class, 'bulk'])->name('tenants.bulk');
                
                // Tenant actions
                Route::post('/tenants/{id}:enable', [\App\Http\Controllers\Admin\TenantsApiController::class, 'enable'])->name('tenants.enable');
                Route::post('/tenants/{id}:disable', [\App\Http\Controllers\Admin\TenantsApiController::class, 'disable'])->name('tenants.disable');
                Route::post('/tenants/{id}:change-plan', [\App\Http\Controllers\Admin\TenantsApiController::class, 'changePlan'])->name('tenants.change-plan');
                
                // Tenant CRUD (must be after specific routes)
                Route::get('/tenants/{id}', [\App\Http\Controllers\Admin\TenantsApiController::class, 'show'])->name('tenants.show');
                Route::patch('/tenants/{id}', [\App\Http\Controllers\Admin\TenantsApiController::class, 'update'])->name('tenants.update');
                Route::delete('/tenants/{id}', [\App\Http\Controllers\Admin\TenantsApiController::class, 'destroy'])->name('tenants.destroy');
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