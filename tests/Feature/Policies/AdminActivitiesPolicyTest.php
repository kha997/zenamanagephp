<?php declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use App\Policies\AdminActivitiesPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActivitiesPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected AdminActivitiesPolicy $policy;
    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected User $superAdmin;
    protected User $orgAdmin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new AdminActivitiesPolicy();
        
        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();
        
        // Create permissions
        $adminAccessPerm = Permission::create([
            'code' => 'admin.access',
            'module' => 'admin',
            'action' => 'access',
            'description' => 'Super Admin access',
        ]);
        
        $activitiesTenantPerm = Permission::create([
            'code' => 'admin.activities.tenant',
            'module' => 'admin',
            'action' => 'activities.tenant',
            'description' => 'Tenant activities',
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
        $orgAdminRole->permissions()->attach([$activitiesTenantPerm->id]);
        
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

    public function test_super_admin_can_view_activities(): void
    {
        $this->assertTrue($this->policy->viewAny($this->superAdmin));
    }

    public function test_org_admin_can_view_activities(): void
    {
        $this->assertTrue($this->policy->viewAny($this->orgAdmin));
    }

    public function test_regular_user_cannot_view_activities(): void
    {
        $this->assertFalse($this->policy->viewAny($this->regularUser));
    }

    public function test_super_admin_can_view_any_tenant_activities(): void
    {
        $this->assertTrue($this->policy->view($this->superAdmin, $this->tenant->id));
        $this->assertTrue($this->policy->view($this->superAdmin, $this->otherTenant->id));
        $this->assertTrue($this->policy->view($this->superAdmin, null));
    }

    public function test_org_admin_can_view_own_tenant_activities(): void
    {
        $this->assertTrue($this->policy->view($this->orgAdmin, $this->tenant->id));
        $this->assertTrue($this->policy->view($this->orgAdmin, null));
    }

    public function test_org_admin_cannot_view_other_tenant_activities(): void
    {
        $this->assertFalse($this->policy->view($this->orgAdmin, $this->otherTenant->id));
    }
}
