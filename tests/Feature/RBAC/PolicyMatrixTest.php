<?php declare(strict_types=1);

namespace Tests\Feature\RBAC;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/**
 * Policy Matrix Test
 * 
 * Verifies that RBAC policies correctly allow/deny access
 * for different user profiles (super-admin, tenant-admin, member).
 */
class PolicyMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $tenantAdmin;
    private User $member;
    private User $client;
    private Project $project;
    private Task $task;
    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = 'test_tenant_' . uniqid();

        // Create super admin (no tenant_id)
        $this->superAdmin = User::factory()->create([
            'tenant_id' => null,
            'is_admin' => true,
        ]);

        // Create tenant admin
        $this->tenantAdmin = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'admin',
        ]);

        // Create member
        $this->member = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'member',
        ]);

        // Create client
        $this->client = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'client',
        ]);

        // Create test data
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);

        $this->task = Task::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $this->project->id,
        ]);
    }

    /**
     * Test super-admin can access all resources
     */
    public function test_super_admin_can_access_all_resources(): void
    {
        $this->actingAs($this->superAdmin);

        // Super admin should be able to view any project
        $this->assertTrue(Gate::allows('view', $this->project));
        $this->assertTrue(Gate::allows('update', $this->project));
        $this->assertTrue(Gate::allows('delete', $this->project));

        // Super admin should be able to view any task
        $this->assertTrue(Gate::allows('view', $this->task));
        $this->assertTrue(Gate::allows('update', $this->task));
        $this->assertTrue(Gate::allows('delete', $this->task));
    }

    /**
     * Test tenant-admin can manage tenant resources
     */
    public function test_tenant_admin_can_manage_tenant_resources(): void
    {
        $this->actingAs($this->tenantAdmin);

        // Tenant admin should be able to view/update/delete projects in their tenant
        $this->assertTrue(Gate::allows('view', $this->project));
        $this->assertTrue(Gate::allows('update', $this->project));
        $this->assertTrue(Gate::allows('delete', $this->project));

        // Tenant admin should be able to view/update/delete tasks in their tenant
        $this->assertTrue(Gate::allows('view', $this->task));
        $this->assertTrue(Gate::allows('update', $this->task));
        $this->assertTrue(Gate::allows('delete', $this->task));
    }

    /**
     * Test member can view but not modify
     */
    public function test_member_can_view_but_not_modify(): void
    {
        $this->actingAs($this->member);

        // Member should be able to view
        $this->assertTrue(Gate::allows('view', $this->project));
        $this->assertTrue(Gate::allows('view', $this->task));

        // Member should NOT be able to update/delete
        $this->assertFalse(Gate::allows('update', $this->project));
        $this->assertFalse(Gate::allows('delete', $this->project));
        $this->assertFalse(Gate::allows('update', $this->task));
        $this->assertFalse(Gate::allows('delete', $this->task));
    }

    /**
     * Test client has limited access
     */
    public function test_client_has_limited_access(): void
    {
        $this->actingAs($this->client);

        // Client should be able to view (if project is client-visible)
        $this->assertTrue(Gate::allows('view', $this->project));

        // Client should NOT be able to modify
        $this->assertFalse(Gate::allows('update', $this->project));
        $this->assertFalse(Gate::allows('delete', $this->project));
    }

    /**
     * Test cross-tenant access is denied
     */
    public function test_cross_tenant_access_is_denied(): void
    {
        // Create another tenant
        $otherTenantId = 'other_tenant_' . uniqid();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenantId,
        ]);

        $this->actingAs($this->tenantAdmin);

        // Tenant admin should NOT be able to access other tenant's project
        $this->assertFalse(Gate::allows('view', $otherProject));
        $this->assertFalse(Gate::allows('update', $otherProject));
        $this->assertFalse(Gate::allows('delete', $otherProject));
    }

    /**
     * Test ability-based permissions
     */
    public function test_ability_based_permissions(): void
    {
        $this->actingAs($this->tenantAdmin);

        // Tenant admin should have projects.manage ability
        $this->assertTrue($this->tenantAdmin->can('projects.manage'));
        $this->assertTrue($this->tenantAdmin->can('tasks.manage'));

        $this->actingAs($this->member);

        // Member should have projects.view but not projects.manage
        $this->assertTrue($this->member->can('projects.view'));
        $this->assertFalse($this->member->can('projects.manage'));
        $this->assertFalse($this->member->can('tasks.manage'));
    }
}

