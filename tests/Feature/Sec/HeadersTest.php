<?php

namespace Tests\Feature\Sec;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HeadersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function app_pages_have_security_headers(): void
    {
        $response = $this->get('/app/dashboard');
        
        // Should redirect to login (302) since we're not authenticated
        $response->assertStatus(302);
        
        // Check security headers on the redirect response
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $this->assertNotEmpty($response->headers->get('X-Content-Type-Options'));
        $this->assertNotEmpty($response->headers->get('X-Frame-Options'));
        $this->assertNotEmpty($response->headers->get('X-XSS-Protection'));
    }

    /** @test */
    public function login_page_has_security_headers(): void
    {
        $response = $this->get('/login');
        
        $response->assertOk();
        
        // Check security headers
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $this->assertNotEmpty($response->headers->get('X-Content-Type-Options'));
        $this->assertNotEmpty($response->headers->get('X-Frame-Options'));
        $this->assertNotEmpty($response->headers->get('X-XSS-Protection'));
    }

    /** @test */
    public function api_endpoints_have_security_headers(): void
    {
        // Test a simple API endpoint (if it exists)
        $response = $this->get('/api/v1/public/health');
        
        // Should have security headers regardless of response code
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $this->assertNotEmpty($response->headers->get('X-Content-Type-Options'));
        $this->assertNotEmpty($response->headers->get('X-Frame-Options'));
        $this->assertNotEmpty($response->headers->get('X-XSS-Protection'));
    }

    /** @test */
    public function debug_routes_have_security_headers(): void
    {
        $response = $this->get('/_debug/test-simple');
        
        $response->assertOk();
        
        // Check security headers
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $this->assertNotEmpty($response->headers->get('X-Content-Type-Options'));
        $this->assertNotEmpty($response->headers->get('X-Frame-Options'));
        $this->assertNotEmpty($response->headers->get('X-XSS-Protection'));
    }
}
