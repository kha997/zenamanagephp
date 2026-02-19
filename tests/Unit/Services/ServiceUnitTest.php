<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\RateLimitService;
use App\Services\AdvancedCacheService;
use App\Services\WebSocketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class ServiceUnitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        if (!Cache::hasMacro('expire')) {
            Cache::macro('expire', function (string $key, int $seconds) {
                $value = Cache::get($key);
                Cache::put($key, $value, $seconds);
                return true;
            });
        }
    }

    /**
     * Test RateLimitService functionality
     */
    public function test_rate_limit_service()
    {
        $service = new RateLimitService();
        $request = Request::create('/api/test', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.5']);

        $defaultConfig = $service->getRateLimitConfig($request);
        $authConfig = $service->getRateLimitConfig($request, 'auth/login');
        $uploadConfig = $service->getRateLimitConfig($request, 'upload');

        $this->assertIsArray($defaultConfig);
        $this->assertArrayHasKey('requests_per_minute', $defaultConfig);
        $this->assertArrayHasKey('burst_limit', $defaultConfig);
        $this->assertArrayHasKey('daily_limit', $defaultConfig);

        $this->assertNotEquals($defaultConfig, $authConfig);
        $this->assertNotEquals($authConfig, $uploadConfig);

        $result = $service->isRequestAllowed($request, 'auth/login');
        $this->assertTrue($result['allowed']);
        $this->assertArrayHasKey('minute_limit', $result);
        $this->assertArrayHasKey('daily_limit', $result);
        $this->assertArrayHasKey('burst_limit', $result);
        $this->assertSame($authConfig, $result['config']);

        $identifier = 'ip:' . $request->ip();
        $stats = $service->getRateLimitStats($identifier);
        $this->assertArrayHasKey('minute', $stats);
        $this->assertArrayHasKey('daily', $stats);
        $this->assertArrayHasKey('burst', $stats);
    }

    /**
     * Test AdvancedCacheService functionality
     *
     * @group redis
     */
    public function test_advanced_cache_service()
    {
        $this->skipUnlessRedisAvailable();

        $service = new AdvancedCacheService();

        $expectedPublicMethods = ['get', 'set', 'invalidate', 'warmUp', 'getStats'];
        foreach ($expectedPublicMethods as $method) {
            $this->assertTrue(
                method_exists($service, $method),
                "AdvancedCacheService should expose {$method}() as part of its public API"
            );
        }
        
        // Test cache stats
        $stats = $service->getStats();
        $this->assertIsArray($stats);
        foreach (['hit_rate', 'miss_rate', 'total_keys', 'memory_usage', 'redis_version'] as $key) {
            $this->assertArrayHasKey($key, $stats, "AdvancedCacheService stats should include {$key}");
        }
        $this->assertArrayHasKey('redis', $stats, 'Stats should include redis diagnostics');
        $this->assertIsArray($stats['redis']);
        $this->assertArrayHasKey('cache_size', $stats);
        $this->assertIsInt($stats['cache_size']);
        $this->assertArrayHasKey('connected_clients', $stats);
        $this->assertIsInt($stats['connected_clients']);

        $testKey = 'advanced_cache_test_' . uniqid();
        $testValue = ['value' => 'cache-data'];

        $this->assertTrue($service->set($testKey, $testValue, ['ttl' => 300]));
        $this->assertSame($testValue, $service->get($testKey));

        $this->assertTrue($service->invalidate($testKey), 'invalidate should return true when deleting the key');
        $this->assertNull($service->get($testKey));

        $warmValue = ['warmed' => true];
        $warmUpResult = $service->warmUp([$testKey], fn (string $key) => $warmValue);
        $this->assertTrue($warmUpResult, 'warmUp should return true when it completes');
        $this->assertSame($warmValue, $service->get($testKey));
    }

    /**
     * Test WebSocketService functionality
     */
    public function test_websocket_service()
    {
        $service = new WebSocketService();

        $channels = $service->getChannels();
        $this->assertArrayHasKey('dashboard', $channels);

        $this->assertTrue($service->isValidEvent('dashboard', 'widget_refresh'));
        $this->assertFalse($service->isValidEvent('dashboard', 'nonexistent_event'));

        $this->assertTrue($service->broadcast('dashboard', 'widget_refresh', ['payload' => 'value']));
        $this->assertTrue($service->broadcastToUser('user-1', 'user_activity', ['event' => 'ping']));
        $this->assertTrue($service->broadcastToTenant('tenant-1', 'notifications', 'system_alert', ['message' => 'test']));
        $this->assertTrue($service->broadcastToUsers(['user-1', 'user-2'], 'system_broadcast', ['message' => 'hello']));
        $this->assertTrue($service->sendNotification('user-1', ['title' => 'alert', 'message' => 'hello']));

        $this->assertTrue($service->broadcastDashboardUpdate('kpi_change', ['value' => 42]));
        $this->assertTrue($service->broadcastProjectUpdate(123, 'project_updated', ['status' => 'ok']));
        $this->assertTrue($service->broadcastTaskUpdate(456, 'task_assigned', ['assignee' => 'Alice']));

        $this->assertTrue($service->markUserOnline('user-1'));
        $this->assertTrue($service->markUserOffline('user-1'));
        $this->assertTrue($service->updateUserActivity('user-1', 'page_view'));

        $stats = $service->getStats();
        $this->assertArrayHasKey('total_connections', $stats);
        $this->assertArrayHasKey('total_messages_sent', $stats);
        $this->assertArrayHasKey('online_users', $stats);
        $this->assertArrayHasKey('redis_connected', $stats);

        $eventTypes = $service->getEventTypes('dashboard');
        $this->assertContains('kpi_change', $eventTypes);
    }

    /**
     * Test service error handling
     */
    public function test_service_error_handling()
    {
        $rateLimitService = new RateLimitService();
        $cacheService = new AdvancedCacheService();
        $websocketService = new WebSocketService();
        
        // Test invalid rate limit type
        $configRequest = Request::create('/nonexistent', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.7']);
        $config = $rateLimitService->getRateLimitConfig($configRequest, 'invalid_type');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('requests_per_minute', $config);
        $this->assertArrayHasKey('daily_limit', $config);
        
        // Test cache service with invalid operations
        try {
            $cacheService->get('non_existent_key');
            $this->assertTrue(true); // Should not throw exception
        } catch (\Exception $e) {
            $this->fail('Cache service should handle non-existent keys gracefully');
        }
        
        // Test WebSocket service with invalid data
        try {
            $this->assertTrue($websocketService->markUserOnline(''));
            $this->assertTrue($websocketService->markUserOffline(''));
        } catch (\Exception $e) {
            $this->fail('WebSocket service should handle invalid data gracefully');
        }
    }

    /**
     * Test service performance
     */
    public function test_service_performance()
    {
        $rateLimitService = new RateLimitService();
        $cacheService = new AdvancedCacheService();
        $websocketService = new WebSocketService();
        $performanceRequest = Request::create('/performance', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.1']);
        
        // Test rate limit service performance
        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $rateLimitService->getRateLimitConfig($performanceRequest, 'auth/login');
        }
        $endTime = microtime(true);
        $rateLimitTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(100, $rateLimitTime, 'Rate limit service should be fast');
        
        // Test cache service performance
        try {
            $redisAvailable = Redis::ping();
        } catch (\Throwable $e) {
            $redisAvailable = false;
        }

        if ($redisAvailable) {
            $startTime = microtime(true);
            for ($i = 0; $i < 100; $i++) {
                $cacheService->getStats();
            }
            $endTime = microtime(true);
            $cacheTime = ($endTime - $startTime) * 1000;
            $this->assertLessThan(500, $cacheTime, 'Cache service should be reasonably fast');
        }
        
        // Test WebSocket service performance
        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $websocketService->getStats();
        }
        $endTime = microtime(true);
        $websocketTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(200, $websocketTime, 'WebSocket service should be fast');
    }

    /**
     * Test service data validation
     */
    public function test_service_data_validation()
    {
        $cacheService = new AdvancedCacheService();
        $websocketService = new WebSocketService();
        
        // Test cache service data validation
        $stats = $cacheService->getStats();
        $this->assertIsFloat($stats['hit_rate']);
        $this->assertIsFloat($stats['miss_rate']);
        $this->assertIsInt($stats['total_keys']);
        $this->assertIsString($stats['memory_usage']);
        
        // Validate rate ranges
        $this->assertGreaterThanOrEqual(0, $stats['hit_rate']);
        $this->assertLessThanOrEqual(1, $stats['hit_rate']);
        $this->assertGreaterThanOrEqual(0, $stats['miss_rate']);
        $this->assertLessThanOrEqual(1, $stats['miss_rate']);
        
        // Test WebSocket service data validation
        $stats = $websocketService->getStats();
        $this->assertIsInt($stats['total_connections']);
        $this->assertIsInt($stats['active_connections']);
        $this->assertIsInt($stats['total_messages_sent']);
        $this->assertIsInt($stats['total_messages_received']);
        $this->assertIsInt($stats['uptime']);
        $this->assertIsFloat($stats['cpu_usage']);
        
        // Validate ranges
        $this->assertGreaterThanOrEqual(0, $stats['total_connections']);
        $this->assertGreaterThanOrEqual(0, $stats['active_connections']);
        $this->assertGreaterThanOrEqual(0, $stats['total_messages_sent']);
        $this->assertGreaterThanOrEqual(0, $stats['total_messages_received']);
        $this->assertGreaterThanOrEqual(0, $stats['uptime']);
        $this->assertGreaterThanOrEqual(0, $stats['cpu_usage']);
        $this->assertLessThanOrEqual(100, $stats['cpu_usage']);
    }

    /**
     * @group redis
     */
    private function skipUnlessRedisAvailable(): void
    {
        try {
            Redis::connection()->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'Redis dependency unavailable for @group redis tests; configure REDIS_HOST/REDIS_PORT. Error: ' . $e->getMessage()
            );
        }
    }
}
