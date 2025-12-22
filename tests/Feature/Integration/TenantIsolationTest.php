<?php declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\User;
use App\Models\Project;
use App\Models\TemplateSet;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $orgAdminA;
    protected User $orgAdminB;
    protected Project $projectA;
    protected Project $projectB;
    protected TemplateSet $templateA;
    protected TemplateSet $templateB;
    protected AuditLog $activityA;
    protected AuditLog $activityB;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        
        // Create permissions and roles
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
        
        $templatesManagePerm = Permission::create([
            'code' => 'admin.templates.manage',
            'module' => 'admin',
            'action' => 'templates.manage',
            'description' => 'Manage templates',
        ]);
        
        $analyticsTenantPerm = Permission::create([
            'code' => 'admin.analytics.tenant',
            'module' => 'admin',
            'action' => 'analytics.tenant',
            'description' => 'Tenant analytics',
        ]);
        
        $activitiesTenantPerm = Permission::create([
            'code' => 'admin.activities.tenant',
            'module' => 'admin',
            'action' => 'activities.tenant',
            'description' => 'Tenant activities',
        ]);
        
        $orgAdminRole = Role::create([
            'name' => 'org_admin',
            'scope' => 'system',
            'is_active' => true,
        ]);
        $orgAdminRole->permissions()->attach([
            $adminAccessTenantPerm->id,
            $projectsReadPerm->id,
            $templatesManagePerm->id,
            $analyticsTenantPerm->id,
            $activitiesTenantPerm->id,
        ]);
        
        $this->orgAdminA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);
        $this->orgAdminA->roles()->attach($orgAdminRole);
        
        $this->orgAdminB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);
        $this->orgAdminB->roles()->attach($orgAdminRole);
        
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);
        
        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);
        
        $this->templateA = TemplateSet::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);
        
        $this->templateB = TemplateSet::factory()->create([
            'tenant_id' => $this->tenantB->id,
        ]);
        
        $this->activityA = AuditLog::create([
            'user_id' => $this->orgAdminA->id,
            'action' => 'project.created',
            'entity_type' => 'Project',
            'tenant_id' => $this->tenantA->id,
        ]);
        
        $this->activityB = AuditLog::create([
            'user_id' => $this->orgAdminB->id,
            'action' => 'project.created',
            'entity_type' => 'Project',
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    public function test_org_admin_a_cannot_see_tenant_b_projects(): void
    {
        $this->actingAs($this->orgAdminA);
        
        $response = $this->get('/admin/projects');
        
        $response->assertStatus(200);
        $response->assertSee($this->projectA->name);
        $response->assertDontSee($this->projectB->name);
    }

    public function test_org_admin_b_cannot_see_tenant_a_projects(): void
    {
        $this->actingAs($this->orgAdminB);
        
        $response = $this->get('/admin/projects');
        
        $response->assertStatus(200);
        $response->assertSee($this->projectB->name);
        $response->assertDontSee($this->projectA->name);
    }

    public function test_org_admin_a_cannot_access_tenant_b_project(): void
    {
        $this->actingAs($this->orgAdminA);
        
        $response = $this->get("/admin/projects/{$this->projectB->id}");
        
        $response->assertStatus(404); // Not found due to tenant scope
    }

    public function test_org_admin_a_cannot_see_tenant_b_templates(): void
    {
        $this->actingAs($this->orgAdminA);
        
        $response = $this->get('/admin/templates');
        
        $response->assertStatus(200);
        $response->assertSee($this->templateA->name);
        $response->assertDontSee($this->templateB->name);
    }

    public function test_org_admin_a_cannot_see_tenant_b_activities(): void
    {
        $this->actingAs($this->orgAdminA);
        
        $response = $this->get('/admin/activities');
        
        $response->assertStatus(200);
        // Should only see activities from tenant A
    }

    public function test_org_admin_a_cannot_freeze_tenant_b_project(): void
    {
        $this->actingAs($this->orgAdminA);
        
        $response = $this->postJson("/admin/projects/{$this->projectB->id}/freeze", [
            'reason' => 'Test',
        ]);
        
        $response->assertStatus(404); // Not found due to tenant scope
    }
}
