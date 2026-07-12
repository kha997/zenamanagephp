<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
use App\Models\WorkTemplateStep;
use App\Services\DocumentChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentChecklistServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(): Project
    {
        $tenant = Tenant::factory()->create();

        return Project::factory()->create(['tenant_id' => (string) $tenant->id]);
    }

    public function test_flags_missing_required_document_type(): void
    {
        $project = $this->makeProject();

        $templateStep = WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'name' => 'Issue Drawings',
            'required_document_types' => ['drawing', 'specification'],
        ]);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'scope_type' => 'project',
        ]);

        WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'work_instance_id' => (string) $instance->id,
            'work_template_step_id' => (string) $templateStep->id,
            'name' => 'Issue Drawings',
        ]);

        Document::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'document_type' => 'drawing',
        ]);

        $report = (new DocumentChecklistService())->buildReport($project);

        $this->assertCount(1, $report);
        $this->assertSame('Issue Drawings', $report[0]['step_name']);
        $this->assertSame(['drawing', 'specification'], $report[0]['required']);
        $this->assertSame(['specification'], $report[0]['missing']);
    }

    public function test_reports_no_missing_types_when_all_present(): void
    {
        $project = $this->makeProject();

        $templateStep = WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'name' => 'Final Review',
            'required_document_types' => ['report'],
        ]);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'scope_type' => 'project',
        ]);

        WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'work_instance_id' => (string) $instance->id,
            'work_template_step_id' => (string) $templateStep->id,
            'name' => 'Final Review',
        ]);

        Document::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'document_type' => 'report',
        ]);

        $report = (new DocumentChecklistService())->buildReport($project);

        $this->assertCount(1, $report);
        $this->assertSame([], $report[0]['missing']);
    }

    public function test_omits_steps_with_no_required_document_types(): void
    {
        $project = $this->makeProject();

        $templateStep = WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'required_document_types' => null,
        ]);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'scope_type' => 'project',
        ]);

        WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'work_instance_id' => (string) $instance->id,
            'work_template_step_id' => (string) $templateStep->id,
        ]);

        $report = (new DocumentChecklistService())->buildReport($project);

        $this->assertSame([], $report);
    }

    public function test_ignores_work_instances_scoped_to_component(): void
    {
        $project = $this->makeProject();

        $templateStep = WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'required_document_types' => ['photo'],
        ]);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'scope_type' => 'component',
        ]);

        WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'work_instance_id' => (string) $instance->id,
            'work_template_step_id' => (string) $templateStep->id,
        ]);

        $report = (new DocumentChecklistService())->buildReport($project);

        $this->assertSame([], $report);
    }

    public function test_ignores_documents_from_another_project(): void
    {
        $project = $this->makeProject();
        $otherProject = Project::factory()->create(['tenant_id' => (string) $project->tenant_id]);

        $templateStep = WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'required_document_types' => ['drawing'],
        ]);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'scope_type' => 'project',
        ]);

        WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'work_instance_id' => (string) $instance->id,
            'work_template_step_id' => (string) $templateStep->id,
        ]);

        Document::factory()->create([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $otherProject->id,
            'document_type' => 'drawing',
        ]);

        $report = (new DocumentChecklistService())->buildReport($project);

        $this->assertSame(['drawing'], $report[0]['missing']);
    }
}
