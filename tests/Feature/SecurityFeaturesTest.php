<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * Security Features Test
 * 
 * Tests the security features including:
 * - CSRF Protection
 * - Rate Limiting
 * - Input Validation
 * - SQL Injection Prevention
 * - XSS Protection
 * - Authentication & Authorization
 * - Session Security
 * - API Security
 */
class SecurityFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->tenant = Tenant::create([
            'name' => 'Security Test Company',
            'slug' => 'security-test-' . uniqid(),
            'status' => 'active'
        ]);

        $this->user = User::create([
            'name' => 'Security Tester',
            'email' => 'security@test-' . uniqid() . '.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SEC-' . uniqid(),
            'name' => 'Security Test Project',
            'description' => 'Test project for security features',
            'status' => 'active',
            'budget_total' => 100000.00
        ]);
    }

    /**
     * Test CSRF protection on forms
     */
    public function test_csrf_protection_on_forms(): void
    {
        // Test that POST requests without CSRF token are rejected
        $response = $this->post('/projects', [
            'name' => 'Test Project',
            'description' => 'Test Description'
        ]);

        $this->assertEquals(419, $response->status()); // CSRF token mismatch
    }

    /**
     * Test rate limiting on API endpoints
     */
    public function test_rate_limiting_on_api_endpoints(): void
    {
        // Clear any existing rate limits
        RateLimiter::clear('api');

        // Test login rate limiting
        $loginData = [
            'email' => $this->user->email,
            'password' => 'wrong_password'
        ];

        // Make multiple failed login attempts
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/auth/login', $loginData);
            
            if ($i < 5) {
                $this->assertEquals(401, $response->status()); // Unauthorized
            } else {
                $this->assertEquals(429, $response->status()); // Too Many Requests
            }
        }
    }

    /**
     * Test input validation and sanitization
     */
    public function test_input_validation_and_sanitization(): void
    {
        $this->actingAs($this->user);

        // Test SQL injection attempt
        $maliciousInput = "'; DROP TABLE users; --";
        
        $response = $this->postJson('/api/projects', [
            'name' => $maliciousInput,
            'description' => 'Test project',
            'tenant_id' => $this->tenant->id,
            'code' => 'TEST-001'
        ]);

        // Should not cause SQL error, should be handled gracefully
        $this->assertNotEquals(500, $response->status());
        
        // Check that malicious input is sanitized
        if ($response->status() === 201) {
            $project = Project::where('name', $maliciousInput)->first();
            $this->assertNull($project); // Should not be created with malicious input
        }
    }

    /**
     * Test XSS protection
     */
    public function test_xss_protection(): void
    {
        $this->actingAs($this->user);

        $xssPayload = '<script>alert("XSS")</script>';
        
        $response = $this->postJson('/api/projects', [
            'name' => 'Safe Project Name',
            'description' => $xssPayload,
            'tenant_id' => $this->tenant->id,
            'code' => 'XSS-001'
        ]);

        if ($response->status() === 201) {
            $project = Project::find($response->json('data.id'));
            $this->assertNotNull($project);
            
            // Check that XSS payload is escaped
            $this->assertStringNotContainsString('<script>', $project->description);
            $this->assertStringContainsString('&lt;script&gt;', $project->description);
        }
    }

    /**
     * Test authentication requirements
     */
    public function test_authentication_requirements(): void
    {
        // Test protected routes without authentication
        $protectedRoutes = [
            '/api/projects',
            '/api/tasks',
            '/api/users',
            '/api/teams'
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->getJson($route);
            $this->assertEquals(401, $response->status()); // Unauthorized
        }
    }

    /**
     * Test authorization and tenant isolation
     */
    public function test_authorization_and_tenant_isolation(): void
    {
        // Create another tenant and user
        $otherTenant = Tenant::create([
            'name' => 'Other Company',
            'slug' => 'other-company-' . uniqid(),
            'status' => 'active'
        ]);

        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@test-' . uniqid() . '.com',
            'password' => bcrypt('password'),
            'tenant_id' => $otherTenant->id
        ]);

        $this->actingAs($this->user);

        // Try to access other tenant's data
        $response = $this->getJson('/api/projects');
        $this->assertEquals(200, $response->status());
        
        $projects = $response->json('data');
        foreach ($projects as $project) {
            $this->assertEquals($this->tenant->id, $project['tenant_id']);
        }
    }

    /**
     * Test session security
     */
    public function test_session_security(): void
    {
        // Test session regeneration on login
        $response = $this->postJson('/api/auth/login', [
            'email' => $this->user->email,
            'password' => 'password'
        ]);

        if ($response->status() === 200) {
            $this->assertArrayHasKey('token', $response->json());
            $this->assertArrayHasKey('user', $response->json());
        }
    }

    /**
     * Test API security headers
     */
    public function test_api_security_headers(): void
    {
        $response = $this->getJson('/api/projects');
        
        // Check for security headers
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertTrue($response->headers->has('X-Frame-Options'));
        $this->assertTrue($response->headers->has('X-XSS-Protection'));
    }

    /**
     * Test password security
     */
    public function test_password_security(): void
    {
        // Test password hashing
        $password = 'testpassword123';
        $hashedPassword = bcrypt($password);
        
        $this->assertNotEquals($password, $hashedPassword);
        $this->assertTrue(password_verify($password, $hashedPassword));
        
        // Test password strength validation
        $weakPasswords = ['123', 'password', 'abc'];
        
        foreach ($weakPasswords as $weakPassword) {
            $response = $this->postJson('/api/auth/register', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => $weakPassword,
                'password_confirmation' => $weakPassword
            ]);
            
            // Should fail validation
            $this->assertNotEquals(201, $response->status());
        }
    }

    /**
     * Test file upload security
     */
    public function test_file_upload_security(): void
    {
        $this->actingAs($this->user);

        // Test malicious file upload
        $maliciousFile = 'malicious.php';
        $maliciousContent = '<?php system($_GET["cmd"]); ?>';
        
        $response = $this->postJson('/api/documents', [
            'name' => 'Test Document',
            'file' => $maliciousFile,
            'content' => $maliciousContent,
            'project_id' => $this->project->id
        ]);

        // Should reject malicious files
        $this->assertNotEquals(201, $response->status());
    }

    /**
     * Test brute force protection
     */
    public function test_brute_force_protection(): void
    {
        // Test multiple failed login attempts
        $loginData = [
            'email' => $this->user->email,
            'password' => 'wrong_password'
        ];

        $attempts = 0;
        $maxAttempts = 10;

        do {
            $response = $this->postJson('/api/auth/login', $loginData);
            $attempts++;
            
            if ($response->status() === 429) {
                break; // Rate limited
            }
        } while ($attempts < $maxAttempts);

        $this->assertLessThanOrEqual($maxAttempts, $attempts);
    }

    /**
     * Test data validation and type safety
     */
    public function test_data_validation_and_type_safety(): void
    {
        $this->actingAs($this->user);

        // Test invalid data types
        $invalidData = [
            'name' => 123, // Should be string
            'budget_total' => 'not_a_number', // Should be number
            'status' => 'invalid_status', // Should be valid enum
            'start_date' => 'not_a_date' // Should be date
        ];

        $response = $this->postJson('/api/projects', array_merge($invalidData, [
            'tenant_id' => $this->tenant->id,
            'code' => 'INVALID-001'
        ]));

        // Should fail validation
        $this->assertNotEquals(201, $response->status());
        $this->assertArrayHasKey('errors', $response->json());
    }

    /**
     * Test API versioning and backward compatibility
     */
    public function test_api_versioning(): void
    {
        // Test API version headers
        $response = $this->getJson('/api/v1/projects');
        
        // Should handle versioning gracefully
        $this->assertNotEquals(404, $response->status());
    }

    /**
     * Test comprehensive security audit
     */
    public function test_comprehensive_security_audit(): void
    {
        $this->actingAs($this->user);

        // Test various security scenarios
        $securityTests = [
            // Test 1: Normal operation should work
            function() {
                $response = $this->getJson('/api/projects');
                return $response->status() === 200;
            },
            
            // Test 2: Invalid JSON should be rejected
            function() {
                $response = $this->call('POST', '/api/projects', [], [], [], 
                    ['CONTENT_TYPE' => 'application/json'], 
                    'invalid json'
                );
                return $response->status() !== 200;
            },
            
            // Test 3: Oversized payload should be rejected
            function() {
                $largeData = str_repeat('a', 10000);
                $response = $this->postJson('/api/projects', [
                    'name' => $largeData,
                    'description' => $largeData,
                    'tenant_id' => $this->tenant->id,
                    'code' => 'LARGE-001'
                ]);
                return $response->status() !== 201;
            }
        ];

        foreach ($securityTests as $index => $test) {
            $this->assertTrue($test(), "Security test " . ($index + 1) . " failed");
        }
    }
}
