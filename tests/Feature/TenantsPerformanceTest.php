<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class TenantsPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tenants;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email' => 'admin@test.com',
            'is_super_admin' => true
        ]);

        // Create test data
        $this->tenants = Tenant::factory()->count(100)->create();
    }

    /**
     * Test API response times
     */
    public function test_api_response_times()
    {
        $this->actingAs($this->user, 'sanctum');

        // Test tenants index endpoint
        $startTime = microtime(true);
        $response = $this->getJson('/api/admin/tenants');
        $endTime = microtime(true);
        
        $responseTime = $endTime - $startTime;
        $this->assertLessThan(0.5, $responseTime, 'Tenants index should respond within 500ms');
        $response->assertStatus(200);

        // Test KPI endpoint
        $startTime = microtime(true);
        $response = $this->getJson('/api/admin/tenants-kpis');
        $endTime = microtime(true);
        
        $responseTime = $endTime - $startTime;
        $this->assertLessThan(0.3, $responseTime, 'KPI endpoint should respond within 300ms');
        $response->assertStatus(200);
    }

    /**
     * Test database query performance
     */
    public function test_database_query_performance()
    {
        $this->actingAs($this->user, 'sanctum');

        // Test with pagination
        $startTime = microtime(true);
        $response = $this->getJson('/api/admin/tenants?page=1&per_page=25');
        $endTime = microtime(true);
        
        $responseTime = $endTime - $startTime;
        $this->assertLessThan(0.4, $responseTime, 'Paginated query should complete within 400ms');
        $response->assertStatus(200);

        // Test with filters
        $startTime = microtime(true);
        $response = $this->getJson('/api/admin/tenants?status=active&plan=pro');
        $endTime = microtime(true);
        
        $responseTime = $endTime - $startTime;
        $this->assertLessThan(0.3, $responseTime, 'Filtered query should complete within 300ms');
        $response->assertStatus(200);

        // Test with search
        $startTime = microtime(true);
        $response = $this->getJson('/api/admin/tenants?q=test');
        $endTime = microtime(true);
        
        $responseTime = $endTime - $startTime;
        $this->assertLessThan(0.5, $responseTime, 'Search query should complete within 500ms');
        $response->assertStatus(200);
    }

    /**
     * Test caching performance
     */
    public function test_caching_performance()
    {
        $this->actingAs($this->user, 'sanctum');

        // Clear cache
        Cache::flush();

        // First request (no cache)
        $startTime = microtime(true);
        $response = $this->getJson('/api/admin/tenants');
        $endTime = microtime(true);
        $firstRequestTime = $endTime - $startTime;

        // Second request (with cache)
        $startTime = microtime(true);
        $response = $this->getJson('/api/admin/tenants');
        $endTime = microtime(true);
        $secondRequestTime = $endTime - $startTime;

        // Cached request should be faster
        $this->assertLessThan($firstRequestTime, $secondRequestTime, 'Cached request should be faster');
        $this->assertLessThan(0.1, $secondRequestTime, 'Cached request should be under 100ms');
    }

    /**
     * Test bulk operations performance
     */
    public function test_bulk_operations_performance()
    {
        $this->actingAs($this->user, 'sanctum');

        $tenantIds = $this->tenants->take(10)->pluck('id')->toArray();

        // Test bulk suspend
        $startTime = microtime(true);
        $response = $this->postJson('/api/admin/tenants/bulk/suspend', [
            'tenant_ids' => $tenantIds,
            'reason' => 'Performance test'
        ]);
        $endTime = microtime(true);
        
        $responseTime = $endTime - $startTime;
        $this->assertLessThan(1.0, $responseTime, 'Bulk suspend should complete within 1 second');
        $response->assertStatus(200);

        // Test bulk resume
        $startTime = microtime(true);
        $response = $this->postJson('/api/admin/tenants/bulk/resume', [
            'tenant_ids' => $tenantIds,
            'reason' => 'Performance test'
        ]);
        $endTime = microtime(true);
        
        $responseTime = $endTime - $startTime;
        $this->assertLessThan(1.0, $responseTime, 'Bulk resume should complete within 1 second');
        $response->assertStatus(200);
    }

    /**
     * Test memory usage
     */
    public function test_memory_usage()
    {
        $this->actingAs($this->user, 'sanctum');

        $initialMemory = memory_get_usage();

        // Load tenants
        $response = $this->getJson('/api/admin/tenants?per_page=100');
        $response->assertStatus(200);

        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        // Memory usage should be reasonable (less than 10MB)
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, 'Memory usage should be less than 10MB');
    }

    /**
     * Test concurrent requests
     */
    public function test_concurrent_requests()
    {
        $this->actingAs($this->user, 'sanctum');

        $startTime = microtime(true);

        // Simulate concurrent requests
        $promises = [];
        for ($i = 0; $i < 10; $i++) {
            $promises[] = $this->getJson('/api/admin/tenants');
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // All requests should complete within reasonable time
        $this->assertLessThan(2.0, $totalTime, 'Concurrent requests should complete within 2 seconds');
    }

    /**
     * Test large dataset performance
     */
    public function test_large_dataset_performance()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create large dataset
        Tenant::factory()->count(1000)->create();

        $startTime = microtime(true);
        $response = $this->getJson('/api/admin/tenants?per_page=100');
        $endTime = microtime(true);
        
        $responseTime = $endTime - $startTime;
        $this->assertLessThan(1.0, $responseTime, 'Large dataset should load within 1 second');
        $response->assertStatus(200);
    }

    /**
     * Test KPI calculation performance
     */
    public function test_kpi_calculation_performance()
    {
        $this->actingAs($this->user, 'sanctum');

        $startTime = microtime(true);
        $response = $this->getJson('/api/admin/tenants-kpis');
        $endTime = microtime(true);
        
        $responseTime = $endTime - $startTime;
        $this->assertLessThan(0.5, $responseTime, 'KPI calculation should complete within 500ms');
        $response->assertStatus(200);

        // Test with different periods
        $periods = ['7d', '30d', '90d'];
        foreach ($periods as $period) {
            $startTime = microtime(true);
            $response = $this->getJson("/api/admin/tenants-kpis?period={$period}");
            $endTime = microtime(true);
            
            $responseTime = $endTime - $startTime;
            $this->assertLessThan(0.5, $responseTime, "KPI calculation for {$period} should complete within 500ms");
            $response->assertStatus(200);
        }
    }

    /**
     * Test export performance
     */
    public function test_export_performance()
    {
        $this->actingAs($this->user, 'sanctum');

        $startTime = microtime(true);
        $response = $this->getJson('/api/admin/tenants/export.csv');
        $endTime = microtime(true);
        
        $responseTime = $endTime - $startTime;
        $this->assertLessThan(2.0, $responseTime, 'Export should complete within 2 seconds');
        $response->assertStatus(200);
    }

    /**
     * Test database indexes
     */
    public function test_database_indexes()
    {
        // Test that indexes exist
        $indexes = \DB::select("SHOW INDEX FROM tenants");
        $indexNames = array_column($indexes, 'Key_name');

        $this->assertContains('idx_tenants_status', $indexNames, 'Status index should exist');
        $this->assertContains('idx_tenants_created_at', $indexNames, 'Created at index should exist');
        $this->assertContains('idx_tenants_domain_unique', $indexNames, 'Domain unique index should exist');
    }
}
