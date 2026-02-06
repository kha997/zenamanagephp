<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTestTrait;

    private const LOGIN_ENDPOINT = '/api/auth/login';

    private ?string $prevCacheDefault = null;
    protected string $tenantId;
    protected array $loginCredentials = [];
    protected array $loginHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->prevCacheDefault = config('cache.default');
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->apiActingAsTenantAdmin();
        Cache::flush();
        $this->tenantId = $this->apiFeatureTenant->id;
        $this->loginCredentials = [
            'email' => $this->apiFeatureUser->email,
            'password' => 'password'
        ];
        $this->loginHeaders = [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenantId,
        ];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        if ($this->prevCacheDefault !== null) {
            config(['cache.default' => $this->prevCacheDefault]);
        }
        parent::tearDown();
    }

    protected function loginRequest(array $payload = null): TestResponse
    {
        return $this->withHeaders($this->loginHeaders)->postJson(self::LOGIN_ENDPOINT, $payload ?? $this->loginCredentials);
    }

    /**
     * Test rate limiting on authentication endpoints
     */
    public function test_auth_endpoints_rate_limiting()
    {
        $data = $this->loginCredentials;

        // Test normal requests within limit
        for ($i = 0; $i < 10; $i++) {
            $response = $this->loginRequest($data);
            
            // Should have rate limit headers
            $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
            $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
            $this->assertTrue($response->headers->has('X-RateLimit-Reset'));
            $this->assertTrue($response->headers->has('X-RateLimit-Window'));
            
            // Check header values
            $this->assertEquals('10', $response->headers->get('X-RateLimit-Limit'));
            $this->assertEquals('60', $response->headers->get('X-RateLimit-Window'));
            
            // Remaining should decrease
            $remaining = (int) $response->headers->get('X-RateLimit-Remaining');
            $this->assertEquals(9 - $i, $remaining);
        }

        // Consume the burst allowance (requests 11-20)
        for ($j = 0; $j < 10; $j++) {
            $this->loginRequest($data);
        }

        // 21st request should be rate limited
        $response = $this->loginRequest($data);
        $this->assertEquals(429, $response->getStatusCode());
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertEquals('0', $response->headers->get('X-RateLimit-Remaining'));
    }

    /**
     * Test burst limit functionality
     */
    public function test_burst_limit_functionality()
    {
        $data = $this->loginCredentials;

        // Make requests up to burst limit (20)
        for ($i = 0; $i < 20; $i++) {
            $response = $this->loginRequest($data);
            
            if ($i < 10) {
                // Normal limit
                $this->assertNotEquals(429, $response->getStatusCode());
            } else {
                // Burst limit - should still work but with burst header
                $this->assertNotEquals(429, $response->getStatusCode());
                if ($response->headers->has('X-RateLimit-Burst')) {
                    $this->assertEquals('true', $response->headers->get('X-RateLimit-Burst'));
                }
            }
        }

        // 21st request should be rate limited
        $response = $this->loginRequest($data);
        $this->assertEquals(429, $response->getStatusCode());
    }

    /**
     * Test rate limiting on API endpoints
     */
    public function test_api_endpoints_rate_limiting()
    {
        $endpoint = '/api/dashboard/data';

        // Test normal requests within API limit (100/minute)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->apiGet($endpoint);

            // Should have rate limit headers
            $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
            $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));

            // Check header values for API endpoints
            $this->assertEquals('100', $response->headers->get('X-RateLimit-Limit'));
        }
    }

    /**
     * Test rate limiting with different IP addresses
     */
    public function test_rate_limiting_per_ip_address()
    {
        $data = $this->loginCredentials;

        // Simulate requests from different IPs
        $baseIp = ['REMOTE_ADDR' => '192.168.1.1'];
        
        // Make 20 requests from first IP
        for ($i = 0; $i < 20; $i++) {
            $response = $this->withServerVariables($baseIp)->loginRequest($data);
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 21st request should be rate limited
        $response = $this->withServerVariables($baseIp)->loginRequest($data);
        $this->assertEquals(429, $response->getStatusCode());

        // Switch to different IP
        $newIp = ['REMOTE_ADDR' => '192.168.1.2'];
        
        // Should be able to make requests from different IP
        $response = $this->withServerVariables($newIp)->loginRequest($data);
        $this->assertNotEquals(429, $response->getStatusCode());
    }

    /**
     * Test rate limiting window reset
     */
    public function test_rate_limiting_window_reset()
    {
        $data = $this->loginCredentials;

        // Exhaust rate limit and burst allowance
        for ($i = 0; $i < 20; $i++) {
            $this->loginRequest($data);
        }

        // Should be rate limited
        $response = $this->loginRequest($data);
        $this->assertEquals(429, $response->getStatusCode());

        // Clear cache to simulate window reset after the bucket window expires
        $now = Carbon::now();
        Carbon::setTestNow($now->copy()->addMinutes(2));
        Cache::flush();

        // Should be able to make requests again
        $response = $this->loginRequest($data);
        $this->assertNotEquals(429, $response->getStatusCode());
    }

    /**
     * Test rate limiting headers format
     */
    public function test_rate_limiting_headers_format()
    {
        $response = $this->loginRequest();

        // Check all required headers are present
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));
        $this->assertTrue($response->headers->has('X-RateLimit-Reset'));
        $this->assertTrue($response->headers->has('X-RateLimit-Window'));

        // Check header values are valid
        $limit = (int) $response->headers->get('X-RateLimit-Limit');
        $remaining = (int) $response->headers->get('X-RateLimit-Remaining');
        $reset = (int) $response->headers->get('X-RateLimit-Reset');
        $window = (int) $response->headers->get('X-RateLimit-Window');

        $this->assertGreaterThan(0, $limit);
        $this->assertGreaterThanOrEqual(0, $remaining);
        $this->assertLessThanOrEqual($limit, $remaining);
        $this->assertGreaterThan(time(), $reset);
        $this->assertGreaterThan(0, $window);
    }

    /**
     * Test rate limiting error response format
     */
    public function test_rate_limiting_error_response()
    {
        // Exhaust rate limit and burst allowance
        for ($i = 0; $i < 20; $i++) {
            $this->loginRequest();
        }

        $response = $this->loginRequest();

        $response->assertStatus(429);
        $this->assertJson($response->getContent());

        $responseData = $response->json();
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Too many requests', $responseData['message']);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertIsArray($responseData['error']);
        $this->assertEquals('E429.RATE_LIMIT', $responseData['error']['code']);
        $this->assertStringContainsString('Too many requests', $responseData['error']['message']);
        $this->assertArrayHasKey('details', $responseData['error']);
        $this->assertIsArray($responseData['error']['details']);
    }
}
