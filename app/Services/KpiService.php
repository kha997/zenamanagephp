<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class KpiService
{
    /**
     * Get KPI cards for the current user/tenant
     */
    public function getKPICards(): array
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $cacheKey = "kpi_cards_{$tenantId}";
        
        return Cache::remember($cacheKey, 60, function () use ($tenantId) {
            return $this->generateKPICards($tenantId);
        });
    }
    
    /**
     * Generate KPI cards based on tenant data
     */
    private function generateKPICards(string $tenantId): array
    {
        $isAdmin = Auth::user()->hasRole('super_admin');
        
        if ($isAdmin) {
            return $this->getAdminKPICards();
        } else {
            return $this->getTenantKPICards($tenantId);
        }
    }
    
    /**
     * Get admin-specific KPI cards
     */
    private function getAdminKPICards(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Total Users',
                'value' => $this->getTotalUsers(),
                'delta' => $this->getUsersDelta(),
                'period' => 'vs last month',
                'link' => '/admin/users',
                'icon' => 'fas fa-users',
                'visible' => true,
                'description' => 'Total registered users'
            ],
            [
                'id' => 2,
                'title' => 'Active Tenants',
                'value' => $this->getActiveTenants(),
                'delta' => $this->getTenantsDelta(),
                'period' => 'vs last month',
                'link' => '/admin/tenants',
                'icon' => 'fas fa-building',
                'visible' => true,
                'description' => 'Active tenant organizations'
            ],
            [
                'id' => 3,
                'title' => 'System Projects',
                'value' => $this->getSystemProjects(),
                'delta' => $this->getProjectsDelta(),
                'period' => 'vs last month',
                'link' => '/admin/projects',
                'icon' => 'fas fa-project-diagram',
                'visible' => true,
                'description' => 'Total projects across all tenants'
            ],
            [
                'id' => 4,
                'title' => 'Critical Alerts',
                'value' => $this->getCriticalAlerts(),
                'delta' => $this->getAlertsDelta(),
                'period' => 'vs last week',
                'link' => '/admin/alerts',
                'icon' => 'fas fa-exclamation-triangle',
                'visible' => true,
                'description' => 'Critical system alerts'
            ],
            [
                'id' => 5,
                'title' => 'System Health',
                'value' => $this->getSystemHealth(),
                'delta' => $this->getHealthDelta(),
                'period' => 'vs last hour',
                'link' => '/admin/analytics',
                'icon' => 'fas fa-heartbeat',
                'visible' => false,
                'description' => 'Overall system health score'
            ],
            [
                'id' => 6,
                'title' => 'Storage Usage',
                'value' => $this->getStorageUsage(),
                'delta' => $this->getStorageDelta(),
                'period' => 'vs last week',
                'link' => '/admin/settings',
                'icon' => 'fas fa-hdd',
                'visible' => false,
                'description' => 'Disk storage utilization'
            ],
            [
                'id' => 7,
                'title' => 'API Requests',
                'value' => $this->getAPIRequests(),
                'delta' => $this->getAPIDelta(),
                'period' => 'vs last hour',
                'link' => '/admin/analytics',
                'icon' => 'fas fa-exchange-alt',
                'visible' => false,
                'description' => 'API requests per hour'
            ],
            [
                'id' => 8,
                'title' => 'Error Rate',
                'value' => $this->getErrorRate(),
                'delta' => $this->getErrorDelta(),
                'period' => 'vs last hour',
                'link' => '/admin/alerts',
                'icon' => 'fas fa-bug',
                'visible' => false,
                'description' => 'System error rate percentage'
            ]
        ];
    }
    
    /**
     * Get tenant-specific KPI cards
     */
    private function getTenantKPICards(string $tenantId): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Active Projects',
                'value' => $this->getActiveProjects($tenantId),
                'delta' => $this->getProjectsDelta($tenantId),
                'period' => 'vs last month',
                'link' => '/app/projects?status=active',
                'icon' => 'fas fa-project-diagram',
                'visible' => true,
                'description' => 'Currently active projects'
            ],
            [
                'id' => 2,
                'title' => 'Overdue Tasks',
                'value' => $this->getOverdueTasks($tenantId),
                'delta' => $this->getTasksDelta($tenantId),
                'period' => 'vs last week',
                'link' => '/app/tasks?status=overdue',
                'icon' => 'fas fa-tasks',
                'visible' => true,
                'description' => 'Tasks past due date'
            ],
            [
                'id' => 3,
                'title' => 'Team Members',
                'value' => $this->getTeamMembers($tenantId),
                'delta' => $this->getTeamDelta($tenantId),
                'period' => 'vs last month',
                'link' => '/app/team',
                'icon' => 'fas fa-users',
                'visible' => true,
                'description' => 'Active team members'
            ],
            [
                'id' => 4,
                'title' => 'Documents',
                'value' => $this->getDocuments($tenantId),
                'delta' => $this->getDocumentsDelta($tenantId),
                'period' => 'vs last week',
                'link' => '/app/documents',
                'icon' => 'fas fa-file-alt',
                'visible' => true,
                'description' => 'Total documents'
            ],
            [
                'id' => 5,
                'title' => 'Budget Used',
                'value' => $this->getBudgetUsage($tenantId),
                'delta' => $this->getBudgetDelta($tenantId),
                'period' => 'vs last month',
                'link' => '/app/projects?view=budget',
                'icon' => 'fas fa-dollar-sign',
                'visible' => false,
                'description' => 'Budget utilization percentage'
            ],
            [
                'id' => 6,
                'title' => 'Completion Rate',
                'value' => $this->getCompletionRate($tenantId),
                'delta' => $this->getCompletionDelta($tenantId),
                'period' => 'vs last month',
                'link' => '/app/projects?view=completion',
                'icon' => 'fas fa-chart-line',
                'visible' => false,
                'description' => 'Project completion rate'
            ],
            [
                'id' => 7,
                'title' => 'Client Satisfaction',
                'value' => $this->getClientSatisfaction($tenantId),
                'delta' => $this->getSatisfactionDelta($tenantId),
                'period' => 'vs last month',
                'link' => '/app/projects?view=satisfaction',
                'icon' => 'fas fa-star',
                'visible' => false,
                'description' => 'Average client rating'
            ],
            [
                'id' => 8,
                'title' => 'Resource Utilization',
                'value' => $this->getResourceUtilization($tenantId),
                'delta' => $this->getResourceDelta($tenantId),
                'period' => 'vs last week',
                'link' => '/app/team?view=utilization',
                'icon' => 'fas fa-chart-pie',
                'visible' => false,
                'description' => 'Team resource usage'
            ]
        ];
    }
    
    /**
     * Get user's KPI preferences
     */
    public function getUserKPIPreferences(): array
    {
        $userId = Auth::id();
        $cacheKey = "kpi_preferences_{$userId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            // This would typically come from a database
            return [
                'kpiRows' => 1,
                'visibleCards' => [1, 2, 3, 4] // Default visible cards
            ];
        });
    }
    
    /**
     * Save user's KPI preferences
     */
    public function saveUserKPIPreferences(array $preferences): void
    {
        $userId = Auth::id();
        $cacheKey = "kpi_preferences_{$userId}";
        
        Cache::put($cacheKey, $preferences, 3600);
        
        // In a real application, you would also save to database
        // UserPreference::updateOrCreate(['user_id' => $userId], $preferences);
    }
    
    // Mock data methods - these would be replaced with actual database queries
    
    private function getTotalUsers(): int
    {
        return 1247; // Mock data
    }
    
    private function getUsersDelta(): string
    {
        return '+23'; // Mock data
    }
    
    private function getActiveTenants(): int
    {
        return 45; // Mock data
    }
    
    private function getTenantsDelta(): string
    {
        return '+3'; // Mock data
    }
    
    private function getSystemProjects(): int
    {
        return 234; // Mock data
    }
    
    private function getProjectsDelta(): string
    {
        return '+12'; // Mock data
    }
    
    private function getCriticalAlerts(): int
    {
        return 3; // Mock data
    }
    
    private function getAlertsDelta(): string
    {
        return '-1'; // Mock data
    }
    
    private function getSystemHealth(): string
    {
        return '98%'; // Mock data
    }
    
    private function getHealthDelta(): string
    {
        return '+2%'; // Mock data
    }
    
    private function getStorageUsage(): string
    {
        return '67%'; // Mock data
    }
    
    private function getStorageDelta(): string
    {
        return '+5%'; // Mock data
    }
    
    private function getAPIRequests(): int
    {
        return 15420; // Mock data
    }
    
    private function getAPIDelta(): string
    {
        return '+8%'; // Mock data
    }
    
    private function getErrorRate(): string
    {
        return '0.2%'; // Mock data
    }
    
    private function getErrorDelta(): string
    {
        return '-0.1%'; // Mock data
    }
    
    private function getActiveProjects(string $tenantId): int
    {
        return 12; // Mock data
    }
    
    private function getOverdueTasks(string $tenantId): int
    {
        return 5; // Mock data
    }
    
    private function getTeamMembers(string $tenantId): int
    {
        return 8; // Mock data
    }
    
    private function getDocuments(string $tenantId): int
    {
        return 24; // Mock data
    }
    
    private function getBudgetUsage(string $tenantId): string
    {
        return '78%'; // Mock data
    }
    
    private function getCompletionRate(string $tenantId): string
    {
        return '92%'; // Mock data
    }
    
    private function getClientSatisfaction(string $tenantId): string
    {
        return '4.8'; // Mock data
    }
    
    private function getResourceUtilization(string $tenantId): string
    {
        return '85%'; // Mock data
    }
    
    private function getTasksDelta(string $tenantId): string
    {
        return '-1'; // Mock data
    }
    
    private function getTeamDelta(string $tenantId): string
    {
        return '+1'; // Mock data
    }
    
    private function getDocumentsDelta(string $tenantId): string
    {
        return '+3'; // Mock data
    }
    
    private function getBudgetDelta(string $tenantId): string
    {
        return '+5%'; // Mock data
    }
    
    private function getCompletionDelta(string $tenantId): string
    {
        return '+3%'; // Mock data
    }
    
    private function getSatisfactionDelta(string $tenantId): string
    {
        return '+0.2'; // Mock data
    }
    
    private function getResourceDelta(string $tenantId): string
    {
        return '-2%'; // Mock data
    }
}
