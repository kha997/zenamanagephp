<?php

namespace Tests\Feature\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrganizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $admin;
    protected $pm;
    protected $designer;
    protected $engineer;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with different roles
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'organization_id' => 1]);
        $this->admin = User::factory()->create(['role' => 'admin', 'organization_id' => 1]);
        $this->pm = User::factory()->create(['role' => 'pm', 'organization_id' => 1]);
        $this->designer = User::factory()->create(['role' => 'designer', 'organization_id' => 1]);
        $this->engineer = User::factory()->create(['role' => 'engineer', 'organization_id' => 1]);
        $this->regularUser = User::factory()->create(['role' => 'user', 'organization_id' => 1]);
    }

    /** @test */
    public function organization_policy_allows_proper_access()
    {
        $organization = Organization::factory()->create(['id' => 1]);
        
        // Super admin can do everything
        $this->assertTrue($this->superAdmin->can('view', $organization));
        $this->assertTrue($this->superAdmin->can('create', Organization::class));
        $this->assertTrue($this->superAdmin->can('update', $organization));
        $this->assertTrue($this->superAdmin->can('delete', $organization));
        
        // Admin can do most things
        $this->assertTrue($this->admin->can('view', $organization));
        $this->assertFalse($this->admin->can('create', Organization::class));
        $this->assertTrue($this->admin->can('update', $organization));
        $this->assertFalse($this->admin->can('delete', $organization));
        
        // PM cannot access organizations
        $this->assertFalse($this->pm->can('view', $organization));
        $this->assertFalse($this->pm->can('create', Organization::class));
        $this->assertFalse($this->pm->can('update', $organization));
        $this->assertFalse($this->pm->can('delete', $organization));
        
        // Designer cannot access organizations
        $this->assertFalse($this->designer->can('view', $organization));
        $this->assertFalse($this->designer->can('create', Organization::class));
        $this->assertFalse($this->designer->can('update', $organization));
        $this->assertFalse($this->designer->can('delete', $organization));
        
        // Engineer cannot access organizations
        $this->assertFalse($this->engineer->can('view', $organization));
        $this->assertFalse($this->engineer->can('create', Organization::class));
        $this->assertFalse($this->engineer->can('update', $organization));
        $this->assertFalse($this->engineer->can('delete', $organization));
        
        // Regular user cannot access
        $this->assertFalse($this->regularUser->can('view', $organization));
        $this->assertFalse($this->regularUser->can('create', Organization::class));
        $this->assertFalse($this->regularUser->can('update', $organization));
        $this->assertFalse($this->regularUser->can('delete', $organization));
    }

    /** @test */
    public function users_can_view_their_own_organization()
    {
        $organization = Organization::factory()->create(['id' => 1]);
        
        // Users can view their own organization
        $this->assertTrue($this->admin->can('view', $organization));
        $this->assertTrue($this->pm->can('view', $organization));
        $this->assertTrue($this->designer->can('view', $organization));
        $this->assertTrue($this->engineer->can('view', $organization));
        $this->assertTrue($this->regularUser->can('view', $organization));
    }

    /** @test */
    public function organization_admins_can_manage_their_organization()
    {
        $organization = Organization::factory()->create(['id' => 1]);
        
        // Organization admin can manage their organization
        $this->assertTrue($this->admin->can('manageSettings', $organization));
        $this->assertTrue($this->admin->can('inviteUsers', $organization));
        $this->assertTrue($this->admin->can('manageBilling', $organization));
        
        // Non-admin cannot manage
        $this->assertFalse($this->pm->can('manageSettings', $organization));
        $this->assertFalse($this->pm->can('inviteUsers', $organization));
        $this->assertFalse($this->pm->can('manageBilling', $organization));
    }

    /** @test */
    public function super_admin_can_manage_all_organizations()
    {
        $organization1 = Organization::factory()->create(['id' => 1]);
        $organization2 = Organization::factory()->create(['id' => 2]);
        
        // Super admin can manage all organizations
        $this->assertTrue($this->superAdmin->can('view', $organization1));
        $this->assertTrue($this->superAdmin->can('view', $organization2));
        $this->assertTrue($this->superAdmin->can('update', $organization1));
        $this->assertTrue($this->superAdmin->can('update', $organization2));
        $this->assertTrue($this->superAdmin->can('delete', $organization1));
        $this->assertTrue($this->superAdmin->can('delete', $organization2));
        $this->assertTrue($this->superAdmin->can('manageSettings', $organization1));
        $this->assertTrue($this->superAdmin->can('manageSettings', $organization2));
    }

    /** @test */
    public function organization_isolation_prevents_cross_organization_access()
    {
        $organization1 = Organization::factory()->create(['id' => 1]);
        $organization2 = Organization::factory()->create(['id' => 2]);
        
        $user1 = User::factory()->create(['organization_id' => 1, 'role' => 'admin']);
        $user2 = User::factory()->create(['organization_id' => 2, 'role' => 'admin']);
        
        // User 1 can access organization 1
        $this->assertTrue($user1->can('view', $organization1));
        $this->assertTrue($user1->can('update', $organization1));
        
        // User 1 cannot access organization 2
        $this->assertFalse($user1->can('view', $organization2));
        $this->assertFalse($user1->can('update', $organization2));
        
        // User 2 can access organization 2
        $this->assertTrue($user2->can('view', $organization2));
        $this->assertTrue($user2->can('update', $organization2));
        
        // User 2 cannot access organization 1
        $this->assertFalse($user2->can('view', $organization1));
        $this->assertFalse($user2->can('update', $organization1));
    }

    /** @test */
    public function only_super_admin_can_create_organizations()
    {
        // Only super admin can create organizations
        $this->assertTrue($this->superAdmin->can('create', Organization::class));
        
        // Others cannot create organizations
        $this->assertFalse($this->admin->can('create', Organization::class));
        $this->assertFalse($this->pm->can('create', Organization::class));
        $this->assertFalse($this->designer->can('create', Organization::class));
        $this->assertFalse($this->engineer->can('create', Organization::class));
        $this->assertFalse($this->regularUser->can('create', Organization::class));
    }

    /** @test */
    public function only_super_admin_can_delete_organizations()
    {
        $organization = Organization::factory()->create(['id' => 1]);
        
        // Only super admin can delete organizations
        $this->assertTrue($this->superAdmin->can('delete', $organization));
        
        // Others cannot delete organizations
        $this->assertFalse($this->admin->can('delete', $organization));
        $this->assertFalse($this->pm->can('delete', $organization));
        $this->assertFalse($this->designer->can('delete', $organization));
        $this->assertFalse($this->engineer->can('delete', $organization));
        $this->assertFalse($this->regularUser->can('delete', $organization));
    }
}
