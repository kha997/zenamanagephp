<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TenantDashboardController extends Controller
{
    /**
     * Get dashboard statistics for Tenant Admin
     */
    public function getStats(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->hasRole('admin')) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $tenantId = $user->tenant_id;

            // Get basic counts for tenant
            $totalUsers = User::where('tenant_id', $tenantId)->count();
            $totalProjects = Project::where('tenant_id', $tenantId)->count();
            $totalTasks = Task::where('tenant_id', $tenantId)->count();
            $totalTeams = Team::where('tenant_id', $tenantId)->count();
            $totalDocuments = Document::where('tenant_id', $tenantId)->count();

            // Get active counts
            $activeUsers = User::where('tenant_id', $tenantId)->where('is_active', true)->count();
            $activeProjects = Project::where('tenant_id', $tenantId)->whereIn('status', ['active', 'in_progress'])->count();
            $completedTasks = Task::where('tenant_id', $tenantId)->where('status', 'done')->count();
            $pendingTasks = Task::where('tenant_id', $tenantId)->whereIn('status', ['todo', 'in_progress'])->count();

            // Get financial metrics for tenant
            $financialMetrics = $this->getTenantFinancialMetrics($tenantId);

            $stats = [
                'totalUsers' => $totalUsers,
                'totalProjects' => $totalProjects,
                'totalTasks' => $totalTasks,
                'totalTeams' => $totalTeams,
                'totalDocuments' => $totalDocuments,
                'activeUsers' => $activeUsers,
                'activeProjects' => $activeProjects,
                'completedTasks' => $completedTasks,
                'pendingTasks' => $pendingTasks,
                'financial' => $financialMetrics,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Tenant dashboard statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Tenant dashboard stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tenant dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get financial metrics for tenant
     */
    private function getTenantFinancialMetrics(string $tenantId): array
    {
        try {
            $totalProjectBudget = Project::where('tenant_id', $tenantId)->sum('budget_planned') ?? 0;
            $totalProjectActual = Project::where('tenant_id', $tenantId)->sum('budget_actual') ?? 0;
            $totalProjectRevenue = Project::where('tenant_id', $tenantId)->where('status', 'completed')->sum('budget_actual') ?? 0;
            
            $budgetUtilization = $totalProjectBudget > 0 ? ($totalProjectActual / $totalProjectBudget) * 100 : 0;
            
            return [
                'totalBudget' => $totalProjectBudget,
                'totalActual' => $totalProjectActual,
                'totalRevenue' => $totalProjectRevenue,
                'budgetUtilization' => round($budgetUtilization, 2),
                'profitMargin' => $this->calculateTenantProfitMargin($tenantId),
                'cashFlow' => $this->calculateTenantCashFlow($tenantId),
            ];

        } catch (\Exception $e) {
            Log::error('Tenant financial metrics error: ' . $e->getMessage());
            return [
                'totalBudget' => 0,
                'totalActual' => 0,
                'totalRevenue' => 0,
                'budgetUtilization' => 0,
                'profitMargin' => 0,
                'cashFlow' => 0,
            ];
        }
    }

    /**
     * Calculate profit margin for tenant
     */
    private function calculateTenantProfitMargin(string $tenantId): float
    {
        try {
            $totalRevenue = Project::where('tenant_id', $tenantId)->where('status', 'completed')->sum('budget_actual') ?? 0;
            $totalBudget = Project::where('tenant_id', $tenantId)->where('status', 'completed')->sum('budget_planned') ?? 0;
            
            if ($totalBudget > 0) {
                return round((($totalRevenue - $totalBudget) / $totalBudget) * 100, 2);
            }
            
            return 0;

        } catch (\Exception $e) {
            Log::error('Tenant profit margin calculation error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calculate cash flow for tenant
     */
    private function calculateTenantCashFlow(string $tenantId): float
    {
        try {
            $inflow = Project::where('tenant_id', $tenantId)->where('status', 'completed')->sum('budget_actual') ?? 0;
            $outflow = Project::where('tenant_id', $tenantId)->sum('budget_planned') ?? 0;
            
            return $inflow - $outflow;

        } catch (\Exception $e) {
            Log::error('Tenant cash flow calculation error: ' . $e->getMessage());
            return 0;
        }
    }
}