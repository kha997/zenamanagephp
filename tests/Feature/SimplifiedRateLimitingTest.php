<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use App\Services\RateLimitService;
use App\Services\RateLimitConfigurationService;
use App\Services\ComprehensiveLoggingService;
use Illuminate\Support\Facades\Cache;

/**
 * Simplified Rate Limiting System Test Suite
 * 
 * Tests core functionality of the rate limiting system
 */
class SimplifiedRateLimitingTest extends TestCase
{
    use RefreshDatabase;
    
    private RateLimitService $rateLimitService;
    private RateLimitConfigurationService $configService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->rateLimitService = app(RateLimitService::class);
        $this->configService = app(RateLimitConfigurationService::class);
        
        // Clear cache before each test
        Cache::flush();
    }
    
    /**
     * Test basic rate limiting functionality
     */
    public function test_basic_rate_limiting(): void
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'role' => 'member'];
        });
        
        // First request should be allowed
        $result = $this->rateLimitService->checkRateLimit($request, 'api');
        
        $this->assertTrue($result['allowed']);
        $this->assertArrayHasKey('strategy', $result);
        $this->assertArrayHasKey('current_requests', $result);
        $this->assertArrayHasKey('max_requests', $result);
        $this->assertArrayHasKey('remaining', $result);
    }
    
    /**
     * Test rate limiting configuration service
     */
    public function test_configuration_service(): void
    {
        // Test getting default config
        $config = $this->configService->getConfig('api');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('requests_per_minute', $config);
        $this->assertArrayHasKey('burst_limit', $config);
        $this->assertArrayHasKey('strategy', $config);
        
        // Test updating config
        $newConfig = [
            'requests_per_minute' => 200,
            'burst_limit' => 400,
            'strategy' => 'token_bucket',
        ];
        
        $this->assertTrue($this->configService->updateConfig('test', $newConfig));
        
        // Test getting updated config
        $updatedConfig = $this->configService->getConfig('test');
        $this->assertEquals(200, $updatedConfig['requests_per_minute']);
        $this->assertEquals('token_bucket', $updatedConfig['strategy']);
    }
    
    /**
     * Test configuration validation
     */
    public function test_configuration_validation(): void
    {
        // Test valid config
        $validConfig = [
            'requests_per_minute' => 100,
            'burst_limit' => 200,
            'window_size' => 60,
            'strategy' => 'sliding_window',
        ];
        
        $errors = $this->configService->validateConfig($validConfig);
        $this->assertEmpty($errors);
        
        // Test invalid config
        $invalidConfig = [
            'requests_per_minute' => -1,
            'burst_limit' => 50,
            'window_size' => 60,
            'strategy' => 'invalid_strategy',
        ];
        
        $errors = $this->configService->validateConfig($invalidConfig);
        $this->assertNotEmpty($errors);
        $this->assertContains('requests_per_minute must be a positive integer', $errors);
        $this->assertContains('strategy must be one of: sliding_window, token_bucket, fixed_window', $errors);
    }
    
    /**
     * Test rate limiting with different user roles
     */
    public function test_rate_limiting_by_user_role(): void
    {
        $memberRequest = Request::create('/test', 'GET');
        $memberRequest->setUserResolver(function () {
            return (object) ['id' => 1, 'role' => 'member'];
        });
        
        $adminRequest = Request::create('/test', 'GET');
        $adminRequest->setUserResolver(function () {
            return (object) ['id' => 2, 'role' => 'admin'];
        });
        
        // Both should be allowed
        $memberResult = $this->rateLimitService->checkRateLimit($memberRequest, 'api');
        $adminResult = $this->rateLimitService->checkRateLimit($adminRequest, 'api');
        
        $this->assertTrue($memberResult['allowed']);
        $this->assertTrue($adminResult['allowed']);
        
        // Admin should have higher max_requests
        $this->assertGreaterThan($memberResult['max_requests'], $adminResult['max_requests']);
    }
    
    /**
     * Test rate limiting for unauthenticated users
     */
    public function test_rate_limiting_for_guests(): void
    {
        $request = Request::create('/test', 'GET');
        // No user resolver set - simulates unauthenticated user
        
        $result = $this->rateLimitService->checkRateLimit($request, 'public');
        
        $this->assertTrue($result['allowed']);
        $this->assertArrayHasKey('strategy', $result);
    }
    
    /**
     * Test rate limit statistics
     */
    public function test_rate_limit_statistics(): void
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'role' => 'member'];
        });
        
        // Make some requests
        for ($i = 1; $i <= 5; $i++) {
            $this->rateLimitService->checkRateLimit($request, 'api');
        }
        
        $stats = $this->rateLimitService->getRateLimitStats();
        $this->assertIsArray($stats);
    }
    
    /**
     * Test configuration statistics
     */
    public function test_configuration_statistics(): void
    {
        $stats = $this->configService->getConfigStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_endpoints', $stats);
        $this->assertArrayHasKey('total_strategies', $stats);
        $this->assertArrayHasKey('role_multipliers', $stats);
        $this->assertArrayHasKey('endpoint_multipliers', $stats);
        $this->assertArrayHasKey('configurations', $stats);
        
        $this->assertGreaterThan(0, $stats['total_endpoints']);
        $this->assertGreaterThan(0, $stats['total_strategies']);
    }
    
    /**
     * Test rate limiting with different endpoints
     */
    public function test_rate_limiting_different_endpoints(): void
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'role' => 'member'];
        });
        
        // Test auth endpoint (should have lower limits)
        $authResult = $this->rateLimitService->checkRateLimit($request, 'auth');
        $this->assertTrue($authResult['allowed']);
        
        // Test upload endpoint (should have even lower limits)
        $uploadResult = $this->rateLimitService->checkRateLimit($request, 'upload');
        $this->assertTrue($uploadResult['allowed']);
        
        // Auth should have lower limits than upload
        $this->assertLessThan($uploadResult['max_requests'], $authResult['max_requests']);
    }
    
    /**
     * Test clearing rate limits
     */
    public function test_clearing_rate_limits(): void
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'role' => 'member'];
        });
        
        // Make requests to create rate limit data
        for ($i = 1; $i <= 10; $i++) {
            $this->rateLimitService->checkRateLimit($request, 'api');
        }
        
        // Clear rate limits
        $cleared = $this->rateLimitService->clearRateLimit('user:1:127.0.0.1', 'api');
        $this->assertTrue($cleared);
    }
    
    /**
     * Test system load adjustment
     */
    public function test_system_load_adjustment(): void
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'role' => 'member'];
        });
        
        // Test with different system loads
        $contexts = [
            ['system_load' => 0.5], // Low load
            ['system_load' => 1.0], // Normal load
            ['system_load' => 1.5], // High load
        ];
        
        $results = [];
        foreach ($contexts as $context) {
            $config = $this->configService->getConfig('api', $context);
            $results[] = $config['requests_per_minute'];
        }
        
        // Higher load should result in lower limits
        $this->assertGreaterThan($results[1], $results[0]); // Low load > Normal load
        $this->assertGreaterThan($results[1], $results[2]); // Normal load > High load
    }
}
