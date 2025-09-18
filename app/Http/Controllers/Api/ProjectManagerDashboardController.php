<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProjectManagerDashboardController extends Controller
{
    /**
     * Get dashboard statistics for Project Manager
     */
    public function getStats(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->hasRole('project_manager')) {
                return response()->json(['error' => 'Unauthorized'], 401);
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
            $completedTasks = Task::whereIn('project_id', $projectIds)->where('status', 'done')->count();
            $pendingTasks = Task::whereIn('project_id', $projectIds)->whereIn('status', ['todo', 'in_progress'])->count();
            $overdueTasks = Task::whereIn('project_id', $projectIds)
                ->where('due_date', '<', now())
                ->whereNotIn('status', ['done', 'cancelled'])
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Project Manager dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
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
}
