<?php

namespace Tests\E2E;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserDashboard;
use App\Models\DashboardWidget;
use App\Models\DashboardMetric;
use App\Models\DashboardAlert;
use App\Models\Project;
use App\Models\Task;
use App\Models\Rfi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\SSOT\FixtureFactory;

class DashboardE2ETest extends TestCase
{
    use RefreshDatabase, FixtureFactory;

    protected $user;
    protected $project;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
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
            'password' => Hash::make('password'),
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
        
        // Create test data
        $this->createTestData();
        
        // Authenticate user
        $this->apiAs($this->user, $this->tenant);
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

        DashboardWidget::create([
            'name' => 'Budget Tracking',
            'code' => 'budget_tracking',
            'type' => 'chart',
            'category' => 'financial',
            'description' => 'Budget tracking widget',
            'config' => json_encode(['default_size' => 'large']),
            'permissions' => json_encode(['project_manager', 'client_rep']),
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

        DashboardMetric::create([
            'name' => 'Task Completion Rate',
            'code' => 'task_completion_rate',
            'description' => 'Task completion rate percentage',
            'unit' => '%',
            'type' => 'gauge',
            'is_active' => true,
            'permissions' => json_encode(['project_manager', 'site_engineer']),
            'tenant_id' => $this->tenant->id
        ]);
    }

    protected function createTestData(): void
    {
        // Create test tasks
        Task::create([
            'title' => 'Foundation Work',
            'description' => 'Complete foundation construction',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => now()->addDays(7),
            'assigned_to' => $this->user->id,
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        Task::create([
            'title' => 'Design Review',
            'description' => 'Review architectural designs',
            'status' => 'completed',
            'priority' => 'medium',
            'due_date' => now()->subDays(1),
            'assigned_to' => $this->user->id,
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        Task::create([
            'title' => 'Material Order',
            'description' => 'Order construction materials',
            'status' => 'pending',
            'priority' => 'low',
            'due_date' => now()->addDays(14),
            'assigned_to' => $this->user->id,
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Create test RFIs
        RFI::create([
            'subject' => 'Foundation Specifications',
            'description' => 'Need clarification on foundation specifications',
            'status' => 'open',
            'priority' => 'high',
            'due_date' => now()->addDays(3),
            'discipline' => 'construction',
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        RFI::create([
            'subject' => 'Electrical Layout',
            'description' => 'Electrical layout approval needed',
            'status' => 'answered',
            'priority' => 'medium',
            'due_date' => now()->subDays(1),
            'discipline' => 'electrical',
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Create test alerts
        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Project milestone approaching',
            'type' => 'project',
            'severity' => 'medium',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => json_encode(['project_id' => $this->project->id])
        ]);

        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Budget utilization exceeds 80%',
            'type' => 'budget',
            'severity' => 'high',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => json_encode(['project_id' => $this->project->id])
        ]);

        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Task deadline approaching',
            'type' => 'schedule',
            'severity' => 'low',
            'is_read' => true,
            'triggered_at' => now()->subHours(2),
            'context' => json_encode(['project_id' => $this->project->id])
        ]);
    }

    /** @test */
    public function it_can_complete_full_dashboard_workflow()
    {
        // Step 1: Get initial dashboard
        $dashboardResponse = $this->getJson('/api/v1/dashboard');
        $dashboardResponse->assertStatus(200);
        $initialDashboard = $dashboardResponse->json('data');

        // Step 2: Get available widgets
        $widgetsResponse = $this->getJson('/api/v1/dashboard/widgets');
        $widgetsResponse->assertStatus(200);
        $availableWidgets = $widgetsResponse->json('data');

        $this->assertCount(4, $availableWidgets);

        // Step 3: Add widgets to dashboard
        $projectOverviewWidget = collect($availableWidgets)->firstWhere('code', 'project_overview');
        $taskProgressWidget = collect($availableWidgets)->firstWhere('code', 'task_progress');
        $budgetTrackingWidget = collect($availableWidgets)->firstWhere('code', 'budget_tracking');

        // Add Project Overview widget
        $addProjectOverviewResponse = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $projectOverviewWidget['id'],
            'config' => [
                'title' => 'Project Overview',
                'size' => 'large'
            ]
        ]);
        $addProjectOverviewResponse->assertStatus(200);
        $projectOverviewInstance = $addProjectOverviewResponse->json('data.widget_instance');

        // Add Task Progress widget
        $addTaskProgressResponse = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $taskProgressWidget['id'],
            'config' => [
                'title' => 'Task Progress',
                'size' => 'medium'
            ]
        ]);
        $addTaskProgressResponse->assertStatus(200);
        $taskProgressInstance = $addTaskProgressResponse->json('data.widget_instance');

        // Add Budget Tracking widget
        $addBudgetTrackingResponse = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $budgetTrackingWidget['id'],
            'config' => [
                'title' => 'Budget Tracking',
                'size' => 'large'
            ]
        ]);
        $addBudgetTrackingResponse->assertStatus(200);
        $budgetTrackingInstance = $addBudgetTrackingResponse->json('data.widget_instance');

        // Step 4: Verify widgets were added
        $updatedDashboardResponse = $this->getJson('/api/v1/dashboard');
        $updatedDashboardResponse->assertStatus(200);
        $updatedDashboard = $updatedDashboardResponse->json('data');
        $this->assertCount(3, $updatedDashboard['layout']);

        // Step 5: Update widget configurations
        $updateConfigResponse = $this->putJson("/api/v1/dashboard/widgets/{$projectOverviewInstance['id']}/config", [
            'config' => [
                'title' => 'Updated Project Overview',
                'size' => 'extra-large'
            ]
        ]);
        $updateConfigResponse->assertStatus(200);

        // Step 6: Update dashboard layout
        $layout = $updatedDashboard['layout'];
        $layout[0]['position'] = ['x' => 0, 'y' => 0];
        $layout[1]['position'] = ['x' => 8, 'y' => 0];
        $layout[2]['position'] = ['x' => 0, 'y' => 8];

        $updateLayoutResponse = $this->putJson('/api/v1/dashboard/layout', [
            'layout' => $layout
        ]);
        $updateLayoutResponse->assertStatus(200);

        // Step 7: Save user preferences
        $preferencesResponse = $this->postJson('/api/v1/dashboard/preferences', [
            'preferences' => [
                'theme' => 'dark',
                'refresh_interval' => 60,
                'compact_mode' => true,
                'show_widget_borders' => false
            ]
        ]);
        $preferencesResponse->assertStatus(200);

        // Step 8: Get dashboard metrics
        $metricsResponse = $this->getJson('/api/v1/dashboard/metrics');
        $metricsResponse->assertStatus(200);
        $metrics = $metricsResponse->json('data');
        $this->assertCount(3, $metrics);

        // Step 9: Get user alerts
        $alertsResponse = $this->getJson('/api/v1/dashboard/alerts');
        $alertsResponse->assertStatus(200);
        $alerts = $alertsResponse->json('data');
        $this->assertCount(3, $alerts);

        // Step 10: Mark alerts as read
        $unreadAlerts = collect($alerts)->where('is_read', false);
        foreach ($unreadAlerts as $alert) {
            $markReadResponse = $this->putJson("/api/v1/dashboard/alerts/{$alert['id']}/read");
            $markReadResponse->assertStatus(200);
        }

        // Step 11: Verify all alerts are read
        $finalAlertsResponse = $this->getJson('/api/v1/dashboard/alerts');
        $finalAlertsResponse->assertStatus(200);
        $finalAlerts = $finalAlertsResponse->json('data');
        $unreadCount = collect($finalAlerts)->where('is_read', false)->count();
        $this->assertEquals(0, $unreadCount);

        // Step 12: Remove a widget
        $removeWidgetResponse = $this->deleteJson("/api/v1/dashboard/widgets/{$taskProgressInstance['id']}");
        $removeWidgetResponse->assertStatus(200);

        // Step 13: Verify widget was removed
        $finalDashboardResponse = $this->getJson('/api/v1/dashboard');
        $finalDashboardResponse->assertStatus(200);
        $finalDashboard = $finalDashboardResponse->json('data');
        $this->assertCount(2, $finalDashboard['layout']);

        // Step 14: Reset dashboard to default
        $resetResponse = $this->postJson('/api/v1/dashboard/reset');
        $resetResponse->assertStatus(200);

        // Step 15: Verify dashboard was reset
        $resetDashboardResponse = $this->getJson('/api/v1/dashboard');
        $resetDashboardResponse->assertStatus(200);
        $resetDashboard = $resetDashboardResponse->json('data');
        $this->assertCount(0, $resetDashboard['layout']);
        $this->assertEquals('light', $resetDashboard['preferences']['theme']);
    }

    /** @test */
    public function it_can_complete_role_based_dashboard_workflow()
    {
        // Step 1: Get role-based dashboard
        $roleBasedResponse = $this->getJson('/api/v1/dashboard/role-based');
        $roleBasedResponse->assertStatus(200);
        $roleBasedData = $roleBasedResponse->json('data');

        $this->assertArrayHasKey('dashboard', $roleBasedData);
        $this->assertArrayHasKey('widgets', $roleBasedData);
        $this->assertArrayHasKey('metrics', $roleBasedData);
        $this->assertArrayHasKey('alerts', $roleBasedData);
        $this->assertArrayHasKey('permissions', $roleBasedData);
        $this->assertArrayHasKey('role_config', $roleBasedData);
        $this->assertArrayHasKey('project_context', $roleBasedData);

        // Step 2: Verify role configuration
        $roleConfig = $roleBasedData['role_config'];
        $this->assertEquals('Project Manager', $roleConfig['name']);
        $this->assertEquals('project_wide', $roleConfig['data_access']);
        $this->assertEquals('assigned', $roleConfig['project_access']);
        $this->assertEquals('full', $roleConfig['customization_level']);

        // Step 3: Verify permissions
        $permissions = $roleBasedData['permissions'];
        $this->assertContains('view', $permissions['dashboard']);
        $this->assertContains('edit', $permissions['dashboard']);
        $this->assertContains('share', $permissions['dashboard']);
        $this->assertContains('view_assigned', $permissions['projects']);
        $this->assertContains('edit_assigned', $permissions['projects']);

        // Step 4: Get role-specific widgets
        $roleWidgetsResponse = $this->getJson('/api/v1/dashboard/role-based/widgets');
        $roleWidgetsResponse->assertStatus(200);
        $roleWidgets = $roleWidgetsResponse->json('data.widgets');
        $this->assertCount(4, $roleWidgets);

        // Step 5: Get role-specific metrics
        $roleMetricsResponse = $this->getJson('/api/v1/dashboard/role-based/metrics');
        $roleMetricsResponse->assertStatus(200);
        $roleMetrics = $roleMetricsResponse->json('data.metrics');
        $this->assertCount(3, $roleMetrics);

        // Step 6: Get role-specific alerts
        $roleAlertsResponse = $this->getJson('/api/v1/dashboard/role-based/alerts');
        $roleAlertsResponse->assertStatus(200);
        $roleAlerts = $roleAlertsResponse->json('data.alerts');
        $this->assertCount(3, $roleAlerts);

        // Step 7: Get available projects
        $projectsResponse = $this->getJson('/api/v1/dashboard/role-based/projects');
        $projectsResponse->assertStatus(200);
        $projects = $projectsResponse->json('data.projects');
        $this->assertCount(1, $projects);
        $this->assertEquals($this->project->id, $projects[0]['id']);

        // Step 8: Switch project context
        $switchProjectResponse = $this->postJson('/api/v1/dashboard/role-based/switch-project', [
            'project_id' => $this->project->id
        ]);
        $switchProjectResponse->assertStatus(200);
        $switchedData = $switchProjectResponse->json('data');
        $this->assertArrayHasKey('dashboard', $switchedData);
        $this->assertArrayHasKey('project_context', $switchedData);

        // Step 9: Get dashboard summary
        $summaryResponse = $this->getJson('/api/v1/dashboard/role-based/summary');
        $summaryResponse->assertStatus(200);
        $summary = $summaryResponse->json('data');
        $this->assertArrayHasKey('user_info', $summary);
        $this->assertArrayHasKey('project_context', $summary);
        $this->assertArrayHasKey('generated_at', $summary);

        // Step 10: Get role permissions
        $permissionsResponse = $this->getJson('/api/v1/dashboard/role-based/permissions');
        $permissionsResponse->assertStatus(200);
        $permissionsData = $permissionsResponse->json('data');
        $this->assertArrayHasKey('permissions', $permissionsData);
        $this->assertArrayHasKey('user_role', $permissionsData);
        $this->assertArrayHasKey('role_name', $permissionsData);

        // Step 11: Get role configuration
        $roleConfigResponse = $this->getJson('/api/v1/dashboard/role-based/role-config');
        $roleConfigResponse->assertStatus(200);
        $roleConfigData = $roleConfigResponse->json('data');
        $this->assertArrayHasKey('role_config', $roleConfigData);
        $this->assertArrayHasKey('user_role', $roleConfigData);
        $this->assertArrayHasKey('tenant_id', $roleConfigData);
    }

    /** @test */
    public function it_can_complete_customization_workflow()
    {
        // Step 1: Get customizable dashboard
        $customizationResponse = $this->getJson('/api/v1/dashboard/customization/');
        $customizationResponse->assertStatus(200);
        $customizationData = $customizationResponse->json('data');

        $this->assertArrayHasKey('dashboard', $customizationData);
        $this->assertArrayHasKey('available_widgets', $customizationData);
        $this->assertArrayHasKey('widget_categories', $customizationData);
        $this->assertArrayHasKey('layout_templates', $customizationData);
        $this->assertArrayHasKey('customization_options', $customizationData);
        $this->assertArrayHasKey('permissions', $customizationData);

        // Step 2: Get available widgets for customization
        $availableWidgetsResponse = $this->getJson('/api/v1/dashboard/customization/widgets');
        $availableWidgetsResponse->assertStatus(200);
        $availableWidgets = $availableWidgetsResponse->json('data.widgets');
        $this->assertCount(4, $availableWidgets);

        // Step 3: Get layout templates
        $templatesResponse = $this->getJson('/api/v1/dashboard/customization/templates');
        $templatesResponse->assertStatus(200);
        $templates = $templatesResponse->json('data.templates');
        $this->assertIsArray($templates);

        // Step 4: Get customization options
        $optionsResponse = $this->getJson('/api/v1/dashboard/customization/options');
        $optionsResponse->assertStatus(200);
        $options = $optionsResponse->json('data');
        $this->assertArrayHasKey('widget_sizes', $options);
        $this->assertArrayHasKey('layout_grid', $options);
        $this->assertArrayHasKey('themes', $options);
        $this->assertArrayHasKey('refresh_intervals', $options);
        $this->assertArrayHasKey('permissions', $options);

        // Step 5: Add widgets via customization
        $projectOverviewWidget = collect($availableWidgets)->firstWhere('code', 'project_overview');
        $taskProgressWidget = collect($availableWidgets)->firstWhere('code', 'task_progress');

        $addWidget1Response = $this->postJson('/api/v1/dashboard/customization/widgets', [
            'widget_id' => $projectOverviewWidget['id'],
            'config' => [
                'title' => 'Custom Project Overview',
                'size' => 'large'
            ]
        ]);
        $addWidget1Response->assertStatus(200);
        $widget1Instance = $addWidget1Response->json('widget_instance');

        $addWidget2Response = $this->postJson('/api/v1/dashboard/customization/widgets', [
            'widget_id' => $taskProgressWidget['id'],
            'config' => [
                'title' => 'Custom Task Progress',
                'size' => 'medium'
            ]
        ]);
        $addWidget2Response->assertStatus(200);
        $widget2Instance = $addWidget2Response->json('widget_instance');

        // Step 6: Update widget configurations
        $updateConfig1Response = $this->putJson("/api/v1/dashboard/customization/widgets/{$widget1Instance['id']}/config", [
            'config' => [
                'title' => 'Updated Project Overview',
                'size' => 'extra-large'
            ]
        ]);
        $updateConfig1Response->assertStatus(200);

        $updateConfig2Response = $this->putJson("/api/v1/dashboard/customization/widgets/{$widget2Instance['id']}/config", [
            'config' => [
                'title' => 'Updated Task Progress',
                'size' => 'large'
            ]
        ]);
        $updateConfig2Response->assertStatus(200);

        // Step 7: Update layout
        $layout = [
            [
                'id' => $widget1Instance['id'],
                'widget_id' => $widget1Instance['widget_id'],
                'type' => $widget1Instance['type'],
                'title' => 'Updated Project Overview',
                'size' => 'extra-large',
                'position' => ['x' => 0, 'y' => 0],
                'config' => $widget1Instance['config'],
                'is_customizable' => $widget1Instance['is_customizable'],
                'created_at' => $widget1Instance['created_at']
            ],
            [
                'id' => $widget2Instance['id'],
                'widget_id' => $widget2Instance['widget_id'],
                'type' => $widget2Instance['type'],
                'title' => 'Updated Task Progress',
                'size' => 'large',
                'position' => ['x' => 8, 'y' => 0],
                'config' => $widget2Instance['config'],
                'is_customizable' => $widget2Instance['is_customizable'],
                'created_at' => $widget2Instance['created_at']
            ]
        ];

        $updateLayoutResponse = $this->putJson('/api/v1/dashboard/customization/layout', [
            'layout' => $layout
        ]);
        $updateLayoutResponse->assertStatus(200);

        // Step 8: Apply layout template
        $applyTemplateResponse = $this->postJson('/api/v1/dashboard/customization/apply-template', [
            'template_id' => 'project_manager'
        ]);
        $applyTemplateResponse->assertStatus(200);

        // Step 9: Save preferences
        $savePreferencesResponse = $this->postJson('/api/v1/dashboard/customization/preferences', [
            'preferences' => [
                'theme' => 'dark',
                'refresh_interval' => 120,
                'compact_mode' => false,
                'show_widget_borders' => true
            ]
        ]);
        $savePreferencesResponse->assertStatus(200);

        // Step 10: Export dashboard
        $exportResponse = $this->getJson('/api/v1/dashboard/customization/export');
        $exportResponse->assertStatus(200);
        $exportData = $exportResponse->json('data');
        $this->assertArrayHasKey('version', $exportData);
        $this->assertArrayHasKey('exported_at', $exportData);
        $this->assertArrayHasKey('user_role', $exportData);
        $this->assertArrayHasKey('dashboard', $exportData);
        $this->assertArrayHasKey('widgets', $exportData);

        // Step 11: Import dashboard
        $importResponse = $this->postJson('/api/v1/dashboard/customization/import', [
            'dashboard_config' => $exportData
        ]);
        $importResponse->assertStatus(200);

        // Step 12: Remove a widget
        $removeWidgetResponse = $this->deleteJson("/api/v1/dashboard/customization/widgets/{$widget2Instance['id']}");
        $removeWidgetResponse->assertStatus(200);

        // Step 13: Reset dashboard
        $resetResponse = $this->postJson('/api/v1/dashboard/customization/reset');
        $resetResponse->assertStatus(200);

        // Step 14: Verify dashboard was reset
        $finalCustomizationResponse = $this->getJson('/api/v1/dashboard/customization/');
        $finalCustomizationResponse->assertStatus(200);
        $finalDashboard = $finalCustomizationResponse->json('data.dashboard');
        $this->assertCount(0, $finalDashboard['layout']);
    }

    /** @test */
    public function it_can_handle_different_user_roles()
    {
        // Test QC Inspector role
        $qcUser = User::create([
            'name' => 'QC Inspector',
            'email' => 'qc@example.com',
            'password' => Hash::make('password'),
            'role' => 'qc_inspector',
            'tenant_id' => $this->tenant->id
        ]);

        $this->apiAs($qcUser, $this->tenant);

        // Get role-based dashboard for QC Inspector
        $roleBasedResponse = $this->getJson('/api/v1/dashboard/role-based');
        $roleBasedResponse->assertStatus(200);
        $roleBasedData = $roleBasedResponse->json('data');

        $roleConfig = $roleBasedData['role_config'];
        $this->assertEquals('QC Inspector', $roleConfig['name']);
        $this->assertEquals('quality_related', $roleConfig['data_access']);
        $this->assertEquals('read_only', $roleConfig['customization_level']);

        $permissions = $roleBasedData['permissions'];
        $this->assertContains('view', $permissions['dashboard']);
        $this->assertNotContains('edit', $permissions['dashboard']);
        $this->assertNotContains('share', $permissions['dashboard']);

        // Test Client Representative role
        $clientUser = User::create([
            'name' => 'Client Representative',
            'email' => 'client@example.com',
            'password' => Hash::make('password'),
            'role' => 'client_rep',
            'tenant_id' => $this->tenant->id
        ]);

        $this->apiAs($clientUser, $this->tenant);

        $clientRoleBasedResponse = $this->getJson('/api/v1/dashboard/role-based');
        $clientRoleBasedResponse->assertStatus(200);
        $clientRoleBasedData = $clientRoleBasedResponse->json('data');

        $clientRoleConfig = $clientRoleBasedData['role_config'];
        $this->assertEquals('Client Representative', $clientRoleConfig['name']);
        $this->assertEquals('client_view', $clientRoleConfig['data_access']);
        $this->assertEquals('read_only', $clientRoleConfig['customization_level']);

        $clientPermissions = $clientRoleBasedData['permissions'];
        $this->assertContains('view', $clientPermissions['dashboard']);
        $this->assertNotContains('edit', $clientPermissions['dashboard']);
        $this->assertNotContains('share', $clientPermissions['dashboard']);
    }

    /** @test */
    public function it_can_handle_permission_validation()
    {
        // Create QC Inspector user
        $qcUser = User::create([
            'name' => 'QC Inspector',
            'email' => 'qc@example.com',
            'password' => Hash::make('password'),
            'role' => 'qc_inspector',
            'tenant_id' => $this->tenant->id
        ]);

        $this->apiAs($qcUser, $this->tenant);

        // Try to add widget that QC Inspector doesn't have permission for
        $projectOverviewWidget = DashboardWidget::where('code', 'project_overview')->first();
        
        $addWidgetResponse = $this->postJson('/api/v1/dashboard/customization/widgets', [
            'widget_id' => $projectOverviewWidget->id
        ]);
        $addWidgetResponse->assertStatus(500);

        // Try to update dashboard layout
        $updateLayoutResponse = $this->putJson('/api/v1/dashboard/customization/layout', [
            'layout' => []
        ]);
        $updateLayoutResponse->assertStatus(500);

        // Try to save preferences
        $savePreferencesResponse = $this->postJson('/api/v1/dashboard/customization/preferences', [
            'preferences' => ['theme' => 'dark']
        ]);
        $savePreferencesResponse->assertStatus(500);
    }

    /** @test */
    public function it_can_handle_error_scenarios()
    {
        // Test invalid widget ID
        $invalidWidgetResponse = $this->postJson('/api/v1/dashboard/customization/widgets', [
            'widget_id' => 'invalid-widget-id'
        ]);
        $invalidWidgetResponse->assertStatus(422);

        // Test invalid project context
        $invalidProjectResponse = $this->postJson('/api/v1/dashboard/role-based/switch-project', [
            'project_id' => 'invalid-project-id'
        ]);
        $invalidProjectResponse->assertStatus(422);

        // Test invalid layout template
        $invalidTemplateResponse = $this->postJson('/api/v1/dashboard/customization/apply-template', [
            'template_id' => 'invalid-template'
        ]);
        $invalidTemplateResponse->assertStatus(500);

        // Test invalid import data
        $invalidImportResponse = $this->postJson('/api/v1/dashboard/customization/import', [
            'dashboard_config' => [
                'version' => 'invalid-version'
            ]
        ]);
        $invalidImportResponse->assertStatus(422);
    }

    /** @test */
    public function it_can_handle_unauthorized_access()
    {
        // Clear authentication
        $this->flushHeaders();
        $this->withHeaders($this->apiHeadersForTenant((string) $this->tenant->id));

        $response = $this->getJson('/api/v1/dashboard');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/dashboard/role-based');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/dashboard/customization/');
        $response->assertStatus(401);
    }
}
