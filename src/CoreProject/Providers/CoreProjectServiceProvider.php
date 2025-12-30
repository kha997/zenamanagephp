<?php declare(strict_types=1);

namespace Src\CoreProject\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
// Tạm thời comment các import chưa tồn tại
// use Src\CoreProject\Services\TaskService;
// use Src\CoreProject\Services\ConditionalTagService;
// use Src\CoreProject\Middleware\ProjectAccessMiddleware;
// use Src\CoreProject\Middleware\ProjectOwnershipMiddleware;
// use Src\CoreProject\Middleware\ProjectStatusMiddleware;
// use Src\CoreProject\Middleware\ComponentAccessMiddleware;
// use Src\CoreProject\Middleware\TaskAccessMiddleware;

/**
 * Service Provider chính cho module CoreProject
 * 
 * Đăng ký:
 * - Services và bindings
 * - Middleware
 * - Routes
 * - Event listeners (thông qua EventServiceProvider)
 */
class CoreProjectServiceProvider extends ServiceProvider
{
    /**
     * Đăng ký các services vào container
     *
     * @return void
     */
    public function register(): void
    {
        // Tạm thời comment các service binding cho đến khi tạo các class
        /*
        // Đăng ký Services
        $this->app->singleton(TaskService::class, function ($app) {
            return new TaskService();
        });
        
        $this->app->singleton(ConditionalTagService::class, function ($app) {
            return new ConditionalTagService();
        });
        */
        
        // Comment dòng này để tránh lỗi
        // $this->app->register(EventServiceProvider::class);
    }

    /**
     * Bootstrap các services
     *
     * @return void
     */
    public function boot(): void
    {
        // $this->registerMiddleware(); // Tạm thời comment
        // $this->loadRoutes(); // Comment để tránh duplicate routes
    }

    /**
     * Đăng ký middleware cho CoreProject
     *
     * @return void
     */
    private function registerMiddleware(): void
    {
        /*
        $router = $this->app->make(Router::class);
        
        // Đăng ký middleware aliases
        $router->aliasMiddleware('project.access', ProjectAccessMiddleware::class);
        $router->aliasMiddleware('project.ownership', ProjectOwnershipMiddleware::class);
        $router->aliasMiddleware('project.status', ProjectStatusMiddleware::class);
        $router->aliasMiddleware('component.access', ComponentAccessMiddleware::class);
        $router->aliasMiddleware('task.access', TaskAccessMiddleware::class);
        */
    }

    /**
     * Load routes cho CoreProject
     *
     * @return void
     */
    private function loadRoutes(): void
    {
        $routePath = __DIR__ . '/../routes/api.php';
        if (file_exists($routePath)) {
            // DISABLED (mounted in routes/api.php): $this->loadRoutesFrom($routePath);
        }
    }

    /**
     * Danh sách các services được cung cấp bởi provider này
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            // TaskService::class,
            // ConditionalTagService::class,
        ];
    }
}