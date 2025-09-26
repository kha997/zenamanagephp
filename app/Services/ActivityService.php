<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class ActivityService
{
    /**
     * Get recent activities for the current user/tenant
     */
    public function getRecentActivities(int $limit = 10): array
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $cacheKey = "activities_{$tenantId}";
        
        return Cache::remember($cacheKey, 60, function () use ($tenantId, $limit) {
            return $this->generateActivities($tenantId, $limit);
        });
    }
    
    /**
     * Generate activities based on tenant data
     */
    private function generateActivities(string $tenantId, int $limit): array
    {
        $isAdmin = Auth::user()->hasRole('super_admin');
        
        if ($isAdmin) {
            return $this->getAdminActivities($limit);
        } else {
            return $this->getTenantActivities($tenantId, $limit);
        }
    }
    
    /**
     * Get admin-specific activities
     */
    private function getAdminActivities(int $limit): array
    {
        return [
            [
                'id' => 1,
                'description' => 'New user registered',
                'user' => 'System',
                'time' => '2 hours ago',
                'icon' => 'fas fa-user-plus',
                'url' => '/admin/users'
            ],
            [
                'id' => 2,
                'description' => 'Tenant created new project',
                'user' => 'Admin',
                'time' => '4 hours ago',
                'icon' => 'fas fa-project-diagram',
                'url' => '/admin/projects'
            ],
            [
                'id' => 3,
                'description' => 'Security scan completed',
                'user' => 'System',
                'time' => '6 hours ago',
                'icon' => 'fas fa-shield-alt',
                'url' => '/admin/security'
            ],
            [
                'id' => 4,
                'description' => 'Database backup completed',
                'user' => 'System',
                'time' => '8 hours ago',
                'icon' => 'fas fa-database',
                'url' => '/admin/settings'
            ],
            [
                'id' => 5,
                'description' => 'Performance report generated',
                'user' => 'System',
                'time' => '12 hours ago',
                'icon' => 'fas fa-chart-bar',
                'url' => '/admin/analytics'
            ]
        ];
    }
    
    /**
     * Get tenant-specific activities
     */
    private function getTenantActivities(string $tenantId, int $limit): array
    {
        return [
            [
                'id' => 1,
                'description' => 'Created project "Website Redesign"',
                'user' => 'John Doe',
                'time' => '2 hours ago',
                'icon' => 'fas fa-project-diagram',
                'url' => '/app/projects/1'
            ],
            [
                'id' => 2,
                'description' => 'Updated task "Design Mockups"',
                'user' => 'Jane Smith',
                'time' => '4 hours ago',
                'icon' => 'fas fa-tasks',
                'url' => '/app/tasks/1'
            ],
            [
                'id' => 3,
                'description' => 'Uploaded document "Requirements.pdf"',
                'user' => 'Mike Johnson',
                'time' => '6 hours ago',
                'icon' => 'fas fa-file-alt',
                'url' => '/app/documents/1'
            ],
            [
                'id' => 4,
                'description' => 'Added team member "Sarah Wilson"',
                'user' => 'John Doe',
                'time' => '8 hours ago',
                'icon' => 'fas fa-user-plus',
                'url' => '/app/team'
            ],
            [
                'id' => 5,
                'description' => 'Completed task "Research Phase"',
                'user' => 'Jane Smith',
                'time' => '12 hours ago',
                'icon' => 'fas fa-check-circle',
                'url' => '/app/tasks/2'
            ],
            [
                'id' => 6,
                'description' => 'Created template "Project Kickoff"',
                'user' => 'Mike Johnson',
                'time' => '1 day ago',
                'icon' => 'fas fa-layer-group',
                'url' => '/app/templates/1'
            ],
            [
                'id' => 7,
                'description' => 'Updated project budget',
                'user' => 'John Doe',
                'time' => '1 day ago',
                'icon' => 'fas fa-dollar-sign',
                'url' => '/app/projects/1'
            ],
            [
                'id' => 8,
                'description' => 'Scheduled team meeting',
                'user' => 'Jane Smith',
                'time' => '2 days ago',
                'icon' => 'fas fa-calendar-alt',
                'url' => '/app/calendar'
            ]
        ];
    }
    
    /**
     * Log a new activity
     */
    public function logActivity(array $activityData): bool
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $cacheKey = "activities_{$tenantId}";
        
        $activities = Cache::get($cacheKey, []);
        array_unshift($activities, array_merge($activityData, [
            'id' => time(), // Simple ID generation
            'time' => 'Just now',
            'user' => Auth::user()->first_name . ' ' . Auth::user()->last_name
        ]));
        
        // Keep only the last 50 activities
        $activities = array_slice($activities, 0, 50);
        
        Cache::put($cacheKey, $activities, 60);
        
        // In a real application, you would also save to database
        // Activity::create($activityData);
        
        return true;
    }
    
    /**
     * Get activity statistics
     */
    public function getActivityStats(): array
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $activities = $this->getRecentActivities(50);
        
        $stats = [
            'total' => count($activities),
            'today' => count(array_filter($activities, fn($activity) => 
                strpos($activity['time'], 'hour') !== false || 
                strpos($activity['time'], 'minute') !== false ||
                strpos($activity['time'], 'Just now') !== false
            )),
            'this_week' => count(array_filter($activities, fn($activity) => 
                strpos($activity['time'], 'day') !== false && 
                (int)filter_var($activity['time'], FILTER_SANITIZE_NUMBER_INT) <= 7
            )),
            'by_user' => $this->getActivitiesByUser($activities)
        ];
        
        return $stats;
    }
    
    /**
     * Get activities grouped by user
     */
    private function getActivitiesByUser(array $activities): array
    {
        $userActivities = [];
        
        foreach ($activities as $activity) {
            $user = $activity['user'];
            if (!isset($userActivities[$user])) {
                $userActivities[$user] = 0;
            }
            $userActivities[$user]++;
        }
        
        return $userActivities;
    }
    
    /**
     * Get activities by type
     */
    public function getActivitiesByType(string $type): array
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $activities = $this->getRecentActivities(50);
        
        return array_filter($activities, fn($activity) => 
            strpos($activity['description'], $type) !== false
        );
    }
    
    /**
     * Clear old activities
     */
    public function clearOldActivities(int $daysOld = 30): bool
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $cacheKey = "activities_{$tenantId}";
        
        // In a real application, you would delete from database
        // Activity::where('created_at', '<', now()->subDays($daysOld))->delete();
        
        return true;
    }
}
