<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    /**
     * Test rate limiting on authentication endpoints
     */
    public function test_auth_endpoints_rate_limiting()
    {
        $endpoint = '/api/auth/login';
        $data = [
            'email' => 'test@example.com',
            'password' => 'password'
        ];

        // Test normal requests within limit
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson($endpoint, $data);
            
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

        // Test rate limit exceeded
        $response = $this->postJson($endpoint, $data);
        $this->assertEquals(429, $response->getStatusCode());
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertEquals('0', $response->headers->get('X-RateLimit-Remaining'));
    }

    /**
     * Test burst limit functionality
     */
    public function test_burst_limit_functionality()
    {
        $endpoint = '/api/auth/login';
        $data = [
            'email' => 'test@example.com',
            'password' => 'password'
        ];

        // Make requests up to burst limit (20)
        for ($i = 0; $i < 20; $i++) {
            $response = $this->postJson($endpoint, $data);
            
            if ($i < 10) {
                // Normal limit
                $this->assertNotEquals(429, $response->getStatusCode());
            } else {
                // Once the limit is reached, the auth limiter should block (no burst)
                $this->assertEquals(429, $response->getStatusCode());
                $this->assertFalse($response->headers->has('X-RateLimit-Burst'));
            }
        }

        // 21st request should still be rate limited
        $response = $this->postJson($endpoint, $data);
        $this->assertEquals(429, $response->getStatusCode());
    }

    /**
     * Test rate limiting on API endpoints
     */
    public function test_api_endpoints_rate_limiting()
    {
        // Create a user and get token for authenticated requests
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $endpoint = '/api/dashboard/data';
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ];

        // Test normal requests within API limit (100/minute)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->getJson($endpoint, $headers);
            
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
        $endpoint = '/api/auth/login';
        $data = [
            'email' => 'test@example.com',
            'password' => 'password'
        ];

        // Simulate requests from different IPs
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.1']);
        // Make 10 requests from first IP
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson($endpoint, $data);
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 11th request should be rate limited
        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.1'])->postJson($endpoint, $data);
        $this->assertEquals(429, $response->getStatusCode());

        // Switch to different IP
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.2']);
        
        // Should be able to make requests from different IP
        $response = $this->postJson($endpoint, $data);
        $this->assertNotEquals(429, $response->getStatusCode());
    }

    /**
     * Test rate limiting window reset
     */
    public function test_rate_limiting_window_reset()
    {
        $endpoint = '/api/auth/login';
        $data = [
            'email' => 'test@example.com',
            'password' => 'password'
        ];

        // Exhaust rate limit
        for ($i = 0; $i < 10; $i++) {
            $this->postJson($endpoint, $data);
        }

        // Should be rate limited
        $response = $this->postJson($endpoint, $data);
        $this->assertEquals(429, $response->getStatusCode());

        // Clear cache to simulate window reset
        Cache::flush();

        // Should be able to make requests again
        $response = $this->postJson($endpoint, $data);
        $this->assertNotEquals(429, $response->getStatusCode());
    }

    /**
     * Test rate limiting headers format
     */
    public function test_rate_limiting_headers_format()
    {
        $endpoint = '/api/auth/login';
        $data = [
            'email' => 'test@example.com',
            'password' => 'password'
        ];

        $response = $this->postJson($endpoint, $data);

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
        $endpoint = '/api/auth/login';
        $data = [
            'email' => 'test@example.com',
            'password' => 'password'
        ];

        // Exhaust rate limit
        for ($i = 0; $i < 10; $i++) {
            $this->postJson($endpoint, $data);
        }

        $response = $this->postJson($endpoint, $data);

        $this->assertEquals(429, $response->getStatusCode());
        $this->assertJson($response->getContent());
        
        $responseData = $response->json();
        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('message', $responseData['error']);
        $this->assertArrayHasKey('code', $responseData['error']);
        $this->assertEquals('E429.RATE_LIMIT', $responseData['error']['code']);
    }
}
