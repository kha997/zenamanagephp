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
            
            // Admin routes (no middleware for testing)
            Route::prefix('admin')->name('admin.')->group(function () {
                Route::view('/dashboard', 'admin.dashboard.index')->name('dashboard');
                Route::view('/tenants', 'admin.tenants.index')->name('tenants.index');
                Route::view('/tenants/{id}', 'admin.tenants.show')->name('tenants.show');
                Route::view('/users', 'admin.users.index')->name('users.index');
                Route::view('/users/{id}', 'admin.users.show')->name('users.show');
                Route::view('/security', 'admin.security.index')->name('security.index');
                Route::view('/settings', 'admin.settings.index')->name('settings.index');
                Route::view('/billing', 'admin.billing.index')->name('billing.index');
                Route::view('/maintenance', 'admin.maintenance.index')->name('maintenance.index');
                Route::view('/alerts', 'admin.alerts.index')->name('alerts.index');
            });
            
            // Test route without prefix
            Route::get('/admin-test', function () {
                return response()->json(['message' => 'Admin test route works']);
            });
            
            // API routes
            Route::prefix('api/admin')->name('api.admin.')->group(function () {
                // Test route
                Route::get('/test', function () {
                    return response()->json(['message' => 'Admin API test route works']);
                });
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
                
                // Users API
                Route::get('/users', [\App\Http\Controllers\Admin\UsersApiController::class, 'index'])->name('users.index');
                Route::post('/users', [\App\Http\Controllers\Admin\UsersApiController::class, 'store'])->name('users.store');
                
                // Export (must be before {id} route)
                Route::get('/users/export', [\App\Http\Controllers\Admin\UsersApiController::class, 'export'])->name('users.export');
                
                // Bulk actions
                Route::post('/users:bulk', [\App\Http\Controllers\Admin\UsersApiController::class, 'bulk'])->name('users.bulk');
                
                // User actions
                Route::post('/users/{id}:enable', [\App\Http\Controllers\Admin\UsersApiController::class, 'enable'])->name('users.enable');
                Route::post('/users/{id}:disable', [\App\Http\Controllers\Admin\UsersApiController::class, 'disable'])->name('users.disable');
                Route::post('/users/{id}:unlock', [\App\Http\Controllers\Admin\UsersApiController::class, 'unlock'])->name('users.unlock');
                Route::post('/users/{id}:change-role', [\App\Http\Controllers\Admin\UsersApiController::class, 'changeRole'])->name('users.change-role');
                Route::post('/users/{id}:force-mfa', [\App\Http\Controllers\Admin\UsersApiController::class, 'forceMfa'])->name('users.force-mfa');
                Route::post('/users/{id}:send-reset-link', [\App\Http\Controllers\Admin\UsersApiController::class, 'sendResetLink'])->name('users.send-reset-link');
                
                // User CRUD (must be after specific routes)
                Route::get('/users/{id}', [\App\Http\Controllers\Admin\UsersApiController::class, 'show'])->name('users.show');
                Route::patch('/users/{id}', [\App\Http\Controllers\Admin\UsersApiController::class, 'update'])->name('users.update');
                Route::delete('/users/{id}', [\App\Http\Controllers\Admin\UsersApiController::class, 'destroy'])->name('users.destroy');
            });
            
            // App routes (no middleware for now)
            Route::get('/app', function () {
                return view('app.dashboard.index');
            })->name('app.dashboard');
            
            Route::get('/app/tasks', function () {
                return view('app.tasks.index');
            })->name('app.tasks');
                
            // Main API routes (consolidated)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
                
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
        // Temporarily disable rate limiting to fix cache issues
        /*
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user('api')?->id ?: $request->ip());
        });
        */
    }
}