<?php

namespace Tests\Unit\Dashboard;

use App\Models\DashboardAlert;
use App\Models\DashboardMetric;
use App\Models\DashboardWidget;
use App\Models\Project;
use App\Models\RFI;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleProject;
use App\Services\DashboardRoleBasedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\RbacTestTrait;

class DashboardRoleBasedServiceTest extends TestCase
{
    use RefreshDatabase;
    use RbacTestTrait;

    protected DashboardRoleBasedService $roleBasedService;
    protected User $user;
    protected Project $project;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleBasedService = app(DashboardRoleBasedService::class);

        $this->user = $this->makeTenantUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'project_manager',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->tenant = Tenant::findOrFail($this->user->tenant_id);

        $this->grantPermissionsByCode($this->user, ['dashboard.view', 'project.read', 'task.view']);
        $role = $this->grantRole($this->user, 'project_manager');

        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
            'description' => 'Project for dashboard tests',
            'status' => 'active',
            'budget' => 100000,
            'spent_amount' => 0,
            'progress' => 0,
            'code' => 'TEST-PRJ',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(6),
        ]);

        UserRoleProject::create([
            'id' => Str::ulid(),
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'role_id' => $role->id,
        ]);

        $this->createTestData();
    }

    protected function createTestData(): void
    {
        DashboardWidget::create([
            'name' => 'Project Overview',
            'code' => 'project_overview',
            'type' => 'card',
            'category' => 'overview',
            'description' => 'Project overview widget',
            'config' => ['default_size' => 'large'],
            'permissions' => ['project_manager'],
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        DashboardWidget::create([
            'name' => 'Task Progress',
            'code' => 'task_progress',
            'type' => 'chart',
            'category' => 'tasks',
            'description' => 'Task progress widget',
            'config' => ['default_size' => 'medium'],
            'permissions' => ['project_manager', 'site_engineer'],
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        DashboardMetric::create([
            'name' => 'Project Progress',
            'code' => 'project_progress',
            'description' => 'Overall project progress percentage',
            'unit' => '%',
            'type' => 'gauge',
            'is_active' => true,
            'permissions' => ['project_manager', 'site_engineer', 'client_rep'],
            'tenant_id' => $this->tenant->id,
        ]);

        Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task 1',
            'description' => 'Task description 1',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => now()->addDays(7),
            'assigned_to' => $this->user->id,
        ]);

        Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task 2',
            'description' => 'Task description 2',
            'status' => 'completed',
            'priority' => 'medium',
            'due_date' => now()->subDays(1),
            'assigned_to' => $this->user->id,
        ]);

        RFI::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'Test RFI Title',
            'question' => 'What is the status?',
            'subject' => 'Test RFI',
            'description' => 'RFI description',
            'status' => 'open',
            'priority' => 'high',
            'rfi_number' => 'RFI-001',
            'due_date' => now()->addDays(3),
            'discipline' => 'construction',
            'asked_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_get_role_based_dashboard()
    {
        $dashboard = $this->roleBasedService->getRoleBasedDashboard($this->user, $this->project->id);

        $this->assertIsArray($dashboard);
        $this->assertArrayHasKey('dashboard', $dashboard);
        $this->assertArrayHasKey('widgets', $dashboard);
        $this->assertArrayHasKey('metrics', $dashboard);
        $this->assertArrayHasKey('alerts', $dashboard);
        $this->assertArrayHasKey('permissions', $dashboard);
        $this->assertArrayHasKey('role_config', $dashboard);
        $this->assertArrayHasKey('project_context', $dashboard);
    }

    /** @test */
    public function it_returns_correct_role_configuration()
    {
        $roleConfig = $this->roleBasedService->getRoleConfiguration('project_manager');

        $this->assertEquals('Project Manager', $roleConfig['name']);
        $this->assertEquals('Comprehensive project management and oversight', $roleConfig['description']);
        $this->assertContains('project_overview', $roleConfig['default_widgets']);
        $this->assertContains('task_progress', $roleConfig['default_widgets']);
        $this->assertEquals('project_wide', $roleConfig['data_access']);
        $this->assertEquals('assigned', $roleConfig['project_access']);
        $this->assertEquals('full', $roleConfig['customization_level']);
    }

    /** @test */
    public function it_returns_default_configuration_for_unknown_role()
    {
        $roleConfig = $this->roleBasedService->getRoleConfiguration('unknown_role');

        $this->assertEquals('Client Representative', $roleConfig['name']);
        $this->assertEquals('Client communication and project oversight', $roleConfig['description']);
    }

    /** @test */
    public function it_can_get_role_based_widgets()
    {
        $roleConfig = $this->roleBasedService->getRoleConfiguration('project_manager');
        $widgets = $this->roleBasedService->getRoleBasedWidgets($this->user, $roleConfig, $this->project->id);

        $this->assertIsArray($widgets);
        $this->assertCount(2, $widgets);

        $this->assertArrayHasKey('widget', $widgets[0]);
        $this->assertArrayHasKey('data', $widgets[0]);
        $this->assertArrayHasKey('permissions', $widgets[0]);

        $widgetCodes = array_column(array_column($widgets, 'widget'), 'code');
        $this->assertContains('project_overview', $widgetCodes);
        $this->assertContains('task_progress', $widgetCodes);
    }

    /** @test */
    public function it_filters_widgets_by_user_role()
    {
        $qcUser = User::create([
            'name' => 'QC Inspector',
            'email' => 'qc@example.com',
            'password' => bcrypt('password'),
            'role' => 'qc_inspector',
            'tenant_id' => $this->tenant->id,
        ]);

        $roleConfig = $this->roleBasedService->getRoleConfiguration('qc_inspector');
        $widgets = $this->roleBasedService->getRoleBasedWidgets($qcUser, $roleConfig, $this->project->id);

        $widgetCodes = array_column(array_column($widgets, 'widget'), 'code');
        $this->assertNotContains('project_overview', $widgetCodes);
    }

    /** @test */
    public function it_can_get_project_overview_data()
    {
        $data = $this->roleBasedService->getProjectOverviewData($this->user, $this->project->id);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_projects', $data);
        $this->assertArrayHasKey('active_projects', $data);
        $this->assertArrayHasKey('completed_projects', $data);
        $this->assertArrayHasKey('total_budget', $data);
        $this->assertArrayHasKey('spent_budget', $data);
        $this->assertArrayHasKey('recent_projects', $data);

        $this->assertEquals(1, $data['total_projects']);
        $this->assertEquals(1, $data['active_projects']);
        $this->assertEquals(0, $data['completed_projects']);
    }

    /** @test */
    public function it_can_get_task_progress_data()
    {
        $data = $this->roleBasedService->getTaskProgressData($this->user, $this->project->id);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_tasks', $data);
        $this->assertArrayHasKey('completed_tasks', $data);
        $this->assertArrayHasKey('in_progress_tasks', $data);
        $this->assertArrayHasKey('pending_tasks', $data);
        $this->assertArrayHasKey('overdue_tasks', $data);
        $this->assertArrayHasKey('completion_rate', $data);
        $this->assertArrayHasKey('recent_tasks', $data);

        $this->assertEquals(2, $data['total_tasks']);
        $this->assertEquals(1, $data['completed_tasks']);
        $this->assertEquals(1, $data['in_progress_tasks']);
        $this->assertEquals(0, $data['pending_tasks']);
    }

    /** @test */
    public function it_can_get_rfi_status_data()
    {
        $data = $this->roleBasedService->getRFIStatusData($this->user, $this->project->id);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_rfis', $data);
        $this->assertArrayHasKey('open_rfis', $data);
        $this->assertArrayHasKey('answered_rfis', $data);
        $this->assertArrayHasKey('closed_rfis', $data);
        $this->assertArrayHasKey('overdue_rfis', $data);
        $this->assertArrayHasKey('recent_rfis', $data);

        $this->assertEquals(1, $data['total_rfis']);
        $this->assertEquals(1, $data['open_rfis']);
    }

    /** @test */
    public function it_can_get_budget_tracking_data()
    {
        $data = $this->roleBasedService->getBudgetTrackingData($this->user, $this->project->id);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_budget', $data);
        $this->assertArrayHasKey('total_spent', $data);
        $this->assertArrayHasKey('remaining_budget', $data);
        $this->assertArrayHasKey('budget_utilization', $data);
        $this->assertArrayHasKey('budget_variance', $data);
        $this->assertArrayHasKey('monthly_spending', $data);
        $this->assertArrayHasKey('top_expense_categories', $data);
        $this->assertArrayHasKey('budget_alerts', $data);

        $this->assertEquals(100000, $data['total_budget']);
        $this->assertEquals(0, $data['total_spent']);
        $this->assertEquals(100000, $data['remaining_budget']);
        $this->assertEquals(0, $data['budget_utilization']);
    }

    /** @test */
    public function it_can_get_role_based_metrics()
    {
        $metrics = $this->roleBasedService->getRoleBasedMetrics($this->user, $this->project->id);

        $this->assertIsArray($metrics);
        $this->assertNotEmpty($metrics);

        $metric = $metrics[0];
        $this->assertArrayHasKey('metric', $metric);
        $this->assertArrayHasKey('value', $metric);
        $this->assertArrayHasKey('trend', $metric);
        $this->assertArrayHasKey('target', $metric);

        $this->assertEquals('project_progress', $metric['metric']['code']);
        $this->assertIsFloat($metric['value']);
    }

    /** @test */
    public function it_can_get_role_based_alerts()
    {
        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'message' => 'Test Alert 1',
            'type' => 'project',
            'severity' => 'medium',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => ['project_id' => $this->project->id],
        ]);

        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'message' => 'Test Alert 2',
            'type' => 'budget',
            'severity' => 'high',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => ['project_id' => $this->project->id],
        ]);

        $alerts = $this->roleBasedService->getRoleBasedAlerts($this->user, $this->project->id);

        $this->assertIsArray($alerts);
        $this->assertCount(2, $alerts);
        $this->assertArrayHasKey('message', $alerts[0]);
    }

    /** @test */
    public function it_can_get_role_permissions()
    {
        $permissions = $this->roleBasedService->getRolePermissions('project_manager');

        $this->assertIsArray($permissions);
        $this->assertArrayHasKey('dashboard', $permissions);
        $this->assertArrayHasKey('widgets', $permissions);
        $this->assertArrayHasKey('projects', $permissions);
        $this->assertArrayHasKey('users', $permissions);
        $this->assertArrayHasKey('reports', $permissions);
        $this->assertArrayHasKey('settings', $permissions);

        $this->assertContains('view', $permissions['dashboard']);
        $this->assertContains('edit', $permissions['dashboard']);
        $this->assertContains('share', $permissions['dashboard']);
        $this->assertContains('view_assigned', $permissions['projects']);
        $this->assertContains('edit_assigned', $permissions['projects']);
    }

    /** @test */
    public function it_can_check_widget_permissions()
    {
        $widget = DashboardWidget::where('code', 'project_overview')->first();

        $this->assertTrue($this->roleBasedService->userCanAccessWidget($this->user, $widget));

        $qcUser = User::create([
            'name' => 'QC Inspector',
            'email' => 'qc@example.com',
            'password' => bcrypt('password'),
            'role' => 'qc_inspector',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertFalse($this->roleBasedService->userCanAccessWidget($qcUser, $widget));
    }

    /** @test */
    public function it_can_get_widget_permissions()
    {
        $widget = DashboardWidget::where('code', 'project_overview')->first();
        $permissions = $this->roleBasedService->getWidgetPermissions($this->user, $widget);

        $this->assertIsArray($permissions);
        $this->assertArrayHasKey('can_view', $permissions);
        $this->assertArrayHasKey('can_edit', $permissions);
        $this->assertArrayHasKey('can_delete', $permissions);
        $this->assertArrayHasKey('can_configure', $permissions);
        $this->assertArrayHasKey('can_share', $permissions);

        $this->assertTrue($permissions['can_view']);
        $this->assertTrue($permissions['can_edit']);
        $this->assertTrue($permissions['can_configure']);
    }

    /** @test */
    public function it_can_get_project_context()
    {
        $context = $this->roleBasedService->getProjectContext($this->user, $this->project->id);

        $this->assertIsArray($context);
        $this->assertArrayHasKey('current_project', $context);
        $this->assertArrayHasKey('available_projects', $context);

        $this->assertNotNull($context['current_project']);
        $this->assertEquals($this->project->id, $context['current_project']['id']);
        $this->assertEquals($this->project->name, $context['current_project']['name']);
        $this->assertEquals($this->project->status, $context['current_project']['status']);
    }

    /** @test */
    public function it_returns_empty_context_for_invalid_project()
    {
        $context = $this->roleBasedService->getProjectContext($this->user, 'invalid-project-id');

        $this->assertIsArray($context);
        $this->assertNull($context['current_project']);
        $this->assertIsArray($context['available_projects']);
    }

    /** @test */
    public function it_handles_different_user_roles_correctly()
    {
        $roles = ['system_admin', 'project_manager', 'design_lead', 'site_engineer', 'qc_inspector', 'client_rep', 'subcontractor_lead'];

        foreach ($roles as $role) {
            $roleConfig = $this->roleBasedService->getRoleConfiguration($role);
            $permissions = $this->roleBasedService->getRolePermissions($role);

            $this->assertIsArray($roleConfig);
            $this->assertIsArray($permissions);
            $this->assertArrayHasKey('name', $roleConfig);
            $this->assertArrayHasKey('description', $roleConfig);
            $this->assertArrayHasKey('default_widgets', $roleConfig);
            $this->assertArrayHasKey('customization_level', $roleConfig);
        }
    }

    /** @test */
    public function it_calculates_budget_variance_correctly()
    {
        $project1 = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Project 1',
            'budget' => 100000,
            'spent_amount' => 120000,
            'status' => 'active',
            'description' => 'Over budget',
        ]);

        $project2 = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Project 2',
            'budget' => 200000,
            'spent_amount' => 150000,
            'status' => 'active',
            'description' => 'Under budget',
        ]);

        $projects = collect([$project1, $project2]);
        $variance = $this->roleBasedService->calculateBudgetVariance($projects);

        $this->assertIsArray($variance);
        $this->assertEquals(-30000, $variance['total_variance']);
        $this->assertEquals(1, $variance['projects_over_budget']);
        $this->assertEquals(1, $variance['projects_under_budget']);
    }

    /** @test */
    public function it_calculates_average_response_time_correctly()
    {
        $rfi1 = RFI::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'RFI 1',
            'question' => 'Question 1',
            'subject' => 'RFI 1',
            'description' => 'RFI description 1',
            'status' => 'closed',
            'rfi_number' => 'RFI-002',
            'created_at' => now()->subHours(24),
            'answered_at' => now()->subHours(12),
            'asked_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $rfi2 = RFI::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'RFI 2',
            'question' => 'Question 2',
            'subject' => 'RFI 2',
            'description' => 'RFI description 2',
            'status' => 'closed',
            'rfi_number' => 'RFI-003',
            'created_at' => now()->subHours(48),
            'answered_at' => now()->subHours(24),
            'asked_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $rfis = collect([$rfi1, $rfi2]);
        $avg = $this->roleBasedService->calculateAverageResponseTime($rfis);

        $this->assertEquals(18, $avg);
    }

    /** @test */
    public function it_generates_budget_alerts_correctly()
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'High Budget Project',
            'budget' => 100000,
            'spent_amount' => 85000,
            'status' => 'active',
            'description' => 'High utilization',
        ]);

        $alerts = $this->roleBasedService->getBudgetAlerts(collect([$project]));

        $this->assertIsArray($alerts);
        $this->assertNotEmpty($alerts);

        $this->assertEquals('budget_warning', $alerts[0]['type']);
        $this->assertEquals('medium', $alerts[0]['severity']);
        $this->assertStringContainsString('Budget utilization exceeds 80%', $alerts[0]['message']);
    }
}
