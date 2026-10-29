<?php

declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Approval;
use App\Models\DeliverableTemplate;
use App\Models\DeliverableTemplateVersion;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkInstanceFieldValue;
use App\Models\WorkInstanceStep;
use App\Models\WorkInstanceStepAttachment;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-015 Option A — web screens to work a WorkInstance (list, detail, update / approve a step, attachments, export).
 */
class WorkInstancePagesTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private const WORK_PERMISSIONS = ['project.view', 'work.view', 'work.update', 'work.approve', 'work.export', 'template.view'];

    private Tenant $tenant;

    private User $user;

    private Project $project;

    private WorkInstance $instance;

    private WorkInstanceStep $step;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant, [], ['admin'], self::WORK_PERMISSIONS);
        $this->headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        [$this->project, $this->instance, $this->step] = $this->seedInstance($this->tenant, $this->user);
    }

    public function test_index_lists_the_projects_instances_with_progress(): void
    {
        $this->actingAs($this->user)
            ->get($this->route('index'), $this->headers)
            ->assertOk()
            ->assertSee('Execution Template')
            ->assertSee('0/1');
    }

    public function test_project_page_links_to_the_work_instances(): void
    {
        $this->actingAs($this->user)
            ->get(route('app.projects.show', $this->project->id), $this->headers)
            ->assertOk()
            ->assertSee('Công việc theo mẫu')
            ->assertSee($this->route('index'), false);
    }

    public function test_show_lists_steps_and_field_values(): void
    {
        $this->actingAs($this->user)
            ->get($this->route('show'), $this->headers)
            ->assertOk()
            ->assertSee('QA Check')
            ->assertSee('Remark')
            ->assertSee('Needs a second look');
    }

    public function test_updating_a_step_persists_status_deadline_and_field_value(): void
    {
        $this->actingAs($this->user)->get($this->route('show'), $this->headers);

        $this->actingAs($this->user)
            ->post($this->route('steps.update'), [
                'status' => 'in_progress',
                'deadline_at' => '2026-11-30',
                'field_values' => ['remark' => 'Rebar checked'],
            ], $this->headers)
            ->assertRedirect($this->route('show'))
            ->assertSessionHas('success');

        $this->step->refresh();
        $this->assertSame('in_progress', $this->step->status);
        $this->assertSame('2026-11-30', $this->step->deadline_at?->toDateString());
        $this->assertDatabaseHas('work_instance_field_values', [
            'work_instance_step_id' => (string) $this->step->id,
            'field_key' => 'remark',
            'value_string' => 'Rebar checked',
        ]);
    }

    public function test_step_assignee_is_limited_to_users_of_the_tenant(): void
    {
        $colleague = $this->createTenantUser($this->tenant, ['name' => 'Site Engineer'], ['member'], []);
        $this->actingAs($this->user)->get($this->route('show'), $this->headers)->assertSee('Site Engineer');

        $this->actingAs($this->user)
            ->post($this->route('steps.update'), ['assignee_id' => (string) $colleague->id], $this->headers)
            ->assertRedirect($this->route('show'));

        $this->assertSame((string) $colleague->id, (string) $this->step->refresh()->assignee_id);

        $outsider = $this->createTenantUser(Tenant::factory()->create(), [], ['member'], []);

        $this->actingAs($this->user)
            ->post($this->route('steps.update'), ['assignee_id' => (string) $outsider->id], $this->headers)
            ->assertSessionHasErrors('assignee_id');

        $this->assertSame((string) $colleague->id, (string) $this->step->refresh()->assignee_id);
    }

    public function test_saving_other_fields_keeps_an_approved_status(): void
    {
        $this->step->update(['status' => 'approved']);
        $this->actingAs($this->user)->get($this->route('show'), $this->headers)
            ->assertSee('<option value="" selected>', false);

        $this->actingAs($this->user)
            ->post($this->route('steps.update'), ['status' => '', 'deadline_at' => '2026-12-01'], $this->headers)
            ->assertRedirect($this->route('show'));

        $this->step->refresh();
        $this->assertSame('approved', $this->step->status);
        $this->assertSame('2026-12-01', $this->step->deadline_at?->toDateString());
    }

    public function test_json_and_datetime_values_are_not_submitted_or_overwritten(): void
    {
        $this->step->update(['snapshot_fields_json' => [
            ['field_key' => 'remark', 'label' => 'Remark', 'type' => 'string'],
            ['field_key' => 'checklist', 'label' => 'Checklist', 'type' => 'json'],
            ['field_key' => 'checked_at', 'label' => 'Checked at', 'type' => 'datetime'],
        ]]);
        WorkInstanceFieldValue::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'work_instance_step_id' => (string) $this->step->id,
            'field_key' => 'checklist',
            'value_json' => ['rebar' => true],
        ]);
        WorkInstanceFieldValue::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'work_instance_step_id' => (string) $this->step->id,
            'field_key' => 'checked_at',
            'value_datetime' => '2026-10-20 08:30:00',
        ]);

        $page = $this->actingAs($this->user)->get($this->route('show'), $this->headers)->assertOk();
        $page->assertSee('name="field_values[remark]"', false)
            ->assertDontSee('name="field_values[checklist]"', false)
            ->assertDontSee('name="field_values[checked_at]"', false)
            ->assertSee('20/10/2026 08:30');

        $this->actingAs($this->user)
            ->post($this->route('steps.update'), ['field_values' => ['remark' => 'Updated']], $this->headers)
            ->assertRedirect($this->route('show'));

        $values = WorkInstanceFieldValue::query()->where('work_instance_step_id', (string) $this->step->id)->get()->keyBy('field_key');
        $this->assertSame(['rebar' => true], $values['checklist']->value_json);
        $this->assertSame('2026-10-20 08:30:00', $values['checked_at']->value_datetime?->format('Y-m-d H:i:s'));
        $this->assertSame('Updated', $values['remark']->value_string);
    }

    public function test_api_step_update_cannot_set_an_approval_status_without_work_approve(): void
    {
        $member = $this->createTenantUser($this->tenant, [], ['member'], ['work.view', 'work.update']);
        $url = route('api.zena.work-instances.steps.update', ['id' => (string) $this->instance->id, 'stepId' => (string) $this->step->id], false);
        $headers = [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenant->id,
            'Authorization' => 'Bearer ' . $member->createToken('test-token')->plainTextToken,
        ];

        foreach (['approved', 'rejected'] as $status) {
            $this->patchJson($url, ['status' => $status], $headers)->assertForbidden();
        }
        $this->assertSame('pending', $this->step->refresh()->status);

        $this->patchJson($url, ['status' => 'in_progress'], $headers)->assertOk();
        $this->assertSame('in_progress', $this->step->refresh()->status);

        $approverHeaders = array_merge($headers, ['Authorization' => 'Bearer ' . $this->user->createToken('test-token')->plainTextToken]);
        $this->patchJson($url, ['status' => 'approved'], $approverHeaders)->assertOk();
        $this->assertSame('approved', $this->step->refresh()->status);
    }

    public function test_step_form_keeps_deadline_time_and_allows_fractional_numbers(): void
    {
        $this->step->update([
            'deadline_at' => '2026-11-30 16:30:00',
            'snapshot_fields_json' => [['field_key' => 'slump', 'label' => 'Slump', 'type' => 'number']],
        ]);

        $this->actingAs($this->user)->get($this->route('show'), $this->headers)
            ->assertOk()
            ->assertSee('type="datetime-local"', false)
            ->assertSee('value="2026-11-30T16:30"', false)
            ->assertSee('step="any"', false);

        $this->actingAs($this->user)
            ->post($this->route('steps.update'), ['deadline_at' => '2026-11-30T16:30', 'field_values' => ['slump' => '1.25']], $this->headers)
            ->assertRedirect($this->route('show'));

        $this->assertSame('2026-11-30 16:30', $this->step->refresh()->deadline_at?->format('Y-m-d H:i'));
        $this->assertDatabaseHas('work_instance_field_values', ['work_instance_step_id' => (string) $this->step->id, 'field_key' => 'slump', 'value_number' => 1.25]);
    }

    public function test_multiline_text_survives_and_unchanged_fields_are_not_rewritten(): void
    {
        WorkInstanceFieldValue::query()->where('work_instance_step_id', (string) $this->step->id)->update(['value_string' => "Line one\nLine two"]);
        $before = WorkInstanceFieldValue::query()->where('work_instance_step_id', (string) $this->step->id)->firstOrFail();

        $this->actingAs($this->user)->get($this->route('show'), $this->headers)
            ->assertSee('<textarea', false)
            ->assertSee("Line one\nLine two", false);

        $this->travel(5)->minutes();

        // A browser posts the textarea back with CRLF line endings.
        $this->actingAs($this->user)
            ->post($this->route('steps.update'), ['status' => 'in_progress', 'field_values' => ['remark' => "Line one\r\nLine two"]], $this->headers)
            ->assertRedirect($this->route('show'));

        $after = $before->fresh();
        $this->assertSame("Line one\nLine two", $after?->value_string);
        $this->assertTrue($before->updated_at?->equalTo($after?->updated_at));
        $this->assertSame('in_progress', $this->step->refresh()->status);
    }

    public function test_approving_a_step_records_the_approval(): void
    {
        $this->actingAs($this->user)->get($this->route('show'), $this->headers);

        $this->actingAs($this->user)
            ->post($this->route('steps.approve'), ['decision' => 'approved', 'comment' => 'OK'], $this->headers)
            ->assertRedirect($this->route('show'))
            ->assertSessionHas('success');

        $this->assertSame('approved', $this->step->refresh()->status);
        $this->assertSame(1, Approval::query()->where('work_instance_step_id', (string) $this->step->id)->count());
    }

    public function test_attachment_upload_then_delete(): void
    {
        $this->actingAs($this->user)->get($this->route('show'), $this->headers);

        $this->actingAs($this->user)
            ->post($this->route('steps.attachments.store'), [
                'file' => UploadedFile::fake()->create('photo.pdf', 10, 'application/pdf'),
            ], $this->headers)
            ->assertRedirect($this->route('show'));

        $attachment = WorkInstanceStepAttachment::query()->where('work_instance_step_id', (string) $this->step->id)->firstOrFail();

        $this->actingAs($this->user)->get($this->route('show'), $this->headers)->assertSee('photo.pdf');

        $this->actingAs($this->user)
            ->post($this->route('steps.attachments.destroy', ['attachment' => (string) $attachment->id]), [], $this->headers)
            ->assertRedirect($this->route('show'));

        $this->assertDatabaseMissing('work_instance_step_attachments', ['id' => (string) $attachment->id]);
    }

    public function test_export_returns_the_rendered_file(): void
    {
        $version = $this->seedDeliverableVersion();
        $this->actingAs($this->user)->get($this->route('show'), $this->headers)->assertSee('Inspection Export');

        $response = $this->actingAs($this->user)
            ->post($this->route('export'), ['deliverable_template_version_id' => (string) $version->id], $this->headers);

        $response->assertOk();
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
    }

    public function test_approve_requires_work_approve_permission(): void
    {
        $member = $this->createTenantUser($this->tenant, [], ['member'], ['project.view', 'work.view', 'work.update']);
        $this->actingAs($member)->get($this->route('show'), $this->headers)->assertOk();

        $this->actingAs($member)
            ->post($this->route('steps.approve'), ['decision' => 'approved'], $this->headers)
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('pending', $this->step->refresh()->status);
    }

    public function test_other_tenant_or_other_project_instance_is_not_found(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['admin'], self::WORK_PERMISSIONS);
        [, $foreignInstance] = $this->seedInstance($otherTenant, $otherUser);

        $this->actingAs($this->user)
            ->get(route('app.projects.work-instances.show', ['project' => $this->project->id, 'instance' => $foreignInstance->id]), $this->headers)
            ->assertNotFound();

        [$otherProject] = $this->seedInstance($this->tenant, $this->user);

        $this->actingAs($this->user)
            ->get(route('app.projects.work-instances.show', ['project' => $otherProject->id, 'instance' => $this->instance->id]), $this->headers)
            ->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->route('index'))->assertRedirect();
        $this->assertGuest();
    }

    /**
     * @param array<string, string> $extra
     */
    private function route(string $name, array $extra = []): string
    {
        $parameters = ['project' => (string) $this->project->id];

        if ($name !== 'index') {
            $parameters['instance'] = (string) $this->instance->id;
        }

        if (str_starts_with($name, 'steps.')) {
            $parameters['step'] = (string) $this->step->id;
        }

        return route('app.projects.work-instances.' . $name, array_merge($parameters, $extra));
    }

    /**
     * @return array{0: Project, 1: WorkInstance, 2: WorkInstanceStep}
     */
    private function seedInstance(Tenant $tenant, User $user): array
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'North Tower',
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);

        $template = WorkTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'code' => 'WT-' . substr((string) Str::ulid(), -8),
            'name' => 'Execution Template',
            'status' => 'published',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $templateVersion = WorkTemplateVersion::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_template_id' => (string) $template->id,
            'semver' => '1.0.0',
            'content_json' => ['steps' => [], 'approvals' => [], 'rules' => []],
            'is_immutable' => true,
            'published_at' => now(),
            'published_by' => (string) $user->id,
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'work_template_version_id' => (string) $templateVersion->id,
            'status' => 'in_progress',
            'created_by' => (string) $user->id,
        ]);

        $step = WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_id' => (string) $instance->id,
            'step_key' => 'qa-check',
            'name' => 'QA Check',
            'type' => 'task',
            'step_order' => 1,
            'status' => 'pending',
            'snapshot_fields_json' => [['field_key' => 'remark', 'label' => 'Remark', 'type' => 'string']],
        ]);

        WorkInstanceFieldValue::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_step_id' => (string) $step->id,
            'field_key' => 'remark',
            'value_string' => 'Needs a second look',
        ]);

        return [$project, $instance, $step];
    }

    private function seedDeliverableVersion(): DeliverableTemplateVersion
    {
        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'code' => 'DT-' . substr((string) Str::ulid(), -8),
            'name' => 'Inspection Export',
            'status' => 'published',
            'created_by' => (string) $this->user->id,
            'updated_by' => (string) $this->user->id,
        ]);

        $storagePath = 'deliverable-templates/' . $this->tenant->id . '/' . $template->id . '/published/export.html';
        Storage::disk('local')->put($storagePath, '<html><body><h1>{{project.name}}</h1></body></html>');

        return DeliverableTemplateVersion::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'deliverable_template_id' => (string) $template->id,
            'version' => '1.0.0',
            'semver' => '1.0.0',
            'storage_path' => $storagePath,
            'checksum_sha256' => hash('sha256', 'unused'),
            'mime' => 'text/html',
            'size' => 0,
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'published_at' => now(),
            'published_by' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
            'updated_by' => (string) $this->user->id,
        ]);
    }
}
