<?php declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Tenant;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User Policy Test
 * 
 * Unit tests for the UserPolicy class.
 */
class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UserPolicy $userPolicy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userPolicy = new UserPolicy();
    }

    /**
     * Test super admin can view any user
     */
    public function test_super_admin_can_view_any_user()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $superAdmin = User::factory()->create([
            'tenant_id' => $tenant1->id,
            'role' => 'super_admin',
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant2->id,
            'role' => 'member',
        ]);

        $this->assertTrue($this->userPolicy->view($superAdmin, $user));
    }

    /**
     * Test user can view themselves
     */
    public function test_user_can_view_themselves()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->assertTrue($this->userPolicy->view($user, $user));
    }

    /**
     * Test user cannot view user from different tenant
     */
    public function test_user_cannot_view_user_from_different_tenant()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $user1 = User::factory()->create([
            'tenant_id' => $tenant1->id,
            'role' => 'admin',
        ]);
        
        $user2 = User::factory()->create([
            'tenant_id' => $tenant2->id,
            'role' => 'member',
        ]);

        $this->assertFalse($this->userPolicy->view($user1, $user2));
    }

    /**
     * Test admin can view users in same tenant
     */
    public function test_admin_can_view_users_in_same_tenant()
    {
        $tenant = Tenant::factory()->create();
        
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->assertTrue($this->userPolicy->view($admin, $user));
    }

    /**
     * Test super admin can create users
     */
    public function test_super_admin_can_create_users()
    {
        $tenant = Tenant::factory()->create();
        $superAdmin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'super_admin',
        ]);

        $this->assertTrue($this->userPolicy->create($superAdmin));
    }

    /**
     * Test admin can create users
     */
    public function test_admin_can_create_users()
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $this->assertTrue($this->userPolicy->create($admin));
    }

    /**
     * Test member cannot create users
     */
    public function test_member_cannot_create_users()
    {
        $tenant = Tenant::factory()->create();
        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->assertFalse($this->userPolicy->create($member));
    }

    /**
     * Test super admin can update any user
     */
    public function test_super_admin_can_update_any_user()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $superAdmin = User::factory()->create([
            'tenant_id' => $tenant1->id,
            'role' => 'super_admin',
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant2->id,
            'role' => 'member',
        ]);

        $this->assertTrue($this->userPolicy->update($superAdmin, $user));
    }

    /**
     * Test user can update themselves
     */
    public function test_user_can_update_themselves()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->assertTrue($this->userPolicy->update($user, $user));
    }

    /**
     * Test user cannot update user from different tenant
     */
    public function test_user_cannot_update_user_from_different_tenant()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $user1 = User::factory()->create([
            'tenant_id' => $tenant1->id,
            'role' => 'admin',
        ]);
        
        $user2 = User::factory()->create([
            'tenant_id' => $tenant2->id,
            'role' => 'member',
        ]);

        $this->assertFalse($this->userPolicy->update($user1, $user2));
    }

    /**
     * Test super admin can delete any user
     */
    public function test_super_admin_can_delete_any_user()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $superAdmin = User::factory()->create([
            'tenant_id' => $tenant1->id,
            'role' => 'super_admin',
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant2->id,
            'role' => 'member',
        ]);

        $this->assertTrue($this->userPolicy->delete($superAdmin, $user));
    }

    /**
     * Test user cannot delete themselves
     */
    public function test_user_cannot_delete_themselves()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $this->assertFalse($this->userPolicy->delete($user, $user));
    }

    /**
     * Test admin can delete users in same tenant
     */
    public function test_admin_can_delete_users_in_same_tenant()
    {
        $tenant = Tenant::factory()->create();
        
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->assertTrue($this->userPolicy->delete($admin, $user));
    }

    /**
     * Test super admin can manage roles
     */
    public function test_super_admin_can_manage_roles()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $superAdmin = User::factory()->create([
            'tenant_id' => $tenant1->id,
            'role' => 'super_admin',
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant2->id,
            'role' => 'member',
        ]);

        $this->assertTrue($this->userPolicy->manageRoles($superAdmin, $user));
    }

    /**
     * Test user cannot manage their own role
     */
    public function test_user_cannot_manage_their_own_role()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $this->assertFalse($this->userPolicy->manageRoles($user, $user));
    }

    /**
     * Test admin can manage roles in same tenant
     */
    public function test_admin_can_manage_roles_in_same_tenant()
    {
        $tenant = Tenant::factory()->create();
        
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->assertTrue($this->userPolicy->manageRoles($admin, $user));
    }

    /**
     * Test only super admin can force delete
     */
    public function test_only_super_admin_can_force_delete()
    {
        $tenant = Tenant::factory()->create();
        
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->assertFalse($this->userPolicy->forceDelete($admin, $user));
        
        $superAdmin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'super_admin',
        ]);

        $this->assertTrue($this->userPolicy->forceDelete($superAdmin, $user));
    }
}
