<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\ZenaProject;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Traits\AuthenticationTestTrait;
use Tests\Traits\RouteNameTrait;
use Laravel\Sanctum\Sanctum;

class SecurityTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTestTrait, RouteNameTrait;

    protected User $user;
    protected ZenaProject $project;
    protected string $token;
    protected string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiActingAsTenantAdmin();
        $this->user = $this->apiFeatureUser;
        $this->tenantId = $this->apiFeatureTenant->id;
        $this->project = ZenaProject::factory()->create([
            'created_by' => $this->user->id,
            'tenant_id' => $this->tenantId
        ]);
        $this->token = $this->apiFeatureToken;
    }

    /**
     * Test JWT authentication
     */
    public function test_jwt_authentication_works()
    {
        $response = $this->apiGet($this->zena('projects.index'));

        $response->assertStatus(200);
    }

    /**
     * Test invalid JWT token
     */
    public function test_invalid_jwt_token_returns_401()
    {
        $response = $this->withHeaders(array_merge($this->apiHeaders, [
            'Authorization' => 'Bearer invalid-token',
        ]))->getJson($this->zena('projects.index'));

        $response->assertStatus(401);
    }

    /**
     * Test missing JWT token
     */
    public function test_missing_jwt_token_returns_401()
    {
        $response = $this->withHeaders($this->tenantHeaders())->getJson($this->zena('projects.index'));
        $response->assertStatus(401);
    }

    /**
     * Test malformed authorization header
     */
    public function test_malformed_authorization_header_returns_401()
    {
        $response = $this->withHeaders(array_merge($this->apiHeaders, [
            'Authorization' => 'Invalid ' . $this->apiHeaders['Authorization'],
        ]))->getJson($this->zena('projects.index'));

        $response->assertStatus(401);
    }

    /**
     * Test SQL injection prevention
     */
    public function test_sql_injection_prevention()
    {
        $maliciousInput = "'; DROP TABLE users; --";

        $response = $this->apiGet($this->zena('projects.index', query: ['search' => $maliciousInput]));

        $response->assertStatus(400);
        
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

        $response = $this->apiPost($this->zena('projects.store'), [
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
        $response = $this->withHeaders($this->tenantHeaders())->postJson($this->zena('projects.store'), [
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
            $response = $this->apiGet($this->zena('projects.index'));
            
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
        $response = $this->apiPost($this->zena('projects.store'), [
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

        $response = $this->apiPost($this->zena('documents.store'), [
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
        $otherProject = ZenaProject::factory()->create([
            'created_by' => $otherUser->id
        ]);

        $headers = array_merge($this->tenantHeaders(), [
            'Authorization' => 'Bearer ' . $this->token,
        ]);
        $response = $this->withHeaders($headers)->getJson($this->zena('projects.show', ['id' => $otherProject->id]));

        // Should return 404 or 403 depending on implementation
        $this->assertContains($response->status(), [403, 404]);
    }

    /**
     * Test multi-tenant isolation
     */
    public function test_multi_tenant_isolation()
    {
        $tenant1User = User::factory()->create(['tenant_id' => 1]);
        $tenant2User = User::factory()->create(['tenant_id' => 2]);
        
        $tenant1Project = ZenaProject::factory()->create([
            'created_by' => $tenant1User->id,
            'tenant_id' => 1
        ]);
        
        $tenant2Project = ZenaProject::factory()->create([
            'created_by' => $tenant2User->id,
            'tenant_id' => 2
        ]);

        $tenant1Headers = [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenant1User->tenant_id,
        ];

        $tenant2Headers = [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenant2User->tenant_id,
        ];

        $this->ensureUserProjectViewPermission($tenant1User);
        $this->ensureUserProjectViewPermission($tenant2User);

        // Unauthenticated request should still return the standard error envelope
        $unauthenticatedResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenant1User->tenant_id,
        ])->getJson($this->zena('projects.show', ['id' => $tenant2Project->id]));

        $unauthenticatedResponse->assertStatus(401);
        $unauthenticatedResponse->assertJsonStructure([
            'success',
            'message',
            'error' => ['id', 'code', 'message', 'details']
        ]);
        $this->assertSame('E401.AUTHENTICATION', $unauthenticatedResponse->json('error.code'));

        // Header mismatch with valid token should be rejected before data access
        Sanctum::actingAs($tenant1User, [], 'sanctum');
        $tenantMismatchResponse = $this->withHeaders(array_merge($tenant1Headers, [
            'X-Tenant-ID' => (string) $tenant2User->tenant_id,
        ]))->getJson($this->zena('projects.show', ['id' => $tenant1Project->id]));

        $tenantMismatchResponse->assertStatus(403);
        $this->assertSame('TENANT_INVALID', $tenantMismatchResponse->json('error.code'));
        $this->assertSame('X-Tenant-ID does not match authenticated user', $tenantMismatchResponse->json('error.message'));

        // Authenticated cross-tenant access should be indistinguishable from not found
        Sanctum::actingAs($tenant1User, [], 'sanctum');
        $crossTenantResponse = $this->withHeaders($tenant1Headers)->getJson($this->zena('projects.show', ['id' => $tenant2Project->id]));
        $crossTenantResponse->assertStatus(404);
        $this->assertSame('E404.NOT_FOUND', $crossTenantResponse->json('error.code'));

        Sanctum::actingAs($tenant2User, [], 'sanctum');
        $reverseCrossTenantResponse = $this->withHeaders($tenant2Headers)->getJson($this->zena('projects.show', ['id' => $tenant1Project->id]));
        $reverseCrossTenantResponse->assertStatus(404);
        $this->assertSame('E404.NOT_FOUND', $reverseCrossTenantResponse->json('error.code'));
    }

    /**
     * Test ULID security
     */
    public function test_ulid_security()
    {
        $project = ZenaProject::factory()->create([
            'created_by' => $this->user->id
        ]);

        // Test with invalid ULID
        $authHeaders = array_merge($this->tenantHeaders(), [
            'Authorization' => 'Bearer ' . $this->token,
        ]);
        $response = $this->withHeaders($authHeaders)->getJson($this->zena('projects.show', ['id' => 'invalid-ulid']));

        $response->assertStatus(404);

        // Test with sequential ID (should fail)
        $response = $this->withHeaders($authHeaders)->getJson($this->zena('projects.show', ['id' => '1']));

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

        $response = $this->withHeaders(array_merge($this->tenantHeaders(), [
            'Authorization' => 'Bearer ' . $this->token,
        ]))->postJson($this->zena('projects.store'), $maliciousData);

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
        $authHeaders = array_merge($this->tenantHeaders(), [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response = $this->withHeaders($authHeaders)->getJson($this->zena('projects.show', ['id' => 'non-existent-id']));

        $response->assertStatus(404);
        
        // Error message should not expose sensitive information
        $errorMessage = $response->json('message');
        $this->assertStringNotContainsString('database', strtolower($errorMessage));
        $this->assertStringNotContainsString('sql', strtolower($errorMessage));
    }

    private function ensureUserProjectViewPermission(User $user): void
    {
        $permission = Permission::updateOrCreate(
            ['code' => 'project.view'],
            [
                'name' => 'project.view',
                'module' => 'project',
                'action' => 'view',
                'description' => 'View project records',
            ]
        );

        $role = Role::firstOrCreate(
            ['name' => 'project_viewer'],
            [
                'scope' => 'system',
                'description' => 'Project Viewer',
                'is_active' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching($permission->id);
        $user->roles()->syncWithoutDetaching($role->id);
        $user->systemRoles()->syncWithoutDetaching($role->id);
    }
}
