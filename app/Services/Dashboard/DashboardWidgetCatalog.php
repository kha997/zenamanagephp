<?php

namespace App\Services\Dashboard;

/**
 * @phpstan-type RoleConfiguration array{
 *     name: string,
 *     description: string,
 *     default_widgets: list<string>,
 *     widget_categories: list<string>,
 *     data_access: string,
 *     project_access: string,
 *     customization_level: string,
 *     priority_metrics: list<string>,
 *     alert_types: list<string>,
 *     dashboard_layout: string
 * }
 */
class DashboardWidgetCatalog
{
    /** @var list<string> */
    private const PROVIDER_CODES = [
        'project_overview', 'task_progress', 'rfi_status', 'budget_tracking',
        'schedule_timeline', 'team_performance', 'quality_metrics', 'safety_summary',
        'inspection_schedule', 'ncr_tracking', 'system_health', 'user_management',
    ];

    public function __construct(private readonly DashboardRoleValidator $roleValidator = new DashboardRoleValidator())
    {
    }

    /** @return list<string> */
    public function roles(): array
    {
        return DashboardRoleValidator::ROLES;
    }

    /** @return list<string> */
    public function forRole(string $role): array
    {
        return $this->configurationForRole($role)['default_widgets'];
    }

    /** @return RoleConfiguration */
    public function configurationForRole(string $role): array
    {
        $this->roleValidator->assertSupported($role);

        return self::configurations()[$role];
    }

    /** @return array<string, 'supported'|'unsupported'> */
    public function capabilitiesForRole(string $role): array
    {
        $widgets = $this->forRole($role);
        $capabilities = [];

        foreach ($widgets as $widgetCode) {
            $capabilities[$widgetCode] = in_array($widgetCode, self::PROVIDER_CODES, true)
                ? 'supported'
                : 'unsupported';
        }

        return $capabilities;
    }

    /** @return list<string> */
    public function providerCodes(): array
    {
        return self::PROVIDER_CODES;
    }

    /** @return array<string, RoleConfiguration> */
    private static function configurations(): array
    {
        return [
            'system_admin' => [
                'name' => 'System Administrator', 'description' => 'Full system access and management',
                'default_widgets' => ['system_health', 'user_management', 'tenant_overview', 'system_metrics', 'audit_logs', 'backup_status'],
                'widget_categories' => ['system', 'management', 'monitoring'], 'data_access' => 'all', 'project_access' => 'all',
                'customization_level' => 'full', 'priority_metrics' => ['system_uptime', 'user_count', 'storage_usage'],
                'alert_types' => ['system', 'security', 'performance'], 'dashboard_layout' => 'admin_grid',
            ],
            'project_manager' => [
                'name' => 'Project Manager', 'description' => 'Comprehensive project management and oversight',
                'default_widgets' => ['project_overview', 'task_progress', 'rfi_status', 'budget_tracking', 'schedule_timeline', 'team_performance', 'quality_metrics', 'safety_summary', 'change_requests'],
                'widget_categories' => ['overview', 'tasks', 'communication', 'quality', 'financial'], 'data_access' => 'project_wide', 'project_access' => 'assigned',
                'customization_level' => 'full', 'priority_metrics' => ['project_progress', 'budget_variance', 'schedule_adherence'],
                'alert_types' => ['project', 'budget', 'schedule', 'quality'], 'dashboard_layout' => 'manager_grid',
            ],
            'design_lead' => [
                'name' => 'Design Lead', 'description' => 'Design coordination and technical oversight',
                'default_widgets' => ['design_progress', 'drawing_status', 'submittal_tracking', 'design_reviews', 'technical_issues', 'coordination_log'],
                'widget_categories' => ['design', 'communication', 'quality'], 'data_access' => 'design_related', 'project_access' => 'assigned',
                'customization_level' => 'limited', 'priority_metrics' => ['design_completion', 'review_cycle_time', 'issue_resolution'],
                'alert_types' => ['design', 'review', 'coordination'], 'dashboard_layout' => 'designer_grid',
            ],
            'site_engineer' => [
                'name' => 'Site Engineer', 'description' => 'Field operations and site management',
                'default_widgets' => ['daily_tasks', 'site_diary', 'inspection_checklist', 'weather_forecast', 'equipment_status', 'safety_alerts', 'progress_photos', 'manpower_tracking'],
                'widget_categories' => ['tasks', 'quality', 'safety', 'field'], 'data_access' => 'site_related', 'project_access' => 'assigned',
                'customization_level' => 'limited', 'priority_metrics' => ['daily_progress', 'safety_incidents', 'quality_issues'],
                'alert_types' => ['safety', 'quality', 'weather', 'equipment'], 'dashboard_layout' => 'field_grid',
            ],
            'qc_inspector' => [
                'name' => 'QC Inspector', 'description' => 'Quality control and inspection management',
                'default_widgets' => ['inspection_schedule', 'ncr_tracking', 'quality_metrics', 'defect_analysis', 'corrective_actions', 'compliance_status', 'inspection_reports', 'quality_trends'],
                'widget_categories' => ['quality', 'inspection', 'compliance'], 'data_access' => 'quality_related', 'project_access' => 'assigned',
                'customization_level' => 'read_only', 'priority_metrics' => ['inspection_completion', 'defect_rate', 'ncr_resolution'],
                'alert_types' => ['quality', 'inspection', 'compliance'], 'dashboard_layout' => 'qc_grid',
            ],
            'client_rep' => [
                'name' => 'Client Representative', 'description' => 'Client communication and project oversight',
                'default_widgets' => ['project_summary', 'progress_report', 'milestone_status', 'budget_summary', 'quality_summary', 'schedule_status', 'client_communications', 'approval_queue'],
                'widget_categories' => ['overview', 'communication', 'reporting'], 'data_access' => 'client_view', 'project_access' => 'assigned',
                'customization_level' => 'read_only', 'priority_metrics' => ['project_progress', 'budget_status', 'quality_score'],
                'alert_types' => ['milestone', 'budget', 'quality'], 'dashboard_layout' => 'client_grid',
            ],
            'subcontractor_lead' => [
                'name' => 'Subcontractor Lead', 'description' => 'Subcontractor coordination and management',
                'default_widgets' => ['subcontractor_progress', 'payment_status', 'work_orders', 'quality_issues', 'safety_compliance', 'resource_allocation', 'performance_metrics', 'contract_status'],
                'widget_categories' => ['subcontractor', 'financial', 'quality'], 'data_access' => 'subcontractor_related', 'project_access' => 'assigned',
                'customization_level' => 'limited', 'priority_metrics' => ['work_completion', 'payment_status', 'quality_score'],
                'alert_types' => ['payment', 'quality', 'safety'], 'dashboard_layout' => 'subcontractor_grid',
            ],
        ];
    }
}
