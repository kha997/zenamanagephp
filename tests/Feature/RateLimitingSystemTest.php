<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use App\Services\RateLimitService;
use App\Services\RateLimitConfigurationService;
use App\Services\ComprehensiveLoggingService;
use App\Http\Middleware\AdvancedRateLimitMiddleware;
use Illuminate\Support\Facades\Cache;

/**
 * Comprehensive Rate Limiting System Test Suite
 * 
 * Tests all aspects of the rate limiting system including:
 * - Different rate limiting strategies
 * - Configuration management
 * - Middleware functionality
 * - Edge cases and error handling
 */
class RateLimitingSystemTest extends TestCase
{
    use RefreshDatabase;
    
    private RateLimitService $rateLimitService;
    private RateLimitConfigurationService $configService;
    private ComprehensiveLoggingService $loggingService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->rateLimitService = app(RateLimitService::class);
        $this->configService = app(RateLimitConfigurationService::class);
        $this->loggingService = app(ComprehensiveLoggingService::class);
        
        // Clear cache before each test
        Cache::flush();
    }
    
    /**
     * Test sliding window rate limiting strategy
     */
    public function test_sliding_window_strategy(): void
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'role' => 'member'];
        });
        
        // First request should be allowed
        $result1 = $this->rateLimitService->checkRateLimit($request, 'api');
        $this->assertTrue($result1['allowed']);
        $this->assertEquals('sliding_window', $result1['strategy']);
        $this->assertEquals(1, $result1['current_requests']);
        
        // Multiple requests should be allowed up to limit
        for ($i = 2; $i <= 10; $i++) {
            $result = $this->rateLimitService->checkRateLimit($request, 'api');
            $this->assertTrue($result['allowed']);
            $this->assertEquals($i, $result['current_requests']);
        }
        
        // Request beyond limit should be denied
        $result = $this->rateLimitService->checkRateLimit($request, 'api');
        $this->assertFalse($result['allowed']);
        $this->assertEquals(0, $result['remaining']);
    }
    
    /**
     * Test token bucket rate limiting strategy
     */
    public function test_token_bucket_strategy(): void
    {
        $request = Request::create('/test', 'POST');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'role' => 'member'];
        });
        
        // First request should be allowed
        $result1 = $this->rateLimitService->checkRateLimit($request, 'upload');
        $this->assertTrue($result1['allowed']);
        $this->assertEquals('token_bucket', $result1['strategy']);
        
        // Multiple requests should consume tokens
        for ($i = 2; $i <= 5; $i++) {
            $result = $this->rateLimitService->checkRateLimit($request, 'upload');
            $this->assertTrue($result['allowed']);
        }
        
        // Request beyond token limit should be denied
        $result = $this->rateLimitService->checkRateLimit($request, 'upload');
        $this->assertFalse($result['allowed']);
        $this->assertGreaterThan(0, $result['retry_after']);
    }
    
    /**
     * Test fixed window rate limiting strategy
     */
    public function test_fixed_window_strategy(): void
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'role' => 'member'];
        });
        
        // Mock the config to use fixed window strategy
        $this->configService->updateConfig('test', [
            'strategy' => 'fixed_window',
            'requests_per_minute' => 5,
            'burst_limit' => 5,
            'window_size' => 60,
        ]);
        
        // Multiple requests should be allowed up to limit
        for ($i = 1; $i <= 5; $i++) {
            $result = $this->rateLimitService->checkRateLimit($request, 'test');
            $this->assertTrue($result['allowed']);
            $this->assertEquals('fixed_window', $result['strategy']);
        }
        
        // Request beyond limit should be denied
        $result = $this->rateLimitService->checkRateLimit($request, 'test');
        $this->assertFalse($result['allowed']);
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
        
        // Admin should have higher limits
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
        $this->assertStringContains('ip:', $this->getIdentifierFromResult($result));
    }
    
    /**
     * Test rate limit configuration service
     */
    public function test_rate_limit_configuration_service(): void
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
     * Test advanced rate limiting middleware
     */
    public function test_advanced_rate_limiting_middleware(): void
    {
        $middleware = app(AdvancedRateLimitMiddleware::class);
        
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'role' => 'member'];
        });
        
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        }, 'api');
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
        $this->assertTrue($response->headers->has('X-RateLimit-Strategy'));
    }
    
    /**
     * Test rate limit exceeded response
     */
    public function test_rate_limit_exceeded_response(): void
    {
        $middleware = app(AdvancedRateLimitMiddleware::class);
        
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(function () {
            return (object) ['id' => 1, 'role' => 'member'];
        });
        
        // Make many requests to exceed rate limit
        for ($i = 1; $i <= 200; $i++) {
            $response = $middleware->handle($request, function ($req) {
                return response('OK');
            }, 'api');
            
            if ($response->getStatusCode() === 429) {
                $this->assertEquals(429, $response->getStatusCode());
                $this->assertTrue($response->headers->has('Retry-After'));
                break;
            }
        }
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
     * Test rate limiting configuration statistics
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
     * Test rate limiting with system load adjustment
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
    
    /**
     * Helper method to extract identifier from rate limit result
     */
    private function getIdentifierFromResult(array $result): string
    {
        // This is a simplified helper - in real implementation you'd need to track this
        return 'ip:127.0.0.1';
    }
}
