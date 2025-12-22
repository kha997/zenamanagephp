<?php declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create permissions
        $this->adminAccessTenantPerm = Permission::create([
            'code' => 'admin.access.tenant',
            'module' => 'admin',
            'action' => 'access.tenant',
            'description' => 'Access admin panel with tenant scope',
        ]);
        
        // Create org_admin role
        $this->orgAdminRole = Role::create([
            'name' => 'org_admin',
            'scope' => 'system',
            'description' => 'Organization Admin',
            'is_active' => true,
        ]);
        
        $this->orgAdminRole->permissions()->attach($this->adminAccessTenantPerm);
        
        // Create super_admin role
        $this->superAdminRole = Role::create([
            'name' => 'super_admin',
            'scope' => 'system',
            'description' => 'Super Administrator',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_access_admin_routes(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach($this->superAdminRole);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin/dashboard');
        
        // Should not be blocked (may return 200 or 404 if route doesn't exist, but not 403)
        $this->assertNotEquals(403, $response->status());
    }

    public function test_org_admin_can_access_admin_routes(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $user->roles()->attach($this->orgAdminRole);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin/dashboard');
        
        // Should not be blocked
        $this->assertNotEquals(403, $response->status());
    }

    public function test_org_admin_without_tenant_id_is_blocked(): void
    {
        $user = User::factory()->create([
            'tenant_id' => null,
        ]);
        $user->roles()->attach($this->orgAdminRole);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin/dashboard');
        
        // Should be blocked because org admin needs tenant_id
        $this->assertEquals(403, $response->status());
    }

    public function test_regular_user_is_blocked(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin/dashboard');
        
        // Should be blocked
        $this->assertEquals(403, $response->status());
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get('/admin/dashboard');
        
        // Should redirect to login
        $response->assertRedirect('/login');
    }

    public function test_inactive_user_is_blocked(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);
        $user->roles()->attach($this->orgAdminRole);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin/dashboard');
        
        // Should be blocked
        $this->assertEquals(403, $response->status());
    }
}
