<?php declare(strict_types=1);

namespace Tests\Feature\Feature\Admin;

use App\Models\User;
use App\Models\TemplateSet;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $superAdmin;
    protected User $orgAdmin;
    protected User $regularUser;
    protected TemplateSet $globalTemplate;
    protected TemplateSet $tenantTemplate;

    protected function setUp(): void
    {
        parent::setUp();
        
        config(['features.tasks.enable_wbs_templates' => true]);
        
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
        
        $this->globalTemplate = TemplateSet::factory()->create([
            'tenant_id' => null,
            'is_global' => true,
        ]);
        
        $this->tenantTemplate = TemplateSet::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_global' => false,
        ]);
    }

    public function test_super_admin_can_access_templates_index(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->get('/admin/templates');
        
        $response->assertStatus(200);
    }

    public function test_org_admin_can_access_templates_index(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->get('/admin/templates');
        
        $response->assertStatus(200);
    }

    public function test_super_admin_sees_all_templates(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->get('/admin/templates');
        
        $response->assertStatus(200);
        $response->assertSee($this->globalTemplate->name);
        $response->assertSee($this->tenantTemplate->name);
    }

    public function test_org_admin_sees_global_and_own_tenant_templates(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->get('/admin/templates');
        
        $response->assertStatus(200);
        $response->assertSee($this->globalTemplate->name);
        $response->assertSee($this->tenantTemplate->name);
    }

    public function test_super_admin_can_create_global_template(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->postJson('/admin/templates', [
            'code' => 'GLOBAL-TEST',
            'name' => 'Global Test Template',
            'version' => '1.0',
            'tenant_id' => null,
        ]);
        
        $response->assertStatus(201);
        $this->assertDatabaseHas('template_sets', [
            'code' => 'GLOBAL-TEST',
            'tenant_id' => null,
        ]);
    }

    public function test_org_admin_can_create_tenant_template(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->postJson('/admin/templates', [
            'code' => 'TENANT-TEST',
            'name' => 'Tenant Test Template',
            'version' => '1.0',
            'tenant_id' => $this->tenant->id,
        ]);
        
        $response->assertStatus(201);
        $this->assertDatabaseHas('template_sets', [
            'code' => 'TENANT-TEST',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_org_admin_cannot_create_global_template(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->postJson('/admin/templates', [
            'code' => 'GLOBAL-TEST',
            'name' => 'Global Test Template',
            'version' => '1.0',
            'tenant_id' => null,
        ]);
        
        $response->assertStatus(403);
    }
}
