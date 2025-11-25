<?php declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantMembersTest extends TestCase
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
        $adminAccessTenantPerm = Permission::create([
            'code' => 'admin.access.tenant',
            'module' => 'admin',
            'action' => 'access.tenant',
            'description' => 'Org Admin access',
        ]);
        
        $membersManagePerm = Permission::create([
            'code' => 'admin.members.manage',
            'module' => 'admin',
            'action' => 'members.manage',
            'description' => 'Manage tenant members',
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

    public function test_org_admin_can_view_members_of_own_tenant(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->get('/admin/members');
        
        $response->assertStatus(200);
        $response->assertSee($this->member->name);
        $response->assertSee($this->member->email);
    }

    public function test_org_admin_cannot_view_members_of_other_tenant(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->get('/admin/members');
        
        $response->assertStatus(200);
        $response->assertDontSee($this->otherMember->name);
        $response->assertDontSee($this->otherMember->email);
    }

    public function test_super_admin_cannot_access_members_route(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->get('/admin/members');
        
        // Should be blocked (403) or redirect
        $this->assertContains($response->status(), [403, 302]);
    }

    public function test_regular_member_cannot_access_members_route(): void
    {
        $this->actingAs($this->member);
        
        $response = $this->get('/admin/members');
        
        // Should be blocked
        $this->assertContains($response->status(), [403, 302]);
    }

    public function test_org_admin_can_access_members_api(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->getJson('/api/v1/admin/members');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'users',
                'pagination',
            ],
        ]);
    }

    public function test_members_api_only_returns_users_from_same_tenant(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->getJson('/api/v1/admin/members');
        
        $response->assertStatus(200);
        $users = $response->json('data.users');
        
        // All users should be from the same tenant
        foreach ($users as $user) {
            $this->assertEquals($this->tenant->id, $user['tenant_id']);
        }
        
        // Should not include other tenant's members
        $userIds = collect($users)->pluck('id')->toArray();
        $this->assertNotContains($this->otherMember->id, $userIds);
    }
}

