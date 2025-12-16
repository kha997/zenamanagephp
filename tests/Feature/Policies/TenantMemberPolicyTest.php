<?php declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantMemberPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected User $orgAdmin;
    protected User $superAdmin;
    protected User $member;
    protected User $otherMember;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();
        
        // Create permissions
        $membersManagePerm = Permission::create([
            'code' => 'admin.members.manage',
            'module' => 'admin',
            'action' => 'members.manage',
            'description' => 'Manage tenant members',
        ]);
        
        $adminAccessTenantPerm = Permission::create([
            'code' => 'admin.access.tenant',
            'module' => 'admin',
            'action' => 'access.tenant',
            'description' => 'Org Admin access',
        ]);
        
        $adminAccessPerm = Permission::create([
            'code' => 'admin.access',
            'module' => 'admin',
            'action' => 'access',
            'description' => 'Super Admin access',
        ]);
        
        // Create roles
        $orgAdminRole = Role::create([
            'name' => 'org_admin',
            'scope' => 'system',
            'is_active' => true,
        ]);
        $orgAdminRole->permissions()->attach([
            $adminAccessTenantPerm->id,
            $membersManagePerm->id,
        ]);
        
        $superAdminRole = Role::create([
            'name' => 'super_admin',
            'scope' => 'system',
            'is_active' => true,
        ]);
        $superAdminRole->permissions()->attach([$adminAccessPerm->id]);
        
        // Create users
        $this->orgAdmin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
        $this->orgAdmin->roles()->attach($orgAdminRole);
        
        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role' => 'super_admin',
        ]);
        $this->superAdmin->roles()->attach($superAdminRole);
        
        $this->member = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);
        
        $this->otherMember = User::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'role' => 'member',
        ]);
    }

    public function test_org_admin_can_view_any_members(): void
    {
        $this->assertTrue($this->orgAdmin->can('admin.members.viewAny'));
    }

    public function test_super_admin_cannot_view_any_members(): void
    {
        $this->assertFalse($this->superAdmin->can('admin.members.viewAny'));
    }

    public function test_org_admin_can_view_member_from_same_tenant(): void
    {
        $this->assertTrue($this->orgAdmin->can('admin.members.view', $this->member));
    }

    public function test_org_admin_cannot_view_member_from_different_tenant(): void
    {
        $this->assertFalse($this->orgAdmin->can('admin.members.view', $this->otherMember));
    }

    public function test_org_admin_can_invite_member(): void
    {
        $this->assertTrue($this->orgAdmin->can('admin.members.invite'));
    }

    public function test_super_admin_cannot_invite_member(): void
    {
        $this->assertFalse($this->superAdmin->can('admin.members.invite'));
    }

    public function test_org_admin_can_update_role_of_member_from_same_tenant(): void
    {
        $this->assertTrue($this->orgAdmin->can('admin.members.updateRole', $this->member));
    }

    public function test_org_admin_cannot_update_role_of_member_from_different_tenant(): void
    {
        $this->assertFalse($this->orgAdmin->can('admin.members.updateRole', $this->otherMember));
    }

    public function test_org_admin_cannot_update_own_role(): void
    {
        $this->assertFalse($this->orgAdmin->can('admin.members.updateRole', $this->orgAdmin));
    }

    public function test_org_admin_can_remove_member_from_same_tenant(): void
    {
        $this->assertTrue($this->orgAdmin->can('admin.members.remove', $this->member));
    }

    public function test_org_admin_cannot_remove_member_from_different_tenant(): void
    {
        $this->assertFalse($this->orgAdmin->can('admin.members.remove', $this->otherMember));
    }

    public function test_org_admin_cannot_remove_self(): void
    {
        $this->assertFalse($this->orgAdmin->can('admin.members.remove', $this->orgAdmin));
    }
}

