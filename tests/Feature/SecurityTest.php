<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Tenant;

/**
 * Security Test Suite
 * 
 * Comprehensive security testing for production hardening
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create test user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified' => true
        ]);
    }

    /**
     * Test security headers are present
     */
    public function test_security_headers_are_present()
    {
        $response = $this->get('/api/v1/test');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Strict-Transport-Security');
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Referrer-Policy');
    }

    /**
     * Test rate limiting works
     */
    public function test_rate_limiting_works()
    {
        // Make multiple requests to trigger rate limit
        for ($i = 0; $i < 15; $i++) {
            $response = $this->get('/api/v1/test');
            
            if ($i >= 10) {
                $response->assertStatus(429);
            }
        }
    }

    /**
     * Test SQL injection protection
     */
    public function test_sql_injection_protection()
    {
        $maliciousInput = "'; DROP TABLE users; --";
        
        $response = $this->postJson('/api/v1/test', [
            'search' => $maliciousInput
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'status' => 'error',
            'code' => 'SUSPICIOUS_INPUT'
        ]);
    }

    /**
     * Test XSS protection
     */
    public function test_xss_protection()
    {
        $xssPayload = '<script>alert("XSS")</script>';
        
        $response = $this->postJson('/api/v1/test', [
            'content' => $xssPayload
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'status' => 'error',
            'code' => 'SUSPICIOUS_INPUT'
        ]);
    }

    /**
     * Test file upload security
     */
    public function test_file_upload_security()
    {
        // Test malicious file upload
        $maliciousFile = $this->createMaliciousFile();
        
        $response = $this->postJson('/api/v1/upload', [
            'file' => $maliciousFile
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'status' => 'error'
        ]);
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation()
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id
        ]);

        // Try to access other tenant's user
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/users/{$otherUser->id}");

        $response->assertStatus(403);
    }

    /**
     * Test authentication bypass protection
     */
    public function test_authentication_bypass_protection()
    {
        // Try to access protected route without authentication
        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(401);
    }

    /**
     * Test CSRF protection
     */
    public function test_csrf_protection()
    {
        // Test CSRF protection on state-changing operations
        $response = $this->postJson('/api/v1/users', [
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        // Should require CSRF token or proper authentication
        $response->assertStatus(401);
    }

    /**
     * Test password policy enforcement
     */
    public function test_password_policy_enforcement()
    {
        $weakPasswords = [
            '123456',
            'password',
            'abc123',
            'qwerty'
        ];

        foreach ($weakPasswords as $password) {
            $response = $this->postJson('/api/v1/auth/register', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => $password,
                'password_confirmation' => $password
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['password']);
        }
    }

    /**
     * Test MFA enforcement
     */
    public function test_mfa_enforcement()
    {
        // Enable MFA for user
        $this->user->update(['mfa_enabled' => true]);

        // Try to login without MFA
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'requires_mfa' => true
        ]);
    }

    /**
     * Test session security
     */
    public function test_session_security()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/sessions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'device_name',
                    'ip_address',
                    'is_current',
                    'is_trusted',
                    'last_activity_at'
                ]
            ]
        ]);
    }

    /**
     * Test audit logging
     */
    public function test_audit_logging()
    {
        // Perform an action that should be logged
        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/users/{$this->user->id}", [
                'name' => 'Updated Name'
            ]);

        $response->assertStatus(200);

        // Check audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'update',
            'entity_type' => 'User',
            'entity_id' => $this->user->id
        ]);
    }

    /**
     * Test production security middleware
     */
    public function test_production_security_middleware()
    {
        // Test that SimpleUserController routes are blocked in production
        config(['app.env' => 'production']);
        
        $response = $this->getJson('/api/simple/users');
        
        $response->assertStatus(404);
        $response->assertJson([
            'status' => 'error',
            'code' => 'PRODUCTION_SECURITY_BLOCK'
        ]);
    }

    /**
     * Create malicious file for testing
     */
    private function createMaliciousFile()
    {
        $tempFile = tmpfile();
        fwrite($tempFile, '<?php echo "malicious"; ?>');
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        
        return new \Illuminate\Http\UploadedFile(
            $tempPath,
            'malicious.php',
            'application/x-php',
            null,
            true
        );
    }
}