<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Src\CoreProject\Models\LegacyProjectAdapter as Project;
use App\Models\Task;
use App\Services\ErrorEnvelopeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Project Manager",
 *     description="Project Manager dashboard and analytics endpoints"
 * )
 */
class ProjectManagerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/project-manager/dashboard/stats",
     *     summary="Get Project Manager dashboard statistics",
     *     description="Retrieve comprehensive statistics for project manager dashboard including project metrics, task counts, and financial data",
     *     tags={"Project Manager"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard statistics retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="totalProjects", type="integer", example=15),
     *                 @OA\Property(property="activeProjects", type="integer", example=8),
     *                 @OA\Property(property="completedProjects", type="integer", example=5),
     *                 @OA\Property(property="totalTasks", type="integer", example=120),
     *                 @OA\Property(property="completedTasks", type="integer", example=85),
     *                 @OA\Property(property="pendingTasks", type="integer", example=25),
     *                 @OA\Property(property="overdueTasks", type="integer", example=10),
     *                 @OA\Property(
     *                     property="financial",
     *                     type="object",
     *                     @OA\Property(property="totalBudget", type="number", format="float", example=500000.00),
     *                     @OA\Property(property="totalActual", type="number", format="float", example=450000.00),
     *                     @OA\Property(property="totalRevenue", type="number", format="float", example=480000.00),
     *                     @OA\Property(property="budgetUtilization", type="number", format="float", example=90.0),
     *                     @OA\Property(property="profitMargin", type="number", format="float", example=15.5)
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Project Manager dashboard statistics retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Insufficient permissions - Project Manager role required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     )
     * )
     * 
     * Get dashboard statistics for Project Manager
     */
    public function getStats(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->hasRole('project_manager')) {
                return ErrorEnvelopeService::authorizationError('project_manager.role_required');
            }

            $tenantId = $user->tenant_id;

            // Get projects managed by this PM
            $managedProjects = Project::where('tenant_id', $tenantId)
                ->where('pm_id', $user->id)
                ->get();

            $projectIds = $managedProjects->pluck('id')->toArray();

            // Get basic counts
            $totalProjects = $managedProjects->count();
            $activeProjects = $managedProjects->whereIn('status', ['active', 'in_progress'])->count();
            $completedProjects = $managedProjects->where('status', 'completed')->count();
            $overdueProjects = $managedProjects->where('end_date', '<', now())->whereNotIn('status', ['completed', 'cancelled'])->count();

            // Get task statistics
            $totalTasks = Task::whereIn('project_id', $projectIds)->count();
            $completedTasks = Task::whereIn('project_id', $projectIds)->whereIn('status', [Task::STATUS_COMPLETED, 'done'])->count();
            $pendingTasks = Task::whereIn('project_id', $projectIds)->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, 'todo'])->count();
            $overdueTasks = Task::whereIn('project_id', $projectIds)
                ->whereNotNull('end_date')
                ->whereDate('end_date', '<', Carbon::today())
                ->whereNotIn('status', [Task::STATUS_COMPLETED, 'done', 'cancelled'])
                ->count();

            // Get financial metrics
            $financialMetrics = $this->getProjectManagerFinancialMetrics($managedProjects);

            $stats = [
                'totalProjects' => $totalProjects,
                'activeProjects' => $activeProjects,
                'completedProjects' => $completedProjects,
                'overdueProjects' => $overdueProjects,
                'totalTasks' => $totalTasks,
                'completedTasks' => $completedTasks,
                'pendingTasks' => $pendingTasks,
                'overdueTasks' => $overdueTasks,
                'financial' => $financialMetrics,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Project Manager dashboard statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Project Manager dashboard stats error: ' . $e->getMessage());
            return ErrorEnvelopeService::serverError(
                'project_manager.stats_retrieval_failed',
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Get financial metrics for Project Manager
     */
    private function getProjectManagerFinancialMetrics($projects): array
    {
        try {
            $totalBudget = $projects->sum('budget_planned');
            $totalActual = $projects->sum('budget_actual');
            $totalRevenue = $projects->where('status', 'completed')->sum('budget_actual');
            
            $budgetUtilization = $totalBudget > 0 ? ($totalActual / $totalBudget) * 100 : 0;
            
            return [
                'totalBudget' => $totalBudget,
                'totalActual' => $totalActual,
                'totalRevenue' => $totalRevenue,
                'budgetUtilization' => round($budgetUtilization, 2),
                'profitMargin' => $this->calculateProjectManagerProfitMargin($projects),
            ];

        } catch (\Exception $e) {
            Log::error('Project Manager financial metrics error: ' . $e->getMessage());
            return [
                'totalBudget' => 0,
                'totalActual' => 0,
                'totalRevenue' => 0,
                'budgetUtilization' => 0,
                'profitMargin' => 0,
            ];
        }
    }

    /**
     * Calculate profit margin for Project Manager
     */
    private function calculateProjectManagerProfitMargin($projects): float
    {
        try {
            $completedProjects = $projects->where('status', 'completed');
            $totalRevenue = $completedProjects->sum('budget_actual');
            $totalBudget = $completedProjects->sum('budget_planned');
            
            if ($totalBudget > 0) {
                return round((($totalRevenue - $totalBudget) / $totalBudget) * 100, 2);
            }
            
            return 0;

        } catch (\Exception $e) {
            Log::error('Project Manager profit margin calculation error: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * @OA\Get(
     *     path="/api/v1/project-manager/dashboard/timeline",
     *     summary="Get Project Manager project timeline",
     *     description="Retrieve project timeline data for project manager dashboard showing project schedules and progress",
     *     tags={"Project Manager"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Project timeline retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Website Redesign"),
     *                     @OA\Property(property="start_date", type="string", format="date", example="2024-01-01"),
     *                     @OA\Property(property="end_date", type="string", format="date", example="2024-03-31"),
     *                     @OA\Property(property="status", type="string", example="active"),
     *                     @OA\Property(property="progress", type="integer", example=75),
     *                     @OA\Property(property="duration_days", type="integer", example=90)
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Project timeline retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Insufficient permissions - Project Manager role required",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorEnvelope")
     *     )
     * )
     * 
     * Get project timeline for Project Manager
     */
    public function getProjectTimeline(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->hasRole('project_manager')) {
                return ErrorEnvelopeService::authorizationError('project_manager.role_required');
            }
            
            $tenantId = $user->tenant_id;
            
            // Get projects with timeline data
            $projects = Project::where('tenant_id', $tenantId)
                ->where('pm_id', $user->id)
                ->select(['id', 'name', 'start_date', 'end_date', 'status', 'progress'])
                ->orderBy('start_date', 'asc')
                ->get();
            
            $timeline = $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                    'status' => $project->status,
                    'progress' => $project->progress,
                    'duration_days' => $project->start_date && $project->end_date 
                        ? $project->start_date->diffInDays($project->end_date) 
                        : null,
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $timeline,
                'message' => 'Project timeline retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Project Manager timeline error: ' . $e->getMessage());
            return ErrorEnvelopeService::serverError(
                'project_manager.timeline_retrieval_failed',
                ['exception' => $e->getMessage()]
            );
        }
    }
}
