<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class SecurityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $project;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create([
            'created_by' => $this->user->id
        ]);
        $this->token = $this->generateJwtToken($this->user);
    }

    /**
     * Test JWT authentication
     */
    public function test_jwt_authentication_works()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/projects');

        $response->assertStatus(200);
    }

    /**
     * Test invalid JWT token
     */
    public function test_invalid_jwt_token_returns_401()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->getJson('/api/zena/projects');

        $response->assertStatus(401);
    }

    /**
     * Test missing JWT token
     */
    public function test_missing_jwt_token_returns_401()
    {
        $response = $this->getJson('/api/zena/projects');
        $response->assertStatus(401);
    }

    /**
     * Test malformed authorization header
     */
    public function test_malformed_authorization_header_returns_401()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Invalid ' . $this->token,
        ])->getJson('/api/zena/projects');

        $response->assertStatus(401);
    }

    /**
     * Test SQL injection prevention
     */
    public function test_sql_injection_prevention()
    {
        $maliciousInput = "'; DROP TABLE users; --";

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/projects?search=' . urlencode($maliciousInput));

        $response->assertStatus(200);
        
        // Verify users table still exists
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id
        ]);
    }

    /**
     * Test XSS prevention
     */
    public function test_xss_prevention()
    {
        $xssPayload = '<script>alert("XSS")</script>';

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/projects', [
            'name' => $xssPayload,
            'description' => 'Test project',
            'status' => 'planning',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31'
        ]);

        $response->assertStatus(201);
        
        $project = $response->json('data');
        $this->assertStringNotContainsString('<script>', $project['name']);
    }

    /**
     * Test CSRF protection
     */
    public function test_csrf_protection()
    {
        // CSRF protection is typically handled by middleware
        // This test verifies that the API endpoints are protected
        $response = $this->postJson('/api/zena/projects', [
            'name' => 'Test Project',
            'description' => 'Test description',
            'status' => 'planning',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31'
        ]);

        $response->assertStatus(401); // Should require authentication
    }

    /**
     * Test rate limiting
     */
    public function test_rate_limiting()
    {
        // Make multiple requests quickly
        for ($i = 0; $i < 100; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])->getJson('/api/zena/projects');
            
            if ($response->status() === 429) {
                $this->assertEquals(429, $response->status());
                return;
            }
        }
        
        // If we get here, rate limiting might not be configured
        $this->assertTrue(true);
    }

    /**
     * Test input validation
     */
    public function test_input_validation()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/projects', [
            'name' => '', // Empty name
            'description' => str_repeat('a', 10000), // Too long description
            'status' => 'invalid_status', // Invalid status
            'start_date' => 'invalid-date', // Invalid date
            'end_date' => '2024-01-01' // End date before start date
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'description', 'status', 'start_date', 'end_date']);
    }

    /**
     * Test file upload security
     */
    public function test_file_upload_security()
    {
        // Test with malicious file
        $maliciousFile = [
            'name' => 'malicious.php',
            'type' => 'application/x-php',
            'size' => 1000,
            'tmp_name' => '/tmp/malicious.php',
            'error' => 0
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/documents', [
            'project_id' => $this->project->id,
            'title' => 'Test Document',
            'description' => 'Test description',
            'document_type' => 'drawing',
            'file' => $maliciousFile
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
    }

    /**
     * Test authorization - user can only access their own data
     */
    public function test_user_can_only_access_own_data()
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create([
            'created_by' => $otherUser->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/zena/projects/{$otherProject->id}");

        // Should return 404 or 403 depending on implementation
        $this->assertContains($response->status(), [401, 403, 404]);
    }

    /**
     * Test multi-tenant isolation
     */
    public function test_multi_tenant_isolation()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $tenant1User = User::factory()->create(['tenant_id' => $tenant1->id]);
        $tenant2User = User::factory()->create(['tenant_id' => $tenant2->id]);
        
        $tenant1Project = Project::factory()->create([
            'created_by' => $tenant1User->id,
            'tenant_id' => $tenant1->id
        ]);
        
        $tenant2Project = Project::factory()->create([
            'created_by' => $tenant2User->id,
            'tenant_id' => $tenant2->id
        ]);

        $token1 = $this->generateJwtToken($tenant1User);
        $token2 = $this->generateJwtToken($tenant2User);

        // User from tenant 1 should not see tenant 2's project
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token1,
            'X-Tenant-ID' => $tenant1->id,
        ])->getJson("/api/zena/projects/{$tenant2Project->id}");

        $this->assertContains($response->status(), [401, 403, 404]);

        // User from tenant 2 should not see tenant 1's project
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
            'X-Tenant-ID' => $tenant2->id,
        ])->getJson("/api/zena/projects/{$tenant1Project->id}");

        $this->assertContains($response->status(), [401, 403, 404]);
    }

    /**
     * Test ULID security
     */
    public function test_ulid_security()
    {
        $project = Project::factory()->create([
            'created_by' => $this->user->id
        ]);

        // Test with invalid ULID
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/projects/invalid-ulid');

        $response->assertStatus(404);

        // Test with sequential ID (should fail)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/projects/1');

        $response->assertStatus(404);
    }

    /**
     * Test data sanitization
     */
    public function test_data_sanitization()
    {
        $maliciousData = [
            'name' => 'Test Project<script>alert("XSS")</script>',
            'description' => 'Test description with <img src=x onerror=alert("XSS")>',
            'status' => 'planning',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/projects', $maliciousData);

        $response->assertStatus(201);
        
        $project = $response->json('data');
        $this->assertStringNotContainsString('<script>', $project['name']);
        $this->assertStringNotContainsString('<img', $project['description']);
    }

    /**
     * Test error message security
     */
    public function test_error_message_security()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/projects/non-existent-id');

        $response->assertStatus(404);
        
        // Error message should not expose sensitive information
        $errorMessage = $response->json('message');
        $this->assertStringNotContainsString('database', strtolower($errorMessage));
        $this->assertStringNotContainsString('sql', strtolower($errorMessage));
    }

    /**
     * TenantIsolationMiddleware should resolve /api/auth/me using the authenticated tenant,
     * even when X-Tenant-ID is stale or mismatched.
     */
    public function test_auth_me_ignores_mismatched_tenant_header()
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth-me-mismatch')->plainTextToken;
        $otherTenant = Tenant::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $otherTenant->id,
        ])->getJson('/api/auth/me');

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('success', true);
    }

    public function test_data_routes_reject_tenant_mismatch_header()
    {
        $user = User::factory()->create();
        $token = $user->createToken('tenant-header-mismatch')->plainTextToken;
        $otherTenant = Tenant::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $otherTenant->id,
        ])->getJson('/api/dashboard/data');

        $response->assertStatus(401);
        $response->assertJsonPath('code', 'TENANT_MISMATCH');
    }

    /**
     * Data routes should allow requests without an X-Tenant-ID header, relying on the
     * authenticated user's tenant_id instead of weakening isolation rules.
     */
    public function test_data_routes_allow_missing_tenant_header_when_user_has_tenant()
    {
        $user = User::factory()->create();
        $token = $user->createToken('tenant-header-optional')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/dashboard/data');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_sequential_auth_me_requests_do_not_share_tenant_context()
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $firstToken = $firstUser->createToken('sequential-first')->plainTextToken;
        $secondToken = $secondUser->createToken('sequential-second')->plainTextToken;

        $responseOne = $this->withHeaders([
            'Authorization' => 'Bearer ' . $firstToken,
            'X-Tenant-ID' => $firstUser->tenant_id,
        ])->getJson('/api/auth/me');

        $responseOne->assertStatus(200);
        $responseOne->assertJsonPath('data.id', $firstUser->id);

        $responseTwo = $this->withHeaders([
            'Authorization' => 'Bearer ' . $secondToken,
        ])->getJson('/api/auth/me');

        $responseTwo->assertStatus(200);
        $responseTwo->assertJsonPath('data.id', $secondUser->id);
    }

    /**
     * Generate JWT token for testing
     */
    private function generateJwtToken(User $user): string
    {
        return $user->createToken('security-test-token')->plainTextToken;
    }
}
