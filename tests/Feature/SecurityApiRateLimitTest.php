<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class SecurityApiRateLimitTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create super admin user
        $this->user = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'admin@example.com'
        ]);
        
        // Create token with admin ability
        $this->token = $this->user->createToken('admin', ['admin'])->plainTextToken;
        
        // Clear rate limit cache
        Cache::flush();
        RateLimiter::clear('security_export');
        RateLimiter::clear('security_test_event');
    }

    /** @test */
    public function it_enforces_export_rate_limit()
    {
        $limit = 10; // per minute
        
        // Make requests up to the limit
        for ($i = 0; $i < $limit; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'text/csv'
            ])->get('/api/admin/security/audit/export');
            
            $response->assertStatus(200);
        }
        
        // Next request should be rate limited
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'text/csv'
        ])->get('/api/admin/security/audit/export');
        
        $response->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJson([
                'error' => [
                    'code' => 'RATE_LIMITED',
                    'message' => 'Too many export requests. Please try again later.'
                ]
            ]);
    }

    /** @test */
    public function it_enforces_test_event_rate_limit()
    {
        $limit = 5; // per minute
        
        // Make requests up to the limit
        for ($i = 0; $i < $limit; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post('/api/admin/security/test-event', [
                'event' => 'login_failed'
            ]);
            
            $response->assertStatus(200);
        }
        
        // Next request should be rate limited
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post('/api/admin/security/test-event', [
            'event' => 'login_failed'
        ]);
        
        $response->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJson([
                'error' => [
                    'code' => 'RATE_LIMITED',
                    'message' => 'Too many test event requests. Please try again later.'
                ]
            ]);
    }

    /** @test */
    public function it_respects_retry_after_header()
    {
        // Exceed rate limit
        for ($i = 0; $i < 11; $i++) {
            $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'text/csv'
            ])->get('/api/admin/security/audit/export');
        }
        
        // Check Retry-After header
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'text/csv'
        ])->get('/api/admin/security/audit/export');
        
        $retryAfter = $response->headers->get('Retry-After');
        $this->assertNotNull($retryAfter);
        $this->assertIsNumeric($retryAfter);
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(60, $retryAfter);
    }

    /** @test */
    public function it_has_different_rate_limits_for_different_endpoints()
    {
        // Test export rate limit (10/min)
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'text/csv'
            ])->get('/api/admin/security/audit/export');
            
            $response->assertStatus(200);
        }
        
        // Export should be rate limited
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'text/csv'
        ])->get('/api/admin/security/audit/export');
        
        $response->assertStatus(429);
        
        // But test event should still work (different rate limit)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post('/api/admin/security/test-event', [
            'event' => 'login_failed'
        ]);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function it_tracks_rate_limits_per_user()
    {
        // Create second admin user
        $user2 = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'admin2@example.com'
        ]);
        
        $token2 = $user2->createToken('admin', ['admin'])->plainTextToken;
        
        // User 1 exceeds rate limit
        for ($i = 0; $i < 11; $i++) {
            $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'text/csv'
            ])->get('/api/admin/security/audit/export');
        }
        
        // User 1 should be rate limited
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'text/csv'
        ])->get('/api/admin/security/audit/export');
        
        $response1->assertStatus(429);
        
        // User 2 should still work
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
            'Accept' => 'text/csv'
        ])->get('/api/admin/security/audit/export');
        
        $response2->assertStatus(200);
    }

    /** @test */
    public function it_resets_rate_limit_after_time_window()
    {
        // Exceed rate limit
        for ($i = 0; $i < 11; $i++) {
            $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'text/csv'
            ])->get('/api/admin/security/audit/export');
        }
        
        // Should be rate limited
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'text/csv'
        ])->get('/api/admin/security/audit/export');
        
        $response->assertStatus(429);
        
        // Manually clear rate limit (simulating time window reset)
        RateLimiter::clear('security_export');
        
        // Should work again
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'text/csv'
        ])->get('/api/admin/security/audit/export');
        
        $response->assertStatus(200);
    }

    /** @test */
    public function it_handles_rate_limit_with_different_http_methods()
    {
        // GET requests should be rate limited
        for ($i = 0; $i < 11; $i++) {
            $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'text/csv'
            ])->get('/api/admin/security/audit/export');
        }
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'text/csv'
        ])->get('/api/admin/security/audit/export');
        
        $response->assertStatus(429);
        
        // POST requests should have separate rate limit
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post('/api/admin/security/test-event', [
            'event' => 'login_failed'
        ]);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function it_logs_rate_limit_violations()
    {
        // Exceed rate limit
        for ($i = 0; $i < 11; $i++) {
            $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'text/csv'
            ])->get('/api/admin/security/audit/export');
        }
        
        // Check that rate limit violation is logged
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'rate_limit_exceeded',
            'user_id' => $this->user->id,
            'details' => json_encode([
                'endpoint' => 'audit/export',
                'limit' => 10,
                'window' => '1 minute'
            ])
        ]);
    }

    /** @test */
    public function it_includes_rate_limit_info_in_response_headers()
    {
        // Make a few requests
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'text/csv'
            ])->get('/api/admin/security/audit/export');
            
            $response->assertStatus(200);
            
            // Check rate limit headers
            $this->assertNotNull($response->headers->get('X-RateLimit-Limit'));
            $this->assertNotNull($response->headers->get('X-RateLimit-Remaining'));
            $this->assertNotNull($response->headers->get('X-RateLimit-Reset'));
            
            $this->assertEquals('10', $response->headers->get('X-RateLimit-Limit'));
            $this->assertEquals((string)(10 - $i - 1), $response->headers->get('X-RateLimit-Remaining'));
        }
    }
}
