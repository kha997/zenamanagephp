<?php declare(strict_types=1);

namespace Src\WorkTemplate\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Src\WorkTemplate\Services\TemplateService;
use Src\WorkTemplate\Services\ProjectTaskService;
use Src\WorkTemplate\Listeners\WorkTemplateEventListener;

/**
 * Service Provider cho Work Template module
 * 
 * Đăng ký routes, services và bindings cho module Work Template
 */
class WorkTemplateServiceProvider extends ServiceProvider
{
    /**
     * Đăng ký services vào container
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(TemplateService::class, function ($app) {
            return new TemplateService();
        });
        
        $this->app->singleton(ProjectTaskService::class, function ($app) {
            return new ProjectTaskService();
        });
    }

    /**
     * Bootstrap services sau khi tất cả services đã được đăng ký
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadRoutes();
        $this->registerEventListeners();
    }

    /**
     * Load routes cho Work Template module
     *
     * @return void
     */
    protected function loadRoutes(): void
    {
        Route::middleware(['api'])
            ->prefix('api')
            ->group(base_path('src/WorkTemplate/routes/api.php'));
    }

    /**
     * Đăng ký Event Listeners
     *
     * @return void
     */
    protected function registerEventListeners(): void
    {
        $this->app['events']->subscribe(WorkTemplateEventListener::class);
    }
}
