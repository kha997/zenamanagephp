<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\DashboardWidget;
use App\Models\UserDashboard;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Tests\Support\SSOT\FixtureFactory;

/**
 * Test Dashboard & Analytics System (Simplified)
 * 
 * Kịch bản: Test dashboard analytics với dữ liệu có sẵn
 */
class DashboardAnalyticsSimpleTest extends TestCase
{
    use RefreshDatabase, FixtureFactory;

    private $tenant;
    private $user;
    private $project;
    private $task;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set cache driver to array for testing (lightweight, deterministic)
        config(['cache.default' => 'array']);
        
        // Clear cache properly
        Cache::flush();
        
        // Disable foreign key constraints for testing
        \DB::statement('PRAGMA foreign_keys=OFF;');
        
        // Tạo tenant
        $this->tenant = $this->createTenant([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'domain' => 'test.com',
            'settings' => json_encode(['timezone' => 'Asia/Ho_Chi_Minh']),
            'status' => 'active',
            'is_active' => true,
        ]);

        // Tạo user
        $this->user = $this->createTenantUserWithRbac($this->tenant, 'project_manager', 'project_manager', [], [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Tạo project
        $this->project = $this->createProjectForTenant($this->tenant, $this->user, [
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
     * Test dashboard metrics calculation
     */
    public function test_dashboard_metrics_calculation(): void
    {
        // Create tasks with different priorities
        $highPriorityTasks = 5;
        $mediumPriorityTasks = 8;
        $lowPriorityTasks = 3;

        for ($i = 1; $i <= $highPriorityTasks; $i++) {
            Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'name' => "High Priority Task {$i}",
                'description' => "High priority task {$i}",
                'status' => 'open',
                'priority' => 'high',
                'assigned_to' => $this->user->id,
                'created_by' => $this->user->id,
            ]);
        }

        for ($i = 1; $i <= $mediumPriorityTasks; $i++) {
            Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'name' => "Medium Priority Task {$i}",
                'description' => "Medium priority task {$i}",
                'status' => 'open',
                'priority' => 'medium',
                'assigned_to' => $this->user->id,
                'created_by' => $this->user->id,
            ]);
        }

        for ($i = 1; $i <= $lowPriorityTasks; $i++) {
            Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'name' => "Low Priority Task {$i}",
                'description' => "Low priority task {$i}",
                'status' => 'open',
                'priority' => 'low',
                'assigned_to' => $this->user->id,
                'created_by' => $this->user->id,
            ]);
        }

        // Test priority distribution
        $highPriorityCount = Task::where('tenant_id', $this->tenant->id)
            ->where('priority', 'high')
            ->count();
        $mediumPriorityCount = Task::where('tenant_id', $this->tenant->id)
            ->where('priority', 'medium')
            ->count();
        $lowPriorityCount = Task::where('tenant_id', $this->tenant->id)
            ->where('priority', 'low')
            ->count();

        $this->assertEquals($highPriorityTasks, $highPriorityCount);
        $this->assertEquals($mediumPriorityTasks + 1, $mediumPriorityCount); // +1 for original task
        $this->assertEquals($lowPriorityTasks, $lowPriorityCount);

        // Test total tasks
        $totalTasks = Task::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals($highPriorityTasks + $mediumPriorityTasks + $lowPriorityTasks + 1, $totalTasks);
    }

    /**
     * Test dashboard performance metrics
     */
    public function test_dashboard_performance_metrics(): void
    {
        // Create tasks with different completion times
        $completedTasks = 0;
        $overdueTasks = 0;
        $onTimeTasks = 0;

        // Create completed tasks
        for ($i = 1; $i <= 5; $i++) {
            Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'name' => "Completed Task {$i}",
                'description' => "Completed task {$i}",
                'status' => 'completed',
                'priority' => 'medium',
                'assigned_to' => $this->user->id,
                'created_by' => $this->user->id,
                'completed_at' => now()->subDays($i),
            ]);
            $completedTasks++;
        }

        // Create overdue tasks
        for ($i = 1; $i <= 3; $i++) {
            Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'name' => "Overdue Task {$i}",
                'description' => "Overdue task {$i}",
                'status' => 'open',
                'priority' => 'high',
                'assigned_to' => $this->user->id,
                'created_by' => $this->user->id,
                'end_date' => '2025-09-19 15:00:00', // Fixed overdue date
            ]);
            $overdueTasks++;
        }

        // Create on-time tasks
        for ($i = 1; $i <= 7; $i++) {
            Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'name' => "On-time Task {$i}",
                'description' => "On-time task {$i}",
                'status' => 'open',
                'priority' => 'medium',
                'assigned_to' => $this->user->id,
                'created_by' => $this->user->id,
                'end_date' => '2025-09-21 15:00:00', // Fixed future date
            ]);
            $onTimeTasks++;
        }

        // Test performance metrics
        $totalTasks = Task::where('tenant_id', $this->tenant->id)->count();
        $completedTasksCount = Task::where('tenant_id', $this->tenant->id)
            ->where('status', 'completed')
            ->count();
        $overdueTasksCount = Task::where('tenant_id', $this->tenant->id)
            ->where('end_date', '<', '2025-09-20 15:00:00')
            ->where('status', '!=', 'completed')
            ->count();

        $this->assertEquals($completedTasks + $overdueTasks + $onTimeTasks + 1, $totalTasks); // +1 for original task
        $this->assertEquals($completedTasks, $completedTasksCount);
        // Debug: dump actual data
        $overdueTasksActual = Task::where('tenant_id', $this->tenant->id)
            ->where('end_date', '<', '2025-09-20 15:00:00')
            ->where('status', '!=', 'completed')
            ->get(['name', 'end_date', 'status']);
        
        $this->assertEquals($overdueTasks, $overdueTasksCount, 
            "Overdue tasks: Expected {$overdueTasks}, Got {$overdueTasksCount}. " . 
            "Actual: " . $overdueTasksActual->toJson());

        // Test completion rate
        $completionRate = ($completedTasksCount / $totalTasks) * 100;
        $this->assertGreaterThan(0, $completionRate);
        $this->assertLessThanOrEqual(100, $completionRate);
    }

    /**
     * Test dashboard user activity metrics
     */
    public function test_dashboard_user_activity_metrics(): void
    {
        // Create additional users
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $users[] = User::factory()->create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => Hash::make('password123'),
                'tenant_id' => $this->tenant->id,
                'is_active' => true,
                'last_login_at' => now()->subDays($i),
            ]);
        }

        // Test user metrics
        $totalUsers = User::where('tenant_id', $this->tenant->id)->count();
        $activeUsers = User::where('tenant_id', $this->tenant->id)
            ->where('is_active', true)
            ->count();
        $recentUsers = User::where('tenant_id', $this->tenant->id)
            ->where('last_login_at', '>=', now()->subDays(7))
            ->count();

        $this->assertEquals(6, $totalUsers); // 1 original + 5 new
        $this->assertEquals(6, $activeUsers); // All users are active
        $this->assertGreaterThan(0, $recentUsers); // At least some recent users
    }

    /**
     * Test dashboard project status distribution
     */
    public function test_dashboard_project_status_distribution(): void
    {
        // Create projects with different statuses (deterministic)
        $statuses = ['active', 'completed', 'on_hold', 'cancelled'];
        $statusCounts = [
            'active' => 3,
            'completed' => 2,
            'on_hold' => 1,
            'cancelled' => 1,
        ];

        foreach ($statuses as $status) {
            $count = $statusCounts[$status];
            
            for ($i = 1; $i <= $count; $i++) {
                Project::factory()->create([
                    'name' => "{$status} Project {$i}",
                    'code' => strtoupper(substr($status, 0, 3)) . "-{$i}",
                    'description' => "Project with status {$status}",
                    'status' => $status,
                    'tenant_id' => $this->tenant->id,
                    'created_by' => $this->user->id,
                ]);
            }
        }

        // Test status distribution
        foreach ($statuses as $status) {
            $count = Project::where('tenant_id', $this->tenant->id)
                ->where('status', $status)
                ->count();
            $expectedCount = $statusCounts[$status];
            // Add 1 for original project if it has the same status
            if ($status === 'active') {
                $expectedCount += 1; // Original project is active
            }
            $this->assertEquals($expectedCount, $count);
        }

        // Test total projects
        $totalProjects = Project::where('tenant_id', $this->tenant->id)->count();
        $expectedTotal = array_sum($statusCounts) + 1; // +1 for original project
        $this->assertEquals($expectedTotal, $totalProjects);
    }

    /**
     * Test dashboard task assignment metrics
     */
    public function test_dashboard_task_assignment_metrics(): void
    {
        // Create additional users for assignment testing
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $users[] = User::factory()->create([
                'name' => "Assignee {$i}",
                'email' => "assignee{$i}@example.com",
                'password' => Hash::make('password123'),
                'tenant_id' => $this->tenant->id,
                'is_active' => true,
            ]);
        }

        // Create tasks assigned to different users (deterministic)
        $assignmentCounts = [
            $users[0]->id => 5,
            $users[1]->id => 3,
            $users[2]->id => 4,
        ];
        
        foreach ($users as $index => $user) {
            $count = $assignmentCounts[$user->id];
            
            for ($i = 1; $i <= $count; $i++) {
                Task::create([
                    'tenant_id' => $this->tenant->id,
                    'project_id' => $this->project->id,
                    'name' => "Task for User {$index} - {$i}",
                    'description' => "Task assigned to user {$index}",
                    'status' => 'open',
                    'priority' => 'medium',
                    'assigned_to' => $user->id,
                    'created_by' => $this->user->id,
                ]);
            }
        }

        // Test assignment metrics
        foreach ($users as $user) {
            $assignedTasks = Task::where('tenant_id', $this->tenant->id)
                ->where('assigned_to', $user->id)
                ->count();
            $this->assertEquals($assignmentCounts[$user->id], $assignedTasks);
        }

        // Test unassigned tasks
        $unassignedTasks = Task::where('tenant_id', $this->tenant->id)
            ->whereNull('assigned_to')
            ->count();
        $this->assertGreaterThanOrEqual(0, $unassignedTasks);
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

        // Create projects for both tenants
        Project::factory()->create([
            'name' => 'Tenant 1 Project',
            'code' => 'T1-PROJ',
            'description' => 'Project for tenant 1',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        Project::factory()->create([
            'name' => 'Tenant 2 Project',
            'code' => 'T2-PROJ',
            'description' => 'Project for tenant 2',
            'status' => 'active',
            'tenant_id' => $anotherTenant->id,
            'created_by' => $this->user->id,
        ]);

        // Test tenant isolation
        $tenant1Projects = Project::where('tenant_id', $this->tenant->id)->count();
        $tenant2Projects = Project::where('tenant_id', $anotherTenant->id)->count();

        $this->assertEquals(2, $tenant1Projects); // 1 original + 1 new
        $this->assertEquals(1, $tenant2Projects); // 1 new

        // Test cross-tenant access prevention
        $crossTenantProjects = Project::where('tenant_id', $this->tenant->id)
            ->where('name', 'Tenant 2 Project')
            ->count();
        $this->assertEquals(0, $crossTenantProjects);
    }

    /**
     * Test dashboard data filtering
     */
    public function test_dashboard_data_filtering(): void
    {
        // Create projects with different creation dates (fixed times)
        $today = Carbon::parse('2025-09-20 15:00:00');
        $yesterday = Carbon::parse('2025-09-19 15:00:00');
        $lastWeek = Carbon::parse('2025-09-13 15:00:00');

        $todayProject = Project::factory()->create([
            'name' => 'Today Project',
            'code' => 'TODAY',
            'description' => 'Project created today',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        DB::table('projects')->where('id', $todayProject->id)->update(['created_at' => $today]);

        $yesterdayProject = Project::factory()->create([
            'name' => 'Yesterday Project',
            'code' => 'YEST',
            'description' => 'Project created yesterday',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        DB::table('projects')->where('id', $yesterdayProject->id)->update(['created_at' => $yesterday]);

        $lastWeekProject = Project::factory()->create([
            'name' => 'Last Week Project',
            'code' => 'WEEK',
            'description' => 'Project created last week',
            'status' => 'completed',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        DB::table('projects')->where('id', $lastWeekProject->id)->update(['created_at' => $lastWeek]);

        // Test date filtering
        $todayProjects = Project::where('tenant_id', $this->tenant->id)
            ->whereDate('created_at', '2025-09-20')
            ->count();
        $this->assertGreaterThanOrEqual(1, $todayProjects); // At least 1 project created today

        $recentProjects = Project::where('tenant_id', $this->tenant->id)
            ->where('created_at', '>=', $yesterday)
            ->count();
        
        // Debug: dump actual data
        $recentProjectsActual = Project::where('tenant_id', $this->tenant->id)
            ->where('created_at', '>=', $yesterday)
            ->get(['name', 'created_at']);
        
        $this->assertEquals(3, $recentProjects, 
            "Recent projects: Expected 3, Got {$recentProjects}. " . 
            "Actual: " . $recentProjectsActual->toJson()); // Original + Today + Yesterday

        // Test status filtering
        $activeProjects = Project::where('tenant_id', $this->tenant->id)
            ->where('status', 'active')
            ->count();
        $this->assertEquals(3, $activeProjects); // Today + Yesterday + Original

        $completedProjects = Project::where('tenant_id', $this->tenant->id)
            ->where('status', 'completed')
            ->count();
        $this->assertEquals(1, $completedProjects); // Last Week
    }

    /**
     * Test dashboard widgets functionality
     */
    public function test_dashboard_widgets(): void
    {
        // Create dashboard widgets
        $widget1 = DashboardWidget::create([
            'name' => 'Project Count Widget',
            'type' => 'metric',
            'category' => 'overview',
            'config' => [
                'title' => 'Total Projects',
                'format' => 'number',
            ],
            'data_source' => [
                'model' => 'Project',
                'method' => 'count',
            ],
            'permissions' => ['view_dashboard'],
            'is_active' => true,
            'description' => 'Shows total number of projects',
        ]);

        $widget2 = DashboardWidget::create([
            'name' => 'Task Status Chart',
            'type' => 'chart',
            'category' => 'analytics',
            'config' => [
                'title' => 'Task Status Distribution',
                'chart_type' => 'pie',
            ],
            'data_source' => [
                'model' => 'Task',
                'method' => 'groupBy',
                'group_by' => 'status',
            ],
            'permissions' => ['view_dashboard'],
            'is_active' => true,
            'description' => 'Shows task status distribution',
        ]);

        // Test widget creation
        $this->assertDatabaseHas('dashboard_widgets', [
            'name' => 'Project Count Widget',
            'type' => 'metric',
            'category' => 'overview',
        ]);

        $this->assertDatabaseHas('dashboard_widgets', [
            'name' => 'Task Status Chart',
            'type' => 'chart',
            'category' => 'analytics',
        ]);

        // Test widget filtering
        $metricWidgets = DashboardWidget::where('type', 'metric')->count();
        $chartWidgets = DashboardWidget::where('type', 'chart')->count();
        $overviewWidgets = DashboardWidget::where('category', 'overview')->count();

        $this->assertEquals(1, $metricWidgets);
        $this->assertEquals(1, $chartWidgets);
        $this->assertEquals(1, $overviewWidgets);

        // Test active widgets
        $activeWidgets = DashboardWidget::where('is_active', true)->count();
        $this->assertEquals(2, $activeWidgets);
    }

    /**
     * Test user dashboards functionality
     */
    public function test_user_dashboards(): void
    {
        // Create user dashboard
        $dashboard = UserDashboard::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'My Dashboard',
            'layout_config' => [
                'columns' => 3,
                'rows' => 4,
                'widgets' => [
                    [
                        'id' => 'widget-1',
                        'x' => 0,
                        'y' => 0,
                        'width' => 1,
                        'height' => 1,
                    ],
                ],
            ],
            'widgets' => [
                'widget-1' => [
                    'type' => 'metric',
                    'config' => ['title' => 'Total Projects'],
                ],
            ],
            'preferences' => [
                'theme' => 'light',
                'refresh_interval' => 60,
            ],
            'is_default' => true,
            'is_active' => true,
        ]);

        // Test dashboard creation
        $this->assertDatabaseHas('user_dashboards', [
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'My Dashboard',
            'is_default' => true,
        ]);

        // Test dashboard relationships
        $this->assertEquals($this->user->id, $dashboard->user_id);
        $this->assertEquals($this->tenant->id, $dashboard->tenant_id);

        // Test dashboard filtering
        $userDashboards = UserDashboard::where('user_id', $this->user->id)->count();
        $defaultDashboards = UserDashboard::where('is_default', true)->count();
        $activeDashboards = UserDashboard::where('is_active', true)->count();

        $this->assertEquals(1, $userDashboards);
        $this->assertEquals(1, $defaultDashboards);
        $this->assertEquals(1, $activeDashboards);
    }

    /**
     * Test dashboard caching functionality
     */
    public function test_dashboard_caching(): void
    {
        // Test cache operations
        $cacheKey = 'dashboard:metrics:user:' . $this->user->id;
        $cacheData = [
            'total_projects' => 5,
            'total_tasks' => 10,
            'completed_tasks' => 3,
        ];

        // Set cache
        Cache::put($cacheKey, $cacheData, 60);

        // Test cache retrieval
        $this->assertTrue(Cache::has($cacheKey));
        $retrievedData = Cache::get($cacheKey);
        $this->assertEquals($cacheData, $retrievedData);

        // Test cache expiration
        Cache::put($cacheKey, $cacheData, 0); // Expire immediately
        $this->assertFalse(Cache::has($cacheKey));

        // Test cache clearing
        Cache::put($cacheKey, $cacheData, 60);
        Cache::forget($cacheKey);
        $this->assertFalse(Cache::has($cacheKey));
    }
}
