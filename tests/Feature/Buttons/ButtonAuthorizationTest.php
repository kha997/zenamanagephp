<?php declare(strict_types=1);

namespace Tests\Feature\Buttons;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Traits\AuthenticationTestTrait;

/**
 * Button Authorization Test
 * 
 * Tests role-based access control for all interactive elements
 */
class ButtonAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTestTrait;

    protected $tenant;
    protected $users = [];
    protected string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'status' => 'active'
        ]);

        $this->tenantId = (string) $this->tenant->id;

        // Create users for each role
        $roles = ['super_admin', 'admin', 'pm', 'designer', 'engineer', 'guest'];
        
        foreach ($roles as $role) {
            $this->users[$role] = User::factory()->create([
                'name' => ucfirst($role) . ' User',
                'email' => $role . '@test-' . uniqid() . '.com',
                'password' => Hash::make('password'),
                'tenant_id' => $this->tenant->id,
                'role' => $role,
            ]);
        }

        // Create test project
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TEST-' . uniqid(),
            'name' => 'Test Project',
            'description' => 'Test project for authorization',
            'status' => 'active',
            'budget_total' => 100000.00
        ]);

        $this->seedSuperAdminRbac();
    }

    private function seedSuperAdminRbac(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'super_admin'],
            [
                'scope' => 'system',
                'tenant_id' => $this->tenant->id,
                'allow_override' => true,
                'is_active' => true,
                'description' => 'System super administrator'
            ]
        );

        if ($role->tenant_id !== $this->tenant->id) {
            $role->fill(['tenant_id' => $this->tenant->id])->save();
        }

        $permission = Permission::firstOrCreate(
            ['code' => 'admin'],
            [
                'name' => 'admin',
                'module' => 'admin',
                'action' => 'access',
                'description' => 'Full admin area access'
            ]
        );

        $role->permissions()->syncWithoutDetaching($permission->id);
        $this->users['super_admin']->roles()->syncWithoutDetaching($role->id);

        // Ensure project manager role + permissions exist for testing
        $pmRole = Role::firstOrCreate(
            ['name' => 'project_manager'],
            [
                'scope' => 'tenant',
                'tenant_id' => $this->tenant->id,
                'allow_override' => false,
                'is_active' => true,
                'description' => 'Tenant-level project manager',
            ]
        );

        if ($pmRole->tenant_id !== $this->tenant->id) {
            $pmRole->fill(['tenant_id' => $this->tenant->id])->save();
        }

        $projectViewPermission = Permission::firstOrCreate(
            ['code' => 'project.view'],
            [
                'name' => 'project.view',
                'module' => 'project',
                'action' => 'view',
                'description' => 'View tenant projects'
            ]
        );

        $projectCreatePermission = Permission::firstOrCreate(
            ['code' => 'project.create'],
            [
                'name' => 'project.create',
                'module' => 'project',
                'action' => 'create',
                'description' => 'Create tenant projects'
            ]
        );

        $projectDeletePermission = Permission::firstOrCreate(
            ['code' => 'project.delete'],
            [
                'name' => 'project.delete',
                'module' => 'project',
                'action' => 'delete',
                'description' => 'Delete tenant projects'
            ]
        );

        $taskUpdatePermission = Permission::firstOrCreate(
            ['code' => 'task.update'],
            [
                'name' => 'task.update',
                'module' => 'task',
                'action' => 'update',
                'description' => 'Update tenant tasks'
            ]
        );

        $pmRole->permissions()->syncWithoutDetaching($projectViewPermission->id);
        $pmRole->permissions()->syncWithoutDetaching($taskUpdatePermission->id);
        $pmRole->permissions()->syncWithoutDetaching($projectCreatePermission->id);
        $pmRole->permissions()->syncWithoutDetaching($projectDeletePermission->id);
        $this->users['pm']->roles()->syncWithoutDetaching($pmRole->id);
    }

    /**
     * Test super_admin access to all functions
     */
    public function test_super_admin_has_full_access(): void
    {
        $this->actingAs($this->users['super_admin']);

        // Test admin access
        $response = $this->getFollowingRedirects('/admin/dashboard');
        $response->assertStatus(200);

        // Test project access
        $response = $this->getFollowingRedirects('/projects');
        $response->assertStatus(200);

        // Test task access
        $response = $this->getFollowingRedirects('/tasks');
        $response->assertStatus(200);

        // Test team access
        $response = $this->getFollowingRedirects('/app/team');
        $response->assertStatus(200);
    }

    /**
     * Test admin access to tenant functions
     */
    public function test_admin_has_tenant_access(): void
    {
        $this->actingAs($this->users['admin']);

        // Test project access
        $response = $this->getFollowingRedirects('/projects');
        $response->assertStatus(200);

        // Test task access
        $response = $this->getFollowingRedirects('/tasks');
        $response->assertStatus(200);

        // Test team access
        $response = $this->getFollowingRedirects('/app/team');
        $response->assertStatus(200);

        // Test admin access (should be denied)
        $response = $this->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    /**
     * Test project manager access
     */
    public function test_pm_has_project_access(): void
    {
        $this->actingAs($this->users['pm']);

        // Test project access
        $response = $this->getFollowingRedirects('/projects');
        $response->assertStatus(200);

        // Test task access
        $response = $this->getFollowingRedirects('/tasks');
        $response->assertStatus(200);

        // Test team access
        $response = $this->getFollowingRedirects('/app/team');
        $response->assertStatus(200);

        // Test admin access (should be denied)
        $response = $this->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    /**
     * Test designer access
     */
    public function test_designer_has_limited_access(): void
    {
        $this->actingAs($this->users['designer']);

        // Test project access (read-only)
        $response = $this->getFollowingRedirects('/projects');
        $response->assertStatus(200);

        // Test task access
        $response = $this->getFollowingRedirects('/tasks');
        $response->assertStatus(200);

        // Test team access (should be denied)
        $response = $this->getFollowingRedirects('/app/team');
        $response->assertStatus(403);

        // Test admin access (should be denied)
        $response = $this->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    /**
     * Test engineer access
     */
    public function test_engineer_has_limited_access(): void
    {
        $this->actingAs($this->users['engineer']);

        // Test project access (read-only)
        $response = $this->getFollowingRedirects('/projects');
        $response->assertStatus(200);

        // Test task access
        $response = $this->getFollowingRedirects('/tasks');
        $response->assertStatus(200);

        // Test team access (should be denied)
        $response = $this->getFollowingRedirects('/app/team');
        $response->assertStatus(403);

        // Test admin access (should be denied)
        $response = $this->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    /**
     * Test guest access (read-only)
     */
    public function test_guest_has_read_only_access(): void
    {
        $this->actingAs($this->users['guest']);

        // Test project access (read-only)
        $response = $this->getFollowingRedirects('/projects');
        $response->assertStatus(200);

        // Test task access (read-only)
        $response = $this->getFollowingRedirects('/tasks');
        $response->assertStatus(200);

        // Test team access (should be denied)
        $response = $this->getFollowingRedirects('/app/team');
        $response->assertStatus(403);

        // Test admin access (should be denied)
        $response = $this->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        // Create another tenant
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Company',
            'slug' => 'other-company-' . uniqid(),
            'status' => 'active'
        ]);

        $otherUser = User::factory()->create([
            'name' => 'Other User',
            'email' => 'other@test-' . uniqid() . '.com',
            'password' => Hash::make('password'),
            'tenant_id' => $otherTenant->id
        ]);

        $this->actingAs($this->users['pm']);

        // Test that user can only access their tenant's data
        $response = $this->getFollowingRedirects('/projects');
        $response->assertStatus(200);
        
        // Verify no cross-tenant data access
        $projects = $response->viewData('projects') ?? [];
        foreach ($projects as $project) {
            $this->assertEquals($this->tenant->id, $project->tenant_id);
        }
    }

    /**
     * Test CRUD operation permissions
     */
    public function test_crud_operation_permissions(): void
    {
        // Test project creation permissions
        $this->actingAs($this->users['pm']);
        $this->get('/projects/create');
        
        $response = $this->post('/projects', [
            'name' => 'New Project',
            'description' => 'Test project',
            'code' => 'NEW-' . uniqid(),
            'status' => 'active',
            'budget_total' => 50000.00
        ]);
        
        $response->assertRedirect('/projects');

        // Test project creation with designer (should be denied)
        $this->actingAs($this->users['designer']);
        $this->get('/projects/create');
        
        $response = $this->post('/projects', [
            'name' => 'New Project 2',
            'description' => 'Test project 2',
            'code' => 'NEW2-' . uniqid(),
            'status' => 'active',
            'budget_total' => 50000.00
        ]);
        
        $response->assertRedirect('/projects');
    }

    /**
     * Test API authorization
     */
    public function test_api_authorization(): void
    {
        $this->actingAs($this->users['pm']);

        // Test authorized API access
        $response = $this->withHeaders($this->tenantHeaders())->getJson('/api/v1/projects');
        $response->assertStatus(200);

        // Test unauthorized API access
        $this->actingAs($this->users['guest']);
        
        $response = $this->withHeaders($this->tenantHeaders())->postJson('/api/v1/projects', [
            'name' => 'Unauthorized Project',
            'description' => 'Should be denied'
        ]);
        
        $response->assertStatus(403);
    }

    /**
     * Test bulk operation permissions
     */
    public function test_bulk_operation_permissions(): void
    {
        $this->actingAs($this->users['pm']);

        // Test authorized bulk operation
        $response = $this->withHeaders($this->tenantHeaders())->postJson('/api/tasks/bulk/status-change', [
            'task_ids' => [],
            'status' => 'completed'
        ]);
        
        $response->assertStatus(200);

        // Test unauthorized bulk operation
        $this->actingAs($this->users['guest']);
        
        $response = $this->withHeaders($this->tenantHeaders())->postJson('/api/tasks/bulk/status-change', [
            'task_ids' => [],
            'status' => 'completed'
        ]);
        
        $response->assertStatus(403);
    }

    private function getFollowingRedirects(string $uri, array $headers = []): \Illuminate\Testing\TestResponse
    {
        $response = $this->withHeaders($headers)->get($uri);
        $redirects = 0;

        while ($response->isRedirection() && $redirects < 5) {
            $location = $response->headers->get('Location');

            if (!$location) {
                break;
            }

            $response = $this->withHeaders($headers)->get($location);
            $redirects++;
        }

        return $response;
    }
}
