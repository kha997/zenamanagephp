<?php declare(strict_types=1);

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;


use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use App\Models\{Project, Task, User};
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('app.dashboard');
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
            })->where('due_date', '<', now())
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
            'overdue' => ['-1', '+2', '-3', '0'],
            'schedule' => ['+3', '+1', '+2', '+4']
        ];
        
        return $changes[$type][array_rand($changes[$type])];
    }

    private function getAlerts(string $tenantId): array
    {
        $alerts = [];
        
        // Check for overdue tasks
        $overdueCount = Task::whereHas('project', function($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->where('due_date', '<', now())
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
        // Mock activity data - implement real activity log later
        return [
            [
                'id' => 1,
                'description' => 'New task created in Project Alpha',
                'time' => '5 minutes ago',
                'user' => 'John Doe',
                'type' => 'task_created'
            ],
            [
                'id' => 2,
                'description' => 'Project Beta status updated to In Progress',
                'time' => '15 minutes ago',
                'user' => 'Sarah Smith',
                'type' => 'project_updated'
            ],
            [
                'id' => 3,
                'description' => 'Document uploaded to Project Gamma',
                'time' => '1 hour ago',
                'user' => 'Mike Johnson',
                'type' => 'document_uploaded'
            ],
            [
                'id' => 4,
                'description' => 'Task completed in Project Delta',
                'time' => '2 hours ago',
                'user' => 'Lisa Brown',
                'type' => 'task_completed'
            ]
        ];
    }
}