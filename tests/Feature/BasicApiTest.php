<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Basic API tests to check if routes are working
 */
class BasicApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test health check endpoint
     */
    public function test_health_check(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'service',
                        'version',
                        'timestamp',
                        'environment',
                        'laravel_version',
                        'database',
                        'services'
                    ]
                ]);
    }

    /**
     * Test API info endpoint
     */
    public function test_api_info(): void
    {
        $response = $this->getJson('/api/info');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'name',
                        'version',
                        'description',
                        'features'
                    ]
                ]);
    }

    /**
     * Test that protected routes return 401 without auth
     */
    public function test_protected_routes_require_auth(): void
    {
        $response = $this->getJson('/api/user');
        $response->assertStatus(401);

        $response = $this->getJson('/api/projects');
        $response->assertStatus(401);

        $response = $this->getJson('/api/tasks');
        $response->assertStatus(401);
    }

    /**
     * Test auth routes are accessible
     */
    public function test_auth_routes_accessible(): void
    {
        // Test login endpoint exists (should return validation error, not 404)
        $response = $this->postJson('/api/auth/login', []);
        $response->assertStatus(422); // Validation error, not 404

        // Test register endpoint exists
        $response = $this->postJson('/api/auth/register', []);
        $response->assertStatus(422); // Validation error, not 404
    }
}
