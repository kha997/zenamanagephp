<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\TemplateSet;
use App\Models\TemplatePhase;
use App\Models\TemplateDiscipline;
use App\Models\TemplateTask;
use App\Models\TemplateTaskDependency;
use App\Models\TemplatePreset;
use App\Models\TemplateApplyLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Task Templates Apply API
 * 
 * Round 97: Apply Template Set → Create Project Tasks
 * 
 * Tests:
 * - 401 when not authenticated
 * - 403 when missing manage_tasks permission
 * - 403/404 when accessing project from another tenant
 * - Happy path: apply template creates tasks and dependencies
 * - Preset filtering: apply with preset creates subset of tasks
 * - Dependencies: dependencies created correctly, skipped when filtered out
 * - Idempotency: same idempotency key does not duplicate tasks
 * 
 * @group task-templates
 * @group task-templates-apply
 * @group tenant-permissions
 */
class TaskTemplatesApplyTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private User $userAWithoutPermissions;
    private Project $projectA;
    private Project $projectB;
    private TemplateSet $templateSetA;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(88888);
        $this->setDomainName('task-templates-apply-test');
        $this->setupDomainIsolation();
        
        // Create tenant A
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Test Tenant A',
            'slug' => 'test-tenant-a-' . uniqid(),
        ]);
        
        // Create tenant B
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Test Tenant B',
            'slug' => 'test-tenant-b-' . uniqid(),
        ]);
        
        // Create user A in tenant A with owner role (has all permissions)
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create user B in tenant B with owner role
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create user A without permissions (member role)
        $this->userAWithoutPermissions = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userAWithoutPermissions->tenants()->attach($this->tenantA->id, [
            'role' => 'member',
            'is_default' => true,
        ]);
        
        // Create projects
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A',
        ]);
        
        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B',
        ]);
        
        // Create template set A in tenant A with full tree
        $this->templateSetA = TemplateSet::create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'TEMPLATE_A',
            'name' => 'Template Set A',
            'version' => '1.0',
            'is_active' => true,
            'created_by' => $this->userA->id,
        ]);
        
        // Create Phase 1
        $phase1 = TemplatePhase::create([
            'set_id' => $this->templateSetA->id,
            'code' => 'PHASE_1',
            'name' => 'Phase 1',
            'order_index' => 0,
        ]);
        
        // Create Phase 2
        $phase2 = TemplatePhase::create([
            'set_id' => $this->templateSetA->id,
            'code' => 'PHASE_2',
            'name' => 'Phase 2',
            'order_index' => 1,
        ]);
        
        // Create Discipline 1
        $discipline1 = TemplateDiscipline::create([
            'set_id' => $this->templateSetA->id,
            'code' => 'DISC_1',
            'name' => 'Discipline 1',
            'order_index' => 0,
        ]);
        
        // Create Discipline 2
        $discipline2 = TemplateDiscipline::create([
            'set_id' => $this->templateSetA->id,
            'code' => 'DISC_2',
            'name' => 'Discipline 2',
            'order_index' => 1,
        ]);
        
        // Create tasks: 2 tasks in Phase 1 / Discipline 1
        $task1 = TemplateTask::create([
            'set_id' => $this->templateSetA->id,
            'phase_id' => $phase1->id,
            'discipline_id' => $discipline1->id,
            'code' => 'TASK_1',
            'name' => 'Task 1',
            'order_index' => 0,
        ]);
        
        $task2 = TemplateTask::create([
            'set_id' => $this->templateSetA->id,
            'phase_id' => $phase1->id,
            'discipline_id' => $discipline1->id,
            'code' => 'TASK_2',
            'name' => 'Task 2',
            'order_index' => 1,
        ]);
        
        // Create tasks: 2 tasks in Phase 2 / Discipline 2
        $task3 = TemplateTask::create([
            'set_id' => $this->templateSetA->id,
            'phase_id' => $phase2->id,
            'discipline_id' => $discipline2->id,
            'code' => 'TASK_3',
            'name' => 'Task 3',
            'order_index' => 0,
        ]);
        
        $task4 = TemplateTask::create([
            'set_id' => $this->templateSetA->id,
            'phase_id' => $phase2->id,
            'discipline_id' => $discipline2->id,
            'code' => 'TASK_4',
            'name' => 'Task 4',
            'order_index' => 1,
        ]);
        
        // Create dependencies: task2 depends on task1, task4 depends on task3
        TemplateTaskDependency::create([
            'set_id' => $this->templateSetA->id,
            'task_id' => $task2->id,
            'depends_on_task_id' => $task1->id,
        ]);
        
        TemplateTaskDependency::create([
            'set_id' => $this->templateSetA->id,
            'task_id' => $task4->id,
            'depends_on_task_id' => $task3->id,
        ]);
    }

    /**
     * Test 401 when not authenticated
     */
    public function test_requires_authentication(): void
    {
        $response = $this->postJson("/api/v1/app/projects/{$this->projectA->id}/task-templates/apply", [
            'template_set_id' => $this->templateSetA->id,
        ]);
        
        $response->assertStatus(401);
    }

    /**
     * Test 403 when missing manage_tasks permission
     */
    public function test_requires_manage_tasks_permission(): void
    {
        Sanctum::actingAs($this->userAWithoutPermissions);
        $token = $this->userAWithoutPermissions->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-' . uniqid(),
        ])->postJson("/api/v1/app/projects/{$this->projectA->id}/task-templates/apply", [
            'template_set_id' => $this->templateSetA->id,
        ]);
        
        $response->assertStatus(403);
    }

    /**
     * Test 404 when accessing project from another tenant
     */
    public function test_cannot_access_project_from_another_tenant(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-cross-tenant-' . uniqid(),
        ])->postJson("/api/v1/app/projects/{$this->projectB->id}/task-templates/apply", [
            'template_set_id' => $this->templateSetA->id,
        ]);
        
        $response->assertStatus(404);
    }

    /**
     * Test happy path: apply template creates tasks and dependencies
     */
    public function test_apply_template_creates_tasks_and_dependencies(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $idempotencyKey = 'test-apply-' . uniqid();
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson("/api/v1/app/projects/{$this->projectA->id}/task-templates/apply", [
            'template_set_id' => $this->templateSetA->id,
            'options' => [
                'include_dependencies' => true,
            ],
        ]);
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Verify response structure
        $this->assertArrayHasKey('project_id', $data);
        $this->assertArrayHasKey('template_set_id', $data);
        $this->assertArrayHasKey('created_tasks', $data);
        $this->assertArrayHasKey('created_dependencies', $data);
        
        // Verify counts
        $this->assertEquals(4, $data['created_tasks'], 'Should create 4 tasks');
        $this->assertEquals(2, $data['created_dependencies'], 'Should create 2 dependencies');
        
        // Verify tasks were created in database
        $tasks = Task::where('project_id', $this->projectA->id)->get();
        $this->assertCount(4, $tasks, 'Should have 4 tasks in database');
        
        // Verify task names
        $taskNames = $tasks->pluck('name')->toArray();
        $this->assertContains('Task 1', $taskNames);
        $this->assertContains('Task 2', $taskNames);
        $this->assertContains('Task 3', $taskNames);
        $this->assertContains('Task 4', $taskNames);
        
        // Verify dependencies were created
        $dependencies = TaskDependency::whereIn('task_id', $tasks->pluck('id'))
            ->whereIn('dependency_id', $tasks->pluck('id'))
            ->get();
        $this->assertCount(2, $dependencies, 'Should have 2 dependencies in database');
        
        // Round 98: Verify apply log was created with correct data
        $applyLog = TemplateApplyLog::where('project_id', $this->projectA->id)
            ->where('set_id', $this->templateSetA->id)
            ->first();
        
        $this->assertNotNull($applyLog, 'Apply log should exist');
        $this->assertEquals($this->tenantA->id, $applyLog->tenant_id, 'Log should have correct tenant_id');
        $this->assertEquals($this->projectA->id, $applyLog->project_id, 'Log should have correct project_id');
        $this->assertEquals($this->templateSetA->id, $applyLog->set_id, 'Log should have correct set_id');
        $this->assertNull($applyLog->preset_id, 'Log should have null preset_id when no preset used');
        $this->assertNotNull($applyLog->options, 'Log should have options');
        $this->assertIsArray($applyLog->options, 'Options should be an array');
        $this->assertTrue($applyLog->options['include_dependencies'] ?? false, 'Options should include include_dependencies=true');
    }

    /**
     * Test preset filtering: apply with preset creates subset of tasks
     */
    public function test_apply_with_preset_creates_subset_of_tasks(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        // Create preset that filters to Phase 1 only
        $preset = TemplatePreset::create([
            'set_id' => $this->templateSetA->id,
            'code' => 'PRESET_PHASE_1',
            'name' => 'Phase 1 Only',
            'filters' => [
                'phases' => ['PHASE_1'],
            ],
        ]);
        
        $idempotencyKey = 'test-preset-' . uniqid();
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson("/api/v1/app/projects/{$this->projectA->id}/task-templates/apply", [
            'template_set_id' => $this->templateSetA->id,
            'preset_id' => $preset->id,
            'options' => [
                'include_dependencies' => true,
            ],
        ]);
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Verify only 2 tasks were created (Phase 1 tasks)
        $this->assertEquals(2, $data['created_tasks'], 'Should create 2 tasks with preset');
        $this->assertEquals(1, $data['created_dependencies'], 'Should create 1 dependency (task2 -> task1)');
        
        // Verify tasks in database
        $tasks = Task::where('project_id', $this->projectA->id)->get();
        $this->assertCount(2, $tasks, 'Should have 2 tasks in database');
        
        $taskNames = $tasks->pluck('name')->toArray();
        $this->assertContains('Task 1', $taskNames);
        $this->assertContains('Task 2', $taskNames);
        $this->assertNotContains('Task 3', $taskNames);
        $this->assertNotContains('Task 4', $taskNames);
        
        // Verify dependency exists (task2 -> task1)
        $task1 = $tasks->firstWhere('name', 'Task 1');
        $task2 = $tasks->firstWhere('name', 'Task 2');
        $dependency = TaskDependency::where('task_id', $task2->id)
            ->where('dependency_id', $task1->id)
            ->first();
        $this->assertNotNull($dependency, 'Dependency task2 -> task1 should exist');
        
        // Round 98: Verify apply log was created with preset_id and options
        $applyLog = TemplateApplyLog::where('project_id', $this->projectA->id)
            ->where('set_id', $this->templateSetA->id)
            ->where('preset_id', $preset->id)
            ->first();
        
        $this->assertNotNull($applyLog, 'Apply log should exist');
        $this->assertEquals($preset->id, $applyLog->preset_id, 'Log should have correct preset_id');
        $this->assertNotNull($applyLog->options, 'Log should have options');
        $this->assertIsArray($applyLog->options, 'Options should be an array');
        $this->assertTrue($applyLog->options['include_dependencies'] ?? false, 'Options should include include_dependencies=true');
    }

    /**
     * Test dependencies are skipped when filtered out
     */
    public function test_dependencies_skipped_when_filtered_out(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        // Create preset that filters to only task2 (task2 depends on task1, but task1 is filtered out)
        $preset = TemplatePreset::create([
            'set_id' => $this->templateSetA->id,
            'code' => 'PRESET_TASK_2_ONLY',
            'name' => 'Task 2 Only',
            'filters' => [
                'tasks' => ['TASK_2'],
            ],
        ]);
        
        $idempotencyKey = 'test-filtered-deps-' . uniqid();
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson("/api/v1/app/projects/{$this->projectA->id}/task-templates/apply", [
            'template_set_id' => $this->templateSetA->id,
            'preset_id' => $preset->id,
            'options' => [
                'include_dependencies' => true,
            ],
        ]);
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Verify only 1 task was created
        $this->assertEquals(1, $data['created_tasks'], 'Should create 1 task');
        // Dependency should be skipped because task1 is not in the selected subset
        $this->assertEquals(0, $data['created_dependencies'], 'Should skip dependency when task1 is filtered out');
        
        // Verify only task2 exists
        $tasks = Task::where('project_id', $this->projectA->id)->get();
        $this->assertCount(1, $tasks, 'Should have 1 task in database');
        $this->assertEquals('Task 2', $tasks->first()->name);
        
        // Verify no dependencies exist
        $dependencies = TaskDependency::whereIn('task_id', $tasks->pluck('id'))->get();
        $this->assertCount(0, $dependencies, 'Should have no dependencies');
    }

    /**
     * Test idempotency: same idempotency key does not duplicate tasks
     */
    public function test_idempotency_prevents_duplicate_tasks(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $idempotencyKey = 'test-idempotency-' . uniqid();
        
        // First call
        $response1 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson("/api/v1/app/projects/{$this->projectA->id}/task-templates/apply", [
            'template_set_id' => $this->templateSetA->id,
            'options' => [
                'include_dependencies' => true,
            ],
        ]);
        
        $response1->assertStatus(200);
        $data1 = $response1->json('data');
        $this->assertEquals(4, $data1['created_tasks']);
        $this->assertEquals(2, $data1['created_dependencies']);
        
        // Second call with same idempotency key
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson("/api/v1/app/projects/{$this->projectA->id}/task-templates/apply", [
            'template_set_id' => $this->templateSetA->id,
            'options' => [
                'include_dependencies' => true,
            ],
        ]);
        
        $response2->assertStatus(200);
        $data2 = $response2->json('data');
        
        // Verify same response (idempotent)
        $this->assertEquals($data1['created_tasks'], $data2['created_tasks']);
        $this->assertEquals($data1['created_dependencies'], $data2['created_dependencies']);
        
        // Verify no duplicate tasks in database
        $tasks = Task::where('project_id', $this->projectA->id)->get();
        $this->assertCount(4, $tasks, 'Should still have only 4 tasks');
        
        // Verify no duplicate dependencies
        $dependencies = TaskDependency::whereIn('task_id', $tasks->pluck('id'))
            ->whereIn('dependency_id', $tasks->pluck('id'))
            ->get();
        $this->assertCount(2, $dependencies, 'Should still have only 2 dependencies');
        
        // Verify response header indicates idempotent replay
        $this->assertEquals('true', $response2->headers->get('X-Idempotent-Replayed'));
        
        // Round 98: Verify only one apply log was created (idempotency prevents duplicate logs)
        $applyLogs = TemplateApplyLog::where('project_id', $this->projectA->id)
            ->where('set_id', $this->templateSetA->id)
            ->get();
        
        $this->assertCount(1, $applyLogs, 'Should have exactly 1 apply log entry (idempotency prevents duplicates)');
        
        $applyLog = $applyLogs->first();
        $this->assertNull($applyLog->preset_id, 'Log should have null preset_id when no preset used');
        $this->assertNotNull($applyLog->options, 'Log should have options');
        $this->assertTrue($applyLog->options['include_dependencies'] ?? false, 'Options should include include_dependencies=true');
    }

    /**
     * Test include_dependencies option: false skips dependency creation
     */
    public function test_include_dependencies_false_skips_dependencies(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $idempotencyKey = 'test-no-deps-' . uniqid();
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson("/api/v1/app/projects/{$this->projectA->id}/task-templates/apply", [
            'template_set_id' => $this->templateSetA->id,
            'options' => [
                'include_dependencies' => false,
            ],
        ]);
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Verify tasks created but no dependencies
        $this->assertEquals(4, $data['created_tasks'], 'Should create 4 tasks');
        $this->assertEquals(0, $data['created_dependencies'], 'Should not create dependencies');
        
        // Verify no dependencies in database
        $tasks = Task::where('project_id', $this->projectA->id)->get();
        $dependencies = TaskDependency::whereIn('task_id', $tasks->pluck('id'))->get();
        $this->assertCount(0, $dependencies, 'Should have no dependencies');
        
        // Round 98: Verify apply log was created with include_dependencies=false
        $applyLog = TemplateApplyLog::where('project_id', $this->projectA->id)
            ->where('set_id', $this->templateSetA->id)
            ->first();
        
        $this->assertNotNull($applyLog, 'Apply log should exist');
        $this->assertNotNull($applyLog->options, 'Log should have options');
        $this->assertFalse($applyLog->options['include_dependencies'] ?? true, 'Options should include include_dependencies=false');
    }
}

