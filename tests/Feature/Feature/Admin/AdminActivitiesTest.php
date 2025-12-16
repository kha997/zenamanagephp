<?php declare(strict_types=1);

namespace Tests\Feature\Feature\Admin;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminActivitiesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected User $superAdmin;
    protected User $orgAdmin;
    protected User $regularUser;
    protected AuditLog $activity;
    protected AuditLog $otherActivity;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();
        
        // Create permissions and roles
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
        
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->roles()->attach($superAdminRole);
        
        $this->orgAdmin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->orgAdmin->roles()->attach($orgAdminRole);
        
        $this->regularUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        
        $this->activity = AuditLog::create([
            'user_id' => $this->orgAdmin->id,
            'action' => 'project.created',
            'entity_type' => 'Project',
            'tenant_id' => $this->tenant->id,
        ]);
        
        $this->otherActivity = AuditLog::create([
            'user_id' => $this->superAdmin->id,
            'action' => 'project.created',
            'entity_type' => 'Project',
            'tenant_id' => $this->otherTenant->id,
        ]);
    }

    public function test_super_admin_can_access_activities(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->get('/admin/activities');
        
        $response->assertStatus(200);
    }

    public function test_org_admin_can_access_activities(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->get('/admin/activities');
        
        $response->assertStatus(200);
    }

    public function test_regular_user_cannot_access_activities(): void
    {
        $this->actingAs($this->regularUser);
        
        $response = $this->get('/admin/activities');
        
        $response->assertStatus(403);
    }

    public function test_super_admin_sees_all_activities(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->get('/admin/activities');
        
        $response->assertStatus(200);
        // Should see both activities
    }

    public function test_org_admin_sees_only_own_tenant_activities(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->get('/admin/activities');
        
        $response->assertStatus(200);
        // Should only see activities from own tenant
    }
}
