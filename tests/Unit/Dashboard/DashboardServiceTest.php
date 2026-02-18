<?php

namespace Tests\Unit\Dashboard;

use Tests\TestCase;
use App\Services\DashboardService;
use App\Models\User;
use App\Models\UserDashboard;
use App\Models\DashboardWidget;
use App\Models\DashboardMetric;
use App\Models\DashboardAlert;
use App\Models\Project;
use App\Models\Task;
use App\Models\Rfi;
use App\Models\Inspection;
use App\Models\NCR;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\SSOT\FixtureFactory;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase, FixtureFactory;

    protected $dashboardService;
    protected $user;
    protected $project;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->dashboardService = new DashboardService();
        
        // Create test tenant
        $this->tenant = $this->createTenant([
            'name' => 'Test Tenant',
            'domain' => 'test.com',
            'is_active' => true
        ]);
        
        // Create test user
        $this->user = $this->createTenantUserWithRbac($this->tenant, 'project_manager', 'project_manager', [], [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'project_manager',
            'tenant_id' => $this->tenant->id,
        ]);
        
        // Create test project
        $this->project = $this->createProjectForTenant($this->tenant, $this->user, [
            'name' => 'Test Project',
            'description' => 'Test project description',
            'status' => 'active',
            'budget' => 100000,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'tenant_id' => $this->tenant->id,
        ]);
        
        // Create test widgets
        $this->createTestWidgets();
        
        // Create test metrics
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
            'config' => json_encode(['default_size' => 'large']),
            'permissions' => json_encode(['project_manager', 'site_engineer']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        DashboardWidget::create([
            'name' => 'Task Progress',
            'code' => 'task_progress',
            'type' => 'chart',
            'category' => 'tasks',
            'description' => 'Task progress widget',
            'config' => json_encode(['default_size' => 'medium']),
            'permissions' => json_encode(['project_manager', 'site_engineer']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        DashboardWidget::create([
            'name' => 'RFI Status',
            'code' => 'rfi_status',
            'type' => 'table',
            'category' => 'communication',
            'description' => 'RFI status widget',
            'config' => json_encode(['default_size' => 'medium']),
            'permissions' => json_encode(['project_manager', 'design_lead']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);
    }

    protected function createTestMetrics(): void
    {
        DashboardMetric::create([
            'name' => 'Project Progress',
            'code' => 'project_progress',
            'description' => 'Overall project progress percentage',
            'unit' => '%',
            'type' => 'gauge',
            'is_active' => true,
            'permissions' => json_encode(['project_manager', 'site_engineer', 'client_rep']),
            'tenant_id' => $this->tenant->id
        ]);

        DashboardMetric::create([
            'name' => 'Budget Utilization',
            'code' => 'budget_utilization',
            'description' => 'Budget utilization percentage',
            'unit' => '%',
            'type' => 'gauge',
            'is_active' => true,
            'permissions' => json_encode(['project_manager', 'client_rep']),
            'tenant_id' => $this->tenant->id
        ]);
    }

    /** @test */
    public function it_can_get_user_dashboard()
    {
        // Create user dashboard
        UserDashboard::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Dashboard',
            'layout' => json_encode([]),
            'is_default' => true,
            'preferences' => json_encode(['theme' => 'light'])
        ]);

        $dashboard = $this->dashboardService->getUserDashboard($this->user);

        $this->assertNotNull($dashboard);
        $this->assertEquals('Test Dashboard', $dashboard['name']);
        $this->assertTrue($dashboard['is_default']);
    }

    /** @test */
    public function it_creates_default_dashboard_when_none_exists()
    {
        $dashboard = $this->dashboardService->getUserDashboard($this->user);

        $this->assertNotNull($dashboard);
        $this->assertTrue($dashboard['is_default']);
        $this->assertIsArray($dashboard['layout']);
    }

    /** @test */
    public function it_can_get_available_widgets_for_user()
    {
        $widgets = $this->dashboardService->getAvailableWidgets($this->user);

        $this->assertIsArray($widgets);
        $this->assertCount(3, $widgets);
        
        // Check that widgets are filtered by user role
        $widgetCodes = array_column($widgets, 'code');
        $this->assertContains('project_overview', $widgetCodes);
        $this->assertContains('task_progress', $widgetCodes);
        $this->assertContains('rfi_status', $widgetCodes);
    }

    /** @test */
    public function it_filters_widgets_by_user_role()
    {
        // Create QC Inspector user
        $qcUser = User::create([
            'name' => 'QC Inspector',
            'email' => 'qc@example.com',
            'password' => bcrypt('password'),
            'role' => 'qc_inspector',
            'tenant_id' => $this->tenant->id
        ]);

        $widgets = $this->dashboardService->getAvailableWidgets($qcUser);

        // QC Inspector should not see project_manager specific widgets
        $widgetCodes = array_column($widgets, 'code');
        $this->assertNotContains('project_overview', $widgetCodes);
    }

    /** @test */
    public function it_can_get_widget_data()
    {
        $widget = DashboardWidget::where('code', 'project_overview')->first();
        
        $data = $this->dashboardService->getWidgetData($widget->id, $this->user, $this->project->id);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_projects', $data);
        $this->assertArrayHasKey('active_projects', $data);
    }

    /** @test */
    public function it_can_add_widget_to_dashboard()
    {
        $widget = DashboardWidget::where('code', 'project_overview')->first();
        
        $result = $this->dashboardService->addWidget($this->user, $widget->id, [
            'title' => 'Custom Project Overview',
            'size' => 'large'
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('widget_instance', $result);
        
        // Verify widget was added to dashboard
        $dashboard = $this->dashboardService->getUserDashboard($this->user);
        $this->assertCount(1, $dashboard['layout']);
        $this->assertEquals('Custom Project Overview', $dashboard['layout'][0]['title']);
    }

    /** @test */
    public function it_can_remove_widget_from_dashboard()
    {
        // First add a widget
        $widget = DashboardWidget::where('code', 'project_overview')->first();
        $addResult = $this->dashboardService->addWidget($this->user, $widget->id);
        $widgetInstanceId = $addResult['widget_instance']['id'];

        // Then remove it
        $result = $this->dashboardService->removeWidget($this->user, $widgetInstanceId);

        $this->assertTrue($result['success']);
        
        // Verify widget was removed
        $dashboard = $this->dashboardService->getUserDashboard($this->user);
        $this->assertCount(0, $dashboard['layout']);
    }

    /** @test */
    public function it_can_update_widget_configuration()
    {
        // First add a widget
        $widget = DashboardWidget::where('code', 'project_overview')->first();
        $addResult = $this->dashboardService->addWidget($this->user, $widget->id);
        $widgetInstanceId = $addResult['widget_instance']['id'];

        // Update configuration
        $result = $this->dashboardService->updateWidgetConfig($this->user, $widgetInstanceId, [
            'title' => 'Updated Title',
            'size' => 'extra-large'
        ]);

        $this->assertTrue($result['success']);
        
        // Verify configuration was updated
        $dashboard = $this->dashboardService->getUserDashboard($this->user);
        $widgetInstance = $dashboard['layout'][0];
        $this->assertEquals('Updated Title', $widgetInstance['title']);
        $this->assertEquals('extra-large', $widgetInstance['size']);
    }

    /** @test */
    public function it_can_update_dashboard_layout()
    {
        // Add multiple widgets
        $widget1 = DashboardWidget::where('code', 'project_overview')->first();
        $widget2 = DashboardWidget::where('code', 'task_progress')->first();
        
        $this->dashboardService->addWidget($this->user, $widget1->id);
        $this->dashboardService->addWidget($this->user, $widget2->id);

        $dashboard = $this->dashboardService->getUserDashboard($this->user);
        $layout = $dashboard['layout'];

        // Update layout positions
        $layout[0]['position'] = ['x' => 0, 'y' => 0];
        $layout[1]['position'] = ['x' => 6, 'y' => 0];

        $result = $this->dashboardService->updateDashboardLayout($this->user, $layout);

        $this->assertTrue($result['success']);
        
        // Verify layout was updated
        $updatedDashboard = $this->dashboardService->getUserDashboard($this->user);
        $this->assertEquals(['x' => 0, 'y' => 0], $updatedDashboard['layout'][0]['position']);
        $this->assertEquals(['x' => 6, 'y' => 0], $updatedDashboard['layout'][1]['position']);
    }

    /** @test */
    public function it_can_get_user_alerts()
    {
        // Create test alerts
        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Test Alert 1',
            'type' => 'info',
            'severity' => 'low',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => json_encode(['project_id' => $this->project->id])
        ]);

        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Test Alert 2',
            'type' => 'warning',
            'severity' => 'medium',
            'is_read' => true,
            'triggered_at' => now()->subHour(),
            'context' => json_encode(['project_id' => $this->project->id])
        ]);

        $alerts = $this->dashboardService->getUserAlerts($this->user);

        $this->assertIsArray($alerts);
        $this->assertCount(2, $alerts);
        
        // Check unread alerts
        $unreadAlerts = array_filter($alerts, function($alert) {
            return !$alert['is_read'];
        });
        $this->assertCount(1, $unreadAlerts);
    }

    /** @test */
    public function it_can_mark_alert_as_read()
    {
        // Create test alert
        $alert = DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Test Alert',
            'type' => 'info',
            'severity' => 'low',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => json_encode([])
        ]);

        $result = $this->dashboardService->markAlertAsRead($this->user, $alert->id);

        $this->assertTrue($result['success']);
        
        // Verify alert was marked as read
        $alert->refresh();
        $this->assertTrue($alert->is_read);
    }

    /** @test */
    public function it_can_mark_all_alerts_as_read()
    {
        // Create multiple test alerts
        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Test Alert 1',
            'type' => 'info',
            'severity' => 'low',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => json_encode([])
        ]);

        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Test Alert 2',
            'type' => 'warning',
            'severity' => 'medium',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => json_encode([])
        ]);

        $result = $this->dashboardService->markAllAlertsAsRead($this->user);

        $this->assertTrue($result['success']);
        
        // Verify all alerts were marked as read
        $alerts = DashboardAlert::where('user_id', $this->user->id)->get();
        foreach ($alerts as $alert) {
            $this->assertTrue($alert->is_read);
        }
    }

    /** @test */
    public function it_can_get_dashboard_metrics()
    {
        // Create test metric values
        $metric = DashboardMetric::where('code', 'project_progress')->first();
        \App\Models\DashboardMetricValue::create([
            'metric_id' => $metric->id,
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'value' => 75.5,
            'timestamp' => now(),
            'context' => json_encode(['phase' => 'construction'])
        ]);

        $metrics = $this->dashboardService->getDashboardMetrics($this->user, $this->project->id);

        $this->assertIsArray($metrics);
        $this->assertCount(1, $metrics);
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
            'show_widget_borders' => false
        ];

        $result = $this->dashboardService->saveUserPreferences($this->user, $preferences);

        $this->assertTrue($result['success']);
        
        // Verify preferences were saved
        $dashboard = $this->dashboardService->getUserDashboard($this->user);
        $savedPreferences = $dashboard['preferences'];
        $this->assertEquals('dark', $savedPreferences['theme']);
        $this->assertEquals(60, $savedPreferences['refresh_interval']);
        $this->assertTrue($savedPreferences['compact_mode']);
        $this->assertFalse($savedPreferences['show_widget_borders']);
    }

    /** @test */
    public function it_can_reset_dashboard_to_default()
    {
        // Add custom widgets
        $widget = DashboardWidget::where('code', 'project_overview')->first();
        $this->dashboardService->addWidget($this->user, $widget->id);

        // Save custom preferences
        $this->dashboardService->saveUserPreferences($this->user, ['theme' => 'dark']);

        // Reset dashboard
        $result = $this->dashboardService->resetDashboard($this->user);

        $this->assertTrue($result['success']);
        
        // Verify dashboard was reset
        $dashboard = $this->dashboardService->getUserDashboard($this->user);
        $this->assertCount(0, $dashboard['layout']); // No custom widgets
        $this->assertEquals('light', $dashboard['preferences']['theme']); // Default theme
    }

    /** @test */
    public function it_handles_database_transactions_correctly()
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();
        DB::shouldReceive('rollBack')->never();

        $widget = DashboardWidget::where('code', 'project_overview')->first();
        $result = $this->dashboardService->addWidget($this->user, $widget->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(
            $widget->code ?? $widget->id,
            $result['widget_instance']['code']
        );
    }

    /** @test */
    public function it_rolls_back_transaction_on_error()
    {
        // Mock database to throw exception
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        
        // Mock UserDashboard to throw exception
        UserDashboard::shouldReceive('where')->andThrow(new \Exception('Database error'));

        $this->expectException(\Exception::class);
        
        $widget = DashboardWidget::where('code', 'project_overview')->first();
        $this->dashboardService->addWidget($this->user, $widget->id);
    }

    /** @test */
    public function it_validates_widget_permissions()
    {
        // Create QC Inspector user
        $qcUser = User::create([
            'name' => 'QC Inspector',
            'email' => 'qc@example.com',
            'password' => bcrypt('password'),
            'role' => 'qc_inspector',
            'tenant_id' => $this->tenant->id
        ]);

        // Try to add widget that QC Inspector doesn't have permission for
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
        $this->assertStringContains('Widget not found', $result['message']);
    }

    /** @test */
    public function it_handles_missing_widget_instance_gracefully()
    {
        $result = $this->dashboardService->removeWidget($this->user, 'non-existent-instance-id');
        
        $this->assertFalse($result['success']);
        $this->assertStringContains('Widget instance not found', $result['message']);
    }

    protected function tearDown(): void
    {
        UserDashboard::resetStaticMock();
        Mockery::close();
        parent::tearDown();
    }
}
