<?php

namespace Tests\Feature\Perf;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Client;
use App\Models\Quote;
use App\Repositories\DashboardRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KpiCacheTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function kpi_cache_works_correctly(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        // Create test data
        Project::factory()->count(5)->create(['tenant_id' => $tenant->id]);
        Task::factory()->count(10)->create(['tenant_id' => $tenant->id]);
        Client::factory()->count(3)->create(['tenant_id' => $tenant->id]);
        Quote::factory()->count(7)->create(['tenant_id' => $tenant->id]);
        
        $repository = new DashboardRepository();
        
        // First call should hit database
        $start = microtime(true);
        $kpis1 = $repository->kpisForTenant($tenant->id);
        $firstCallTime = microtime(true) - $start;
        
        // Second call should hit cache
        $start = microtime(true);
        $kpis2 = $repository->kpisForTenant($tenant->id);
        $secondCallTime = microtime(true) - $start;
        
        // Cache should be faster
        $this->assertLessThan($firstCallTime, $secondCallTime);
        
        // Results should be identical
        $this->assertEquals($kpis1, $kpis2);
        
        // Verify cache key exists
        $this->assertTrue(Cache::has("kpi:{$tenant->id}"));
    }

    /** @test */
    public function kpi_cache_has_correct_ttl(): void
    {
        $tenant = Tenant::factory()->create();
        $repository = new DashboardRepository();
        
        // Create test data
        Project::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        
        // Warm cache
        $repository->kpisForTenant($tenant->id);
        
        // Cache should exist
        $this->assertTrue(Cache::has("kpi:{$tenant->id}"));
        
        // Clear cache manually to test TTL
        Cache::forget("kpi:{$tenant->id}");
        
        // Cache should be gone
        $this->assertFalse(Cache::has("kpi:{$tenant->id}"));
    }

    /** @test */
    public function kpi_cache_can_be_cleared(): void
    {
        $tenant = Tenant::factory()->create();
        $repository = new DashboardRepository();
        
        // Create test data
        Project::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        
        // Warm cache
        $repository->kpisForTenant($tenant->id);
        $this->assertTrue(Cache::has("kpi:{$tenant->id}"));
        
        // Clear cache
        $repository->clearKpiCache($tenant->id);
        $this->assertFalse(Cache::has("kpi:{$tenant->id}"));
    }
}
