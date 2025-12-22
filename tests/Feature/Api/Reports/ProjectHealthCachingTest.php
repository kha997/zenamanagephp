<?php declare(strict_types=1);

namespace Tests\Feature\Api\Reports;

use App\Events\ProjectHealthPortfolioGenerated;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Project Health Portfolio Caching
 * 
 * Round 85: Project Health Portfolio Caching
 * 
 * Tests that project health portfolio caching works correctly:
 * - Event fires on every request when cache is disabled (default)
 * - Event fires only on first request when cache is enabled
 * - Caching is per-tenant (isolated)
 * 
 * @group reports
 * @group projects
 * @group health
 * @group caching
 */
class ProjectHealthCachingTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(45678);
        
        // Create tenant
        $this->tenant = Tenant::factory()->create(['name' => 'Test Tenant']);
        
        // Create user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'user@test.com',
        ]);
        
        // Attach user to tenant with 'admin' role (which has tenant.view_reports)
        $this->user->tenants()->attach($this->tenant->id, ['role' => 'admin']);
        
        // Create token
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /**
     * Test that when cache is disabled (default), event fires on every request
     */
    public function test_cache_disabled_event_fires_on_every_request(): void
    {
        // Create test data
        Project::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Ensure cache is disabled (default behavior)
        Config::set('reports.project_health.cache_enabled', false);
        Config::set('reports.project_health.cache_ttl_seconds', 60);

        Event::fake([ProjectHealthPortfolioGenerated::class]);

        Sanctum::actingAs($this->user);
        
        // First request
        $response1 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $response1->assertStatus(200);
        $data1 = $response1->json('data');
        $this->assertIsArray($data1);

        // Second request
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $response2->assertStatus(200);
        $data2 = $response2->json('data');
        $this->assertIsArray($data2);

        // Assert event was dispatched twice (once per request)
        Event::assertDispatched(ProjectHealthPortfolioGenerated::class, 2);
    }

    /**
     * Test that when cache is enabled, event fires only on first request per tenant
     */
    public function test_cache_enabled_event_fires_only_on_first_request(): void
    {
        // Create test data
        Project::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Enable caching
        Config::set('reports.project_health.cache_enabled', true);
        Config::set('reports.project_health.cache_ttl_seconds', 300);
        Config::set('reports.project_health.monitoring_enabled', true);
        Config::set('reports.project_health.sample_rate', 1.0);
        Config::set('reports.project_health.log_when_empty', true);

        Event::fake([ProjectHealthPortfolioGenerated::class]);

        Sanctum::actingAs($this->user);
        
        // First request - should trigger rebuild and dispatch event
        $response1 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $response1->assertStatus(200);
        $data1 = $response1->json('data');
        $this->assertIsArray($data1);
        $this->assertGreaterThanOrEqual(2, count($data1));

        // Second request - should hit cache and NOT dispatch event
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $response2->assertStatus(200);
        $data2 = $response2->json('data');
        $this->assertIsArray($data2);
        $this->assertGreaterThanOrEqual(2, count($data2));

        // Assert event was dispatched only once (first request only)
        Event::assertDispatched(ProjectHealthPortfolioGenerated::class, 1);
        
        // Verify the event has correct tenant ID
        Event::assertDispatched(ProjectHealthPortfolioGenerated::class, function ($event) {
            return $event->tenantId === (string) $this->tenant->id
                && $event->projectCount >= 2
                && $event->durationMs > 0;
        });
    }

    /**
     * Test that caching is per-tenant (isolated)
     */
    public function test_caching_is_per_tenant(): void
    {
        // Create two tenants
        $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        // Create users for each tenant
        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'email' => 'usera@test.com',
        ]);
        $userA->tenants()->attach($tenantA->id, ['role' => 'admin']);
        $tokenA = $userA->createToken('test-token-a')->plainTextToken;

        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'email' => 'userb@test.com',
        ]);
        $userB->tenants()->attach($tenantB->id, ['role' => 'admin']);
        $tokenB = $userB->createToken('test-token-b')->plainTextToken;

        // Create projects for each tenant
        Project::factory()->count(2)->create([
            'tenant_id' => $tenantA->id,
        ]);
        Project::factory()->count(2)->create([
            'tenant_id' => $tenantB->id,
        ]);

        // Enable caching
        Config::set('reports.project_health.cache_enabled', true);
        Config::set('reports.project_health.cache_ttl_seconds', 300);
        Config::set('reports.project_health.monitoring_enabled', true);
        Config::set('reports.project_health.sample_rate', 1.0);
        Config::set('reports.project_health.log_when_empty', true);

        Event::fake([ProjectHealthPortfolioGenerated::class]);

        // Tenant A: First request
        Sanctum::actingAs($userA);
        $responseA1 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$tokenA}",
        ])->getJson('/api/v1/app/reports/projects/health');
        $responseA1->assertStatus(200);

        // Tenant A: Second request (cache hit)
        $responseA2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$tokenA}",
        ])->getJson('/api/v1/app/reports/projects/health');
        $responseA2->assertStatus(200);

        // Tenant B: First request
        Sanctum::actingAs($userB);
        $responseB1 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$tokenB}",
        ])->getJson('/api/v1/app/reports/projects/health');
        $responseB1->assertStatus(200);

        // Tenant B: Second request (cache hit)
        $responseB2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$tokenB}",
        ])->getJson('/api/v1/app/reports/projects/health');
        $responseB2->assertStatus(200);

        // Assert total event dispatch count is 2 (one rebuild per tenant)
        Event::assertDispatched(ProjectHealthPortfolioGenerated::class, 2);

        // Verify each event has the correct tenant ID
        $dispatchedEvents = Event::dispatched(ProjectHealthPortfolioGenerated::class);
        $tenantIds = array_map(fn($event) => $event->tenantId, $dispatchedEvents);
        
        $this->assertContains((string) $tenantA->id, $tenantIds);
        $this->assertContains((string) $tenantB->id, $tenantIds);
        
        // Verify each tenant's event has correct project count
        $eventForTenantA = collect($dispatchedEvents)->first(fn($event) => $event->tenantId === (string) $tenantA->id);
        $eventForTenantB = collect($dispatchedEvents)->first(fn($event) => $event->tenantId === (string) $tenantB->id);
        
        $this->assertNotNull($eventForTenantA);
        $this->assertNotNull($eventForTenantB);
        $this->assertEquals(2, $eventForTenantA->projectCount);
        $this->assertEquals(2, $eventForTenantB->projectCount);
    }

    /**
     * Test that invalid TTL (<= 0) disables caching even if cache_enabled is true
     */
    public function test_invalid_ttl_disables_caching(): void
    {
        // Create test data
        Project::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Enable caching but set invalid TTL
        Config::set('reports.project_health.cache_enabled', true);
        Config::set('reports.project_health.cache_ttl_seconds', 0); // Invalid TTL

        Event::fake([ProjectHealthPortfolioGenerated::class]);

        Sanctum::actingAs($this->user);
        
        // First request
        $response1 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $response1->assertStatus(200);

        // Second request
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $response2->assertStatus(200);

        // Assert event was dispatched twice (caching disabled due to invalid TTL)
        Event::assertDispatched(ProjectHealthPortfolioGenerated::class, 2);
    }

    /**
     * Test that cache rebuilds after TTL expiration triggers new event
     * 
     * Round 94: Project Health Hardening (schema + caching TTL + snapshot command)
     */
    public function test_cache_rebuilds_after_ttl_expiration_triggers_new_event(): void
    {
        // Create test data
        Project::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Enable caching with a small TTL
        Config::set('reports.project_health.cache_enabled', true);
        Config::set('reports.project_health.cache_ttl_seconds', 60);
        Config::set('reports.project_health.monitoring_enabled', true);
        Config::set('reports.project_health.sample_rate', 1.0);
        Config::set('reports.project_health.log_when_empty', true);

        Event::fake([ProjectHealthPortfolioGenerated::class]);

        Sanctum::actingAs($this->user);

        // First request - should trigger rebuild and dispatch event
        $response1 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $response1->assertStatus(200);
        $data1 = $response1->json('data');
        $this->assertIsArray($data1);
        $this->assertGreaterThanOrEqual(2, count($data1));

        // Assert event was dispatched once (first request)
        Event::assertDispatched(ProjectHealthPortfolioGenerated::class, 1);

        // Second request - should hit cache and NOT dispatch event
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $response2->assertStatus(200);
        $data2 = $response2->json('data');
        $this->assertIsArray($data2);

        // Assert event still dispatched only once (cache hit)
        Event::assertDispatched(ProjectHealthPortfolioGenerated::class, 1);

        // Simulate TTL expiry by clearing the cache key
        // Use reflection to access the private makeCacheKeyForTenant method
        // to ensure we use the exact same cache key format as the service
        $service = app(\App\Services\Reports\ProjectHealthPortfolioService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('makeCacheKeyForTenant');
        $method->setAccessible(true);
        $cacheKey = $method->invoke($service, $this->tenant->id);
        \Illuminate\Support\Facades\Cache::forget($cacheKey);

        // Third request - should rebuild cache and dispatch event again
        $response3 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/app/reports/projects/health');

        $response3->assertStatus(200);
        $data3 = $response3->json('data');
        $this->assertIsArray($data3);
        $this->assertGreaterThanOrEqual(2, count($data3));

        // Assert event was dispatched twice total (first request + cache rebuild after expiry)
        Event::assertDispatched(ProjectHealthPortfolioGenerated::class, 2);

        // Verify both events have correct tenant ID
        // Event::dispatched() returns a Collection where each item is an array [eventInstance, ...]
        $dispatchedEvents = Event::dispatched(ProjectHealthPortfolioGenerated::class);
        $this->assertCount(2, $dispatchedEvents, 'Should have dispatched 2 events');
        
        foreach ($dispatchedEvents as $eventArray) {
            // Each item is an array, first element is the event instance
            $event = is_array($eventArray) ? $eventArray[0] : $eventArray;
            $this->assertEquals((string) $this->tenant->id, $event->tenantId);
            $this->assertGreaterThanOrEqual(2, $event->projectCount);
            $this->assertGreaterThan(0, $event->durationMs);
        }
    }
}

