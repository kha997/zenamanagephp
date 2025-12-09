<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectActivity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * ProjectTaskReorderApiTest
 * 
 * Round 210: Test reorder endpoint for ProjectTask
 * 
 * @group project-tasks
 * @group api-v1
 * @group reorder
 */
class ProjectTaskReorderApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(210001);
        $this->setDomainName('project-task-reorder-api');
        $this->setupDomainIsolation();

        // Create tenants
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
        ]);
        
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);

        // Create users
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'pm',
        ]);

        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'pm',
        ]);

        // Attach users to tenants via pivot table with is_default = true
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        // Refresh users to ensure pivot data is available
        $this->userA->refresh();
        $this->userB->refresh();
    }

    /**
     * Test reordering tasks within project successfully
     */
    public function test_it_reorders_tasks_within_project_successfully(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        // Create project
        $project = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Test Project',
            'code' => 'PRJ-REORDER-001',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create tasks with initial sort_order
        $task1 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Task 1',
            'sort_order' => 10,
            'phase_label' => 'Phase A',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        $task2 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Task 2',
            'sort_order' => 20,
            'phase_label' => 'Phase A',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        $task3 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Task 3',
            'sort_order' => 30,
            'phase_label' => 'Phase A',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Reorder: task3, task1, task2
        $response = $this->postJson("/api/v1/app/projects/{$project->id}/tasks/reorder", [
            'ordered_ids' => [$task3->id, $task1->id, $task2->id],
        ]);
        
        $response->assertStatus(204);
        
        // Verify new sort_order in database
        $task1->refresh();
        $task2->refresh();
        $task3->refresh();
        
        $this->assertEquals(20, $task1->sort_order); // Second position
        $this->assertEquals(30, $task2->sort_order); // Third position
        $this->assertEquals(10, $task3->sort_order); // First position
    }

    /**
     * Test that reorder fails if task not in project
     */
    public function test_it_fails_if_task_not_in_project(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        // Create project
        $project = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Test Project',
            'code' => 'PRJ-REORDER-002',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create another project
        $otherProject = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Other Project',
            'code' => 'PRJ-REORDER-003',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create task in project
        $task1 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Task 1',
            'sort_order' => 10,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create task in other project
        $task2 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $otherProject->id,
            'name' => 'Task 2',
            'sort_order' => 10,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Try to reorder with task from other project - should fail
        $response = $this->postJson("/api/v1/app/projects/{$project->id}/tasks/reorder", [
            'ordered_ids' => [$task1->id, $task2->id],
        ]);
        
        $response->assertStatus(404);
    }

    /**
     * Test that reorder enforces tenant scope
     */
    public function test_it_enforces_tenant_scope(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        // Create project for Tenant A
        $projectA = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Tenant A Project',
            'code' => 'PRJ-TENANT-A-REORDER',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create task for Tenant A
        $taskA = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $projectA->id,
            'name' => 'Tenant A Task',
            'sort_order' => 10,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Switch to Tenant B
        Sanctum::actingAs($this->userB, [], 'sanctum');
        
        $resolvedTenantIdB = $tenancyService->resolveActiveTenantId(auth()->user(), request());
        
        // Create project for Tenant B
        $projectB = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantIdB,
            'name' => 'Tenant B Project',
            'code' => 'PRJ-TENANT-B-REORDER',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'created_by' => $this->userB->id,
            'updated_by' => $this->userB->id,
        ]);
        
        // Create task for Tenant B
        $taskB = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantIdB,
            'project_id' => $projectB->id,
            'name' => 'Tenant B Task',
            'sort_order' => 10,
            'created_by' => $this->userB->id,
            'updated_by' => $this->userB->id,
        ]);
        
        // Try to reorder Tenant A's task from Tenant B - should fail
        $response = $this->postJson("/api/v1/app/projects/{$projectA->id}/tasks/reorder", [
            'ordered_ids' => [$taskA->id],
        ]);
        
        $response->assertStatus(404);
        
        // Try to reorder with Tenant A's task ID from Tenant B - should fail
        $response = $this->postJson("/api/v1/app/projects/{$projectB->id}/tasks/reorder", [
            'ordered_ids' => [$taskB->id, $taskA->id],
        ]);
        
        $response->assertStatus(404);
    }

    /**
     * Test that reorder rejects duplicate IDs
     */
    public function test_it_rejects_duplicate_ids_in_payload(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        // Create project
        $project = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Test Project',
            'code' => 'PRJ-REORDER-004',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create task
        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Task 1',
            'sort_order' => 10,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Try to reorder with duplicate IDs - should fail validation
        $response = $this->postJson("/api/v1/app/projects/{$project->id}/tasks/reorder", [
            'ordered_ids' => [$task->id, $task->id],
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ordered_ids']);
    }

    /**
     * Test that reorder ignores soft-deleted tasks
     */
    public function test_it_ignores_soft_deleted_tasks(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        // Create project
        $project = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Test Project',
            'code' => 'PRJ-REORDER-005',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create tasks
        $task1 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Task 1',
            'sort_order' => 10,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        $task2 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Task 2',
            'sort_order' => 20,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Soft delete task2
        $task2->delete();
        
        // Try to reorder with soft-deleted task - should fail
        $response = $this->postJson("/api/v1/app/projects/{$project->id}/tasks/reorder", [
            'ordered_ids' => [$task1->id, $task2->id],
        ]);
        
        $response->assertStatus(404);
    }

    /**
     * Test validation rules
     */
    public function test_it_validates_required_fields(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        // Create project
        $project = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Test Project',
            'code' => 'PRJ-REORDER-006',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Try to reorder without ordered_ids - should fail validation
        $response = $this->postJson("/api/v1/app/projects/{$project->id}/tasks/reorder", []);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ordered_ids']);
        
        // Try with empty array - should fail validation
        $response = $this->postJson("/api/v1/app/projects/{$project->id}/tasks/reorder", [
            'ordered_ids' => [],
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ordered_ids']);
        
        // Try with invalid type - should fail validation
        $response = $this->postJson("/api/v1/app/projects/{$project->id}/tasks/reorder", [
            'ordered_ids' => 'not-an-array',
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ordered_ids']);
    }

    /**
     * Test that reorder logs project activity
     * 
     * Round 211: Test activity logging for task reordering
     */
    public function test_it_logs_project_tasks_reordered_activity(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        // Create project
        $project = Project::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Test Project',
            'code' => 'PRJ-REORDER-ACTIVITY',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create tasks with initial sort_order in the same phase
        $task1 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Task 1',
            'sort_order' => 10,
            'phase_code' => 'TKKT',
            'phase_label' => 'Thi công khởi tạo',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        $task2 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Task 2',
            'sort_order' => 20,
            'phase_code' => 'TKKT',
            'phase_label' => 'Thi công khởi tạo',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        $task3 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Task 3',
            'sort_order' => 30,
            'phase_code' => 'TKKT',
            'phase_label' => 'Thi công khởi tạo',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        $task4 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Task 4',
            'sort_order' => 40,
            'phase_code' => 'TKKT',
            'phase_label' => 'Thi công khởi tạo',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        $task5 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Task 5',
            'sort_order' => 50,
            'phase_code' => 'TKKT',
            'phase_label' => 'Thi công khởi tạo',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Expected before order: task1, task2, task3, task4, task5 (by sort_order)
        $expectedBefore = [$task1->id, $task2->id, $task3->id, $task4->id, $task5->id];
        
        // Reorder: task5, task3, task1, task4, task2
        $newOrder = [$task5->id, $task3->id, $task1->id, $task4->id, $task2->id];
        
        $response = $this->postJson("/api/v1/app/projects/{$project->id}/tasks/reorder", [
            'ordered_ids' => $newOrder,
        ]);
        
        $response->assertStatus(204);
        
        // Assert exactly one ProjectActivity was created
        $activities = ProjectActivity::where('project_id', $project->id)
            ->where('action', ProjectActivity::ACTION_PROJECT_TASKS_REORDERED)
            ->where('entity_type', ProjectActivity::ENTITY_PROJECT_TASK)
            ->get();
        
        $this->assertCount(1, $activities, 'Exactly one activity should be logged');
        
        $activity = $activities->first();
        
        // Assert activity properties
        $this->assertEquals($project->id, $activity->project_id);
        $this->assertEquals((string) $resolvedTenantId, $activity->tenant_id);
        $this->assertEquals($this->userA->id, $activity->user_id);
        $this->assertEquals(ProjectActivity::ACTION_PROJECT_TASKS_REORDERED, $activity->action);
        $this->assertEquals(ProjectActivity::ENTITY_PROJECT_TASK, $activity->entity_type);
        $this->assertNull($activity->entity_id, 'Entity ID should be null for bulk action');
        
        // Assert metadata
        $metadata = $activity->metadata;
        $this->assertEquals('TKKT', $metadata['phase_code']);
        $this->assertEquals('Thi công khởi tạo', $metadata['phase_label']);
        $this->assertEquals(5, $metadata['task_count']);
        $this->assertEquals($expectedBefore, $metadata['task_ids_before'], 'Before order should match sort_order');
        $this->assertEquals($newOrder, $metadata['task_ids_after'], 'After order should match reorder request');
        
        // Assert description contains phase label and task count
        $this->assertStringContainsString('Reordered 5 task(s)', $activity->description);
        $this->assertStringContainsString('Thi công khởi tạo', $activity->description);
    }
}

