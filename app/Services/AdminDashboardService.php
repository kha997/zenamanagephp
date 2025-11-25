<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AdminDashboardService
 * 
 * Service để tổng hợp dữ liệu cho Admin Dashboard (system-wide)
 */
class AdminDashboardService
{
    /**
     * Get total users count (system-wide, all tenants)
     */
    public function getTotalUsers(): int
    {
        return Cache::remember('admin_dashboard_total_users', 60, function () {
            return User::count();
        });
    }

    /**
     * Get total projects count (system-wide, all tenants)
     */
    public function getTotalProjects(): int
    {
        return Cache::remember('admin_dashboard_total_projects', 60, function () {
            return Project::count();
        });
    }

    /**
     * Get total tasks count (system-wide, all tenants)
     */
    public function getTotalTasks(): int
    {
        return Cache::remember('admin_dashboard_total_tasks', 60, function () {
            return Task::count();
        });
    }

    /**
     * Get active sessions count
     * 
     * Note: This is a simplified implementation. In production, you might want to
     * track active sessions more accurately using Redis or a sessions table.
     */
    public function getActiveSessions(): int
    {
        try {
            // Check if sessions table exists
            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                // Count sessions that were active in the last 30 minutes
                $activeSessions = DB::table('sessions')
                    ->where('last_activity', '>', now()->subMinutes(30)->timestamp)
                    ->count();
                
                return $activeSessions;
            }
            
            // Fallback: return 0 if sessions table doesn't exist
            return 0;
        } catch (\Exception $e) {
            Log::warning('Failed to get active sessions count', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get recent system-wide activities
     * 
     * Returns activities from:
     * - User registrations
     * - Tenant creations
     * - Security events (if audit log exists)
     * - System events
     */
    public function getRecentActivities(int $limit = 10): array
    {
        $activities = [];
        
        try {
            // Get recent user registrations
            $recentUsers = User::orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'email', 'created_at', 'tenant_id']);
            
            foreach ($recentUsers as $user) {
                $activities[] = [
                    'id' => 'user_' . $user->id,
                    'type' => 'user',
                    'action' => 'registered',
                    'description' => "User {$user->name} ({$user->email}) registered",
                    'timestamp' => $user->created_at->toISOString(),
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ],
                ];
            }
            
            // Get recent tenant creations (if Tenant model exists)
            if (class_exists(\App\Models\Tenant::class)) {
                $recentTenants = \App\Models\Tenant::orderBy('created_at', 'desc')
                    ->limit(3)
                    ->get(['id', 'name', 'created_at']);
                
                foreach ($recentTenants as $tenant) {
                    $activities[] = [
                        'id' => 'tenant_' . $tenant->id,
                        'type' => 'system',
                        'action' => 'tenant_created',
                        'description' => "Tenant '{$tenant->name}' was created",
                        'timestamp' => $tenant->created_at->toISOString(),
                    ];
                }
            }
            
            // Get recent projects (system-wide)
            $recentProjects = Project::orderBy('created_at', 'desc')
                ->limit(3)
                ->get(['id', 'name', 'created_at', 'tenant_id']);
            
            foreach ($recentProjects as $project) {
                $activities[] = [
                    'id' => 'project_' . $project->id,
                    'type' => 'project',
                    'action' => 'created',
                    'description' => "Project '{$project->name}' was created",
                    'timestamp' => $project->created_at->toISOString(),
                ];
            }
            
            // Sort by timestamp (most recent first) and limit
            usort($activities, function ($a, $b) {
                return strcmp($b['timestamp'], $a['timestamp']);
            });
            
            return array_slice($activities, 0, $limit);
            
        } catch (\Exception $e) {
            Log::error('Failed to get recent activities', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [];
        }
    }

    /**
     * Get system health status
     * 
     * Returns: 'good', 'warning', or 'critical'
     * 
     * Health calculation based on:
     * - Database connectivity
     * - Cache status
     * - Queue status (if available)
     * - Error rate (if available)
     */
    public function getSystemHealth(): string
    {
        try {
            $healthScore = 100;
            $issues = [];
            
            // Check database connectivity
            try {
                DB::connection()->getPdo();
            } catch (\Exception $e) {
                $healthScore -= 50;
                $issues[] = 'database';
            }
            
            // Check cache
            try {
                Cache::put('health_check', 'ok', 1);
                if (Cache::get('health_check') !== 'ok') {
                    $healthScore -= 20;
                    $issues[] = 'cache';
                }
            } catch (\Exception $e) {
                $healthScore -= 20;
                $issues[] = 'cache';
            }
            
            // Determine health status
            if ($healthScore >= 90) {
                return 'good';
            } elseif ($healthScore >= 70) {
                return 'warning';
            } else {
                return 'critical';
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to calculate system health', [
                'error' => $e->getMessage()
            ]);
            
            // Default to warning if we can't determine health
            return 'warning';
        }
    }
}

