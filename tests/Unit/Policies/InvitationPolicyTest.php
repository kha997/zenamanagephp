<?php declare(strict_types=1);

namespace Tests\Unit\Policies;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Tests\Helpers\PolicyTestHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Invitation;
use App\Policies\InvitationPolicy;

/**
 * Unit tests for InvitationPolicy
 * 
 * Tests tenant isolation, admin permissions, and inviter/invitee permissions
 * 
 * @group invitations
 * @group policies
 */
class InvitationPolicyTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $superAdmin; // Super admin
    private User $orgAdmin; // Org admin
    private User $user1; // Inviter
    private User $user2; // Invitee (same email)
    private User $user3; // Different tenant
    private Invitation $invitation1;
    private Invitation $invitation2;
    private InvitationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(121212);
        $this->setDomainName('invitations');
        $this->setupDomainIsolation();
        
        // Create tenants
        $this->tenant1 = TestDataSeeder::createTenant(['name' => 'Tenant 1']);
        $this->tenant2 = TestDataSeeder::createTenant(['name' => 'Tenant 2']);
        
        // Create users
        $this->superAdmin = PolicyTestHelper::createUserWithRole($this->tenant1, 'super_admin', [
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
        ]);
        
        $this->orgAdmin = PolicyTestHelper::createUserWithRole($this->tenant1, 'admin', [
            'name' => 'Org Admin',
            'email' => 'orgadmin@test.com',
        ]);
        
        $this->user1 = PolicyTestHelper::createUserWithRole($this->tenant1, 'admin', [
            'name' => 'User 1',
            'email' => 'user1@test.com',
        ]);
        
        $this->user2 = PolicyTestHelper::createUserWithRole($this->tenant1, 'member', [
            'name' => 'User 2',
            'email' => 'invitee@test.com',
        ]);
        
        $this->user3 = PolicyTestHelper::createUserWithRole($this->tenant2, 'admin', [
            'name' => 'User 3',
            'email' => 'user3@test.com',
        ]);
        
        // Create invitations
        $this->invitation1 = Invitation::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'invited_by' => $this->user1->id,
            'email' => 'invitee@test.com',
            'status' => 'pending',
        ]);
        
        $this->invitation2 = Invitation::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'invited_by' => $this->user3->id,
            'email' => 'other@test.com',
            'status' => 'pending',
        ]);
        
        $this->policy = new InvitationPolicy();
    }

    /**
     * Test viewAny policy - super admin can view all
     */
    public function test_view_any_policy_super_admin_can_view_all(): void
    {
        // Super admin has admin.access permission
        // Note: This requires permission setup, so we'll test basic structure
        $this->markTestSkipped('Requires permission setup');
    }

    /**
     * Test viewAny policy - org admin can view their tenant
     */
    public function test_view_any_policy_org_admin_can_view_tenant(): void
    {
        // Org admin has admin.access.tenant permission
        $this->markTestSkipped('Requires permission setup');
    }

    /**
     * Test view policy - super admin can view any
     */
    public function test_view_policy_super_admin_can_view_any(): void
    {
        // Super admin has admin.access permission
        $this->markTestSkipped('Requires permission setup');
    }

    /**
     * Test view policy - inviter can view
     */
    public function test_view_policy_inviter_can_view(): void
    {
        $this->assertTrue($this->policy->view($this->user1, $this->invitation1));
    }

    /**
     * Test view policy - invitee can view
     */
    public function test_view_policy_invitee_can_view(): void
    {
        $this->assertTrue($this->policy->view($this->user2, $this->invitation1));
    }

    /**
     * Test view policy - different tenant cannot view
     */
    public function test_view_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->invitation2));
        $this->assertFalse($this->policy->view($this->user3, $this->invitation1));
    }

    /**
     * Test create policy - super admin can create
     */
    public function test_create_policy_super_admin_can_create(): void
    {
        // Super admin has admin.access permission
        $this->markTestSkipped('Requires permission setup');
    }

    /**
     * Test create policy - org admin can create for their tenant
     */
    public function test_create_policy_org_admin_can_create_for_tenant(): void
    {
        // Org admin has admin.access.tenant permission
        $this->markTestSkipped('Requires permission setup');
    }

    /**
     * Test update policy - inviter can update if not accepted
     */
    public function test_update_policy_inviter_can_update_if_not_accepted(): void
    {
        $this->assertTrue($this->policy->update($this->user1, $this->invitation1));
    }

    /**
     * Test update policy - inviter cannot update if accepted
     */
    public function test_update_policy_inviter_cannot_update_if_accepted(): void
    {
        $this->invitation1->update(['status' => 'accepted']);
        $this->invitation1->refresh();
        $this->assertFalse($this->policy->update($this->user1, $this->invitation1));
    }

    /**
     * Test delete policy - inviter can delete
     */
    public function test_delete_policy_inviter_can_delete(): void
    {
        $this->assertTrue($this->policy->delete($this->user1, $this->invitation1));
    }

    /**
     * Test decline policy - invitee can decline
     */
    public function test_decline_policy_invitee_can_decline(): void
    {
        $this->assertTrue($this->policy->decline($this->user2, $this->invitation1));
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->invitation2));
        $this->assertFalse($this->policy->update($this->user1, $this->invitation2));
        $this->assertFalse($this->policy->delete($this->user1, $this->invitation2));
    }
}

