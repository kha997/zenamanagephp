<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\InteractsWithWorkTemplateV2;
use Tests\TestCase;

class WorkTemplateV2ApiTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithWorkTemplateV2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkTemplateV2Routes();
    }

    public function test_v2_template_crud_publish_and_version_show_flow(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], [
            'template.view',
            'template.edit_draft',
            'template.publish',
            'template.delete',
        ]);

        $create = $this->postJson(
            $this->workTemplateRoute('store'),
            $this->workTemplateV2Payload('WT-V2-FLOW'),
            $this->authHeaders($user)
        );

        $create->assertStatus(201)
            ->assertJsonPath('data.code', 'WT-V2-FLOW')
            ->assertJsonPath('data.draft_version.schema_version', 2)
            ->assertJsonPath('data.draft_version.phases.0.tasks.0.required_documents.0.key', 'design-drawing')
            ->assertJsonPath('data.draft_version.phases.0.tasks.0.assignments.1.assignment_type', 'approver')
            ->assertJsonPath('data.draft_version.phases.0.tasks.0.triggers.0.action', 'notify_role');

        $templateId = (string) $create->json('data.id');

        $show = $this->getJson($this->workTemplateRoute('show', ['id' => $templateId]), $this->authHeaders($user));
        $show->assertOk()
            ->assertJsonPath('data.draft_version.phases.0.key', 'design');

        $updatePayload = $this->workTemplateV2Payload('WT-V2-FLOW');
        $updatePayload['name'] = 'Updated Design Baseline';
        $updatePayload['phases'][0]['tasks'][0]['name'] = 'Submit Updated Drawings';
        $updatePayload['phases'][0]['tasks'][0]['checklist_items'][] = [
            'key' => 'title-block',
            'label' => 'Title block completed',
            'order' => 2,
            'is_required' => true,
        ];

        $update = $this->putJson(
            $this->workTemplateRoute('update', ['id' => $templateId]),
            $updatePayload,
            $this->authHeaders($user)
        );

        $update->assertOk()
            ->assertJsonPath('data.name', 'Updated Design Baseline')
            ->assertJsonPath('data.draft_version.phases.0.tasks.0.name', 'Submit Updated Drawings')
            ->assertJsonPath('data.draft_version.phases.0.tasks.0.checklist_items.1.key', 'title-block');

        $publish = $this->postJson($this->workTemplateRoute('publish', ['id' => $templateId]), [], $this->authHeaders($user));
        $publish->assertOk()
            ->assertJsonPath('data.schema_version', 2)
            ->assertJsonPath('data.phases.0.tasks.0.name', 'Submit Updated Drawings');

        $publishedVersionId = (string) $publish->json('data.id');

        $versionShow = $this->getJson(
            $this->workTemplateRoute('versions.show', ['id' => $templateId, 'versionId' => $publishedVersionId]),
            $this->authHeaders($user)
        );

        $versionShow->assertOk()
            ->assertJsonPath('data.id', $publishedVersionId)
            ->assertJsonPath('data.phases.0.tasks.0.checklist_items.1.key', 'title-block');

        $delete = $this->deleteJson($this->workTemplateRoute('destroy', ['id' => $templateId]), [], $this->authHeaders($user));
        $delete->assertOk();

        $this->assertSoftDeleted('work_templates', ['id' => $templateId]);

        $this->getJson($this->workTemplateRoute('show', ['id' => $templateId]), $this->authHeaders($user))
            ->assertStatus(404);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $user->id,
            'action' => 'zena.work-template.create',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $user->id,
            'action' => 'zena.work-template.update',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $user->id,
            'action' => 'zena.work-template.publish',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $user->id,
            'action' => 'zena.work-template.delete',
        ]);

        $this->assertSame(
            4,
            AuditLog::query()->where('tenant_id', $tenant->id)->whereIn('action', [
                'zena.work-template.create',
                'zena.work-template.update',
                'zena.work-template.publish',
                'zena.work-template.delete',
            ])->count()
        );
    }

    public function test_index_include_returns_nested_draft_and_published_versions_for_v2_templates(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], [
            'template.view',
            'template.edit_draft',
            'template.publish',
        ]);

        [$template, $draftVersion] = $this->seedV2Template($tenant, $user, 'WT-V2-LIST');

        $publishedVersion = WorkTemplateVersion::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_template_id' => (string) $template->id,
            'semver' => '1.0.0',
            'schema_version' => 2,
            'content_json' => $draftVersion->content_json,
            'is_immutable' => true,
            'published_at' => now(),
            'published_by' => (string) $user->id,
            'source_version_id' => (string) $draftVersion->id,
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $response = $this->getJson(
            $this->workTemplateRoute('index') . '?include=draft_version,published_versions',
            $this->authHeaders($user)
        );

        $response->assertOk()
            ->assertJsonPath('data.0.id', (string) $template->id)
            ->assertJsonPath('data.0.draft_version.id', (string) $draftVersion->id)
            ->assertJsonPath('data.0.published_versions.0.id', (string) $publishedVersion->id);
    }
}
