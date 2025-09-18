<?php

namespace App\Services;

use App\Models\User;
use App\Models\DashboardWidget;
use App\Models\UserDashboard;
use App\Models\Project;
use App\Models\Task;
use App\Models\RFI;
use App\Models\Inspection;
use App\Models\NCR;
use App\Models\ChangeRequest;
use App\Models\Submittal;
use App\Models\SiteDiary;
use App\Models\SafetyIncident;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class DashboardRoleBasedService
{
    protected $dataAggregationService;
    protected $customizationService;

    public function __construct(
        DashboardDataAggregationService $dataAggregationService,
        DashboardCustomizationService $customizationService
    ) {
        $this->dataAggregationService = $dataAggregationService;
        $this->customizationService = $customizationService;
    }

    /**
     * Get role-based dashboard configuration
     */
    public function getRoleBasedDashboard(User $user, ?string $projectId = null): array
    {
        try {
            $role = $user->role;
            $tenantId = $user->tenant_id;

            // Get role-specific configuration
            $roleConfig = $this->getRoleConfiguration($role);
            
            // Get user's current dashboard or create default
            $dashboard = $this->getOrCreateUserDashboard($user, $roleConfig);
            
            // Get role-specific widgets with data
            $widgets = $this->getRoleBasedWidgets($user, $roleConfig, $projectId);
            
            // Get role-specific metrics
            $metrics = $this->getRoleBasedMetrics($user, $projectId);
            
            // Get role-specific alerts
            $alerts = $this->getRoleBasedAlerts($user, $projectId);
            
            // Get role-specific permissions
            $permissions = $this->getRolePermissions($role);

            return [
                'dashboard' => $dashboard,
                'widgets' => $widgets,
                'metrics' => $metrics,
                'alerts' => $alerts,
                'permissions' => $permissions,
                'role_config' => $roleConfig,
                'project_context' => $this->getProjectContext($user, $projectId)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get role-based dashboard', [
                'user_id' => $user->id,
                'role' => $user->role,
                'project_id' => $projectId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get role-specific configuration
     */
    protected function getRoleConfiguration(string $role): array
    {
        $configurations = [
            'system_admin' => [
                'name' => 'System Administrator',
                'description' => 'Full system access and management',
                'default_widgets' => [
                    'system_health', 'user_management', 'tenant_overview',
                    'system_metrics', 'audit_logs', 'backup_status'
                ],
                'widget_categories' => ['system', 'management', 'monitoring'],
                'data_access' => 'all',
                'project_access' => 'all',
                'customization_level' => 'full',
                'priority_metrics' => ['system_uptime', 'user_count', 'storage_usage'],
                'alert_types' => ['system', 'security', 'performance'],
                'dashboard_layout' => 'admin_grid'
            ],
            'project_manager' => [
                'name' => 'Project Manager',
                'description' => 'Comprehensive project management and oversight',
                'default_widgets' => [
                    'project_overview', 'task_progress', 'rfi_status',
                    'budget_tracking', 'schedule_timeline', 'team_performance',
                    'quality_metrics', 'safety_summary', 'change_requests'
                ],
                'widget_categories' => ['overview', 'tasks', 'communication', 'quality', 'financial'],
                'data_access' => 'project_wide',
                'project_access' => 'assigned',
                'customization_level' => 'full',
                'priority_metrics' => ['project_progress', 'budget_variance', 'schedule_adherence'],
                'alert_types' => ['project', 'budget', 'schedule', 'quality'],
                'dashboard_layout' => 'manager_grid'
            ],
            'design_lead' => [
                'name' => 'Design Lead',
                'description' => 'Design coordination and technical oversight',
                'default_widgets' => [
                    'design_progress', 'drawing_status', 'submittal_tracking',
                    'design_reviews', 'technical_issues', 'coordination_log'
                ],
                'widget_categories' => ['design', 'communication', 'quality'],
                'data_access' => 'design_related',
                'project_access' => 'assigned',
                'customization_level' => 'limited',
                'priority_metrics' => ['design_completion', 'review_cycle_time', 'issue_resolution'],
                'alert_types' => ['design', 'review', 'coordination'],
                'dashboard_layout' => 'designer_grid'
            ],
            'site_engineer' => [
                'name' => 'Site Engineer',
                'description' => 'Field operations and site management',
                'default_widgets' => [
                    'daily_tasks', 'site_diary', 'inspection_checklist',
                    'weather_forecast', 'equipment_status', 'safety_alerts',
                    'progress_photos', 'manpower_tracking'
                ],
                'widget_categories' => ['tasks', 'quality', 'safety', 'field'],
                'data_access' => 'site_related',
                'project_access' => 'assigned',
                'customization_level' => 'limited',
                'priority_metrics' => ['daily_progress', 'safety_incidents', 'quality_issues'],
                'alert_types' => ['safety', 'quality', 'weather', 'equipment'],
                'dashboard_layout' => 'field_grid'
            ],
            'qc_inspector' => [
                'name' => 'QC Inspector',
                'description' => 'Quality control and inspection management',
                'default_widgets' => [
                    'inspection_schedule', 'ncr_tracking', 'quality_metrics',
                    'defect_analysis', 'corrective_actions', 'compliance_status',
                    'inspection_reports', 'quality_trends'
                ],
                'widget_categories' => ['quality', 'inspection', 'compliance'],
                'data_access' => 'quality_related',
                'project_access' => 'assigned',
                'customization_level' => 'read_only',
                'priority_metrics' => ['inspection_completion', 'defect_rate', 'ncr_resolution'],
                'alert_types' => ['quality', 'inspection', 'compliance'],
                'dashboard_layout' => 'qc_grid'
            ],
            'client_rep' => [
                'name' => 'Client Representative',
                'description' => 'Client communication and project oversight',
                'default_widgets' => [
                    'project_summary', 'progress_report', 'milestone_status',
                    'budget_summary', 'quality_summary', 'schedule_status',
                    'client_communications', 'approval_queue'
                ],
                'widget_categories' => ['overview', 'communication', 'reporting'],
                'data_access' => 'client_view',
                'project_access' => 'assigned',
                'customization_level' => 'read_only',
                'priority_metrics' => ['project_progress', 'budget_status', 'quality_score'],
                'alert_types' => ['milestone', 'budget', 'quality'],
                'dashboard_layout' => 'client_grid'
            ],
            'subcontractor_lead' => [
                'name' => 'Subcontractor Lead',
                'description' => 'Subcontractor coordination and management',
                'default_widgets' => [
                    'subcontractor_progress', 'payment_status', 'work_orders',
                    'quality_issues', 'safety_compliance', 'resource_allocation',
                    'performance_metrics', 'contract_status'
                ],
                'widget_categories' => ['subcontractor', 'financial', 'quality'],
                'data_access' => 'subcontractor_related',
                'project_access' => 'assigned',
                'customization_level' => 'limited',
                'priority_metrics' => ['work_completion', 'payment_status', 'quality_score'],
                'alert_types' => ['payment', 'quality', 'safety'],
                'dashboard_layout' => 'subcontractor_grid'
            ]
        ];

        return $configurations[$role] ?? $configurations['client_rep'];
    }

    /**
     * Get or create user dashboard based on role
     */
    protected function getOrCreateUserDashboard(User $user, array $roleConfig): array
    {
        $dashboard = UserDashboard::where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$dashboard) {
            // Create default dashboard for role
            $defaultLayout = $this->createDefaultLayoutForRole($user, $roleConfig);
            
            $dashboard = UserDashboard::create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $roleConfig['name'] . ' Dashboard',
                'layout' => json_encode($defaultLayout),
                'is_default' => true,
                'preferences' => json_encode($this->getDefaultPreferencesForRole($roleConfig))
            ]);
        }

        return [
            'id' => $dashboard->id,
            'name' => $dashboard->name,
            'layout' => json_decode($dashboard->layout, true) ?? [],
            'preferences' => json_decode($dashboard->preferences, true) ?? [],
            'is_default' => $dashboard->is_default,
            'created_at' => $dashboard->created_at,
            'updated_at' => $dashboard->updated_at
        ];
    }

    /**
     * Get role-based widgets with data
     */
    protected function getRoleBasedWidgets(User $user, array $roleConfig, ?string $projectId = null): array
    {
        $widgets = [];
        $availableWidgets = DashboardWidget::where('is_active', true)
            ->where('tenant_id', $user->tenant_id)
            ->get();

        foreach ($roleConfig['default_widgets'] as $widgetCode) {
            $widget = $availableWidgets->firstWhere('code', $widgetCode);
            if ($widget && $this->userCanAccessWidget($user, $widget)) {
                $widgetData = $this->getWidgetDataForRole($user, $widget, $projectId);
                $widgets[] = [
                    'widget' => $widget->toArray(),
                    'data' => $widgetData,
                    'permissions' => $this->getWidgetPermissions($user, $widget)
                ];
            }
        }

        return $widgets;
    }

    /**
     * Get widget data based on user role
     */
    protected function getWidgetDataForRole(User $user, DashboardWidget $widget, ?string $projectId = null): array
    {
        $role = $user->role;
        $tenantId = $user->tenant_id;

        try {
            switch ($widget->code) {
                case 'project_overview':
                    return $this->getProjectOverviewData($user, $projectId);
                
                case 'task_progress':
                    return $this->getTaskProgressData($user, $projectId);
                
                case 'rfi_status':
                    return $this->getRFIStatusData($user, $projectId);
                
                case 'budget_tracking':
                    return $this->getBudgetTrackingData($user, $projectId);
                
                case 'schedule_timeline':
                    return $this->getScheduleTimelineData($user, $projectId);
                
                case 'team_performance':
                    return $this->getTeamPerformanceData($user, $projectId);
                
                case 'quality_metrics':
                    return $this->getQualityMetricsData($user, $projectId);
                
                case 'safety_summary':
                    return $this->getSafetySummaryData($user, $projectId);
                
                case 'inspection_schedule':
                    return $this->getInspectionScheduleData($user, $projectId);
                
                case 'ncr_tracking':
                    return $this->getNCRTrackingData($user, $projectId);
                
                case 'system_health':
                    return $this->getSystemHealthData($user);
                
                case 'user_management':
                    return $this->getUserManagementData($user);
                
                default:
                    return $this->dataAggregationService->getWidgetData($widget->id, $user, $projectId);
            }
        } catch (\Exception $e) {
            Log::error('Failed to get widget data for role', [
                'user_id' => $user->id,
                'widget_code' => $widget->code,
                'error' => $e->getMessage()
            ]);
            return ['error' => 'Failed to load widget data'];
        }
    }

    /**
     * Get project overview data based on role
     */
    protected function getProjectOverviewData(User $user, ?string $projectId = null): array
    {
        $role = $user->role;
        $tenantId = $user->tenant_id;

        $query = Project::where('tenant_id', $tenantId);
        
        // Role-based project access
        if ($projectId) {
            $query->where('id', $projectId);
        } elseif ($role !== 'system_admin') {
            // Non-admin users only see assigned projects
            $query->whereHas('projectUsers', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $projects = $query->get();

        $overview = [
            'total_projects' => $projects->count(),
            'active_projects' => $projects->where('status', 'active')->count(),
            'completed_projects' => $projects->where('status', 'completed')->count(),
            'total_budget' => $projects->sum('budget'),
            'spent_budget' => $projects->sum('spent_amount'),
            'recent_projects' => $projects->take(5)->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'progress' => $project->progress_percentage,
                    'budget' => $project->budget,
                    'spent' => $project->spent_amount
                ];
            })
        ];

        // Add role-specific metrics
        switch ($role) {
            case 'project_manager':
                $overview['overdue_tasks'] = Task::whereIn('project_id', $projects->pluck('id'))
                    ->where('due_date', '<', now())
                    ->where('status', '!=', 'completed')
                    ->count();
                break;
            
            case 'site_engineer':
                $overview['daily_inspections'] = Inspection::whereIn('project_id', $projects->pluck('id'))
                    ->whereDate('scheduled_date', today())
                    ->count();
                break;
            
            case 'qc_inspector':
                $overview['pending_ncrs'] = NCR::whereIn('project_id', $projects->pluck('id'))
                    ->where('status', 'open')
                    ->count();
                break;
        }

        return $overview;
    }

    /**
     * Get task progress data based on role
     */
    protected function getTaskProgressData(User $user, ?string $projectId = null): array
    {
        $role = $user->role;
        $tenantId = $user->tenant_id;

        $query = Task::where('tenant_id', $tenantId);
        
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        // Role-based task access
        switch ($role) {
            case 'project_manager':
                // PM sees all tasks in assigned projects
                $query->whereHas('project.projectUsers', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
                break;
            
            case 'site_engineer':
                // Site engineer sees assigned tasks
                $query->where('assigned_to', $user->id);
                break;
            
            case 'design_lead':
                // Design lead sees design-related tasks
                $query->where('category', 'design');
                break;
            
            case 'qc_inspector':
                // QC inspector sees quality-related tasks
                $query->where('category', 'quality');
                break;
        }

        $tasks = $query->get();

        return [
            'total_tasks' => $tasks->count(),
            'completed_tasks' => $tasks->where('status', 'completed')->count(),
            'in_progress_tasks' => $tasks->where('status', 'in_progress')->count(),
            'pending_tasks' => $tasks->where('status', 'pending')->count(),
            'overdue_tasks' => $tasks->where('due_date', '<', now())
                ->where('status', '!=', 'completed')
                ->count(),
            'completion_rate' => $tasks->count() > 0 
                ? round(($tasks->where('status', 'completed')->count() / $tasks->count()) * 100, 2)
                : 0,
            'recent_tasks' => $tasks->sortByDesc('created_at')->take(10)->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => $task->due_date,
                    'assigned_to' => $task->assigned_to
                ];
            })
        ];
    }

    /**
     * Get RFI status data based on role
     */
    protected function getRFIStatusData(User $user, ?string $projectId = null): array
    {
        $role = $user->role;
        $tenantId = $user->tenant_id;

        $query = RFI::where('tenant_id', $tenantId);
        
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        // Role-based RFI access
        switch ($role) {
            case 'project_manager':
                $query->whereHas('project.projectUsers', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
                break;
            
            case 'design_lead':
                $query->where('discipline', 'design');
                break;
            
            case 'site_engineer':
                $query->where('discipline', 'construction');
                break;
        }

        $rfis = $query->get();

        return [
            'total_rfis' => $rfis->count(),
            'open_rfis' => $rfis->where('status', 'open')->count(),
            'answered_rfis' => $rfis->where('status', 'answered')->count(),
            'closed_rfis' => $rfis->where('status', 'closed')->count(),
            'overdue_rfis' => $rfis->where('due_date', '<', now())
                ->where('status', '!=', 'closed')
                ->count(),
            'average_response_time' => $this->calculateAverageResponseTime($rfis),
            'recent_rfis' => $rfis->sortByDesc('created_at')->take(10)->map(function ($rfi) {
                return [
                    'id' => $rfi->id,
                    'subject' => $rfi->subject,
                    'status' => $rfi->status,
                    'priority' => $rfi->priority,
                    'due_date' => $rfi->due_date,
                    'discipline' => $rfi->discipline
                ];
            })
        ];
    }

    /**
     * Get budget tracking data based on role
     */
    protected function getBudgetTrackingData(User $user, ?string $projectId = null): array
    {
        $role = $user->role;
        $tenantId = $user->tenant_id;

        $query = Project::where('tenant_id', $tenantId);
        
        if ($projectId) {
            $query->where('id', $projectId);
        } elseif ($role !== 'system_admin') {
            $query->whereHas('projectUsers', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $projects = $query->get();

        $totalBudget = $projects->sum('budget');
        $totalSpent = $projects->sum('spent_amount');
        $remainingBudget = $totalBudget - $totalSpent;

        return [
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'remaining_budget' => $remainingBudget,
            'budget_utilization' => $totalBudget > 0 
                ? round(($totalSpent / $totalBudget) * 100, 2)
                : 0,
            'budget_variance' => $this->calculateBudgetVariance($projects),
            'monthly_spending' => $this->getMonthlySpending($projects),
            'top_expense_categories' => $this->getTopExpenseCategories($projects),
            'budget_alerts' => $this->getBudgetAlerts($projects)
        ];
    }

    /**
     * Get role-based metrics
     */
    protected function getRoleBasedMetrics(User $user, ?string $projectId = null): array
    {
        $role = $user->role;
        $roleConfig = $this->getRoleConfiguration($role);
        
        $metrics = [];
        
        foreach ($roleConfig['priority_metrics'] as $metricCode) {
            $metric = DashboardMetric::where('code', $metricCode)
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', true)
                ->first();
            
            if ($metric) {
                $value = $this->getMetricValue($user, $metric, $projectId);
                $metrics[] = [
                    'metric' => $metric->toArray(),
                    'value' => $value,
                    'trend' => $this->getMetricTrend($user, $metric, $projectId),
                    'target' => $this->getMetricTarget($user, $metric, $projectId)
                ];
            }
        }
        
        return $metrics;
    }

    /**
     * Get role-based alerts
     */
    protected function getRoleBasedAlerts(User $user, ?string $projectId = null): array
    {
        $role = $user->role;
        $roleConfig = $this->getRoleConfiguration($role);
        
        $alerts = DashboardAlert::where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->where('is_read', false)
            ->whereIn('type', $roleConfig['alert_types'])
            ->when($projectId, function ($query) use ($projectId) {
                $query->where('context->project_id', $projectId);
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
        
        return $alerts->map(function ($alert) {
            return [
                'id' => $alert->id,
                'type' => $alert->type,
                'severity' => $alert->severity,
                'message' => $alert->message,
                'context' => $alert->context,
                'triggered_at' => $alert->triggered_at,
                'widget_id' => $alert->widget_id,
                'metric_id' => $alert->metric_id
            ];
        })->toArray();
    }

    /**
     * Get role permissions
     */
    protected function getRolePermissions(string $role): array
    {
        $permissions = [
            'system_admin' => [
                'dashboard' => ['view', 'edit', 'delete', 'share'],
                'widgets' => ['view', 'add', 'edit', 'delete', 'configure'],
                'projects' => ['view_all', 'edit_all', 'delete_all'],
                'users' => ['view_all', 'edit_all', 'delete_all'],
                'reports' => ['view_all', 'export_all'],
                'settings' => ['view_all', 'edit_all']
            ],
            'project_manager' => [
                'dashboard' => ['view', 'edit', 'share'],
                'widgets' => ['view', 'add', 'edit', 'configure'],
                'projects' => ['view_assigned', 'edit_assigned'],
                'users' => ['view_team', 'edit_team'],
                'reports' => ['view_assigned', 'export_assigned'],
                'settings' => ['view_project', 'edit_project']
            ],
            'design_lead' => [
                'dashboard' => ['view', 'edit'],
                'widgets' => ['view', 'add', 'edit', 'configure'],
                'projects' => ['view_assigned', 'edit_design'],
                'users' => ['view_team'],
                'reports' => ['view_design', 'export_design'],
                'settings' => ['view_design']
            ],
            'site_engineer' => [
                'dashboard' => ['view', 'edit'],
                'widgets' => ['view', 'add', 'edit', 'configure'],
                'projects' => ['view_assigned', 'edit_field'],
                'users' => ['view_team'],
                'reports' => ['view_field', 'export_field'],
                'settings' => ['view_field']
            ],
            'qc_inspector' => [
                'dashboard' => ['view'],
                'widgets' => ['view', 'configure'],
                'projects' => ['view_assigned'],
                'users' => ['view_team'],
                'reports' => ['view_quality', 'export_quality'],
                'settings' => ['view_quality']
            ],
            'client_rep' => [
                'dashboard' => ['view'],
                'widgets' => ['view'],
                'projects' => ['view_assigned'],
                'users' => ['view_team'],
                'reports' => ['view_client', 'export_client'],
                'settings' => ['view_client']
            ],
            'subcontractor_lead' => [
                'dashboard' => ['view', 'edit'],
                'widgets' => ['view', 'add', 'edit', 'configure'],
                'projects' => ['view_assigned', 'edit_subcontractor'],
                'users' => ['view_team'],
                'reports' => ['view_subcontractor', 'export_subcontractor'],
                'settings' => ['view_subcontractor']
            ]
        ];

        return $permissions[$role] ?? $permissions['client_rep'];
    }

    /**
     * Check if user can access widget
     */
    protected function userCanAccessWidget(User $user, DashboardWidget $widget): bool
    {
        $permissions = json_decode($widget->permissions, true) ?? [];
        
        if (empty($permissions)) {
            return true; // No restrictions
        }

        return in_array($user->role, $permissions);
    }

    /**
     * Get widget permissions for user
     */
    protected function getWidgetPermissions(User $user, DashboardWidget $widget): array
    {
        $rolePermissions = $this->getRolePermissions($user->role);
        $widgetPermissions = json_decode($widget->permissions, true) ?? [];
        
        return [
            'can_view' => $this->userCanAccessWidget($user, $widget),
            'can_edit' => in_array('edit', $rolePermissions['widgets']),
            'can_delete' => in_array('delete', $rolePermissions['widgets']),
            'can_configure' => in_array('configure', $rolePermissions['widgets']),
            'can_share' => in_array('share', $rolePermissions['dashboard'])
        ];
    }

    /**
     * Create default layout for role
     */
    protected function createDefaultLayoutForRole(User $user, array $roleConfig): array
    {
        $layout = [];
        $y = 0;

        foreach ($roleConfig['default_widgets'] as $index => $widgetCode) {
            $widget = DashboardWidget::where('code', $widgetCode)
                ->where('tenant_id', $user->tenant_id)
                ->where('is_active', true)
                ->first();

            if ($widget && $this->userCanAccessWidget($user, $widget)) {
                $layout[] = [
                    'id' => \Str::ulid(),
                    'widget_id' => $widget->id,
                    'type' => $widget->type,
                    'title' => $widget->name,
                    'size' => $this->getDefaultSizeForRole($user->role, $widgetCode),
                    'position' => ['x' => 0, 'y' => $y],
                    'config' => $widget->config ?? [],
                    'is_customizable' => $this->isWidgetCustomizableForRole($user->role),
                    'created_at' => now()->toISOString()
                ];
                
                $size = $this->getDefaultSizeForRole($user->role, $widgetCode);
                $sizeMap = [
                    'small' => 2,
                    'medium' => 4,
                    'large' => 6,
                    'extra-large' => 8
                ];
                
                $y += $sizeMap[$size] ?? 4;
            }
        }

        return $layout;
    }

    /**
     * Get default size for widget based on role
     */
    protected function getDefaultSizeForRole(string $role, string $widgetCode): string
    {
        $sizeMap = [
            'system_admin' => [
                'system_health' => 'large',
                'user_management' => 'medium',
                'tenant_overview' => 'extra-large'
            ],
            'project_manager' => [
                'project_overview' => 'large',
                'task_progress' => 'medium',
                'budget_tracking' => 'medium',
                'schedule_timeline' => 'large'
            ],
            'site_engineer' => [
                'daily_tasks' => 'large',
                'site_diary' => 'medium',
                'inspection_checklist' => 'medium'
            ],
            'qc_inspector' => [
                'inspection_schedule' => 'large',
                'ncr_tracking' => 'medium',
                'quality_metrics' => 'medium'
            ]
        ];

        return $sizeMap[$role][$widgetCode] ?? 'medium';
    }

    /**
     * Check if widget is customizable for role
     */
    protected function isWidgetCustomizableForRole(string $role): bool
    {
        $customizableRoles = ['system_admin', 'project_manager', 'design_lead', 'site_engineer'];
        return in_array($role, $customizableRoles);
    }

    /**
     * Get default preferences for role
     */
    protected function getDefaultPreferencesForRole(array $roleConfig): array
    {
        $basePreferences = [
            'theme' => 'light',
            'refresh_interval' => 300,
            'compact_mode' => false,
            'show_widget_borders' => true,
            'enable_animations' => true
        ];

        // Role-specific preferences
        switch ($roleConfig['name']) {
            case 'System Administrator':
                $basePreferences['theme'] = 'dark';
                $basePreferences['refresh_interval'] = 60;
                break;
            
            case 'Site Engineer':
                $basePreferences['compact_mode'] = true;
                $basePreferences['refresh_interval'] = 180;
                break;
            
            case 'QC Inspector':
                $basePreferences['show_widget_borders'] = false;
                break;
        }

        return $basePreferences;
    }

    /**
     * Get project context for user
     */
    protected function getProjectContext(User $user, ?string $projectId = null): array
    {
        if (!$projectId) {
            return ['current_project' => null, 'available_projects' => []];
        }

        $project = Project::where('id', $projectId)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$project) {
            return ['current_project' => null, 'available_projects' => []];
        }

        $availableProjects = Project::where('tenant_id', $user->tenant_id)
            ->whereHas('projectUsers', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'status' => $p->status
                ];
            });

        return [
            'current_project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'progress' => $project->progress_percentage,
                'budget' => $project->budget,
                'start_date' => $project->start_date,
                'end_date' => $project->end_date
            ],
            'available_projects' => $availableProjects
        ];
    }

    /**
     * Helper methods for data calculations
     */
    protected function calculateAverageResponseTime(Collection $rfis): float
    {
        $responseTimes = $rfis->where('status', 'closed')
            ->map(function ($rfi) {
                return $rfi->answered_at ? 
                    $rfi->answered_at->diffInHours($rfi->created_at) : 0;
            })
            ->filter()
            ->values();

        return $responseTimes->count() > 0 ? $responseTimes->avg() : 0;
    }

    protected function calculateBudgetVariance(Collection $projects): array
    {
        $variances = $projects->map(function ($project) {
            $planned = $project->budget;
            $actual = $project->spent_amount;
            $variance = $actual - $planned;
            $variancePercentage = $planned > 0 ? ($variance / $planned) * 100 : 0;
            
            return [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'planned' => $planned,
                'actual' => $actual,
                'variance' => $variance,
                'variance_percentage' => $variancePercentage
            ];
        });

        return [
            'total_variance' => $variances->sum('variance'),
            'average_variance_percentage' => $variances->avg('variance_percentage'),
            'projects_over_budget' => $variances->where('variance', '>', 0)->count(),
            'projects_under_budget' => $variances->where('variance', '<', 0)->count()
        ];
    }

    protected function getMonthlySpending(Collection $projects): array
    {
        // Implementation for monthly spending data
        return [
            'current_month' => 0,
            'previous_month' => 0,
            'monthly_trend' => 'stable'
        ];
    }

    protected function getTopExpenseCategories(Collection $projects): array
    {
        // Implementation for top expense categories
        return [
            ['category' => 'Labor', 'amount' => 0, 'percentage' => 0],
            ['category' => 'Materials', 'amount' => 0, 'percentage' => 0],
            ['category' => 'Equipment', 'amount' => 0, 'percentage' => 0]
        ];
    }

    protected function getBudgetAlerts(Collection $projects): array
    {
        $alerts = [];
        
        foreach ($projects as $project) {
            $utilization = $project->budget > 0 ? 
                ($project->spent_amount / $project->budget) * 100 : 0;
            
            if ($utilization > 90) {
                $alerts[] = [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'type' => 'budget_exceeded',
                    'message' => 'Budget utilization exceeds 90%',
                    'severity' => 'high'
                ];
            } elseif ($utilization > 80) {
                $alerts[] = [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'type' => 'budget_warning',
                    'message' => 'Budget utilization exceeds 80%',
                    'severity' => 'medium'
                ];
            }
        }
        
        return $alerts;
    }

    protected function getScheduleTimelineData(User $user, ?string $projectId = null): array
    {
        // Implementation for schedule timeline data
        return [
            'milestones' => [],
            'critical_path' => [],
            'schedule_variance' => 0
        ];
    }

    protected function getTeamPerformanceData(User $user, ?string $projectId = null): array
    {
        // Implementation for team performance data
        return [
            'team_members' => [],
            'performance_metrics' => [],
            'productivity_trend' => 'stable'
        ];
    }

    protected function getQualityMetricsData(User $user, ?string $projectId = null): array
    {
        // Implementation for quality metrics data
        return [
            'quality_score' => 0,
            'defect_rate' => 0,
            'inspection_completion' => 0
        ];
    }

    protected function getSafetySummaryData(User $user, ?string $projectId = null): array
    {
        // Implementation for safety summary data
        return [
            'safety_score' => 0,
            'incidents_count' => 0,
            'days_since_last_incident' => 0
        ];
    }

    protected function getInspectionScheduleData(User $user, ?string $projectId = null): array
    {
        // Implementation for inspection schedule data
        return [
            'scheduled_inspections' => [],
            'completed_inspections' => [],
            'overdue_inspections' => []
        ];
    }

    protected function getNCRTrackingData(User $user, ?string $projectId = null): array
    {
        // Implementation for NCR tracking data
        return [
            'open_ncrs' => [],
            'closed_ncrs' => [],
            'average_resolution_time' => 0
        ];
    }

    protected function getSystemHealthData(User $user): array
    {
        // Implementation for system health data
        return [
            'system_uptime' => 99.9,
            'active_users' => 0,
            'storage_usage' => 0
        ];
    }

    protected function getUserManagementData(User $user): array
    {
        // Implementation for user management data
        return [
            'total_users' => 0,
            'active_users' => 0,
            'new_users_this_month' => 0
        ];
    }

    protected function getMetricValue(User $user, DashboardMetric $metric, ?string $projectId = null): float
    {
        // Implementation for metric value calculation
        return 0.0;
    }

    protected function getMetricTrend(User $user, DashboardMetric $metric, ?string $projectId = null): string
    {
        // Implementation for metric trend calculation
        return 'stable';
    }

    protected function getMetricTarget(User $user, DashboardMetric $metric, ?string $projectId = null): float
    {
        // Implementation for metric target calculation
        return 0.0;
    }
}
