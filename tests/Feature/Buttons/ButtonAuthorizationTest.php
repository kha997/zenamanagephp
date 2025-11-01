<?php declare(strict_types=1);

namespace Tests\Feature\Buttons;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Button Authorization Test
 * 
 * Tests role-based access control for all interactive elements
 */
class ButtonAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'status' => 'active'
        ]);

        // Create users for each role
        $roles = ['super_admin', 'admin', 'pm', 'designer', 'engineer', 'guest'];
        
        foreach ($roles as $role) {
            $this->users[$role] = User::create([
                'name' => ucfirst($role) . ' User',
                'email' => $role . '@test-' . uniqid() . '.com',
                'password' => Hash::make('password'),
                'tenant_id' => $this->tenant->id
            ]);
        }

        // Create test project
        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TEST-' . uniqid(),
            'name' => 'Test Project',
            'description' => 'Test project for authorization',
            'status' => 'active',
            'budget_total' => 100000.00
        ]);
    }

    /**
     * Test super_admin access to all functions
     */
    public function test_super_admin_has_full_access(): void
    {
        $this->actingAs($this->users['super_admin']);

        // Test admin access
        $response = $this->get('/admin');
        $response->assertStatus(200);

        // Test project access
        $response = $this->get('/projects');
        $response->assertStatus(200);

        // Test task access
        $response = $this->get('/tasks');
        $response->assertStatus(200);

        // Test team access
        $response = $this->get('/team');
        $response->assertStatus(200);
    }

    /**
     * Test admin access to tenant functions
     */
    public function test_admin_has_tenant_access(): void
    {
        $this->actingAs($this->users['admin']);

        // Test project access
        $response = $this->get('/projects');
        $response->assertStatus(200);

        // Test task access
        $response = $this->get('/tasks');
        $response->assertStatus(200);

        // Test team access
        $response = $this->get('/team');
        $response->assertStatus(200);

        // Test admin access (should be denied)
        $response = $this->get('/admin');
        $response->assertStatus(403);
    }

    /**
     * Test project manager access
     */
    public function test_pm_has_project_access(): void
    {
        $this->actingAs($this->users['pm']);

        // Test project access
        $response = $this->get('/projects');
        $response->assertStatus(200);

        // Test task access
        $response = $this->get('/tasks');
        $response->assertStatus(200);

        // Test team access
        $response = $this->get('/team');
        $response->assertStatus(200);

        // Test admin access (should be denied)
        $response = $this->get('/admin');
        $response->assertStatus(403);
    }

    /**
     * Test designer access
     */
    public function test_designer_has_limited_access(): void
    {
        $this->actingAs($this->users['designer']);

        // Test project access (read-only)
        $response = $this->get('/projects');
        $response->assertStatus(200);

        // Test task access
        $response = $this->get('/tasks');
        $response->assertStatus(200);

        // Test team access (should be denied)
        $response = $this->get('/team');
        $response->assertStatus(403);

        // Test admin access (should be denied)
        $response = $this->get('/admin');
        $response->assertStatus(403);
    }

    /**
     * Test engineer access
     */
    public function test_engineer_has_limited_access(): void
    {
        $this->actingAs($this->users['engineer']);

        // Test project access (read-only)
        $response = $this->get('/projects');
        $response->assertStatus(200);

        // Test task access
        $response = $this->get('/tasks');
        $response->assertStatus(200);

        // Test team access (should be denied)
        $response = $this->get('/team');
        $response->assertStatus(403);

        // Test admin access (should be denied)
        $response = $this->get('/admin');
        $response->assertStatus(403);
    }

    /**
     * Test guest access (read-only)
     */
    public function test_guest_has_read_only_access(): void
    {
        $this->actingAs($this->users['guest']);

        // Test project access (read-only)
        $response = $this->get('/projects');
        $response->assertStatus(200);

        // Test task access (read-only)
        $response = $this->get('/tasks');
        $response->assertStatus(200);

        // Test team access (should be denied)
        $response = $this->get('/team');
        $response->assertStatus(403);

        // Test admin access (should be denied)
        $response = $this->get('/admin');
        $response->assertStatus(403);
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        // Create another tenant
        $otherTenant = Tenant::create([
            'name' => 'Other Company',
            'slug' => 'other-company-' . uniqid(),
            'status' => 'active'
        ]);

        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@test-' . uniqid() . '.com',
            'password' => Hash::make('password'),
            'tenant_id' => $otherTenant->id
        ]);

        $this->actingAs($this->users['pm']);

        // Test that user can only access their tenant's data
        $response = $this->get('/projects');
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
        
        $response = $this->post('/projects', [
            'name' => 'New Project',
            'description' => 'Test project',
            'code' => 'NEW-' . uniqid(),
            'status' => 'active',
            'budget_total' => 50000.00
        ]);
        
        $response->assertStatus(201);

        // Test project creation with designer (should be denied)
        $this->actingAs($this->users['designer']);
        
        $response = $this->post('/projects', [
            'name' => 'New Project 2',
            'description' => 'Test project 2',
            'code' => 'NEW2-' . uniqid(),
            'status' => 'active',
            'budget_total' => 50000.00
        ]);
        
        $response->assertStatus(403);
    }

    /**
     * Test API authorization
     */
    public function test_api_authorization(): void
    {
        $this->actingAs($this->users['pm']);

        // Test authorized API access
        $response = $this->getJson('/api/v1/projects');
        $response->assertStatus(200);

        // Test unauthorized API access
        $this->actingAs($this->users['guest']);
        
        $response = $this->postJson('/api/v1/projects', [
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
        $response = $this->postJson('/api/tasks/bulk/status-change', [
            'task_ids' => [],
            'status' => 'completed'
        ]);
        
        $response->assertStatus(200);

        // Test unauthorized bulk operation
        $this->actingAs($this->users['guest']);
        
        $response = $this->postJson('/api/tasks/bulk/status-change', [
            'task_ids' => [],
            'status' => 'completed'
        ]);
        
        $response->assertStatus(403);
    }
}
