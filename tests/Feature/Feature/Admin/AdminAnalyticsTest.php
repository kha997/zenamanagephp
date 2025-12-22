<?php declare(strict_types=1);

namespace Tests\Feature\Feature\Admin;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
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
        
        $analyticsTenantPerm = Permission::create([
            'code' => 'admin.analytics.tenant',
            'module' => 'admin',
            'action' => 'analytics.tenant',
            'description' => 'Tenant analytics',
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
        $orgAdminRole->permissions()->attach([$analyticsTenantPerm->id]);
        
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

    public function test_super_admin_can_access_analytics(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->get('/admin/analytics');
        
        $response->assertStatus(200);
    }

    public function test_org_admin_can_access_analytics(): void
    {
        $this->actingAs($this->orgAdmin);
        
        $response = $this->get('/admin/analytics');
        
        $response->assertStatus(200);
    }

    public function test_regular_user_cannot_access_analytics(): void
    {
        $this->actingAs($this->regularUser);
        
        $response = $this->get('/admin/analytics');
        
        $response->assertStatus(403);
    }

    public function test_analytics_page_shows_kpi_cards(): void
    {
        $this->actingAs($this->superAdmin);
        
        $response = $this->get('/admin/analytics');
        
        $response->assertStatus(200);
        $response->assertSee('Total Projects');
        $response->assertSee('Active Projects');
    }
}
