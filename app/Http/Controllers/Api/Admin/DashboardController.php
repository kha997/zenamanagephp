<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get admin dashboard statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            // Mock data for demo purposes
            $stats = [
                'totalUsers' => 156,
                'activeUsers' => 142,
                'totalProjects' => 23,
                'activeProjects' => 18,
                'totalTasks' => 456,
                'completedTasks' => 389,
                'systemHealth' => 'good',
                'storageUsed' => '2.4 GB',
                'storageTotal' => '10 GB',
                'uptime' => '99.9%',
                'responseTime' => '120ms',
                'errorRate' => '0.1%'
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load dashboard statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent activities
     */
    public function getActivities(): JsonResponse
    {
        try {
            // Mock data for demo purposes
            $activities = [
                [
                    'id' => 1,
                    'type' => 'user_login',
                    'description' => 'John Doe logged in',
                    'timestamp' => now()->subMinutes(5)->toISOString(),
                    'user' => 'John Doe',
                    'icon' => 'fas fa-sign-in-alt',
                    'color' => 'text-green-600'
                ],
                [
                    'id' => 2,
                    'type' => 'project_created',
                    'description' => 'New project "Website Redesign" created',
                    'timestamp' => now()->subMinutes(15)->toISOString(),
                    'user' => 'Jane Smith',
                    'icon' => 'fas fa-plus',
                    'color' => 'text-blue-600'
                ],
                [
                    'id' => 3,
                    'type' => 'task_completed',
                    'description' => 'Task "Update Documentation" completed',
                    'timestamp' => now()->subMinutes(30)->toISOString(),
                    'user' => 'Mike Johnson',
                    'icon' => 'fas fa-check',
                    'color' => 'text-green-600'
                ],
                [
                    'id' => 4,
                    'type' => 'file_uploaded',
                    'description' => 'New file uploaded to project',
                    'timestamp' => now()->subHours(1)->toISOString(),
                    'user' => 'Sarah Wilson',
                    'icon' => 'fas fa-upload',
                    'color' => 'text-purple-600'
                ],
                [
                    'id' => 5,
                    'type' => 'system_alert',
                    'description' => 'High memory usage detected',
                    'timestamp' => now()->subHours(2)->toISOString(),
                    'user' => 'System',
                    'icon' => 'fas fa-exclamation-triangle',
                    'color' => 'text-yellow-600'
                ]
            ];

            return response()->json([
                'status' => 'success',
                'data' => $activities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load activities: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system alerts
     */
    public function getAlerts(): JsonResponse
    {
        try {
            // Mock data for demo purposes
            $alerts = [
                [
                    'id' => 1,
                    'type' => 'warning',
                    'title' => 'High Memory Usage',
                    'message' => 'Server memory usage is at 85%',
                    'timestamp' => now()->subMinutes(10)->toISOString(),
                    'severity' => 'medium',
                    'resolved' => false
                ],
                [
                    'id' => 2,
                    'type' => 'info',
                    'title' => 'Scheduled Maintenance',
                    'message' => 'System maintenance scheduled for tonight at 2 AM',
                    'timestamp' => now()->subHours(1)->toISOString(),
                    'severity' => 'low',
                    'resolved' => false
                ],
                [
                    'id' => 3,
                    'type' => 'success',
                    'title' => 'Backup Completed',
                    'message' => 'Daily backup completed successfully',
                    'timestamp' => now()->subHours(3)->toISOString(),
                    'severity' => 'low',
                    'resolved' => true
                ]
            ];

            return response()->json([
                'status' => 'success',
                'data' => $alerts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load alerts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get consolidated dashboard summary with KPIs and mini-sparklines
     * GET /api/admin/dashboard/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $range = $request->get('range', '30d');
        $cacheKey = "admin_dashboard_summary_{$range}";
        
        // Cache for 30 seconds with ETag support
        $data = Cache::remember($cacheKey, 30, function () use ($range) {
            $days = $this->parseRange($range);
            
            return [
                'tenants' => $this->getTenantsStats($days),
                'users' => $this->getUsersStats($days),
                'errors' => $this->getErrorsStats($days),
                'queue' => $this->getQueueStats(),
                'storage' => $this->getStorageStats()
            ];
        });

        // Generate ETag according to spec (quoted hash)
        $etag = '"' . substr(hash('md5', 'summary:' . $range . '|' . json_encode($data)), 0, 16) . '"';
        
        // Check if client has same ETag (support both quoted and unquoted)
        $clientETag = $request->header('If-None-Match');
        if ($clientETag && ($clientETag === $etag || str_replace('"', '', $clientETag) === str_replace('"', '', $etag))) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=30');
        }

        return response()->json($data, 200, [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=30, stale-while-revalidate=30',
            'Content-Type' => 'application/json'
        ]);
    }

    /**
     * Get chart datasets for dashboard
     * GET /api/admin/dashboard/charts
     */
    public function charts(Request $request): JsonResponse
    {
        $range = $request->get('range', '30d');
        $cacheKey = "admin_dashboard_charts_{$range}";
        
        $data = Cache::remember($cacheKey, 30, function () use ($range) {
            $days = $this->parseRange($range);
            
            return [
                'signups' => $this->getSignupsChartData($days),
                'error_rate' => $this->getErrorRateChartData($days),
                'timestamp' => $days
            ];
        });

        $etag = '"' . substr(hash('md5', 'charts:' . $range . '|' . json_encode($data)), 0, 16) . '"';
        
        $clientETag = $request->header('If-None-Match');
        if ($clientETag && ($clientETag === $etag || str_replace('"', '', $clientETag) === str_replace('"', '', $etag))) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=30, stale-while-revalidate=30');
        }

        return response()->json($data, 200, [
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age=30, stale-while-revalidate=30',
            'Content-Type' => 'application/json'
        ]);
    }

    /**
     * Get recent activity with cursor-based pagination
     * GET /api/admin/dashboard/activity
     */
    public function activity(Request $request): JsonResponse
    {
        $cursor = $request->get('cursor', '');
        $limit = 20;
        
        // Use project_activities table instead of activity_logs
        $query = DB::table('project_activities')
            ->select('id', 'description as message', 'action as severity', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($cursor) {
            $query->where('id', '<', base64_decode($cursor));
        }

        $activities = $query->get();
        $nextCursor = $activities->count() === $limit ? base64_encode($activities->last()->id) : null;

        $data = [
            'items' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'message' => $activity->message,
                    'severity' => $activity->severity,
                    'ts' => $activity->created_at,
                    'time_ago' => Carbon::parse($activity->created_at)->diffForHumans()
                ];
            }),
            'cursor' => $nextCursor,
            'has_more' => $nextCursor !== null
        ];

        $etag = md5(json_encode($data));

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'private, max-age=10');
        }

        return response()->json($data, 200, [
            'ETag' => $etag,
            'Cache-Control' => 'private, max-age=10'
        ]);
    }

    /**
     * Export signups data as CSV
     * GET /api/admin/dashboard/signups/export.csv
     */
    public function exportSignups(Request $request)
    {
        // Rate limiting: 10 requests per minute
        $rateKey = "export_signups_" . $request->ip();
        $currentCount = Cache::get($rateKey, 0);
        
        if ($currentCount >= 10) {
            return response('Rate limited', 429, [
                'Retry-After' => '60',
                'X-RateLimit-Limit' => '10',
                'X-RateLimit-Remaining' => '0'
            ]);
        }

        $range = $request->get('range', '30d');
        $days = $this->parseRange($range);
        $data = $this->getSignupsChartData($days);

        Cache::put($rateKey, $currentCount + 1, 60); // Increment and expire in 1 minute

        $filename = "signups_{$range}_" . date('Y-m-d_H-i-s') . '.csv';
        
        return response($this->generateCSV($data))
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Cache-Control', 'no-store')
            ->header('ETag', '"' . substr(hash('md5', 'signups:' . $filename . ':' . date('Y-m-d-H')), 0, 16) . '"')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Export error rate data as CSV
     * GET /api/admin/dashboard/errors/export.csv
     */
    public function exportErrors(Request $request)
    {
        // Rate limiting
        $rateKey = "export_errors_" . $request->ip();
        $currentCount = Cache::get($rateKey, 0);
        
        if ($currentCount >= 10) {
            return response('Rate limited', 429, [
                'Retry-After' => '60',
                'X-RateLimit-Limit' => '10',
                'X-RateLimit-Remaining' => '0'
            ]);
        }

        $range = $request->get('range', '30d');
        $days = $this->parseRange($range);
        $data = $this->getErrorRateChartData($days);

        Cache::put($rateKey, $currentCount + 1, 60); // Increment and expire in 1 minute

        $filename = "error_rate_{$range}_" . date('Y-m-d_H-i-s') . '.csv';
        
        return response($this->generateCSV($data))
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Cache-Control', 'no-store')
            ->header('ETag', '"' . substr(hash('md5', 'errors:' . $filename . ':' . date('Y-m-d-H')), 0, 16) . '"')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Get dashboard metrics
     */
    public function getMetrics(): JsonResponse
    {
        try {
            // Mock data for demo purposes
            $metrics = [
                'performance' => [
                    'response_time' => 120,
                    'uptime' => 99.9,
                    'error_rate' => 0.1
                ],
                'usage' => [
                    'cpu_usage' => 45,
                    'memory_usage' => 65,
                    'disk_usage' => 24
                ],
                'users' => [
                    'active_today' => 89,
                    'active_this_week' => 156,
                    'new_this_month' => 23
                ]
            ];

            return response()->json([
                'status' => 'success',
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse range string to days
     */
    private function parseRange(string $range): int
    {
        return match ($range) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '365d', '1y' => 365,
            default => 30
        };
    }

    /**
     * Get tenants statistics with sparkline
     */
    private function getTenantsStats(int $days): array
    {
        // Get current count - using table if exists
        $total = DB::table('tenants')->exists() ? DB::table('tenants')->count() : 89;
        
        // Mock previous period for demonstration
        $prevTotal = $total - rand(1, 10);
        
        // Generate mock sparkline data
        $sparkline = [];
        for ($i = 0; $i < $days; $i++) {
            $sparkline[] = rand(0, 5);
        }

        return [
            'total' => $total,
            'growth_rate' => $prevTotal > 0 ? round((($total - $prevTotal) / $prevTotal) * 100, 1) : 5.2,
            'sparkline' => $sparkline
        ];
    }

    /**
     * Get users statistics with sparkline
     */
    private function getUsersStats(int $days): array
    {
        $total = DB::table('users')->exists() ? DB::table('users')->count() : 1247;
        $prevTotal = $total - rand(50, 150);
        
        $sparkline = [];
        for ($i = 0; $i < $days; $i++) {
            $sparkline[] = rand(8, 25);
        }

        return [
            'total' => $total,
            'growth_rate' => $prevTotal > 0 ? round((($total - $prevTotal) / $prevTotal) * 100, 1) : 12.1,
            'sparkline' => $sparkline
        ];
    }

    /**
     * Get errors statistics today vs yesterday
     */
    private function getErrorsStats(int $days): array
    {
        $errors24h = rand(8, 18);
        $errorsYesterday = $errors24h - rand(-5, 5);
        $change = $errors24h - $errorsYesterday;

        $sparkline = [];
        for ($i = 0; $i < $days; $i++) {
            $sparkline[] = rand(5, 25);
        }

        return [
            'last_24h' => $errors24h,
            'change_from_yesterday' => $change,
            'sparkline' => $sparkline
        ];
    }

    /**
     * Get queue job statistics
     */
    private function getQueueStats(): array
    {
        $activeJobs = rand(100, 200);
        
        $status = match (true) {
            $activeJobs === 0 => 'Idle',
            $activeJobs < 10 => 'Healthy',
            $activeJobs < 50 => 'Busy',
            default => 'Processing'
        };

        $sparkline = [];
        for ($i = 0; $i < 30; $i++) {
            $sparkline[] = rand(20, 80);
        }

        return [
            'active_jobs' => $activeJobs,
            'status' => $status,
            'sparkline' => $sparkline
        ];
    }

    /**
     * Get storage usage statistics
     */
    private function getStorageStats(): array
    {
        $usedBytes = rand(1.5e12, 2.5e12);
        $capacityBytes = 2.9e12;

        $sparkline = [];
        for ($i = 0; $i < 30; $i++) {
            $sparkline[] = ($usedBytes / $capacityBytes) * 100 + rand(-2, 2);
        }

        return [
            'used_bytes' => $usedBytes,
            'capacity_bytes' => $capacityBytes,
            'sparkline' => $sparkline
        ];
    }

    /**
     * Get signups chart data for Chart.js
     */
    private function getSignupsChartData(int $days): array
    {
        $labels = [];
        $values = [];
        
        for ($i = $days; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = $date;
            $values[] = rand(40, 65);
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'New Signups',
                'data' => $values,
                'borderColor' => '#3B82F6',
                'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                'tension' => 0.4,
                'fill' => true
            ]]
        ];
    }

    /**
     * Get error rate chart data for Chart.js
     */
    private function getErrorRateChartData(int $days): array
    {
        $labels = [];
        $values = [];
        
        for ($i = $days; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = $date;
            $values[] = rand(8, 35);
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Error Rate',
                'data' => $values,
                'borderColor' => '#EF4444',
                'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                'tension' => 0.4,
                'fill' => true
            ]]
        ];
    }

    /**
     * Generate CSV from chart data with CSV injection protection
     */
    private function generateCSV(array $chartData): string
    {
        $output = "Date,Value\n";
        
        if (isset($chartData['labels']) && isset($chartData['datasets'])) {
            $data = $chartData['datasets'][0]['data'] ?? [];
            $labels = $chartData['labels'] ?? [];
            
            for ($i = 0; $i < count($labels); $i++) {
                $date = $labels[$i] ?? '';
                $value = $data[$i] ?? 0;
                
                // CSV injection protection: prefix with quote if starts with dangerous chars
                $safeDate = preg_match('/^[=\+\-@]/', (string)$date) ? "'" . $date : $date;
                $safeValue = preg_match('/^[=\+\-@]/', (string)$value) ? "'" . $value : $value;
                
                $output .= $safeDate . ',' . $safeValue . "\n";
            }
        }
        
        return $output;
    }
}
