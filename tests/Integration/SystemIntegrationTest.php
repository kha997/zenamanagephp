<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserDashboard;
use App\Models\DashboardWidget;
use App\Models\DashboardMetric;
use App\Models\DashboardAlert;
use App\Models\Project;
use App\Models\Task;
use App\Models\RFI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;

class SystemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $project;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = \App\Models\Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.com',
            'is_active' => true
        ]);
        
        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => 'project_manager',
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create test project
        $this->project = Project::create([
            'name' => 'Test Project',
            'description' => 'Test project description',
            'status' => 'active',
            'budget' => 100000,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create comprehensive test data
        $this->createComprehensiveTestData();
        
        // Authenticate user
        Sanctum::actingAs($this->user);
    }

    protected function createComprehensiveTestData(): void
    {
        // Create all widget types
        $this->createAllWidgetTypes();
        
        // Create all metric types
        $this->createAllMetricTypes();
        
        // Create comprehensive project data
        $this->createComprehensiveProjectData();
        
        // Create alerts for all types
        $this->createAllAlertTypes();
    }

    protected function createAllWidgetTypes(): void
    {
        // Card widgets
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
            'name' => 'Budget Summary',
            'code' => 'budget_summary',
            'type' => 'card',
            'category' => 'financial',
            'description' => 'Budget summary widget',
            'config' => json_encode(['default_size' => 'medium']),
            'permissions' => json_encode(['project_manager', 'client_rep']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        // Chart widgets
        DashboardWidget::create([
            'name' => 'Task Progress Chart',
            'code' => 'task_progress_chart',
            'type' => 'chart',
            'category' => 'tasks',
            'description' => 'Task progress chart widget',
            'config' => json_encode(['default_size' => 'large', 'chart_type' => 'line']),
            'permissions' => json_encode(['project_manager', 'site_engineer']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        DashboardWidget::create([
            'name' => 'Budget Utilization Chart',
            'code' => 'budget_utilization_chart',
            'type' => 'chart',
            'category' => 'financial',
            'description' => 'Budget utilization chart widget',
            'config' => json_encode(['default_size' => 'medium', 'chart_type' => 'pie']),
            'permissions' => json_encode(['project_manager', 'client_rep']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        // Table widgets
        DashboardWidget::create([
            'name' => 'RFI Status Table',
            'code' => 'rfi_status_table',
            'type' => 'table',
            'category' => 'communication',
            'description' => 'RFI status table widget',
            'config' => json_encode(['default_size' => 'large', 'columns' => ['subject', 'status', 'priority']]),
            'permissions' => json_encode(['project_manager', 'design_lead']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        DashboardWidget::create([
            'name' => 'Task List Table',
            'code' => 'task_list_table',
            'type' => 'table',
            'category' => 'tasks',
            'description' => 'Task list table widget',
            'config' => json_encode(['default_size' => 'large', 'columns' => ['title', 'status', 'due_date']]),
            'permissions' => json_encode(['project_manager', 'site_engineer']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        // Alert widgets
        DashboardWidget::create([
            'name' => 'System Alerts',
            'code' => 'system_alerts',
            'type' => 'alert',
            'category' => 'alerts',
            'description' => 'System alerts widget',
            'config' => json_encode(['default_size' => 'medium', 'alert_types' => ['system', 'security']]),
            'permissions' => json_encode(['system_admin']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        DashboardWidget::create([
            'name' => 'Project Alerts',
            'code' => 'project_alerts',
            'type' => 'alert',
            'category' => 'alerts',
            'description' => 'Project alerts widget',
            'config' => json_encode(['default_size' => 'medium', 'alert_types' => ['project', 'budget', 'schedule']]),
            'permissions' => json_encode(['project_manager', 'site_engineer']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        // Timeline widgets
        DashboardWidget::create([
            'name' => 'Project Timeline',
            'code' => 'project_timeline',
            'type' => 'timeline',
            'category' => 'schedule',
            'description' => 'Project timeline widget',
            'config' => json_encode(['default_size' => 'large', 'timeline_type' => 'gantt']),
            'permissions' => json_encode(['project_manager', 'site_engineer']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        DashboardWidget::create([
            'name' => 'Milestone Timeline',
            'code' => 'milestone_timeline',
            'type' => 'timeline',
            'category' => 'schedule',
            'description' => 'Milestone timeline widget',
            'config' => json_encode(['default_size' => 'medium', 'timeline_type' => 'milestone']),
            'permissions' => json_encode(['project_manager', 'client_rep']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        // Progress widgets
        DashboardWidget::create([
            'name' => 'Overall Progress',
            'code' => 'overall_progress',
            'type' => 'progress',
            'category' => 'overview',
            'description' => 'Overall progress widget',
            'config' => json_encode(['default_size' => 'medium', 'progress_type' => 'circular']),
            'permissions' => json_encode(['project_manager', 'site_engineer', 'client_rep']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        DashboardWidget::create([
            'name' => 'Task Completion Progress',
            'code' => 'task_completion_progress',
            'type' => 'progress',
            'category' => 'tasks',
            'description' => 'Task completion progress widget',
            'config' => json_encode(['default_size' => 'medium', 'progress_type' => 'linear']),
            'permissions' => json_encode(['project_manager', 'site_engineer']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);
    }

    protected function createAllMetricTypes(): void
    {
        // Gauge metrics
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

        // Counter metrics
        DashboardMetric::create([
            'name' => 'Total Tasks',
            'code' => 'total_tasks',
            'description' => 'Total number of tasks',
            'unit' => 'count',
            'type' => 'counter',
            'is_active' => true,
            'permissions' => json_encode(['project_manager', 'site_engineer']),
            'tenant_id' => $this->tenant->id
        ]);

        DashboardMetric::create([
            'name' => 'Open RFIs',
            'code' => 'open_rfis',
            'description' => 'Number of open RFIs',
            'unit' => 'count',
            'type' => 'counter',
            'is_active' => true,
            'permissions' => json_encode(['project_manager', 'design_lead']),
            'tenant_id' => $this->tenant->id
        ]);

        // Histogram metrics
        DashboardMetric::create([
            'name' => 'Task Duration Distribution',
            'code' => 'task_duration_distribution',
            'description' => 'Distribution of task durations',
            'unit' => 'hours',
            'type' => 'histogram',
            'is_active' => true,
            'permissions' => json_encode(['project_manager', 'site_engineer']),
            'tenant_id' => $this->tenant->id
        ]);

        DashboardMetric::create([
            'name' => 'Budget Variance Distribution',
            'code' => 'budget_variance_distribution',
            'description' => 'Distribution of budget variances',
            'unit' => '%',
            'type' => 'histogram',
            'is_active' => true,
            'permissions' => json_encode(['project_manager', 'client_rep']),
            'tenant_id' => $this->tenant->id
        ]);

        // Summary metrics
        DashboardMetric::create([
            'name' => 'Project Summary',
            'code' => 'project_summary',
            'description' => 'Project summary statistics',
            'unit' => 'summary',
            'type' => 'summary',
            'is_active' => true,
            'permissions' => json_encode(['project_manager', 'site_engineer', 'client_rep']),
            'tenant_id' => $this->tenant->id
        ]);
    }

    protected function createComprehensiveProjectData(): void
    {
        // Create tasks with different statuses
        $taskStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        $priorities = ['low', 'medium', 'high', 'critical'];
        
        for ($i = 1; $i <= 50; $i++) {
            Task::create([
                'title' => "Task {$i}",
                'description' => "Description for task {$i}",
                'status' => $taskStatuses[array_rand($taskStatuses)],
                'priority' => $priorities[array_rand($priorities)],
                'due_date' => now()->addDays(rand(1, 30)),
                'assigned_to' => $this->user->id,
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id
            ]);
        }

        // Create RFIs with different statuses
        $rfiStatuses = ['open', 'answered', 'closed'];
        $disciplines = ['construction', 'electrical', 'mechanical', 'plumbing', 'hvac'];
        
        for ($i = 1; $i <= 25; $i++) {
            RFI::create([
                'subject' => "RFI {$i}",
                'description' => "Description for RFI {$i}",
                'status' => $rfiStatuses[array_rand($rfiStatuses)],
                'priority' => $priorities[array_rand($priorities)],
                'due_date' => now()->addDays(rand(1, 14)),
                'discipline' => $disciplines[array_rand($disciplines)],
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id
            ]);
        }
    }

    protected function createAllAlertTypes(): void
    {
        $alertTypes = ['project', 'budget', 'schedule', 'quality', 'safety', 'system'];
        $severities = ['low', 'medium', 'high', 'critical'];
        
        for ($i = 1; $i <= 20; $i++) {
            DashboardAlert::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'message' => "Alert message {$i}",
                'type' => $alertTypes[array_rand($alertTypes)],
                'severity' => $severities[array_rand($severities)],
                'is_read' => rand(0, 1) == 1,
                'triggered_at' => now()->subDays(rand(0, 30)),
                'context' => json_encode(['project_id' => $this->project->id])
            ]);
        }
    }

    /** @test */
    public function it_can_complete_full_system_workflow()
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

        // Step 2: Add multiple widgets of different types
        $widgets = DashboardWidget::where('permissions', 'like', '%project_manager%')->take(5)->get();
        
        $addedWidgets = [];
        foreach ($widgets as $widget) {
            $addResponse = $this->postJson('/api/v1/dashboard/widgets', [
                'widget_id' => $widget->id,
                'config' => [
                    'title' => "Custom {$widget->name}",
                    'size' => 'medium'
                ]
            ]);
            
            $addResponse->assertStatus(200);
            $addedWidgets[] = $addResponse->json('data.widget_instance');
        }

        $this->assertCount(5, $addedWidgets);

        // Step 3: Update widget configurations
        foreach ($addedWidgets as $index => $widgetInstance) {
            $updateResponse = $this->putJson("/api/v1/dashboard/widgets/{$widgetInstance['id']}/config", [
                'config' => [
                    'title' => "Updated Widget {$index}",
                    'size' => 'large'
                ]
            ]);
            
            $updateResponse->assertStatus(200);
        }

        // Step 4: Update dashboard layout
        $dashboardResponse = $this->getJson('/api/v1/dashboard');
        $layout = $dashboardResponse->json('data.layout');

        // Arrange widgets in a grid
        foreach ($layout as $index => $widgetInstance) {
            $layout[$index]['position'] = [
                'x' => ($index % 3) * 4,
                'y' => intval($index / 3) * 4
            ];
        }

        $layoutResponse = $this->putJson('/api/v1/dashboard/layout', [
            'layout' => $layout
        ]);
        
        $layoutResponse->assertStatus(200);

        // Step 5: Test all widget data endpoints
        foreach ($addedWidgets as $widgetInstance) {
            $widget = DashboardWidget::find($widgetInstance['widget_id']);
            
            $dataResponse = $this->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");
            $dataResponse->assertStatus(200);
            
            $data = $dataResponse->json('data');
            $this->assertIsArray($data);
        }

        // Step 6: Test metrics endpoints
        $metricsResponse = $this->getJson('/api/v1/dashboard/metrics');
        $metricsResponse->assertStatus(200);
        
        $metrics = $metricsResponse->json('data');
        $this->assertIsArray($metrics);
        $this->assertGreaterThan(0, count($metrics));

        // Step 7: Test alerts management
        $alertsResponse = $this->getJson('/api/v1/dashboard/alerts');
        $alertsResponse->assertStatus(200);
        
        $alerts = $alertsResponse->json('data');
        $this->assertIsArray($alerts);

        // Mark some alerts as read
        $unreadAlerts = array_filter($alerts, function($alert) {
            return !$alert['is_read'];
        });

        if (count($unreadAlerts) > 0) {
            $firstAlert = $unreadAlerts[0];
            
            $markReadResponse = $this->putJson("/api/v1/dashboard/alerts/{$firstAlert['id']}/read");
            $markReadResponse->assertStatus(200);
        }

        // Step 8: Test customization features
        $customizationResponse = $this->getJson('/api/v1/dashboard/customization/');
        $customizationResponse->assertStatus(200);
        
        $customizationData = $customizationResponse->json('data');
        $this->assertArrayHasKey('available_widgets', $customizationData);
        $this->assertArrayHasKey('customization_options', $customizationData);

        // Step 9: Test preferences
        $preferencesResponse = $this->postJson('/api/v1/dashboard/preferences', [
            'preferences' => [
                'theme' => 'dark',
                'refresh_interval' => 60,
                'compact_mode' => true,
                'show_widget_borders' => false
            ]
        ]);
        
        $preferencesResponse->assertStatus(200);

        // Step 10: Test export/import
        $exportResponse = $this->getJson('/api/v1/dashboard/customization/export');
        $exportResponse->assertStatus(200);
        
        $exportData = $exportResponse->json('data');
        $this->assertArrayHasKey('version', $exportData);
        $this->assertArrayHasKey('dashboard', $exportData);

        // Step 11: Test project context switching
        $switchProjectResponse = $this->postJson('/api/v1/dashboard/role-based/switch-project', [
            'project_id' => $this->project->id
        ]);
        
        $switchProjectResponse->assertStatus(200);

        // Step 12: Verify final dashboard state
        $finalDashboardResponse = $this->getJson('/api/v1/dashboard');
        $finalDashboardResponse->assertStatus(200);
        
        $finalDashboard = $finalDashboardResponse->json('data');
        $this->assertCount(5, $finalDashboard['layout']);
        $this->assertEquals('dark', $finalDashboard['preferences']['theme']);
    }

    /** @test */
    public function it_can_handle_all_widget_types()
    {
        $widgetTypes = ['card', 'chart', 'table', 'alert', 'timeline', 'progress'];
        
        foreach ($widgetTypes as $type) {
            $widget = DashboardWidget::where('type', $type)->first();
            
            if ($widget) {
                // Add widget
                $addResponse = $this->postJson('/api/v1/dashboard/widgets', [
                    'widget_id' => $widget->id,
                    'config' => [
                        'title' => "Test {$type} Widget",
                        'size' => 'medium'
                    ]
                ]);
                
                $addResponse->assertStatus(200);
                
                // Get widget data
                $dataResponse = $this->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");
                $dataResponse->assertStatus(200);
                
                $data = $dataResponse->json('data');
                $this->assertIsArray($data);
                
                // Update widget config
                $widgetInstance = $addResponse->json('data.widget_instance');
                $updateResponse = $this->putJson("/api/v1/dashboard/widgets/{$widgetInstance['id']}/config", [
                    'config' => [
                        'title' => "Updated {$type} Widget",
                        'size' => 'large'
                    ]
                ]);
                
                $updateResponse->assertStatus(200);
                
                // Remove widget
                $removeResponse = $this->deleteJson("/api/v1/dashboard/widgets/{$widgetInstance['id']}");
                $removeResponse->assertStatus(200);
            }
        }
    }

    /** @test */
    public function it_can_handle_all_metric_types()
    {
        $metricTypes = ['gauge', 'counter', 'histogram', 'summary'];
        
        foreach ($metricTypes as $type) {
            $metric = DashboardMetric::where('type', $type)->first();
            
            if ($metric) {
                // Create metric value
                \App\Models\DashboardMetricValue::create([
                    'metric_id' => $metric->id,
                    'tenant_id' => $this->tenant->id,
                    'project_id' => $this->project->id,
                    'value' => rand(1, 100),
                    'timestamp' => now(),
                    'context' => json_encode(['test' => true])
                ]);
            }
        }

        // Test metrics endpoint
        $metricsResponse = $this->getJson('/api/v1/dashboard/metrics');
        $metricsResponse->assertStatus(200);
        
        $metrics = $metricsResponse->json('data');
        $this->assertIsArray($metrics);
        
        // Verify all metric types are present
        $presentTypes = array_unique(array_column($metrics, 'type'));
        foreach ($metricTypes as $type) {
            $this->assertContains($type, $presentTypes);
        }
    }

    /** @test */
    public function it_can_handle_all_alert_types()
    {
        $alertTypes = ['project', 'budget', 'schedule', 'quality', 'safety', 'system'];
        
        foreach ($alertTypes as $type) {
            // Create alert
            $alert = DashboardAlert::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'message' => "Test {$type} alert",
                'type' => $type,
                'severity' => 'medium',
                'is_read' => false,
                'triggered_at' => now(),
                'context' => json_encode(['test' => true])
            ]);

            // Test alert retrieval
            $alertsResponse = $this->getJson('/api/v1/dashboard/alerts');
            $alertsResponse->assertStatus(200);
            
            $alerts = $alertsResponse->json('data');
            $alertFound = false;
            
            foreach ($alerts as $alertData) {
                if ($alertData['id'] === $alert->id) {
                    $alertFound = true;
                    $this->assertEquals($type, $alertData['type']);
                    break;
                }
            }
            
            $this->assertTrue($alertFound, "Alert of type {$type} not found");
            
            // Mark alert as read
            $markReadResponse = $this->putJson("/api/v1/dashboard/alerts/{$alert->id}/read");
            $markReadResponse->assertStatus(200);
            
            // Verify alert is marked as read
            $alert->refresh();
            $this->assertTrue($alert->is_read);
        }
    }

    /** @test */
    public function it_can_handle_role_based_data_filtering()
    {
        $roles = ['project_manager', 'site_engineer', 'qc_inspector', 'client_rep'];
        
        foreach ($roles as $role) {
            // Create user with specific role
            $user = User::create([
                'name' => "Test {$role}",
                'email' => "{$role}@example.com",
                'password' => Hash::make('password'),
                'role' => $role,
                'tenant_id' => $this->tenant->id
            ]);

            Sanctum::actingAs($user);

            // Test role-based dashboard
            $roleBasedResponse = $this->getJson('/api/v1/dashboard/role-based');
            $roleBasedResponse->assertStatus(200);
            
            $roleBasedData = $roleBasedResponse->json('data');
            $this->assertArrayHasKey('role_config', $roleBasedData);
            
            $roleConfig = $roleBasedData['role_config'];
            $this->assertArrayHasKey('name', $roleConfig);
            $this->assertArrayHasKey('customization_level', $roleConfig);
            $this->assertArrayHasKey('data_access', $roleConfig);

            // Test role-based widgets
            $widgetsResponse = $this->getJson('/api/v1/dashboard/role-based/widgets');
            $widgetsResponse->assertStatus(200);
            
            $widgets = $widgetsResponse->json('data.widgets');
            $this->assertIsArray($widgets);

            // Test role-based metrics
            $metricsResponse = $this->getJson('/api/v1/dashboard/role-based/metrics');
            $metricsResponse->assertStatus(200);
            
            $metrics = $metricsResponse->json('data.metrics');
            $this->assertIsArray($metrics);

            // Test role-based alerts
            $alertsResponse = $this->getJson('/api/v1/dashboard/role-based/alerts');
            $alertsResponse->assertStatus(200);
            
            $alerts = $alertsResponse->json('data.alerts');
            $this->assertIsArray($alerts);

            // Test permissions
            $permissionsResponse = $this->getJson('/api/v1/dashboard/role-based/permissions');
            $permissionsResponse->assertStatus(200);
            
            $permissions = $permissionsResponse->json('data.permissions');
            $this->assertIsArray($permissions);
            $this->assertArrayHasKey('dashboard', $permissions);
            $this->assertArrayHasKey('widgets', $permissions);
        }
    }

    /** @test */
    public function it_can_handle_concurrent_requests()
    {
        $concurrentRequests = 10;
        $responses = [];
        
        // Make concurrent requests
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $responses[] = $this->getJson('/api/v1/dashboard/role-based');
        }
        
        // Verify all requests succeeded
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        // Verify data consistency
        $firstResponse = $responses[0]->json('data');
        foreach (array_slice($responses, 1) as $response) {
            $responseData = $response->json('data');
            $this->assertEquals($firstResponse['role_config']['name'], $responseData['role_config']['name']);
        }
    }

    /** @test */
    public function it_can_handle_large_datasets()
    {
        // Create large number of tasks
        for ($i = 1; $i <= 1000; $i++) {
            Task::create([
                'title' => "Task {$i}",
                'description' => "Description for task {$i}",
                'status' => 'pending',
                'priority' => 'medium',
                'due_date' => now()->addDays(rand(1, 30)),
                'assigned_to' => $this->user->id,
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id
            ]);
        }

        // Create large number of RFIs
        for ($i = 1; $i <= 500; $i++) {
            RFI::create([
                'subject' => "RFI {$i}",
                'description' => "Description for RFI {$i}",
                'status' => 'open',
                'priority' => 'medium',
                'due_date' => now()->addDays(rand(1, 14)),
                'discipline' => 'construction',
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id
            ]);
        }

        // Test dashboard performance with large datasets
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/v1/dashboard/role-based');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(2000, $executionTime, 'Dashboard should load in less than 2000ms with large datasets');
        
        // Test widget data performance
        $widget = DashboardWidget::where('code', 'project_overview')->first();
        
        $startTime = microtime(true);
        
        $dataResponse = $this->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;
        
        $dataResponse->assertStatus(200);
        $this->assertLessThan(1000, $executionTime, 'Widget data should load in less than 1000ms with large datasets');
    }

    /** @test */
    public function it_can_handle_database_transactions()
    {
        // Test transaction rollback on error
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        
        // Mock service to throw exception
        $this->mock(\App\Services\DashboardService::class, function ($mock) {
            $mock->shouldReceive('addWidget')->andThrow(new \Exception('Database error'));
        });

        $widget = DashboardWidget::first();
        
        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id
        ]);

        $response->assertStatus(500);
    }

    /** @test */
    public function it_can_handle_memory_usage()
    {
        $startMemory = memory_get_usage();
        
        // Perform multiple operations
        for ($i = 0; $i < 10; $i++) {
            $response = $this->getJson('/api/v1/dashboard/role-based');
            $response->assertStatus(200);
            
            $widgetsResponse = $this->getJson('/api/v1/dashboard/widgets');
            $widgetsResponse->assertStatus(200);
            
            $metricsResponse = $this->getJson('/api/v1/dashboard/metrics');
            $metricsResponse->assertStatus(200);
        }
        
        $endMemory = memory_get_usage();
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB
        
        $this->assertLessThan(100, $memoryUsed, 'Memory usage should be less than 100MB for 10 operations');
    }

    /** @test */
    public function it_can_handle_error_recovery()
    {
        // Test error handling and recovery
        $widget = DashboardWidget::first();
        
        // Test with invalid widget ID
        $invalidResponse = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => 'invalid-widget-id'
        ]);
        $invalidResponse->assertStatus(422);
        
        // Test with valid widget ID (should work)
        $validResponse = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id
        ]);
        $validResponse->assertStatus(200);
        
        // Test with invalid project context
        $invalidProjectResponse = $this->postJson('/api/v1/dashboard/role-based/switch-project', [
            'project_id' => 'invalid-project-id'
        ]);
        $invalidProjectResponse->assertStatus(422);
        
        // Test with valid project context (should work)
        $validProjectResponse = $this->postJson('/api/v1/dashboard/role-based/switch-project', [
            'project_id' => $this->project->id
        ]);
        $validProjectResponse->assertStatus(200);
    }
}
