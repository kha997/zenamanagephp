<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Project;
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
 * ProjectTaskFromTemplate API Test
 * 
 * Round 202: Auto-generate ProjectTasks from TaskTemplates when creating projects from templates
 * 
 * @group project-tasks
 * @group templates
 * @group api-v1
 */
class ProjectTaskFromTemplateApiTest extends TestCase
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
        $this->setDomainName('project-task-from-template-api');
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
     * Test that creating a project from template auto-generates ProjectTasks from TaskTemplates
     */
    public function test_it_auto_generates_project_tasks_from_task_templates(): void
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
        
        // Create TaskTemplates with different due_days_offset
        $taskTemplate1 = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'template_id' => $template->id,
            'name' => 'Task 1',
            'order_index' => 1,
            'metadata' => [
                'default_due_days_offset' => 0,
                'is_milestone' => false,
                'default_status' => 'pending',
            ],
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        $taskTemplate2 = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'template_id' => $template->id,
            'name' => 'Task 2',
            'order_index' => 2,
            'metadata' => [
                'default_due_days_offset' => 7,
                'is_milestone' => true,
                'default_status' => 'pending',
            ],
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create project from template with start_date
        $response = $this->postJson("/api/v1/app/templates/{$template->id}/projects", [
            'name' => 'Test Project',
            'description' => 'Test project description',
            'code' => 'PRJ-TEST-001',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);
        
        $response->assertStatus(201);
        
        $projectId = $response->json('data.id');
        $this->assertNotNull($projectId);
        
        // Assert ProjectTasks were created
        $projectTasks = ProjectTask::withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $resolvedTenantId)
            ->where('project_id', $projectId)
            ->get();
        
        $this->assertCount(2, $projectTasks, 'Should create 2 ProjectTasks from 2 TaskTemplates');
        
        // Assert first task
        $task1 = $projectTasks->firstWhere('template_task_id', $taskTemplate1->id);
        $this->assertNotNull($task1, 'Task 1 should be created');
        $this->assertEquals('Task 1', $task1->name);
        $this->assertEquals(1, $task1->sort_order);
        $this->assertEquals('2025-01-01', $task1->due_date->format('Y-m-d')); // start_date + 0 days
        $this->assertEquals('pending', $task1->status);
        $this->assertFalse($task1->is_milestone);
        
        // Assert second task
        $task2 = $projectTasks->firstWhere('template_task_id', $taskTemplate2->id);
        $this->assertNotNull($task2, 'Task 2 should be created');
        $this->assertEquals('Task 2', $task2->name);
        $this->assertEquals(2, $task2->sort_order);
        $this->assertEquals('2025-01-08', $task2->due_date->format('Y-m-d')); // start_date + 7 days
        $this->assertEquals('pending', $task2->status);
        $this->assertTrue($task2->is_milestone);
    }

    /**
     * Test that soft-deleted TaskTemplates are not used to generate ProjectTasks
     */
    public function test_it_does_not_create_tasks_from_soft_deleted_task_templates(): void
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
        
        // Create active TaskTemplate
        $activeTaskTemplate = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'template_id' => $template->id,
            'name' => 'Active Task',
            'order_index' => 1,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create and soft-delete TaskTemplate
        $deletedTaskTemplate = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'template_id' => $template->id,
            'name' => 'Deleted Task',
            'order_index' => 2,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        $deletedTaskTemplate->delete(); // Soft delete
        
        // Create project from template
        $response = $this->postJson("/api/v1/app/templates/{$template->id}/projects", [
            'name' => 'Test Project',
            'description' => 'Test project description',
            'code' => 'PRJ-TEST-002',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);
        
        $response->assertStatus(201);
        
        $projectId = $response->json('data.id');
        
        // Assert only active TaskTemplate generated a ProjectTask
        $projectTasks = ProjectTask::withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $resolvedTenantId)
            ->where('project_id', $projectId)
            ->get();
        
        $this->assertCount(1, $projectTasks, 'Should only create 1 ProjectTask from active TaskTemplate');
        
        $task = $projectTasks->first();
        $this->assertEquals($activeTaskTemplate->id, $task->template_task_id);
        $this->assertNotEquals($deletedTaskTemplate->id, $task->template_task_id);
    }

    /**
     * Test that ProjectTasks have null due_date when TaskTemplate has no default_due_days_offset
     * 
     * Note: Project validation now requires start_date, so we test the scenario where
     * TaskTemplate doesn't have default_due_days_offset in metadata, which results in null due_date
     * even when project has start_date.
     */
    public function test_it_creates_tasks_with_null_due_date_when_task_template_has_no_offset(): void
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
        
        // Create TaskTemplate WITHOUT default_due_days_offset in metadata
        $taskTemplate = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'template_id' => $template->id,
            'name' => 'Task without Offset',
            'order_index' => 1,
            'metadata' => [
                // No default_due_days_offset - this should result in null due_date
            ],
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create project from template with start_date (required by validation)
        $response = $this->postJson("/api/v1/app/templates/{$template->id}/projects", [
            'name' => 'Test Project',
            'description' => 'Test project description',
            'code' => 'PRJ-TEST-003',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);
        
        $response->assertStatus(201);
        
        $projectId = $response->json('data.id');
        
        // Assert ProjectTask has null due_date (because TaskTemplate has no default_due_days_offset)
        $projectTask = ProjectTask::withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $resolvedTenantId)
            ->where('project_id', $projectId)
            ->first();
        
        $this->assertNotNull($projectTask);
        $this->assertNull($projectTask->due_date, 'due_date should be null when TaskTemplate has no default_due_days_offset');
    }

    /**
     * Test multi-tenant isolation for ProjectTasks
     */
    public function test_it_maintains_tenant_isolation_for_project_tasks(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        // Get resolved tenant ID
        $tenancyService = app(\App\Services\TenancyService::class);
        $authenticatedUser = auth()->user();
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($authenticatedUser, request());
        
        // Create template for Tenant A
        $templateA = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'name' => 'Template A',
            'category' => 'project',
            'status' => 'draft',
            'version' => 1,
            'is_active' => true,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create TaskTemplate for Tenant A
        $taskTemplateA = TaskTemplate::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantId,
            'template_id' => $templateA->id,
            'name' => 'Tenant A Task',
            'order_index' => 1,
            'created_by' => $this->userA->id,
            'updated_by' => $this->userA->id,
        ]);
        
        // Create project from template for Tenant A
        $response = $this->postJson("/api/v1/app/templates/{$templateA->id}/projects", [
            'name' => 'Tenant A Project',
            'description' => 'Tenant A project description',
            'code' => 'PRJ-TENANT-A-001',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);
        
        $response->assertStatus(201);
        $projectIdA = $response->json('data.id');
        
        // Switch to Tenant B
        Sanctum::actingAs($this->userB, [], 'sanctum');
        
        $resolvedTenantIdB = $tenancyService->resolveActiveTenantId(auth()->user(), request());
        
        // Create template for Tenant B
        $templateB = Template::withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $resolvedTenantIdB,
            'name' => 'Template B',
            'category' => 'project',
            'status' => 'draft',
            'version' => 1,
            'is_active' => true,
            'created_by' => $this->userB->id,
            'updated_by' => $this->userB->id,
        ]);
        
        // Create project from template for Tenant B
        $response = $this->postJson("/api/v1/app/templates/{$templateB->id}/projects", [
            'name' => 'Tenant B Project',
            'description' => 'Tenant B project description',
            'code' => 'PRJ-TENANT-B-001',
            'status' => 'planning',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]);
        
        $response->assertStatus(201);
        $projectIdB = $response->json('data.id');
        
        // Assert Tenant A's ProjectTasks are isolated
        $tenantATasks = ProjectTask::withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $resolvedTenantId)
            ->where('project_id', $projectIdA)
            ->get();
        
        $this->assertCount(1, $tenantATasks);
        $this->assertEquals((string) $resolvedTenantId, $tenantATasks->first()->tenant_id);
        
        // Assert Tenant B cannot see Tenant A's tasks
        $tenantBTasks = ProjectTask::withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $resolvedTenantIdB)
            ->where('project_id', $projectIdB)
            ->get();
        
        // Tenant B's project should have no tasks (no TaskTemplates created)
        $this->assertCount(0, $tenantBTasks);
    }
}
