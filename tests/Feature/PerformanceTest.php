<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Dashboard;
use App\Models\Widget;
use App\Models\SupportTicket;
use App\Models\PerformanceMetric;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Tests\Traits\AuthenticationTrait;
use Tests\Traits\RouteNameTrait;

class PerformanceTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTrait, RouteNameTrait;

    private const SLOW_TESTS_ENV = 'RUN_SLOW_TESTS';
    private const STRESS_TESTS_ENV = 'RUN_STRESS_TESTS';

    protected $user;
    protected $admin;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();

        $password = bcrypt('password');
        $this->user = $this->createTenantUser($this->tenant, [
            'role' => 'user',
            'password' => $password,
        ]);
        $this->admin = $this->createTenantUser($this->tenant, [
            'role' => 'admin',
            'password' => $password,
        ]);
    }

    /**
     * Test API response times
     */
    public function test_api_response_times()
    {
        $this->apiAs($this->user, $this->tenant);

        // Test dashboard list endpoint
        $startTime = microtime(true);
        $response = $this->getJson('/api/v1/dashboard');
        $endTime = microtime(true);
        
        $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        $this->assertLessThan(500, $responseTime, 'Dashboard list API should respond within 500ms');

        // Test widget list endpoint
        $startTime = microtime(true);
        $response = $this->getJson('/api/v1/dashboard/widgets');
        $endTime = microtime(true);
        
        $responseTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(300, $responseTime, 'Widget list API should respond within 300ms');

        // Test support tickets endpoint
        $startTime = microtime(true);
        $response = $this->getJson('/api/v1/notifications');
        $endTime = microtime(true);
        
        $responseTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(400, $responseTime, 'Notifications API should respond within 400ms');
    }

    /**
     * Test database query performance
     */
    public function test_database_query_performance()
    {
        $this->actingAs($this->user);

        // Create test data
        $dashboards = Dashboard::factory()->count(50)->create(['user_id' => $this->user->id]);
        
        foreach ($dashboards as $dashboard) {
            Widget::factory()->count(5)->create(['dashboard_id' => $dashboard->id]);
        }

        // Test dashboard query with relationships
        $startTime = microtime(true);
        $dashboards = Dashboard::with('widgets')->where('user_id', $this->user->id)->get();
        $endTime = microtime(true);
        
        $queryTime = ($endTime - $startTime) * 1000;
        
        $this->assertLessThan(200, $queryTime, 'Dashboard query with widgets should complete within 200ms');
        $this->assertCount(50, $dashboards);

        // Test complex query performance
        $startTime = microtime(true);
        $result = DB::table('dashboards')
            ->join('widgets', 'dashboards.id', '=', 'widgets.dashboard_id')
            ->where('dashboards.user_id', $this->user->id)
            ->select('dashboards.name', 'widgets.name', 'widgets.type')
            ->get();
        $endTime = microtime(true);
        
        $queryTime = ($endTime - $startTime) * 1000;
        
        $this->assertLessThan(300, $queryTime, 'Complex join query should complete within 300ms');
        $this->assertCount(250, $result); // 50 dashboards * 5 widgets each
    }

    /**
     * Test cache performance
     */
    public function test_cache_performance()
    {
        $this->actingAs($this->user);

        // Test cache write performance
        $startTime = microtime(true);
        Cache::put('test_key', 'test_value', 60);
        $endTime = microtime(true);
        
        $writeTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(10, $writeTime, 'Cache write should complete within 10ms');

        // Test cache read performance
        $startTime = microtime(true);
        $value = Cache::get('test_key');
        $endTime = microtime(true);
        
        $readTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(5, $readTime, 'Cache read should complete within 5ms');
        $this->assertEquals('test_value', $value);

        // Test cache bulk operations
        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            Cache::put("bulk_key_{$i}", "bulk_value_{$i}", 60);
        }
        $endTime = microtime(true);
        
        $bulkWriteTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(100, $bulkWriteTime, 'Bulk cache writes should complete within 100ms');
    }

    /**
     * Test memory usage
     */
    public function test_memory_usage()
    {
        $this->actingAs($this->user);

        DB::disableQueryLog();
        gc_collect_cycles();
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $baseline = memory_get_usage(false);

        // Create large dataset
        for ($i = 0; $i < 100; $i++) {
            $dashboard = Dashboard::factory()->create(['user_id' => $this->user->id]);

            for ($j = 0; $j < 10; $j++) {
                Widget::factory()->create(['dashboard_id' => $dashboard->id]);
            }
        }

        gc_collect_cycles();
        $peak = memory_get_peak_usage(false);
        $deltaMB = ($peak - $baseline) / 1024 / 1024;

        // Assert memory growth stays reasonable for creating 100 dashboards with 10 widgets each.
        $this->assertLessThan(80, $deltaMB, "Memory delta should not exceed 80MB for 1000 records (delta={$deltaMB}MB)");

        // Test memory cleanup
        gc_collect_cycles();

        $finalMemory = memory_get_usage(false);
        $finalDeltaMB = ($finalMemory - $baseline) / 1024 / 1024;

        // Allow small runtime variance while ensuring memory is not retained excessively after cleanup.
        $this->assertLessThan(20, $finalDeltaMB, "Final memory after cleanup should remain under +20MB from baseline (delta={$finalDeltaMB}MB)");
    }

    /**
     * Test concurrent request handling
     */
    public function test_concurrent_request_handling()
    {
        $this->actingAs($this->user);

        $dashboard = Dashboard::factory()->create(['user_id' => $this->user->id]);

        $startTime = microtime(true);

        // Simulate concurrent requests
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->get($this->namedRoute('api.legacy.dashboards.show', ['dashboard' => $dashboard->id]));
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;

        // All requests should succeed
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }

        // Total time should be reasonable
        $this->assertLessThan(1000, $totalTime, '10 concurrent requests should complete within 1000ms');
    }

    /**
     * Test large dataset handling
     */
    public function test_large_dataset_handling()
    {
        $this->actingAs($this->user);

        // Create large dataset
        $dashboards = Dashboard::factory()->count(1000)->create(['user_id' => $this->user->id]);

        $startTime = microtime(true);
        $response = $this->get($this->namedRoute('api.legacy.dashboards.index'));
        $endTime = microtime(true);

        $responseTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(1000, $responseTime, 'Large dataset should be handled within 1000ms');

        // Test pagination performance
        $startTime = microtime(true);
        $response = $this->get($this->namedRoute('api.legacy.dashboards.index', [], ['page' => 1, 'per_page' => 50]));
        $endTime = microtime(true);

        $responseTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(400, $responseTime, 'Paginated results should load within 400ms');
    }

    /**
     * Test file upload performance
     */
    public function test_file_upload_performance()
    {
        $this->actingAs($this->user);

        // Test small file upload
        $smallFile = \Illuminate\Http\UploadedFile::fake()->create('small.txt', 100); // 100KB

        $startTime = microtime(true);
        $response = $this->post('/api/upload', ['file' => $smallFile]);
        $endTime = microtime(true);

        $uploadTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(500, $uploadTime, 'Small file upload should complete within 500ms');

        // Test medium file upload
        $mediumFile = \Illuminate\Http\UploadedFile::fake()->create('medium.txt', 1024); // 1MB

        $startTime = microtime(true);
        $response = $this->post('/api/upload', ['file' => $mediumFile]);
        $endTime = microtime(true);

        $uploadTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(2000, $uploadTime, 'Medium file upload should complete within 2000ms');
    }

    /**
     * Test search performance
     */
    public function test_search_performance()
    {
        $this->actingAs($this->user);

        // Create test data with searchable content
        for ($i = 0; $i < 100; $i++) {
            Dashboard::factory()->create([
                'user_id' => $this->user->id,
                'name' => "Dashboard {$i}",
                'description' => "Description for dashboard {$i}"
            ]);
        }

        // Test search performance
        $startTime = microtime(true);
        $response = $this->get($this->namedRoute('api.legacy.dashboards.index', [], ['search' => 'Dashboard']));
        $endTime = microtime(true);

        $searchTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(300, $searchTime, 'Search should complete within 300ms');

        // Test complex search
        $startTime = microtime(true);
        $response = $this->get($this->namedRoute('api.legacy.dashboards.index', [], ['search' => 'Dashboard 50']));
        $endTime = microtime(true);

        $searchTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(200, $searchTime, 'Complex search should complete within 200ms');
    }

    /**
     * Test WebSocket performance
     */
    public function test_websocket_performance()
    {
        $this->actingAs($this->user);

        // Test WebSocket authentication performance
        $startTime = microtime(true);
        $response = $this->get('/api/websocket/auth');
        $endTime = microtime(true);

        $authTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(100, $authTime, 'WebSocket authentication should complete within 100ms');
    }

    /**
     * Test maintenance task performance
     */
    public function test_maintenance_task_performance()
    {
        $this->actingAs($this->admin);

        // Test cache clearing performance
        $startTime = microtime(true);
        $response = $this->post('/admin/maintenance/clear-cache');
        $endTime = microtime(true);

        $maintenanceTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(1000, $maintenanceTime, 'Cache clearing should complete within 1000ms');

        // Test database maintenance performance
        $startTime = microtime(true);
        $response = $this->post('/admin/maintenance/database');
        $endTime = microtime(true);

        $maintenanceTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(2000, $maintenanceTime, 'Database maintenance should complete within 2000ms');
    }

    /**
     * Test backup performance
     */
    public function test_backup_performance()
    {
        $this->actingAs($this->admin);

        // Create test data
        Dashboard::factory()->count(100)->create(['user_id' => $this->user->id]);

        // Test backup performance
        $startTime = microtime(true);
        $response = $this->post('/admin/maintenance/backup-database');
        $endTime = microtime(true);

        $backupTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(5000, $backupTime, 'Database backup should complete within 5000ms');
    }

    /**
     * Test system health check performance
     */
    public function test_system_health_check_performance()
    {
        $this->actingAs($this->admin);

        // Test health check performance
        $startTime = microtime(true);
        $response = $this->get('/api/health');
        $endTime = microtime(true);

        $healthCheckTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(500, $healthCheckTime, 'Health check should complete within 500ms');
    }

    /**
     * Test performance metrics collection
     */
    public function test_performance_metrics_collection()
    {
        $this->actingAs($this->admin);

        // Create some performance metrics
        PerformanceMetric::factory()->count(100)->create();

        // Test metrics retrieval performance
        $startTime = microtime(true);
        $response = $this->get('/api/health/performance');
        $endTime = microtime(true);

        $metricsTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(300, $metricsTime, 'Performance metrics should load within 300ms');
    }

    /**
     * Test stress test with mixed operations
     */
    public function test_stress_test_mixed_operations()
    {
        $this->actingAs($this->user);

        $startTime = microtime(true);

        // Perform mixed operations
        for ($i = 0; $i < 50; $i++) {
            // Create dashboard
            $dashboardResponse = $this->post($this->namedRoute('api.legacy.dashboards.store'), [
                'name' => "Stress Test Dashboard {$i}",
                'description' => "Stress test description {$i}",
                'layout' => 'grid',
                'is_public' => false
            ]);

            if ($dashboardResponse->status() === 201) {
                $dashboardId = $dashboardResponse->json('id');

                // Create widget
                $this->post($this->namedRoute('api.legacy.widgets.store'), [
                    'dashboard_id' => $dashboardId,
                    'type' => 'chart',
                    'title' => "Widget {$i}",
                    'config' => ['chart_type' => 'line'],
                    'position' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]
                ]);

                // Update dashboard
                $this->put($this->namedRoute('api.legacy.dashboards.update', ['dashboard' => $dashboardId]), [
                    'name' => "Updated Dashboard {$i}"
                ]);

                // Get dashboard
                $this->get($this->namedRoute('api.legacy.dashboards.show', ['dashboard' => $dashboardId]));

                // Delete dashboard
                $this->delete($this->namedRoute('api.legacy.dashboards.destroy', ['dashboard' => $dashboardId]));
            }
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000;

        // Assert reasonable performance for mixed operations
        $this->assertLessThan(10000, $totalTime, 'Mixed operations should complete within 10000ms');
    }

    /**
     * Test memory leak detection
     *
     * @group slow
     * @group performance
     */
    public function test_memory_leak_detection()
    {
        $this->skipUnlessSlowTestsEnabled();

        $this->actingAs($this->user);

        $initialMemory = memory_get_usage(true);

        // Perform operations that might cause memory leaks
        for ($i = 0; $i < 100; $i++) {
            $dashboard = Dashboard::factory()->create(['user_id' => $this->user->id]);
            
            for ($j = 0; $j < 10; $j++) {
                Widget::factory()->create(['dashboard_id' => $dashboard->id]);
            }

            // Simulate API calls
            $this->get($this->namedRoute('api.legacy.dashboards.show', ['dashboard' => $dashboard->id]));
            $this->get($this->namedRoute('api.v1.dashboard.widgets.index', [], ['dashboard_id' => $dashboard->id]));

            // Clean up
            $dashboard->delete();
        }

        // Force garbage collection
        gc_collect_cycles();

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        $memoryIncreaseMB = $memoryIncrease / 1024 / 1024;

        // Assert no significant memory leak (at most 10MB increase).
        $this->assertLessThanOrEqual(10, $memoryIncreaseMB, 'Memory leak should not exceed 10MB');
    }

    private function slowTestsEnabled(): bool
    {
        return (string) env(self::SLOW_TESTS_ENV, env(self::STRESS_TESTS_ENV, '0')) === '1';
    }

    /**
     * @group slow
     */
    private function skipUnlessSlowTestsEnabled(): void
    {
        if (!$this->slowTestsEnabled()) {
            $this->markTestSkipped(self::SLOW_TESTS_ENV . '=1 is required for slow/performance tests');
        }
    }
}
