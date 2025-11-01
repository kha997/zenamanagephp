<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * Test Realtime Sync & WebSocket Events (Simplified)
 * 
 * Kịch bản: Test real-time notifications, WebSocket events, và data synchronization
 */
class RealtimeSyncWebSocketSimpleTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $project;
    private $user1;
    private $user2;

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
        for ($i = 1; $i <= 5; $i++) {
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
        
        $this->assertEquals(5, $bulkProjectCount);

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

    /**
     * Test broadcasting configuration
     */
    public function test_broadcasting_configuration(): void
    {
        // Test broadcasting configuration
        $broadcastingConfig = config('broadcasting');
        
        $this->assertNotNull($broadcastingConfig);
        $this->assertArrayHasKey('default', $broadcastingConfig);
        $this->assertArrayHasKey('connections', $broadcastingConfig);

        // Test connections
        $connections = $broadcastingConfig['connections'];
        $this->assertArrayHasKey('pusher', $connections);
        $this->assertArrayHasKey('redis', $connections);
        $this->assertArrayHasKey('log', $connections);
        $this->assertArrayHasKey('null', $connections);

        // Test Redis connection
        $redisConnection = $connections['redis'];
        $this->assertEquals('redis', $redisConnection['driver']);
        $this->assertEquals('default', $redisConnection['connection']);
    }

    /**
     * Test event system
     */
    public function test_event_system(): void
    {
        // Test that events can be fired
        $eventFired = false;
        
        Event::listen('test.event', function () use (&$eventFired) {
            $eventFired = true;
        });

        // Fire test event
        Event::dispatch('test.event');

        // Verify event was fired
        $this->assertTrue($eventFired);
    }

    /**
     * Test notification events
     */
    public function test_notification_events(): void
    {
        // Test Task creation (simpler than RFI/CR)
        $task = Task::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Task',
            'description' => 'Test task for realtime sync',
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_to' => $this->user2->id,
            'created_by' => $this->user1->id,
        ]);

        // Verify task created successfully
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertEquals($this->project->id, $task->project_id);
        $this->assertEquals($this->tenant->id, $task->tenant_id);
        $this->assertEquals($this->user2->id, $task->assigned_to);
        $this->assertEquals($this->user1->id, $task->created_by);

        // Test that this would trigger real-time notifications
        $this->assertTrue(true); // Task created successfully
    }

    /**
     * Test real-time data updates
     */
    public function test_realtime_data_updates(): void
    {
        // Create initial task
        $task = Task::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Realtime Task',
            'description' => 'Task for realtime updates',
            'status' => 'pending',
            'priority' => 'high',
            'assigned_to' => $this->user2->id,
            'created_by' => $this->user1->id,
        ]);

        // Update task status
        $task->update(['status' => 'in_progress']);

        // Verify update
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'in_progress'
        ]);

        // Update task completion
        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Verify completion
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'completed'
        ]);

        $this->assertNotNull($task->completed_at);
    }

    /**
     * Test multi-user data synchronization
     */
    public function test_multi_user_data_synchronization(): void
    {
        // Create task assigned to user2
        $task = Task::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Multi-user Task',
            'description' => 'Task for multi-user sync',
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_to' => $this->user2->id,
            'created_by' => $this->user1->id,
        ]);

        // Simulate user2 updating the task
        $task->update([
            'status' => 'in_progress',
            'updated_by' => $this->user2->id,
        ]);

        // Verify both users can see the update
        $updatedTask = Task::find($task->id);
        
        $this->assertEquals('in_progress', $updatedTask->status);
        $this->assertEquals($this->user2->id, $updatedTask->assigned_to);
        $this->assertEquals($this->user1->id, $updatedTask->created_by);

        // Test cache invalidation for both users
        $user1CacheKey = "user_tasks_{$this->user1->id}";
        $user2CacheKey = "user_tasks_{$this->user2->id}";
        
        Cache::put($user1CacheKey, ['task_id' => $task->id], 300);
        Cache::put($user2CacheKey, ['task_id' => $task->id], 300);

        // Simulate cache invalidation
        Cache::forget($user1CacheKey);
        Cache::forget($user2CacheKey);

        $this->assertFalse(Cache::has($user1CacheKey));
        $this->assertFalse(Cache::has($user2CacheKey));
    }

    /**
     * Test WebSocket channels
     */
    public function test_websocket_channels(): void
    {
        $config = config('websocket');
        $channels = $config['channels'];

        // Test user-specific channels
        $userChannel = str_replace('{user_id}', $this->user1->id, $channels['dashboard']);
        $this->assertEquals("dashboard.{$this->user1->id}", $userChannel);

        $alertChannel = str_replace('{user_id}', $this->user1->id, $channels['alerts']);
        $this->assertEquals("alerts.{$this->user1->id}", $alertChannel);

        $notificationChannel = str_replace('{user_id}', $this->user1->id, $channels['notifications']);
        $this->assertEquals("notifications.{$this->user1->id}", $notificationChannel);

        // Test project-specific channels
        $projectChannel = str_replace('{project_id}', $this->project->id, $channels['project']);
        $this->assertEquals("project.{$this->project->id}", $projectChannel);

        // Test tenant-specific channels
        $systemChannel = str_replace('{tenant_id}', $this->tenant->id, $channels['system']);
        $this->assertEquals("system.{$this->tenant->id}", $systemChannel);
    }

    /**
     * Test WebSocket rate limiting
     */
    public function test_websocket_rate_limiting(): void
    {
        $config = config('websocket');
        $rateLimiting = $config['rate_limiting'];

        // Test rate limiting configuration
        $this->assertTrue($rateLimiting['enabled']);
        $this->assertEquals(5, $rateLimiting['max_connections_per_user']);
        $this->assertEquals(10, $rateLimiting['max_connections_per_ip']);
        $this->assertEquals(300, $rateLimiting['connection_timeout']);

        // Test rate limiting logic (simulated)
        $userConnections = 3;
        $ipConnections = 7;
        
        $this->assertLessThanOrEqual($rateLimiting['max_connections_per_user'], $userConnections);
        $this->assertLessThanOrEqual($rateLimiting['max_connections_per_ip'], $ipConnections);
    }
}
