<?php declare(strict_types=1);

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SSOT\FixtureFactory;
use Tests\Traits\AuthenticationTrait;

class PerformanceMonitoringTest extends TestCase
{
    use RefreshDatabase, FixtureFactory, AuthenticationTrait;

    protected $user;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant + PM user with explicit RBAC role assignment (SSOT helper)
        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUserWithRbac(
            $this->tenant,
            'project_manager',
            'project_manager',
            [],
            ['role' => 'project_manager']
        );
    }

    /**
     * Test API performance budgets
     */
    public function test_api_performance_budgets()
    {
        // Create test data
        $this->createTestData(100, 500);

        $this->apiAs($this->user, $this->tenant);

        // Test dashboard stats endpoint performance
        $startTime = microtime(true);
        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');
        $endTime = microtime(true);
        
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        
        // Performance budget: API should complete within 300ms
        $this->assertLessThan(300, $executionTime, 
            "Dashboard stats API should complete within 300ms, took {$executionTime}ms");
    }

    /**
     * Test page performance budgets
     */
    public function test_page_performance_budgets()
    {
        // Create test data
        $this->createTestData(50, 200);

        $this->actingAs($this->user)
            ->withHeaders($this->apiHeadersForTenant((string) $this->tenant->id));

        // Test dashboard page performance
        $startTime = microtime(true);
        $response = $this->get('/app/dashboard');
        $endTime = microtime(true);
        
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        
        // Performance budget: Page should complete within 500ms
        $this->assertLessThan(500, $executionTime, 
            "Dashboard page should complete within 500ms, took {$executionTime}ms");
    }

    /**
     * Test database query performance
     */
    public function test_database_query_performance()
    {
        // Create test data
        $this->createTestData(200, 1000);

        $this->apiAs($this->user, $this->tenant);

        // Enable query logging
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');
        
        $queries = DB::getQueryLog();
        
        $response->assertStatus(200);
        
        // Performance budget: includes auth + tenant isolation + RBAC guard checks
        $this->assertLessThanOrEqual(12, count($queries),
            "Dashboard stats should not exceed 12 queries, executed " . count($queries) . " queries");
        
        // Performance budget: No query should take more than 100ms
        foreach ($queries as $query) {
            $this->assertLessThan(100, $query['time'], 
                "Query should not take more than 100ms, took {$query['time']}ms: " . $query['query']);
        }
    }

    /**
     * Test memory usage performance
     */
    public function test_memory_usage_performance()
    {
        // Create test data
        $this->createTestData(100, 500);

        $this->apiAs($this->user, $this->tenant);

        $memoryBefore = memory_get_usage();
        
        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');
        
        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;
        
        $response->assertStatus(200);
        
        // Performance budget: Should not use more than 10MB
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, 
            "Dashboard stats should not use more than 10MB, used " . round($memoryUsed / 1024 / 1024, 2) . "MB");
    }

    /**
     * Test concurrent request performance
     */
    public function test_concurrent_request_performance()
    {
        // Create test data
        $this->createTestData(50, 200);

        $this->apiAs($this->user, $this->tenant);

        $startTime = microtime(true);
        
        // Simulate concurrent requests
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->getJson('/api/v1/project-manager/dashboard/stats');
        }
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;
        
        // All responses should be successful
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        // Performance budget: 10 concurrent requests should complete within 2 seconds
        $this->assertLessThan(2000, $executionTime, 
            "10 concurrent requests should complete within 2 seconds, took {$executionTime}ms");
    }

    /**
     * Test large dataset performance
     */
    public function test_large_dataset_performance()
    {
        // Create large dataset
        $this->createTestData(1000, 5000);

        $this->apiAs($this->user, $this->tenant);

        $startTime = microtime(true);
        
        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        
        // Performance budget: Large dataset should complete within 1 second
        $this->assertLessThan(1000, $executionTime, 
            "Large dataset should complete within 1 second, took {$executionTime}ms");
    }

    /**
     * Test N+1 query prevention
     */
    public function test_n_plus_one_query_prevention()
    {
        // Create test data with relationships
        $projects = Project::factory()->count(50)->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id
        ]);

        foreach ($projects as $project) {
            Task::factory()->count(10)->create([
                'project_id' => $project->id
            ]);
        }

        $this->apiAs($this->user, $this->tenant);

        // Enable query logging
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');
        
        $queries = DB::getQueryLog();
        
        $response->assertStatus(200);
        
        // Performance budget: Should not have N+1 queries
        $this->assertLessThanOrEqual(5, count($queries), 
            "Should not have N+1 queries, executed " . count($queries) . " queries");
    }

    /**
     * Test cache performance
     */
    public function test_cache_performance()
    {
        // Create test data
        $this->createTestData(100, 500);

        $this->apiAs($this->user, $this->tenant);

        // First request (cache miss)
        $startTime = microtime(true);
        $response1 = $this->getJson('/api/v1/project-manager/dashboard/stats');
        $endTime = microtime(true);
        $firstRequestTime = ($endTime - $startTime) * 1000;

        // Second request (cache hit)
        $startTime = microtime(true);
        $response2 = $this->getJson('/api/v1/project-manager/dashboard/stats');
        $endTime = microtime(true);
        $secondRequestTime = ($endTime - $startTime) * 1000;

        $response1->assertStatus(200);
        $response2->assertStatus(200);
        
        // Performance budget: warmed request should not regress significantly vs first hit.
        $this->assertLessThan($firstRequestTime * 1.5, $secondRequestTime,
            "Subsequent request should not be more than 50% slower");
    }

    /**
     * Test error handling performance
     */
    public function test_error_handling_performance()
    {
        $this->apiAs($this->user, $this->tenant);

        $startTime = microtime(true);
        
        // Test error response performance
        $response = $this->getJson('/api/v1/nonexistent-endpoint'); // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_NONEXISTENT_ENDPOINT)
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(404);
        
        // Performance budget: Error responses should complete within 100ms
        $this->assertLessThan(100, $executionTime, 
            "Error response should complete within 100ms, took {$executionTime}ms");
    }

    /**
     * Test authentication performance
     */
    public function test_authentication_performance()
    {
        $startTime = microtime(true);
        
        // Test authentication performance
        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(401);
        
        // Performance budget: Authentication check should complete within 50ms
        $this->assertLessThan(50, $executionTime, 
            "Authentication check should complete within 50ms, took {$executionTime}ms");
    }

    /**
     * Create test data for performance testing
     */
    private function createTestData(int $projectCount, int $taskCount): void
    {
        // Create projects
        $projects = Project::factory()->count($projectCount)->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id,
            'budget_planned' => rand(10000, 100000),
            'budget_actual' => rand(5000, 80000)
        ]);

        // Create tasks
        $tasksPerProject = intval($taskCount / $projectCount);
        foreach ($projects as $project) {
            Task::factory()->count($tasksPerProject)->create([
                'project_id' => $project->id,
                'status' => ['pending', 'in_progress', 'completed'][rand(0, 2)]
            ]);
        }
    }
}
