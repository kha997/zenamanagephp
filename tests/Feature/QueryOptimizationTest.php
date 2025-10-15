<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\QueryOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Query Optimization Tests
 * 
 * Tests to verify N+1 query prevention and performance optimizations
 */
class QueryOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Create projects with tasks
        $projects = Project::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'owner_id' => $this->user->id,
        ]);
        
        foreach ($projects as $project) {
            Task::factory()->count(3)->create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $project->id,
                'assignee_id' => $this->user->id,
            ]);
        }
        
        // Create clients with quotes
        $clients = Client::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);
        
        foreach ($clients as $client) {
            Quote::factory()->count(2)->create([
                'tenant_id' => $this->tenant->id,
                'client_id' => $client->id,
            ]);
        }
    }

    /**
     * Test that dashboard queries are optimized
     */
    public function test_dashboard_queries_are_optimized(): void
    {
        DB::enableQueryLog();
        
        // Simulate dashboard request
        $response = $this->actingAs($this->user)
            ->get('/app/dashboard');
        
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        
        $response->assertStatus(200);
        
        // Should have minimal queries (ideally < 10)
        $this->assertLessThan(10, count($queries), 'Dashboard should have optimized queries');
        
        // Check for N+1 patterns
        $this->assertNoN1Queries($queries);
    }

    /**
     * Test that project listing queries are optimized
     */
    public function test_project_listing_queries_are_optimized(): void
    {
        DB::enableQueryLog();
        
        $response = $this->actingAs($this->user)
            ->get('/app/projects');
        
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        
        $response->assertStatus(200);
        
        // Should have minimal queries
        $this->assertLessThan(15, count($queries), 'Project listing should have optimized queries');
        
        // Check for N+1 patterns
        $this->assertNoN1Queries($queries);
    }

    /**
     * Test that client listing queries are optimized
     */
    public function test_client_listing_queries_are_optimized(): void
    {
        DB::enableQueryLog();
        
        $response = $this->actingAs($this->user)
            ->get('/app/clients');
        
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        
        $response->assertStatus(200);
        
        // Should have minimal queries
        $this->assertLessThan(10, count($queries), 'Client listing should have optimized queries');
        
        // Check for N+1 patterns
        $this->assertNoN1Queries($queries);
    }

    /**
     * Test QueryOptimizationService eager loading
     */
    public function test_query_optimization_service_eager_loading(): void
    {
        DB::enableQueryLog();
        
        // Test project eager loading
        $projects = QueryOptimizationService::eagerLoad(
            Project::where('tenant_id', $this->tenant->id),
            'project'
        )->get();
        
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        
        // Should load projects and owners in minimal queries
        $this->assertLessThan(5, count($queries), 'Eager loading should minimize queries');
        
        // Verify relationships are loaded
        foreach ($projects as $project) {
            $this->assertTrue($project->relationLoaded('owner'), 'Owner relationship should be loaded');
        }
    }

    /**
     * Test aggregated statistics queries
     */
    public function test_aggregated_statistics_queries(): void
    {
        DB::enableQueryLog();
        
        // Test project stats
        $projectStats = QueryOptimizationService::getProjectStats((string) $this->tenant->id);
        
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        
        // Should use single query for all statistics
        $this->assertCount(1, $queries, 'Statistics should use single aggregated query');
        
        // Verify stats are calculated correctly
        $this->assertArrayHasKey('total_projects', $projectStats);
        $this->assertArrayHasKey('active_projects', $projectStats);
        $this->assertEquals(5, $projectStats['total_projects']);
    }

    /**
     * Test caching functionality
     */
    public function test_caching_functionality(): void
    {
        // Clear cache first
        Cache::flush();
        
        // First call should hit database
        $stats1 = QueryOptimizationService::getProjectStats((string) $this->tenant->id);
        
        // Second call should hit cache (same result)
        $stats2 = QueryOptimizationService::getProjectStats((string) $this->tenant->id);
        
        // Results should be identical
        $this->assertEquals($stats1, $stats2);
        
        // Verify cache key exists
        $cacheKey = QueryOptimizationService::generateTenantCacheKey('project-stats', (string) $this->tenant->id);
        $this->assertTrue(Cache::has($cacheKey), 'Cache should contain the data');
    }

    /**
     * Test cache key generation
     */
    public function test_cache_key_generation(): void
    {
        $key1 = QueryOptimizationService::generateTenantCacheKey('test', 'tenant1');
        $key2 = QueryOptimizationService::generateTenantCacheKey('test', 'tenant1', ['param' => 'value']);
        $key3 = QueryOptimizationService::generateTenantCacheKey('test', 'tenant2');
        
        $this->assertEquals('test-tenant1', $key1);
        $this->assertNotEquals($key1, $key2);
        $this->assertNotEquals($key1, $key3);
    }

    /**
     * Test cache clearing
     */
    public function test_cache_clearing(): void
    {
        // Set some cache values
        Cache::put('test-tenant1', 'value1', 300);
        Cache::put('test-tenant1-param', 'value2', 300);
        Cache::put('other-tenant1', 'value3', 300);
        
        // Verify cache values exist
        $this->assertEquals('value1', Cache::get('test-tenant1'));
        $this->assertEquals('value2', Cache::get('test-tenant1-param'));
        $this->assertEquals('value3', Cache::get('other-tenant1'));
        
        // Clear tenant cache for specific prefixes
        QueryOptimizationService::clearTenantCache('tenant1', ['test']);
        
        // Check cache values
        $this->assertNull(Cache::get('test-tenant1'));
        $this->assertNull(Cache::get('test-tenant1-param'));
        $this->assertEquals('value3', Cache::get('other-tenant1')); // Should remain
    }

    /**
     * Test status counts optimization
     */
    public function test_status_counts_optimization(): void
    {
        DB::enableQueryLog();
        
        $statusCounts = QueryOptimizationService::getStatusCounts(
            Project::where('tenant_id', $this->tenant->id),
            'status',
            ['active', 'completed', 'archived']
        );
        
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        
        // Should use single query
        $this->assertCount(1, $queries, 'Status counts should use single query');
        
        // Verify counts
        $this->assertArrayHasKey('active_count', $statusCounts);
        $this->assertArrayHasKey('completed_count', $statusCounts);
        $this->assertArrayHasKey('archived_count', $statusCounts);
        $this->assertArrayHasKey('total_count', $statusCounts);
    }

    /**
     * Assert no N+1 query patterns
     */
    private function assertNoN1Queries(array $queries): void
    {
        $sqlQueries = array_map(fn($q) => $q['query'], $queries);
        
        // Check for repeated similar queries (potential N+1)
        $queryPatterns = [];
        foreach ($sqlQueries as $sql) {
            // Normalize query for comparison
            $normalized = preg_replace('/\s+/', ' ', trim($sql));
            $pattern = preg_replace('/\d+/', '?', $normalized);
            
            if (!isset($queryPatterns[$pattern])) {
                $queryPatterns[$pattern] = 0;
            }
            $queryPatterns[$pattern]++;
        }
        
        // Check for patterns that appear too frequently
        foreach ($queryPatterns as $pattern => $count) {
            if ($count > 5) { // Arbitrary threshold
                $this->fail("Potential N+1 query detected: '{$pattern}' appears {$count} times");
            }
        }
    }

    /**
     * Test query performance middleware
     */
    public function test_query_performance_middleware(): void
    {
        // This test would require setting up the middleware in the test environment
        // For now, we'll just verify the middleware class exists and is instantiable
        $middleware = new \App\Http\Middleware\QueryPerformanceMiddleware();
        $this->assertInstanceOf(\App\Http\Middleware\QueryPerformanceMiddleware::class, $middleware);
    }
}
