<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tenants;

use App\Models\User;
use App\Models\Tenant;
use App\Models\ChangeRequest;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Change Requests API permission enforcement
 * 
 * Tests that change requests endpoints properly enforce tenant.permission middleware
 * for view endpoints (GET) and mutation endpoints (POST, PUT, PATCH, DELETE).
 * 
 * Round 27: Security / RBAC Hardening
 * 
 * @group tenant-change-requests
 * @group tenant-permissions
 */
class TenantChangeRequestsPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private Tenant $tenantB;
    private ChangeRequest $changeRequest;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(55555);
        $this->setDomainName('tenant-change-requests-permission');
        $this->setupDomainIsolation();
        
        // Create tenant A
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant A',
            'slug' => 'test-tenant-a-' . uniqid(),
        ]);
        
        // Create tenant B for isolation tests
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Test Tenant B',
            'slug' => 'test-tenant-b-' . uniqid(),
        ]);
        
        // Create a project in tenant A
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
        ]);
        
        // Create a change request in tenant A
        $requester = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $this->changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'title' => 'Test Change Request',
            'status' => ChangeRequest::STATUS_DRAFT,
            'requested_by' => $requester->id,
        ]);
    }

    /**
     * Test that GET /api/v1/app/change-requests requires tenant.view_projects permission
     */
    public function test_get_change_requests_requires_view_permission(): void
    {
        $roles = ['owner', 'admin', 'member', 'viewer'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/change-requests');
            
            $response->assertStatus(200, "Role {$role} should be able to GET change requests (has tenant.view_projects)");
            $response->assertJsonStructure([
                'success',
                'data',
            ]);
        }
    }

    /**
     * Test that GET /api/v1/app/change-requests/{id} requires tenant.view_projects permission
     */
    public function test_get_change_request_requires_view_permission(): void
    {
        $viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $viewer->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($viewer);
        $token = $viewer->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/change-requests/{$this->changeRequest->id}");
        
        $response->assertStatus(200);
    }

    /**
     * Test that POST /api/v1/app/change-requests requires tenant.manage_projects permission
     */
    public function test_create_change_request_requires_manage_permission(): void
    {
        $roles = ['owner', 'admin'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-cr-' . $role . '-' . uniqid(),
            ])->postJson('/api/v1/app/change-requests', [
                'title' => 'New Change Request ' . uniqid(),
                'description' => 'Test description',
                'project_id' => $this->project->id,
                'change_type' => 'scope',
            ]);
            
            $response->assertStatus(201, "Role {$role} should be able to create change request");
            $response->assertJsonStructure([
                'success',
                'data',
            ]);
        }
    }

    /**
     * Test that POST /api/v1/app/change-requests returns 403 without permission
     */
    public function test_create_change_request_returns_403_without_permission(): void
    {
        $roles = ['member', 'viewer'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-cr-' . $role . '-' . uniqid(),
            ])->postJson('/api/v1/app/change-requests', [
                'title' => 'New Change Request',
                'description' => 'Test description',
                'project_id' => $this->project->id,
            ]);
            
            $response->assertStatus(403, "Role {$role} should NOT be able to create change request");
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test that PUT /api/v1/app/change-requests/{id} requires tenant.manage_projects permission
     */
    public function test_update_change_request_requires_manage_permission(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/change-requests/{$this->changeRequest->id}", [
            'title' => 'Updated Change Request Name',
            'description' => 'Updated description',
        ]);
        
        $response->assertStatus(200);
    }

    /**
     * Test that DELETE /api/v1/app/change-requests/{id} requires tenant.manage_projects permission
     */
    public function test_delete_change_request_requires_manage_permission(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        $crToDelete = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'status' => ChangeRequest::STATUS_DRAFT,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson("/api/v1/app/change-requests/{$crToDelete->id}");
        
        $response->assertStatus(200);
    }

    /**
     * Test that POST /api/v1/app/change-requests/{changeRequest}/submit requires tenant.manage_projects permission
     */
    public function test_submit_change_request_requires_manage_permission(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-submit-' . uniqid(),
        ])->postJson("/api/v1/app/change-requests/{$this->changeRequest->id}/submit");
        
        $response->assertStatus(200);
    }

    /**
     * Test that POST /api/v1/app/change-requests/{id}/submit returns 403 for viewer/member roles
     */
    public function test_submit_change_request_denies_viewer_member(): void
    {
        $roles = ['viewer', 'member'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-submit-deny-' . $role . '-' . uniqid(),
            ])->postJson("/api/v1/app/change-requests/{$this->changeRequest->id}/submit");
            
            $response->assertStatus(403, "Role {$role} should NOT be able to submit change request");
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test that POST /api/v1/app/change-requests/{id}/approve returns 403 for viewer/member roles
     */
    public function test_approve_change_request_denies_viewer_member(): void
    {
        // Set change request to awaiting approval status
        $this->changeRequest->update([
            'status' => ChangeRequest::STATUS_AWAITING_APPROVAL,
        ]);
        
        $roles = ['viewer', 'member'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-approve-deny-' . $role . '-' . uniqid(),
            ])->postJson("/api/v1/app/change-requests/{$this->changeRequest->id}/approve");
            
            $response->assertStatus(403, "Role {$role} should NOT be able to approve change request");
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test that POST /api/v1/app/change-requests/{id}/reject returns 403 for viewer/member roles
     */
    public function test_reject_change_request_denies_viewer_member(): void
    {
        // Set change request to awaiting approval status
        $this->changeRequest->update([
            'status' => ChangeRequest::STATUS_AWAITING_APPROVAL,
        ]);
        
        $roles = ['viewer', 'member'];
        
        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);
            
            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);
            
            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
                'Idempotency-Key' => 'test-reject-deny-' . $role . '-' . uniqid(),
            ])->postJson("/api/v1/app/change-requests/{$this->changeRequest->id}/reject", [
                'decision_note' => 'Test rejection',
            ]);
            
            $response->assertStatus(403, "Role {$role} should NOT be able to reject change request");
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test tenant isolation - change requests from tenant A not visible in tenant B
     */
    public function test_tenant_isolation(): void
    {
        // Create project and change request in tenant B
        $projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Project',
        ]);
        
        $requesterB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);
        
        $crB = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $projectB->id,
            'title' => 'Tenant B Change Request',
            'requested_by' => $requesterB->id,
        ]);
        
        // Create user in tenant A
        $userA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $userA->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($userA);
        $token = $userA->createToken('test-token')->plainTextToken;
        
        // User A should only see change requests from tenant A
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/change-requests');
        
        $response->assertStatus(200);
        $changeRequests = $response->json('data', []);
        
        // Verify change request B is not in the list
        $crIds = array_column($changeRequests, 'id');
        $this->assertNotContains($crB->id, $crIds, 'Tenant B change request should not be visible in tenant A');
        
        // Verify user A cannot access change request B directly
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/change-requests/{$crB->id}");
        
        // Should return 403 or 404
        $this->assertContains($response2->status(), [403, 404], 'Should not be able to access tenant B change request');
    }

    /**
     * Test that tenant A cannot modify change request of tenant B
     */
    public function test_cannot_modify_change_request_of_another_tenant(): void
    {
        // Create project and change request in tenant B
        $projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Project',
        ]);
        
        $requesterB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);
        
        $crB = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $projectB->id,
            'title' => 'Tenant B Change Request',
            'status' => ChangeRequest::STATUS_DRAFT,
            'requested_by' => $requesterB->id,
        ]);
        
        // Create owner of tenant A
        $userOwnerA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $userOwnerA->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($userOwnerA);
        $token = $userOwnerA->createToken('test-token')->plainTextToken;
        
        // Attempt to update change request of tenant B
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-cross-tenant-' . uniqid(),
        ])->putJson("/api/v1/app/change-requests/{$crB->id}", [
            'title' => 'Hacked Name',
        ]);
        
        // Should return 403 or 404
        $this->assertContains($response->status(), [403, 404], 'Should not be able to update tenant B change request');
        
        // Verify change request B is unchanged
        $crB->refresh();
        $this->assertEquals('Tenant B Change Request', $crB->title, 'Change request should not be modified');
    }
}

