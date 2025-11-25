<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * CSRF and CORS Security Tests
 * 
 * Tests CSRF protection and CORS configuration to ensure security.
 */
class CsrfCorsSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /**
     * Test that CSRF token is required for POST requests
     */
    public function test_csrf_token_required_for_post_requests(): void
    {
        $this->actingAs($this->user);

        // Make POST request without CSRF token
        $response = $this->post('/admin/dashboard', [
            'test' => 'data',
        ]);

        // Should return 419 (CSRF token mismatch)
        $response->assertStatus(419);
    }

    /**
     * Test that CSRF token is required for PUT requests
     */
    public function test_csrf_token_required_for_put_requests(): void
    {
        $this->actingAs($this->user);

        $response = $this->put('/admin/dashboard/1', [
            'test' => 'data',
        ]);

        $response->assertStatus(419);
    }

    /**
     * Test that CSRF token is required for DELETE requests
     */
    public function test_csrf_token_required_for_delete_requests(): void
    {
        $this->actingAs($this->user);

        $response = $this->delete('/admin/dashboard/1');

        $response->assertStatus(419);
    }

    /**
     * Test that API routes are excluded from CSRF verification
     */
    public function test_api_routes_excluded_from_csrf(): void
    {
        $this->actingAs($this->user, 'sanctum');

        // API routes should not require CSRF token
        $response = $this->postJson('/api/v1/app/projects', [
            'name' => 'Test Project',
            'code' => 'TEST001',
        ]);

        // Should not return 419 (CSRF error)
        $this->assertNotEquals(419, $response->status());
    }

    /**
     * Test that allowed origins can access API
     */
    public function test_allowed_origins_can_access_api(): void
    {
        $allowedOrigin = 'https://app.zenamanage.com';

        $response = $this->withHeaders([
            'Origin' => $allowedOrigin,
        ])->getJson('/api/v1/app/projects');

        // Should include CORS headers
        $response->assertHeader('Access-Control-Allow-Origin', $allowedOrigin);
    }

    /**
     * Test that unauthorized origins are blocked
     */
    public function test_unauthorized_origins_are_blocked(): void
    {
        $unauthorizedOrigin = 'https://evil.com';

        $response = $this->withHeaders([
            'Origin' => $unauthorizedOrigin,
        ])->options('/api/v1/app/projects');

        // Should not include CORS headers for unauthorized origin
        // Laravel's HandleCors middleware may return 403 or not set the header
        $this->assertNotEquals($unauthorizedOrigin, $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * Test that CORS preflight requests work for allowed origins
     */
    public function test_cors_preflight_requests_work(): void
    {
        $allowedOrigin = 'https://app.zenamanage.com';

        $response = $this->withHeaders([
            'Origin' => $allowedOrigin,
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization',
        ])->options('/api/v1/app/projects');

        $response->assertStatus(200);
        $response->assertHeader('Access-Control-Allow-Origin', $allowedOrigin);
        $response->assertHeader('Access-Control-Allow-Methods');
    }

    /**
     * Test that session cookies have secure flags in production
     */
    public function test_session_cookies_secure_in_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('session.secure', true);
        Config::set('session.same_site', 'strict');

        $this->actingAs($this->user);

        $response = $this->get('/admin/dashboard');

        // In production, session cookie should have Secure and SameSite=Strict
        $cookies = $response->headers->getCookies();
        $sessionCookie = collect($cookies)->firstWhere('getName', config('session.cookie'));

        if ($sessionCookie) {
            $this->assertTrue($sessionCookie->isSecure(), 'Session cookie should be secure in production');
            $this->assertEquals('strict', $sessionCookie->getSameSite(), 'Session cookie should have SameSite=Strict in production');
        }
    }

    /**
     * Test that session cookies are HTTP-only
     */
    public function test_session_cookies_are_http_only(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/admin/dashboard');

        $cookies = $response->headers->getCookies();
        $sessionCookie = collect($cookies)->firstWhere('getName', config('session.cookie'));

        if ($sessionCookie) {
            $this->assertTrue($sessionCookie->isHttpOnly(), 'Session cookie should be HTTP-only');
        }
    }

    /**
     * Test that CORS supports credentials
     */
    public function test_cors_supports_credentials(): void
    {
        $allowedOrigin = 'https://app.zenamanage.com';

        $response = $this->withHeaders([
            'Origin' => $allowedOrigin,
        ])->getJson('/api/v1/app/projects');

        // Should include Access-Control-Allow-Credentials header
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    /**
     * Test that CORS exposes required headers
     */
    public function test_cors_exposes_required_headers(): void
    {
        $allowedOrigin = 'https://app.zenamanage.com';

        $response = $this->withHeaders([
            'Origin' => $allowedOrigin,
        ])->getJson('/api/v1/app/projects');

        // Should expose X-Request-Id and other required headers
        $exposedHeaders = $response->headers->get('Access-Control-Expose-Headers');
        $this->assertStringContainsString('X-Request-Id', $exposedHeaders ?? '');
    }
}

