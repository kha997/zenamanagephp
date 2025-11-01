<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Security Penetration Test Suite
 * 
 * Comprehensive security testing for penetration testing
 */
class SecurityPenetrationTest extends TestCase
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
            'email_verified' => true,
            'password' => Hash::make('TestPassword123!')
        ]);
        
        // Create test project
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create test task
        $this->task = Task::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication Security Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Test brute force attack protection
     */
    public function test_brute_force_protection()
    {
        $wrongPassword = 'WrongPassword123!';
        
        // Attempt multiple failed logins
        for ($i = 0; $i < 15; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => $this->user->email,
                'password' => $wrongPassword
            ]);
            
            if ($i < 10) {
                $response->assertStatus(401);
            } else {
                // Should be rate limited after 10 attempts
                $response->assertStatus(429);
            }
        }
    }

    /**
     * Test SQL injection in login
     */
    public function test_sql_injection_login()
    {
        $maliciousInputs = [
            "'; DROP TABLE users; --",
            "' OR '1'='1",
            "' OR 1=1 --",
            "admin'--",
            "admin'/*",
            "' UNION SELECT * FROM users --",
        ];

        foreach ($maliciousInputs as $input) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => $input,
                'password' => $input
            ]);

            // Should not return 500 error (SQL injection successful)
            $response->assertStatus(400);
            $response->assertJson([
                'status' => 'error',
                'code' => 'SUSPICIOUS_INPUT'
            ]);
        }
    }

    /**
     * Test XSS in login form
     */
    public function test_xss_login()
    {
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            'javascript:alert("XSS")',
            '<img src=x onerror=alert("XSS")>',
            '<svg onload=alert("XSS")>',
            '<iframe src="javascript:alert(\'XSS\')"></iframe>',
        ];

        foreach ($xssPayloads as $payload) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => $payload,
                'password' => $payload
            ]);

            $response->assertStatus(400);
            $response->assertJson([
                'status' => 'error',
                'code' => 'SUSPICIOUS_INPUT'
            ]);
        }
    }

    /**
     * Test JWT token manipulation
     */
    public function test_jwt_token_manipulation()
    {
        // Login to get valid token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'TestPassword123!'
        ]);

        $token = $loginResponse->json('data.token');

        // Test with manipulated token
        $manipulatedTokens = [
            substr($token, 0, -10) . 'manipulated', // Change last part
            str_replace('a', 'b', $token), // Change characters
            $token . 'extra', // Add extra characters
            substr($token, 0, 10), // Truncated token
        ];

        foreach ($manipulatedTokens as $manipulatedToken) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $manipulatedToken
            ])->getJson('/api/v1/users');

            $response->assertStatus(401);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization Security Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Test horizontal privilege escalation
     */
    public function test_horizontal_privilege_escalation()
    {
        // Create another user in same tenant
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        // Login as first user
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'TestPassword123!'
        ]);

        $token = $loginResponse->json('data.token');

        // Try to access other user's data
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson("/api/v1/users/{$otherUser->id}");

        // Should be denied (403) or not found (404)
        $response->assertStatus(403);
    }

    /**
     * Test vertical privilege escalation
     */
    public function test_vertical_privilege_escalation()
    {
        // Login as regular user
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'TestPassword123!'
        ]);

        $token = $loginResponse->json('data.token');

        // Try to access admin endpoints
        $adminEndpoints = [
            '/api/v1/admin/users',
            '/api/v1/admin/settings',
            '/api/v1/admin/logs',
        ];

        foreach ($adminEndpoints as $endpoint) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token
            ])->getJson($endpoint);

            $response->assertStatus(403);
        }
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

        // Login as first user
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'TestPassword123!'
        ]);

        $token = $loginResponse->json('data.token');

        // Try to access other tenant's data
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson("/api/v1/users/{$otherUser->id}");

        $response->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Input Validation Security Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Test command injection
     */
    public function test_command_injection()
    {
        $maliciousInputs = [
            '; ls -la',
            '| cat /etc/passwd',
            '`whoami`',
            '$(id)',
            '; rm -rf /',
            '| nc -l 4444',
        ];

        foreach ($maliciousInputs as $input) {
            $response = $this->postJson('/api/v1/users', [
                'name' => $input,
                'email' => 'test@example.com',
                'password' => 'TestPassword123!'
            ]);

            $response->assertStatus(400);
            $response->assertJson([
                'status' => 'error',
                'code' => 'SUSPICIOUS_INPUT'
            ]);
        }
    }

    /**
     * Test LDAP injection
     */
    public function test_ldap_injection()
    {
        $maliciousInputs = [
            '*',
            '*)(uid=*',
            '*)(|(uid=*',
            '*))(|(uid=*',
            '*)(|(objectClass=*',
        ];

        foreach ($maliciousInputs as $input) {
            $response = $this->postJson('/api/v1/users', [
                'name' => $input,
                'email' => 'test@example.com',
                'password' => 'TestPassword123!'
            ]);

            $response->assertStatus(400);
            $response->assertJson([
                'status' => 'error',
                'code' => 'SUSPICIOUS_INPUT'
            ]);
        }
    }

    /**
     * Test NoSQL injection
     */
    public function test_nosql_injection()
    {
        $maliciousInputs = [
            '{"$ne": null}',
            '{"$gt": ""}',
            '{"$regex": ".*"}',
            '{"$where": "this.password"}',
            '{"$or": [{"$ne": null}]}',
        ];

        foreach ($maliciousInputs as $input) {
            $response = $this->postJson('/api/v1/users', [
                'name' => $input,
                'email' => 'test@example.com',
                'password' => 'TestPassword123!'
            ]);

            $response->assertStatus(400);
            $response->assertJson([
                'status' => 'error',
                'code' => 'SUSPICIOUS_INPUT'
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | File Upload Security Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Test malicious file upload
     */
    public function test_malicious_file_upload()
    {
        $maliciousFiles = [
            'malicious.php' => '<?php echo "hacked"; ?>',
            'malicious.jsp' => '<% out.println("hacked"); %>',
            'malicious.asp' => '<% response.write("hacked") %>',
            'malicious.exe' => 'MZ\x90\x00\x03\x00\x00\x00', // PE header
            'malicious.sh' => '#!/bin/bash\necho "hacked"',
        ];

        foreach ($maliciousFiles as $filename => $content) {
            $file = $this->createTestFile($filename, $content);
            
            $response = $this->postJson('/api/v1/upload-document', [
                'title' => 'Test Document',
                'description' => 'Test Description',
                'document_type' => 'other',
                'project_id' => $this->project->id,
                'file' => $file
            ]);

            $response->assertStatus(400);
            $response->assertJson([
                'status' => 'error'
            ]);
        }
    }

    /**
     * Test file size limit
     */
    public function test_file_size_limit()
    {
        // Create a large file (11MB)
        $largeContent = str_repeat('A', 11 * 1024 * 1024);
        $file = $this->createTestFile('large.txt', $largeContent);
        
        $response = $this->postJson('/api/v1/upload-document', [
            'title' => 'Large Document',
            'description' => 'Large Description',
            'document_type' => 'other',
            'project_id' => $this->project->id,
            'file' => $file
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'status' => 'error'
        ]);
    }

    /**
     * Test file type validation
     */
    public function test_file_type_validation()
    {
        $invalidFiles = [
            'test.exe' => 'executable content',
            'test.bat' => '@echo off',
            'test.com' => 'command content',
            'test.scr' => 'screensaver content',
        ];

        foreach ($invalidFiles as $filename => $content) {
            $file = $this->createTestFile($filename, $content);
            
            $response = $this->postJson('/api/v1/upload-document', [
                'title' => 'Test Document',
                'description' => 'Test Description',
                'document_type' => 'other',
                'project_id' => $this->project->id,
                'file' => $file
            ]);

            $response->assertStatus(400);
            $response->assertJson([
                'status' => 'error'
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Session Security Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Test session fixation
     */
    public function test_session_fixation()
    {
        // Get initial session
        $response = $this->getJson('/api/v1/auth/me');
        $initialSessionId = $response->headers->get('Set-Cookie');

        // Login
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'TestPassword123!'
        ]);

        $newSessionId = $loginResponse->headers->get('Set-Cookie');

        // Session ID should change after login
        $this->assertNotEquals($initialSessionId, $newSessionId);
    }

    /**
     * Test session hijacking
     */
    public function test_session_hijacking()
    {
        // Login and get session
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'TestPassword123!'
        ]);

        $token = $loginResponse->json('data.token');

        // Simulate session hijacking with different IP
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Forwarded-For' => '192.168.1.100',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ])->getJson('/api/v1/users');

        // Should still work (session management handles this)
        $response->assertStatus(200);
    }

    /*
    |--------------------------------------------------------------------------
    | CSRF Security Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Test CSRF protection
     */
    public function test_csrf_protection()
    {
        // Login first
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'TestPassword123!'
        ]);

        $token = $loginResponse->json('data.token');

        // Try to make state-changing request without CSRF token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'TestPassword123!'
        ]);

        // Should be protected by CSRF or other validation
        $response->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Security Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Test rate limiting on sensitive endpoints
     */
    public function test_rate_limiting()
    {
        $sensitiveEndpoints = [
            '/api/v1/auth/login',
            '/api/v1/auth/register',
            '/api/v1/auth/forgot-password',
        ];

        foreach ($sensitiveEndpoints as $endpoint) {
            // Make multiple requests
            for ($i = 0; $i < 15; $i++) {
                $response = $this->postJson($endpoint, [
                    'email' => 'test@example.com',
                    'password' => 'TestPassword123!'
                ]);

                if ($i >= 10) {
                    $response->assertStatus(429);
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Information Disclosure Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Test information disclosure in error messages
     */
    public function test_information_disclosure()
    {
        // Try to access non-existent resource
        $response = $this->getJson('/api/v1/users/non-existent-id');

        // Should not reveal internal structure
        $response->assertStatus(404);
        $response->assertJsonMissing(['stack_trace', 'file', 'line']);
    }

    /**
     * Test directory traversal
     */
    public function test_directory_traversal()
    {
        $maliciousPaths = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\drivers\\etc\\hosts',
            '....//....//....//etc/passwd',
            '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc%2fpasswd',
        ];

        foreach ($maliciousPaths as $path) {
            $response = $this->getJson("/api/v1/files/{$path}");

            $response->assertStatus(400);
            $response->assertJson([
                'status' => 'error',
                'code' => 'SUSPICIOUS_INPUT'
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Create test file for upload testing
     */
    private function createTestFile(string $filename, string $content)
    {
        $tempFile = tmpfile();
        fwrite($tempFile, $content);
        $tempPath = stream_get_meta_data($tempFile)['uri'];
        
        return new \Illuminate\Http\UploadedFile(
            $tempPath,
            $filename,
            mime_content_type($tempPath),
            null,
            true
        );
    }

    /**
     * Test security headers
     */
    public function test_security_headers()
    {
        $response = $this->getJson('/api/v1/test');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Strict-Transport-Security');
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Referrer-Policy');
    }

    /**
     * Test HTTPS enforcement
     */
    public function test_https_enforcement()
    {
        // Simulate HTTP request
        $response = $this->getJson('/api/v1/test', [
            'HTTP_X_FORWARDED_PROTO' => 'http'
        ]);

        // Should redirect to HTTPS or return security error
        $response->assertStatus(301);
    }
}
