<?php

namespace Tests\Unit\Dashboard;

use App\Models\DashboardAlert;
use App\Models\DashboardMetric;
use App\Models\DashboardMetricValue;
use App\Models\DashboardWidget;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDashboard;
use App\Models\UserRoleProject;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use Tests\Traits\RbacTestTrait;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;
    use RbacTestTrait;

    protected DashboardService $dashboardService;
    protected User $user;
    protected Project $project;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboardService = app(DashboardService::class);

        $this->user = $this->makeTenantUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'project_manager',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->tenant = Tenant::findOrFail($this->user->tenant_id);

        $this->grantPermissionsByCode($this->user, ['dashboard.view', 'project.read']);
        $role = $this->grantRole($this->user, 'project_manager');

        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
            'description' => 'Test project description',
            'status' => 'active',
            'budget' => 100000,
            'spent_amount' => 0,
            'progress' => 0,
            'code' => 'DSH-PRJ',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(6),
        ]);

        UserRoleProject::create([
            'id' => Str::ulid(),
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'role_id' => $role->id,
        ]);

        $this->createTestWidgets();
        $this->createTestMetrics();
    }

    protected function createTestWidgets(): void
    {
        DashboardWidget::create([
            'name' => 'Project Overview',
            'code' => 'project_overview',
            'type' => 'card',
            'category' => 'overview',
            'description' => 'Project overview widget',
            'config' => ['default_size' => 'large'],
            'data_source' => [
                'type' => 'static',
                'data' => ['total_projects' => 1, 'active_projects' => 1]
            ],
            'permissions' => ['roles' => ['project_manager', 'site_engineer']],
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
            'permissions' => ['roles' => ['project_manager', 'site_engineer']],
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        DashboardWidget::create([
            'name' => 'RFI Status',
            'code' => 'rfi_status',
            'type' => 'table',
            'category' => 'communication',
            'description' => 'RFI status widget',
            'config' => ['default_size' => 'medium'],
            'permissions' => ['roles' => ['project_manager', 'design_lead']],
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function createTestMetrics(): void
    {
        DashboardMetric::create([
            'name' => 'Project Progress',
            'code' => 'project_progress_metric',
            'metric_code' => 'project_progress',
            'description' => 'Overall project progress percentage',
            'unit' => '%',
            'type' => 'gauge',
            'is_active' => true,
            'permissions' => ['roles' => ['project_manager', 'site_engineer', 'client_rep']],
            'tenant_id' => $this->tenant->id,
        ]);

        DashboardMetric::create([
            'name' => 'Budget Utilization',
            'code' => 'budget_utilization_metric',
            'metric_code' => 'budget_utilization',
            'description' => 'Budget utilization percentage',
            'unit' => '%',
            'type' => 'gauge',
            'is_active' => true,
            'permissions' => ['roles' => ['project_manager', 'client_rep']],
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function it_can_get_user_dashboard()
    {
        UserDashboard::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Dashboard',
            'layout_config' => [],
            'widgets' => [],
            'is_default' => true,
            'is_active' => true,
            'preferences' => ['theme' => 'light'],
        ]);

        $dashboard = $this->dashboardService->getUserDashboard($this->user->id);

        $this->assertInstanceOf(UserDashboard::class, $dashboard);
        $this->assertEquals('Test Dashboard', $dashboard->name);
        $this->assertTrue($dashboard->is_default);
    }

    /** @test */
    public function it_creates_default_dashboard_when_none_exists()
    {
        $dashboard = $this->dashboardService->getUserDashboard($this->user->id);

        $this->assertInstanceOf(UserDashboard::class, $dashboard);
        $this->assertTrue($dashboard->is_default);
        $this->assertIsArray($dashboard->layout_config);
        $this->assertEquals('Default Dashboard', $dashboard->name);
    }

    /** @test */
    public function it_can_get_available_widgets_for_user()
    {
        $widgets = $this->dashboardService->getAvailableWidgetsForUser($this->user);

        $this->assertIsArray($widgets);
        $this->assertCount(3, $widgets);

        $availableIds = array_column($widgets, 'id');
        $projectOverviewId = DashboardWidget::where('code', 'project_overview')->value('id');
        $this->assertContains($projectOverviewId, $availableIds);
    }

    /** @test */
    public function it_filters_widgets_by_user_role()
    {
        $qcUser = $this->makeTenantUser([
            'name' => 'QC Inspector',
            'email' => 'qc@example.com',
            'role' => 'qc_inspector',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->grantPermissionsByCode($qcUser, ['dashboard.view']);
        $this->grantRole($qcUser, 'qc_inspector');

        $qcWidget = DashboardWidget::create([
            'name' => 'QC Insights',
            'code' => 'qc_insights',
            'type' => 'card',
            'category' => 'quality',
            'description' => 'QC-only dashboard widget',
            'config' => ['default_size' => 'medium'],
            'data_source' => [
                'type' => 'static',
                'data' => ['inspections' => 5]
            ],
            'permissions' => ['roles' => ['qc_inspector']],
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        $adminWidget = DashboardWidget::create([
            'name' => 'System Overview Admin',
            'code' => 'system_overview_admin',
            'type' => 'card',
            'category' => 'overview',
            'description' => 'Only visible to system administrators',
            'config' => ['default_size' => 'large'],
            'data_source' => [
                'type' => 'static',
                'data' => ['systems' => 1]
            ],
            'permissions' => ['roles' => ['system_admin']],
            'is_active' => true,
            'tenant_id' => null,
        ]);

        $widgets = $this->dashboardService->getAvailableWidgetsForUser($qcUser);
        $availableIds = array_column($widgets, 'id');

        $this->assertSame('qc_inspector', $qcUser->role);
        $this->assertContains($qcWidget->id, $availableIds);
        $this->assertNotContains($adminWidget->id, $availableIds);
    }

    /** @test */
    public function it_can_get_widget_data()
    {
        $widget = DashboardWidget::where('code', 'project_overview')->first();
        $data = $this->dashboardService->getWidgetData($widget->id, $this->user, $this->project->id);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_projects', $data);
    }

    /** @test */
    public function it_can_add_widget_to_dashboard()
    {
        $widget = DashboardWidget::where('code', 'project_overview')->first();

        $result = $this->dashboardService->addWidget($this->user, $widget->id, [
            'title' => 'Custom Project Overview',
            'size' => 'large',
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('widget_instance', $result);

        $dashboard = $this->dashboardService->getUserDashboard($this->user->id);
        $this->assertNotEmpty($dashboard->layout);

        $customWidget = null;
        foreach ($dashboard->layout as $instance) {
            if (($instance['title'] ?? null) === 'Custom Project Overview') {
                $customWidget = $instance;
                break;
            }
        }

        $this->assertNotNull($customWidget, 'Custom widget instance is present in the dashboard layout');
        $this->assertEquals('Custom Project Overview', $customWidget['title']);
        $this->assertEquals('large', $customWidget['size']);
    }

    /** @test */
    public function it_can_remove_widget_from_dashboard()
    {
        $widget = DashboardWidget::where('code', 'project_overview')->first();
        $addResult = $this->dashboardService->addWidget($this->user, $widget->id);
        $widgetInstanceId = $addResult['widget_instance']['id'];

        $result = $this->dashboardService->removeWidget($this->user, $widgetInstanceId);

        $this->assertTrue($result['success']);

        $dashboard = $this->dashboardService->getUserDashboard($this->user->id);
        $this->assertCount(0, $dashboard->layout);
    }

    /** @test */
    public function it_can_update_widget_configuration()
    {
        $widget = DashboardWidget::where('code', 'project_overview')->first();
        $addResult = $this->dashboardService->addWidget($this->user, $widget->id);
        $widgetInstanceId = $addResult['widget_instance']['id'];

        $updatedDashboard = $this->dashboardService->updateWidgetConfig($this->user->id, $widgetInstanceId, [
            'title' => 'Updated Title',
            'size' => 'extra-large',
        ]);

        $this->assertInstanceOf(UserDashboard::class, $updatedDashboard);
        $this->assertEquals('Updated Title', $updatedDashboard->layout[0]['config']['title']);
        $this->assertEquals('extra-large', $updatedDashboard->layout[0]['config']['size']);
    }

    /** @test */
    public function it_can_update_dashboard_layout()
    {
        $widget1 = DashboardWidget::where('code', 'project_overview')->first();
        $widget2 = DashboardWidget::where('code', 'task_progress')->first();

        $this->dashboardService->addWidget($this->user, $widget1->id);
        $this->dashboardService->addWidget($this->user, $widget2->id);

        $dashboard = $this->dashboardService->getUserDashboard($this->user->id);
        $layout = $dashboard->layout;

        $layout[0]['position'] = ['x' => 0, 'y' => 0];
        $layout[1]['position'] = ['x' => 6, 'y' => 0];

        $updated = $this->dashboardService->updateDashboardLayout($this->user->id, $layout, $dashboard->widgets);

        $this->assertInstanceOf(UserDashboard::class, $updated);
        $this->assertEquals(['x' => 0, 'y' => 0], $updated->layout_config[0]['position']);
        $this->assertEquals(['x' => 6, 'y' => 0], $updated->layout_config[1]['position']);
    }

    /** @test */
    public function it_can_get_user_alerts()
    {
        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Test Alert 1',
            'type' => 'info',
            'severity' => 'low',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => ['project_id' => $this->project->id],
        ]);

        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Test Alert 2',
            'type' => 'warning',
            'severity' => 'medium',
            'is_read' => true,
            'triggered_at' => now()->subHour(),
            'context' => ['project_id' => $this->project->id],
        ]);

        $alerts = $this->dashboardService->getUserAlerts($this->user);

        $this->assertIsArray($alerts);
        $this->assertCount(2, $alerts);

        $unread = array_filter($alerts, fn($alert) => !$alert['is_read']);
        $this->assertCount(1, $unread);
    }

    /** @test */
    public function it_can_mark_alert_as_read()
    {
        $alert = DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Test Alert',
            'type' => 'info',
            'severity' => 'low',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => [],
        ]);

        $result = $this->dashboardService->markAlertAsRead($this->user, $alert->id);

        $this->assertTrue($result['success']);

        $alert->refresh();
        $this->assertTrue($alert->is_read);
    }

    /** @test */
    public function it_can_mark_all_alerts_as_read()
    {
        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Test Alert 1',
            'type' => 'info',
            'severity' => 'low',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => [],
        ]);

        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Test Alert 2',
            'type' => 'warning',
            'severity' => 'medium',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => [],
        ]);

        $result = $this->dashboardService->markAllAlertsAsRead($this->user);

        $this->assertTrue($result['success']);

        $alerts = DashboardAlert::where('user_id', $this->user->id)->get();
        foreach ($alerts as $alert) {
            $this->assertTrue($alert->is_read);
        }
    }

    /** @test */
    public function it_can_get_dashboard_metrics()
    {
        $metric = DashboardMetric::where('metric_code', 'project_progress')->first();

        DashboardMetricValue::create([
            'metric_id' => $metric->id,
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'value' => 75.5,
            'recorded_at' => now(),
        ]);

        $metrics = $this->dashboardService->getDashboardMetrics($this->user, $this->project->id);

        $this->assertIsArray($metrics);
        $this->assertNotEmpty($metrics);
        $this->assertEquals('project_progress', $metrics[0]['code']);
        $this->assertEquals(75.5, $metrics[0]['value']);
    }

    /** @test */
    public function it_can_save_user_preferences()
    {
        $preferences = [
            'theme' => 'dark',
            'refresh_interval' => 60,
            'compact_mode' => true,
            'show_widget_borders' => false,
        ];

        $dashboard = $this->dashboardService->saveUserPreferences($this->user->id, $preferences);

        $this->assertInstanceOf(UserDashboard::class, $dashboard);
        $this->assertEquals('dark', $dashboard->preferences['theme']);
        $this->assertEquals(60, $dashboard->preferences['refresh_interval']);
        $this->assertTrue($dashboard->preferences['compact_mode']);
        $this->assertFalse($dashboard->preferences['show_widget_borders']);
    }

    /** @test */
    public function it_can_reset_dashboard_to_default()
    {
        $widget = DashboardWidget::where('code', 'project_overview')->first();
        $this->dashboardService->addWidget($this->user, $widget->id);
        $this->dashboardService->saveUserPreferences($this->user->id, ['theme' => 'dark']);

        $result = $this->dashboardService->resetDashboard($this->user->id);

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(UserDashboard::class, $result['dashboard']);

        $dashboard = $this->dashboardService->getUserDashboard($this->user->id);
        $this->assertCount(0, $dashboard->layout);
        $this->assertEquals([], $dashboard->preferences);
    }

    /** @test */
    public function it_handles_database_transactions_correctly()
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();
        DB::shouldReceive('rollBack')->never();

        $widget = DashboardWidget::where('code', 'project_overview')->first();
        $this->dashboardService->addWidget($this->user, $widget->id);

        $dashboard = $this->dashboardService->getUserDashboard($this->user->id);
        $this->assertCount(1, $dashboard->layout);
    }

    /** @test */
    public function it_rolls_back_transaction_on_error()
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->andThrow(new \Exception('Database error'));
        DB::shouldReceive('rollBack')->once();

        $this->expectException(\Exception::class);

        $widget = DashboardWidget::where('code', 'project_overview')->first();
        $this->dashboardService->addWidget($this->user, $widget->id);
    }

    /** @test */
    public function it_validates_widget_permissions()
    {
        $qcUser = User::create([
            'name' => 'QC Inspector',
            'email' => 'qc@example.com',
            'password' => bcrypt('password'),
            'role' => 'qc_inspector',
            'tenant_id' => $this->tenant->id,
        ]);

        $widget = DashboardWidget::where('code', 'project_overview')->first();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User does not have permission to access this widget');

        $this->dashboardService->addWidget($qcUser, $widget->id);
    }

    /** @test */
    public function it_handles_missing_widget_gracefully()
    {
        $result = $this->dashboardService->addWidget($this->user, 'non-existent-widget-id');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Widget not found', $result['message']);
    }

    /** @test */
    public function it_handles_missing_widget_instance_gracefully()
    {
        $result = $this->dashboardService->removeWidget($this->user, 'non-existent-instance-id');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Widget instance not found', $result['message']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
