<?php declare(strict_types=1);

namespace Src\DocumentManagement\Providers;

use Illuminate\Support\ServiceProvider;
use Src\DocumentManagement\Services\DocumentService;
use Src\DocumentManagement\Listeners\DocumentEventListener;

/**
 * Service Provider cho module Document Management
 */
class DocumentManagementServiceProvider extends ServiceProvider
{
    /**
     * Register any application services
     */
    public function register(): void
    {
        // Register DocumentService
        $this->app->singleton(DocumentService::class, function ($app) {
            return new DocumentService();
        });
    }

    /**
     * Bootstrap any application services
     */
    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        // Register event listeners
        $this->app['events']->subscribe(DocumentEventListener::class);
    }
}