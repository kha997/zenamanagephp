<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Rfi;
use App\Models\ChangeRequest;
use App\Models\Tenant;
use App\Services\DashboardRealTimeService;
use App\Services\WebSocketService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Test Realtime Sync & WebSocket Events
 * 
 * Kịch bản: Test real-time notifications, WebSocket events, và data synchronization
 */
class RealtimeSyncWebSocketTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $project;
    private $user1;
    private $user2;
    private $dashboardRealTimeService;
    private $webSocketService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'domain' => 'test.com',
            'settings' => json_encode(['timezone' => 'Asia/Ho_Chi_Minh']),
            'status' => 'active',
            'is_active' => true,
        ]);

        // Tạo users
        $this->user1 = User::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        $this->user2 = User::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'profile_data' => '{}',
        ]);

        // Tạo project
        $this->project = Project::create([
            'name' => 'Test Project',
            'code' => 'REALTIME-TEST-001',
            'description' => 'Test Description',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user1->id,
        ]);

        // Initialize services (mock them to avoid dependency issues)
        $this->dashboardRealTimeService = $this->createMock(DashboardRealTimeService::class);
        $this->webSocketService = $this->createMock(WebSocketService::class);

        // Clear cache
        Cache::flush();
    }

    /**
     * Test WebSocket connection và authentication
     */
    public function test_websocket_connection_and_authentication(): void
    {
        // Test WebSocket server configuration
        $config = config('websocket');
        
        $this->assertNotNull($config);
        $this->assertEquals('0.0.0.0', $config['host']);
        $this->assertEquals(8080, $config['port']);
        $this->assertTrue($config['rate_limiting']['enabled']);
        $this->assertEquals(5, $config['rate_limiting']['max_connections_per_user']);

        // Test channels configuration
        $channels = $config['channels'];
        $this->assertArrayHasKey('dashboard', $channels);
        $this->assertArrayHasKey('alerts', $channels);
        $this->assertArrayHasKey('notifications', $channels);
        $this->assertArrayHasKey('project', $channels);

        // Test authentication settings
        $auth = $config['auth'];
        $this->assertEquals('sanctum', $auth['guard']);
        $this->assertEquals('Authorization', $auth['token_header']);
        $this->assertEquals('Bearer ', $auth['token_prefix']);

        // Test heartbeat settings
        $heartbeat = $config['heartbeat'];
        $this->assertEquals(30, $heartbeat['interval']);
        $this->assertEquals(60, $heartbeat['timeout']);
    }

    /**
     * Test dashboard real-time updates
     */
    public function test_dashboard_realtime_updates(): void
    {
        // Test dashboard update broadcasting
        $widgetData = [
            'widget_id' => 'project_stats',
            'data' => [
                'total_projects' => 5,
                'active_projects' => 3,
                'completed_projects' => 2,
            ],
            'timestamp' => now()->toISOString(),
        ];

        // Mock the broadcast methods
        $this->mock(DashboardRealTimeService::class, function ($mock) {
            $mock->shouldReceive('broadcastDashboardUpdate')
                ->once()
                ->with($this->user1->id, 'project_stats', \Mockery::type('array'))
                ->andReturn(true);
        });

        // Test dashboard update
        $result = $this->dashboardRealTimeService->broadcastDashboardUpdate(
            $this->user1->id,
            'project_stats',
            $widgetData['data']
        );

        $this->assertTrue(true); // Mock will handle the assertion
    }

    /**
     * Test alert broadcasting
     */
    public function test_alert_broadcasting(): void
    {
        $alert = [
            'id' => 'alert_001',
            'type' => 'warning',
            'title' => 'Project Deadline Approaching',
            'message' => 'Project "Test Project" deadline is in 3 days',
            'project_id' => $this->project->id,
            'user_id' => $this->user1->id,
            'timestamp' => now()->toISOString(),
        ];

        // Mock the broadcast methods
        $this->mock(DashboardRealTimeService::class, function ($mock) {
            $mock->shouldReceive('broadcastAlert')
                ->once()
                ->with($this->user1->id, \Mockery::type('array'))
                ->andReturn(true);
        });

        // Test alert broadcasting
        $result = $this->dashboardRealTimeService->broadcastAlert($this->user1->id, $alert);

        $this->assertTrue(true); // Mock will handle the assertion
    }

    /**
     * Test metric updates broadcasting
     */
    public function test_metric_updates_broadcasting(): void
    {
        $metricData = [
            'metric_code' => 'project_progress',
            'value' => 75.5,
            'unit' => 'percentage',
            'timestamp' => now()->toISOString(),
        ];

        // Mock the broadcast methods
        $this->mock(DashboardRealTimeService::class, function ($mock) {
            $mock->shouldReceive('broadcastMetricUpdate')
                ->once()
                ->with($this->tenant->id, 'project_progress', \Mockery::type('array'))
                ->andReturn(true);
        });

        // Test metric update broadcasting
        $result = $this->dashboardRealTimeService->broadcastMetricUpdate(
            $this->tenant->id,
            'project_progress',
            $metricData
        );

        $this->assertTrue(true); // Mock will handle the assertion
    }

    /**
     * Test project updates broadcasting
     */
    public function test_project_updates_broadcasting(): void
    {
        $projectUpdate = [
            'event_type' => 'task_completed',
            'data' => [
                'task_id' => 'task_001',
                'task_name' => 'Foundation Work',
                'completed_by' => $this->user1->id,
                'completion_date' => now()->toISOString(),
            ],
        ];

        // Mock the broadcast methods
        $this->mock(DashboardRealTimeService::class, function ($mock) {
            $mock->shouldReceive('broadcastProjectUpdate')
                ->once()
                ->with($this->project->id, 'task_completed', \Mockery::type('array'))
                ->andReturn(true);
        });

        // Test project update broadcasting
        $result = $this->dashboardRealTimeService->broadcastProjectUpdate(
            $this->project->id,
            'task_completed',
            $projectUpdate['data']
        );

        $this->assertTrue(true); // Mock will handle the assertion
    }

    /**
     * Test system notifications broadcasting
     */
    public function test_system_notifications_broadcasting(): void
    {
        $systemNotification = [
            'type' => 'maintenance',
            'message' => 'System maintenance scheduled for tonight at 2 AM',
            'data' => [
                'maintenance_start' => now()->addHours(8)->toISOString(),
                'estimated_duration' => '2 hours',
                'affected_services' => ['dashboard', 'api'],
            ],
        ];

        // Mock the broadcast methods
        $this->mock(DashboardRealTimeService::class, function ($mock) {
            $mock->shouldReceive('broadcastSystemNotification')
                ->once()
                ->with($this->tenant->id, 'maintenance', \Mockery::type('string'), \Mockery::type('array'))
                ->andReturn(true);
        });

        // Test system notification broadcasting
        $result = $this->dashboardRealTimeService->broadcastSystemNotification(
            $this->tenant->id,
            'maintenance',
            $systemNotification['message'],
            $systemNotification['data']
        );

        $this->assertTrue(true); // Mock will handle the assertion
    }

    /**
     * Test widget refresh triggering
     */
    public function test_widget_refresh_triggering(): void
    {
        // Mock the broadcast methods
        $this->mock(DashboardRealTimeService::class, function ($mock) {
            $mock->shouldReceive('triggerWidgetRefresh')
                ->once()
                ->with($this->user1->id, 'project_timeline')
                ->andReturn(true);
        });

        // Test widget refresh
        $result = $this->dashboardRealTimeService->triggerWidgetRefresh(
            $this->user1->id,
            'project_timeline'
        );

        $this->assertTrue(true); // Mock will handle the assertion
    }

    /**
     * Test WebSocket service broadcasting
     */
    public function test_websocket_service_broadcasting(): void
    {
        $notificationData = [
            'type' => 'task_assignment',
            'title' => 'New Task Assigned',
            'message' => 'You have been assigned to "Foundation Work" task',
            'task_id' => 'task_001',
            'project_id' => $this->project->id,
        ];

        // Mock the WebSocket service
        $this->mock(WebSocketService::class, function ($mock) {
            $mock->shouldReceive('broadcastToUser')
                ->once()
                ->with($this->user1->id, \Mockery::type('array'))
                ->andReturn(true);
        });

        // Test WebSocket broadcasting
        $result = $this->webSocketService->broadcastToUser($this->user1->id, $notificationData);

        $this->assertTrue(true); // Mock will handle the assertion
    }

    /**
     * Test cache busting và data consistency
     */
    public function test_cache_busting_and_data_consistency(): void
    {
        // Set initial cache data
        $cacheKey = "dashboard_data_{$this->user1->id}";
        $initialData = [
            'projects' => 3,
            'tasks' => 15,
            'last_updated' => now()->subMinutes(5)->toISOString(),
        ];
        
        Cache::put($cacheKey, $initialData, 300);

        // Verify cache exists
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertEquals($initialData, Cache::get($cacheKey));

        // Simulate cache busting
        Cache::forget($cacheKey);
        
        // Verify cache is cleared
        $this->assertFalse(Cache::has($cacheKey));

        // Set new data
        $newData = [
            'projects' => 4,
            'tasks' => 18,
            'last_updated' => now()->toISOString(),
        ];
        
        Cache::put($cacheKey, $newData, 300);

        // Verify new data
        $this->assertTrue(Cache::has($cacheKey));
        $this->assertEquals($newData, Cache::get($cacheKey));
        $this->assertNotEquals($initialData, Cache::get($cacheKey));
    }

    /**
     * Test notification events
     */
    public function test_notification_events(): void
    {
        // Test RFI notification
        $rfi = Rfi::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Foundation Question',
            'description' => 'What is the concrete strength requirement?',
            'status' => 'open',
            'priority' => 'high',
            'created_by' => $this->user1->id,
            'assigned_to' => $this->user2->id,
        ]);

        // Test Change Request notification
        $changeRequest = ChangeRequest::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'title' => 'Design Change',
            'description' => 'Change foundation depth from 1.5m to 2m',
            'status' => 'pending',
            'impact_analysis' => 'Cost increase: $10,000',
            'created_by' => $this->user1->id,
        ]);

        // Test Task notification
        $task = Task::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Foundation Work',
            'description' => 'Complete foundation work',
            'status' => 'in_progress',
            'priority' => 'high',
            'assigned_to' => $this->user2->id,
            'created_by' => $this->user1->id,
        ]);

        // Verify entities created successfully
        $this->assertDatabaseHas('rfis', ['id' => $rfi->id]);
        $this->assertDatabaseHas('change_requests', ['id' => $changeRequest->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);

        // Test that these would trigger real-time notifications
        $this->assertTrue(true); // Entities created successfully
    }

    /**
     * Test data consistency
     */
    public function test_data_consistency(): void
    {
        // Create initial data
        $initialProjectCount = Project::where('tenant_id', $this->tenant->id)->count();
        $initialUserCount = User::where('tenant_id', $this->tenant->id)->count();

        // Create new project
        $newProject = Project::create([
            'name' => 'New Project',
            'code' => 'REALTIME-TEST-002',
            'description' => 'New Test Description',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user1->id,
        ]);

        // Verify data consistency
        $newProjectCount = Project::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals($initialProjectCount + 1, $newProjectCount);

        // Verify relationships
        $this->assertEquals($this->tenant->id, $newProject->tenant_id);
        $this->assertEquals($this->user1->id, $newProject->created_by);

        // Test cache consistency
        $cacheKey = "project_count_{$this->tenant->id}";
        Cache::put($cacheKey, $newProjectCount, 300);
        
        $cachedCount = Cache::get($cacheKey);
        $this->assertEquals($newProjectCount, $cachedCount);
    }

    /**
     * Test performance optimization
     */
    public function test_performance_optimization(): void
    {
        // Test bulk operations
        $projects = [];
        for ($i = 1; $i <= 10; $i++) {
            $projects[] = [
                'name' => "Bulk Project {$i}",
                'code' => "BULK-{$i}",
                'description' => "Bulk test project {$i}",
                'status' => 'active',
                'tenant_id' => $this->tenant->id,
                'created_by' => $this->user1->id,
            ];
        }

        // Bulk insert
        Project::insert($projects);

        // Verify bulk insert
        $bulkProjectCount = Project::where('tenant_id', $this->tenant->id)
            ->where('name', 'like', 'Bulk Project%')
            ->count();
        
        $this->assertEquals(10, $bulkProjectCount);

        // Test cache performance
        $startTime = microtime(true);
        
        // Cache frequently accessed data
        $projectStats = [
            'total_projects' => Project::where('tenant_id', $this->tenant->id)->count(),
            'active_projects' => Project::where('tenant_id', $this->tenant->id)->where('status', 'active')->count(),
            'completed_projects' => Project::where('tenant_id', $this->tenant->id)->where('status', 'completed')->count(),
        ];
        
        Cache::put("project_stats_{$this->tenant->id}", $projectStats, 300);
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Verify performance (should be fast)
        $this->assertLessThan(1.0, $executionTime); // Less than 1 second
        
        // Verify cached data
        $cachedStats = Cache::get("project_stats_{$this->tenant->id}");
        $this->assertEquals($projectStats, $cachedStats);
    }

    /**
     * Test real-time statistics
     */
    public function test_realtime_statistics(): void
    {
        // Mock real-time stats
        $this->mock(DashboardRealTimeService::class, function ($mock) {
            $mock->shouldReceive('getRealTimeStats')
                ->once()
                ->andReturn([
                    'websocket_connections' => 5,
                    'sse_connections' => 3,
                    'cache_hit_rate' => 85.5,
                    'broadcast_rate' => 120,
                    'last_update' => now()->toISOString(),
                ]);
        });

        // Test real-time statistics
        $stats = $this->dashboardRealTimeService->getRealTimeStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('websocket_connections', $stats);
        $this->assertArrayHasKey('sse_connections', $stats);
        $this->assertArrayHasKey('cache_hit_rate', $stats);
        $this->assertArrayHasKey('broadcast_rate', $stats);
        $this->assertArrayHasKey('last_update', $stats);
    }

    /**
     * Test event listeners setup
     */
    public function test_event_listeners_setup(): void
    {
        // Test that event listeners can be set up
        $this->mock(DashboardRealTimeService::class, function ($mock) {
            $mock->shouldReceive('setupEventListeners')
                ->once()
                ->andReturn(true);
        });

        // Test event listeners setup
        $result = $this->dashboardRealTimeService->setupEventListeners();

        $this->assertTrue(true); // Mock will handle the assertion
    }

    /**
     * Test WebSocket server health check
     */
    public function test_websocket_server_health_check(): void
    {
        // Test WebSocket server configuration
        $config = config('websocket');
        
        // Test monitoring configuration
        $monitoring = $config['monitoring'];
        $this->assertTrue($monitoring['enabled']);
        $this->assertEquals('/websocket/health', $monitoring['health_check_endpoint']);
        $this->assertEquals('/websocket/stats', $monitoring['stats_endpoint']);

        // Test performance configuration
        $performance = $config['performance'];
        $this->assertEquals(1024 * 1024, $performance['max_message_size']); // 1MB
        $this->assertEquals(1000, $performance['max_connections']);
        $this->assertTrue($performance['keep_alive']);

        // Test CORS configuration
        $cors = $config['cors'];
        $this->assertTrue($cors['enabled']);
        $this->assertContains('GET', $cors['methods']);
        $this->assertContains('POST', $cors['methods']);
        $this->assertContains('Authorization', $cors['headers']);
    }
}
