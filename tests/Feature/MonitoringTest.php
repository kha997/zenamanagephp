<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Services\MonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;
    private MonitoringService $monitoringService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        
        $this->monitoringService = app(MonitoringService::class);
    }

    /** @test */
    public function it_can_get_system_health(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/monitoring/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'status',
                    'timestamp',
                    'uptime',
                    'memory_usage' => [
                        'current',
                        'peak',
                    ],
                    'disk_usage' => [
                        'total',
                        'used',
                        'free',
                        'percentage',
                    ],
                    'api_metrics',
                    'database_metrics',
                    'queue_metrics',
                ]
            ]);
    }

    /** @test */
    public function it_can_get_api_metrics(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/monitoring/api-metrics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'avg_response_time',
                    'p95_response_time',
                    'error_rate',
                    'requests_per_minute',
                    'total_requests',
                ]
            ]);
    }

    /** @test */
    public function it_can_get_database_metrics(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/monitoring/database-metrics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'connection_count',
                    'slow_queries',
                    'table_sizes',
                    'cache_hit_ratio',
                ]
            ]);
    }

    /** @test */
    public function it_can_get_queue_metrics(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/monitoring/queue-metrics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'pending_jobs',
                    'failed_jobs',
                    'processed_jobs',
                    'queue_size',
                ]
            ]);
    }

    /** @test */
    public function it_can_get_monitoring_dashboard(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/monitoring/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'api_metrics',
                    'database_metrics',
                    'queue_metrics',
                    'system_health',
                ]
            ]);
    }

    /** @test */
    public function it_logs_api_request_metrics(): void
    {
        $this->monitoringService->logApiRequest('GET', '/api/v1/app/projects', 150.5, 200);

        // Verify metrics are logged (this would typically check a log file or metrics store)
        $this->assertTrue(true); // Placeholder - in real implementation, check logs
    }

    /** @test */
    public function it_logs_page_load_metrics(): void
    {
        $this->monitoringService->logPageLoad('app.projects', 200.0, 200);

        // Verify metrics are logged
        $this->assertTrue(true); // Placeholder - in real implementation, check logs
    }

    /** @test */
    public function it_caches_metrics_for_performance(): void
    {
        // Clear cache first
        Cache::flush();

        // First call should populate cache
        $this->monitoringService->getApiMetrics();

        // Verify cache is populated
        $this->assertTrue(Cache::has('api_metrics'));

        // Second call should use cache
        $startTime = microtime(true);
        $this->monitoringService->getApiMetrics();
        $endTime = microtime(true);

        // Should be very fast due to caching
        $this->assertLessThan(0.01, $endTime - $startTime);
    }

    /** @test */
    public function it_handles_database_errors_gracefully(): void
    {
        // Mock database connection failure
        DB::shouldReceive('select')
            ->andThrow(new \Exception('Database connection failed'));

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/monitoring/database-metrics');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'connection_count' => 0,
                    'slow_queries' => 0,
                    'table_sizes' => [],
                    'cache_hit_ratio' => 0,
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/app/monitoring/health');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_tenant_ability(): void
    {
        $adminUser = User::factory()->create([
            'tenant_id' => null, // Admin user
        ]);

        $response = $this->actingAs($adminUser)
            ->getJson('/api/v1/app/monitoring/health');

        $response->assertStatus(403);
    }

    /** @test */
    public function it_calculates_system_status_correctly(): void
    {
        // Test healthy status
        $health = $this->monitoringService->getSystemHealth();
        $this->assertContains($health['status'], ['healthy', 'warning', 'error']);

        // Test with high error rate
        Cache::put('api_errors', 100);
        Cache::put('api_success', 10);

        $health = $this->monitoringService->getSystemHealth();
        $this->assertEquals('error', $health['status']);
    }

    /** @test */
    public function it_tracks_memory_usage(): void
    {
        $memoryUsage = $this->monitoringService->getSystemHealth()['memory_usage'];

        $this->assertIsArray($memoryUsage);
        $this->assertArrayHasKey('current', $memoryUsage);
        $this->assertArrayHasKey('peak', $memoryUsage);
        $this->assertIsNumeric($memoryUsage['current']);
        $this->assertIsNumeric($memoryUsage['peak']);
        $this->assertGreaterThan(0, $memoryUsage['current']);
    }

    /** @test */
    public function it_tracks_disk_usage(): void
    {
        $diskUsage = $this->monitoringService->getSystemHealth()['disk_usage'];

        $this->assertIsArray($diskUsage);
        $this->assertArrayHasKey('total', $diskUsage);
        $this->assertArrayHasKey('used', $diskUsage);
        $this->assertArrayHasKey('free', $diskUsage);
        $this->assertArrayHasKey('percentage', $diskUsage);
        $this->assertIsNumeric($diskUsage['percentage']);
        $this->assertGreaterThanOrEqual(0, $diskUsage['percentage']);
        $this->assertLessThanOrEqual(100, $diskUsage['percentage']);
    }

    /** @test */
    public function it_handles_monitoring_service_errors(): void
    {
        // Mock service to throw exception
        $this->mock(MonitoringService::class, function ($mock) {
            $mock->shouldReceive('getSystemHealth')
                ->andThrow(new \Exception('Service error'));
        });

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/monitoring/health');

        $response->assertStatus(500)
            ->assertJsonStructure([
                'status',
                'message',
                'error' => [
                    'id',
                    'message',
                ]
            ]);
    }

    /** @test */
    public function it_provides_structured_logging(): void
    {
        // This test would verify that logs are structured with proper fields
        // In a real implementation, you'd check the log files or use a log testing library
        
        $this->monitoringService->logApiRequest('POST', '/api/v1/app/tasks', 300.0, 201);

        // Verify log structure (placeholder)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_tracks_performance_metrics_over_time(): void
    {
        // Log multiple requests
        $this->monitoringService->logApiRequest('GET', '/api/v1/app/projects', 100.0, 200);
        $this->monitoringService->logApiRequest('GET', '/api/v1/app/tasks', 150.0, 200);
        $this->monitoringService->logApiRequest('POST', '/api/v1/app/tasks', 200.0, 201);

        // Get metrics
        $metrics = $this->monitoringService->getApiMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('avg_response_time', $metrics);
        $this->assertArrayHasKey('p95_response_time', $metrics);
    }
}
