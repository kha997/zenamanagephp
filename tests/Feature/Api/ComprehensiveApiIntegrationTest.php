<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ComprehensiveApiIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test complete authentication workflow with rate limiting
     */
    public function test_complete_authentication_workflow()
    {
        // Test CSRF token endpoint (public)
        $response = $this->getJson('/api/csrf-token');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'csrf_token'
            ]
        ]);

        // Test login with rate limiting
        $user = \App\Models\User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        $loginData = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        // First login attempt
        $response = $this->postJson('/api/auth/login', $loginData);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user',
                'token',
                'expires_at'
            ]
        ]);

        // Check rate limiting headers
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));

        $token = $response->json('data.token');

        // Test authenticated endpoints
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ];

        // Test user info endpoint
        $response = $this->getJson('/api/auth/me', $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'email',
                'created_at'
            ]
        ]);

        // Test permissions endpoint
        $response = $this->getJson('/api/auth/permissions', $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'permissions',
                'roles'
            ]
        ]);

        // Test logout
        $response = $this->postJson('/api/auth/logout', [], $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message'
        ]);
    }

    /**
     * Test dashboard workflow with caching
     */
    public function test_dashboard_workflow_with_caching()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ];

        // Test dashboard data endpoint (should be cached)
        $response1 = $this->getJson('/api/dashboard/data', $headers);
        $response1->assertStatus(200);
        $response1->assertJsonStructure([
            'success',
            'data' => [
                'projects',
                'tasks',
                'notifications',
                'statistics'
            ]
        ]);

        // Second request should be faster (cached)
        $response2 = $this->getJson('/api/dashboard/data', $headers);
        $response2->assertStatus(200);
        $this->assertEquals($response1->getContent(), $response2->getContent());

        // Test dashboard analytics
        $response = $this->getJson('/api/dashboard/analytics', $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'charts',
                'metrics',
                'trends'
            ]
        ]);

        // Test dashboard notifications
        $response = $this->getJson('/api/dashboard/notifications', $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'notifications',
                'unread_count'
            ]
        ]);

        // Test dashboard preferences
        $response = $this->getJson('/api/dashboard/preferences', $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'theme',
                'layout',
                'widgets'
            ]
        ]);
    }

    /**
     * Test cache management workflow
     */
    public function test_cache_management_workflow()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ];

        // Test cache stats
        $response = $this->getJson('/api/cache/stats', $headers);
        $response->assertStatus(200);
        $stats = $response->json('data');

        // Test cache config
        $response = $this->getJson('/api/cache/config', $headers);
        $response->assertStatus(200);
        $config = $response->json('data');

        // Test cache warmup
        $response = $this->postJson('/api/cache/warmup', [
            'keys' => ['dashboard_data', 'user_preferences']
        ], $headers);
        $response->assertStatus(200);

        // Test cache invalidation
        $testKey = 'test_key_' . uniqid();
        Cache::put($testKey, 'test_value', 300);

        $response = $this->postJson('/api/cache/invalidate/key', [
            'key' => $testKey
        ], $headers);
        $response->assertStatus(200);

        $this->assertFalse(Cache::has($testKey));
    }

    /**
     * Test WebSocket workflow
     */
    public function test_websocket_workflow()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ];

        // Test WebSocket info
        $response = $this->getJson('/api/websocket/info', $headers);
        $response->assertStatus(200);
        $info = $response->json('data');

        // Test WebSocket stats
        $response = $this->getJson('/api/websocket/stats', $headers);
        $response->assertStatus(200);
        $stats = $response->json('data');

        // Test connection test
        $response = $this->getJson('/api/websocket/test', $headers);
        $response->assertStatus(200);

        // Test marking user online
        $connectionId = 'test_connection_' . uniqid();
        $response = $this->postJson('/api/websocket/online', [
            'user_id' => $user->id,
            'connection_id' => $connectionId,
            'metadata' => [
                'browser' => 'Chrome',
                'os' => 'macOS'
            ]
        ], $headers);
        $response->assertStatus(200);

        // Test updating activity
        $response = $this->postJson('/api/websocket/activity', [
            'user_id' => $user->id,
            'activity_type' => 'page_view',
            'activity_data' => [
                'page' => '/dashboard',
                'duration' => 30
            ]
        ], $headers);
        $response->assertStatus(200);

        // Test sending notification
        $response = $this->postJson('/api/websocket/notification', [
            'user_id' => $user->id,
            'type' => 'system_message',
            'title' => 'Test Message',
            'message' => 'This is a test message',
            'priority' => 'normal'
        ], $headers);
        $response->assertStatus(200);

        // Test broadcasting
        $response = $this->postJson('/api/websocket/broadcast', [
            'channel' => 'notifications',
            'event' => 'system_notification',
            'data' => [
                'title' => 'System Update',
                'message' => 'System will be updated in 5 minutes'
            ],
            'target_users' => [$user->id]
        ], $headers);
        $response->assertStatus(200);

        // Test marking user offline
        $response = $this->postJson('/api/websocket/offline', [
            'user_id' => $user->id,
            'connection_id' => $connectionId,
            'reason' => 'user_disconnect'
        ], $headers);
        $response->assertStatus(200);
    }

    /**
     * Test multi-tenant isolation
     */
    public function test_multi_tenant_isolation()
    {
        // Create users from different tenants
        $tenant1 = \App\Models\Tenant::factory()->create();
        $tenant2 = \App\Models\Tenant::factory()->create();

        $user1 = \App\Models\User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = \App\Models\User::factory()->create(['tenant_id' => $tenant2->id]);

        $token1 = $user1->createToken('test-token')->plainTextToken;
        $token2 = $user2->createToken('test-token')->plainTextToken;

        $headers1 = [
            'Authorization' => 'Bearer ' . $token1,
            'Accept' => 'application/json',
            'X-Tenant-ID' => $tenant1->id
        ];

        $headers2 = [
            'Authorization' => 'Bearer ' . $token2,
            'Accept' => 'application/json',
            'X-Tenant-ID' => $tenant2->id
        ];

        // Test that users can only access their own data
        $response1 = $this->getJson('/api/auth/me', $headers1);
        $response1->assertStatus(200);
        $this->assertEquals($user1->id, $response1->json('data.id'));

        $response2 = $this->getJson('/api/auth/me', $headers2);
        $response2->assertStatus(200);
        $this->assertEquals($user2->id, $response2->json('data.id'));

        // Test tenant isolation in dashboard
        $response1 = $this->getJson('/api/dashboard/data', $headers1);
        $response1->assertStatus(200);

        $response2 = $this->getJson('/api/dashboard/data', $headers2);
        $response2->assertStatus(200);

        // Data should be different for different tenants
        $this->assertNotEquals($response1->getContent(), $response2->getContent());
    }

    /**
     * Test error handling across all endpoints
     */
    public function test_comprehensive_error_handling()
    {
        // Test authentication errors
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(401);

        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer invalid_token',
            'Accept' => 'application/json'
        ]);
        $response->assertStatus(401);

        // Test rate limiting errors
        $loginData = [
            'email' => 'test@example.com',
            'password' => 'password'
        ];

        // Exhaust rate limit
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', $loginData);
        }

        $response = $this->postJson('/api/auth/login', $loginData);
        $response->assertStatus(429);
        $response->assertJsonStructure([
            'success',
            'error' => [
                'message',
                'code'
            ]
        ]);

        // Test validation errors
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ];

        // Test invalid cache key
        $response = $this->postJson('/api/cache/invalidate/key', [
            'key' => ''
        ], $headers);
        $response->assertStatus(422);

        // Test invalid WebSocket data
        $response = $this->postJson('/api/websocket/online', [
            'user_id' => 'invalid_id',
            'connection_id' => ''
        ], $headers);
        $response->assertStatus(422);
    }

    /**
     * Test performance across all endpoints
     */
    public function test_performance_across_endpoints()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ];

        $endpoints = [
            '/api/auth/me',
            '/api/auth/permissions',
            '/api/dashboard/data',
            '/api/dashboard/analytics',
            '/api/dashboard/notifications',
            '/api/cache/stats',
            '/api/cache/config',
            '/api/websocket/info',
            '/api/websocket/stats',
            '/api/websocket/test'
        ];

        foreach ($endpoints as $endpoint) {
            $startTime = microtime(true);
            $response = $this->getJson($endpoint, $headers);
            $endTime = microtime(true);

            $response->assertStatus(200);
            
            $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            
            // Assert response time is reasonable (less than 500ms for pages, 300ms for APIs)
            $this->assertLessThan(300, $responseTime, "Endpoint {$endpoint} took {$responseTime}ms");
        }
    }

    /**
     * Test security headers across all endpoints
     */
    public function test_security_headers()
    {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ];

        $endpoints = [
            '/api/auth/me',
            '/api/dashboard/data',
            '/api/cache/stats',
            '/api/websocket/info'
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint, $headers);
            $response->assertStatus(200);

            // Check for security headers
            $this->assertTrue($response->headers->has('X-Content-Type-Options'));
            $this->assertTrue($response->headers->has('X-Frame-Options'));
            $this->assertTrue($response->headers->has('X-XSS-Protection'));
            $this->assertTrue($response->headers->has('Referrer-Policy'));

            // Check header values
            $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
            $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
            $this->assertEquals('1; mode=block', $response->headers->get('X-XSS-Protection'));
        }
    }
}
