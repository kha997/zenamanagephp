<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LegacyRouteMonitoringService
{
    /**
     * Record legacy route usage
     */
    public function recordUsage(string $legacyPath, string $newPath, array $metadata = []): void
    {
        $key = "legacy_route_usage:{$legacyPath}";
        $dailyKey = "legacy_route_daily:" . date('Y-m-d') . ":{$legacyPath}";
        
        // Increment usage counters
        Cache::increment($key, 1);
        Cache::increment($dailyKey, 1);
        
        // Set TTL for daily counter (7 days)
        Cache::put($dailyKey, Cache::get($dailyKey, 0), now()->addDays(7));
        
        // Log detailed usage
        Log::info('Legacy route usage recorded', array_merge([
            'legacy_path' => $legacyPath,
            'new_path' => $newPath,
            'timestamp' => now()->toISOString(),
            'date' => date('Y-m-d'),
            'hour' => date('H'),
        ], $metadata));
    }

    /**
     * Get usage statistics for a legacy route
     */
    public function getUsageStats(string $legacyPath): array
    {
        $key = "legacy_route_usage:{$legacyPath}";
        $totalUsage = Cache::get($key, 0);
        
        // Get daily usage for the last 7 days
        $dailyUsage = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dailyKey = "legacy_route_daily:{$date}:{$legacyPath}";
            $dailyUsage[$date] = Cache::get($dailyKey, 0);
        }
        
        return [
            'legacy_path' => $legacyPath,
            'total_usage' => $totalUsage,
            'daily_usage' => $dailyUsage,
            'last_7_days_total' => array_sum($dailyUsage),
            'average_daily' => array_sum($dailyUsage) / 7,
            'trend' => $this->calculateTrend($dailyUsage)
        ];
    }

    /**
     * Get all legacy routes usage statistics
     */
    public function getAllUsageStats(): array
    {
        $legacyRoutes = ['/dashboard', '/projects', '/tasks'];
        $stats = [];
        
        foreach ($legacyRoutes as $route) {
            $stats[$route] = $this->getUsageStats($route);
        }
        
        return [
            'routes' => $stats,
            'summary' => $this->getSummaryStats($stats),
            'generated_at' => now()->toISOString()
        ];
    }

    /**
     * Get summary statistics
     */
    private function getSummaryStats(array $stats): array
    {
        $totalUsage = 0;
        $totalLast7Days = 0;
        $highestUsageRoute = null;
        $highestUsage = 0;
        
        foreach ($stats as $route => $data) {
            $totalUsage += $data['total_usage'];
            $totalLast7Days += $data['last_7_days_total'];
            
            if ($data['total_usage'] > $highestUsage) {
                $highestUsage = $data['total_usage'];
                $highestUsageRoute = $route;
            }
        }
        
        return [
            'total_legacy_usage' => $totalUsage,
            'last_7_days_total' => $totalLast7Days,
            'average_daily_total' => $totalLast7Days / 7,
            'highest_usage_route' => $highestUsageRoute,
            'highest_usage_count' => $highestUsage,
            'active_routes' => count(array_filter($stats, fn($s) => $s['last_7_days_total'] > 0))
        ];
    }

    /**
     * Calculate usage trend
     */
    private function calculateTrend(array $dailyUsage): string
    {
        $values = array_values($dailyUsage);
        $count = count($values);
        
        if ($count < 2) {
            return 'insufficient_data';
        }
        
        $firstHalf = array_slice($values, 0, intval($count / 2));
        $secondHalf = array_slice($values, intval($count / 2));
        
        $firstAverage = array_sum($firstHalf) / count($firstHalf);
        $secondAverage = array_sum($secondHalf) / count($secondHalf);
        
        if ($secondAverage > $firstAverage * 1.1) {
            return 'increasing';
        } elseif ($secondAverage < $firstAverage * 0.9) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    /**
     * Get migration phase statistics
     */
    public function getMigrationPhaseStats(): array
    {
        $currentDate = now()->format('Y-m-d');
        $legacyRoutes = [
            '/dashboard' => [
                'announce_date' => '2024-12-20',
                'redirect_date' => '2024-12-27',
                'remove_date' => '2025-01-10'
            ],
            '/projects' => [
                'announce_date' => '2024-12-20',
                'redirect_date' => '2024-12-27',
                'remove_date' => '2025-01-10'
            ],
            '/tasks' => [
                'announce_date' => '2024-12-20',
                'redirect_date' => '2024-12-27',
                'remove_date' => '2025-01-10'
            ]
        ];
        
        $phaseStats = [
            'announce' => 0,
            'redirect' => 0,
            'remove' => 0
        ];
        
        foreach ($legacyRoutes as $route => $dates) {
            $phase = $this->getCurrentPhase($dates, $currentDate);
            $phaseStats[$phase]++;
        }
        
        return [
            'current_date' => $currentDate,
            'phase_distribution' => $phaseStats,
            'total_routes' => count($legacyRoutes),
            'migration_progress' => [
                'completed_announce' => $phaseStats['redirect'] + $phaseStats['remove'],
                'completed_redirect' => $phaseStats['remove'],
                'completion_percentage' => ($phaseStats['remove'] / count($legacyRoutes)) * 100
            ]
        ];
    }

    /**
     * Get current migration phase for a route
     */
    private function getCurrentPhase(array $dates, string $currentDate): string
    {
        if ($currentDate < $dates['redirect_date']) {
            return 'announce';
        } elseif ($currentDate < $dates['remove_date']) {
            return 'redirect';
        } else {
            return 'remove';
        }
    }

    /**
     * Generate usage report
     */
    public function generateUsageReport(): array
    {
        $usageStats = $this->getAllUsageStats();
        $phaseStats = $this->getMigrationPhaseStats();
        
        return [
            'report_type' => 'legacy_route_usage',
            'generated_at' => now()->toISOString(),
            'usage_statistics' => $usageStats,
            'migration_phase_statistics' => $phaseStats,
            'recommendations' => $this->generateRecommendations($usageStats, $phaseStats)
        ];
    }

    /**
     * Generate recommendations based on usage data
     */
    private function generateRecommendations(array $usageStats, array $phaseStats): array
    {
        $recommendations = [];
        
        // Check for high usage routes
        foreach ($usageStats['routes'] as $route => $stats) {
            if ($stats['last_7_days_total'] > 100) {
                $recommendations[] = [
                    'type' => 'high_usage_warning',
                    'route' => $route,
                    'message' => "Route {$route} has high usage ({$stats['last_7_days_total']} requests in last 7 days). Consider extending migration timeline.",
                    'priority' => 'high'
                ];
            }
            
            if ($stats['trend'] === 'increasing') {
                $recommendations[] = [
                    'type' => 'increasing_usage_warning',
                    'route' => $route,
                    'message' => "Route {$route} usage is increasing. Investigate and communicate migration more effectively.",
                    'priority' => 'medium'
                ];
            }
        }
        
        // Check migration progress
        if ($phaseStats['migration_progress']['completion_percentage'] < 50) {
            $recommendations[] = [
                'type' => 'slow_migration_progress',
                'message' => "Migration progress is slow ({$phaseStats['migration_progress']['completion_percentage']}% completed). Consider additional communication or extended timeline.",
                'priority' => 'medium'
            ];
        }
        
        return $recommendations;
    }

    /**
     * Clear old usage data
     */
    public function clearOldData(int $daysToKeep = 30): int
    {
        $cleared = 0;
        $cutoffDate = now()->subDays($daysToKeep);
        
        // Clear old daily usage data
        for ($i = $daysToKeep + 1; $i <= 365; $i++) {
            $date = now()->subDays($i)->format('Y-m-d');
            $legacyRoutes = ['/dashboard', '/projects', '/tasks'];
            
            foreach ($legacyRoutes as $route) {
                $key = "legacy_route_daily:{$date}:{$route}";
                if (Cache::forget($key)) {
                    $cleared++;
                }
            }
        }
        
        Log::info('Legacy route monitoring data cleaned', [
            'cleared_entries' => $cleared,
            'cutoff_date' => $cutoffDate->format('Y-m-d'),
            'days_kept' => $daysToKeep
        ]);
        
        return $cleared;
    }
}
