<?php declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\User;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use App\Policies\AdminProjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected AdminProjectPolicy $policy;
    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected User $superAdmin;
    protected User $orgAdmin;
    protected User $regularUser;
    protected Project $project;
    protected Project $otherProject;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new AdminProjectPolicy();
        
        // Create tenants
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
        
        $projectsReadPerm = Permission::create([
            'code' => 'admin.projects.read',
            'module' => 'admin',
            'action' => 'projects.read',
            'description' => 'Read projects',
        ]);
        
        $projectsForceOpsPerm = Permission::create([
            'code' => 'admin.projects.force_ops',
            'module' => 'admin',
            'action' => 'projects.force_ops',
            'description' => 'Force operations',
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
            $projectsReadPerm->id,
            $projectsForceOpsPerm->id,
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
        
        // Create projects
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        
        $this->otherProject = Project::factory()->create([
            'tenant_id' => $this->otherTenant->id,
        ]);
    }

    public function test_super_admin_can_view_any_projects(): void
    {
        $this->assertTrue($this->policy->viewAny($this->superAdmin));
    }

    public function test_org_admin_can_view_any_projects(): void
    {
        $this->assertTrue($this->policy->viewAny($this->orgAdmin));
    }

    public function test_regular_user_cannot_view_any_projects(): void
    {
        $this->assertFalse($this->policy->viewAny($this->regularUser));
    }

    public function test_super_admin_can_view_any_project(): void
    {
        $this->assertTrue($this->policy->view($this->superAdmin, $this->project));
        $this->assertTrue($this->policy->view($this->superAdmin, $this->otherProject));
    }

    public function test_org_admin_can_view_own_tenant_project(): void
    {
        $this->assertTrue($this->policy->view($this->orgAdmin, $this->project));
    }

    public function test_org_admin_cannot_view_other_tenant_project(): void
    {
        $this->assertFalse($this->policy->view($this->orgAdmin, $this->otherProject));
    }

    public function test_super_admin_can_freeze_any_project(): void
    {
        $this->assertTrue($this->policy->freeze($this->superAdmin, $this->project));
        $this->assertTrue($this->policy->freeze($this->superAdmin, $this->otherProject));
    }

    public function test_org_admin_can_freeze_own_tenant_project(): void
    {
        $this->assertTrue($this->policy->freeze($this->orgAdmin, $this->project));
    }

    public function test_org_admin_cannot_freeze_other_tenant_project(): void
    {
        $this->assertFalse($this->policy->freeze($this->orgAdmin, $this->otherProject));
    }

    public function test_regular_user_cannot_freeze_project(): void
    {
        $this->assertFalse($this->policy->freeze($this->regularUser, $this->project));
    }

    public function test_archive_uses_same_permission_as_freeze(): void
    {
        $this->assertTrue($this->policy->archive($this->superAdmin, $this->project));
        $this->assertTrue($this->policy->archive($this->orgAdmin, $this->project));
        $this->assertFalse($this->policy->archive($this->orgAdmin, $this->otherProject));
        $this->assertFalse($this->policy->archive($this->regularUser, $this->project));
    }

    public function test_emergency_suspend_uses_same_permission_as_freeze(): void
    {
        $this->assertTrue($this->policy->emergencySuspend($this->superAdmin, $this->project));
        $this->assertTrue($this->policy->emergencySuspend($this->orgAdmin, $this->project));
        $this->assertFalse($this->policy->emergencySuspend($this->orgAdmin, $this->otherProject));
        $this->assertFalse($this->policy->emergencySuspend($this->regularUser, $this->project));
    }
}
