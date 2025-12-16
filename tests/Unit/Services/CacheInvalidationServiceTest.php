<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CacheInvalidationService;
use App\Services\AdvancedCacheService;
use App\Services\CacheKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Mockery;

/**
 * Cache Invalidation Service Unit Test
 * 
 * Tests CacheInvalidationService methods and invalidation map.
 * 
 * @group cache
 * @group unit
 */
class CacheInvalidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private CacheInvalidationService $service;
    private AdvancedCacheService $cacheService;
    private CacheKeyService $keyService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cacheService = app(AdvancedCacheService::class);
        $this->keyService = app(CacheKeyService::class);
        $this->service = new CacheInvalidationService($this->cacheService, $this->keyService);
        
        Cache::flush();
    }

    /**
     * Test: forTaskUpdate invalidates correct cache keys
     */
    public function test_for_task_update_invalidates_correct_keys(): void
    {
        $task = \App\Models\Task::factory()->create([
            'tenant_id' => 'tenant123',
            'project_id' => 'project456',
        ]);
        
        // Set cache that should be invalidated
        $taskKey = "task:{$task->id}";
        $projectKpiKey = "project:{$task->project_id}:kpis";
        
        $this->cacheService->set($taskKey, ['data'], 3600);
        $this->cacheService->set($projectKpiKey, ['kpis'], 3600);
        
        // Call forTaskUpdate
        $this->service->forTaskUpdate($task);
        
        // Verify cache is invalidated
        $this->assertFalse(Cache::has($taskKey), 'Task cache should be invalidated');
        $this->assertFalse(Cache::has($projectKpiKey), 'Project KPI cache should be invalidated');
    }

    /**
     * Test: forProjectUpdate invalidates correct cache keys
     */
    public function test_for_project_update_invalidates_correct_keys(): void
    {
        $project = \App\Models\Project::factory()->create([
            'tenant_id' => 'tenant123',
        ]);
        
        // Set cache that should be invalidated
        $projectKey = "project:{$project->id}";
        $projectKpiKey = "project:{$project->id}:kpis";
        
        $this->cacheService->set($projectKey, ['data'], 3600);
        $this->cacheService->set($projectKpiKey, ['kpis'], 3600);
        
        // Call forProjectUpdate
        $this->service->forProjectUpdate($project);
        
        // Verify cache is invalidated
        $this->assertFalse(Cache::has($projectKey), 'Project cache should be invalidated');
        $this->assertFalse(Cache::has($projectKpiKey), 'Project KPI cache should be invalidated');
    }

    /**
     * Test: invalidateOnEvent uses invalidation map
     */
    public function test_invalidate_on_event_uses_map(): void
    {
        $payload = [
            'task_id' => 'task123',
            'project_id' => 'project456',
            'tenant_id' => 'tenant789',
        ];
        
        // Set cache that should be invalidated
        $taskKey = "task:task123";
        $this->cacheService->set($taskKey, ['data'], 3600);
        
        // Call invalidateOnEvent with TaskUpdated event
        $this->service->invalidateOnEvent('TaskUpdated', $payload);
        
        // Verify cache is invalidated (if pattern resolution works)
        // Note: This depends on pattern resolution logic
        $this->markTestIncomplete('Pattern resolution testing requires specific cache key format');
    }

    /**
     * Test: resolvePattern replaces placeholders correctly
     */
    public function test_resolve_pattern_replaces_placeholders(): void
    {
        // This tests the private resolvePattern method indirectly
        // via invalidateOnEvent
        
        $payload = [
            'task_id' => 'task123',
            'project_id' => 'project456',
        ];
        
        // Set cache with resolved key
        $taskKey = "task:task123";
        $this->cacheService->set($taskKey, ['data'], 3600);
        
        // Call invalidateOnEvent which uses resolvePattern internally
        $this->service->invalidateOnEvent('TaskUpdated', $payload);
        
        // Cache should be invalidated if pattern resolution works
        $this->markTestIncomplete('Pattern resolution verification requires reflection or public method');
    }

    /**
     * Test: invalidateEntity invalidates entity cache
     */
    public function test_invalidate_entity_invalidates_cache(): void
    {
        $entityId = 'entity123';
        
        // Set entity cache
        $entityKey = "task:{$entityId}";
        $this->cacheService->set($entityKey, ['data'], 3600);
        
        // Call invalidateEntity
        $this->service->invalidateEntity('task', $entityId);
        
        // Verify cache is invalidated
        $this->assertFalse(Cache::has($entityKey), 'Entity cache should be invalidated');
    }

    /**
     * Test: invalidateTenant invalidates all tenant cache
     */
    public function test_invalidate_tenant_invalidates_all_tenant_cache(): void
    {
        $tenantId = 'tenant123';
        
        // Set tenant cache
        $tenantKey1 = "tenant:{$tenantId}:project:1";
        $tenantKey2 = "tenant:{$tenantId}:task:2";
        
        $this->cacheService->set($tenantKey1, ['data1'], 3600);
        $this->cacheService->set($tenantKey2, ['data2'], 3600);
        
        // Call invalidateTenant
        $this->service->invalidateTenant($tenantId);
        
        // Verify cache is invalidated (pattern-based)
        // Note: Pattern invalidation may not work with all cache drivers
        $this->markTestIncomplete('Tenant pattern invalidation requires Redis cache driver');
    }
}

