<?php declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use App\Policies\AdminSettingsPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected AdminSettingsPolicy $policy;
    protected Tenant $tenant;
    protected User $superAdmin;
    protected User $orgAdmin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new AdminSettingsPolicy();
        
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
        
        $settingsTenantPerm = Permission::create([
            'code' => 'admin.settings.tenant',
            'module' => 'admin',
            'action' => 'settings.tenant',
            'description' => 'Tenant settings',
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
        $orgAdminRole->permissions()->attach([
            $adminAccessTenantPerm->id,
            $settingsTenantPerm->id,
        ]);
        
        // Create users
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

    public function test_super_admin_can_view_system_settings(): void
    {
        $this->assertTrue($this->policy->viewSystemSettings($this->superAdmin));
    }

    public function test_org_admin_cannot_view_system_settings(): void
    {
        $this->assertFalse($this->policy->viewSystemSettings($this->orgAdmin));
    }

    public function test_regular_user_cannot_view_system_settings(): void
    {
        $this->assertFalse($this->policy->viewSystemSettings($this->regularUser));
    }

    public function test_super_admin_can_update_system_settings(): void
    {
        $this->assertTrue($this->policy->updateSystemSettings($this->superAdmin));
    }

    public function test_org_admin_cannot_update_system_settings(): void
    {
        $this->assertFalse($this->policy->updateSystemSettings($this->orgAdmin));
    }

    public function test_super_admin_can_view_tenant_settings(): void
    {
        $this->assertTrue($this->policy->viewTenantSettings($this->superAdmin));
    }

    public function test_org_admin_can_view_tenant_settings(): void
    {
        $this->assertTrue($this->policy->viewTenantSettings($this->orgAdmin));
    }

    public function test_regular_user_cannot_view_tenant_settings(): void
    {
        $this->assertFalse($this->policy->viewTenantSettings($this->regularUser));
    }

    public function test_super_admin_can_update_any_tenant_settings(): void
    {
        $this->assertTrue($this->policy->updateTenantSettings($this->superAdmin, $this->tenant->id));
        
        $otherTenant = Tenant::factory()->create();
        $this->assertTrue($this->policy->updateTenantSettings($this->superAdmin, $otherTenant->id));
    }

    public function test_org_admin_can_update_own_tenant_settings(): void
    {
        $this->assertTrue($this->policy->updateTenantSettings($this->orgAdmin, $this->tenant->id));
        $this->assertTrue($this->policy->updateTenantSettings($this->orgAdmin, null));
    }

    public function test_org_admin_cannot_update_other_tenant_settings(): void
    {
        $otherTenant = Tenant::factory()->create();
        $this->assertFalse($this->policy->updateTenantSettings($this->orgAdmin, $otherTenant->id));
    }
}
