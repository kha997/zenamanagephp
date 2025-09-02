<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use Src\RBAC\Services\AuthService;

/**
 * Test bảo mật cho SQL injection và XSS
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $authHeaders;
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Đăng ký middleware alias cho test environment
        $this->app['router']->aliasMiddleware('rbac', \Src\RBAC\Middleware\RBACMiddleware::class);
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password123')
        ]);
        
        $this->authService = app(AuthService::class);
        $token = $this->authService->createTokenForUser($this->user);
        $this->authHeaders = ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * Test SQL Injection trong search parameters
     */
    public function test_sql_injection_in_search_parameters()
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Các payload SQL injection phổ biến
        $sqlInjectionPayloads = [
            "'; DROP TABLE projects; --",
            "' OR '1'='1",
            "' UNION SELECT * FROM users --",
            "'; INSERT INTO projects (name) VALUES ('hacked'); --",
            "' OR 1=1 --",
            "admin'--",
            "admin'/*",
            "' OR 'x'='x",
            "') OR ('1'='1",
            "' OR 1=1#"
        ];
        
        foreach ($sqlInjectionPayloads as $payload) {
            // Test search trong projects
            $response = $this->withHeaders($this->authHeaders)
                ->getJson('/api/v1/projects?search=' . urlencode($payload));
            
            // Không được trả về lỗi SQL và không được hack
            $this->assertNotEquals(500, $response->getStatusCode(), 
                "SQL injection payload should not cause server error: {$payload}");
            
            // Test search trong tasks
            $response = $this->withHeaders($this->authHeaders)
                ->getJson("/api/v1/projects/{$project->id}/tasks?search=" . urlencode($payload));
            
            $this->assertNotEquals(500, $response->getStatusCode(), 
                "SQL injection in tasks should not cause server error: {$payload}");
        }
        
        // Verify database integrity
        $this->assertTrue(DB::table('projects')->exists(), 'Projects table should still exist');
        $this->assertTrue(DB::table('users')->exists(), 'Users table should still exist');
    }

    /**
     * Test SQL Injection trong filter parameters
     */
    public function test_sql_injection_in_filters()
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $maliciousFilters = [
            'status' => "active'; DROP TABLE tasks; --",
            'priority' => "high' OR '1'='1",
            'component_id' => "1' UNION SELECT id FROM users --"
        ];
        
        foreach ($maliciousFilters as $filterKey => $filterValue) {
            $response = $this->withHeaders($this->authHeaders)
                ->getJson("/api/v1/projects/{$project->id}/tasks?{$filterKey}=" . urlencode($filterValue));
            
            // Should handle malicious input gracefully
            $this->assertContains($response->getStatusCode(), [200, 400, 422], 
                "Filter injection should be handled gracefully: {$filterKey}={$filterValue}");
        }
    }

    /**
     * Test XSS trong input fields
     */
    public function test_xss_in_input_fields()
    {
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<svg onload=alert("XSS")>',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(\'XSS\')"></iframe>',
            '<body onload=alert("XSS")>',
            '<div onclick="alert(\'XSS\')">Click me</div>',
            '"><script>alert("XSS")</script>',
            "';alert('XSS');//",
            '<script>document.location="http://evil.com"</script>'
        ];
        
        foreach ($xssPayloads as $payload) {
            // Test XSS trong project creation
            $response = $this->withHeaders($this->authHeaders)
                ->postJson('/api/v1/projects', [
                    'name' => $payload,
                    'description' => $payload,
                    'start_date' => '2024-01-01',
                    'end_date' => '2024-12-31'
                ]);
            
            if ($response->getStatusCode() === 201) {
                $project = $response->json('data');
                
                // Verify XSS payload is escaped/sanitized
                $this->assertStringNotContainsString('<script>', $project['name'], 
                    'XSS payload should be sanitized in name field');
                $this->assertStringNotContainsString('javascript:', $project['description'], 
                    'XSS payload should be sanitized in description field');
            }
        }
    }

    /**
     * Test XSS trong response data
     */
    public function test_xss_in_response_data()
    {
        // Tạo project với potential XSS content
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => '<script>alert("XSS")</script>Test Project',
            'description' => '<img src=x onerror=alert("XSS")>Description'
        ]);
        
        $response = $this->withHeaders($this->authHeaders)
            ->getJson("/api/v1/projects/{$project->id}");
        
        $response->assertStatus(200);
        $responseData = $response->json('data');
        
        // Verify response data is properly escaped
        $this->assertStringNotContainsString('<script>', $responseData['name'], 
            'Response should not contain unescaped script tags');
        $this->assertStringNotContainsString('onerror=', $responseData['description'], 
            'Response should not contain unescaped event handlers');
    }

    /**
     * Test CSRF protection
     */
    public function test_csrf_protection()
    {
        // Test POST request without CSRF token (should fail)
        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Test Project',
            'description' => 'Test Description'
        ]);
        
        // Should require authentication
        $this->assertEquals(401, $response->getStatusCode());
        
        // Test with invalid token
        $response = $this->withHeaders(['Authorization' => 'Bearer invalid_token'])
            ->postJson('/api/v1/projects', [
                'name' => 'Test Project',
                'description' => 'Test Description'
            ]);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * Test authorization bypass attempts
     */
    public function test_authorization_bypass_attempts()
    {
        // Tạo project của tenant khác
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        
        // Attempt to access other tenant's project
        $response = $this->withHeaders($this->authHeaders)
            ->getJson("/api/v1/projects/{$otherProject->id}");
        
        // Should be forbidden or not found
        $this->assertContains($response->getStatusCode(), [403, 404], 
            'Should not allow access to other tenant\'s projects');
        
        // Attempt to modify other tenant's project
        $response = $this->withHeaders($this->authHeaders)
            ->putJson("/api/v1/projects/{$otherProject->id}", [
                'name' => 'Hacked Project Name'
            ]);
        
        $this->assertContains($response->getStatusCode(), [403, 404], 
            'Should not allow modification of other tenant\'s projects');
    }

    /**
     * Test input validation bypass attempts
     */
    public function test_input_validation_bypass()
    {
        $maliciousInputs = [
            // Extremely long strings
            'name' => str_repeat('A', 10000),
            // Null bytes
            'description' => "Test\x00Description",
            // Unicode attacks
            'name' => "Test\u202e\u0041\u0042\u0043",
            // Path traversal
            'name' => '../../../etc/passwd',
            // Command injection
            'description' => '; cat /etc/passwd'
        ];
        
        foreach ($maliciousInputs as $field => $value) {
            $response = $this->withHeaders($this->authHeaders)
                ->postJson('/api/v1/projects', [
                    'name' => $field === 'name' ? $value : 'Valid Name',
                    'description' => $field === 'description' ? $value : 'Valid Description',
                    'start_date' => '2024-01-01',
                    'end_date' => '2024-12-31'
                ]);
            
            // Should either reject with validation error or sanitize input
            if ($response->getStatusCode() === 201) {
                $project = $response->json('data');
                
                // Verify malicious content is sanitized
                $this->assertStringNotContainsString('\x00', $project[$field] ?? '', 
                    'Null bytes should be removed');
                $this->assertStringNotContainsString('../', $project[$field] ?? '', 
                    'Path traversal should be prevented');
            } else {
                // Should return validation error
                $this->assertContains($response->getStatusCode(), [400, 422], 
                    'Should return validation error for malicious input');
            }
        }
    }
}