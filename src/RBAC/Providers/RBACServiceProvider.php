<?php declare(strict_types=1);

namespace Src\RBAC\Providers;

use Illuminate\Support\ServiceProvider;
use Src\RBAC\Services\AuthService;
use Src\RBAC\Services\RBACManager;
use Src\RBAC\Services\PermissionMatrixService;

/**
 * Service Provider cho module RBAC
 * Đăng ký các service và load routes
 */
class RBACServiceProvider extends ServiceProvider
{
    /**
     * Đăng ký các service vào container
     */
    public function register(): void
    {
        $this->app->singleton(AuthService::class, function ($app) {
            return new AuthService();
        });

        $this->app->singleton(RBACManager::class, function ($app) {
            return new RBACManager();
        });

        $this->app->singleton(PermissionMatrixService::class, function ($app) {
            return new PermissionMatrixService();
        });
    }

    /**
     * Bootstrap các service sau khi container đã sẵn sàng
     */
    public function boot(): void
    {
        // Load RBAC routes với prefix api/v1
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }
}