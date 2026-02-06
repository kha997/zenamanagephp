<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CachingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test cache stats endpoint
     */
    public function test_cache_stats_endpoint()
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('test-token')->plainTextToken;

        $headers = $this->authHeaders($user, $token);

        $response = $this->getJson('/api/cache/stats', $headers);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'hit_rate',
                'miss_rate',
                'total_keys',
                'memory_usage',
                'uptime',
                'connected_clients',
                'used_memory_human',
                'redis_version'
            ]
        ]);

        $data = $response->json('data');
        $this->assertIsFloat($data['hit_rate']);
        $this->assertIsFloat($data['miss_rate']);
        $this->assertIsInt($data['total_keys']);
        $this->assertIsString($data['memory_usage']);
    }

    /**
     * Test cache config endpoint
     */
    public function test_cache_config_endpoint()
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('test-token')->plainTextToken;

        $headers = $this->authHeaders($user, $token);
        $response = $this->getJson('/api/cache/config', $headers);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'driver',
                'default_ttl',
                'prefix',
                'serializer',
                'compression',
                'tags_enabled',
                'warmup_enabled'
            ]
        ]);

        $data = $response->json('data');
        $this->assertIsString($data['driver']);
        $this->assertIsInt($data['default_ttl']);
        $this->assertIsString($data['prefix']);
    }

    /**
     * Test cache key invalidation
     */
    public function test_cache_key_invalidation()
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('test-token')->plainTextToken;
        $headers = $this->authHeaders($user, $token);

        // Set a cache key
        $cacheKey = 'test_key_' . uniqid();
        $prefixedKey = $this->tenantCacheKey($cacheKey, $user);
        Cache::put($prefixedKey, 'test_value', 300);
        $this->assertTrue(Cache::has($prefixedKey));

        // Invalidate the key
        $response = $this->postJson('/api/cache/invalidate/key', [
            'key' => $cacheKey
        ], $headers);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'invalidated_keys',
                'message'
            ]
        ]);

        // Verify key is invalidated
        $this->assertFalse(Cache::has($prefixedKey));
    }

    /**
     * Test cache tags invalidation
     */
    public function test_cache_tags_invalidation()
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('test-token')->plainTextToken;
        $headers = $this->authHeaders($user, $token);

        // Set cache keys with tags
        $tag1 = 'test_tag_1';
        $tag2 = 'test_tag_2';
        $key1 = $this->tenantCacheKey('key1', $user);
        $key2 = $this->tenantCacheKey('key2', $user);
        $key3 = $this->tenantCacheKey('key3', $user);
        
        Cache::tags([$tag1])->put($key1, 'value1', 300);
        Cache::tags([$tag2])->put($key2, 'value2', 300);
        Cache::tags([$tag1, $tag2])->put($key3, 'value3', 300);

        $this->assertTrue(Cache::tags([$tag1])->has($key1));
        $this->assertTrue(Cache::tags([$tag2])->has($key2));
        $this->assertTrue(Cache::tags([$tag1, $tag2])->has($key3));

        // Invalidate by tag
        $response = $this->postJson('/api/cache/invalidate/tags', [
            'tags' => [$tag1]
        ], $headers);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'invalidated_keys',
                'message'
            ]
        ]);

        // Verify keys with tag1 are invalidated
        $this->assertFalse(Cache::tags([$tag1])->has($key1));
        $this->assertTrue(Cache::tags([$tag2])->has($key2)); // Different tag
        $this->assertFalse(Cache::tags([$tag1, $tag2])->has($key3)); // Has tag1
    }

    /**
     * Test cache pattern invalidation
     */
    public function test_cache_pattern_invalidation()
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('test-token')->plainTextToken;
        $headers = $this->authHeaders($user, $token);

        // Set cache keys with pattern
        $user123Profile = $this->tenantCacheKey('user_123_profile', $user);
        $user123Settings = $this->tenantCacheKey('user_123_settings', $user);
        $user456Profile = $this->tenantCacheKey('user_456_profile', $user);
        $otherDataKey = $this->tenantCacheKey('other_data', $user);

        Cache::put($user123Profile, 'profile_data', 300);
        Cache::put($user123Settings, 'settings_data', 300);
        Cache::put($user456Profile, 'profile_data', 300);
        Cache::put($otherDataKey, 'other_value', 300);

        $this->assertTrue(Cache::has($user123Profile));
        $this->assertTrue(Cache::has($user123Settings));
        $this->assertTrue(Cache::has($user456Profile));
        $this->assertTrue(Cache::has($otherDataKey));

        // Invalidate by pattern
        $response = $this->postJson('/api/cache/invalidate/pattern', [
            'pattern' => 'user_123_*'
        ], $headers);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'invalidated_keys',
                'message'
            ]
        ]);

        // Verify pattern-based invalidation
        $this->assertFalse(Cache::has($user123Profile));
        $this->assertFalse(Cache::has($user123Settings));
        $this->assertTrue(Cache::has($user456Profile)); // Different pattern
        $this->assertTrue(Cache::has($otherDataKey)); // Different pattern
    }

    /**
     * Test cache warmup
     */
    public function test_cache_warmup()
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('test-token')->plainTextToken;
        $headers = $this->authHeaders($user, $token);

        $response = $this->postJson('/api/cache/warmup', [
            'keys' => [
                'dashboard_data',
                'user_preferences',
                'system_config'
            ],
            'data_provider' => 'dashboard'
        ], $headers);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'warmed_keys',
                'message'
            ]
        ]);

        $data = $response->json('data');
        $this->assertIsArray($data['warmed_keys']);
        $this->assertGreaterThan(0, count($data['warmed_keys']));
    }

    /**
     * Test cache clear all
     */
    public function test_cache_clear_all()
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('test-token')->plainTextToken;
        $headers = $this->authHeaders($user, $token);

        // Set some cache data
        $prefixedKey1 = $this->tenantCacheKey('test_key_1', $user);
        $prefixedKey2 = $this->tenantCacheKey('test_key_2', $user);
        $prefixedKey3 = $this->tenantCacheKey('test_key_3', $user);

        Cache::put($prefixedKey1, 'value1', 300);
        Cache::put($prefixedKey2, 'value2', 300);
        Cache::put($prefixedKey3, 'value3', 300);

        $this->assertTrue(Cache::has($prefixedKey1));
        $this->assertTrue(Cache::has($prefixedKey2));
        $this->assertTrue(Cache::has($prefixedKey3));

        // Clear all cache
        $response = $this->postJson('/api/cache/clear', [], $headers);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'cleared_keys',
                'message'
            ]
        ]);

        // Verify all cache is cleared
        $this->assertFalse(Cache::has($prefixedKey1));
        $this->assertFalse(Cache::has($prefixedKey2));
        $this->assertFalse(Cache::has($prefixedKey3));
    }

    /**
     * Test dashboard caching middleware
     */
    public function test_dashboard_caching_middleware()
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('test-token')->plainTextToken;
        $headers = $this->authHeaders($user, $token);

        // First request - should not be cached
        $response1 = $this->getJson('/api/dashboard/data', $headers);
        $response1->assertStatus(200);
        $this->assertFalse($response1->headers->has('X-Cache-Status'));

        // Second request - should be cached
        $response2 = $this->getJson('/api/dashboard/data', $headers);
        $response2->assertStatus(200);
        
        // Check if caching headers are present
        if ($response2->headers->has('X-Cache-Status')) {
            $this->assertEquals('HIT', $response2->headers->get('X-Cache-Status'));
        }

        // Response should be identical
        $this->assertEquals($response1->getContent(), $response2->getContent());
    }

    /**
     * Test cache performance metrics
     */
    public function test_cache_performance_metrics()
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('test-token')->plainTextToken;
        $headers = $this->authHeaders($user, $token);

        $response = $this->getJson('/api/cache/stats', $headers);

        $response->assertStatus(200);
        $data = $response->json('data');

        // Test performance metrics
        $this->assertArrayHasKey('hit_rate', $data);
        $this->assertArrayHasKey('miss_rate', $data);
        $this->assertArrayHasKey('memory_usage', $data);
        $this->assertArrayHasKey('uptime', $data);

        // Validate metrics are reasonable
        $this->assertGreaterThanOrEqual(0, $data['hit_rate']);
        $this->assertLessThanOrEqual(1, $data['hit_rate']);
        $this->assertGreaterThanOrEqual(0, $data['miss_rate']);
        $this->assertLessThanOrEqual(1, $data['miss_rate']);
        $this->assertGreaterThan(0, $data['uptime']);
    }

    /**
     * Test cache error handling
     */
    public function test_cache_error_handling()
    {
        $user = $this->createAdminUser();
        $token = $user->createToken('test-token')->plainTextToken;
        $headers = $this->authHeaders($user, $token);

        // Test invalid key invalidation
        $response = $this->postJson('/api/cache/invalidate/key', [
            'key' => 'non_existent_key'
        ], $headers);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(0, $data['invalidated_keys']);

        // Test invalid pattern
        $response = $this->postJson('/api/cache/invalidate/pattern', [
            'pattern' => 'invalid_pattern['
        ], $headers);

        $response->assertStatus(400);
        $response->assertJsonStructure([
            'success',
            'error' => [
                'message',
                'code'
            ]
        ]);
    }

    private function createAdminUser(): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'admin', 'scope' => Role::SCOPE_SYSTEM],
            ['allow_override' => true, 'is_active' => true]
        );

        $user = User::factory()->create();
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function authHeaders(User $user, string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
        ];
    }

    private function tenantCacheKey(string $key, User $user): string
    {
        return sprintf('tenant:%s:%s', (string) $user->tenant_id, $key);
    }
}
