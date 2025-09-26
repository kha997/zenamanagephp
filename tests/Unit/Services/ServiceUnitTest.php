<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\RateLimitService;
use App\Services\AdvancedCacheService;
use App\Services\WebSocketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    }

    /**
     * Test RateLimitService functionality
     */
    public function test_rate_limit_service()
    {
        $service = new RateLimitService();
        
        // Test getting configuration
        $config = $service->getConfig('auth');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('requests_per_minute', $config);
        $this->assertArrayHasKey('burst_limit', $config);
        $this->assertArrayHasKey('window_size', $config);
        
        // Test different rate limit types
        $authConfig = $service->getConfig('auth');
        $apiConfig = $service->getConfig('api');
        $defaultConfig = $service->getConfig('default');
        
        $this->assertNotEquals($authConfig, $apiConfig);
        $this->assertNotEquals($apiConfig, $defaultConfig);
        
        // Test identifier generation
        $identifier = $service->getIdentifier(request());
        $this->assertIsString($identifier);
        $this->assertNotEmpty($identifier);
    }

    /**
     * Test AdvancedCacheService functionality
     */
    public function test_advanced_cache_service()
    {
        if (!Redis::ping()) {
            $this->markTestSkipped('Redis is not available');
        }

        $service = new AdvancedCacheService();
        
        // Test cache stats
        $stats = $service->getStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('hit_rate', $stats);
        $this->assertArrayHasKey('miss_rate', $stats);
        $this->assertArrayHasKey('total_keys', $stats);
        $this->assertArrayHasKey('memory_usage', $stats);
        
        // Test cache config
        $config = $service->getConfig();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('driver', $config);
        $this->assertArrayHasKey('default_ttl', $config);
        $this->assertArrayHasKey('prefix', $config);
        
        // Test cache operations
        $testKey = 'test_key_' . uniqid();
        $testValue = 'test_value_' . uniqid();
        
        // Test put and get
        $service->put($testKey, $testValue, 300);
        $retrievedValue = $service->get($testKey);
        $this->assertEquals($testValue, $retrievedValue);
        
        // Test has
        $this->assertTrue($service->has($testKey));
        
        // Test forget
        $service->forget($testKey);
        $this->assertFalse($service->has($testKey));
        
        // Test tags
        $taggedKey = 'tagged_key_' . uniqid();
        $taggedValue = 'tagged_value_' . uniqid();
        $tag = 'test_tag';
        
        $service->tags([$tag])->put($taggedKey, $taggedValue, 300);
        $this->assertTrue($service->tags([$tag])->has($taggedKey));
        
        // Test tag invalidation
        $service->tags([$tag])->flush();
        $this->assertFalse($service->tags([$tag])->has($taggedKey));
    }

    /**
     * Test WebSocketService functionality
     */
    public function test_websocket_service()
    {
        $service = new WebSocketService();
        
        // Test connection info
        $info = $service->getConnectionInfo();
        $this->assertIsArray($info);
        $this->assertArrayHasKey('server_url', $info);
        $this->assertArrayHasKey('port', $info);
        $this->assertArrayHasKey('protocol', $info);
        $this->assertArrayHasKey('secure', $info);
        
        // Test stats
        $stats = $service->getStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_connections', $stats);
        $this->assertArrayHasKey('active_connections', $stats);
        $this->assertArrayHasKey('total_messages_sent', $stats);
        $this->assertArrayHasKey('total_messages_received', $stats);
        
        // Test channels
        $channels = $service->getChannels();
        $this->assertIsArray($channels);
        
        // Test connection test
        $testResult = $service->testConnection();
        $this->assertIsArray($testResult);
        $this->assertArrayHasKey('connection_status', $testResult);
        $this->assertArrayHasKey('response_time', $testResult);
        
        // Test user online/offline
        $userId = 1;
        $connectionId = 'test_connection_' . uniqid();
        
        $result = $service->markUserOnline($userId, $connectionId, [
            'browser' => 'Chrome',
            'os' => 'macOS'
        ]);
        $this->assertIsArray($result);
        $this->assertEquals($userId, $result['user_id']);
        $this->assertEquals('online', $result['status']);
        
        $result = $service->markUserOffline($userId, $connectionId, 'user_disconnect');
        $this->assertIsArray($result);
        $this->assertEquals($userId, $result['user_id']);
        $this->assertEquals('offline', $result['status']);
        
        // Test activity update
        $activityResult = $service->updateActivity($userId, 'page_view', [
            'page' => '/dashboard',
            'duration' => 30
        ]);
        $this->assertIsArray($activityResult);
        $this->assertEquals($userId, $activityResult['user_id']);
        $this->assertEquals('page_view', $activityResult['activity_type']);
        
        // Test broadcasting
        $broadcastResult = $service->broadcast('notifications', 'system_notification', [
            'title' => 'Test Notification',
            'message' => 'This is a test'
        ], [$userId]);
        $this->assertIsArray($broadcastResult);
        $this->assertEquals('notifications', $broadcastResult['channel']);
        $this->assertEquals('system_notification', $broadcastResult['event']);
        
        // Test notification sending
        $notificationResult = $service->sendNotification($userId, 'task_assigned', 'New Task', 'You have a new task', [
            'task_id' => 123
        ], 'normal');
        $this->assertIsArray($notificationResult);
        $this->assertEquals($userId, $notificationResult['user_id']);
        $this->assertEquals('task_assigned', $notificationResult['type']);
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
        $config = $rateLimitService->getConfig('invalid_type');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('requests_per_minute', $config); // Should return default
        
        // Test cache service with invalid operations
        try {
            $cacheService->get('non_existent_key');
            $this->assertTrue(true); // Should not throw exception
        } catch (\Exception $e) {
            $this->fail('Cache service should handle non-existent keys gracefully');
        }
        
        // Test WebSocket service with invalid data
        try {
            $websocketService->markUserOnline(0, '', []);
            $this->assertTrue(true); // Should handle invalid data gracefully
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
        
        // Test rate limit service performance
        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $rateLimitService->getConfig('auth');
        }
        $endTime = microtime(true);
        $rateLimitTime = ($endTime - $startTime) * 1000;
        $this->assertLessThan(100, $rateLimitTime, 'Rate limit service should be fast');
        
        // Test cache service performance
        if (Redis::ping()) {
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
}
