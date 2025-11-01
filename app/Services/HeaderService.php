<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/**
 * HeaderService
 * 
 * Provides real data for HeaderShell component
 * Replaces mock data and Alpine.js logic
 */
class HeaderService
{
    /**
     * Get navigation menu for user based on context
     */
    public function getNavigation(User $user, string $context = 'app'): array
    {
        $cacheKey = "header_navigation_{$user->id}_{$context}";
        
        return Cache::remember($cacheKey, 300, function() use ($user, $context) {
            $isAdmin = $user->hasRole('super_admin');
            $isAppAdmin = $user->hasRole('admin');
            
            if ($context === 'admin' && $isAdmin) {
                return $this->getAdminNavigation($user);
            } elseif ($context === 'app') {
                return $this->getAppNavigation($user, $isAppAdmin);
            }
            
            return [];
        });
    }
    
    /**
     * Get admin navigation menu
     */
    private function getAdminNavigation(User $user): array
    {
        $navigation = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'route' => 'admin.dashboard'],
            ['key' => 'users', 'label' => 'Users', 'icon' => 'fas fa-users', 'route' => 'admin.users.index'],
            ['key' => 'tenants', 'label' => 'Tenants', 'icon' => 'fas fa-building', 'route' => 'admin.tenants.index'],
            ['key' => 'projects', 'label' => 'Projects', 'icon' => 'fas fa-project-diagram', 'route' => 'admin.projects.index'],
            ['key' => 'security', 'label' => 'Security', 'icon' => 'fas fa-shield-alt', 'route' => 'admin.security.index'],
            ['key' => 'alerts', 'label' => 'Alerts', 'icon' => 'fas fa-exclamation-triangle', 'route' => 'admin.alerts.index'],
            ['key' => 'activities', 'label' => 'Activities', 'icon' => 'fas fa-history', 'route' => 'admin.activities.index'],
            ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'fas fa-chart-bar', 'route' => 'admin.analytics.index'],
            ['key' => 'maintenance', 'label' => 'Maintenance', 'icon' => 'fas fa-tools', 'route' => 'admin.maintenance.index'],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'fas fa-cog', 'route' => 'admin.settings.index']
        ];
        
        // Add alert count to alerts menu
        $alertCount = $this->getAlertCount($user);
        foreach ($navigation as &$item) {
            if ($item['key'] === 'alerts' && $alertCount > 0) {
                $item['badge'] = $alertCount;
            }
        }
        
        return $navigation;
    }
    
    /**
     * Get app navigation menu
     */
    private function getAppNavigation(User $user, bool $isAppAdmin): array
    {
        $navigation = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'route' => 'app.dashboard'],
            ['key' => 'projects', 'label' => 'Projects', 'icon' => 'fas fa-project-diagram', 'route' => 'app.projects.index'],
            ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'fas fa-tasks', 'route' => 'app.tasks.index'],
            ['key' => 'team', 'label' => 'Team', 'icon' => 'fas fa-users', 'route' => 'app.team.index'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'fas fa-chart-bar', 'route' => 'app.reports.index']
        ];
        
        // Add admin-only items if user is app admin
        if ($isAppAdmin) {
            $navigation[] = ['key' => 'settings', 'label' => 'Settings', 'icon' => 'fas fa-cog', 'route' => 'app.settings.index'];
        }
        
        return $navigation;
    }
    
    /**
     * Get user notifications
     */
    public function getNotifications(User $user): Collection
    {
        $cacheKey = "header_notifications_{$user->id}";
        
        return Cache::remember($cacheKey, 60, function() use ($user) {
            // Get real notifications from database
            $notifications = collect();
            
            // Add system notifications
            $systemNotifications = $this->getSystemNotifications($user);
            $notifications = $notifications->merge($systemNotifications);
            
            // Add project notifications
            $projectNotifications = $this->getProjectNotifications($user);
            $notifications = $notifications->merge($projectNotifications);
            
            // Add task notifications
            $taskNotifications = $this->getTaskNotifications($user);
            $notifications = $notifications->merge($taskNotifications);
            
            return $notifications->sortByDesc('created_at')->take(10);
        });
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount(User $user): int
    {
        $cacheKey = "header_unread_count_{$user->id}";
        
        return Cache::remember($cacheKey, 60, function() use ($user) {
            return $this->getNotifications($user)->where('read', false)->count();
        });
    }
    
    /**
     * Get system notifications
     */
    private function getSystemNotifications(User $user): Collection
    {
        // This would typically come from a notifications table
        // For now, return empty collection
        return collect();
    }
    
    /**
     * Get project notifications
     */
    private function getProjectNotifications(User $user): Collection
    {
        // Get recent project updates
        $projects = $user->projects()
            ->where('updated_at', '>=', now()->subDays(7))
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();
        
        return $projects->map(function($project) {
            return [
                'id' => 'project_' . $project->id,
                'title' => 'Project Updated',
                'message' => "Project \"{$project->name}\" was updated",
                'type' => 'info',
                'read' => false,
                'created_at' => $project->updated_at->toISOString(),
                'url' => route('app.projects.show', $project)
            ];
        });
    }
    
    /**
     * Get task notifications
     */
    private function getTaskNotifications(User $user): Collection
    {
        // Get recent task assignments
        $tasks = $user->tasks()
            ->where('created_at', '>=', now()->subDays(3))
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();
        
        return $tasks->map(function($task) {
            return [
                'id' => 'task_' . $task->id,
                'title' => 'New Task Assignment',
                'message' => "You have been assigned to task \"{$task->name}\"",
                'type' => 'assignment',
                'read' => false,
                'created_at' => $task->created_at->toISOString(),
                'url' => route('app.tasks.show', $task)
            ];
        });
    }
    
    /**
     * Get alert count for admin
     */
    public function getAlertCount(User $user): int
    {
        if (!$user->hasRole('super_admin')) {
            return 0;
        }
        
        $cacheKey = "header_alert_count_{$user->id}";
        
        return Cache::remember($cacheKey, 300, function() use ($user) {
            // Get system alerts
            $systemAlerts = $this->getSystemAlerts($user);
            
            // Get security alerts
            $securityAlerts = $this->getSecurityAlerts($user);
            
            // Get performance alerts
            $performanceAlerts = $this->getPerformanceAlerts($user);
            
            return $systemAlerts + $securityAlerts + $performanceAlerts;
        });
    }
    
    /**
     * Get system alerts
     */
    private function getSystemAlerts(User $user): int
    {
        // Check for system issues
        $alerts = 0;
        
        // Check disk space
        $diskUsage = $this->getDiskUsage();
        if ($diskUsage > 90) {
            $alerts++;
        }
        
        // Check memory usage
        $memoryUsage = $this->getMemoryUsage();
        if ($memoryUsage > 85) {
            $alerts++;
        }
        
        return $alerts;
    }
    
    /**
     * Get security alerts
     */
    private function getSecurityAlerts(User $user): int
    {
        // Check for failed login attempts
        $failedLogins = $this->getFailedLoginAttempts();
        
        // Check for suspicious activity
        $suspiciousActivity = $this->getSuspiciousActivity();
        
        return $failedLogins + $suspiciousActivity;
    }
    
    /**
     * Get performance alerts
     */
    private function getPerformanceAlerts(User $user): int
    {
        // Check for slow queries
        $slowQueries = $this->getSlowQueries();
        
        // Check for high error rates
        $errorRate = $this->getErrorRate();
        
        return $slowQueries + ($errorRate > 5 ? 1 : 0);
    }
    
    /**
     * Get breadcrumbs for current route
     */
    public function getBreadcrumbs(string $routeName, array $params = []): array
    {
        $breadcrumbs = [];
        
        // Add home breadcrumb
        $breadcrumbs[] = [
            'label' => 'Home',
            'url' => route('app.dashboard')
        ];
        
        // Parse route name to generate breadcrumbs
        $segments = explode('.', $routeName);
        
        if (count($segments) > 1) {
            $currentPath = '';
            
            for ($i = 0; $i < count($segments) - 1; $i++) {
                $currentPath .= $segments[$i] . '.';
                $route = rtrim($currentPath, '.') . '.index';
                
                if (Route::has($route)) {
                    $breadcrumbs[] = [
                        'label' => ucfirst($segments[$i]),
                        'url' => route($route)
                    ];
                }
            }
        }
        
        // Add current page (no URL)
        $currentLabel = ucfirst(end($segments));
        $breadcrumbs[] = [
            'label' => $currentLabel,
            'url' => null
        ];
        
        return $breadcrumbs;
    }
    
    /**
     * Get user theme preference
     */
    public function getUserTheme(User $user): string
    {
        return $user->preferences['theme'] ?? 'light';
    }
    
    /**
     * Set user theme preference
     */
    public function setUserTheme(User $user, string $theme): void
    {
        $preferences = $user->preferences ?? [];
        $preferences['theme'] = $theme;
        $user->preferences = $preferences;
        $user->save();
        
        // Clear cache
        Cache::forget("header_navigation_{$user->id}_app");
        Cache::forget("header_navigation_{$user->id}_admin");
    }
    
    // Helper methods for system monitoring
    
    private function getDiskUsage(): int
    {
        $bytes = disk_free_space('/');
        $totalBytes = disk_total_space('/');
        return (int) (100 - ($bytes / $totalBytes) * 100);
    }
    
    private function getMemoryUsage(): int
    {
        $memUsage = memory_get_usage(true);
        $memLimit = ini_get('memory_limit');
        $memLimitBytes = $this->convertToBytes($memLimit);
        return (int) (($memUsage / $memLimitBytes) * 100);
    }
    
    private function getFailedLoginAttempts(): int
    {
        // This would typically query a failed_logins table
        return 0;
    }
    
    private function getSuspiciousActivity(): int
    {
        // This would typically query security logs
        return 0;
    }
    
    private function getSlowQueries(): int
    {
        // This would typically query slow query logs
        return 0;
    }
    
    private function getErrorRate(): int
    {
        // This would typically query error logs
        return 0;
    }
    
    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
}
