<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DashboardMetric;
use App\Models\DashboardMetricValue;
use App\Models\Dashboard;
use App\Models\Widget;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardAnalyticsController extends Controller
{
    /**
     * Get dashboard analytics data
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $projectId = $request->get('project_id');
            $dateRange = $request->get('date_range', '30d');

            // Calculate date range
            $endDate = now();
            $startDate = match($dateRange) {
                '7d' => $endDate->copy()->subDays(7),
                '30d' => $endDate->copy()->subDays(30),
                '90d' => $endDate->copy()->subDays(90),
                '1y' => $endDate->copy()->subYear(),
                default => $endDate->copy()->subDays(30)
            };

            // Get metrics data
            $metrics = DashboardMetric::where('tenant_id', $user->tenant_id)
                ->when($projectId, function ($query) use ($projectId) {
                    return $query->where('project_id', $projectId);
                })
                ->active()
                ->get();

            $analyticsData = [
                'summary' => $this->getSummaryMetrics($user->tenant_id, $projectId),
                'charts' => $this->getChartsData($user->tenant_id, $projectId, $startDate, $endDate),
                'activity' => $this->getActivityData($user->tenant_id, $projectId, $startDate, $endDate),
                'performance' => $this->getPerformanceMetrics($user->tenant_id, $projectId),
                'date_range' => [
                    'start' => $startDate->toISOString(),
                    'end' => $endDate->toISOString()
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $analyticsData
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard analytics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch analytics data']
            ], 500);
        }
    }

    /**
     * Get dashboard metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $projectId = $request->get('project_id');
            $category = $request->get('category');

            $metrics = DashboardMetric::where('tenant_id', $user->tenant_id)
                ->when($projectId, function ($query) use ($projectId) {
                    return $query->where('project_id', $projectId);
                })
                ->when($category, function ($query) use ($category) {
                    return $query->where('category', $category);
                })
                ->active()
                ->with(['values' => function ($query) {
                    $query->orderBy('recorded_at', 'desc')->limit(10);
                }])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard metrics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch metrics']
            ], 500);
        }
    }

    /**
     * Get summary metrics
     */
    private function getSummaryMetrics(string $tenantId, ?string $projectId): array
    {
        // Mock data for now - can be replaced with real calculations
        return [
            'total_projects' => 12,
            'active_projects' => 8,
            'completed_projects' => 3,
            'overdue_projects' => 1,
            'total_tasks' => 156,
            'completed_tasks' => 98,
            'pending_tasks' => 45,
            'overdue_tasks' => 13,
            'team_members' => 24,
            'active_members' => 22,
            'budget_utilization' => 78.5,
            'schedule_performance' => 85.2
        ];
    }

    /**
     * Get charts data
     */
    private function getChartsData(string $tenantId, ?string $projectId, $startDate, $endDate): array
    {
        // Mock data for charts
        return [
            'project_status_distribution' => [
                ['status' => 'active', 'count' => 8],
                ['status' => 'completed', 'count' => 3],
                ['status' => 'on_hold', 'count' => 1]
            ],
            'task_completion_trend' => [
                ['date' => $startDate->format('Y-m-d'), 'completed' => 5, 'created' => 8],
                ['date' => $startDate->addDay()->format('Y-m-d'), 'completed' => 7, 'created' => 6],
                ['date' => $startDate->addDay()->format('Y-m-d'), 'completed' => 4, 'created' => 9]
            ],
            'budget_vs_actual' => [
                ['project' => 'Project A', 'budgeted' => 100000, 'actual' => 85000],
                ['project' => 'Project B', 'budgeted' => 150000, 'actual' => 142000],
                ['project' => 'Project C', 'budgeted' => 80000, 'actual' => 92000]
            ]
        ];
    }

    /**
     * Get activity data
     */
    private function getActivityData(string $tenantId, ?string $projectId, $startDate, $endDate): array
    {
        // Mock activity data
        return [
            'recent_activities' => [
                [
                    'id' => 1,
                    'type' => 'task_completed',
                    'description' => 'Task "Design Review" completed',
                    'user' => 'John Doe',
                    'timestamp' => now()->subHours(2)->toISOString()
                ],
                [
                    'id' => 2,
                    'type' => 'project_updated',
                    'description' => 'Project "Website Redesign" updated',
                    'user' => 'Jane Smith',
                    'timestamp' => now()->subHours(4)->toISOString()
                ]
            ],
            'cursor' => 'eyJpZCI6MiwidGltZXN0YW1wIjoiMjAyNS0xMC0xNFQxMDowMDowMFoifQ=='
        ];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(string $tenantId, ?string $projectId): array
    {
        return [
            'page_load_time' => 245, // ms
            'api_response_time' => 89, // ms
            'database_query_time' => 12, // ms
            'memory_usage' => 45.2, // MB
            'cpu_usage' => 23.1 // %
        ];
    }
}