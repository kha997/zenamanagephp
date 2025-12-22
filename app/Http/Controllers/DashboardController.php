<?php declare(strict_types=1);

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use App\Services\RealData\RealActivityService;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Get user from Auth (proper Laravel authentication)
        $user = Auth::user();
        if (!$user) {
            // Redirect to login if not authenticated
            return redirect()->route('login');
        }
        
        $tenantId = $user->tenant_id;
        
        // Get dashboard data for new standardized dashboard
        $totalProjects = Project::where('tenant_id', $tenantId)->count();
        $totalTasks = Task::where('tenant_id', $tenantId)->count();
        $totalTeamMembers = User::where('tenant_id', $tenantId)->count();
        $budgetUsed = Project::where('tenant_id', $tenantId)->sum('budget_total') ?? 0;
        
        // Calculate changes (mock data for now)
        $projectsChange = $this->calculateChange('projects', (string)$tenantId, 30);
        $tasksChange = $this->calculateChange('tasks', (string)$tenantId, 30);
        $teamChange = $this->calculateChange('team', (string)$tenantId, 30);
        $budgetChange = $this->calculateChange('budget', (string)$tenantId, 30);
        
        // Get recent data
        $recentProjects = Project::where('tenant_id', $tenantId)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();
            
        $recentTasks = Task::where('tenant_id', $tenantId)
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();
            
        $teamMembers = User::where('tenant_id', $tenantId)
            ->orderBy('last_login_at', 'desc')
            ->limit(10)
            ->get();
            
        // Get recent activity
        $recentActivity = $this->getRecentActivity($tenantId);
        
        // Get alerts
        $alerts = $this->getAlerts($tenantId);
        $systemAlerts = collect($alerts)->map(function($alert) {
            return [
                'type' => isset($alert['priority']) && $alert['priority'] === 'high' ? 'error' : 'warning',
                'title' => isset($alert['type']) ? ucfirst($alert['type']) : 'Alert',
                'message' => $alert['message'] ?? 'No message'
            ];
        });
        
        // Mock chart data
        $projectProgressData = [
            ['label' => 'Completed', 'value' => 60],
            ['label' => 'In Progress', 'value' => 30],
            ['label' => 'Not Started', 'value' => 10]
        ];
        
        $taskCompletionData = [
            ['date' => '2024-01-01', 'completed' => 5, 'total' => 10],
            ['date' => '2024-01-02', 'completed' => 8, 'total' => 12],
            ['date' => '2024-01-03', 'completed' => 12, 'total' => 15],
            ['date' => '2024-01-04', 'completed' => 15, 'total' => 18],
            ['date' => '2024-01-05', 'completed' => 18, 'total' => 20]
        ];
        
        return view('app.dashboard.index', compact(
            'totalProjects',
            'totalTasks', 
            'totalTeamMembers',
            'budgetUsed',
            'projectsChange',
            'tasksChange',
            'teamChange',
            'budgetChange',
            'recentProjects',
            'recentTasks',
            'teamMembers',
            'recentActivity',
            'systemAlerts',
            'projectProgressData',
            'taskCompletionData'
        ));
    }

    public function metrics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $period = (int) $request->get('period', 30);
            
            // Calculate date range
            $startDate = Carbon::now()->subDays($period);
            
            // Get metrics
            $activeProjects = Project::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count();
                
            $openTasks = Task::whereHas('project', function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })->whereIn('status', ['pending', 'in_progress'])->count();
            
            $overdueTasks = Task::whereHas('project', function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })->where('end_date', '<', now())
              ->whereNotIn('status', ['completed', 'cancelled'])
              ->count();
              
            $onScheduleProjects = Project::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where('end_date', '>=', now())
                ->count();

            // Calculate changes (mock data for now)
            $metrics = [
                'activeProjects' => $activeProjects,
                'openTasks' => $openTasks,
                'overdueTasks' => $overdueTasks,
                'onSchedule' => $onScheduleProjects,
                'projectsChange' => $this->calculateChange('projects', $tenantId, $period),
                'tasksChange' => $this->calculateChange('tasks', $tenantId, $period),
                'overdueChange' => $this->calculateChange('overdue', $tenantId, $period),
                'scheduleChange' => $this->calculateChange('schedule', $tenantId, $period)
            ];

            // Get alerts
            $alerts = $this->getAlerts($tenantId);
            
            // Get recent activity
            $activity = $this->getRecentActivity($tenantId);

            return response()->json([
                'success' => true,
                'metrics' => $metrics,
                'alerts' => $alerts,
                'activity' => $activity,
                'period' => $period,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            \Log::error('Dashboard metrics error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to load dashboard metrics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function calculateChange(string $type, string $tenantId, int $period): string
    {
        // Mock calculation - implement real logic later
        $changes = [
            'projects' => ['+2', '+1', '+3', '-1'],
            'tasks' => ['+5', '+8', '+3', '+12'],
            'team' => ['+1', '+2', '0', '+1'],
            'budget' => ['+5%', '+2%', '+8%', '+3%'],
            'overdue' => ['-1', '+2', '-3', '0'],
            'schedule' => ['+3', '+1', '+2', '+4']
        ];
        
        // Guard against undefined keys
        if (!isset($changes[$type])) {
            return '0';
        }
        
        return $changes[$type][array_rand($changes[$type])];
    }

    private function getAlerts(string $tenantId): array
    {
        $alerts = [];
        
        // Check for overdue tasks
        $overdueCount = Task::whereHas('project', function($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->where('end_date', '<', now())
          ->whereNotIn('status', ['completed', 'cancelled'])
          ->count();
          
        if ($overdueCount > 0) {
            $alerts[] = [
                'id' => 1,
                'message' => "{$overdueCount} tasks are overdue",
                'priority' => 'high',
                'type' => 'overdue_tasks'
            ];
        }

        // Check for approaching deadlines
        $approachingDeadlines = Project::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereBetween('end_date', [now(), now()->addDays(7)])
            ->count();
            
        if ($approachingDeadlines > 0) {
            $alerts[] = [
                'id' => 2,
                'message' => "{$approachingDeadlines} project deadlines approaching",
                'priority' => 'medium',
                'type' => 'approaching_deadlines'
            ];
        }

        return $alerts;
    }

    private function getRecentActivity(string $tenantId): array
    {
        $realActivityService = app(RealActivityService::class);
        return $realActivityService->getRecentActivities($tenantId, 10);
    }
}