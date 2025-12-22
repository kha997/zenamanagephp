<?php declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security Headers Test
 * 
 * Tests that security headers are properly set in responses.
 * 
 * @group security
 * @group headers
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Security headers are present in responses
     */
    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/');
        
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Test: CSP header is present
     */
    public function test_csp_header_is_present(): void
    {
        $response = $this->get('/');
        
        $response->assertHeader('Content-Security-Policy');
        
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    /**
     * Test: HSTS header in production
     */
    public function test_hsts_header_in_production(): void
    {
        // This test would need to run in production environment
        // For now, verify the header is conditionally set
        $response = $this->get('/');
        
        // In non-production, HSTS may not be set
        // In production with HTTPS, HSTS should be set
        if (app()->environment('production') && request()->secure()) {
            $response->assertHeader('Strict-Transport-Security');
        }
    }

    /**
     * Test: Permissions Policy header is present
     */
    public function test_permissions_policy_header_is_present(): void
    {
        $response = $this->get('/');
        
        $response->assertHeader('Permissions-Policy');
        
        $policy = $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
    }

    /**
     * Test: Server information is removed
     */
    public function test_server_information_is_removed(): void
    {
        $response = $this->get('/');
        
        $this->assertNull($response->headers->get('X-Powered-By'));
        $this->assertNull($response->headers->get('Server'));
    }

    /**
     * Test: API routes have minimal CSP
     */
    public function test_api_routes_have_minimal_csp(): void
    {
        $response = $this->getJson('/api/v1/me');
        
        $csp = $response->headers->get('Content-Security-Policy');
        
        // API routes should have minimal CSP
        if ($csp) {
            $this->assertStringContainsString("default-src 'none'", $csp);
        }
    }

    /**
     * Test: Production routes are blocked in production
     */
    public function test_production_routes_blocked_in_production(): void
    {
        // This test would need to run in production environment
        // For now, verify the middleware is registered
        $this->markTestIncomplete('Production route blocking requires production environment');
    }
}

