<?php declare(strict_types=1);

namespace Tests\Feature\Feature\Api;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NavigationApiTest extends TestCase
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
        
        // Create permissions and roles
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
        
        $templatesManagePerm = Permission::create([
            'code' => 'admin.templates.manage',
            'module' => 'admin',
            'action' => 'templates.manage',
            'description' => 'Manage templates',
        ]);
        
        $projectsReadPerm = Permission::create([
            'code' => 'admin.projects.read',
            'module' => 'admin',
            'action' => 'projects.read',
            'description' => 'Read projects',
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
        $orgAdminRole->permissions()->attach([
            $adminAccessTenantPerm->id,
            $templatesManagePerm->id,
            $projectsReadPerm->id,
        ]);
        
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

    public function test_super_admin_navigation_includes_system_items(): void
    {
        Sanctum::actingAs($this->superAdmin);
        
        $response = $this->getJson('/api/v1/me/nav');
        
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertArrayHasKey('navigation', $data);
        $this->assertArrayHasKey('admin_access', $data);
        $this->assertTrue($data['admin_access']['is_super_admin']);
        
        $navItems = collect($data['navigation']);
        $this->assertTrue($navItems->contains('path', '/admin/users'));
        $this->assertTrue($navItems->contains('path', '/admin/tenants'));
    }

    public function test_org_admin_navigation_excludes_system_items(): void
    {
        Sanctum::actingAs($this->orgAdmin);
        
        $response = $this->getJson('/api/v1/me/nav');
        
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertArrayHasKey('navigation', $data);
        $this->assertArrayHasKey('admin_access', $data);
        $this->assertTrue($data['admin_access']['is_org_admin']);
        
        $navItems = collect($data['navigation']);
        $this->assertFalse($navItems->contains('path', '/admin/users'));
        $this->assertFalse($navItems->contains('path', '/admin/tenants'));
        $this->assertTrue($navItems->contains('path', '/admin/templates'));
        $this->assertTrue($navItems->contains('path', '/admin/projects'));
    }

    public function test_regular_user_navigation_excludes_admin_items(): void
    {
        Sanctum::actingAs($this->regularUser);
        
        $response = $this->getJson('/api/v1/me/nav');
        
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertArrayHasKey('navigation', $data);
        
        $navItems = collect($data['navigation']);
        $this->assertFalse($navItems->contains('path', '/admin/dashboard'));
        $this->assertFalse($navItems->contains('path', '/admin/templates'));
    }
}
