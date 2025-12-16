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
 * Tests for Change Requests API tenant context isolation
 * 
 * Tests that getAlerts() and getActivity() endpoints properly use active tenant context
 * (via getTenantId()) instead of user->tenant_id, especially for multi-tenant users.
 * 
 * Round 28: ChangeRequests Tenant Context & Workflow Hardening
 * 
 * @group tenant-change-requests
 * @group tenant-context
 */
class TenantChangeRequestsTenantContextTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $multiTenantUser;
    private Project $projectA;
    private Project $projectB;
    private ChangeRequest $crA1;
    private ChangeRequest $crA2;
    private ChangeRequest $crB1;
    private ChangeRequest $crB2;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(66666);
        $this->setDomainName('tenant-change-requests-context');
        $this->setupDomainIsolation();
        
        // Create tenant A
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Test Tenant A',
            'slug' => 'test-tenant-a-' . uniqid(),
        ]);
        
        // Create tenant B
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Test Tenant B',
            'slug' => 'test-tenant-b-' . uniqid(),
        ]);
        
        // Create multi-tenant user (member of both tenants)
        $this->multiTenantUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id, // Legacy tenant_id (tenant A)
            'email_verified_at' => now(),
        ]);
        
        // Attach user to tenant A (default)
        $this->multiTenantUser->tenants()->attach($this->tenantA->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        // Attach user to tenant B (non-default)
        $this->multiTenantUser->tenants()->attach($this->tenantB->id, [
            'role' => 'admin',
            'is_default' => false,
        ]);
        
        // Create projects in both tenants
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A',
        ]);
        
        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B',
        ]);
        
        // Create change requests in tenant A
        $requesterA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);
        
        $this->crA1 = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'title' => 'Change Request A1',
            'status' => ChangeRequest::STATUS_AWAITING_APPROVAL,
            'due_date' => now()->subDay(), // Overdue
            'requested_by' => $requesterA->id,
        ]);
        
        $this->crA2 = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'title' => 'Change Request A2',
            'status' => ChangeRequest::STATUS_DRAFT,
            'requested_by' => $requesterA->id,
        ]);
        
        // Create change requests in tenant B
        $requesterB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);
        
        $this->crB1 = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'title' => 'Change Request B1',
            'status' => ChangeRequest::STATUS_AWAITING_APPROVAL,
            'due_date' => now()->subDay(), // Overdue
            'requested_by' => $requesterB->id,
        ]);
        
        $this->crB2 = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'title' => 'Change Request B2',
            'status' => ChangeRequest::STATUS_APPROVED,
            'requested_by' => $requesterB->id,
        ]);
    }

    /**
     * Test that getAlerts() uses active tenant context (tenant B) and only shows tenant B alerts
     */
    public function test_get_alerts_uses_active_tenant_context(): void
    {
        Sanctum::actingAs($this->multiTenantUser);
        $token = $this->multiTenantUser->createToken('test-token')->plainTextToken;
        
        // Set active tenant to tenant B via session
        $this->withSession(['selected_tenant_id' => $this->tenantB->id]);
        
        // Call getAlerts endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/change-requests/alerts');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
        ]);
        
        $alerts = $response->json('data', []);
        
        // Verify alerts only contain tenant B change requests
        $alertIds = array_column($alerts, 'id');
        
        // Should contain alert for crB1 (overdue in tenant B)
        $this->assertContains(
            'cr_overdue_' . $this->crB1->id,
            $alertIds,
            'Should include tenant B overdue change request in alerts'
        );
        
        // Should NOT contain alert for crA1 (overdue in tenant A)
        $this->assertNotContains(
            'cr_overdue_' . $this->crA1->id,
            $alertIds,
            'Should NOT include tenant A change request when tenant B is active'
        );
    }

    /**
     * Test that getActivity() uses active tenant context (tenant B) and only shows tenant B activity
     */
    public function test_get_activity_uses_active_tenant_context(): void
    {
        Sanctum::actingAs($this->multiTenantUser);
        $token = $this->multiTenantUser->createToken('test-token')->plainTextToken;
        
        // Set active tenant to tenant B via session
        $this->withSession(['selected_tenant_id' => $this->tenantB->id]);
        
        // Call getActivity endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/change-requests/activity');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
        ]);
        
        $activities = $response->json('data', []);
        
        // Verify activities only contain tenant B change requests
        $activityIds = array_column($activities, 'id');
        
        // Should contain activities for tenant B change requests
        $this->assertContains(
            'cr_' . $this->crB1->id,
            $activityIds,
            'Should include tenant B change request in activity'
        );
        
        $this->assertContains(
            'cr_' . $this->crB2->id,
            $activityIds,
            'Should include tenant B change request in activity'
        );
        
        // Should NOT contain activities for tenant A change requests
        $this->assertNotContains(
            'cr_' . $this->crA1->id,
            $activityIds,
            'Should NOT include tenant A change request when tenant B is active'
        );
        
        $this->assertNotContains(
            'cr_' . $this->crA2->id,
            $activityIds,
            'Should NOT include tenant A change request when tenant B is active'
        );
    }

    /**
     * Test that getAlerts() uses active tenant context (tenant A) when tenant A is active
     */
    public function test_get_alerts_uses_active_tenant_a_context(): void
    {
        Sanctum::actingAs($this->multiTenantUser);
        $token = $this->multiTenantUser->createToken('test-token')->plainTextToken;
        
        // Set active tenant to tenant A via session
        $this->withSession(['selected_tenant_id' => $this->tenantA->id]);
        
        // Call getAlerts endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/change-requests/alerts');
        
        $response->assertStatus(200);
        
        $alerts = $response->json('data', []);
        $alertIds = array_column($alerts, 'id');
        
        // Should contain alert for crA1 (overdue in tenant A)
        $this->assertContains(
            'cr_overdue_' . $this->crA1->id,
            $alertIds,
            'Should include tenant A overdue change request in alerts'
        );
        
        // Should NOT contain alert for crB1 (overdue in tenant B)
        $this->assertNotContains(
            'cr_overdue_' . $this->crB1->id,
            $alertIds,
            'Should NOT include tenant B change request when tenant A is active'
        );
    }

    /**
     * Test cross-tenant isolation: user only in tenant A cannot see tenant B data
     */
    public function test_cross_tenant_isolation_alerts(): void
    {
        // Create user only in tenant A
        $userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        $userA->tenants()->attach($this->tenantA->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($userA);
        $token = $userA->createToken('test-token')->plainTextToken;
        
        // Call getAlerts endpoint (should use tenant A context)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/change-requests/alerts');
        
        $response->assertStatus(200);
        
        $alerts = $response->json('data', []);
        $alertIds = array_column($alerts, 'id');
        
        // Should NOT contain any tenant B alerts
        $this->assertNotContains(
            'cr_overdue_' . $this->crB1->id,
            $alertIds,
            'User in tenant A should NOT see tenant B alerts'
        );
    }

    /**
     * Test cross-tenant isolation: user only in tenant A cannot see tenant B activity
     */
    public function test_cross_tenant_isolation_activity(): void
    {
        // Create user only in tenant A
        $userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        $userA->tenants()->attach($this->tenantA->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($userA);
        $token = $userA->createToken('test-token')->plainTextToken;
        
        // Call getActivity endpoint (should use tenant A context)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/change-requests/activity');
        
        $response->assertStatus(200);
        
        $activities = $response->json('data', []);
        $activityIds = array_column($activities, 'id');
        
        // Should NOT contain any tenant B activities
        $this->assertNotContains(
            'cr_' . $this->crB1->id,
            $activityIds,
            'User in tenant A should NOT see tenant B activity'
        );
        
        $this->assertNotContains(
            'cr_' . $this->crB2->id,
            $activityIds,
            'User in tenant A should NOT see tenant B activity'
        );
    }
}

