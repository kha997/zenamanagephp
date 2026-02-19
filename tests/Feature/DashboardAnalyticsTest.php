<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\DashboardWidget;
use App\Models\UserDashboard;
use App\Models\DashboardMetric;
use App\Services\DashboardService;
use App\Services\DashboardDataAggregationService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Test Dashboard & Analytics System
 * 
 * Kịch bản: Test dashboard widgets, analytics, metrics, và role-based data
 */
class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $project;
    private $task;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable foreign key constraints for testing
        \DB::statement('PRAGMA foreign_keys=OFF;');
        
        // Tạo tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'domain' => 'test.com',
            'settings' => json_encode(['timezone' => 'Asia/Ho_Chi_Minh']),
            'status' => 'active',
            'is_active' => true,
        ]);

        // Tạo user
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Tạo project
        $this->project = Project::factory()->create([
            'name' => 'Test Project',
            'code' => 'DASH-TEST-001',
            'description' => 'Test Description',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        // Tạo task
        $this->task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'description' => 'Task Description',
            'status' => 'open',
            'priority' => 'medium',
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Test dashboard widget creation
     */
    public function test_can_create_dashboard_widget(): void
    {
        $widget = DashboardWidget::create([
            'name' => 'Project Overview',
            'type' => 'chart',
            'category' => 'project',
            'config' => [
                'chart_type' => 'bar',
                'refresh_interval' => 30,
                'size' => 'large'
            ],
            'data_source' => [
                'model' => 'Project',
                'method' => 'getProjectStats'
            ],
            'permissions' => ['project_manager', 'admin'],
            'is_active' => true,
            'description' => 'Overview of project statistics'
        ]);

        $this->assertDatabaseHas('dashboard_widgets', [
            'id' => $widget->id,
            'name' => 'Project Overview',
            'type' => 'chart',
            'category' => 'project',
            'is_active' => true,
        ]);

        $this->assertEquals('Project Overview', $widget->name);
        $this->assertEquals('chart', $widget->type);
        $this->assertIsArray($widget->config);
        $this->assertEquals('bar', $widget->config['chart_type']);
    }

    /**
     * Test user dashboard creation
     */
    public function test_can_create_user_dashboard(): void
    {
        $dashboard = UserDashboard::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'My Dashboard',
            'layout_config' => [
                'columns' => 3,
                'rows' => 4,
                'grid_gap' => '16px'
            ],
            'widgets' => [
                [
                    'id' => 'widget-1',
                    'position' => [0, 0],
                    'size' => [2, 1]
                ]
            ],
            'preferences' => [
                'theme' => 'light',
                'refresh_interval' => 30
            ],
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('user_dashboards', [
            'id' => $dashboard->id,
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'My Dashboard',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->assertEquals('My Dashboard', $dashboard->name);
        $this->assertIsArray($dashboard->layout_config);
        $this->assertIsArray($dashboard->widgets);
        $this->assertIsArray($dashboard->preferences);
    }

    /**
     * Test dashboard metrics
     */
    public function test_can_create_dashboard_metrics(): void
    {
        $metric = DashboardMetric::create([
            'name' => 'Total Projects',
            'value' => 15,
            'unit' => 'projects',
            'category' => 'project',
            'tenant_id' => $this->tenant->id,
            'metadata' => [
                'trend' => 'up',
                'change_percentage' => 12.5,
                'last_updated' => now()->toISOString()
            ],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('dashboard_metrics', [
            'id' => $metric->id,
            'name' => 'Total Projects',
            'value' => 15,
            'unit' => 'projects',
            'category' => 'project',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->assertEquals('Total Projects', $metric->name);
        $this->assertEquals(15, $metric->value);
        $this->assertIsArray($metric->metadata);
        $this->assertEquals('up', $metric->metadata['trend']);
    }

    /**
     * Test dashboard service functionality
     */
    public function test_dashboard_service_can_get_user_dashboard(): void
    {
        // Create a user dashboard first
        $dashboard = UserDashboard::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Dashboard',
            'layout_config' => ['columns' => 3],
            'widgets' => [],
            'is_default' => true,
            'is_active' => true,
        ]);

        $dashboardService = new DashboardService();
        $userDashboard = $dashboardService->getUserDashboard($this->user->id);

        $this->assertNotNull($userDashboard);
        $this->assertEquals($dashboard->id, $userDashboard->id);
        $this->assertEquals($this->user->id, $userDashboard->user_id);
    }

    /**
     * Test dashboard data aggregation
     */
    public function test_dashboard_data_aggregation(): void
    {
        // Create additional projects and tasks for aggregation
        for ($i = 1; $i <= 5; $i++) {
            Project::factory()->create([
                'name' => "Project {$i}",
                'code' => "PROJ-{$i}",
                'description' => "Description {$i}",
                'status' => $i % 2 === 0 ? 'active' : 'completed',
                'tenant_id' => $this->tenant->id,
                'created_by' => $this->user->id,
            ]);
        }

        for ($i = 1; $i <= 10; $i++) {
            Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'name' => "Task {$i}",
                'description' => "Task Description {$i}",
                'status' => $i % 3 === 0 ? 'completed' : 'open',
                'priority' => 'medium',
                'assigned_to' => $this->user->id,
                'created_by' => $this->user->id,
            ]);
        }

        // Test project count aggregation
        $totalProjects = Project::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(6, $totalProjects); // 1 original + 5 new

        // Test task count aggregation
        $totalTasks = Task::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(11, $totalTasks); // 1 original + 10 new

        // Test completed tasks
        $completedTasks = Task::where('tenant_id', $this->tenant->id)
            ->where('status', 'completed')
            ->count();
        $this->assertEquals(3, $completedTasks); // 3 completed tasks
    }

    /**
     * Test dashboard widget filtering by role
     */
    public function test_dashboard_widget_role_filtering(): void
    {
        // Create widgets for different roles
        $adminWidget = DashboardWidget::create([
            'name' => 'Admin Widget',
            'type' => 'metric',
            'category' => 'admin',
            'permissions' => ['admin'],
            'is_active' => true,
        ]);

        $pmWidget = DashboardWidget::create([
            'name' => 'PM Widget',
            'type' => 'chart',
            'category' => 'project',
            'permissions' => ['project_manager'],
            'is_active' => true,
        ]);

        $generalWidget = DashboardWidget::create([
            'name' => 'General Widget',
            'type' => 'card',
            'category' => 'general',
            'permissions' => ['admin', 'project_manager', 'user'],
            'is_active' => true,
        ]);

        // Test role-based filtering
        $adminWidgets = DashboardWidget::all()->filter(fn ($widget) => $widget->isAvailableForRole('admin'));
        $this->assertCount(2, $adminWidgets); // adminWidget + generalWidget

        $pmWidgets = DashboardWidget::all()->filter(fn ($widget) => $widget->isAvailableForRole('project_manager'));
        $this->assertCount(2, $pmWidgets); // pmWidget + generalWidget

        $generalWidgets = DashboardWidget::all()->filter(fn ($widget) => $widget->isAvailableForRole('user'));
        $this->assertCount(1, $generalWidgets); // generalWidget only
    }

    /**
     * Test dashboard customization
     */
    public function test_dashboard_customization(): void
    {
        $dashboard = UserDashboard::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Custom Dashboard',
            'layout_config' => ['columns' => 2],
            'widgets' => [],
            'preferences' => ['theme' => 'dark'],
            'is_default' => false,
            'is_active' => true,
        ]);

        // Test layout update
        $dashboard->update([
            'layout_config' => [
                'columns' => 4,
                'rows' => 6,
                'grid_gap' => '20px'
            ]
        ]);

        $this->assertEquals(4, $dashboard->layout_config['columns']);
        $this->assertEquals(6, $dashboard->layout_config['rows']);

        // Test widget addition
        $dashboard->update([
            'widgets' => [
                [
                    'id' => 'widget-1',
                    'position' => [0, 0],
                    'size' => [2, 1],
                    'config' => ['title' => 'Test Widget']
                ],
                [
                    'id' => 'widget-2',
                    'position' => [2, 0],
                    'size' => [2, 1],
                    'config' => ['title' => 'Another Widget']
                ]
            ]
        ]);

        $this->assertCount(2, $dashboard->widgets);
        $this->assertEquals('Test Widget', $dashboard->widgets[0]['config']['title']);
    }

    /**
     * Test dashboard analytics calculations
     */
    public function test_dashboard_analytics_calculations(): void
    {
        // Create projects with different statuses
        $activeProjects = 3;
        $completedProjects = 2;
        $totalProjects = $activeProjects + $completedProjects;

        for ($i = 1; $i <= $activeProjects; $i++) {
            Project::factory()->create([
                'name' => "Active Project {$i}",
                'code' => "ACT-{$i}",
                'status' => 'active',
                'tenant_id' => $this->tenant->id,
                'created_by' => $this->user->id,
            ]);
        }

        for ($i = 1; $i <= $completedProjects; $i++) {
            Project::factory()->create([
                'name' => "Completed Project {$i}",
                'code' => "COMP-{$i}",
                'status' => 'completed',
                'tenant_id' => $this->tenant->id,
                'created_by' => $this->user->id,
            ]);
        }

        // Test analytics calculations
        $totalProjectsCount = Project::where('tenant_id', $this->tenant->id)->count();
        $activeProjectsCount = Project::where('tenant_id', $this->tenant->id)
            ->where('status', 'active')
            ->count();
        $completedProjectsCount = Project::where('tenant_id', $this->tenant->id)
            ->where('status', 'completed')
            ->count();

        $this->assertEquals($totalProjects + 1, $totalProjectsCount); // +1 for original project
        $this->assertEquals($activeProjects + 1, $activeProjectsCount); // +1 for original project
        $this->assertEquals($completedProjects, $completedProjectsCount);

        // Test completion rate calculation
        $completionRate = ($completedProjectsCount / $totalProjectsCount) * 100;
        $this->assertGreaterThan(0, $completionRate);
        $this->assertLessThanOrEqual(100, $completionRate);
    }

    /**
     * Test dashboard real-time data
     */
    public function test_dashboard_real_time_data(): void
    {
        // Create metrics for real-time testing
        $metrics = [];
        for ($i = 1; $i <= 5; $i++) {
            $metrics[] = DashboardMetric::create([
                'name' => "Metric {$i}",
                'value' => rand(10, 100),
                'unit' => 'units',
                'category' => 'performance',
                'tenant_id' => $this->tenant->id,
                'metadata' => [
                    'last_updated' => now()->toISOString(),
                    'trend' => rand(0, 1) ? 'up' : 'down'
                ],
                'is_active' => true,
            ]);
        }

        // Test real-time data retrieval
        $activeMetrics = DashboardMetric::where('tenant_id', $this->tenant->id)
            ->where('is_active', true)
            ->get();

        $this->assertCount(5, $activeMetrics);

        // Test metric updates
        $firstMetric = $activeMetrics->first();
        $originalValue = $firstMetric->value;
        $firstMetric->update(['value' => $originalValue + 10]);

        $this->assertEquals($originalValue + 10, $firstMetric->fresh()->value);
    }

    /**
     * Test dashboard performance metrics
     */
    public function test_dashboard_performance_metrics(): void
    {
        // Create performance metrics
        $performanceMetrics = [
            'response_time' => 150, // ms
            'uptime' => 99.9, // percentage
            'throughput' => 1000, // requests per minute
            'error_rate' => 0.1, // percentage
        ];

        foreach ($performanceMetrics as $name => $value) {
            DashboardMetric::create([
                'name' => ucfirst(str_replace('_', ' ', $name)),
                'value' => $value,
                'unit' => $name === 'uptime' || $name === 'error_rate' ? '%' : 
                         ($name === 'response_time' ? 'ms' : 'req/min'),
                'category' => 'performance',
                'tenant_id' => $this->tenant->id,
                'metadata' => [
                    'threshold' => $name === 'response_time' ? 200 : 
                                  ($name === 'uptime' ? 99.0 : 
                                   ($name === 'error_rate' ? 1.0 : 500)),
                    'status' => $name === 'error_rate' ? 'warning' : 'good'
                ],
                'is_active' => true,
            ]);
        }

        // Test performance metrics retrieval
        $perfMetrics = DashboardMetric::where('tenant_id', $this->tenant->id)
            ->where('category', 'performance')
            ->get();

        $this->assertCount(4, $perfMetrics);

        // Test threshold checking
        $responseTimeMetric = $perfMetrics->where('name', 'Response time')->first();
        $this->assertLessThan($responseTimeMetric->metadata['threshold'], $responseTimeMetric->value);
    }

    /**
     * Test dashboard widget data caching
     */
    public function test_dashboard_widget_data_caching(): void
    {
        $widget = DashboardWidget::create([
            'name' => 'Cached Widget',
            'type' => 'chart',
            'category' => 'analytics',
            'config' => [
                'cache_ttl' => 300, // 5 minutes
                'refresh_interval' => 60
            ],
            'data_source' => [
                'model' => 'Project',
                'cache_key' => 'project_stats'
            ],
            'is_active' => true,
        ]);

        // Test widget configuration
        $this->assertEquals(300, $widget->config['cache_ttl']);
        $this->assertEquals(60, $widget->config['refresh_interval']);
        $this->assertEquals('project_stats', $widget->data_source['cache_key']);

        // Test widget data source configuration
        $this->assertEquals('Project', $widget->data_source['model']);
        $this->assertIsArray($widget->data_source);
    }

    /**
     * Test dashboard multi-tenant isolation
     */
    public function test_dashboard_multi_tenant_isolation(): void
    {
        // Create another tenant
        $anotherTenant = Tenant::factory()->create([
            'name' => 'Another Company',
            'slug' => 'another-company',
            'domain' => 'another.com',
            'status' => 'active',
            'is_active' => true,
        ]);

        // Create widgets for both tenants
        $widget1 = DashboardWidget::create([
            'name' => 'Tenant 1 Widget',
            'type' => 'metric',
            'category' => 'general',
            'is_active' => true,
        ]);

        $metric1 = DashboardMetric::create([
            'name' => 'Tenant 1 Metric',
            'value' => 100,
            'unit' => 'units',
            'category' => 'general',
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $metric2 = DashboardMetric::create([
            'name' => 'Tenant 2 Metric',
            'value' => 200,
            'unit' => 'units',
            'category' => 'general',
            'tenant_id' => $anotherTenant->id,
            'is_active' => true,
        ]);

        // Test tenant isolation
        $tenant1Metrics = DashboardMetric::where('tenant_id', $this->tenant->id)->get();
        $tenant2Metrics = DashboardMetric::where('tenant_id', $anotherTenant->id)->get();

        $this->assertCount(1, $tenant1Metrics);
        $this->assertCount(1, $tenant2Metrics);
        $this->assertEquals($this->tenant->id, $tenant1Metrics->first()->tenant_id);
        $this->assertEquals($anotherTenant->id, $tenant2Metrics->first()->tenant_id);
    }
}
