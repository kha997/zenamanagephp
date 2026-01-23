<?php declare(strict_types=1);

namespace Tests\Feature\Buttons;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\InteractsWithRbac;
use Tests\TestCase;

/**
 * Button Authorization Test
 * 
 * Tests role-based access control for all interactive elements
 */
class ButtonAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    protected $tenant;
    protected $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'status' => 'active'
        ]);

        $this->seedRolesAndPermissions();

        foreach (['super_admin', 'admin', 'pm', 'designer', 'engineer', 'guest'] as $role) {
            $this->users[$role] = $this->createUserWithRole($role, $this->tenant);
        }

        foreach (['designer', 'engineer', 'guest'] as $role) {
            $this->assertTrue(
                $this->users[$role]->hasRole($role),
                "{$role} user should retain the {$role} role"
            );
            $this->assertFalse(
                $this->users[$role]->hasAnyRole(['super_admin', 'admin', 'pm']),
                "{$role} should not inherit admin-level roles"
            );
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

    protected function authRole(string $role): User
    {
        $user = $this->users[$role];
        $this->actingAs($user);        // web guard for /admin/dashboard
        \Laravel\Sanctum\Sanctum::actingAs($user); // api guard for /api/*
        return $user;
    }

    /**
     * Test super_admin access to all functions
     */
    public function test_super_admin_has_full_access(): void
    {
        $this->authRole('super_admin');

        $response = $this->get('/admin/dashboard');
        $response->assertStatus(200);

        $response = $this->getJson('/api/projects');
        $response->assertStatus(200);

        $response = $this->getJson('/api/tasks');
        $response->assertStatus(200);

        $response = $this->getJson('/api/dashboard/users-v2');
        $response->assertStatus(200);
    }

    /**
     * Test admin access to tenant functions
     */
    public function test_admin_has_tenant_access(): void
    {
        $this->authRole('admin');

        $response = $this->getJson('/api/projects');
        $response->assertStatus(200);

        $response = $this->getJson('/api/tasks');
        $response->assertStatus(200);

        $response = $this->getJson('/api/dashboard/users-v2');
        $response->assertStatus(200);

        $response = $this->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    /**
     * Test project manager access
     */
    public function test_pm_has_project_access(): void
    {
        $this->authRole('pm');

        $response = $this->getJson('/api/projects');
        $response->assertStatus(200);

        $response = $this->getJson('/api/tasks');
        $response->assertStatus(200);

        $response = $this->getJson('/api/dashboard/users-v2');
        $response->assertStatus(200);

        $response = $this->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    /**
     * Test designer access
     */
    public function test_designer_has_limited_access(): void
    {
        $this->authRole('designer');

        $response = $this->getJson('/api/projects');
        $response->assertStatus(200);

        $response = $this->getJson('/api/tasks');
        $response->assertStatus(200);

        $response = $this->getJson('/api/dashboard/users-v2');
        $response->assertStatus(403);

        $response = $this->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    /**
     * Test engineer access
     */
    public function test_engineer_has_limited_access(): void
    {
        $this->authRole('engineer');

        $response = $this->getJson('/api/projects');
        $response->assertStatus(200);

        $response = $this->getJson('/api/tasks');
        $response->assertStatus(200);

        $response = $this->getJson('/api/dashboard/users-v2');
        $response->assertStatus(403);

        $response = $this->get('/admin/dashboard');
        $response->assertStatus(403);
    }

    /**
     * Test guest access (read-only)
     */
    public function test_guest_has_read_only_access(): void
    {
        $this->authRole('guest');

        $response = $this->getJson('/api/projects');
        $response->assertStatus(200);

        $response = $this->getJson('/api/tasks');
        $response->assertStatus(200);

        $response = $this->getJson('/api/dashboard/users-v2');
        $response->assertStatus(403);

        $response = $this->get('/admin/dashboard');
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

        $otherProject = Project::create([
            'tenant_id' => $otherTenant->id,
            'code' => 'OTHER-' . uniqid(),
            'name' => 'Other Tenant Project',
            'description' => 'Should be isolated',
            'status' => 'active',
            'budget_total' => 75000.00
        ]);

        $this->authRole('pm');

        $response = $this->getJson('/api/projects');
        $response->assertStatus(200);
        $response->assertJsonMissing(['id' => $otherProject->id]);
    }

    /**
     * Test CRUD operation permissions
     */
    public function test_crud_operation_permissions(): void
    {
        $this->authRole('pm');

        $response = $this->postJson('/api/projects', [
            'name' => 'New Project',
            'description' => 'Test project',
            'code' => 'NEW-' . uniqid(),
            'status' => 'active',
            'budget_total' => 50000.00
        ]);

        if (!in_array($response->status(), [200, 201], true)) {
            file_put_contents('/tmp/crud_operation_failure.json', json_encode([
                'status' => $response->status(),
                'body' => $response->json(),
            ]));
        }

        $this->assertTrue(
            in_array($response->status(), [200, 201], true),
            'Expected project creation to return 200 or 201'
        );

        $this->authRole('designer');

        $response = $this->postJson('/api/projects', [
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
        $this->authRole('pm');

        $response = $this->getJson('/api/projects');
        $response->assertStatus(200);

        $this->authRole('guest');

        $response = $this->postJson('/api/projects', [
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
        $this->authRole('pm');

        // Test authorized bulk operation
        $response = $this->postJson('/api/auth/bulk/tasks/update-status', [
            'task_ids' => [],
            'status' => 'completed'
        ]);

        $this->assertContains($response->status(), [200, 201, 202]);

        // Test unauthorized bulk operation
        $this->authRole('guest');
        
        $response = $this->postJson('/api/auth/bulk/tasks/update-status', [
            'task_ids' => [],
            'status' => 'completed'
        ]);
        
        $response->assertStatus(403);
    }
}
