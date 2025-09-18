<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ZenaProject;
use App\Models\ZenaTask;
use App\Models\ZenaRfi;
use App\Models\ZenaNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ZenaDashboardController extends Controller
{
    /**
     * Get main dashboard overview with role-based data.
     */
    public function getDashboard(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $roles = $user->roles()->pluck('name')->toArray();
        $projectId = $request->input('project_id');

        $dashboardData = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
            ],
            'current_project' => $projectId ? ZenaProject::find($projectId) : null,
            'summary' => $this->getRoleBasedSummary($user, $roles, $projectId),
            'recent_activities' => $this->getRecentActivities($projectId),
            'upcoming_tasks' => $this->getUpcomingTasks($projectId),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $dashboardData,
        ]);
    }

    /**
     * Get dashboard widgets for the current user.
     */
    public function getWidgets(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $roles = $user->roles()->pluck('name')->toArray();
        $projectId = $request->input('project_id');

        $widgets = [
            [
                'id' => 'recent_activities',
                'title' => 'Recent Activities',
                'type' => 'activity_feed',
                'size' => 'large',
                'data' => $this->getRecentActivities($projectId),
            ],
            [
                'id' => 'upcoming_tasks',
                'title' => 'Upcoming Tasks',
                'type' => 'task_list',
                'size' => 'medium',
                'data' => $this->getUpcomingTasks($projectId),
            ],
        ];

        return response()->json([
            'status' => 'success',
            'data' => $widgets,
        ]);
    }

    /**
     * Get dashboard metrics/KPIs.
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $roles = $user->roles()->pluck('name')->toArray();
        $projectId = $request->input('project_id');

        $metrics = $this->getRoleBasedMetrics($user, $roles, $projectId);

        return response()->json([
            'status' => 'success',
            'data' => $metrics,
        ]);
    }

    /**
     * Get dashboard alerts and notifications.
     */
    public function getAlerts(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        $limit = $request->input('limit', 10);

        $alerts = ZenaNotification::where('user_id', $user->id)
            ->when($projectId, function ($query) use ($projectId) {
                return $query->where('project_id', $projectId);
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $alerts,
        ]);
    }

    /**
     * Get available projects for the user.
     */
    public function getAvailableProjects(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $roles = $user->roles()->pluck('name')->toArray();
        
        $query = ZenaProject::query();

        // SuperAdmin and Admin can see all projects
        if (!in_array('SuperAdmin', $roles) && !in_array('Admin', $roles)) {
            $query->whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $projects = $query->select(['id', 'name', 'status', 'start_date', 'end_date', 'budget'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                    'budget' => $project->budget,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $projects,
        ]);
    }

    /**
     * Switch project context for the user.
     */
    public function switchProjectContext(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        
        // Validate project access
        $project = ZenaProject::where('id', $projectId)
            ->whereHas('users', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$project) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found or access denied',
            ], 404);
        }

        // Update user's current project preference
        $preferences = $user->preferences ?? [];
        $preferences['current_project_id'] = $projectId;
        $user->update(['preferences' => $preferences]);

        return response()->json([
            'status' => 'success',
            'message' => 'Project context switched successfully',
            'data' => [
                'project' => $project,
            ],
        ]);
    }

    /**
     * Get role-based summary data.
     */
    private function getRoleBasedSummary($user, array $roles, ?string $projectId = null): array
    {
        if (in_array('SuperAdmin', $roles) || in_array('Admin', $roles)) {
            return [
                'total_projects' => ZenaProject::count(),
                'active_projects' => ZenaProject::where('status', 'active')->count(),
                'total_users' => \App\Models\User::count(),
                'total_tasks' => ZenaTask::count(),
                'pending_rfis' => ZenaRfi::where('status', 'pending')->count(),
            ];
        } elseif (in_array('PM', $roles)) {
            $projects = ZenaProject::whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->get();

            return [
                'managed_projects' => $projects->count(),
                'total_tasks' => ZenaTask::whereIn('project_id', $projects->pluck('id'))->count(),
                'completed_tasks' => ZenaTask::whereIn('project_id', $projects->pluck('id'))
                    ->where('status', 'completed')->count(),
                'pending_rfis' => ZenaRfi::whereIn('project_id', $projects->pluck('id'))
                    ->where('status', 'pending')->count(),
            ];
        }

        return [
            'assigned_projects' => ZenaProject::whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->count(),
        ];
    }

    /**
     * Get role-based metrics.
     */
    private function getRoleBasedMetrics($user, array $roles, ?string $projectId = null): array
    {
        if (in_array('SuperAdmin', $roles) || in_array('Admin', $roles)) {
            return [
                'total_projects' => ZenaProject::count(),
                'active_projects' => ZenaProject::where('status', 'active')->count(),
                'total_users' => \App\Models\User::count(),
                'total_tasks' => ZenaTask::count(),
                'completion_rate' => $this->calculateCompletionRate(),
            ];
        } elseif (in_array('PM', $roles)) {
            $projects = ZenaProject::whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->get();

            return [
                'managed_projects' => $projects->count(),
                'total_tasks' => ZenaTask::whereIn('project_id', $projects->pluck('id'))->count(),
                'completed_tasks' => ZenaTask::whereIn('project_id', $projects->pluck('id'))
                    ->where('status', 'completed')->count(),
                'overdue_tasks' => ZenaTask::whereIn('project_id', $projects->pluck('id'))
                    ->where('due_date', '<', now())
                    ->where('status', '!=', 'completed')->count(),
            ];
        }

        return [];
    }

    /**
     * Get recent activities.
     */
    private function getRecentActivities(?string $projectId = null): array
    {
        // Sample data - in real implementation, this would come from activity log
        return [
            [
                'id' => '1',
                'type' => 'task_completed',
                'description' => 'Task "Foundation Design" completed',
                'user' => 'John Doe',
                'timestamp' => now()->subHours(2),
            ],
            [
                'id' => '2',
                'type' => 'rfi_created',
                'description' => 'New RFI created for Project Alpha',
                'user' => 'Jane Smith',
                'timestamp' => now()->subHours(4),
            ],
        ];
    }

    /**
     * Get upcoming tasks.
     */
    private function getUpcomingTasks(?string $projectId = null): array
    {
        $query = ZenaTask::where('due_date', '>=', now())
            ->where('status', '!=', 'completed')
            ->orderBy('due_date', 'asc');

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return $query->limit(5)->get()->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'due_date' => $task->due_date,
                'priority' => $task->priority,
                'status' => $task->status,
            ];
        })->toArray();
    }

    /**
     * Calculate overall completion rate.
     */
    private function calculateCompletionRate(): float
    {
        $totalTasks = ZenaTask::count();
        if ($totalTasks === 0) {
            return 0.0;
        }

        $completedTasks = ZenaTask::where('status', 'completed')->count();
        return round(($completedTasks / $totalTasks) * 100, 2);
    }
}