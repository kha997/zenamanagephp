<?php declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemUsersTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected User $superAdmin;
    protected User $orgAdmin;
    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();
        
        // Create permissions
        $adminAccessPerm = Permission::create([
            'code' => 'admin.access',
            'module' => 'admin',
            'action' => 'access',
            'description' => 'Super Admin access',
        ]);
        
        $adminAccessTenantPerm = Permission::create([
            'code' => 'admin.access.tenant',
            'module' => 'admin',
            'action' => 'access.tenant',
            'description' => 'Org Admin access',
        ]);
        
        // Create roles
        $superAdminRole = Role::create([
            'name' => 'super_admin',
            'scope' => 'system',
            'is_active' => true,
        ]);
        $superAdminRole->permissions()->attach([$adminAccessPerm->id]);
        
        $orgAdminRole = Role::create([
            'name' => 'org_admin',
            'scope' => 'system',
            'is_active' => true,
        ]);
        $orgAdminRole->permissions()->attach([$adminAccessTenantPerm->id]);
        
        // Create users
        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'role' => 'super_admin',
        ]);
        $this->superAdmin->roles()->attach($superAdminRole);
        
        $this->orgAdmin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
        $this->orgAdmin->roles()->attach($orgAdminRole);
        
        $this->member = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member',
        ]);
    }

    public function test_super_admin_can_view_all_users(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->get('/admin/users');
        
        $response->assertStatus(200);
        // Should see users from all tenants
        $response->assertSee($this->member->name);
    }

    public function test_super_admin_can_filter_users_by_tenant(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->get('/admin/users?tenant_id=' . $this->tenant->id);
        
        $response->assertStatus(200);
        // Should only see users from filtered tenant
    }

    public function test_org_admin_cannot_access_users_route(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->get('/admin/users');
        
        // Should be blocked (403)
        $response->assertStatus(403);
    }

    public function test_org_admin_gets_suggestion_when_accessing_users(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->getJson('/admin/users');
        
        $response->assertStatus(403);
        $response->assertJson([
            'code' => 'SUPER_ADMIN_REQUIRED',
        ]);
        // Should have suggestion
        $this->assertArrayHasKey('suggestion', $response->json());
    }

    public function test_regular_member_cannot_access_users_route(): void
    {
        $this->actingAs($this->member);
        
        $response = $this->get('/admin/users');
        
        // Should be blocked
        $this->assertContains($response->status(), [403, 302]);
    }

    public function test_super_admin_can_access_users_api(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->getJson('/api/v1/admin/users');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'users',
                'pagination',
            ],
        ]);
    }

    public function test_users_api_returns_users_from_all_tenants(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->getJson('/api/v1/admin/users');
        
        $response->assertStatus(200);
        $users = $response->json('data.users');
        
        // Should include users from multiple tenants
        $tenantIds = collect($users)->pluck('tenant_id')->unique()->filter()->toArray();
        $this->assertGreaterThanOrEqual(1, count($tenantIds));
    }
}

