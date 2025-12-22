<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Share common data with all views
        View::composer('*', function ($view) {
            $user = Auth::user();
            $tenantId = $user ? $user->tenant_id : '01k5kzpfwd618xmwdwq3rej3jz';
            
            // Get cached navigation counters
            $navCounters = Cache::remember('nav-counters-' . $tenantId, 60, function () use ($tenantId) {
                    return $this->getNavigationCounters($tenantId);
                });

            $view->with([
                'currentTenant' => $tenantId,
                'currentUser' => $user,
                'navCounters' => $navCounters,
                'featureFlags' => $this->getFeatureFlags($tenantId, $user),
            ]);
        });

        // Register Blade components with aliases
        $this->registerBladeComponents();
    }

    /**
     * Get navigation counters for the current tenant
     */
    private function getNavigationCounters(string $tenantId): array
    {
        try {
            return [
                'projects_count' => \App\Models\Project::where('tenant_id', $tenantId)->count(),
                'tasks_count' => \App\Models\Task::where('tenant_id', $tenantId)->count(),
                'pending_tasks_count' => \App\Models\Task::where('tenant_id', $tenantId)
                    ->where('status', 'pending')->count(),
                'overdue_tasks_count' => \App\Models\Task::where('tenant_id', $tenantId)
                    ->where('due_date', '<', now())
                    ->where('status', '!=', 'completed')->count(),
            ];
        } catch (\Exception $e) {
            \Log::error('Failed to get navigation counters: ' . $e->getMessage());
            return [
                'projects_count' => 0,
                'tasks_count' => 0,
                'pending_tasks_count' => 0,
                'overdue_tasks_count' => 0,
            ];
        }
    }

    /**
     * Get feature flags for the current tenant/user
     */
    private function getFeatureFlags(string $tenantId, $user): array
    {
        $defaultFlags = config('features', []);
        
        // Get user-specific flags from session or database
        $userFlags = session('feature_flags', []);
        
        // Get tenant-specific flags (could be from database)
        $tenantFlags = Cache::remember('feature-flags-' . $tenantId, 300, function () use ($tenantId) {
                return $this->getTenantFeatureFlags($tenantId);
            });

        return array_merge($defaultFlags, $tenantFlags, $userFlags);
    }

    /**
     * Get tenant-specific feature flags
     */
    private function getTenantFeatureFlags(string $tenantId): array
    {
        // This could be from a database table in the future
        return [
            'projects' => [
                'view_mode' => session('projects_view_mode', config('features.projects.view_mode')),
            ],
            'tasks' => [
                'view_mode' => session('tasks_view_mode', config('features.tasks.view_mode')),
            ],
        ];
    }

    /**
     * Register Blade components with aliases
     */
    private function registerBladeComponents(): void
    {
        // Register component aliases for easier usage
        Blade::component('components.kpi.strip', 'kpi-strip');
        Blade::component('components.projects.filters', 'projects-filters');
        Blade::component('components.projects.table', 'projects-table');
        Blade::component('components.projects.card-grid', 'projects-card-grid');
        Blade::component('components.shared.empty-state', 'empty-state');
        Blade::component('components.shared.alert', 'alert');
        Blade::component('components.shared.pagination', 'pagination');
        Blade::component('components.shared.toolbar', 'toolbar');
    }
}
