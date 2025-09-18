<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Src\Common\Services\CacheService;

/**
 * Unit tests cho CacheService
 * 
 * Kiểm tra caching logic, invalidation, và performance optimization
 */
class CacheServiceTest extends TestCase
{
    use RefreshDatabase;

    private CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = new CacheService();
        Cache::flush(); // Clear cache trước mỗi test
    }

    /**
     * Test basic cache operations
     */
    public function test_basic_cache_operations(): void
    {
        $key = 'test_key';
        $value = ['data' => 'test_value'];
        $ttl = 3600;

        // Test put
        $result = $this->cacheService->put($key, $value, $ttl);
        $this->assertTrue($result);

        // Test get
        $cached = $this->cacheService->get($key);
        $this->assertEquals($value, $cached);

        // Test has
        $this->assertTrue($this->cacheService->has($key));

        // Test forget
        $this->cacheService->forget($key);
        $this->assertFalse($this->cacheService->has($key));
    }

    /**
     * Test cache tags functionality
     */
    public function test_cache_tags(): void
    {
        $key1 = 'project_1_data';
        $key2 = 'project_1_tasks';
        $tags = ['project_1'];
        
        $this->cacheService->putWithTags($tags, $key1, 'project data', 3600);
        $this->cacheService->putWithTags($tags, $key2, 'tasks data', 3600);
        
        $this->assertTrue($this->cacheService->getWithTags($tags, $key1) !== null);
        $this->assertTrue($this->cacheService->getWithTags($tags, $key2) !== null);
        
        // Flush by tags
        $this->cacheService->flushByTags($tags);
        
        $this->assertNull($this->cacheService->getWithTags($tags, $key1));
        $this->assertNull($this->cacheService->getWithTags($tags, $key2));
    }

    /**
     * Test remember functionality
     */
    public function test_remember_functionality(): void
    {
        $key = 'expensive_calculation';
        $callCount = 0;
        
        $callback = function() use (&$callCount) {
            $callCount++;
            return 'calculated_result';
        };
        
        // First call should execute callback
        $result1 = $this->cacheService->remember($key, $callback, 3600);
        $this->assertEquals('calculated_result', $result1);
        $this->assertEquals(1, $callCount);
        
        // Second call should use cache
        $result2 = $this->cacheService->remember($key, $callback, 3600);
        $this->assertEquals('calculated_result', $result2);
        $this->assertEquals(1, $callCount); // Callback không được gọi lần 2
    }

    /**
     * Test cache invalidation patterns
     */
    public function test_cache_invalidation_patterns(): void
    {
        $projectId = 'project_123';
        $tags = ['project', "project_{$projectId}"];
        
        // Cache project data with tags
        $this->cacheService->putWithTags($tags, 'project_data', ['name' => 'Test Project'], 3600);
        $this->cacheService->putWithTags($tags, 'project_tasks', ['task1', 'task2'], 3600);
        $this->cacheService->putWithTags($tags, 'project_progress', 75.5, 3600);
        
        $this->assertTrue($this->cacheService->getWithTags($tags, 'project_data') !== null);
        $this->assertTrue($this->cacheService->getWithTags($tags, 'project_tasks') !== null);
        $this->assertTrue($this->cacheService->getWithTags($tags, 'project_progress') !== null);
        
        // Invalidate all project cache
        $this->cacheService->invalidateProjectCache($projectId);
        
        $this->assertNull($this->cacheService->getWithTags($tags, 'project_data'));
        $this->assertNull($this->cacheService->getWithTags($tags, 'project_tasks'));
        $this->assertNull($this->cacheService->getWithTags($tags, 'project_progress'));
    }
}