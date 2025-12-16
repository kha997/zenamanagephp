<?php declare(strict_types=1);

namespace Tests\Feature;

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
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ProjectApplyTemplateTest
 * 
 * Feature tests for template application to projects.
 * Tests preview, apply, dependencies, mapping, and tenant isolation.
 */
class ProjectApplyTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Tenant $tenant;
    protected Project $project;
    protected TemplateSet $templateSet;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable feature flag
        config(['features.tasks.enable_wbs_templates' => true]);

        // Create tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'user@test.com',
            'password' => Hash::make('password'),
        ]);

        // Create project
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
        ]);

        // Create template set
        $this->templateSet = TemplateSet::factory()->create([
            'tenant_id' => null, // Global template
            'code' => 'TEST-TEMPLATE',
            'name' => 'Test Template',
            'is_global' => true,
            'created_by' => $this->user->id,
        ]);

        // Create phase
        $phase = TemplatePhase::factory()->create([
            'set_id' => $this->templateSet->id,
            'code' => 'PHASE1',
            'name' => 'Phase 1',
            'order_index' => 1,
        ]);

        // Create discipline
        $discipline = TemplateDiscipline::factory()->create([
            'set_id' => $this->templateSet->id,
            'code' => 'DISC1',
            'name' => 'Discipline 1',
            'order_index' => 1,
        ]);

        // Create tasks
        $task1 = TemplateTask::factory()->create([
            'set_id' => $this->templateSet->id,
            'phase_id' => $phase->id,
            'discipline_id' => $discipline->id,
            'code' => 'TASK1',
            'name' => 'Task 1',
            'order_index' => 1,
        ]);

        $task2 = TemplateTask::factory()->create([
            'set_id' => $this->templateSet->id,
            'phase_id' => $phase->id,
            'discipline_id' => $discipline->id,
            'code' => 'TASK2',
            'name' => 'Task 2',
            'order_index' => 2,
        ]);

        // Create dependency: TASK2 depends on TASK1
        TemplateTaskDependency::factory()->create([
            'set_id' => $this->templateSet->id,
            'task_id' => $task2->id,
            'depends_on_task_id' => $task1->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function user_can_preview_template_application()
    {
        $response = $this->postJson('/api/v1/app/template-sets/preview', [
            'set_id' => $this->templateSet->id,
            'project_id' => $this->project->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'total_tasks',
                'total_dependencies',
                'estimated_duration',
                'breakdown' => [
                    'phase',
                    'discipline',
                ],
            ],
        ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['total_tasks']);
    }

    /** @test */
    public function user_can_apply_template_to_project()
    {
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/apply-template", [
            'set_id' => $this->templateSet->id,
            'options' => [
                'conflict_behavior' => 'skip',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'tasks_created',
                'dependencies_created',
                'warnings',
                'errors',
            ],
        ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['tasks_created']);

        // Verify tasks were created
        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'name' => 'Task 1',
        ]);

        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'name' => 'Task 2',
        ]);

        // Verify dependencies were created
        $task1 = Task::where('project_id', $this->project->id)
            ->where('name', 'Task 1')
            ->first();
        $task2 = Task::where('project_id', $this->project->id)
            ->where('name', 'Task 2')
            ->first();

        $this->assertNotNull($task1);
        $this->assertNotNull($task2);

        $dependency = TaskDependency::where('task_id', $task2->id)
            ->where('dependency_id', $task1->id)
            ->first();
        $this->assertNotNull($dependency);

        // Verify log was created
        $this->assertDatabaseHas('template_apply_logs', [
            'project_id' => $this->project->id,
            'set_id' => $this->templateSet->id,
        ]);
    }

    /** @test */
    public function template_application_maps_disciplines_to_tags()
    {
        $discipline = $this->templateSet->disciplines()->first();
        $discipline->update(['color_hex' => '#FF0000']);

        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/apply-template", [
            'set_id' => $this->templateSet->id,
            'options' => [
                'conflict_behavior' => 'skip',
            ],
        ]);

        $response->assertOk();

        $task = Task::where('project_id', $this->project->id)->first();
        $this->assertNotNull($task);
        $tags = $task->tags ?? [];
        $this->assertContains($discipline->code, $tags);
    }

    /** @test */
    public function template_application_creates_deliverable_folders()
    {
        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/apply-template", [
            'set_id' => $this->templateSet->id,
            'options' => [
                'conflict_behavior' => 'skip',
                'create_deliverable_folders' => true,
            ],
        ]);

        $response->assertOk();

        $phase = $this->templateSet->phases()->first();
        $discipline = $this->templateSet->disciplines()->first();

        $folderPath = storage_path("app/projects/{$this->project->id}/deliverables/{$phase->code}/{$discipline->code}");
        $this->assertDirectoryExists($folderPath);
    }

    /** @test */
    public function tenant_user_cannot_apply_template_from_other_tenant()
    {
        // Create tenant B and template
        $tenantB = Tenant::factory()->create();
        $templateSetB = TemplateSet::factory()->create([
            'tenant_id' => $tenantB->id,
            'code' => 'TENANT-B-TEMPLATE',
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/app/projects/{$this->project->id}/apply-template", [
            'set_id' => $templateSetB->id,
        ]);

        $response->assertForbidden();
    }

    /** @test */
    public function user_can_view_template_application_history()
    {
        // Apply template first
        $this->postJson("/api/v1/app/projects/{$this->project->id}/apply-template", [
            'set_id' => $this->templateSet->id,
        ]);

        // Get history
        $response = $this->getJson("/api/v1/app/projects/{$this->project->id}/template-history");

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'set',
                    'counts',
                    'executor',
                    'created_at',
                ],
            ],
        ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->templateSet->id, $data[0]['set']['id']);
    }

    /** @test */
    public function preview_respects_preset_filters()
    {
        // Create preset
        $preset = TemplatePreset::factory()->create([
            'set_id' => $this->templateSet->id,
            'code' => 'PRESET1',
            'name' => 'Preset 1',
            'filters' => [
                'phases' => ['PHASE1'],
            ],
        ]);

        $response = $this->postJson('/api/v1/app/template-sets/preview', [
            'set_id' => $this->templateSet->id,
            'project_id' => $this->project->id,
            'preset_code' => 'PRESET1',
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['total_tasks']);
    }
}

