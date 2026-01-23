<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\RbacTestTrait;

class ComprehensiveApiIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker, RbacTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
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
            'csrf_token'
        ]);

        // Test login with rate limiting
        $user = $this->makeTenantUser([
            'email' => 'test@example.com',
            'role' => 'super_admin',
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
            'user' => [
                'id',
                'name',
                'email'
            ],
            'token',
            'expires_at'
        ]);

        // Check rate limiting headers
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'));

        $token = $response->json('token');

        // Test authenticated endpoints
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ];

        // Test user info endpoint
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me', $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'user' => [
                'id',
                'name',
                'email',
                'tenant_id'
            ]
        ]);

        // Test permissions endpoint
        $response = $this->getJson('/api/auth/permissions', $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'permissions',
            'roles'
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
        $authContext = $this->actingAsWithPermissions(['dashboard.view']);
        $headers = $this->authHeaders($authContext);
        Sanctum::actingAs($authContext['user']);

        // Test dashboard data endpoint (should be cached)
        $response1 = $this->getJson('/api/dashboard/data', $headers);
        $response1->assertStatus(200);
        $this->assertSame('MISS', $response1->headers->get('X-Cache-Status'));
        $response1->assertJsonStructure([
            'success',
            'data' => [
                'kpis',
                'alerts',
                'quickActions',
                'notifications',
                'stats',
                'recentActivity',
                'generated_at'
            ]
        ]);
        $cacheKey = $response1->headers->get('X-Cache-Key');
        $cacheTtl = $response1->headers->get('X-Cache-TTL');

        $this->assertSame('MISS', $response1->headers->get('X-Cache'));
        $this->assertSame('300', $cacheTtl);
        $this->assertNotEmpty($cacheKey);

        // Second request should be faster (cached)
        $response2 = $this->getJson('/api/dashboard/data', $headers);
        $response2->assertStatus(200);
        $this->assertSame('HIT', $response2->headers->get('X-Cache-Status'));
        $this->assertSame('HIT', $response2->headers->get('X-Cache'));
        $this->assertSame($cacheKey, $response2->headers->get('X-Cache-Key'));
        $this->assertSame($cacheTtl, $response2->headers->get('X-Cache-TTL'));
        $this->assertTrue($response2->headers->has('X-Cache-Date'));

        // Test dashboard analytics
        $response = $this->getJson('/api/dashboard/analytics', $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'analytics'
            ]
        ]);

        // Test dashboard notifications
        $response = $this->getJson('/api/dashboard/notifications', $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data'
        ]);
        $this->assertIsArray($response->json('data'));

        // Test dashboard preferences
        $response = $this->getJson('/api/dashboard/preferences', $headers);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data'
        ]);
        $this->assertIsArray($response->json('data'));
    }

    /**
     * Test cache management workflow
     */
    public function test_cache_management_workflow()
    {
        $authContext = $this->actingAsWithPermissions([], [
            'attributes' => ['role' => 'super_admin'],
        ]);

        $headers = $this->authHeaders($authContext);
        Sanctum::actingAs($authContext['user']);

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
        $authContext = $this->actingAsWithPermissions([], [
            'attributes' => ['role' => 'super_admin'],
        ]);
        $headers = $this->authHeaders($authContext);
        Sanctum::actingAs($authContext['user']);
        $userId = $authContext['user']->id;

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
            'user_id' => $userId,
            'connection_id' => $connectionId,
            'metadata' => [
                'browser' => 'Chrome',
                'os' => 'macOS'
            ]
        ], $headers);
        $response->assertStatus(200);

        // Test updating activity
        $response = $this->postJson('/api/websocket/activity', [
            'user_id' => $userId,
            'activity' => 'page_view',
            'metadata' => [
                'page' => '/dashboard',
                'duration' => 30
            ]
        ], $headers);
        $response->assertStatus(200);

        // Test sending notification
        $response = $this->postJson('/api/websocket/notification', [
            'user_id' => $userId,
            'notification' => [
                'type' => 'system_message',
                'title' => 'Test Message',
                'message' => 'This is a test message',
                'metadata' => [],
                'priority' => 'normal'
            ]
        ], $headers);
        $response->assertStatus(200);

        // Test broadcasting
        $response = $this->postJson('/api/websocket/broadcast', [
            'channel' => 'notifications',
            'event' => 'new_notification',
            'data' => [
                'title' => 'System Update',
                'message' => 'System will be updated in 5 minutes'
            ],
            'target_users' => [$userId]
        ], $headers);
        $response->assertStatus(200);

        // Test marking user offline
        $response = $this->postJson('/api/websocket/offline', [
            'user_id' => $userId,
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
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $authA = $this->actingAsWithPermissions(['project.create', 'project.read'], [
            'attributes' => ['tenant_id' => (string) $tenantA->id],
        ]);
        $authB = $this->actingAsWithPermissions(['project.read'], [
            'attributes' => ['tenant_id' => (string) $tenantB->id],
        ]);


        [$startDate, $endDate] = [
            now()->toDateString(),
            now()->addMonth()->toDateString(),
        ];

        $createPayload = [
            'name' => 'Tenant A Project',
            'description' => 'Integration test project',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        $createResponse = $this->postJson('/api/projects', $createPayload, [
            'Authorization' => 'Bearer ' . $authA['sanctum_token'],
            'Accept' => 'application/json',
        ]);
        $createResponse->assertStatus(201);
        $projectId = $createResponse->json('data.id');

        DB::table('project_team_members')->insert([
            'project_id' => $projectId,
            'user_id' => $authA['user']->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        $headersA = $this->authHeaders($authA);
        $headersB = $this->authHeaders($authB);

        $this->withRbacEnforced(function () use ($headersA, $headersB, $projectId) {
            $responseAllowed = $this->getJson("/api/_test/tenant-projects/{$projectId}", $headersA);
            $responseAllowed->assertStatus(200);
            $this->assertEquals('success', $responseAllowed->json('status'));

            $responseForbidden = $this->getJson("/api/_test/tenant-projects/{$projectId}", $headersB);
            $responseForbidden->assertStatus(403);
            $this->assertEquals('error', $responseForbidden->json('status'));
        });
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
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message',
                'details'
            ]
        ]);
        $this->assertIsArray($response->json('error.details'));

        // Test validation errors
        $authContext = $this->actingAsWithPermissions([], [
            'attributes' => ['role' => 'super_admin'],
        ]);

        $headers = $this->authHeaders($authContext);

        // Test invalid cache key
        $response = $this->postJson('/api/cache/invalidate/key', [
            'key' => ''
        ], $headers);
        $response->assertStatus(422);

        // Test invalid WebSocket data
        $response = $this->postJson('/api/websocket/online', [
            'user_id' => ''
        ], $headers);
        $response->assertStatus(422);
    }

    /**
     * Test performance across all endpoints
     */
    public function test_performance_across_endpoints()
    {
        if (!filter_var(env('PERF_ASSERTIONS', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('PERF_ASSERTIONS disabled');
        }

        $authContext = $this->actingAsWithPermissions([], [
            'attributes' => ['role' => 'super_admin'],
        ]);

        $headers = $this->authHeaders($authContext);
        Sanctum::actingAs($authContext['user']);

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
        $authContext = $this->actingAsWithPermissions([], [
            'attributes' => ['role' => 'super_admin'],
        ]);

        $headers = $this->authHeaders($authContext);
        Sanctum::actingAs($authContext['user']);

        $allowedOrigins = config('cors.allowed_origins', ['*']);
        $originList = is_array($allowedOrigins) ? $allowedOrigins : [$allowedOrigins];
        $endpoints = [
            '/api/health',
            '/api/v1/health',
            '/api/zena/health',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint, $headers);
            $response->assertStatus(200);

            $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
            $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
            $this->assertEquals('1; mode=block', $response->headers->get('X-XSS-Protection'));
            $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
            $this->assertEquals('geolocation=(), microphone=(), camera=()', $response->headers->get('Permissions-Policy'));
            $this->assertContains($response->headers->get('Access-Control-Allow-Origin'), $originList);
            $this->assertEquals('GET, POST, PUT, PATCH, DELETE, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
            $this->assertEquals('Content-Type, Authorization, X-CSRF-TOKEN, X-Requested-With', $response->headers->get('Access-Control-Allow-Headers'));
            $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));
            $this->assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
        }
    }

    private function authHeaders(array $context, array $overrides = []): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $context['sanctum_token'],
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $context['user']->tenant_id,
        ];

        return array_merge($headers, $overrides);
    }

    private function withRbacEnforced(callable $callback): void
    {
        $originalEnv = getenv('RBAC_BYPASS_TESTING');
        $originalConfig = config('rbac.bypass_testing');

        config(['rbac.bypass_testing' => false]);
        putenv('RBAC_BYPASS_TESTING=0');
        $_ENV['RBAC_BYPASS_TESTING'] = '0';
        $_SERVER['RBAC_BYPASS_TESTING'] = '0';

        try {
            $callback();
        } finally {
            config(['rbac.bypass_testing' => $originalConfig]);

            if ($originalEnv === false) {
                putenv('RBAC_BYPASS_TESTING');
                unset($_ENV['RBAC_BYPASS_TESTING'], $_SERVER['RBAC_BYPASS_TESTING']);
                return;
            }

            putenv("RBAC_BYPASS_TESTING={$originalEnv}");
            $_ENV['RBAC_BYPASS_TESTING'] = $originalEnv;
            $_SERVER['RBAC_BYPASS_TESTING'] = $originalEnv;
        }
    }
}
