<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ApiTestConfiguration extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test that all required services are available
     *
     * @group redis
     */
    public function test_required_services_available()
    {
        // Test Redis availability
        try {
            \Illuminate\Support\Facades\Redis::ping();
            $this->assertTrue(true, 'Redis is available');
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Redis dependency unavailable for @group redis tests; configure REDIS_HOST/REDIS_PORT. Error: ' . $e->getMessage()
            );
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
        $middleware = $kernel->getMiddleware();

        // Check that our custom middleware are registered
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
            $routeRegistered = collect($routes)->contains(
                fn ($registeredRoute) => trim($registeredRoute->uri(), '/') === trim($route, '/')
            );

            $this->assertTrue(
                $routeRegistered,
                "Route {$route} is not registered"
            );
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
        $this->assertEquals('redis', config('cache.default'));
        $this->assertEquals('redis', config('queue.default'));
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
     *
     * @group redis
     */
    public function test_redis_configuration()
    {
        try {
            $redis = \Illuminate\Support\Facades\Redis::connection();
            $this->assertTrue($redis->ping() === 'PONG', 'Redis connection is not working');
            
            // Test basic Redis operations
            $redis->set('test_key', 'test_value');
            $value = $redis->get('test_key');
            $this->assertEquals('test_value', $value);
            $redis->del('test_key');
        } catch (\Exception $e) {
            $this->markTestSkipped(
                'Redis dependency unavailable for @group redis tests; configure REDIS_HOST/REDIS_PORT. Error: ' . $e->getMessage()
            );
        }
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
            'data'
        ]);

        // Test authenticated endpoint
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data'
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
            'success',
            'error' => [
                'message',
                'code'
            ]
        ]);

        // Test 404 error
        $response = $this->getJson('/api/v1/nonexistent-endpoint'); // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_NONEXISTENT_ENDPOINT) ssot-allow-hardcode
        $response->assertStatus(404);
    }

    /**
     * Test CORS configuration
     */
    public function test_cors_configuration()
    {
        $response = $this->options('/api/auth/login', [], [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization'
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Methods'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Headers'));
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

        foreach ($requiredHeaders as $header) {
            $this->assertTrue(
                $response->headers->has($header),
                "Security header {$header} is missing"
            );
        }
    }
}
