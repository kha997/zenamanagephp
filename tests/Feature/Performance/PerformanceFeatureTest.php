<?php declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email' => 'performance-test@example.com',
            'tenant_id' => 'test-tenant',
        ]);
    }

    public function test_can_get_performance_dashboard()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/performance/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'performance',
                    'memory',
                    'network',
                    'recommendations' => [
                        'performance',
                        'memory',
                        'network',
                    ],
                    'thresholds' => [
                        'performance',
                        'memory',
                        'network',
                    ],
                    'timestamp',
                ],
            ]);
    }

    public function test_can_get_performance_stats()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/performance/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_can_get_memory_stats()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/performance/memory');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_can_get_network_stats()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/performance/network');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    public function test_can_get_recommendations()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/performance/recommendations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'performance',
                    'memory',
                    'network',
                ],
            ]);
    }

    public function test_can_get_thresholds()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/performance/thresholds');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'performance',
                    'memory',
                    'network',
                ],
            ]);
    }

    public function test_can_set_thresholds()
    {
        $thresholds = [
            'performance' => [
                'page_load_time' => 1000,
                'api_response_time' => 500,
            ],
            'memory' => [
                'warning' => 60,
                'critical' => 80,
            ],
            'network' => [
                'response_time' => 500,
                'timeout' => 60,
            ],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/thresholds', $thresholds);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Thresholds updated successfully',
            ]);
    }

    public function test_can_record_page_load_time()
    {
        $data = [
            'route' => '/test-route',
            'load_time' => 250.5,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/page-load', $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Page load time recorded',
            ]);
    }

    public function test_can_record_api_response_time()
    {
        $data = [
            'endpoint' => '/api/test',
            'response_time' => 150.3,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/api-response', $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'API response time recorded',
            ]);
    }

    public function test_can_record_memory_usage()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/memory');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Memory usage recorded',
            ]);
    }

    public function test_can_monitor_network_endpoint()
    {
        $data = [
            'url' => 'https://httpbin.org/get',
            'options' => [],
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/network-monitor', $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'url',
                    'response_time',
                    'success',
                ],
            ]);
    }

    public function test_can_get_realtime_metrics()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/performance/realtime');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'performance',
                    'memory',
                    'network',
                ],
            ]);
    }

    public function test_can_clear_performance_data()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/clear');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Performance data cleared',
            ]);
    }

    public function test_can_export_performance_data()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/performance/export');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'performance',
                    'memory',
                    'network',
                ],
            ]);
    }

    public function test_can_force_garbage_collection()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/gc');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'before_usage',
                    'after_usage',
                    'freed_memory',
                ],
            ]);
    }

    public function test_can_test_network_connectivity()
    {
        $data = [
            'url' => 'https://httpbin.org/get',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/test-connectivity', $data);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'url',
                    'success',
                    'response_time',
                ],
            ]);
    }

    public function test_can_get_network_health_status()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/performance/network-health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'health_score',
                    'status',
                    'recommendations_count',
                    'critical_issues',
                ],
            ]);
    }

    public function test_validates_page_load_time_input()
    {
        $data = [
            'route' => '', // Empty route
            'load_time' => -100, // Negative load time
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/page-load', $data);

        $response->assertStatus(422);
    }

    public function test_validates_api_response_time_input()
    {
        $data = [
            'endpoint' => '', // Empty endpoint
            'response_time' => -50, // Negative response time
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/api-response', $data);

        $response->assertStatus(422);
    }

    public function test_validates_thresholds_input()
    {
        $data = [
            'performance' => 'invalid', // Should be array
            'memory' => 'invalid', // Should be array
            'network' => 'invalid', // Should be array
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/thresholds', $data);

        $response->assertStatus(422);
    }

    public function test_validates_network_monitor_input()
    {
        $data = [
            'url' => 'invalid-url', // Invalid URL
            'options' => 'invalid', // Should be array
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/network-monitor', $data);

        $response->assertStatus(422);
    }

    public function test_validates_connectivity_test_input()
    {
        $data = [
            'url' => 'invalid-url', // Invalid URL
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/test-connectivity', $data);

        $response->assertStatus(422);
    }

    public function test_requires_authentication()
    {
        $response = $this->getJson('/api/admin/performance/dashboard');
        $response->assertStatus(401);
    }

    public function test_performance_dashboard_returns_structured_data()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/performance/dashboard');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Check performance data structure
        $this->assertArrayHasKey('performance', $data);
        
        // Check memory data structure
        $this->assertArrayHasKey('memory', $data);
        
        // Check network data structure
        $this->assertArrayHasKey('network', $data);
        
        // Check recommendations structure
        $this->assertArrayHasKey('recommendations', $data);
        $this->assertArrayHasKey('performance', $data['recommendations']);
        $this->assertArrayHasKey('memory', $data['recommendations']);
        $this->assertArrayHasKey('network', $data['recommendations']);
        
        // Check thresholds structure
        $this->assertArrayHasKey('thresholds', $data);
        $this->assertArrayHasKey('performance', $data['thresholds']);
        $this->assertArrayHasKey('memory', $data['thresholds']);
        $this->assertArrayHasKey('network', $data['thresholds']);
    }

    public function test_performance_monitoring_workflow()
    {
        // 1. Record page load time
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/page-load', [
                'route' => '/test-workflow',
                'load_time' => 300.0,
            ])
            ->assertStatus(200);

        // 2. Record API response time
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/api-response', [
                'endpoint' => '/api/test-workflow',
                'response_time' => 150.0,
            ])
            ->assertStatus(200);

        // 3. Record memory usage
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/performance/memory')
            ->assertStatus(200);

        // 4. Get dashboard data
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/admin/performance/dashboard')
            ->assertStatus(200);

        $data = $response->json('data');
        
        // Verify data structure exists (data may be empty initially)
        $this->assertArrayHasKey('performance', $data);
        $this->assertArrayHasKey('memory', $data);
        $this->assertArrayHasKey('network', $data);
    }
}
