<?php

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard metrics - cached for 60 seconds
     * GET /api/v1/app/dashboard/metrics
     */
    public function metrics(Request $request)
    {
        $user = Auth::user();
        
        // Simple test data first
        return response()->json([
            'kpis' => [
                'total_users' => [
                    'value' => 12,
                    'label' => 'Active Users',
                    'url' => '/app/team/users?filter=active',
                    'icon' => 'fas fa-users',
                    'color' => 'blue'
                ],
                'active_projects' => [
                    'value' => 8,
                    'label' => 'Active Projects',
                    'url' => '/app/projects?filter=active',
                    'icon' => 'fas fa-project-diagram',
                    'color' => 'green'
                ],
                'total_tasks' => [
                    'completed' => 45,
                    'pending' => 23,
                    'label' => 'Tasks',
                    'url' => '/app/tasks',
                    'icon' => 'fas fa-tasks',
                    'color' => 'purple'
                ],
                'documents' => [
                    'value' => 156,
                    'label' => 'Documents This Week',
                    'url' => '/app/documents?filter=this_week',
                    'icon' => 'fas fa-file-alt',
                    'color' => 'orange'
                ]
            ],
            'alerts' => [
                [
                    'id' => 'overdue_tasks',
                    'type' => 'critical',
                    'title' => 'Overdue Tasks',
                    'message' => 'You have 5 overdue tasks',
                    'cta' => [
                        'text' => 'View Tasks',
                        'url' => '/app/tasks?filter=overdue',
                        'action' => 'resolve'
                    ],
                    'dismissible' => false
                ],
                [
                    'id' => 'urgent_projects',
                    'type' => 'warning',
                    'title' => 'Urgent Projects',
                    'message' => '3 projects due within 3 days',
                    'cta' => [
                        'text' => 'Review Projects',
                        'url' => '/app/projects?filter=urgent',
                        'action' => 'acknowledge'
                    ],
                    'dismissible' => true
                ]
            ],
            'now_panel' => [
                [
                    'id' => 'review_proposals',
                    'title' => 'Review Project Proposals',
                    'description' => '3 new proposals waiting for review',
                    'priority' => 'high',
                    'cta' => [
                        'text' => 'Review Now',
                        'url' => '/app/projects?filter=pending_review',
                        'action' => 'review'
                    ]
                ],
                [
                    'id' => 'update_status',
                    'title' => 'Update Task Status',
                    'description' => 'Mark completed tasks as done',
                    'priority' => 'medium',
                    'cta' => [
                        'text' => 'Update Status',
                        'url' => '/app/tasks?filter=my_tasks',
                        'action' => 'update'
                    ]
                ]
            ],
            'work_queue' => [
                'my_work' => [
                    'tasks' => [
                        [
                            'id' => 1,
                            'title' => 'Design Review',
                            'project' => 'Project Alpha',
                            'priority' => 'high',
                            'due_date' => '2025-09-25',
                            'status' => 'in_progress',
                            'focus_time' => 0
                        ],
                        [
                            'id' => 2,
                            'title' => 'Code Implementation',
                            'project' => 'Project Beta',
                            'priority' => 'medium',
                            'due_date' => '2025-09-28',
                            'status' => 'pending',
                            'focus_time' => 0
                        ]
                    ],
                    'total' => 2
                ],
                'team_work' => [
                    'tasks' => [
                        [
                            'id' => 3,
                            'title' => 'Testing Phase',
                            'assignee' => 'John Doe',
                            'project' => 'Project Gamma',
                            'priority' => 'high',
                            'due_date' => '2025-09-26',
                            'status' => 'in_progress'
                        ]
                    ],
                    'total' => 1
                ]
            ],
            'insights' => [
                [
                    'title' => 'Task Completion Trend',
                    'type' => 'line',
                    'data' => [
                        ['date' => 'Sep 15', 'value' => 5],
                        ['date' => 'Sep 16', 'value' => 8],
                        ['date' => 'Sep 17', 'value' => 12],
                        ['date' => 'Sep 18', 'value' => 15],
                        ['date' => 'Sep 19', 'value' => 18],
                        ['date' => 'Sep 20', 'value' => 22],
                        ['date' => 'Sep 21', 'value' => 25]
                    ],
                    'url' => '/app/reports/task-completion'
                ],
                [
                    'title' => 'Project Status',
                    'type' => 'doughnut',
                    'data' => [
                        ['label' => 'Active', 'value' => 8],
                        ['label' => 'Completed', 'value' => 12],
                        ['label' => 'On Hold', 'value' => 3]
                    ],
                    'url' => '/app/reports/project-status'
                ]
            ],
            'activity' => [
                [
                    'id' => 1,
                    'type' => 'task',
                    'description' => 'Task "Design Review" completed',
                    'user' => 'John Doe',
                    'created_at' => '2025-09-22T14:30:00Z',
                    'metadata' => []
                ],
                [
                    'id' => 2,
                    'type' => 'document',
                    'description' => 'New document uploaded: "Project Plan.pdf"',
                    'user' => 'Jane Smith',
                    'created_at' => '2025-09-22T13:30:00Z',
                    'metadata' => []
                ]
            ],
            'shortcuts' => [
                [
                    'title' => 'New Project',
                    'url' => '/app/projects/create',
                    'icon' => 'fas fa-plus',
                    'color' => 'green'
                ],
                [
                    'title' => 'New Task',
                    'url' => '/app/tasks/create',
                    'icon' => 'fas fa-tasks',
                    'color' => 'blue'
                ],
                [
                    'title' => 'Upload Document',
                    'url' => '/app/documents/upload',
                    'icon' => 'fas fa-upload',
                    'color' => 'purple'
                ],
                [
                    'title' => 'Team Chat',
                    'url' => '/app/team/chat',
                    'icon' => 'fas fa-comments',
                    'color' => 'orange'
                ]
            ],
            'focus_mode' => [
                'is_active' => false,
                'current_task' => null,
                'started_at' => null,
                'focus_time_today' => 0
            ],
            'generated_at' => now()->toISOString(),
            'cache_ttl' => 60
        ]);
    }

    /**
     * Get 4 mandatory KPIs
     */
    private function getKPIs($user)
    {
        $tenantId = $user->tenant_id;
        
        return [
            'total_users' => [
                'value' => \App\Models\User::where('tenant_id', $tenantId)->where('is_active', true)->count(),
                'label' => 'Active Users',
                'url' => '/app/team/users?filter=active',
                'icon' => 'fas fa-users',
                'color' => 'blue'
            ],
            'active_projects' => [
                'value' => \App\Models\Project::where('tenant_id', $tenantId)->whereIn('status', ['active', 'in_progress'])->count(),
                'label' => 'Active Projects',
                'url' => '/app/projects?filter=active',
                'icon' => 'fas fa-project-diagram',
                'color' => 'green'
            ],
            'total_tasks' => [
                'completed' => \App\Models\Task::where('tenant_id', $tenantId)->where('status', 'completed')->count(),
                'pending' => \App\Models\Task::where('tenant_id', $tenantId)->whereIn('status', ['pending', 'in_progress'])->count(),
                'label' => 'Tasks',
                'url' => '/app/tasks',
                'icon' => 'fas fa-tasks',
                'color' => 'purple'
            ],
            'documents' => [
                'value' => \App\Models\Document::where('tenant_id', $tenantId)->where('created_at', '>=', Carbon::now()->subWeek())->count(),
                'label' => 'Documents This Week',
                'url' => '/app/documents?filter=this_week',
                'icon' => 'fas fa-file-alt',
                'color' => 'orange'
            ]
        ];
    }

    /**
     * Get critical alerts (max 3)
     */
    private function getCriticalAlerts($user)
    {
        $alerts = [];
        
        // Check for overdue tasks (using created_at as proxy for due_date)
        $overdueTasks = \App\Models\Task::where('tenant_id', $user->tenant_id)
            ->where('created_at', '<', now()->subDays(7))
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();
            
        if ($overdueTasks > 0) {
            $alerts[] = [
                'id' => 'overdue_tasks',
                'type' => 'critical',
                'title' => 'Overdue Tasks',
                'message' => "You have {$overdueTasks} overdue tasks",
                'cta' => [
                    'text' => 'View Tasks',
                    'url' => '/app/tasks?filter=overdue',
                    'action' => 'resolve'
                ],
                'dismissible' => false
            ];
        }
        
        // Check for project deadlines (using created_at as proxy)
        $urgentProjects = \App\Models\Project::where('tenant_id', $user->tenant_id)
            ->where('created_at', '<=', Carbon::now()->subDays(30))
            ->whereIn('status', ['active', 'in_progress'])
            ->count();
            
        if ($urgentProjects > 0) {
            $alerts[] = [
                'id' => 'urgent_projects',
                'type' => 'warning',
                'title' => 'Urgent Projects',
                'message' => "{$urgentProjects} projects due within 3 days",
                'cta' => [
                    'text' => 'Review Projects',
                    'url' => '/app/projects?filter=urgent',
                    'action' => 'acknowledge'
                ],
                'dismissible' => true
            ];
        }
        
        // Check for system maintenance
        $alerts[] = [
            'id' => 'system_maintenance',
            'type' => 'info',
            'title' => 'System Maintenance',
            'message' => 'Scheduled maintenance on Sunday 2:00 AM - 4:00 AM',
            'cta' => [
                'text' => 'Learn More',
                'url' => '/app/settings/maintenance',
                'action' => 'acknowledge'
            ],
            'dismissible' => true
        ];
        
        return array_slice($alerts, 0, 3); // Max 3 alerts
    }

    /**
     * Get Now Panel tasks (3-5 items based on role)
     */
    private function getNowPanelTasks($user)
    {
        $tasks = [];
        
        // Role-based tasks
        if ($user->hasRole('project_manager')) {
            $tasks[] = [
                'id' => 'review_proposals',
                'title' => 'Review Project Proposals',
                'description' => '3 new proposals waiting for review',
                'priority' => 'high',
                'cta' => [
                    'text' => 'Review Now',
                    'url' => '/app/projects?filter=pending_review',
                    'action' => 'review'
                ]
            ];
        }
        
        if ($user->hasRole('designer')) {
            $tasks[] = [
                'id' => 'design_review',
                'title' => 'Complete Design Review',
                'description' => 'Design review for Project Alpha due today',
                'priority' => 'high',
                'cta' => [
                    'text' => 'Start Review',
                    'url' => '/app/projects/alpha/design',
                    'action' => 'start'
                ]
            ];
        }
        
        // Common tasks for all users
        $tasks[] = [
            'id' => 'update_status',
            'title' => 'Update Task Status',
            'description' => 'Mark completed tasks as done',
            'priority' => 'medium',
            'cta' => [
                'text' => 'Update Status',
                'url' => '/app/tasks?filter=my_tasks',
                'action' => 'update'
            ]
        ];
        
        $tasks[] = [
            'id' => 'upload_documents',
            'title' => 'Upload Project Documents',
            'description' => 'Upload latest project documentation',
            'priority' => 'medium',
            'cta' => [
                'text' => 'Upload Now',
                'url' => '/app/documents/upload',
                'action' => 'upload'
            ]
        ];
        
        return array_slice($tasks, 0, 5); // Max 5 tasks
    }

    /**
     * Get Work Queue (My Work / Team)
     */
    private function getWorkQueue($user)
    {
        $tenantId = $user->tenant_id;
        
        return [
            'my_work' => [
                'tasks' => \App\Models\Task::where('tenant_id', $tenantId)
                    ->where('assigned_to', $user->id)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->orderBy('priority', 'desc')
                    ->orderBy('created_at', 'asc')
                    ->limit(10)
                    ->get()
                    ->map(function ($task) {
                        return [
                            'id' => $task->id,
                            'title' => $task->title,
                            'project' => $task->project->name ?? 'No Project',
                            'priority' => $task->priority,
                            'due_date' => $task->created_at,
                            'status' => $task->status,
                            'focus_time' => $task->focus_time ?? 0
                        ];
                    }),
                'total' => \App\Models\Task::where('tenant_id', $tenantId)
                    ->where('assigned_to', $user->id)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->count()
            ],
            'team_work' => [
                'tasks' => \App\Models\Task::where('tenant_id', $tenantId)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->orderBy('priority', 'desc')
                    ->orderBy('created_at', 'asc')
                    ->limit(10)
                    ->get()
                    ->map(function ($task) {
                        return [
                            'id' => $task->id,
                            'title' => $task->title,
                            'assignee' => $task->assignee->name ?? 'Unassigned',
                            'project' => $task->project->name ?? 'No Project',
                            'priority' => $task->priority,
                            'due_date' => $task->created_at,
                            'status' => $task->status
                        ];
                    }),
                'total' => \App\Models\Task::where('tenant_id', $tenantId)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->count()
            ]
        ];
    }

    /**
     * Get Insights (2-4 mini charts)
     */
    private function getInsights($user)
    {
        $tenantId = $user->tenant_id;
        
        return [
            'task_completion_trend' => [
                'title' => 'Task Completion Trend',
                'type' => 'line',
                'data' => $this->getTaskCompletionTrend($tenantId),
                'url' => '/app/reports/task-completion'
            ],
            'project_status_distribution' => [
                'title' => 'Project Status',
                'type' => 'doughnut',
                'data' => $this->getProjectStatusDistribution($tenantId),
                'url' => '/app/reports/project-status'
            ],
            'team_productivity' => [
                'title' => 'Team Productivity',
                'type' => 'bar',
                'data' => $this->getTeamProductivity($tenantId),
                'url' => '/app/reports/team-productivity'
            ]
        ];
    }

    /**
     * Get Recent Activity (10 records)
     */
    private function getRecentActivity($user)
    {
        $tenantId = $user->tenant_id;
        
        return \App\Models\Activity::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'description' => $activity->description,
                    'user' => $activity->user->name ?? 'System',
                    'created_at' => $activity->created_at,
                    'metadata' => $activity->metadata
                ];
            });
    }

    /**
     * Get Shortcuts (max 8)
     */
    private function getShortcuts($user)
    {
        $shortcuts = [
            [
                'title' => 'New Project',
                'url' => '/app/projects/create',
                'icon' => 'fas fa-plus',
                'color' => 'green'
            ],
            [
                'title' => 'New Task',
                'url' => '/app/tasks/create',
                'icon' => 'fas fa-tasks',
                'color' => 'blue'
            ],
            [
                'title' => 'Upload Document',
                'url' => '/app/documents/upload',
                'icon' => 'fas fa-upload',
                'color' => 'purple'
            ],
            [
                'title' => 'Team Chat',
                'url' => '/app/team/chat',
                'icon' => 'fas fa-comments',
                'color' => 'orange'
            ]
        ];
        
        // Add role-specific shortcuts
        if ($user->hasRole('project_manager')) {
            $shortcuts[] = [
                'title' => 'Project Reports',
                'url' => '/app/reports/projects',
                'icon' => 'fas fa-chart-bar',
                'color' => 'indigo'
            ];
        }
        
        return array_slice($shortcuts, 0, 8); // Max 8 shortcuts
    }

    /**
     * Get Focus Mode status
     */
    private function getFocusMode($user)
    {
        $focusSession = \App\Models\FocusSession::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
            
        return [
            'is_active' => $focusSession ? true : false,
            'current_task' => $focusSession ? $focusSession->task : null,
            'started_at' => $focusSession ? $focusSession->started_at : null,
            'focus_time_today' => $this->getFocusTimeToday($user->id)
        ];
    }

    // Helper methods for insights
    private function getTaskCompletionTrend($tenantId)
    {
        // Return last 7 days data
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $completed = \App\Models\Task::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereDate('updated_at', $date)
                ->count();
            $data[] = ['date' => $date->format('M d'), 'value' => $completed];
        }
        return $data;
    }

    private function getProjectStatusDistribution($tenantId)
    {
        $statuses = ['planning', 'active', 'in_progress', 'completed', 'on_hold'];
        $data = [];
        
        foreach ($statuses as $status) {
            $count = \App\Models\Project::where('tenant_id', $tenantId)
                ->where('status', $status)
                ->count();
            $data[] = ['label' => ucfirst($status), 'value' => $count];
        }
        
        return $data;
    }

    private function getTeamProductivity($tenantId)
    {
        return \App\Models\User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->limit(5)
            ->get()
            ->map(function ($user) {
                $completedTasks = \App\Models\Task::where('assigned_to', $user->id)
                    ->where('status', 'completed')
                    ->where('updated_at', '>=', Carbon::now()->subWeek())
                    ->count();
                    
                return [
                    'name' => $user->name,
                    'completed_tasks' => $completedTasks
                ];
            });
    }

    private function getFocusTimeToday($userId)
    {
        return \App\Models\FocusSession::where('user_id', $userId)
            ->whereDate('started_at', Carbon::today())
            ->sum('duration');
    }
}
