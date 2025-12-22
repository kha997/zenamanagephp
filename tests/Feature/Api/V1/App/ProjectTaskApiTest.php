<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectTask;
use App\Models\TaskTemplate;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * ProjectTask API Test
 * 
 * Round 206: Test update, complete, incomplete endpoints for ProjectTask
 * 
 * @group project-tasks
 * @group api-v1
 */
class ProjectTaskApiTest extends TestCase
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
        $this->setDomainSeed(202001);
        $this->setDomainName('project-task-api');
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
     * Test updating task status and fields
     */
    public function test_it_updates_task_status_and_fields(): void
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
            'code' => 'PRJ-TEST-001',
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
            'name' => 'Test Task',
            'status' => 'pending',
            'due_date' => '2025-01-10',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Update task
        $response = $this->patchJson("/api/v1/app/projects/{$project->id}/tasks/{$task->id}", [
            'status' => 'in_progress',
            'due_date' => '2025-01-15',
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'status',
                'due_date',
                'is_completed',
            ],
        ]);
        
        $responseData = $response->json('data');
        $this->assertEquals('in_progress', $responseData['status']);
        $this->assertEquals('2025-01-15', $responseData['due_date']);
        
        // Verify in database
        $task->refresh();
        $this->assertEquals('in_progress', $task->status);
        $this->assertEquals('2025-01-15', $task->due_date->format('Y-m-d'));
        
        // Verify activity log
        $activity = ProjectActivity::where('project_id', $project->id)
            ->where('action', ProjectActivity::ACTION_PROJECT_TASK_UPDATED)
            ->where('entity_id', $task->id)
            ->first();
        
        $this->assertNotNull($activity);
        $this->assertEquals($this->userA->id, $activity->user_id);
        $this->assertArrayHasKey('changes', $activity->metadata);
    }

    /**
     * Test marking task as completed
     */
    public function test_it_marks_task_as_completed(): void
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
            'code' => 'PRJ-TEST-002',
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
            'name' => 'Test Task',
            'status' => 'pending',
            'is_completed' => false,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Mark as completed
        $response = $this->postJson("/api/v1/app/projects/{$project->id}/tasks/{$task->id}/complete");
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'is_completed',
                'completed_at',
            ],
        ]);
        
        $responseData = $response->json('data');
        $this->assertTrue($responseData['is_completed']);
        $this->assertNotNull($responseData['completed_at']);
        
        // Verify in database
        $task->refresh();
        $this->assertTrue($task->is_completed);
        $this->assertNotNull($task->completed_at);
        $this->assertEquals('completed', $task->status); // Should auto-set to 'completed'
        
        // Verify activity log
        $activity = ProjectActivity::where('project_id', $project->id)
            ->where('action', ProjectActivity::ACTION_PROJECT_TASK_COMPLETED)
            ->where('entity_id', $task->id)
            ->first();
        
        $this->assertNotNull($activity);
        $this->assertEquals($this->userA->id, $activity->user_id);
    }

    /**
     * Test marking task as incomplete
     */
    public function test_it_marks_task_as_incomplete(): void
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
            'code' => 'PRJ-TEST-003',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create completed task
        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'status' => 'completed',
            'is_completed' => true,
            'completed_at' => now(),
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Mark as incomplete
        $response = $this->postJson("/api/v1/app/projects/{$project->id}/tasks/{$task->id}/incomplete");
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'is_completed',
                'completed_at',
            ],
        ]);
        
        $responseData = $response->json('data');
        $this->assertFalse($responseData['is_completed']);
        $this->assertNull($responseData['completed_at']);
        
        // Verify in database
        $task->refresh();
        $this->assertFalse($task->is_completed);
        $this->assertNull($task->completed_at);
        
        // Verify activity log
        $activity = ProjectActivity::where('project_id', $project->id)
            ->where('action', ProjectActivity::ACTION_PROJECT_TASK_MARKED_INCOMPLETE)
            ->where('entity_id', $task->id)
            ->first();
        
        $this->assertNotNull($activity);
        $this->assertEquals($this->userA->id, $activity->user_id);
    }

    /**
     * Test cross-tenant isolation
     */
    public function test_it_maintains_tenant_isolation(): void
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
            'code' => 'PRJ-TENANT-A-001',
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
            'code' => 'PRJ-TENANT-B-001',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'created_by' => $this->userB->id,
            'updated_by' => $this->userB->id,
        ]);
        
        // Try to update Tenant A's task from Tenant B - should fail
        $response = $this->patchJson("/api/v1/app/projects/{$projectA->id}/tasks/{$taskA->id}", [
            'status' => 'in_progress',
        ]);
        
        $response->assertStatus(404);
        
        // Try to complete Tenant A's task from Tenant B - should fail
        $response = $this->postJson("/api/v1/app/projects/{$projectA->id}/tasks/{$taskA->id}/complete");
        
        $response->assertStatus(404);
    }

    /**
     * Test that soft-deleted task cannot be updated
     */
    public function test_it_cannot_update_soft_deleted_task(): void
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
            'code' => 'PRJ-TEST-004',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create and soft-delete task
        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'project_id' => $project->id,
            'name' => 'Test Task',
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        $task->delete(); // Soft delete
        
        // Try to update soft-deleted task - should fail
        $response = $this->patchJson("/api/v1/app/projects/{$project->id}/tasks/{$task->id}", [
            'status' => 'in_progress',
        ]);
        
        $response->assertStatus(404);
        
        // Try to complete soft-deleted task - should fail
        $response = $this->postJson("/api/v1/app/projects/{$project->id}/tasks/{$task->id}/complete");
        
        $response->assertStatus(404);
    }

    /**
     * Test activity logging when tasks are generated from template
     */
    public function test_it_logs_activity_when_tasks_generated_from_template(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        // Create template
        $template = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Test Template',
            'category' => 'project',
            'status' => 'draft',
            'version' => 1,
            'is_active' => true,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create TaskTemplates
        $taskTemplate1 = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'template_id' => $template->id,
            'name' => 'Task 1',
            'order_index' => 1,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        $taskTemplate2 = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'template_id' => $template->id,
            'name' => 'Task 2',
            'order_index' => 2,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create project from template
        $response = $this->postJson("/api/v1/app/templates/{$template->id}/projects", [
            'name' => 'Test Project',
            'description' => 'Test project description',
            'code' => 'PRJ-TEST-005',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);
        
        $response->assertStatus(201);
        
        $projectId = $response->json('data.id');
        
        // Verify activity log
        $activity = ProjectActivity::where('project_id', $projectId)
            ->where('action', ProjectActivity::ACTION_PROJECT_TASKS_GENERATED_FROM_TEMPLATE)
            ->first();
        
        $this->assertNotNull($activity);
        $this->assertEquals($this->userA->id, $activity->user_id);
        $this->assertEquals($template->id, $activity->metadata['template_id']);
        $this->assertEquals(2, $activity->metadata['task_count']);
    }
}

