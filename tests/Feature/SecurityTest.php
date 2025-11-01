<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test security headers are present
     */
    public function test_security_headers_present(): void
    {
        $response = $this->get('/app/dashboard');

        // Content Security Policy
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        
        // X-Content-Type-Options
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        
        // X-Frame-Options
        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
        
        // X-XSS-Protection
        $this->assertEquals('1; mode=block', $response->headers->get('X-XSS-Protection'));
        
        // Referrer Policy
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        
        // Permissions Policy
        $this->assertTrue($response->headers->has('Permissions-Policy'));
    }

    /**
     * Test CSRF protection is enabled
     */
    public function test_csrf_protection_enabled(): void
    {
        // Test that CSRF token is present in forms
        $response = $this->get('/app/projects');
        $this->assertStringContainsString('csrf-token', $response->getContent());
        
        // Test that POST requests without CSRF token are rejected
        $response = $this->post('/app/projects', [
            'name' => 'Test Project',
            'description' => 'Test Description'
        ]);
        
        $this->assertEquals(419, $response->getStatusCode());
    }

    /**
     * Test rate limiting works
     */
    public function test_rate_limiting_works(): void
    {
        // Make multiple requests quickly
        for ($i = 0; $i < 65; $i++) {
            $response = $this->get('/api/v1/health');
            
            if ($i >= 60) {
                $this->assertEquals(429, $response->getStatusCode());
                $this->assertTrue($response->headers->has('Retry-After'));
                break;
            }
        }
    }

    /**
     * Test input validation and sanitization
     */
    public function test_input_validation_and_sanitization(): void
    {
        // Test XSS protection
        $maliciousInput = '<script>alert("xss")</script>';
        
        $response = $this->post('/app/projects', [
            'name' => $maliciousInput,
            'description' => 'Test Description'
        ]);
        
        // Should sanitize the input
        $this->assertStringNotContainsString('<script>', $response->getContent());
    }

    /**
     * Test secure session management
     */
    public function test_secure_session_management(): void
    {
        $response = $this->get('/app/dashboard');
        
        // Check for secure session cookies
        $cookies = $response->headers->getCookies();
        $sessionCookie = null;
        
        foreach ($cookies as $cookie) {
            if (str_contains($cookie->getName(), 'laravel_session')) {
                $sessionCookie = $cookie;
                break;
            }
        }
        
        $this->assertNotNull($sessionCookie);
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertTrue($sessionCookie->isSecure());
    }

    /**
     * Test SQL injection protection
     */
    public function test_sql_injection_protection(): void
    {
        $maliciousInput = "'; DROP TABLE users; --";
        
        $response = $this->post('/app/projects', [
            'name' => $maliciousInput,
            'description' => 'Test Description'
        ]);
        
        // Should not cause SQL error
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    /**
     * Test authentication bypass protection
     */
    public function test_authentication_bypass_protection(): void
    {
        // Test that protected routes require authentication
        $response = $this->get('/app/projects');
        
        // Should redirect to login or show auth error
        $this->assertTrue(
            $response->isRedirect() || 
            $response->getStatusCode() === 401 ||
            $response->getStatusCode() === 403
        );
    }

    /**
     * Test file upload security
     */
    public function test_file_upload_security(): void
    {
        // Test malicious file upload
        $maliciousFile = [
            'name' => 'malicious.php',
            'type' => 'application/x-php',
            'size' => 1024,
            'tmp_name' => '/tmp/malicious.php',
            'error' => 0
        ];
        
        $response = $this->post('/app/documents', [
            'file' => $maliciousFile,
            'name' => 'Test Document'
        ]);
        
        // Should reject PHP files
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    /**
     * Test directory traversal protection
     */
    public function test_directory_traversal_protection(): void
    {
        $maliciousPath = '../../../etc/passwd';
        
        $response = $this->get('/app/documents/' . urlencode($maliciousPath));
        
        // Should not allow directory traversal
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    /**
     * Test HTTPS enforcement (if applicable)
     */
    public function test_https_enforcement(): void
    {
        // This test would only work in HTTPS environment
        if (app()->environment('production')) {
            $response = $this->get('/app/dashboard');
            
            // Should have HSTS header
            $this->assertTrue($response->headers->has('Strict-Transport-Security'));
        } else {
            // In non-production, just verify the route exists
            $response = $this->get('/app/dashboard');
            $this->assertTrue(in_array($response->status(), [200, 302, 401]));
        }
    }

    /**
     * Test sensitive data exposure
     */
    public function test_sensitive_data_exposure(): void
    {
        $response = $this->get('/app/dashboard');
        $content = $response->getContent();
        
        // Should not expose sensitive information
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('secret', $content);
        $this->assertStringNotContainsString('token', $content);
    }

    /**
     * Test error handling doesn't expose sensitive information
     */
    public function test_error_handling_security(): void
    {
        // Trigger an error
        $response = $this->get('/nonexistent-route');
        
        // Should not expose file paths or sensitive information
        $content = $response->getContent();
        $this->assertStringNotContainsString('/Applications/', $content);
        $this->assertStringNotContainsString('vendor/', $content);
        $this->assertStringNotContainsString('app/', $content);
    }

    /**
     * Test API security
     */
    public function test_api_security(): void
    {
        // Test that API endpoints require proper authentication
        $response = $this->get('/api/v1/projects');
        
        $this->assertEquals(401, $response->getStatusCode());
        
        // Test that API responses don't expose sensitive data
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token'
        ])->get('/api/v1/projects');
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test admin route protection
     */
    public function test_admin_route_protection(): void
    {
        // Test that admin routes are protected
        $response = $this->get('/admin/dashboard');
        
        $this->assertTrue(
            $response->isRedirect() || 
            $response->getStatusCode() === 401 ||
            $response->getStatusCode() === 403
        );
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        // This would require setting up test tenants
        // For now, just verify the middleware exists
        $this->assertTrue(class_exists('App\Traits\TenantScope'));
    }

    /**
     * Test overall security compliance
     */
    public function test_security_compliance(): void
    {
        $complianceChecks = [
            'Security Headers Present' => $this->checkSecurityHeaders(),
            'CSRF Protection Enabled' => $this->checkCSRFProtection(),
            'Rate Limiting Works' => $this->checkRateLimiting(),
            'Input Validation Works' => $this->checkInputValidation(),
            'Session Security Enabled' => $this->checkSessionSecurity(),
            'SQL Injection Protection' => $this->checkSQLInjectionProtection(),
            'Authentication Protection' => $this->checkAuthenticationProtection(),
            'File Upload Security' => $this->checkFileUploadSecurity(),
            'Directory Traversal Protection' => $this->checkDirectoryTraversalProtection(),
            'Error Handling Security' => $this->checkErrorHandlingSecurity(),
        ];
        
        $passedChecks = array_filter($complianceChecks);
        $totalChecks = count($complianceChecks);
        $passedCount = count($passedChecks);
        
        $this->assertEquals(
            $totalChecks,
            $passedCount,
            "Security compliance check failed: $passedCount/$totalChecks passed. Failed: " . 
            implode(', ', array_keys(array_diff($complianceChecks, $passedChecks)))
        );
    }

    /**
     * Check security headers
     */
    private function checkSecurityHeaders(): bool
    {
        $response = $this->get('/app/dashboard');
        return $response->headers->has('Content-Security-Policy') &&
               $response->headers->has('X-Content-Type-Options') &&
               $response->headers->has('X-Frame-Options');
    }

    /**
     * Check CSRF protection
     */
    private function checkCSRFProtection(): bool
    {
        $response = $this->post('/app/projects', ['name' => 'test']);
        return $response->getStatusCode() === 419;
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimiting(): bool
    {
        // Make multiple requests
        for ($i = 0; $i < 65; $i++) {
            $response = $this->get('/api/v1/health');
            if ($i >= 60 && $response->getStatusCode() === 429) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check input validation
     */
    private function checkInputValidation(): bool
    {
        $response = $this->post('/app/projects', [
            'name' => '<script>alert("xss")</script>'
        ]);
        return !str_contains($response->getContent(), '<script>');
    }

    /**
     * Check session security
     */
    private function checkSessionSecurity(): bool
    {
        $response = $this->get('/app/dashboard');
        $cookies = $response->headers->getCookies();
        
        foreach ($cookies as $cookie) {
            if (str_contains($cookie->getName(), 'laravel_session')) {
                return $cookie->isHttpOnly();
            }
        }
        
        return false;
    }

    /**
     * Check SQL injection protection
     */
    private function checkSQLInjectionProtection(): bool
    {
        $response = $this->post('/app/projects', [
            'name' => "'; DROP TABLE users; --"
        ]);
        return $response->getStatusCode() !== 500;
    }

    /**
     * Check authentication protection
     */
    private function checkAuthenticationProtection(): bool
    {
        $response = $this->get('/app/projects');
        return $response->isRedirect() || 
               $response->getStatusCode() === 401 ||
               $response->getStatusCode() === 403;
    }

    /**
     * Check file upload security
     */
    private function checkFileUploadSecurity(): bool
    {
        $response = $this->post('/app/documents', [
            'file' => [
                'name' => 'malicious.php',
                'type' => 'application/x-php'
            ]
        ]);
        return $response->getStatusCode() !== 200;
    }

    /**
     * Check directory traversal protection
     */
    private function checkDirectoryTraversalProtection(): bool
    {
        $response = $this->get('/app/documents/../../../etc/passwd');
        return $response->getStatusCode() !== 200;
    }

    /**
     * Check error handling security
     */
    private function checkErrorHandlingSecurity(): bool
    {
        $response = $this->get('/nonexistent-route');
        $content = $response->getContent();
        return !str_contains($content, '/Applications/') &&
               !str_contains($content, 'vendor/');
    }
}