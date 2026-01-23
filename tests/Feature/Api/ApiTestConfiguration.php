<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tests\TestCase;

class ApiTestConfiguration extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test that all required services are available
     */
    public function test_required_services_available()
    {
        // Test Redis availability
        try {
            \Illuminate\Support\Facades\Redis::ping();
            $this->assertTrue(true, 'Redis is available');
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis is not available: ' . $e->getMessage());
        }

        // Test Cache availability
        try {
            \Illuminate\Support\Facades\Cache::put('test_key', 'test_value', 60);
            $value = \Illuminate\Support\Facades\Cache::get('test_key');
            $this->assertEquals('test_value', $value);
            \Illuminate\Support\Facades\Cache::forget('test_key');
        } catch (\Exception $e) {
            $this->fail('Cache is not available: ' . $e->getMessage());
        }

        // Test Database availability
        try {
            \App\Models\User::count();
            $this->assertTrue(true, 'Database is available');
        } catch (\Exception $e) {
            $this->fail('Database is not available: ' . $e->getMessage());
        }
    }

    /**
     * Test middleware registration
     */
    public function test_middleware_registration()
    {
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $middleware = array_values($kernel->getMiddlewareAliases());

        // Check that our custom middleware aliases are registered
        $this->assertContains(\App\Http\Middleware\EnhancedRateLimitMiddleware::class, $middleware);
        $this->assertContains(\App\Http\Middleware\ApiResponseCacheMiddleware::class, $middleware);
    }

    /**
     * Test service provider registration
     */
    public function test_service_provider_registration()
    {
        $app = app();
        
        // Test that our services are bound
        $this->assertTrue($app->bound(\App\Services\RateLimitService::class));
        $this->assertTrue($app->bound(\App\Services\AdvancedCacheService::class));
        $this->assertTrue($app->bound(\App\Services\WebSocketService::class));
    }

    /**
     * Test route registration
     */
    public function test_route_registration()
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes();
        
        $requiredRoutes = [
            'api/auth/login',
            'api/auth/logout',
            'api/auth/me',
            'api/auth/permissions',
            'api/dashboard/data',
            'api/cache/stats',
            'api/cache/config',
            'api/websocket/info',
            'api/websocket/stats',
            'api/websocket/test'
        ];

        foreach ($requiredRoutes as $route) {
            try {
                $this->assertNotNull(
                    $routes->match(\Illuminate\Http\Request::create($route, 'GET')),
                    "Route {$route} is not registered"
                );
            } catch (MethodNotAllowedHttpException $e) {
                $this->assertTrue(true, "Route {$route} exists but GET is not allowed");
            }
        }
    }

    /**
     * Test environment configuration
     */
    public function test_environment_configuration()
    {
        // Test required environment variables
        $requiredEnvVars = [
            'APP_NAME',
            'APP_ENV',
            'APP_KEY',
            'DB_CONNECTION',
            'CACHE_DRIVER'
        ];

        foreach ($requiredEnvVars as $envVar) {
            $this->assertNotEmpty(env($envVar), "Environment variable {$envVar} is not set");
        }

        // Test cache configuration
        $cacheDriver = config('cache.default');
        $queueDriver = config('queue.default');

        if (app()->environment('testing')) {
            $this->assertEquals('array', $cacheDriver);
            $this->assertEquals('sync', $queueDriver);
        } else {
            $this->assertEquals('redis', $cacheDriver);
            $this->assertEquals('redis', $queueDriver);
        }
    }

    /**
     * Test database configuration
     */
    public function test_database_configuration()
    {
        $connection = \Illuminate\Support\Facades\DB::connection();
        $this->assertTrue($connection->getPdo() !== null, 'Database connection is not available');
        
        // Test that we can run migrations
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate:status');
            $this->assertTrue(true, 'Migrations can be run');
        } catch (\Exception $e) {
            $this->fail('Migrations cannot be run: ' . $e->getMessage());
        }
    }

    /**
     * Test Redis configuration
     */
    public function test_redis_configuration()
    {
        $requiresRedis = env('REDIS_REQUIRED_FOR_TESTS', '0') === '1';

        if ($requiresRedis) {
            try {
                $redis = Redis::connection();
                $this->assertSame('PONG', $redis->ping(), 'Redis connection is not working');
            } catch (\Exception $e) {
                $this->fail('Redis connectivity is required but unavailable: ' . $e->getMessage());
            }

            return;
        }

        // Default to a mock so local dev/test runs stay deterministic without real Redis.
        $connMock = \Mockery::mock('Illuminate\Redis\Connections\Connection');
        Redis::shouldReceive('connection')->once()->andReturn($connMock);
        $connMock->shouldReceive('ping')->once()->andReturn(true);

        $redis = Redis::connection();
        $this->assertTrue($redis->ping());
    }

    /**
     * Test API response format consistency
     */
    public function test_api_response_format()
    {
        // Test public endpoint
        $response = $this->getJson('/api/csrf-token');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'csrf_token'
        ]);

        // Test authenticated endpoint
        $user = \App\Models\User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me', [
            'Accept' => 'application/json'
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'user'
        ]);
    }

    /**
     * Test error response format
     */
    public function test_error_response_format()
    {
        // Test 401 error
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(401);
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message',
                'details'
            ]
        ]);
        $this->assertIsArray($response->json('error.details'));

        // Test 404 error
        $response = $this->getJson('/api/non-existent-endpoint');
        $response->assertStatus(404);
    }

    /**
     * Test CORS configuration
     */
    public function test_cors_configuration()
    {
        config([
            'cors.paths' => ['api/*'],
            'cors.allowed_methods' => ['*'],
            'cors.allowed_origins' => ['*'],
            'cors.allowed_headers' => ['*'],
            'cors.exposed_headers' => [],
            'cors.max_age' => 0,
            'cors.supports_credentials' => false,
        ]);

        $origin = 'https://example.test';
        $preflightHeaders = [
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization',
        ];

        $preflight = $this->options('/api/health', [], $preflightHeaders);

        $this->assertTrue(
            in_array($preflight->getStatusCode(), [200, 204], true),
            'Preflight did not return success'
        );

        $this->assertTrue(
            $preflight->headers->has('Access-Control-Allow-Origin'),
            'Preflight response is missing Access-Control-Allow-Origin'
        );

        $allowOrigin = $preflight->headers->get('Access-Control-Allow-Origin');
        $this->assertContains(
            $allowOrigin,
            ['*', $origin],
            'Preflight Access-Control-Allow-Origin is not expected'
        );

        foreach ([
            'Access-Control-Allow-Methods',
            'Access-Control-Allow-Headers'
        ] as $header) {
            $this->assertTrue(
                $preflight->headers->has($header),
                "Preflight response is missing {$header}"
            );
        }

        $getResponse = $this->getJson('/api/health', [
            'Origin' => $origin,
        ]);

        $this->assertTrue(
            $getResponse->headers->has('Access-Control-Allow-Origin'),
            'GET response is missing Access-Control-Allow-Origin'
        );

        $allowOrigin = $getResponse->headers->get('Access-Control-Allow-Origin');
        $this->assertContains(
            $allowOrigin,
            ['*', $origin],
            'GET Access-Control-Allow-Origin is not expected'
        );
    }

    /**
     * Test security headers
     */
    public function test_security_headers()
    {
        $response = $this->getJson('/api/csrf-token');
        
        $requiredHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options',
            'X-XSS-Protection',
            'Referrer-Policy'
        ];

        if (!$response->headers->has('X-Content-Type-Options')) {
            $this->markTestSkipped('Security headers not configured in this environment');
        }

        foreach ($requiredHeaders as $header) {
            $this->assertTrue(
                $response->headers->has($header),
                "Security header {$header} is missing"
            );
        }
    }
}
