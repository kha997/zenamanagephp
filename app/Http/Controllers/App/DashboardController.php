<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? null;

        try {
            // Fetch dashboard data
            $dashboardData = $this->fetchDashboardData($tenantId);
            
            // Prepare chart data
            $chartData = $this->prepareChartData($dashboardData);
            
            // Prepare team status
            $teamStatus = $this->prepareTeamStatus($dashboardData['teamMembers'] ?? collect());
            
            // Prepare system alerts
            $systemAlerts = $this->prepareSystemAlerts($dashboardData);
            
            // Bootstrap data for frontend
            $dashboardBootstrap = [
                'kpis' => [
                    'totalProjects' => $dashboardData['totalProjects'] ?? 0,
                    'projectGrowth' => $this->formatChange($dashboardData['projectsChange'] ?? 0),
                    'activeTasks' => $dashboardData['totalTasks'] ?? 0,
                    'taskGrowth' => $this->formatChange($dashboardData['tasksChange'] ?? 0),
                    'teamMembers' => $dashboardData['totalTeamMembers'] ?? 0,
                    'teamGrowth' => $this->formatChange($dashboardData['teamChange'] ?? 0),
                    'completionRate' => $dashboardData['completionRate'] ?? 0,
                ],
                'alerts' => $systemAlerts,
                'recentProjects' => $dashboardData['recentProjects'] ?? collect(),
                'recentActivity' => $dashboardData['recentActivity'] ?? collect(),
                'teamStatus' => $teamStatus,
                'charts' => $chartData,
            ];

            return view('app.dashboard.index', [
                'user' => $user,
                'dashboardBootstrap' => json_encode($dashboardBootstrap),
                'totalProjects' => $dashboardData['totalProjects'] ?? 0,
                'projectsChange' => $dashboardData['projectsChange'] ?? 0,
                'totalTasks' => $dashboardData['totalTasks'] ?? 0,
                'tasksChange' => $dashboardData['tasksChange'] ?? 0,
                'totalTeamMembers' => $dashboardData['totalTeamMembers'] ?? 0,
                'teamChange' => $dashboardData['teamChange'] ?? 0,
                'budgetUsed' => $dashboardData['budgetUsed'] ?? 0,
                'budgetChange' => $dashboardData['budgetChange'] ?? 0,
                'completionRate' => $dashboardData['completionRate'] ?? 0,
                'recentProjects' => $dashboardData['recentProjects'] ?? collect(),
                'recentTasks' => $dashboardData['recentTasks'] ?? collect(),
                'recentActivity' => $dashboardData['recentActivity'] ?? collect(),
                'teamMembers' => $teamStatus,
                'systemAlerts' => $systemAlerts,
                'projectProgressData' => $chartData['projectProgress'] ?? [],
                'taskCompletionData' => $chartData['taskCompletion'] ?? [],
            ]);

        } catch (\Exception $e) {
            \Log::error('DashboardController error', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'tenant_id' => $tenantId
            ]);

            // Return minimal data on error
            return view('app.dashboard.index', [
                'user' => $user,
                'dashboardBootstrap' => json_encode([
                    'kpis' => ['totalProjects' => 0, 'activeTasks' => 0, 'teamMembers' => 0, 'completionRate' => 0],
                    'alerts' => [],
                    'recentProjects' => collect(),
                    'recentActivity' => collect(),
                    'teamStatus' => [],
                    'charts' => ['projectProgress' => [], 'taskCompletion' => []],
                ]),
                'totalProjects' => 0,
                'projectsChange' => 0,
                'totalTasks' => 0,
                'tasksChange' => 0,
                'totalTeamMembers' => 0,
                'teamChange' => 0,
                'budgetUsed' => 0,
                'budgetChange' => 0,
                'completionRate' => 0,
                'recentProjects' => collect(),
                'recentTasks' => collect(),
                'recentActivity' => collect(),
                'teamMembers' => [],
                'systemAlerts' => collect(),
                'projectProgressData' => [],
                'taskCompletionData' => [],
            ]);
        }
    }

    private function fetchDashboardData($tenantId)
    {
        // Mock data for now - replace with actual API calls
        return [
            'totalProjects' => 12,
            'projectsChange' => 8,
            'totalTasks' => 45,
            'tasksChange' => 15,
            'totalTeamMembers' => 8,
            'teamChange' => 2,
            'budgetUsed' => 125000,
            'budgetChange' => 5,
            'completionRate' => 87,
            'recentProjects' => collect([
                (object)['id' => 1, 'name' => 'Website Redesign', 'status' => 'active', 'progress' => 75],
                (object)['id' => 2, 'name' => 'Mobile App', 'status' => 'active', 'progress' => 45],
                (object)['id' => 3, 'name' => 'API Integration', 'status' => 'completed', 'progress' => 100],
            ]),
            'recentTasks' => collect([
                (object)['id' => 1, 'name' => 'Design mockups', 'status' => 'completed', 'priority' => 'high'],
                (object)['id' => 2, 'name' => 'Setup database', 'status' => 'in_progress', 'priority' => 'medium'],
                (object)['id' => 3, 'name' => 'Write documentation', 'status' => 'pending', 'priority' => 'low'],
            ]),
            'recentActivity' => collect([
                (object)['id' => 1, 'type' => 'project_created', 'message' => 'New project "Website Redesign" created', 'created_at' => now()->subHours(2)],
                (object)['id' => 2, 'type' => 'task_completed', 'message' => 'Task "Design mockups" completed', 'created_at' => now()->subHours(4)],
                (object)['id' => 3, 'type' => 'member_added', 'message' => 'John Doe joined the team', 'created_at' => now()->subHours(6)],
            ]),
            'teamMembers' => collect([
                (object)['id' => 1, 'name' => 'John Doe', 'role' => 'Project Manager', 'status' => 'online', 'last_login' => now()->subMinutes(5)],
                (object)['id' => 2, 'name' => 'Jane Smith', 'role' => 'Developer', 'status' => 'away', 'last_login' => now()->subHours(1)],
                (object)['id' => 3, 'name' => 'Bob Wilson', 'role' => 'Designer', 'status' => 'offline', 'last_login' => now()->subDays(1)],
            ]),
        ];
    }

    private function prepareChartData($dashboardData)
    {
        return [
            'projectProgress' => [
                'labels' => ['Planning', 'In Progress', 'Review', 'Completed'],
                'datasets' => [
                    [
                        'label' => 'Projects',
                        'data' => [2, 5, 3, 2],
                        'backgroundColor' => [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(34, 197, 94, 0.8)',
                        ],
                        'borderColor' => [
                            'rgba(59, 130, 246, 1)',
                            'rgba(16, 185, 129, 1)',
                            'rgba(245, 158, 11, 1)',
                            'rgba(34, 197, 94, 1)',
                        ],
                        'borderWidth' => 2,
                    ],
                ],
            ],
            'taskCompletion' => [
                'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'datasets' => [
                    [
                        'label' => 'Completed',
                        'data' => [12, 19, 8, 15, 22, 18, 25],
                        'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                        'borderColor' => 'rgba(16, 185, 129, 1)',
                        'borderWidth' => 2,
                        'fill' => true,
                    ],
                    [
                        'label' => 'Total',
                        'data' => [20, 25, 15, 22, 30, 25, 35],
                        'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                        'borderColor' => 'rgba(59, 130, 246, 1)',
                        'borderWidth' => 2,
                        'fill' => true,
                    ],
                ],
            ],
        ];
    }

    private function prepareTeamStatus($teamMembers)
    {
        return $teamMembers->map(function ($member) {
            return [
                'id' => $member->id,
                'name' => $member->name,
                'role' => $member->role,
                'status' => $member->status,
                'last_login' => $member->last_login,
                'status_color' => $this->getStatusColor($member->status),
            ];
        })->toArray();
    }

    private function prepareSystemAlerts($dashboardData)
    {
        $alerts = collect();
        
        // Add sample alerts
        if (($dashboardData['totalProjects'] ?? 0) > 10) {
            $alerts->push([
                'id' => 1,
                'type' => 'warning',
                'title' => 'High Project Count',
                'message' => 'You have many active projects. Consider reviewing priorities.',
            ]);
        }
        
        if (($dashboardData['completionRate'] ?? 0) < 80) {
            $alerts->push([
                'id' => 2,
                'type' => 'info',
                'title' => 'Completion Rate',
                'message' => 'Project completion rate is below target. Focus on finishing tasks.',
            ]);
        }
        
        return $alerts;
    }

    private function getStatusColor($status)
    {
        return match ($status) {
            'online' => 'green',
            'away' => 'yellow',
            'offline' => 'gray',
            default => 'gray',
        };
    }

    private function formatChange($change)
    {
        return $change > 0 ? "+{$change}%" : "{$change}%";
    }
}
