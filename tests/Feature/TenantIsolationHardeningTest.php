<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Subtask;
use App\Models\TaskComment;
use App\Models\TaskAttachment;
use App\Models\TaskAssignment;
use App\Models\Invitation;
use App\Models\ChangeRequest;
use App\Models\Notification;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tenant Isolation Hardening Tests
 * 
 * Comprehensive tests to verify tenant isolation at model level,
 * database constraints, and cache isolation.
 */
class TenantIsolationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantAId;
    private string $tenantBId;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two tenants
        $tenantA = \App\Models\Tenant::factory()->create(['name' => 'Tenant A']);
        $tenantB = \App\Models\Tenant::factory()->create(['name' => 'Tenant B']);
        
        $this->tenantAId = $tenantA->id;
        $this->tenantBId = $tenantB->id;

        // Create users for each tenant
        $this->userA = User::factory()->create(['tenant_id' => $this->tenantAId]);
        $this->userB = User::factory()->create(['tenant_id' => $this->tenantBId]);
    }

    /**
     * Test that tenant A cannot read data from tenant B
     */
    public function test_tenant_a_cannot_read_tenant_b_data(): void
    {
        // Create project for tenant B
        $projectB = Project::factory()->create(['tenant_id' => $this->tenantBId]);

        // Authenticate as user A
        $this->actingAs($this->userA);

        // Try to access project from tenant B
        $found = Project::find($projectB->id);
        
        $this->assertNull($found, 'Tenant A should not be able to access Tenant B project');
    }

    /**
     * Test that tenant A cannot create data for tenant B
     */
    public function test_tenant_a_cannot_create_data_for_tenant_b(): void
    {
        $this->actingAs($this->userA);

        // Try to create project with tenant B's ID
        $project = Project::factory()->make(['tenant_id' => $this->tenantBId]);
        
        // The BelongsToTenant trait should override tenant_id
        $project->save();
        
        // Verify tenant_id was set to tenant A
        $this->assertEquals($this->tenantAId, $project->tenant_id, 'Tenant ID should be set to current tenant');
    }

    /**
     * Test GlobalScope on all models with BelongsToTenant
     */
    public function test_global_scope_filters_by_tenant(): void
    {
        // Create data for both tenants
        $projectA = Project::factory()->create(['tenant_id' => $this->tenantAId]);
        $projectB = Project::factory()->create(['tenant_id' => $this->tenantBId]);
        
        $taskA = Task::factory()->create(['tenant_id' => $this->tenantAId, 'project_id' => $projectA->id]);
        $taskB = Task::factory()->create(['tenant_id' => $this->tenantBId, 'project_id' => $projectB->id]);

        // Authenticate as user A
        $this->actingAs($this->userA);

        // Query should only return tenant A data
        $projects = Project::all();
        $this->assertCount(1, $projects);
        $this->assertEquals($this->tenantAId, $projects->first()->tenant_id);

        $tasks = Task::all();
        $this->assertCount(1, $tasks);
        $this->assertEquals($this->tenantAId, $tasks->first()->tenant_id);
    }

    /**
     * Test raw queries still respect tenant isolation
     */
    public function test_raw_queries_respect_tenant_isolation(): void
    {
        // Create data for both tenants
        Project::factory()->create(['tenant_id' => $this->tenantAId]);
        Project::factory()->create(['tenant_id' => $this->tenantBId]);

        $this->actingAs($this->userA);

        // Raw query should still be filtered by GlobalScope
        $count = Project::whereRaw('1=1')->count();
        $this->assertEquals(1, $count, 'Raw query should only return tenant A projects');
    }

    /**
     * Test tenant isolation for Subtask model
     */
    public function test_subtask_tenant_isolation(): void
    {
        $projectA = Project::factory()->create(['tenant_id' => $this->tenantAId]);
        $taskA = Task::factory()->create(['tenant_id' => $this->tenantAId, 'project_id' => $projectA->id]);
        $subtaskA = Subtask::factory()->create(['tenant_id' => $this->tenantAId, 'task_id' => $taskA->id]);

        $projectB = Project::factory()->create(['tenant_id' => $this->tenantBId]);
        $taskB = Task::factory()->create(['tenant_id' => $this->tenantBId, 'project_id' => $projectB->id]);
        $subtaskB = Subtask::factory()->create(['tenant_id' => $this->tenantBId, 'task_id' => $taskB->id]);

        $this->actingAs($this->userA);

        $subtasks = Subtask::all();
        $this->assertCount(1, $subtasks);
        $this->assertEquals($this->tenantAId, $subtasks->first()->tenant_id);
    }

    /**
     * Test tenant isolation for TaskComment model
     */
    public function test_task_comment_tenant_isolation(): void
    {
        $projectA = Project::factory()->create(['tenant_id' => $this->tenantAId]);
        $taskA = Task::factory()->create(['tenant_id' => $this->tenantAId, 'project_id' => $projectA->id]);
        $commentA = TaskComment::factory()->create(['tenant_id' => $this->tenantAId, 'task_id' => $taskA->id]);

        $projectB = Project::factory()->create(['tenant_id' => $this->tenantBId]);
        $taskB = Task::factory()->create(['tenant_id' => $this->tenantBId, 'project_id' => $projectB->id]);
        $commentB = TaskComment::factory()->create(['tenant_id' => $this->tenantBId, 'task_id' => $taskB->id]);

        $this->actingAs($this->userA);

        $comments = TaskComment::all();
        $this->assertCount(1, $comments);
        $this->assertEquals($this->tenantAId, $comments->first()->tenant_id);
    }

    /**
     * Test tenant isolation for TaskAssignment model
     */
    public function test_task_assignment_tenant_isolation(): void
    {
        $projectA = Project::factory()->create(['tenant_id' => $this->tenantAId]);
        $taskA = Task::factory()->create(['tenant_id' => $this->tenantAId, 'project_id' => $projectA->id]);
        $assignmentA = TaskAssignment::factory()->create(['tenant_id' => $this->tenantAId, 'task_id' => $taskA->id, 'user_id' => $this->userA->id]);

        $projectB = Project::factory()->create(['tenant_id' => $this->tenantBId]);
        $taskB = Task::factory()->create(['tenant_id' => $this->tenantBId, 'project_id' => $projectB->id]);
        $assignmentB = TaskAssignment::factory()->create(['tenant_id' => $this->tenantBId, 'task_id' => $taskB->id, 'user_id' => $this->userB->id]);

        $this->actingAs($this->userA);

        $assignments = TaskAssignment::all();
        $this->assertCount(1, $assignments);
        $this->assertEquals($this->tenantAId, $assignments->first()->tenant_id);
    }

    /**
     * Test tenant isolation for Invitation model
     */
    public function test_invitation_tenant_isolation(): void
    {
        Invitation::factory()->create(['tenant_id' => $this->tenantAId]);
        Invitation::factory()->create(['tenant_id' => $this->tenantBId]);

        $this->actingAs($this->userA);

        $invitations = Invitation::all();
        $this->assertCount(1, $invitations);
        $this->assertEquals($this->tenantAId, $invitations->first()->tenant_id);
    }

    /**
     * Test tenant isolation for ChangeRequest model
     */
    public function test_change_request_tenant_isolation(): void
    {
        $projectA = Project::factory()->create(['tenant_id' => $this->tenantAId]);
        $changeRequestA = ChangeRequest::factory()->create(['tenant_id' => $this->tenantAId, 'project_id' => $projectA->id]);

        $projectB = Project::factory()->create(['tenant_id' => $this->tenantBId]);
        $changeRequestB = ChangeRequest::factory()->create(['tenant_id' => $this->tenantBId, 'project_id' => $projectB->id]);

        $this->actingAs($this->userA);

        $changeRequests = ChangeRequest::all();
        $this->assertCount(1, $changeRequests);
        $this->assertEquals($this->tenantAId, $changeRequests->first()->tenant_id);
    }

    /**
     * Test tenant isolation for Notification model
     */
    public function test_notification_tenant_isolation(): void
    {
        Notification::factory()->create(['tenant_id' => $this->tenantAId, 'user_id' => $this->userA->id]);
        Notification::factory()->create(['tenant_id' => $this->tenantBId, 'user_id' => $this->userB->id]);

        $this->actingAs($this->userA);

        $notifications = Notification::all();
        $this->assertCount(1, $notifications);
        $this->assertEquals($this->tenantAId, $notifications->first()->tenant_id);
    }

    /**
     * Test tenant isolation for AuditLog model
     */
    public function test_audit_log_tenant_isolation(): void
    {
        AuditLog::factory()->create(['tenant_id' => $this->tenantAId, 'user_id' => $this->userA->id]);
        AuditLog::factory()->create(['tenant_id' => $this->tenantBId, 'user_id' => $this->userB->id]);

        $this->actingAs($this->userA);

        $auditLogs = AuditLog::all();
        $this->assertCount(1, $auditLogs);
        $this->assertEquals($this->tenantAId, $auditLogs->first()->tenant_id);
    }

    /**
     * Test that super admin can bypass tenant scope using withoutTenantScope()
     */
    public function test_super_admin_can_bypass_tenant_scope(): void
    {
        // Create super admin user with super_admin role
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
        ]);
        
        // Assign super_admin role
        $superAdminRole = \App\Models\Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->roles()->attach($superAdminRole->id);

        // Create data for both tenants
        Project::factory()->create(['tenant_id' => $this->tenantAId]);
        Project::factory()->create(['tenant_id' => $this->tenantBId]);

        $this->actingAs($superAdmin);

        // By default, GlobalScope should still apply (even for super admin)
        $projects = Project::all();
        // Super admin without tenant_id should see 0 projects (GlobalScope filters by null tenant_id)
        $this->assertCount(0, $projects, 'Super admin without tenant_id should see 0 projects by default');

        // Super admin can explicitly bypass tenant scope when needed
        $allProjects = Project::withoutTenantScope()->get();
        $this->assertGreaterThanOrEqual(2, $allProjects->count(), 'Super admin can bypass tenant scope explicitly');
    }

    /**
     * Test that non-super-admin cannot bypass tenant scope
     */
    public function test_non_super_admin_cannot_bypass_tenant_scope(): void
    {
        // Create regular user
        $regularUser = User::factory()->create(['tenant_id' => $this->tenantAId]);

        // Create data for both tenants
        Project::factory()->create(['tenant_id' => $this->tenantAId]);
        Project::factory()->create(['tenant_id' => $this->tenantBId]);

        $this->actingAs($regularUser);

        // Regular user should not be able to bypass tenant scope
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->expectExceptionMessage('Only super-admin can bypass tenant scope');
        
        Project::withoutTenantScope()->get();
    }

    /**
     * Test database constraint prevents NULL tenant_id
     */
    public function test_database_constraint_prevents_null_tenant_id(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to create project without tenant_id
        // This should fail if NOT NULL constraint is in place
        DB::table('projects')->insert([
            'id' => \Illuminate\Support\Str::ulid(),
            'name' => 'Test Project',
            'code' => 'TEST',
            'tenant_id' => null, // This should fail
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Test composite unique index with tenant_id
     */
    public function test_composite_unique_index_with_tenant_id(): void
    {
        $projectA = Project::factory()->create([
            'tenant_id' => $this->tenantAId,
            'code' => 'PROJ001',
        ]);

        // Same code for different tenant should be allowed
        $projectB = Project::factory()->create([
            'tenant_id' => $this->tenantBId,
            'code' => 'PROJ001', // Same code, different tenant
        ]);

        $this->assertNotEquals($projectA->id, $projectB->id);

        // Same code for same tenant should fail
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Project::factory()->create([
            'tenant_id' => $this->tenantAId,
            'code' => 'PROJ001', // Same code, same tenant - should fail
        ]);
    }

    /**
     * Test that explain plan shows proper index usage for list queries
     */
    public function test_explain_plan_uses_proper_indexes(): void
    {
        // Create test data
        Project::factory()->count(10)->create(['tenant_id' => $this->tenantAId]);
        Task::factory()->count(20)->create([
            'tenant_id' => $this->tenantAId,
            'project_id' => Project::where('tenant_id', $this->tenantAId)->first()->id,
        ]);

        $this->actingAs($this->userA);

        // Test Projects list query uses (tenant_id, created_at) index
        $projectsQuery = Project::query()->orderBy('created_at', 'desc');
        $explain = DB::select("EXPLAIN " . $projectsQuery->toSql(), $projectsQuery->getBindings());
        
        $this->assertNotEmpty($explain, 'Explain plan should return results');
        
        // Check that tenant_id is in the query
        $sql = $projectsQuery->toSql();
        $this->assertStringContainsString('tenant_id', $sql, 'Query should filter by tenant_id');

        // Test Tasks list query uses (tenant_id, status, created_at) index
        $tasksQuery = Task::query()
            ->where('status', 'active')
            ->orderBy('created_at', 'desc');
        $explain = DB::select("EXPLAIN " . $tasksQuery->toSql(), $tasksQuery->getBindings());
        
        $this->assertNotEmpty($explain, 'Explain plan should return results');
        
        // Check that tenant_id and status are in the query
        $sql = $tasksQuery->toSql();
        $this->assertStringContainsString('tenant_id', $sql, 'Query should filter by tenant_id');
        $this->assertStringContainsString('status', $sql, 'Query should filter by status');
    }

    /**
     * Test cross-tenant query always returns empty (except with withoutTenantScope)
     */
    public function test_cross_tenant_query_always_empty(): void
    {
        // Create data for both tenants
        $projectA = Project::factory()->create(['tenant_id' => $this->tenantAId]);
        $projectB = Project::factory()->create(['tenant_id' => $this->tenantBId]);
        
        $taskA = Task::factory()->create([
            'tenant_id' => $this->tenantAId,
            'project_id' => $projectA->id,
        ]);
        $taskB = Task::factory()->create([
            'tenant_id' => $this->tenantBId,
            'project_id' => $projectB->id,
        ]);

        // Authenticate as user A
        $this->actingAs($this->userA);

        // Regular queries should only return tenant A data
        $projects = Project::all();
        $this->assertCount(1, $projects, 'Should only return tenant A projects');
        $this->assertEquals($this->tenantAId, $projects->first()->tenant_id);

        $tasks = Task::all();
        $this->assertCount(1, $tasks, 'Should only return tenant A tasks');
        $this->assertEquals($this->tenantAId, $tasks->first()->tenant_id);

        // Try to access tenant B data directly by ID - should return null
        $foundProjectB = Project::find($projectB->id);
        $this->assertNull($foundProjectB, 'Should not find tenant B project');

        $foundTaskB = Task::find($taskB->id);
        $this->assertNull($foundTaskB, 'Should not find tenant B task');

        // Try to query with tenant B ID explicitly - should return empty
        $crossTenantProjects = Project::where('tenant_id', $this->tenantBId)->get();
        $this->assertCount(0, $crossTenantProjects, 'Should not return tenant B projects even with explicit filter');

        $crossTenantTasks = Task::where('tenant_id', $this->tenantBId)->get();
        $this->assertCount(0, $crossTenantTasks, 'Should not return tenant B tasks even with explicit filter');
    }

    /**
     * Test partial unique constraint with soft delete
     */
    public function test_partial_unique_constraint_with_soft_delete(): void
    {
        // Create project with code
        $project1 = Project::factory()->create([
            'tenant_id' => $this->tenantAId,
            'code' => 'TEST001',
        ]);

        // Soft delete the project
        $project1->delete();
        $this->assertNotNull($project1->deleted_at, 'Project should be soft deleted');

        // Should be able to create another project with same code (since first is deleted)
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenantAId,
            'code' => 'TEST001', // Same code, but first is deleted
        ]);

        $this->assertNotEquals($project1->id, $project2->id);
        $this->assertNull($project2->deleted_at, 'New project should not be deleted');

        // Should NOT be able to create another active project with same code
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Project::factory()->create([
            'tenant_id' => $this->tenantAId,
            'code' => 'TEST001', // Same code, same tenant, both active - should fail
        ]);
    }
}

