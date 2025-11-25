<?php declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $superAdmin;
    protected User $orgAdmin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        
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
        
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->roles()->attach($superAdminRole);
        
        $this->orgAdmin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->orgAdmin->roles()->attach($orgAdminRole);
        
        $this->regularUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_super_admin_can_access_all_admin_routes(): void
    {
        $this->actingAs($this->superAdmin);
        
        $routes = [
            '/admin/dashboard',
            '/admin/projects',
            '/admin/templates',
            '/admin/analytics',
            '/admin/activities',
            '/admin/settings',
            '/admin/users',
            '/admin/tenants',
            '/admin/security',
            '/admin/maintenance',
        ];
        
        foreach ($routes as $route) {
            $response = $this->get($route);
            $this->assertNotEquals(403, $response->status(), "Super Admin should access {$route}");
        }
    }

    public function test_org_admin_can_access_tenant_admin_routes(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $allowedRoutes = [
            '/admin/dashboard',
            '/admin/projects',
            '/admin/templates',
            '/admin/analytics',
            '/admin/activities',
            '/admin/settings',
        ];
        
        foreach ($allowedRoutes as $route) {
            $response = $this->get($route);
            $this->assertNotEquals(403, $response->status(), "Org Admin should access {$route}");
        }
    }

    public function test_org_admin_cannot_access_system_routes(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $blockedRoutes = [
            '/admin/users',
            '/admin/tenants',
            '/admin/security',
            '/admin/maintenance',
        ];
        
        foreach ($blockedRoutes as $route) {
            $response = $this->get($route);
            $this->assertEquals(403, $response->status(), "Org Admin should be blocked from {$route}");
        }
    }

    public function test_regular_user_cannot_access_any_admin_routes(): void
    {
        $this->actingAs($this->regularUser);
        
        $routes = [
            '/admin/dashboard',
            '/admin/projects',
            '/admin/templates',
            '/admin/analytics',
            '/admin/activities',
            '/admin/settings',
            '/admin/users',
            '/admin/tenants',
        ];
        
        foreach ($routes as $route) {
            $response = $this->get($route);
            $this->assertEquals(403, $response->status(), "Regular user should be blocked from {$route}");
        }
    }
}
