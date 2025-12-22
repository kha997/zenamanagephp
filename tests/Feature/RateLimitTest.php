<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test rate limiting with simple endpoint
     */
    public function test_rate_limiting_simple(): void
    {
        // Clear any existing rate limits
        RateLimiter::clear('api');

        // Test simple API endpoint multiple times
        for ($i = 0; $i < 65; $i++) {
            $response = $this->getJson('/api/user');
            
            if ($i < 60) {
                $this->assertEquals(200, $response->status()); // Success
            } else {
                $this->assertEquals(429, $response->status()); // Too Many Requests
                break; // Stop after first rate limit
            }
        }
    }
}
