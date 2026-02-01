<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\MaterialRequest;
use App\Models\Project;
use App\Models\QcInspection;
use App\Models\Rfi;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteEngineerDashboardController extends Controller
{
    use ZenaContractResponseTrait;

    /**
     * Get Site Engineer dashboard overview.
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
        
        // Get Site Engineer's projects
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
                    'site_tasks' => $project->tasks()->where('assigned_to', $user->id)->count(),
                ];
            }),
            'summary' => [
                'assigned_projects' => $projects->count(),
                'site_tasks' => Task::whereIn('project_id', $projects->pluck('id'))
                    ->where('assigned_to', $user->id)
                    ->where('type', 'site')->count(),
                'completed_site_tasks' => Task::whereIn('project_id', $projects->pluck('id'))
                    ->where('assigned_to', $user->id)
                    ->where('type', 'site')
                    ->where('status', 'completed')->count(),
                'material_requests' => MaterialRequest::whereIn('project_id', $projects->pluck('id'))
                    ->where('status', 'pending')->count(),
                'qc_inspections' => QcInspection::whereIn('project_id', $projects->pluck('id'))
                    ->where('status', 'pending')->count(),
            ],
            'recent_activities' => $this->getRecentActivities($projects->pluck('id')->toArray()),
            'site_conditions' => $this->getSiteConditions($projects->pluck('id')->toArray()),
        ];

        return $this->zenaSuccessResponse($overview);
    }

    /**
     * Get site tasks for the site engineer.
     */
    public function getSiteTasks(Request $request): JsonResponse
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
            ->where('type', 'site');

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
     * Get material requests for the site engineer.
     */
    public function getMaterialRequests(Request $request): JsonResponse
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
        
        $query = MaterialRequest::query();

        if ($projectId) {
            $query->where('project_id', $projectId);
        } else {
            // Get material requests from site engineer's projects
            $projectIds = Project::whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->pluck('id');
            
            $query->whereIn('project_id', $projectIds);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $materialRequests = $query->with(['project:id,name', 'requestedBy:id,name'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'title' => $request->title,
                    'description' => $request->description,
                    'material_type' => $request->material_type,
                    'quantity' => $request->quantity,
                    'unit' => $request->unit,
                    'status' => $request->status,
                    'priority' => $request->priority,
                    'requested_date' => $request->requested_date,
                    'required_date' => $request->required_date,
                    'project' => $request->project,
                    'requested_by' => $request->requestedBy,
                    'created_at' => $request->created_at,
                ];
            });

        return $this->zenaSuccessResponse($materialRequests);
    }

    /**
     * Get site RFIs for the site engineer.
     */
    public function getSiteRfis(Request $request): JsonResponse
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
        
        $query = Rfi::query();

        if ($projectId) {
            $query->where('project_id', $projectId);
        } else {
            // Get RFIs from site engineer's projects
            $projectIds = Project::whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->pluck('id');
            
            $query->whereIn('project_id', $projectIds);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $rfis = $query->with(['project:id,name', 'createdBy:id,name', 'assignedUser:id,name'])
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
                    'assigned_to' => $rfi->assignedUser,
                    'created_at' => $rfi->created_at,
                ];
            });

        return $this->zenaSuccessResponse($rfis);
    }

    /**
     * Get QC inspections for the site engineer.
     */
    public function getQcInspections(Request $request): JsonResponse
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
        
        $query = QcInspection::query();

        if ($projectId) {
            $query->where('project_id', $projectId);
        } else {
            // Get inspections from site engineer's projects
            $projectIds = Project::whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->pluck('id');
            
            $query->whereIn('project_id', $projectIds);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $inspections = $query->with(['project:id,name', 'inspector:id,name'])
            ->orderBy('scheduled_date', 'asc')
            ->get()
            ->map(function ($inspection) {
                return [
                    'id' => $inspection->id,
                    'title' => $inspection->title,
                    'description' => $inspection->description,
                    'inspection_type' => $inspection->inspection_type,
                    'status' => $inspection->status,
                    'scheduled_date' => $inspection->scheduled_date,
                    'project' => $inspection->project,
                    'inspector' => $inspection->inspector,
                    'created_at' => $inspection->created_at,
                ];
            });

        return $this->zenaSuccessResponse($inspections);
    }

    /**
     * Get site safety status.
     */
    public function getSiteSafetyStatus(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        
        // Get site engineer's projects
        $projects = Project::whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });

        if ($projectId) {
            $projects->where('id', $projectId);
        }

        $projects = $projects->get();

        $safetyStatus = [
            'overall_safety_score' => $this->calculateSafetyScore($projects->pluck('id')->toArray()),
            'safety_incidents' => $this->getSafetyIncidents($projects->pluck('id')->toArray()),
            'safety_checklists' => $this->getSafetyChecklists($projects->pluck('id')->toArray()),
            'safety_training_status' => $this->getSafetyTrainingStatus($projects->pluck('id')->toArray()),
        ];

        return $this->zenaSuccessResponse($safetyStatus);
    }

    /**
     * Get daily site report.
     */
    public function getDailySiteReport(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        $date = $request->input('date', now()->format('Y-m-d'));
        
        // Get site engineer's projects
        $projects = Project::whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });

        if ($projectId) {
            $projects->where('id', $projectId);
        }

        $projects = $projects->get();

        $report = [
            'date' => $date,
            'weather_conditions' => $this->getWeatherConditions($date),
            'work_completed' => $this->getWorkCompleted($projects->pluck('id')->toArray(), $date),
            'work_planned' => $this->getWorkPlanned($projects->pluck('id')->toArray(), $date),
            'issues_encountered' => $this->getIssuesEncountered($projects->pluck('id')->toArray(), $date),
            'material_deliveries' => $this->getMaterialDeliveries($projects->pluck('id')->toArray(), $date),
            'safety_observations' => $this->getSafetyObservations($projects->pluck('id')->toArray(), $date),
        ];

        return $this->zenaSuccessResponse($report);
    }

    /**
     * Get site tasks count for a project and user.
     */
    private function getSiteTasksCount(string $projectId, string $userId): int
    {
        return Task::where('project_id', $projectId)
            ->where('assigned_to', $userId)
            ->where('type', 'site')
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
                'type' => 'task_completed',
                'description' => 'Site task "Foundation Pouring" completed',
                'project_id' => $projectIds[0] ?? null,
                'user' => 'Site Engineer',
                'timestamp' => now()->subHours(2),
            ],
            [
                'id' => '2',
                'type' => 'material_requested',
                'description' => 'Material request for concrete submitted',
                'project_id' => $projectIds[0] ?? null,
                'user' => 'Site Engineer',
                'timestamp' => now()->subHours(4),
            ],
        ];
    }

    /**
     * Get site conditions.
     */
    private function getSiteConditions(array $projectIds): array
    {
        // Sample data - in real implementation, this would come from site monitoring
        return [
            [
                'project_id' => $projectIds[0] ?? null,
                'condition' => 'weather',
                'status' => 'good',
                'description' => 'Clear skies, optimal working conditions',
            ],
            [
                'project_id' => $projectIds[0] ?? null,
                'condition' => 'access',
                'status' => 'good',
                'description' => 'Site access clear, no obstructions',
            ],
        ];
    }

    /**
     * Calculate safety score.
     */
    private function calculateSafetyScore(array $projectIds): int
    {
        // Sample calculation - in real implementation, this would analyze safety metrics
        return 85; // Out of 100
    }

    /**
     * Get safety incidents.
     */
    private function getSafetyIncidents(array $projectIds): array
    {
        // Sample data - in real implementation, this would come from safety incident reports
        return [
            [
                'id' => '1',
                'project_id' => $projectIds[0] ?? null,
                'type' => 'near_miss',
                'description' => 'Worker almost fell from scaffold',
                'severity' => 'low',
                'date' => now()->subDays(2),
            ],
        ];
    }

    /**
     * Get safety checklists.
     */
    private function getSafetyChecklists(array $projectIds): array
    {
        // Sample data - in real implementation, this would come from safety checklist system
        return [
            [
                'id' => '1',
                'project_id' => $projectIds[0] ?? null,
                'checklist_type' => 'daily_safety',
                'status' => 'completed',
                'completed_by' => 'Site Engineer',
                'date' => now()->subDays(1),
            ],
        ];
    }

    /**
     * Get safety training status.
     */
    private function getSafetyTrainingStatus(array $projectIds): array
    {
        // Sample data - in real implementation, this would come from training management system
        return [
            [
                'training_type' => 'fall_protection',
                'status' => 'current',
                'expiry_date' => now()->addMonths(6),
            ],
            [
                'training_type' => 'hazard_communication',
                'status' => 'expired',
                'expiry_date' => now()->subDays(30),
            ],
        ];
    }

    /**
     * Get weather conditions.
     */
    private function getWeatherConditions(string $date): array
    {
        // Sample data - in real implementation, this would integrate with weather API
        return [
            'temperature' => '22°C',
            'conditions' => 'Clear',
            'wind_speed' => '5 mph',
            'humidity' => '65%',
            'impact_on_work' => 'Optimal',
        ];
    }

    /**
     * Get work completed.
     */
    private function getWorkCompleted(array $projectIds, string $date): array
    {
        // Sample data - in real implementation, this would come from task completion logs
        return [
            [
                'task' => 'Foundation Pouring',
                'progress' => '100%',
                'notes' => 'Completed successfully',
            ],
            [
                'task' => 'Rebar Installation',
                'progress' => '75%',
                'notes' => 'Partial completion due to material shortage',
            ],
        ];
    }

    /**
     * Get work planned.
     */
    private function getWorkPlanned(array $projectIds, string $date): array
    {
        // Sample data - in real implementation, this would come from work planning system
        return [
            [
                'task' => 'Concrete Curing',
                'duration' => '8 hours',
                'crew_size' => 2,
            ],
            [
                'task' => 'Formwork Removal',
                'duration' => '4 hours',
                'crew_size' => 3,
            ],
        ];
    }

    /**
     * Get issues encountered.
     */
    private function getIssuesEncountered(array $projectIds, string $date): array
    {
        // Sample data - in real implementation, this would come from issue tracking system
        return [
            [
                'issue' => 'Material Delivery Delay',
                'severity' => 'medium',
                'description' => 'Concrete delivery delayed by 2 hours',
                'resolution' => 'Rescheduled for tomorrow',
            ],
        ];
    }

    /**
     * Get material deliveries.
     */
    private function getMaterialDeliveries(array $projectIds, string $date): array
    {
        // Sample data - in real implementation, this would come from material tracking system
        return [
            [
                'material' => 'Concrete',
                'quantity' => '50 cubic meters',
                'supplier' => 'ABC Concrete Co.',
                'delivery_time' => '09:00 AM',
                'status' => 'delivered',
            ],
        ];
    }

    /**
     * Get safety observations.
     */
    private function getSafetyObservations(array $projectIds, string $date): array
    {
        // Sample data - in real implementation, this would come from safety observation system
        return [
            [
                'observation' => 'All workers wearing hard hats',
                'type' => 'positive',
                'noted_by' => 'Site Engineer',
                'time' => '10:30 AM',
            ],
            [
                'observation' => 'Safety barrier needs repair',
                'type' => 'concern',
                'noted_by' => 'Site Engineer',
                'time' => '02:15 PM',
            ],
        ];
    }
}
