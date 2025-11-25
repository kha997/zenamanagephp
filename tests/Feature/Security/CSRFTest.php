<?php declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * CSRF Protection Tests
 * 
 * PR: Security drill
 * 
 * Tests that CSRF protection is properly enforced for web routes.
 */
class CSRFTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_csrf_protection_on_post_requests(): void
    {
        // Login user
        $this->actingAs($this->user);

        // Try to POST without CSRF token
        $response = $this->post('/admin/settings', [
            'name' => 'Test',
        ]);

        // Should be blocked by CSRF middleware
        $this->assertEquals(419, $response->getStatusCode());
    }

    public function test_csrf_token_in_session(): void
    {
        // Login user
        $this->actingAs($this->user);

        // Get CSRF token from session
        $response = $this->get('/admin/settings');
        
        // Should have CSRF token in response (for Blade forms)
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_api_routes_not_protected_by_csrf(): void
    {
        // Create API token
        $token = $this->user->createToken('test-token');
        $tokenString = $token->plainTextToken;

        // API routes should not require CSRF token
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenString)
            ->postJson('/api/v1/app/projects', [
                'name' => 'Test Project',
            ]);

        // Should work without CSRF token (API uses token auth)
        $this->assertNotEquals(419, $response->getStatusCode());
    }

    public function test_csrf_protection_on_put_requests(): void
    {
        // Login user
        $this->actingAs($this->user);

        // Try to PUT without CSRF token
        $response = $this->put('/admin/settings/1', [
            'name' => 'Updated',
        ]);

        // Should be blocked by CSRF middleware
        $this->assertEquals(419, $response->getStatusCode());
    }

    public function test_csrf_protection_on_delete_requests(): void
    {
        // Login user
        $this->actingAs($this->user);

        // Try to DELETE without CSRF token
        $response = $this->delete('/admin/settings/1');

        // Should be blocked by CSRF middleware
        $this->assertEquals(419, $response->getStatusCode());
    }
}

