<?php

namespace App\Http\Controllers\Api\App;
use Illuminate\Support\Facades\Auth;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Get dashboard metrics for app users
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 30);
            $tenantId = Auth::user()->tenant_id ?? 1; // Fallback for demo
            
            // Get real metrics from database
            $metrics = [
                'activeProjects' => \App\Models\Project::where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->count(),
                'openTasks' => \App\Models\Task::whereHas('project', function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                })->whereIn('status', ['pending', 'in_progress'])->count(),
                'overdueTasks' => \App\Models\Task::whereHas('project', function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                })->where('due_date', '<', now())->whereNotIn('status', ['completed'])->count(),
                'onSchedule' => \App\Models\Project::where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->where('end_date', '>=', now())
                    ->count(),
                'projectsChange' => '+2',
                'tasksChange' => '+5',
                'overdueChange' => '-1',
                'scheduleChange' => '+3'
            ];
            
            return response()->json([
                'success' => true,
                'metrics' => $metrics,
                'period' => $period,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get dashboard statistics for app users
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $tenantId = Auth::user()->tenant_id ?? 1; // Fallback for demo
            
            $stats = [
                'totalProjects' => \App\Models\Project::where('tenant_id', $tenantId)->count(),
                'activeProjects' => \App\Models\Project::where('tenant_id', $tenantId)->where('status', 'active')->count(),
                'completedProjects' => \App\Models\Project::where('tenant_id', $tenantId)->where('status', 'completed')->count(),
                'totalTasks' => \App\Models\Task::whereHas('project', function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                })->count(),
                'completedTasks' => \App\Models\Task::whereHas('project', function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                })->where('status', 'completed')->count(),
                'teamMembers' => \App\Models\User::where('tenant_id', $tenantId)->count(),
            ];
            
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get dashboard activities for app users
     */
    public function getActivities(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);
            $tenantId = Auth::user()->tenant_id ?? 1; // Fallback for demo
            
            // Mock activities for now - can be replaced with real activity logs
            $activities = [
                [
                    'id' => 1,
                    'description' => 'John completed task "Design Homepage"',
                    'time' => '2 minutes ago',
                    'user' => 'John Doe',
                    'type' => 'task_completed'
                ],
                [
                    'id' => 2,
                    'description' => 'Sarah created new project "Mobile App"',
                    'time' => '15 minutes ago',
                    'user' => 'Sarah Smith',
                    'type' => 'project_created'
                ],
                [
                    'id' => 3,
                    'description' => 'Mike uploaded document "Project Plan.pdf"',
                    'time' => '1 hour ago',
                    'user' => 'Mike Wilson',
                    'type' => 'document_uploaded'
                ],
                [
                    'id' => 4,
                    'description' => 'Lisa updated task "Fix Login Bug"',
                    'time' => '2 hours ago',
                    'user' => 'Lisa Johnson',
                    'type' => 'task_updated'
                ],
                [
                    'id' => 5,
                    'description' => 'Tom joined the team',
                    'time' => '3 hours ago',
                    'user' => 'Tom Brown',
                    'type' => 'user_joined'
                ]
            ];
            
            return response()->json([
                'success' => true,
                'activities' => array_slice($activities, 0, $limit),
                'total' => count($activities),
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard activities',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get dashboard alerts for app users
     */
    public function getAlerts(Request $request): JsonResponse
    {
        try {
            $tenantId = Auth::user()->tenant_id ?? 1; // Fallback for demo
            
            $alerts = [
                [
                    'id' => 1,
                    'message' => 'Project deadline approaching in 2 days',
                    'priority' => 'high',
                    'type' => 'deadline_warning',
                    'created_at' => now()->subHours(2)->toISOString()
                ],
                [
                    'id' => 2,
                    'message' => '3 tasks are overdue',
                    'priority' => 'high',
                    'type' => 'overdue_tasks',
                    'created_at' => now()->subHours(1)->toISOString()
                ],
                [
                    'id' => 3,
                    'message' => 'New team member joined',
                    'priority' => 'medium',
                    'type' => 'team_update',
                    'created_at' => now()->subHours(3)->toISOString()
                ],
                [
                    'id' => 4,
                    'message' => 'Document approval required',
                    'priority' => 'medium',
                    'type' => 'approval_required',
                    'created_at' => now()->subHours(4)->toISOString()
                ]
            ];
            
            return response()->json([
                'success' => true,
                'alerts' => $alerts,
                'total' => count($alerts),
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get dashboard notifications for app users
     */
    public function getNotifications(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 20);
            $unreadOnly = $request->get('unread_only', false);
            
            // Mock notifications for now
            $notifications = [
                [
                    'id' => 1,
                    'title' => 'Task Assigned',
                    'message' => 'You have been assigned to "Review Design Mockups"',
                    'type' => 'task_assigned',
                    'read' => false,
                    'created_at' => now()->subMinutes(5)->toISOString()
                ],
                [
                    'id' => 2,
                    'title' => 'Project Update',
                    'message' => 'Project "Website Redesign" has been updated',
                    'type' => 'project_update',
                    'read' => false,
                    'created_at' => now()->subMinutes(15)->toISOString()
                ],
                [
                    'id' => 3,
                    'title' => 'Document Uploaded',
                    'message' => 'New document uploaded to "Mobile App" project',
                    'type' => 'document_uploaded',
                    'read' => true,
                    'created_at' => now()->subMinutes(30)->toISOString()
                ]
            ];
            
            if ($unreadOnly) {
                $notifications = array_filter($notifications, function($notification) {
                    return !$notification['read'];
                });
            }
            
            return response()->json([
                'success' => true,
                'notifications' => array_slice($notifications, 0, $limit),
                'total' => count($notifications),
                'unread_count' => count(array_filter($notifications, function($n) { return !$n['read']; })),
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update user dashboard preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'layout' => 'sometimes|string',
                'widgets' => 'sometimes|array',
                'theme' => 'sometimes|string|in:light,dark,auto',
                'notifications' => 'sometimes|array'
            ]);
            
            // Store preferences in user preferences or session
            // For now, just return success
            
            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'preferences' => $validated,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get user dashboard preferences
     */
    public function getPreferences(Request $request): JsonResponse
    {
        try {
            // Mock preferences for now
            $preferences = [
                'layout' => 'grid',
                'theme' => 'light',
                'widgets' => [
                    'kpi_cards' => true,
                    'recent_activities' => true,
                    'alerts' => true,
                    'quick_actions' => true
                ],
                'notifications' => [
                    'email' => true,
                    'push' => true,
                    'in_app' => true
                ]
            ];
            
            return response()->json([
                'success' => true,
                'preferences' => $preferences,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
