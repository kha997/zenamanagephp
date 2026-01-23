<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProjectAnalyticsController - API Controller cho Project Analytics
 */
class ProjectAnalyticsController extends Controller
{
    /**
     * Get project metrics overview
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $tenantId = $user->tenant_id;
            $dateRange = $request->input('date_range', '30'); // days
            
            $startDate = now()->subDays($dateRange);
            $endDate = now();
            
            // Basic metrics
            $totalProjects = Project::forTenant($tenantId)->count();
            $activeProjects = Project::forTenant($tenantId)->active()->count();
            $completedProjects = Project::forTenant($tenantId)->byStatus('completed')->count();
            $overdueProjects = Project::forTenant($tenantId)->overdue()->count();
            
            // Budget metrics
            $totalBudget = Project::forTenant($tenantId)->sum('budget_planned');
            $actualSpent = Project::forTenant($tenantId)->sum('budget_actual');
            $budgetUtilization = $totalBudget > 0 ? round(($actualSpent / $totalBudget) * 100, 2) : 0;
            
            // Progress metrics
            $avgProgress = Project::forTenant($tenantId)->avg('progress') ?? 0;
            $projectsOnTrack = Project::forTenant($tenantId)
                ->where('progress', '>=', 75)
                ->where('status', 'active')
                ->count();
            
            // Timeline metrics
            $projectsStartingSoon = Project::forTenant($tenantId)
                ->where('start_date', '>=', now())
                ->where('start_date', '<=', now()->addDays(7))
                ->count();
            
            $projectsEndingSoon = Project::forTenant($tenantId)
                ->where('end_date', '>=', now())
                ->where('end_date', '<=', now()->addDays(7))
                ->count();
            
            // Milestone metrics
            $totalMilestones = ProjectMilestone::whereHas('project', function ($query) {
                $query->where('tenant_id', $tenantId);
            })->count();
            
            $completedMilestones = ProjectMilestone::whereHas('project', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })->where('status', 'completed')->count();
            
            $milestoneCompletionRate = $totalMilestones > 0 ? 
                round(($completedMilestones / $totalMilestones) * 100, 2) : 0;
            
            $metrics = [
                'overview' => [
                    'total_projects' => $totalProjects,
                    'active_projects' => $activeProjects,
                    'completed_projects' => $completedProjects,
                    'overdue_projects' => $overdueProjects,
                ],
                'budget' => [
                    'total_budget' => $totalBudget,
                    'actual_spent' => $actualSpent,
                    'budget_utilization_percentage' => $budgetUtilization,
                    'remaining_budget' => $totalBudget - $actualSpent,
                ],
                'progress' => [
                    'average_progress' => round($avgProgress, 2),
                    'projects_on_track' => $projectsOnTrack,
                    'milestone_completion_rate' => $milestoneCompletionRate,
                ],
                'timeline' => [
                    'projects_starting_soon' => $projectsStartingSoon,
                    'projects_ending_soon' => $projectsEndingSoon,
                ],
                'milestones' => [
                    'total_milestones' => $totalMilestones,
                    'completed_milestones' => $completedMilestones,
                    'pending_milestones' => $totalMilestones - $completedMilestones,
                ]
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => $metrics
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get project metrics', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve project metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get project trends over time
     */
    public function getTrends(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $tenantId = $user->tenant_id;
            $period = $request->input('period', 'month'); // day, week, month, quarter, year
            
            $dateFormat = match($period) {
                'day' => '%Y-%m-%d',
                'week' => '%Y-%u',
                'month' => '%Y-%m',
                'quarter' => '%Y-%q',
                'year' => '%Y',
                default => '%Y-%m'
            };
            
            // Project creation trends
            $projectTrends = Project::forTenant($tenantId)
                ->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as period, COUNT(*) as count")
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy('period')
                ->orderBy('period')
                ->get();
            
            // Project completion trends
            $completionTrends = Project::forTenant($tenantId)
                ->selectRaw("DATE_FORMAT(updated_at, '{$dateFormat}') as period, COUNT(*) as count")
                ->where('status', 'completed')
                ->where('updated_at', '>=', now()->subMonths(12))
                ->groupBy('period')
                ->orderBy('period')
                ->get();
            
            // Budget trends
            $budgetTrends = Project::forTenant($tenantId)
                ->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as period, SUM(budget_planned) as planned, SUM(budget_actual) as actual")
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy('period')
                ->orderBy('period')
                ->get();
            
            // Progress trends
            $progressTrends = Project::forTenant($tenantId)
                ->selectRaw("DATE_FORMAT(updated_at, '{$dateFormat}') as period, AVG(progress) as avg_progress")
                ->where('updated_at', '>=', now()->subMonths(12))
                ->groupBy('period')
                ->orderBy('period')
                ->get();
            
            $trends = [
                'project_creation' => $projectTrends,
                'project_completion' => $completionTrends,
                'budget_trends' => $budgetTrends,
                'progress_trends' => $progressTrends,
                'period' => $period
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => $trends
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get project trends', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve project trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get project performance analysis
     */
    public function getPerformance(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $tenantId = $user->tenant_id;
            $dateRange = $request->input('date_range', '90'); // days
            
            $startDate = now()->subDays($dateRange);
            
            // Performance by status
            $statusPerformance = Project::forTenant($tenantId)
                ->selectRaw('status, COUNT(*) as count, AVG(progress) as avg_progress')
                ->groupBy('status')
                ->get();
            
            // Performance by priority
            $priorityPerformance = Project::forTenant($tenantId)
                ->selectRaw('priority, COUNT(*) as count, AVG(progress) as avg_progress')
                ->groupBy('priority')
                ->get();
            
            // Top performing projects
            $topProjects = Project::forTenant($tenantId)
                ->where('progress', '>', 0)
                ->orderBy('progress', 'desc')
                ->limit(10)
                ->get(['id', 'name', 'progress', 'status', 'priority']);
            
            // Projects needing attention
            $attentionProjects = Project::forTenant($tenantId)
                ->where(function ($query) {
                    $query->where('progress', '<', 25)
                          ->orWhere('end_date', '<', now())
                          ->orWhere('budget_actual', '>', DB::raw('budget_planned * 1.1'));
                })
                ->where('status', '!=', 'completed')
                ->limit(10)
                ->get(['id', 'name', 'progress', 'status', 'end_date', 'budget_planned', 'budget_actual']);
            
            // Team performance
            $teamPerformance = DB::table('project_team_members')
                ->join('projects', 'project_team_members.project_id', '=', 'projects.id')
                ->join('users', 'project_team_members.user_id', '=', 'users.id')
                ->where('projects.tenant_id', $tenantId)
                ->selectRaw('users.name, COUNT(projects.id) as project_count, AVG(projects.progress) as avg_progress')
                ->groupBy('users.id', 'users.name')
                ->orderBy('avg_progress', 'desc')
                ->limit(10)
                ->get();
            
            $performance = [
                'status_performance' => $statusPerformance,
                'priority_performance' => $priorityPerformance,
                'top_projects' => $topProjects,
                'attention_projects' => $attentionProjects,
                'team_performance' => $teamPerformance,
                'date_range' => $dateRange
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => $performance
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get project performance', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve project performance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get project risk analysis
     */
    public function getRiskAnalysis(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $tenantId = $user->tenant_id;
            
            // High risk projects (overdue, over budget, low progress)
            $highRiskProjects = Project::forTenant($tenantId)
                ->where(function ($query) {
                    $query->where('end_date', '<', now())
                          ->orWhere('budget_actual', '>', DB::raw('budget_planned * 1.2'))
                          ->orWhere(function ($q) {
                              $q->where('progress', '<', 25)
                                ->where('start_date', '<', now()->subDays(30));
                          });
                })
                ->where('status', '!=', 'completed')
                ->get(['id', 'name', 'status', 'progress', 'end_date', 'budget_planned', 'budget_actual']);
            
            // Risk factors analysis
            $riskFactors = [
                'overdue_projects' => Project::forTenant($tenantId)->overdue()->count(),
                'over_budget_projects' => Project::forTenant($tenantId)
                    ->whereRaw('budget_actual > budget_planned * 1.1')
                    ->count(),
                'low_progress_projects' => Project::forTenant($tenantId)
                    ->where('progress', '<', 25)
                    ->where('status', 'active')
                    ->count(),
                'delayed_milestones' => ProjectMilestone::whereHas('project', function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                })->where('target_date', '<', now())
                  ->where('status', 'pending')
                  ->count(),
            ];
            
            // Risk score calculation
            $totalProjects = Project::forTenant($tenantId)->count();
            $riskScore = $totalProjects > 0 ? 
                round((($riskFactors['overdue_projects'] + $riskFactors['over_budget_projects'] + 
                       $riskFactors['low_progress_projects']) / $totalProjects) * 100, 2) : 0;
            
            // Risk mitigation suggestions
            $suggestions = [];
            if ($riskFactors['overdue_projects'] > 0) {
                $suggestions[] = 'Review overdue projects and adjust timelines';
            }
            if ($riskFactors['over_budget_projects'] > 0) {
                $suggestions[] = 'Monitor budget overruns and implement cost controls';
            }
            if ($riskFactors['low_progress_projects'] > 0) {
                $suggestions[] = 'Investigate low progress projects and provide support';
            }
            if ($riskFactors['delayed_milestones'] > 0) {
                $suggestions[] = 'Review delayed milestones and update project plans';
            }
            
            $riskAnalysis = [
                'risk_score' => $riskScore,
                'risk_level' => $riskScore > 30 ? 'High' : ($riskScore > 15 ? 'Medium' : 'Low'),
                'risk_factors' => $riskFactors,
                'high_risk_projects' => $highRiskProjects,
                'suggestions' => $suggestions,
                'total_projects' => $totalProjects
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => $riskAnalysis
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get project risk analysis', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve project risk analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}