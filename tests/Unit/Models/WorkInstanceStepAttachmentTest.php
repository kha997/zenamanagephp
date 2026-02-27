<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
use App\Models\WorkInstanceStepAttachment;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkInstanceStepAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_step_and_casts_file_size(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);

        $template = WorkTemplate::create([
            'tenant_id' => (string) $tenant->id,
            'code' => 'WT-ATTACH',
            'name' => 'Attachment Template',
            'status' => 'draft',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $version = WorkTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'work_template_id' => (string) $template->id,
            'semver' => 'draft-initial',
            'content_json' => ['steps' => []],
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $instance = WorkInstance::create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'work_template_version_id' => (string) $version->id,
            'status' => 'pending',
            'created_by' => (string) $user->id,
        ]);

        $step = WorkInstanceStep::create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_id' => (string) $instance->id,
            'step_key' => 'step-1',
            'name' => 'Step 1',
            'type' => 'task',
            'step_order' => 1,
            'status' => 'pending',
        ]);

        $attachment = WorkInstanceStepAttachment::create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_step_id' => (string) $step->id,
            'file_name' => 'test.pdf',
            'file_path' => 'work-instances/path/test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => '2048',
            'uploaded_by' => (string) $user->id,
        ]);

        $this->assertInstanceOf(WorkInstanceStep::class, $attachment->step);
        $this->assertSame((string) $step->id, (string) $attachment->step->id);
        $this->assertSame(2048, $attachment->file_size);
        $this->assertSame((string) $attachment->id, (string) $step->attachments()->firstOrFail()->id);
    }
}
