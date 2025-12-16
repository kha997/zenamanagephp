<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin Analytics Controller
 * 
 * Dashboard analytics for admin with tenant scoping.
 */
class AdminAnalyticsController extends Controller
{
    /**
     * Display analytics dashboard
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        
        // Determine tenant scope
        $tenantId = null;
        if ($user->can('admin.access.tenant') && !$user->isSuperAdmin()) {
            $tenantId = $user->tenant_id;
        }
        
        // Date range filter
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        
        // Build base queries with tenant scope
        $projectQuery = Project::query();
        $taskQuery = Task::query();
        $userQuery = User::query();
        
        if ($tenantId) {
            $projectQuery->where('tenant_id', $tenantId);
            $taskQuery->whereHas('project', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            });
            $userQuery->where('tenant_id', $tenantId);
        }
        
        // KPI Cards
        $kpis = [
            'total_projects' => (clone $projectQuery)->count(),
            'active_projects' => (clone $projectQuery)->where('status', 'active')->count(),
            'total_tasks' => (clone $taskQuery)->count(),
            'completed_tasks' => (clone $taskQuery)->where('status', 'completed')->count(),
            'total_users' => (clone $userQuery)->where('is_active', true)->count(),
            'revenue' => (clone $projectQuery)->sum('budget_total'),
        ];
        
        // Project status distribution
        $projectStatusDistribution = (clone $projectQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
        
        // Task completion trends (last 30 days)
        $taskTrends = (clone $taskQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as total'),
                DB::raw('sum(case when status = "completed" then 1 else 0 end) as completed')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // User activity (active users in last 7 days)
        $userActivity = (clone $userQuery)
            ->where('last_login_at', '>=', now()->subDays(7))
            ->count();
        
        $data = [
            'kpis' => $kpis,
            'project_status_distribution' => $projectStatusDistribution,
            'task_trends' => $taskTrends,
            'user_activity' => $userActivity,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'tenant_id' => $tenantId,
            ],
        ];
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }
        
        return view('admin.analytics.index', $data);
    }
}
