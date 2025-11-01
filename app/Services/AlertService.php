<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class AlertService
{
    /**
     * Get alerts for the current user/tenant
     */
    public function getAlerts(): array
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $cacheKey = "alerts_{$tenantId}";
        
        return Cache::remember($cacheKey, 30, function () use ($tenantId) {
            return $this->generateAlerts($tenantId);
        });
    }
    
    /**
     * Generate alerts based on tenant data
     */
    private function generateAlerts(string $tenantId): array
    {
        $isAdmin = Auth::user()->hasRole('super_admin');
        
        if ($isAdmin) {
            return $this->getAdminAlerts();
        } else {
            return $this->getTenantAlerts($tenantId);
        }
    }
    
    /**
     * Get admin-specific alerts
     */
    private function getAdminAlerts(): array
    {
        return [
            [
                'id' => 1,
                'level' => 'critical',
                'message' => 'High CPU usage detected on server',
                'time' => '2 minutes ago',
                'action' => 'resolve',
                'url' => '/admin/analytics?view=performance'
            ],
            [
                'id' => 2,
                'level' => 'high',
                'message' => 'Database connection pool near capacity',
                'time' => '15 minutes ago',
                'action' => 'acknowledge',
                'url' => '/admin/settings?view=database'
            ],
            [
                'id' => 3,
                'level' => 'high',
                'message' => 'SSL certificate expires in 7 days',
                'time' => '1 hour ago',
                'action' => 'resolve',
                'url' => '/admin/settings?view=security'
            ]
        ];
    }
    
    /**
     * Get tenant-specific alerts
     */
    private function getTenantAlerts(string $tenantId): array
    {
        return [
            [
                'id' => 1,
                'level' => 'critical',
                'message' => 'Project deadline approaching in 2 days',
                'time' => '5 minutes ago',
                'action' => 'resolve',
                'url' => '/app/projects?status=urgent'
            ],
            [
                'id' => 2,
                'level' => 'high',
                'message' => 'Team member offline for 4 hours',
                'time' => '30 minutes ago',
                'action' => 'acknowledge',
                'url' => '/app/team?view=status'
            ],
            [
                'id' => 3,
                'level' => 'high',
                'message' => 'Budget exceeded by 15%',
                'time' => '2 hours ago',
                'action' => 'resolve',
                'url' => '/app/projects?view=budget'
            ]
        ];
    }
    
    /**
     * Resolve an alert
     */
    public function resolveAlert(int $alertId): bool
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $cacheKey = "alerts_{$tenantId}";
        
        $alerts = Cache::get($cacheKey, []);
        $alerts = array_filter($alerts, fn($alert) => $alert['id'] !== $alertId);
        
        Cache::put($cacheKey, $alerts, 30);
        
        // In a real application, you would also update the database
        // Alert::where('id', $alertId)->update(['status' => 'resolved']);
        
        return true;
    }
    
    /**
     * Acknowledge an alert
     */
    public function acknowledgeAlert(int $alertId): bool
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $cacheKey = "alerts_{$tenantId}";
        
        $alerts = Cache::get($cacheKey, []);
        $alerts = array_filter($alerts, fn($alert) => $alert['id'] !== $alertId);
        
        Cache::put($cacheKey, $alerts, 30);
        
        // In a real application, you would also update the database
        // Alert::where('id', $alertId)->update(['status' => 'acknowledged']);
        
        return true;
    }
    
    /**
     * Mute an alert
     */
    public function muteAlert(int $alertId, int $durationMinutes = 60): bool
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $cacheKey = "alerts_{$tenantId}";
        
        $alerts = Cache::get($cacheKey, []);
        $alerts = array_filter($alerts, fn($alert) => $alert['id'] !== $alertId);
        
        Cache::put($cacheKey, $alerts, 30);
        
        // In a real application, you would also update the database
        // Alert::where('id', $alertId)->update([
        //     'status' => 'muted',
        //     'muted_until' => now()->addMinutes($durationMinutes)
        // ]);
        
        return true;
    }
    
    /**
     * Dismiss all alerts
     */
    public function dismissAllAlerts(): bool
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $cacheKey = "alerts_{$tenantId}";
        
        Cache::put($cacheKey, [], 30);
        
        // In a real application, you would also update the database
        // Alert::where('tenant_id', $tenantId)->update(['status' => 'dismissed']);
        
        return true;
    }
    
    /**
     * Create a new alert
     */
    public function createAlert(array $alertData): bool
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $cacheKey = "alerts_{$tenantId}";
        
        $alerts = Cache::get($cacheKey, []);
        $alerts[] = array_merge($alertData, [
            'id' => time(), // Simple ID generation
            'time' => 'Just now'
        ]);
        
        Cache::put($cacheKey, $alerts, 30);
        
        // In a real application, you would also save to database
        // Alert::create($alertData);
        
        return true;
    }
    
    /**
     * Get alert statistics
     */
    public function getAlertStats(): array
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $alerts = $this->getAlerts();
        
        $stats = [
            'total' => count($alerts),
            'critical' => count(array_filter($alerts, fn($alert) => $alert['level'] === 'critical')),
            'high' => count(array_filter($alerts, fn($alert) => $alert['level'] === 'high')),
            'medium' => count(array_filter($alerts, fn($alert) => $alert['level'] === 'medium')),
            'low' => count(array_filter($alerts, fn($alert) => $alert['level'] === 'low'))
        ];
        
        return $stats;
    }
}
