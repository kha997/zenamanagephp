<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\Drawing;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Submittal;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignerDashboardController extends Controller
{
    use ZenaContractResponseTrait;

    /**
     * Get Designer dashboard overview.
     */
    public function getOverview(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        
        // Get Designer's projects
        $projects = Project::whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });

        if ($projectId) {
            $projects->where('id', $projectId);
        }

        $projects = $projects->get();

        $overview = [
            'projects' => $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'progress' => $project->progress_percentage ?? 0,
                    'design_tasks' => $project->tasks()->where('assigned_to', $user->id)->count(),
                ];
            }),
            'summary' => [
                'assigned_projects' => $projects->count(),
                'design_tasks' => Task::whereIn('project_id', $projects->pluck('id'))
                    ->where('assigned_to', $user->id)
                    ->where('type', 'design')->count(),
                'completed_designs' => Task::whereIn('project_id', $projects->pluck('id'))
                    ->where('assigned_to', $user->id)
                    ->where('type', 'design')
                    ->where('status', 'completed')->count(),
                'pending_rfis' => Rfi::whereIn('project_id', $projects->pluck('id'))
                    ->where('assigned_to', $user->id)
                    ->where('status', 'pending')->count(),
                'drawings_to_review' => Drawing::whereIn('project_id', $projects->pluck('id'))
                    ->where('status', 'pending_review')->count(),
            ],
            'recent_activities' => $this->getRecentActivities($projects->pluck('id')->toArray()),
            'upcoming_design_deadlines' => $this->getUpcomingDesignDeadlines($projects->pluck('id')->toArray(), $user->id),
        ];

        return $this->zenaSuccessResponse($overview);
    }

    /**
     * Get design tasks for the designer.
     */
    public function getDesignTasks(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        $status = $request->input('status');
        $priority = $request->input('priority');
        
        $query = Task::where('assigned_to', $user->id)
            ->where('type', 'design');

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        $tasks = $query->with(['project:id,name', 'assignedUser:id,name'])
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => $task->due_date,
                    'project' => $task->project,
                    'assigned_user' => $task->assignedUser,
                    'created_at' => $task->created_at,
                ];
            });

        return $this->zenaSuccessResponse($tasks);
    }

    /**
     * Get drawings status for the designer.
     */
    public function getDrawingsStatus(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        
        $query = Drawing::query();

        if ($projectId) {
            $query->where('project_id', $projectId);
        } else {
            // Get drawings from designer's projects
            $projectIds = Project::whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->pluck('id');
            
            $query->whereIn('project_id', $projectIds);
        }

        $drawings = $query->with(['project:id,name'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($drawing) {
                return [
                    'id' => $drawing->id,
                    'title' => $drawing->title,
                    'drawing_number' => $drawing->drawing_number,
                    'revision' => $drawing->revision,
                    'status' => $drawing->status,
                    'project' => $drawing->project,
                    'created_at' => $drawing->created_at,
                    'updated_at' => $drawing->updated_at,
                ];
            });

        $statusSummary = [
            'total_drawings' => $drawings->count(),
            'pending_review' => $drawings->where('status', 'pending_review')->count(),
            'approved' => $drawings->where('status', 'approved')->count(),
            'rejected' => $drawings->where('status', 'rejected')->count(),
            'draft' => $drawings->where('status', 'draft')->count(),
        ];

        return $this->zenaSuccessResponse([
            'drawings' => $drawings,
            'summary' => $statusSummary,
        ]);
    }

    /**
     * Get RFIs to answer for the designer.
     */
    public function getRfisToAnswer(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        $status = $request->input('status', 'pending');
        
        $query = Rfi::where('assigned_to', $user->id)
            ->where('status', $status);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $rfis = $query->with(['project:id,name', 'createdBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($rfi) {
                return [
                    'id' => $rfi->id,
                    'title' => $rfi->title,
                    'description' => $rfi->description,
                    'status' => $rfi->status,
                    'priority' => $rfi->priority,
                    'due_date' => $rfi->due_date,
                    'project' => $rfi->project,
                    'created_by' => $rfi->createdBy,
                    'created_at' => $rfi->created_at,
                ];
            });

        return $this->zenaSuccessResponse($rfis);
    }

    /**
     * Get submittals status for the designer.
     */
    public function getSubmittalsStatus(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        
        $query = Submittal::query();

        if ($projectId) {
            $query->where('project_id', $projectId);
        } else {
            // Get submittals from designer's projects
            $projectIds = Project::whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->pluck('id');
            
            $query->whereIn('project_id', $projectIds);
        }

        $submittals = $query->with(['project:id,name'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($submittal) {
                return [
                    'id' => $submittal->id,
                    'title' => $submittal->title,
                    'submittal_number' => $submittal->submittal_number,
                    'status' => $submittal->status,
                    'project' => $submittal->project,
                    'created_at' => $submittal->created_at,
                    'updated_at' => $submittal->updated_at,
                ];
            });

        $statusSummary = [
            'total_submittals' => $submittals->count(),
            'pending_review' => $submittals->where('status', 'pending_review')->count(),
            'approved' => $submittals->where('status', 'approved')->count(),
            'rejected' => $submittals->where('status', 'rejected')->count(),
            'draft' => $submittals->where('status', 'draft')->count(),
        ];

        return $this->zenaSuccessResponse([
            'submittals' => $submittals,
            'summary' => $statusSummary,
        ]);
    }

    /**
     * Get design workload for the designer.
     */
    public function getDesignWorkload(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        
        // Get designer's projects
        $projects = Project::whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });

        if ($projectId) {
            $projects->where('id', $projectId);
        }

        $projects = $projects->get();

        $workload = [
            'current_workload' => $this->calculateCurrentWorkload($projects->pluck('id')->toArray(), $user->id),
            'upcoming_deadlines' => $this->getUpcomingDeadlines($projects->pluck('id')->toArray(), $user->id),
            'workload_by_project' => $projects->map(function ($project) use ($user) {
                return [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'assigned_tasks' => $project->tasks()->where('assigned_to', $user->id)->count(),
                    'completed_tasks' => $project->tasks()->where('assigned_to', $user->id)->where('status', 'completed')->count(),
                    'pending_tasks' => $project->tasks()->where('assigned_to', $user->id)->where('status', '!=', 'completed')->count(),
                ];
            }),
        ];

        return $this->zenaSuccessResponse($workload);
    }

    /**
     * Get design tasks count for a project and user.
     */
    private function getDesignTasksCount(string $projectId, string $userId): int
    {
        return Task::where('project_id', $projectId)
            ->where('assigned_to', $userId)
            ->where('type', 'design')
            ->count();
    }

    /**
     * Get recent activities.
     */
    private function getRecentActivities(array $projectIds): array
    {
        // Sample data - in real implementation, this would come from activity log
        return [
            [
                'id' => '1',
                'type' => 'drawing_created',
                'description' => 'New drawing "Foundation Plan" created',
                'project_id' => $projectIds[0] ?? null,
                'user' => 'Designer',
                'timestamp' => now()->subHours(1),
            ],
            [
                'id' => '2',
                'type' => 'rfi_answered',
                'description' => 'RFI #001 answered',
                'project_id' => $projectIds[0] ?? null,
                'user' => 'Designer',
                'timestamp' => now()->subHours(3),
            ],
        ];
    }

    /**
     * Get upcoming design deadlines.
     */
    private function getUpcomingDesignDeadlines(array $projectIds, string $userId): array
    {
        return Task::whereIn('project_id', $projectIds)
            ->where('assigned_to', $userId)
            ->where('type', 'design')
            ->where('due_date', '>=', now())
            ->where('status', '!=', 'completed')
            ->orderBy('due_date', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'due_date' => $task->due_date,
                    'priority' => $task->priority,
                    'status' => $task->status,
                ];
            })
            ->toArray();
    }

    /**
     * Calculate current workload.
     */
    private function calculateCurrentWorkload(array $projectIds, string $userId): array
    {
        $totalTasks = Task::whereIn('project_id', $projectIds)
            ->where('assigned_to', $userId)
            ->where('type', 'design')
            ->count();

        $completedTasks = Task::whereIn('project_id', $projectIds)
            ->where('assigned_to', $userId)
            ->where('type', 'design')
            ->where('status', 'completed')
            ->count();

        $pendingRfis = Rfi::whereIn('project_id', $projectIds)
            ->where('assigned_to', $userId)
            ->where('status', 'pending')
            ->count();

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'pending_tasks' => $totalTasks - $completedTasks,
            'pending_rfis' => $pendingRfis,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
        ];
    }

    /**
     * Get upcoming deadlines.
     */
    private function getUpcomingDeadlines(array $projectIds, string $userId): array
    {
        return Task::whereIn('project_id', $projectIds)
            ->where('assigned_to', $userId)
            ->where('type', 'design')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(7))
            ->where('status', '!=', 'completed')
            ->orderBy('due_date', 'asc')
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'due_date' => $task->due_date,
                    'priority' => $task->priority,
                    'days_remaining' => now()->diffInDays($task->due_date, false),
                ];
            })
            ->toArray();
    }
}
