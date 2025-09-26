<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
