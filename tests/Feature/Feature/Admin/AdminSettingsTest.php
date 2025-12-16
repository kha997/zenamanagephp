<?php declare(strict_types=1);

namespace Tests\Feature\Feature\Admin;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
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
        
        $settingsTenantPerm = Permission::create([
            'code' => 'admin.settings.tenant',
            'module' => 'admin',
            'action' => 'settings.tenant',
            'description' => 'Tenant settings',
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
        $orgAdminRole->permissions()->attach([$settingsTenantPerm->id]);
        
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

    public function test_super_admin_can_access_settings(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->get('/admin/settings');
        
        $response->assertStatus(200);
    }

    public function test_org_admin_can_access_settings(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->get('/admin/settings');
        
        $response->assertStatus(200);
    }

    public function test_regular_user_cannot_access_settings(): void
    {
        $this->actingAs($this->regularUser);
        
        $response = $this->get('/admin/settings');
        
        $response->assertStatus(403);
    }

    public function test_super_admin_can_see_system_settings_tab(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->get('/admin/settings');
        
        $response->assertStatus(200);
        $response->assertSee('System Settings');
    }

    public function test_org_admin_cannot_see_system_settings_tab(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->get('/admin/settings');
        
        $response->assertStatus(200);
        $response->assertDontSee('System Settings');
    }

    public function test_super_admin_can_update_system_settings(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->postJson('/admin/settings/system', [
            'maintenance_mode' => true,
        ]);
        
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_org_admin_cannot_update_system_settings(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->postJson('/admin/settings/system', [
            'maintenance_mode' => true,
        ]);
        
        $response->assertStatus(403);
    }

    public function test_org_admin_can_update_tenant_settings(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->postJson('/admin/settings/tenant', [
            'tenant_id' => $this->tenant->id,
            'branding' => ['company_name' => 'Test Company'],
        ]);
        
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
