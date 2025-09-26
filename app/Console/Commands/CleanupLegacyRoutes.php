<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class CleanupLegacyRoutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zena:cleanup-legacy {--dry-run : Show what would be cleaned up without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up legacy routes and files from the old system structure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 Starting ZenaManage Legacy Cleanup...');
        
        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No files will be modified');
        }

        $this->cleanupOldViews();
        $this->cleanupOldControllers();
        $this->cleanupOldRoutes();
        $this->cleanupOldAssets();
        $this->updateDocumentation();

        $this->info('✅ Legacy cleanup completed successfully!');
    }

    /**
     * Clean up old view files
     */
    private function cleanupOldViews()
    {
        $this->info('📁 Cleaning up old view files...');
        
        $oldViews = [
            'resources/views/dashboards/admin.blade.php',
            'resources/views/dashboards/project-manager.blade.php',
            'resources/views/dashboards/designer.blade.php',
            'resources/views/dashboards/site-engineer.blade.php',
            'resources/views/dashboards/qc-engineer.blade.php',
            'resources/views/dashboards/procurement.blade.php',
            'resources/views/dashboards/finance.blade.php',
            'resources/views/dashboards/client.blade.php',
            'resources/views/admin/super-admin-dashboard.blade.php',
        ];

        foreach ($oldViews as $view) {
            if (File::exists($view)) {
                if ($this->option('dry-run')) {
                    $this->line("  Would remove: {$view}");
                } else {
                    File::delete($view);
                    $this->line("  Removed: {$view}");
                }
            }
        }
    }

    /**
     * Clean up old controller files
     */
    private function cleanupOldControllers()
    {
        $this->info('🎮 Cleaning up old controller files...');
        
        $oldControllers = [
            'app/Http/Controllers/DashboardController.php',
            'app/Http/Controllers/AdminDashboardController.php',
            'app/Http/Controllers/ProjectManagerDashboardController.php',
            'app/Http/Controllers/DesignerDashboardController.php',
            'app/Http/Controllers/SiteEngineerDashboardController.php',
            'app/Http/Controllers/QCEngineerDashboardController.php',
            'app/Http/Controllers/ProcurementDashboardController.php',
            'app/Http/Controllers/FinanceDashboardController.php',
            'app/Http/Controllers/ClientDashboardController.php',
        ];

        foreach ($oldControllers as $controller) {
            if (File::exists($controller)) {
                if ($this->option('dry-run')) {
                    $this->line("  Would remove: {$controller}");
                } else {
                    File::delete($controller);
                    $this->line("  Removed: {$controller}");
                }
            }
        }
    }

    /**
     * Clean up old route files
     */
    private function cleanupOldRoutes()
    {
        $this->info('🛣️ Cleaning up old route files...');
        
        $oldRoutes = [
            'routes/dashboard.php',
            'routes/admin.php',
            'routes/projects-old.php',
            'routes/tasks-old.php',
        ];

        foreach ($oldRoutes as $route) {
            if (File::exists($route)) {
                if ($this->option('dry-run')) {
                    $this->line("  Would remove: {$route}");
                } else {
                    File::delete($route);
                    $this->line("  Removed: {$route}");
                }
            }
        }
    }

    /**
     * Clean up old asset files
     */
    private function cleanupOldAssets()
    {
        $this->info('🎨 Cleaning up old asset files...');
        
        $oldAssets = [
            'public/css/dashboard.css',
            'public/css/admin.css',
            'public/js/dashboard.js',
            'public/js/admin.js',
        ];

        foreach ($oldAssets as $asset) {
            if (File::exists($asset)) {
                if ($this->option('dry-run')) {
                    $this->line("  Would remove: {$asset}");
                } else {
                    File::delete($asset);
                    $this->line("  Removed: {$asset}");
                }
            }
        }
    }

    /**
     * Update documentation
     */
    private function updateDocumentation()
    {
        $this->info('📚 Updating documentation...');
        
        if (!$this->option('dry-run')) {
            // Update README.md
            $readmePath = 'README.md';
            if (File::exists($readmePath)) {
                $readme = File::get($readmePath);
                $readme = str_replace('Old Dashboard System', 'New SPA System', $readme);
                $readme = str_replace('/dashboard', '/app/dashboard', $readme);
                $readme = str_replace('/admin', '/admin (Super Admin only)', $readme);
                File::put($readmePath, $readme);
                $this->line("  Updated: {$readmePath}");
            }

            // Clear caches
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            
            $this->line("  Cleared all caches");
        }
    }
}
