<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use App\Services\SecurityHeadersService;
use App\Services\ComprehensiveLoggingService;
use App\Http\Controllers\SecurityController;
use App\Http\Middleware\EnhancedSecurityHeadersMiddleware;

/**
 * Comprehensive Security Headers Test Suite
 * 
 * Tests all aspects of the security headers system including:
 * - Security headers generation
 * - CSP policies
 * - Environment-specific configurations
 * - Security monitoring and reporting
 */
class SecurityHeadersSystemTest extends TestCase
{
    use RefreshDatabase;
    
    private SecurityHeadersService $securityHeadersService;
    private ComprehensiveLoggingService $loggingService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->securityHeadersService = app(SecurityHeadersService::class);
        $this->loggingService = app(ComprehensiveLoggingService::class);
    }
    
    /**
     * Test basic security headers generation
     */
    public function test_basic_security_headers_generation(): void
    {
        $request = Request::create('/test', 'GET');
        
        $headers = $this->securityHeadersService->generateSecurityHeaders($request);
        
        $this->assertIsArray($headers);
        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertArrayHasKey('X-XSS-Protection', $headers);
        $this->assertArrayHasKey('Referrer-Policy', $headers);
        $this->assertArrayHasKey('Permissions-Policy', $headers);
    }
    
    /**
     * Test CSP generation for different environments
     */
    public function test_csp_generation_different_environments(): void
    {
        $request = Request::create('/test', 'GET');
        
        // Test local environment
        app()->instance('env', 'local');
        $localHeaders = $this->securityHeadersService->generateSecurityHeaders($request);
        
        $this->assertStringContainsString('unsafe-inline', $localHeaders['Content-Security-Policy']);
        $this->assertStringContainsString('unsafe-eval', $localHeaders['Content-Security-Policy']);
        
        // Test production environment
        app()->instance('env', 'production');
        $prodHeaders = $this->securityHeadersService->generateSecurityHeaders($request);
        
        $this->assertStringNotContainsString('unsafe-eval', $prodHeaders['Content-Security-Policy']);
    }
    
    /**
     * Test security headers for sensitive pages
     */
    public function test_security_headers_sensitive_pages(): void
    {
        $sensitivePaths = [
            '/admin/dashboard',
            '/app/profile',
            '/app/settings',
            '/login',
            '/register',
        ];
        
        foreach ($sensitivePaths as $path) {
            $request = Request::create($path, 'GET');
            $headers = $this->securityHeadersService->generateSecurityHeaders($request);
            
            $this->assertArrayHasKey('Cache-Control', $headers);
            $this->assertStringContainsString('no-store', $headers['Cache-Control']);
            $this->assertStringContainsString('no-cache', $headers['Cache-Control']);
        }
    }
    
    /**
     * Test security headers for public pages
     */
    public function test_security_headers_public_pages(): void
    {
        $publicPaths = [
            '/',
            '/about',
            '/contact',
            '/public/info',
        ];
        
        foreach ($publicPaths as $path) {
            $request = Request::create($path, 'GET');
            $headers = $this->securityHeadersService->generateSecurityHeaders($request);
            
            $this->assertArrayHasKey('Cache-Control', $headers);
            $this->assertStringContainsString('public', $headers['Cache-Control']);
        }
    }
    
    /**
     * Test HTTPS-specific headers
     */
    public function test_https_specific_headers(): void
    {
        $request = Request::create('/test', 'GET');
        $request->server->set('HTTPS', 'on');
        $request->server->set('SERVER_PORT', '443');
        
        $headers = $this->securityHeadersService->generateSecurityHeaders($request);
        
        $this->assertArrayHasKey('Strict-Transport-Security', $headers);
        $this->assertStringContainsString('max-age', $headers['Strict-Transport-Security']);
    }
    
    /**
     * Test permissions policy for different page types
     */
    public function test_permissions_policy_different_pages(): void
    {
        // Test admin pages
        $adminRequest = Request::create('/admin/dashboard', 'GET');
        $adminHeaders = $this->securityHeadersService->generateSecurityHeaders($adminRequest);
        
        $this->assertStringContainsString('fullscreen=(self)', $adminHeaders['Permissions-Policy']);
        $this->assertStringContainsString('notifications=(self)', $adminHeaders['Permissions-Policy']);
        
        // Test regular pages
        $regularRequest = Request::create('/dashboard', 'GET');
        $regularHeaders = $this->securityHeadersService->generateSecurityHeaders($regularRequest);
        
        $this->assertStringContainsString('fullscreen=()', $regularHeaders['Permissions-Policy']);
        $this->assertStringContainsString('notifications=()', $regularHeaders['Permissions-Policy']);
    }
    
    /**
     * Test enhanced security headers middleware
     */
    public function test_enhanced_security_headers_middleware(): void
    {
        $middleware = app(EnhancedSecurityHeadersMiddleware::class);
        
        $request = Request::create('/test', 'GET');
        
        $response = $middleware->handle($request, function ($req) {
            return response('OK');
        });
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertTrue($response->headers->has('X-Frame-Options'));
        $this->assertTrue($response->headers->has('X-XSS-Protection'));
        $this->assertTrue($response->headers->has('Referrer-Policy'));
        $this->assertTrue($response->headers->has('Permissions-Policy'));
    }
    
    /**
     * Test security controller endpoints
     */
    public function test_security_controller_endpoints(): void
    {
        // Test security config endpoint
        $response = $this->get('/_security/config');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'config' => [
                'environment',
                'https_enabled',
                'csp_enabled',
                'hsts_enabled',
                'frame_options',
                'referrer_policy',
                'permissions_policy_enabled',
                'coep_enabled',
                'coop_enabled',
                'corp_enabled',
            ]
        ]);
        
        // Test security headers test endpoint
        $response = $this->get('/_security/test-headers');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'headers',
            'test_info' => [
                'url',
                'method',
                'environment',
                'https',
                'user_agent',
                'ip',
            ]
        ]);
        
        // Test security headers validation endpoint
        $response = $this->get('/_security/validate-headers');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'validation' => [
                'csp_present',
                'frame_options_present',
                'content_type_options_present',
                'xss_protection_present',
                'referrer_policy_present',
                'permissions_policy_present',
                'hsts_present',
                'coep_present',
                'coop_present',
                'corp_present',
                'score',
                'total',
                'percentage',
            ],
            'headers'
        ]);
    }
    
    /**
     * Test CSP violation reporting
     */
    public function test_csp_violation_reporting(): void
    {
        $violationData = [
            'csp-report' => [
                'document-uri' => 'https://example.com/page',
                'referrer' => 'https://example.com/',
                'violated-directive' => 'script-src',
                'effective-directive' => 'script-src',
                'original-policy' => "script-src 'self'",
                'disposition' => 'enforce',
                'blocked-uri' => 'https://evil.com/script.js',
                'line-number' => 1,
                'column-number' => 1,
                'source-file' => 'https://example.com/page',
            ]
        ];
        
        $response = $this->post('/_security/csp-report', $violationData);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'CSP violation reported successfully'
        ]);
    }
    
    /**
     * Test security headers configuration
     */
    public function test_security_headers_configuration(): void
    {
        $config = $this->securityHeadersService->getSecurityConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('environment', $config);
        $this->assertArrayHasKey('https_enabled', $config);
        $this->assertArrayHasKey('csp_enabled', $config);
        $this->assertArrayHasKey('hsts_enabled', $config);
        $this->assertArrayHasKey('frame_options', $config);
        $this->assertArrayHasKey('referrer_policy', $config);
        $this->assertArrayHasKey('permissions_policy_enabled', $config);
        $this->assertArrayHasKey('coep_enabled', $config);
        $this->assertArrayHasKey('coop_enabled', $config);
        $this->assertArrayHasKey('corp_enabled', $config);
    }
    
    /**
     * Test security headers for API requests
     */
    public function test_security_headers_api_requests(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Accept', 'application/json');
        
        $headers = $this->securityHeadersService->generateSecurityHeaders($request);
        
        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
        $this->assertEquals('nosniff', $headers['X-Content-Type-Options']);
    }
    
    /**
     * Test security headers for admin requests
     */
    public function test_security_headers_admin_requests(): void
    {
        $request = Request::create('/admin/dashboard', 'GET');
        
        $headers = $this->securityHeadersService->generateSecurityHeaders($request);
        
        $this->assertArrayHasKey('Permissions-Policy', $headers);
        $this->assertStringContainsString('fullscreen=(self)', $headers['Permissions-Policy']);
        $this->assertStringContainsString('notifications=(self)', $headers['Permissions-Policy']);
    }
    
    /**
     * Test security headers for embed requests
     */
    public function test_security_headers_embed_requests(): void
    {
        $request = Request::create('/embed/widget', 'GET');
        
        $headers = $this->securityHeadersService->generateSecurityHeaders($request);
        
        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertEquals('SAMEORIGIN', $headers['X-Frame-Options']);
        
        $this->assertArrayHasKey('Cross-Origin-Embedder-Policy', $headers);
        $this->assertEquals('credentialless', $headers['Cross-Origin-Embedder-Policy']);
    }
    
    /**
     * Test security headers documentation endpoint
     */
    public function test_security_documentation_endpoint(): void
    {
        $response = $this->get('/_security/docs');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'title',
            'description',
            'endpoints',
            'headers'
        ]);
    }
}
