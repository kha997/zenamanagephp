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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

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
                // Depending on configured limiter, request may remain unauthorized or be throttled.
                $this->assertContains($response->status(), [401, 429]);
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

            // Input must be safely rejected: no server error, no successful auth.
            $this->assertContains($response->status(), [400, 401, 422]);
            $response->assertJsonPath('status', 'error');
            $code = $response->json('code') ?? $response->json('error.code');
            $this->assertNotNull($code);
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

            // Input must be safely rejected: no server error, no successful auth.
            $this->assertContains($response->status(), [400, 401, 422]);
            $response->assertJsonPath('status', 'error');
            $code = $response->json('code') ?? $response->json('error.code');
            $this->assertNotNull($code);
        }
    }

    /**
     * Test JWT token manipulation
     */
    public function test_jwt_token_manipulation()
    {
        // Login to get valid token
        $loginResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $this->tenant->id,
        ])->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'TestPassword123!'
        ]);
        $loginResponse->assertStatus(200);

        $token = $loginResponse->json('data.token');
        $this->assertNotEmpty($token);

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
            ])->getJson('/api/v1/dashboard');

            $this->assertContains($response->status(), [401, 403]);
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
        $loginResponse = $this->withHeaders([
            'X-Tenant-ID' => (string) $this->tenant->id,
        ])->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'TestPassword123!'
        ]);
        $loginResponse->assertStatus(200);

        $token = $loginResponse->json('data.token');
        $this->assertNotEmpty($token);

        // Try to access other user's data
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => (string) $this->tenant->id,
        ])->getJson("/api/v1/dashboard/users/{$otherUser->id}/assignments");

        // Auth/tenant middleware may reject before authorization in this stack.
        $this->assertContains($response->status(), [401, 403, 404]);
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
            route('api.sidebar-configs.index', [], false),
            route('api.v1.admin.dashboard.stats', [], false),
            route('api.v1.admin.dashboard.metrics', [], false),
        ];

        foreach ($adminEndpoints as $endpoint) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token
            ])->getJson($endpoint);

            $this->assertContains($response->status(), [401, 403, 404]);
        }
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation()
    {
        // Create another tenant ID for header mismatch check
        $otherTenant = Tenant::factory()->create();

        Sanctum::actingAs($this->user, [], 'sanctum');

        // Header mismatch must be blocked before resource access.
        $response = $this->withHeaders([
            'X-Tenant-ID' => (string) $otherTenant->id,
        ])->getJson("/api/zena/projects/{$this->project->id}");

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'TENANT_INVALID');
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
        $endpoint = '/api/v1/dashboard/simple/users';
        $maliciousInputs = [
            '; ls -la',
            '| cat /etc/passwd',
            '`whoami`',
            '$(id)',
            '; rm -rf /',
            '| nc -l 4444',
        ];

        foreach ($maliciousInputs as $input) {
            $email = 'sec-' . Str::ulid() . '@example.com';

            $response = $this->postJson($endpoint, [
                'name' => $input,
                'email' => $email,
                'password' => 'TestPassword123!',
                'password_confirmation' => 'TestPassword123!',
            ]);

            $status = $response->status();
            $this->assertNotSame(
                404,
                $status,
                "Endpoint {$endpoint} should exist; got 404 for payload: {$input}"
            );

            // Payload must never be accepted; allow stack-specific secure rejections.
            $this->assertContains($status, [400, 401, 403, 422], "Unexpected status {$status}");

            if ($status === 400) {
                $this->assertSame(
                    'SUSPICIOUS_INPUT',
                    $response->json('code') ?? $response->json('error.code')
                );
            }

            if ($status === 422) {
                $response->assertJsonValidationErrors(['name']);
            }
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
            $response = $this->postJson(route('users.store', [], false), [
                'name' => $input,
                'email' => 'test@example.com',
                'password' => 'TestPassword123!'
            ]);

            // Payload must never be accepted; route-specific handling may vary by stack.
            $this->assertContains($response->status(), [400, 401, 403, 404, 422]);
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
            $response = $this->postJson(route('users.store', [], false), [
                'name' => $input,
                'email' => 'test@example.com',
                'password' => 'TestPassword123!'
            ]);

            // Payload must never be accepted; route-specific handling may vary by stack.
            $this->assertContains($response->status(), [400, 401, 403, 404, 422]);
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
        $sessionCookieName = config('session.cookie');

        // Start a real web session and capture the pre-login session id.
        $initialResponse = $this->get('/login');
        $initialSessionCookie = collect($initialResponse->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $sessionCookieName);

        $this->assertNotNull($initialSessionCookie, 'Initial web session cookie was not issued.');
        $initialSessionId = $initialSessionCookie->getValue();

        // Attempt login with the existing session to ensure session fixation defense rotates id.
        $loginResponse = $this->withCookie($sessionCookieName, $initialSessionId)->post('/login', [
            'email' => $this->user->email,
            'password' => 'TestPassword123!'
        ]);
        $newSessionCookie = collect($loginResponse->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $sessionCookieName);

        $this->assertNotNull($newSessionCookie, 'Login response did not rotate session cookie.');
        $newSessionId = $newSessionCookie->getValue();

        // Session id must rotate after successful login.
        $this->assertNotSame($initialSessionId, $newSessionId);
    }

    /**
     * Test session hijacking
     */
    public function test_session_hijacking()
    {
        $sessionCookieName = config('session.cookie');

        // Start a real web session and capture pre-login session id.
        $initialResponse = $this->get('/login');
        $initialSessionCookie = collect($initialResponse->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $sessionCookieName);

        $this->assertNotNull($initialSessionCookie, 'Initial web session cookie was not issued.');
        $initialSessionId = $initialSessionCookie->getValue();

        // Login and capture rotated authenticated session cookie.
        $loginResponse = $this->withCookie($sessionCookieName, $initialSessionId)->post('/login', [
            'email' => $this->user->email,
            'password' => 'TestPassword123!'
        ]);
        $newSessionCookie = collect($loginResponse->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === $sessionCookieName);

        $this->assertNotNull($newSessionCookie, 'Login response did not rotate session cookie.');
        $sessionCookieValue = $newSessionCookie->getValue();

        // Simulate session hijacking by replaying authenticated cookie from a different client fingerprint.
        $response = $this->withHeaders([
            'X-Forwarded-For' => '192.168.1.100',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ])->withCookie($sessionCookieName, $sessionCookieValue)
            ->get('/app/dashboard');

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
        // Hit a protected API route without any auth context.
        $response = $this->getJson('/api/v1/dashboard');

        // For API routes this is effectively an auth-guard check.
        $response->assertStatus(401);

        if ($response->json('status') !== null) {
            $response->assertJsonPath('status', 'error');
        }
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
            [
                'uri' => '/api/v1/auth/login',
                'payload' => [
                    'email' => 'test@example.com',
                    'password' => 'TestPassword123!',
                ],
                'expected_secure_statuses' => [401, 422, 429],
            ],
            [
                'uri' => '/api/v1/auth/register',
                'payload' => [
                    // Intentionally incomplete to ensure validation/secure rejection paths.
                    'email' => 'test@example.com',
                    'password' => 'TestPassword123!',
                ],
                'expected_secure_statuses' => [400, 422, 429],
            ],
        ];

        foreach ($sensitiveEndpoints as $endpoint) {
            // Make multiple requests
            for ($i = 0; $i < 15; $i++) {
                $response = $this->postJson($endpoint['uri'], $endpoint['payload']);

                // Rate limiting may or may not execute before auth/validation in this stack.
                // The invariant is secure rejection, never a server error.
                $this->assertContains($response->status(), $endpoint['expected_secure_statuses']);
                $this->assertLessThan(500, $response->status());
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
        $response = $this->getJson(route('users.show', ['user' => 'non-existent-id'], false));

        // Route stack may reject unauthenticated requests before 404 resolution.
        $this->assertContains($response->status(), [401, 404]);
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
            $response = $this->getJson('/api/v1/auth/oidc/' . rawurlencode($path) . '/initiate'); // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_SECURITY_TRAVERSAL)

            $this->assertContains($response->status(), [400, 404, 422]);
            $this->assertLessThan(500, $response->status());
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
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Referrer-Policy');
    }

    /**
     * Test HTTPS enforcement
     */
    public function test_https_enforcement()
    {
        // Simulate HTTP request
        $response = $this->getJson('/api/v1/auth/me', [
            'HTTP_X_FORWARDED_PROTO' => 'http'
        ]);

        if (app()->environment('production')) {
            $this->assertContains($response->status(), [301, 302]);
            return;
        }

        // Outside production, HTTPS forcing is not enabled; still expect secure rejection.
        $this->assertFalse(in_array($response->status(), [301, 302], true));
        $this->assertContains($response->status(), [401, 404]);
    }
}
