<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\TemplateSet;
use App\Models\TemplatePhase;
use App\Models\TemplateDiscipline;
use App\Models\TemplateTask;
use App\Models\TemplateTaskDependency;
use App\Models\TemplatePreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Task Templates API
 * 
 * Round 95: Task Template Library – Backend v1
 * 
 * Tests:
 * - 401 when not authenticated
 * - 403 when missing view/manage permissions
 * - Multi-tenant isolation (tenant A cannot read template tenant B)
 * - Shape validation (tree structure with phases/disciplines/tasks)
 * 
 * @group task-templates
 * @group tenant-permissions
 */
class TaskTemplatesTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private User $userAWithoutPermissions;
    private TemplateSet $templateSetA;
    private TemplateSet $templateSetB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(99999);
        $this->setDomainName('task-templates-test');
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
        
        // Create template set A in tenant A with full tree
        $this->templateSetA = TemplateSet::create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'TEMPLATE_A',
            'name' => 'Template Set A',
            'version' => '1.0',
            'is_active' => true,
            'created_by' => $this->userA->id,
        ]);
        
        $phaseA = TemplatePhase::create([
            'set_id' => $this->templateSetA->id,
            'code' => 'PHASE_1',
            'name' => 'Phase 1',
            'order_index' => 0,
        ]);
        
        $disciplineA = TemplateDiscipline::create([
            'set_id' => $this->templateSetA->id,
            'code' => 'DISC_1',
            'name' => 'Discipline 1',
            'order_index' => 0,
        ]);
        
        $taskA1 = TemplateTask::create([
            'set_id' => $this->templateSetA->id,
            'phase_id' => $phaseA->id,
            'discipline_id' => $disciplineA->id,
            'code' => 'TASK_1',
            'name' => 'Task 1',
            'order_index' => 0,
        ]);
        
        $taskA2 = TemplateTask::create([
            'set_id' => $this->templateSetA->id,
            'phase_id' => $phaseA->id,
            'discipline_id' => $disciplineA->id,
            'code' => 'TASK_2',
            'name' => 'Task 2',
            'order_index' => 1,
        ]);
        
        // Create dependency: taskA2 depends on taskA1
        TemplateTaskDependency::create([
            'set_id' => $this->templateSetA->id,
            'task_id' => $taskA2->id,
            'depends_on_task_id' => $taskA1->id,
        ]);
        
        // Create template set B in tenant B
        $this->templateSetB = TemplateSet::create([
            'tenant_id' => $this->tenantB->id,
            'code' => 'TEMPLATE_B',
            'name' => 'Template Set B',
            'version' => '1.0',
            'is_active' => true,
            'created_by' => $this->userB->id,
        ]);
    }

    /**
     * Test 401 when not authenticated
     */
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/app/task-templates');
        
        $response->assertStatus(401);
    }

    /**
     * Test 403 when missing view permission
     */
    public function test_requires_view_permission(): void
    {
        Sanctum::actingAs($this->userAWithoutPermissions);
        $token = $this->userAWithoutPermissions->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/task-templates');
        
        $response->assertStatus(403);
    }

    /**
     * Test 403 when missing manage permission for create
     */
    public function test_requires_manage_permission_for_create(): void
    {
        Sanctum::actingAs($this->userAWithoutPermissions);
        $token = $this->userAWithoutPermissions->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-' . uniqid(),
        ])->postJson('/api/v1/app/task-templates', [
            'code' => 'NEW_TEMPLATE',
            'name' => 'New Template',
        ]);
        
        $response->assertStatus(403);
    }

    /**
     * Test multi-tenant isolation: tenant A cannot see tenant B templates
     */
    public function test_tenant_a_cannot_see_tenant_b_templates(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/task-templates');
        
        $response->assertStatus(200);
        $templates = $response->json('data', []);
        
        // Verify template B is not in the list
        $templateIds = array_column($templates, 'id');
        $this->assertNotContains($this->templateSetB->id, $templateIds, 'Tenant B template should not be visible in tenant A');
        $this->assertContains($this->templateSetA->id, $templateIds, 'Tenant A template should be visible');
    }

    /**
     * Test multi-tenant isolation: tenant A cannot access tenant B template directly
     */
    public function test_tenant_a_cannot_access_tenant_b_template(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/task-templates/{$this->templateSetB->id}");
        
        // Should return 404 (not found) or 403 (forbidden)
        $this->assertContains($response->status(), [403, 404], 'Should not be able to access tenant B template');
    }

    /**
     * Test multi-tenant isolation: tenant A cannot update tenant B template
     */
    public function test_tenant_a_cannot_update_tenant_b_template(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-cross-tenant-' . uniqid(),
        ])->putJson("/api/v1/app/task-templates/{$this->templateSetB->id}", [
            'name' => 'Hacked Template Name',
        ]);
        
        // Should return 404 or 403
        $this->assertContains($response->status(), [403, 404], 'Should not be able to update tenant B template');
        
        // Verify template B is unchanged
        $this->templateSetB->refresh();
        $this->assertEquals('Template Set B', $this->templateSetB->name, 'Template should not be modified');
    }

    /**
     * Test shape validation: GET /task-templates/{set} returns correct tree structure
     */
    public function test_show_returns_correct_tree_structure(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/app/task-templates/{$this->templateSetA->id}");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Verify structure
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('phases', $data);
        $this->assertIsArray($data['phases']);
        
        // Verify phase structure
        $this->assertCount(1, $data['phases']);
        $phase = $data['phases'][0];
        $this->assertArrayHasKey('id', $phase);
        $this->assertArrayHasKey('code', $phase);
        $this->assertArrayHasKey('name', $phase);
        $this->assertArrayHasKey('disciplines', $phase);
        $this->assertIsArray($phase['disciplines']);
        
        // Verify discipline structure
        $this->assertCount(1, $phase['disciplines']);
        $discipline = $phase['disciplines'][0];
        $this->assertArrayHasKey('id', $discipline);
        $this->assertArrayHasKey('code', $discipline);
        $this->assertArrayHasKey('name', $discipline);
        $this->assertArrayHasKey('tasks', $discipline);
        $this->assertIsArray($discipline['tasks']);
        
        // Verify tasks structure
        $this->assertCount(2, $discipline['tasks']);
        $task1 = $discipline['tasks'][0];
        $this->assertArrayHasKey('id', $task1);
        $this->assertArrayHasKey('code', $task1);
        $this->assertArrayHasKey('name', $task1);
        $this->assertArrayHasKey('dependencies', $task1);
        $this->assertIsArray($task1['dependencies']);
        
        // Verify task2 has dependency on task1
        $task2 = $discipline['tasks'][1];
        $this->assertCount(1, $task2['dependencies']);
        $this->assertEquals($task1['id'], $task2['dependencies'][0]['id']);
    }

    /**
     * Test create template set
     */
    public function test_can_create_template_set(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-create-' . uniqid(),
        ])->postJson('/api/v1/app/task-templates', [
            'code' => 'NEW_TEMPLATE',
            'name' => 'New Template Set',
            'description' => 'Test description',
            'version' => '1.0',
            'is_active' => true,
        ]);
        
        $response->assertStatus(201);
        $data = $response->json('data');
        
        $this->assertEquals('NEW_TEMPLATE', $data['code']);
        $this->assertEquals('New Template Set', $data['name']);
        $this->assertEquals($this->tenantA->id, $data['tenant_id']);
    }

    /**
     * Test update template set metadata
     */
    public function test_can_update_template_set(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-update-' . uniqid(),
        ])->putJson("/api/v1/app/task-templates/{$this->templateSetA->id}", [
            'name' => 'Updated Template Name',
            'description' => 'Updated description',
        ]);
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals('Updated Template Name', $data['name']);
        $this->assertEquals('Updated description', $data['description']);
    }

    /**
     * Test duplicate template set
     */
    public function test_can_duplicate_template_set(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-duplicate-' . uniqid(),
        ])->postJson("/api/v1/app/task-templates/{$this->templateSetA->id}/duplicate", [
            'code' => 'TEMPLATE_A_COPY',
            'name' => 'Template Set A (Copy)',
        ]);
        
        $response->assertStatus(201);
        $data = $response->json('data');
        
        $this->assertEquals('TEMPLATE_A_COPY', $data['code']);
        $this->assertEquals('Template Set A (Copy)', $data['name']);
        
        // Verify the duplicate has phases, disciplines, and tasks
        $this->assertArrayHasKey('phases', $data);
        $this->assertCount(1, $data['phases']);
        $this->assertArrayHasKey('disciplines', $data['phases'][0]);
        $this->assertCount(1, $data['phases'][0]['disciplines']);
        $this->assertArrayHasKey('tasks', $data['phases'][0]['disciplines'][0]);
        $this->assertCount(2, $data['phases'][0]['disciplines'][0]['tasks']);
    }

    /**
     * Test list templates with pagination
     */
    public function test_list_templates_with_pagination(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/task-templates?per_page=10');
        
        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertIsArray($data['data']);
    }

    /**
     * Regression test: Preview breakdown must correctly group by phase and discipline
     * 
     * Round 96: Fix for TemplateApplyService::preview breakdown bug
     * 
     * Tests that breakdown correctly shows distinct phase/discipline codes with correct counts
     * when tasks are distributed across multiple phases and disciplines.
     */
    public function test_preview_breakdown_correctly_groups_by_phase_and_discipline(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // Create a template set with 2 phases and 2 disciplines
        $templateSet = TemplateSet::create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'MULTI_PHASE_TEMPLATE',
            'name' => 'Multi Phase Template',
            'version' => '1.0',
            'is_active' => true,
            'created_by' => $this->userA->id,
        ]);

        // Create Phase A
        $phaseA = TemplatePhase::create([
            'set_id' => $templateSet->id,
            'code' => 'PHASE-A',
            'name' => 'Phase A',
            'order_index' => 0,
        ]);

        // Create Phase B
        $phaseB = TemplatePhase::create([
            'set_id' => $templateSet->id,
            'code' => 'PHASE-B',
            'name' => 'Phase B',
            'order_index' => 1,
        ]);

        // Create Discipline 1
        $discipline1 = TemplateDiscipline::create([
            'set_id' => $templateSet->id,
            'code' => 'DISC-1',
            'name' => 'Discipline 1',
            'order_index' => 0,
        ]);

        // Create Discipline 2
        $discipline2 = TemplateDiscipline::create([
            'set_id' => $templateSet->id,
            'code' => 'DISC-2',
            'name' => 'Discipline 2',
            'order_index' => 1,
        ]);

        // Create tasks: 3 tasks in Phase A / Discipline 1
        TemplateTask::create([
            'set_id' => $templateSet->id,
            'phase_id' => $phaseA->id,
            'discipline_id' => $discipline1->id,
            'code' => 'TASK-A1-1',
            'name' => 'Task A1-1',
            'order_index' => 0,
        ]);
        TemplateTask::create([
            'set_id' => $templateSet->id,
            'phase_id' => $phaseA->id,
            'discipline_id' => $discipline1->id,
            'code' => 'TASK-A1-2',
            'name' => 'Task A1-2',
            'order_index' => 1,
        ]);
        TemplateTask::create([
            'set_id' => $templateSet->id,
            'phase_id' => $phaseA->id,
            'discipline_id' => $discipline1->id,
            'code' => 'TASK-A1-3',
            'name' => 'Task A1-3',
            'order_index' => 2,
        ]);

        // Create tasks: 2 tasks in Phase B / Discipline 2
        TemplateTask::create([
            'set_id' => $templateSet->id,
            'phase_id' => $phaseB->id,
            'discipline_id' => $discipline2->id,
            'code' => 'TASK-B2-1',
            'name' => 'Task B2-1',
            'order_index' => 0,
        ]);
        TemplateTask::create([
            'set_id' => $templateSet->id,
            'phase_id' => $phaseB->id,
            'discipline_id' => $discipline2->id,
            'code' => 'TASK-B2-2',
            'name' => 'Task B2-2',
            'order_index' => 1,
        ]);

        // Create a project
        $project = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
        ]);

        // Call preview endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-preview-' . uniqid(),
        ])->postJson('/api/v1/app/template-sets/preview', [
            'set_id' => $templateSet->id,
            'project_id' => $project->id,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');

        // Verify breakdown structure
        $this->assertArrayHasKey('breakdown', $data);
        $this->assertArrayHasKey('phase', $data['breakdown']);
        $this->assertArrayHasKey('discipline', $data['breakdown']);

        // Verify phase breakdown has both phases with correct counts
        $phaseBreakdown = $data['breakdown']['phase'];
        $this->assertArrayHasKey('PHASE-A', $phaseBreakdown, 'Phase A should be in breakdown');
        $this->assertArrayHasKey('PHASE-B', $phaseBreakdown, 'Phase B should be in breakdown');
        $this->assertEquals(3, $phaseBreakdown['PHASE-A'], 'Phase A should have 3 tasks');
        $this->assertEquals(2, $phaseBreakdown['PHASE-B'], 'Phase B should have 2 tasks');

        // Verify discipline breakdown has both disciplines with correct counts
        $disciplineBreakdown = $data['breakdown']['discipline'];
        $this->assertArrayHasKey('DISC-1', $disciplineBreakdown, 'Discipline 1 should be in breakdown');
        $this->assertArrayHasKey('DISC-2', $disciplineBreakdown, 'Discipline 2 should be in breakdown');
        $this->assertEquals(3, $disciplineBreakdown['DISC-1'], 'Discipline 1 should have 3 tasks');
        $this->assertEquals(2, $disciplineBreakdown['DISC-2'], 'Discipline 2 should have 2 tasks');

        // Verify total tasks count
        $this->assertEquals(5, $data['total_tasks'], 'Total tasks should be 5');
    }

    /**
     * Regression test: Duplicate template set must copy presets
     * 
     * Round 96: Fix for TaskTemplateService::duplicateTemplateSet missing presets
     * 
     * Tests that when duplicating a template set, all presets are copied to the new set
     * with correct FK references and preserved fields.
     */
    public function test_duplicate_template_set_copies_presets(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;

        // Create a template set with presets
        $sourceSet = TemplateSet::create([
            'tenant_id' => $this->tenantA->id,
            'code' => 'SOURCE_TEMPLATE',
            'name' => 'Source Template',
            'version' => '1.0',
            'is_active' => true,
            'created_by' => $this->userA->id,
        ]);

        // Create preset 1
        $preset1 = TemplatePreset::create([
            'set_id' => $sourceSet->id,
            'code' => 'PRESET-1',
            'name' => 'Preset 1',
            'description' => 'First preset',
            'filters' => [
                'phases' => ['PHASE-A'],
                'disciplines' => ['DISC-1'],
            ],
        ]);

        // Create preset 2
        $preset2 = TemplatePreset::create([
            'set_id' => $sourceSet->id,
            'code' => 'PRESET-2',
            'name' => 'Preset 2',
            'description' => 'Second preset',
            'filters' => [
                'phases' => ['PHASE-B'],
                'disciplines' => ['DISC-2'],
                'tasks' => ['TASK-1'],
            ],
        ]);

        // Verify source set has 2 presets
        $sourceSet->refresh();
        $this->assertEquals(2, $sourceSet->presets()->count(), 'Source set should have 2 presets');

        // Duplicate the template set
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'test-duplicate-presets-' . uniqid(),
        ])->postJson("/api/v1/app/task-templates/{$sourceSet->id}/duplicate", [
            'code' => 'DUPLICATE_TEMPLATE',
            'name' => 'Duplicate Template',
        ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $duplicateSetId = $data['id'];

        // Verify duplicated set exists
        $duplicateSet = TemplateSet::find($duplicateSetId);
        $this->assertNotNull($duplicateSet, 'Duplicated set should exist');

        // Verify duplicated set has the same number of presets
        $duplicatePresets = $duplicateSet->presets()->get();
        $this->assertCount(2, $duplicatePresets, 'Duplicated set should have 2 presets');

        // Verify preset fields match (but FK points to new set)
        $duplicatePreset1 = $duplicatePresets->firstWhere('code', 'PRESET-1');
        $this->assertNotNull($duplicatePreset1, 'Preset 1 should be duplicated');
        $this->assertEquals($duplicateSetId, $duplicatePreset1->set_id, 'Preset 1 should reference new set');
        $this->assertEquals('PRESET-1', $duplicatePreset1->code, 'Preset 1 code should match');
        $this->assertEquals('Preset 1', $duplicatePreset1->name, 'Preset 1 name should match');
        $this->assertEquals('First preset', $duplicatePreset1->description, 'Preset 1 description should match');
        $this->assertEquals($preset1->filters, $duplicatePreset1->filters, 'Preset 1 filters should match');

        $duplicatePreset2 = $duplicatePresets->firstWhere('code', 'PRESET-2');
        $this->assertNotNull($duplicatePreset2, 'Preset 2 should be duplicated');
        $this->assertEquals($duplicateSetId, $duplicatePreset2->set_id, 'Preset 2 should reference new set');
        $this->assertEquals('PRESET-2', $duplicatePreset2->code, 'Preset 2 code should match');
        $this->assertEquals('Preset 2', $duplicatePreset2->name, 'Preset 2 name should match');
        $this->assertEquals('Second preset', $duplicatePreset2->description, 'Preset 2 description should match');
        $this->assertEquals($preset2->filters, $duplicatePreset2->filters, 'Preset 2 filters should match');

        // Verify source presets remain unchanged
        $sourceSet->refresh();
        $this->assertEquals(2, $sourceSet->presets()->count(), 'Source set should still have 2 presets');
        $sourcePreset1 = $sourceSet->presets()->where('code', 'PRESET-1')->first();
        $this->assertEquals($sourceSet->id, $sourcePreset1->set_id, 'Source preset 1 should still reference source set');
    }
}

