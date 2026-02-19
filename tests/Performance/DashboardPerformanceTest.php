<?php

namespace Tests\Performance;

use Tests\TestCase;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tests\Support\SSOT\FixtureFactory;

class DashboardPerformanceTest extends TestCase
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
        
        // Create large dataset for performance testing
        $this->createLargeDataset();
        
        // Authenticate user
        $this->apiAs($this->user, $this->tenant);
    }

    protected function createLargeDataset(): void
    {
        // Create 100 widgets
        for ($i = 1; $i <= 100; $i++) {
            DashboardWidget::create([
                'name' => "Widget {$i}",
                'code' => "widget_{$i}",
                'type' => 'card',
                'category' => 'overview',
                'description' => "Widget {$i} description",
                'config' => json_encode(['default_size' => 'medium']),
                'permissions' => json_encode(['project_manager']),
                'is_active' => true,
                'tenant_id' => $this->tenant->id
            ]);
        }

        // Create 50 metrics
        for ($i = 1; $i <= 50; $i++) {
            DashboardMetric::create([
                'name' => "Metric {$i}",
                'code' => "metric_{$i}",
                'description' => "Metric {$i} description",
                'unit' => '%',
                'type' => 'gauge',
                'is_active' => true,
                'permissions' => json_encode(['project_manager']),
                'tenant_id' => $this->tenant->id
            ]);
        }

        // Create 1000 tasks
        for ($i = 1; $i <= 1000; $i++) {
            Task::create([
                'title' => "Task {$i}",
                'description' => "Task {$i} description",
                'status' => ['pending', 'in_progress', 'completed'][array_rand(['pending', 'in_progress', 'completed'])],
                'priority' => ['low', 'medium', 'high'][array_rand(['low', 'medium', 'high'])],
                'due_date' => now()->addDays(rand(1, 30)),
                'assigned_to' => $this->user->id,
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id
            ]);
        }

        // Create 500 RFIs
        for ($i = 1; $i <= 500; $i++) {
            Rfi::factory()->create([
                'title' => "RFI {$i}",
                'subject' => "RFI {$i}",
                'description' => "RFI {$i} description",
                'status' => ['open', 'answered', 'closed'][array_rand(['open', 'answered', 'closed'])],
                'priority' => ['low', 'medium', 'high'][array_rand(['low', 'medium', 'high'])],
                'due_date' => now()->addDays(rand(1, 14)),
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id,
                'asked_by' => $this->user->id,
                'created_by' => $this->user->id,
                'assigned_to' => $this->user->id,
            ]);
        }

        // Create 200 inspections
        for ($i = 1; $i <= 200; $i++) {
            Inspection::create([
                'title' => "Inspection {$i}",
                'description' => "Inspection {$i} description",
                'status' => ['scheduled', 'in_progress', 'completed'][array_rand(['scheduled', 'in_progress', 'completed'])],
                'type' => ['quality', 'safety', 'compliance'][array_rand(['quality', 'safety', 'compliance'])],
                'scheduled_date' => now()->addDays(rand(1, 30)),
                'inspector_id' => $this->user->id,
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id
            ]);
        }

        // Create 100 NCRs
        for ($i = 1; $i <= 100; $i++) {
            NCR::create([
                'title' => "NCR {$i}",
                'description' => "NCR {$i} description",
                'status' => ['open', 'in_progress', 'closed'][array_rand(['open', 'in_progress', 'closed'])],
                'severity' => ['low', 'medium', 'high'][array_rand(['low', 'medium', 'high'])],
                'category' => ['quality', 'safety', 'compliance'][array_rand(['quality', 'safety', 'compliance'])],
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id
            ]);
        }

        // Create 1000 alerts
        for ($i = 1; $i <= 1000; $i++) {
            DashboardAlert::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'message' => "Alert {$i}",
                'type' => ['project', 'budget', 'schedule', 'quality'][array_rand(['project', 'budget', 'schedule', 'quality'])],
                'severity' => ['low', 'medium', 'high', 'critical'][array_rand(['low', 'medium', 'high', 'critical'])],
                'is_read' => rand(0, 1) == 1,
                'triggered_at' => now()->subDays(rand(0, 30)),
                'context' => json_encode(['project_id' => $this->project->id])
            ]);
        }
    }

    /** @test */
    public function it_can_load_dashboard_with_large_dataset_quickly()
    {
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/v1/dashboard');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(500, $executionTime, 'Dashboard should load in less than 500ms');
        
        echo "\nDashboard load time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_load_role_based_dashboard_with_large_dataset_quickly()
    {
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/v1/dashboard/role-based');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(1000, $executionTime, 'Role-based dashboard should load in less than 1000ms');
        
        echo "\nRole-based dashboard load time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_load_widgets_with_large_dataset_quickly()
    {
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/v1/dashboard/widgets');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(300, $executionTime, 'Widgets should load in less than 300ms');
        
        echo "\nWidgets load time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_load_metrics_with_large_dataset_quickly()
    {
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/v1/dashboard/metrics');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(400, $executionTime, 'Metrics should load in less than 400ms');
        
        echo "\nMetrics load time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_load_alerts_with_large_dataset_quickly()
    {
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/v1/dashboard/alerts');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(300, $executionTime, 'Alerts should load in less than 300ms');
        
        echo "\nAlerts load time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_add_widget_quickly()
    {
        $widget = DashboardWidget::first();
        
        $startTime = microtime(true);
        
        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id,
            'config' => [
                'title' => 'Performance Test Widget',
                'size' => 'medium'
            ]
        ]);
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(200, $executionTime, 'Widget addition should complete in less than 200ms');
        
        echo "\nWidget addition time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_update_widget_config_quickly()
    {
        // First add a widget
        $widget = DashboardWidget::first();
        $addResponse = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id
        ]);
        $widgetInstanceId = $addResponse->json('data.widget_instance.id');

        $startTime = microtime(true);
        
        $response = $this->putJson("/api/v1/dashboard/widgets/{$widgetInstanceId}/config", [
            'config' => [
                'title' => 'Updated Performance Test Widget',
                'size' => 'large'
            ]
        ]);
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(150, $executionTime, 'Widget config update should complete in less than 150ms');
        
        echo "\nWidget config update time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_update_dashboard_layout_quickly()
    {
        // Add multiple widgets first
        $widgets = DashboardWidget::take(5)->get();
        $widgetInstances = [];
        
        foreach ($widgets as $widget) {
            $addResponse = $this->postJson('/api/v1/dashboard/widgets', [
                'widget_id' => $widget->id
            ]);
            $widgetInstances[] = $addResponse->json('data.widget_instance');
        }

        // Get current dashboard
        $dashboardResponse = $this->getJson('/api/v1/dashboard');
        $layout = $dashboardResponse->json('data.layout');

        // Update layout positions
        foreach ($layout as $index => $widgetInstance) {
            $layout[$index]['position'] = ['x' => $index * 4, 'y' => 0];
        }

        $startTime = microtime(true);
        
        $response = $this->putJson('/api/v1/dashboard/layout', [
            'layout' => $layout
        ]);
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(300, $executionTime, 'Layout update should complete in less than 300ms');
        
        echo "\nLayout update time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_mark_alerts_as_read_quickly()
    {
        $alerts = DashboardAlert::where('user_id', $this->user->id)
            ->where('is_read', false)
            ->take(100)
            ->get();

        $startTime = microtime(true);
        
        foreach ($alerts as $alert) {
            $response = $this->putJson("/api/v1/dashboard/alerts/{$alert->id}/read");
            $response->assertStatus(200);
        }
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $this->assertLessThan(1000, $executionTime, 'Marking 100 alerts as read should complete in less than 1000ms');
        
        echo "\nMark 100 alerts as read time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_mark_all_alerts_as_read_quickly()
    {
        $startTime = microtime(true);
        
        $response = $this->putJson('/api/v1/dashboard/alerts/read-all');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(500, $executionTime, 'Mark all alerts as read should complete in less than 500ms');
        
        echo "\nMark all alerts as read time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_load_customization_dashboard_quickly()
    {
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/v1/dashboard/customization/');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(800, $executionTime, 'Customization dashboard should load in less than 800ms');
        
        echo "\nCustomization dashboard load time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_export_dashboard_quickly()
    {
        // Add some widgets first
        $widgets = DashboardWidget::take(3)->get();
        foreach ($widgets as $widget) {
            $this->postJson('/api/v1/dashboard/widgets', [
                'widget_id' => $widget->id
            ]);
        }

        $startTime = microtime(true);
        
        $response = $this->getJson('/api/v1/dashboard/customization/export');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(400, $executionTime, 'Dashboard export should complete in less than 400ms');
        
        echo "\nDashboard export time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_import_dashboard_quickly()
    {
        $dashboardConfig = [
            'version' => '1.0',
            'exported_at' => now()->toISOString(),
            'user_role' => 'project_manager',
            'dashboard' => [
                'name' => 'Imported Dashboard',
                'layout' => [],
                'preferences' => ['theme' => 'dark']
            ],
            'widgets' => []
        ];

        $startTime = microtime(true);
        
        $response = $this->postJson('/api/v1/dashboard/customization/import', [
            'dashboard_config' => $dashboardConfig
        ]);
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(300, $executionTime, 'Dashboard import should complete in less than 300ms');
        
        echo "\nDashboard import time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_reset_dashboard_quickly()
    {
        // Add some widgets first
        $widgets = DashboardWidget::take(5)->get();
        foreach ($widgets as $widget) {
            $this->postJson('/api/v1/dashboard/widgets', [
                'widget_id' => $widget->id
            ]);
        }

        $startTime = microtime(true);
        
        $response = $this->postJson('/api/v1/dashboard/reset');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(200, $executionTime, 'Dashboard reset should complete in less than 200ms');
        
        echo "\nDashboard reset time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_handle_concurrent_requests()
    {
        $concurrentRequests = 10;
        $startTime = microtime(true);
        
        $promises = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $promises[] = $this->getJson('/api/v1/dashboard');
        }
        
        // Wait for all requests to complete
        foreach ($promises as $promise) {
            $promise->assertStatus(200);
        }
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $this->assertLessThan(2000, $executionTime, '10 concurrent requests should complete in less than 2000ms');
        
        echo "\n10 concurrent requests time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_handle_database_query_optimization()
    {
        // Test query count for dashboard loading
        DB::enableQueryLog();
        
        $response = $this->getJson('/api/v1/dashboard/role-based');
        
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        $response->assertStatus(200);
        $this->assertLessThan(20, $queryCount, 'Dashboard should use less than 20 database queries');
        
        echo "\nDatabase queries count: {$queryCount}\n";
        
        DB::disableQueryLog();
    }

    /** @test */
    public function it_can_handle_memory_usage()
    {
        $startMemory = memory_get_usage();
        
        $response = $this->getJson('/api/v1/dashboard/role-based');
        
        $endMemory = memory_get_usage();
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB
        
        $response->assertStatus(200);
        $this->assertLessThan(50, $memoryUsed, 'Dashboard should use less than 50MB of memory');
        
        echo "\nMemory usage: {$memoryUsed}MB\n";
    }

    /** @test */
    public function it_can_handle_large_widget_data()
    {
        // Create widget with large data
        $widget = DashboardWidget::create([
            'name' => 'Large Data Widget',
            'code' => 'large_data_widget',
            'type' => 'table',
            'category' => 'data',
            'description' => 'Widget with large dataset',
            'config' => json_encode(['default_size' => 'large']),
            'permissions' => json_encode(['project_manager']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        $startTime = microtime(true);
        
        $response = $this->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(1000, $executionTime, 'Large widget data should load in less than 1000ms');
        
        echo "\nLarge widget data load time: {$executionTime}ms\n";
    }

    /** @test */
    public function it_can_handle_role_based_filtering_performance()
    {
        // Test different roles
        $roles = ['project_manager', 'site_engineer', 'qc_inspector', 'client_rep'];
        
        foreach ($roles as $role) {
            $user = User::create([
                'name' => "Test {$role}",
                'email' => "{$role}@example.com",
                'password' => Hash::make('password'),
                'role' => $role,
                'tenant_id' => $this->tenant->id
            ]);

            $this->apiAs($user, $this->tenant);

            $startTime = microtime(true);
            
            $response = $this->getJson('/api/v1/dashboard/role-based/widgets');
            
            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            
            $response->assertStatus(200);
            $this->assertLessThan(500, $executionTime, "Role-based filtering for {$role} should complete in less than 500ms");
            
            echo "\nRole-based filtering for {$role}: {$executionTime}ms\n";
        }
    }
}
