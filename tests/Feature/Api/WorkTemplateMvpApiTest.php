<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
use App\Models\WorkInstanceStepAttachment;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateField;
use App\Models\WorkTemplateStep;
use App\Models\WorkTemplateVersion;
use App\Services\WorkTemplatePackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class WorkTemplateMvpApiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    public function test_tenant_isolation_anti_enumeration_for_work_template_and_work_instance_ids(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $actorA = $this->createTenantUser($tenantA, [], ['member'], ['template.view', 'work.export']);
        $actorB = $this->createTenantUser($tenantB, [], ['member'], ['template.view', 'work.export']);

        $projectB = Project::factory()->create(['tenant_id' => $tenantB->id, 'created_by' => $actorB->id, 'pm_id' => $actorB->id]);
        $templateB = $this->createDraftTemplate($tenantB, $actorB);

        $draft = WorkTemplateVersion::query()->where('work_template_id', $templateB->id)->whereNull('published_at')->firstOrFail();
        $published = WorkTemplateVersion::create([
            'tenant_id' => (string) $tenantB->id,
            'work_template_id' => (string) $templateB->id,
            'semver' => '1.0.0',
            'content_json' => $draft->content_json,
            'is_immutable' => true,
            'published_at' => now(),
            'published_by' => (string) $actorB->id,
            'created_by' => (string) $actorB->id,
            'updated_by' => (string) $actorB->id,
        ]);

        $sourceStep = WorkTemplateStep::query()->where('work_template_version_id', $draft->id)->firstOrFail();
        $sourceField = WorkTemplateField::query()->where('work_template_step_id', $sourceStep->id)->first();

        $publishedStep = WorkTemplateStep::create([
            'tenant_id' => (string) $tenantB->id,
            'work_template_version_id' => (string) $published->id,
            'step_key' => $sourceStep->step_key,
            'name' => $sourceStep->name,
            'type' => $sourceStep->type,
            'step_order' => $sourceStep->step_order,
            'depends_on' => $sourceStep->depends_on,
            'assignee_rule_json' => $sourceStep->assignee_rule_json,
            'sla_hours' => $sourceStep->sla_hours,
        ]);

        if ($sourceField) {
            WorkTemplateField::create([
                'tenant_id' => (string) $tenantB->id,
                'work_template_step_id' => (string) $publishedStep->id,
                'field_key' => $sourceField->field_key,
                'label' => $sourceField->label,
                'type' => $sourceField->type,
                'is_required' => $sourceField->is_required,
            ]);
        }

        $instanceB = WorkInstance::create([
            'tenant_id' => (string) $tenantB->id,
            'project_id' => (string) $projectB->id,
            'work_template_version_id' => (string) $published->id,
            'status' => 'pending',
            'created_by' => (string) $actorB->id,
        ]);

        WorkInstanceStep::create([
            'tenant_id' => (string) $tenantB->id,
            'work_instance_id' => (string) $instanceB->id,
            'work_template_step_id' => (string) $publishedStep->id,
            'step_key' => $publishedStep->step_key,
            'name' => $publishedStep->name,
            'type' => $publishedStep->type,
            'step_order' => 1,
            'status' => 'pending',
        ]);

        $this->getJson('/api/zena/work-templates/' . $templateB->id, $this->authHeaders($actorA))
            ->assertStatus(404);

        $this->postJson('/api/zena/work-instances/' . $instanceB->id . '/export', [], $this->authHeaders($actorA))
            ->assertStatus(404);
    }

    public function test_rbac_denies_publish_apply_and_approve_without_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft', 'work.update']);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id, 'pm_id' => $user->id]);

        $template = $this->createDraftTemplate($tenant, $user);

        $this->postJson('/api/zena/work-templates/' . $template->id . '/publish', [], $this->authHeaders($user))
            ->assertStatus(403);

        $this->postJson('/api/zena/projects/' . $project->id . '/apply-template', [
            'work_template_id' => (string) $template->id,
        ], $this->authHeaders($user))->assertStatus(403);

        $instance = WorkInstance::create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'work_template_version_id' => (string) WorkTemplateVersion::query()->where('work_template_id', $template->id)->value('id'),
            'status' => 'pending',
            'created_by' => (string) $user->id,
        ]);

        $step = WorkInstanceStep::create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_id' => (string) $instance->id,
            'step_key' => 'approval-step',
            'name' => 'Approval Step',
            'type' => 'approval',
            'step_order' => 1,
            'status' => 'in_progress',
        ]);

        $this->postJson('/api/zena/work-instances/' . $instance->id . '/steps/' . $step->id . '/approve', [
            'decision' => 'approved',
        ], $this->authHeaders($user))->assertStatus(403);
    }

    public function test_project_work_instances_list_requires_work_view_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.apply']);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);

        $template = $this->createDraftTemplate($tenant, $user);
        $versionId = (string) WorkTemplateVersion::query()->where('work_template_id', $template->id)->value('id');

        WorkInstance::create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'work_template_version_id' => $versionId,
            'status' => 'pending',
            'created_by' => (string) $user->id,
        ]);

        $this->getJson('/api/zena/projects/' . $project->id . '/work-instances', $this->authHeaders($user))
            ->assertStatus(403);
    }

    public function test_project_work_instances_list_respects_tenant_scope_with_anti_enumeration(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $actorA = $this->createTenantUser($tenantA, [], ['member'], ['work.view']);
        $actorB = $this->createTenantUser($tenantB, [], ['member'], ['work.view']);

        $projectA = Project::factory()->create([
            'tenant_id' => (string) $tenantA->id,
            'created_by' => (string) $actorA->id,
            'pm_id' => (string) $actorA->id,
        ]);
        $projectB = Project::factory()->create([
            'tenant_id' => (string) $tenantB->id,
            'created_by' => (string) $actorB->id,
            'pm_id' => (string) $actorB->id,
        ]);

        $templateA = $this->createDraftTemplate($tenantA, $actorA);
        $templateB = $this->createDraftTemplate($tenantB, $actorB);
        $versionA = (string) WorkTemplateVersion::query()->where('work_template_id', $templateA->id)->value('id');
        $versionB = (string) WorkTemplateVersion::query()->where('work_template_id', $templateB->id)->value('id');

        WorkInstance::create([
            'tenant_id' => (string) $tenantA->id,
            'project_id' => (string) $projectA->id,
            'work_template_version_id' => $versionA,
            'status' => 'pending',
            'created_by' => (string) $actorA->id,
        ]);
        WorkInstance::create([
            'tenant_id' => (string) $tenantB->id,
            'project_id' => (string) $projectB->id,
            'work_template_version_id' => $versionB,
            'status' => 'pending',
            'created_by' => (string) $actorB->id,
        ]);

        $this->getJson('/api/zena/projects/' . $projectA->id . '/work-instances', $this->authHeaders($actorA))
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1);

        $this->getJson('/api/zena/projects/' . $projectB->id . '/work-instances', $this->authHeaders($actorA))
            ->assertStatus(404);
    }

    public function test_publish_immutability_keeps_published_version_content_unchanged_after_template_update(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft', 'template.publish']);

        $template = $this->createDraftTemplate($tenant, $user);

        $this->postJson('/api/zena/work-templates/' . $template->id . '/publish', [], $this->authHeaders($user))
            ->assertStatus(200);

        $published = WorkTemplateVersion::query()
            ->where('tenant_id', $tenant->id)
            ->where('work_template_id', $template->id)
            ->whereNotNull('published_at')
            ->latest('created_at')
            ->firstOrFail();

        $originalContent = $published->content_json;

        $this->putJson('/api/zena/work-templates/' . $template->id, [
            'steps' => [
                [
                    'key' => 'step-updated',
                    'name' => 'Updated Step',
                    'type' => 'task',
                    'order' => 1,
                    'fields' => [],
                ],
            ],
        ], $this->authHeaders($user))->assertStatus(200);

        $published->refresh();

        $this->assertSame($originalContent, $published->content_json);
        $this->assertTrue($published->is_immutable);
        $this->assertNotNull($published->published_at);
    }

    public function test_apply_snapshot_isolated_from_later_published_versions(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft', 'template.publish', 'template.apply']);

        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id, 'pm_id' => $user->id]);
        $template = $this->createDraftTemplate($tenant, $user, [
            'key' => 'initial-step',
            'name' => 'Initial Step',
        ]);

        $this->postJson('/api/zena/work-templates/' . $template->id . '/publish', [], $this->authHeaders($user))
            ->assertStatus(200);

        $applyResponse = $this->postJson('/api/zena/projects/' . $project->id . '/apply-template', [
            'work_template_id' => (string) $template->id,
        ], $this->authHeaders($user));
        $applyResponse->assertStatus(201);

        $workInstanceId = (string) $applyResponse->json('data.id');

        $this->putJson('/api/zena/work-templates/' . $template->id, [
            'steps' => [
                [
                    'key' => 'new-step',
                    'name' => 'Step In New Version',
                    'type' => 'task',
                    'order' => 1,
                    'fields' => [],
                ],
            ],
        ], $this->authHeaders($user))->assertStatus(200);

        $this->postJson('/api/zena/work-templates/' . $template->id . '/publish', [], $this->authHeaders($user))
            ->assertStatus(200);

        $snapshotStep = WorkInstanceStep::query()
            ->where('tenant_id', $tenant->id)
            ->where('work_instance_id', $workInstanceId)
            ->orderBy('step_order')
            ->firstOrFail();

        $this->assertSame('initial-step', $snapshotStep->step_key);
        $this->assertSame('Initial Step', $snapshotStep->name);
    }

    public function test_publish_apply_approve_write_audit_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], [
            'template.view',
            'template.edit_draft',
            'template.publish',
            'template.apply',
            'work.approve',
            'work.update',
        ]);

        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id, 'pm_id' => $user->id]);
        $template = $this->createDraftTemplate($tenant, $user);

        $this->postJson('/api/zena/work-templates/' . $template->id . '/publish', [], $this->authHeaders($user))
            ->assertStatus(200);

        $apply = $this->postJson('/api/zena/projects/' . $project->id . '/apply-template', [
            'work_template_id' => (string) $template->id,
        ], $this->authHeaders($user));

        $apply->assertStatus(201);
        $instanceId = (string) $apply->json('data.id');

        $stepId = (string) WorkInstanceStep::query()
            ->where('tenant_id', $tenant->id)
            ->where('work_instance_id', $instanceId)
            ->value('id');

        $this->postJson('/api/zena/work-instances/' . $instanceId . '/steps/' . $stepId . '/approve', [
            'decision' => 'approved',
            'comment' => 'Looks good',
        ], $this->authHeaders($user))->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $user->id,
            'action' => 'zena.work-template.publish',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $user->id,
            'action' => 'zena.work-template.apply',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $user->id,
            'action' => 'zena.work-instance.approve',
        ]);

        $this->assertGreaterThanOrEqual(
            3,
            AuditLog::query()->where('tenant_id', $tenant->id)->whereIn('action', [
                'zena.work-template.publish',
                'zena.work-template.apply',
                'zena.work-instance.approve',
            ])->count()
        );
    }

    public function test_work_instance_step_attachments_upload_list_delete_are_tenant_scoped_and_audited(): void
    {
        Storage::fake('local');

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $actorA = $this->createTenantUser($tenantA, [], ['member'], [
            'template.view',
            'template.edit_draft',
            'template.publish',
            'template.apply',
            'work.view',
            'work.update',
        ]);
        $actorB = $this->createTenantUser($tenantB, [], ['member'], ['work.view', 'work.update']);

        $projectA = Project::factory()->create([
            'tenant_id' => (string) $tenantA->id,
            'created_by' => (string) $actorA->id,
            'pm_id' => (string) $actorA->id,
        ]);
        $templateA = $this->createDraftTemplate($tenantA, $actorA);

        $this->postJson('/api/zena/work-templates/' . $templateA->id . '/publish', [], $this->authHeaders($actorA))
            ->assertStatus(200);

        $apply = $this->postJson('/api/zena/projects/' . $projectA->id . '/apply-template', [
            'work_template_id' => (string) $templateA->id,
        ], $this->authHeaders($actorA));
        $apply->assertStatus(201);

        $instanceId = (string) $apply->json('data.id');
        $stepId = (string) WorkInstanceStep::query()
            ->where('tenant_id', $tenantA->id)
            ->where('work_instance_id', $instanceId)
            ->value('id');

        $file = UploadedFile::fake()->createWithContent('qa-checklist.txt', 'step attachment file content', 'text/plain');

        $uploadHeaders = $this->authHeaders($actorA);
        unset($uploadHeaders['Content-Type']);

        $upload = $this->withHeaders($uploadHeaders)
            ->post('/api/zena/work-instances/' . $instanceId . '/steps/' . $stepId . '/attachments', [
                'file' => $file,
            ]);

        $upload->assertStatus(201)
            ->assertJsonPath('data.attachment.file_name', 'qa-checklist.txt');

        $attachmentId = (string) $upload->json('data.attachment.id');

        $this->getJson('/api/zena/work-instances/' . $instanceId . '/steps/' . $stepId . '/attachments', $this->authHeaders($actorA))
            ->assertStatus(200)
            ->assertJsonPath('data.attachments.0.id', $attachmentId);

        $record = WorkInstanceStepAttachment::query()->whereKey($attachmentId)->firstOrFail();
        Storage::disk('local')->assertExists($record->file_path);

        $this->deleteJson('/api/zena/work-instances/' . $instanceId . '/steps/' . $stepId . '/attachments/' . $attachmentId, [], $this->authHeaders($actorA))
            ->assertStatus(200);

        Storage::disk('local')->assertMissing($record->file_path);
        $this->assertDatabaseMissing('work_instance_step_attachments', ['id' => $attachmentId]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenantA->id,
            'user_id' => (string) $actorA->id,
            'action' => 'zena.work-instance.step.attachment.upload',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenantA->id,
            'user_id' => (string) $actorA->id,
            'action' => 'zena.work-instance.step.attachment.list',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenantA->id,
            'user_id' => (string) $actorA->id,
            'action' => 'zena.work-instance.step.attachment.delete',
        ]);

        $this->getJson('/api/zena/work-instances/' . $instanceId . '/steps/' . $stepId . '/attachments', $this->authHeaders($actorB))
            ->assertStatus(403);
    }

    public function test_export_import_template_package_round_trip_preserves_equivalent_structure(): void
    {
        $sourceTenant = Tenant::factory()->create();
        $sourceUser = $this->createTenantUser($sourceTenant, [], ['member'], ['template.view', 'template.edit_draft', 'template.publish']);

        $template = $this->createDraftTemplate($sourceTenant, $sourceUser, [
            'key' => 'quality-check',
            'name' => 'Quality Check',
            'fields' => [
                [
                    'key' => 'result',
                    'label' => 'Result',
                    'type' => 'enum',
                    'required' => true,
                ],
                [
                    'key' => 'notes',
                    'label' => 'Inspector Notes',
                    'type' => 'string',
                    'required' => false,
                ],
            ],
        ]);

        $this->postJson('/api/zena/work-templates/' . $template->id . '/publish', [], $this->authHeaders($sourceUser))
            ->assertStatus(200);

        $export = $this->getJson('/api/zena/export-template-package/' . $template->id, $this->authHeaders($sourceUser));
        $export->assertStatus(200)
            ->assertJsonPath('data.schema_version', WorkTemplatePackageService::SCHEMA_VERSION);

        $package = $export->json('data');

        $import = $this->postJson('/api/zena/import-template-package', $package, $this->authHeaders($sourceUser));
        $import->assertStatus(201);

        $importedTemplateId = (string) $import->json('data.id');

        $this->assertNotSame((string) $template->id, $importedTemplateId);
        $this->assertDatabaseHas('work_templates', [
            'id' => $importedTemplateId,
            'tenant_id' => (string) $sourceTenant->id,
        ]);

        $reExport = $this->getJson('/api/zena/export-template-package/' . $importedTemplateId, $this->authHeaders($sourceUser));
        $reExport->assertStatus(200);

        $this->assertSame(
            $this->normalizeTemplatePackageTemplate($package['template']),
            $this->normalizeTemplatePackageTemplate($reExport->json('data.template'))
        );
    }

    public function test_import_template_package_rejects_unsupported_schema_version(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.edit_draft']);

        $payload = $this->minimalTemplatePackagePayload();
        $payload['schema_version'] = '0.9.0';

        $this->postJson('/api/zena/import-template-package', $payload, $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unsupported schema_version "0.9.0". Expected "' . WorkTemplatePackageService::SCHEMA_VERSION . '".');
    }

    public function test_template_package_import_export_rbac_enforced(): void
    {
        $tenant = Tenant::factory()->create();
        $authorizedCreator = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft']);
        $template = $this->createDraftTemplate($tenant, $authorizedCreator);
        $userWithoutRbac = User::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'is_active' => true,
        ]);

        $this->getJson('/api/zena/export-template-package/' . $template->id, $this->authHeaders($userWithoutRbac))
            ->assertStatus(403);

        $this->postJson('/api/zena/import-template-package', $this->minimalTemplatePackagePayload(), $this->authHeaders($userWithoutRbac))
            ->assertStatus(403);
    }

    public function test_template_package_import_export_write_audit_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft']);
        $template = $this->createDraftTemplate($tenant, $user);

        $export = $this->getJson('/api/zena/export-template-package/' . $template->id, $this->authHeaders($user));
        $export->assertStatus(200);

        $this->postJson('/api/zena/import-template-package', $export->json('data'), $this->authHeaders($user))
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $user->id,
            'action' => 'zena.work-template.export-package',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $user->id,
            'action' => 'zena.work-template.import-package',
        ]);
    }

    private function createDraftTemplate(Tenant $tenant, User $user, array $stepOverrides = []): WorkTemplate
    {
        $template = WorkTemplate::create([
            'tenant_id' => (string) $tenant->id,
            'code' => 'WT-' . substr((string) \Illuminate\Support\Str::ulid(), -8),
            'name' => 'Template ' . substr((string) \Illuminate\Support\Str::ulid(), -6),
            'description' => 'Draft template',
            'status' => 'draft',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $step = array_merge([
            'key' => 'step-1',
            'name' => 'Initial Step',
            'type' => 'task',
            'order' => 1,
            'depends_on' => [],
            'assignee_rule' => ['role' => 'project_manager'],
            'sla_hours' => 24,
            'fields' => [
                [
                    'key' => 'remark',
                    'label' => 'Remark',
                    'type' => 'string',
                    'required' => false,
                ],
            ],
        ], $stepOverrides);

        $version = WorkTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'work_template_id' => (string) $template->id,
            'semver' => 'draft-initial',
            'content_json' => [
                'steps' => [$step],
                'approvals' => [],
                'rules' => [],
            ],
            'is_immutable' => false,
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $templateStep = WorkTemplateStep::create([
            'tenant_id' => (string) $tenant->id,
            'work_template_version_id' => (string) $version->id,
            'step_key' => (string) $step['key'],
            'name' => (string) $step['name'],
            'type' => (string) $step['type'],
            'step_order' => (int) $step['order'],
            'depends_on' => $step['depends_on'] ?? [],
            'assignee_rule_json' => $step['assignee_rule'] ?? null,
            'sla_hours' => $step['sla_hours'] ?? null,
        ]);

        foreach (($step['fields'] ?? []) as $field) {
            WorkTemplateField::create([
                'tenant_id' => (string) $tenant->id,
                'work_template_step_id' => (string) $templateStep->id,
                'field_key' => (string) $field['key'],
                'label' => (string) $field['label'],
                'type' => (string) $field['type'],
                'is_required' => (bool) ($field['required'] ?? false),
            ]);
        }

        return $template;
    }

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('test-token')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    private function minimalTemplatePackagePayload(): array
    {
        return [
            'manifest' => [
                'format' => 'json',
                'exported_at' => now()->toIso8601String(),
                'code' => 'WT-PKG',
                'name' => 'Package Template',
            ],
            'schema_version' => WorkTemplatePackageService::SCHEMA_VERSION,
            'template' => [
                'code' => 'WT-PKG',
                'name' => 'Package Template',
                'description' => 'Template from package',
                'status' => 'draft',
                'versions' => [
                    [
                        'semver' => 'draft-1',
                        'is_immutable' => false,
                        'published_at' => null,
                        'content_json' => [
                            'steps' => [
                                [
                                    'key' => 's1',
                                    'name' => 'Step 1',
                                    'type' => 'task',
                                    'order' => 1,
                                    'fields' => [
                                        [
                                            'key' => 'f1',
                                            'label' => 'Field 1',
                                            'type' => 'string',
                                            'required' => false,
                                        ],
                                    ],
                                ],
                            ],
                            'approvals' => [],
                            'rules' => [],
                        ],
                        'steps' => [
                            [
                                'key' => 's1',
                                'name' => 'Step 1',
                                'type' => 'task',
                                'order' => 1,
                                'fields' => [
                                    [
                                        'key' => 'f1',
                                        'label' => 'Field 1',
                                        'type' => 'string',
                                        'required' => false,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function normalizeTemplatePackageTemplate(array $template): array
    {
        $versions = array_map(function (array $version): array {
            $steps = array_map(function (array $step): array {
                $fields = $step['fields'] ?? [];
                usort($fields, static fn (array $left, array $right): int => strcmp((string) ($left['key'] ?? ''), (string) ($right['key'] ?? '')));

                $normalizedFields = array_map(static fn (array $field): array => [
                    'key' => $field['key'] ?? null,
                    'label' => $field['label'] ?? null,
                    'type' => $field['type'] ?? null,
                    'required' => (bool) ($field['required'] ?? false),
                ], array_values($fields));

                return [
                    'key' => $step['key'] ?? null,
                    'name' => $step['name'] ?? null,
                    'type' => $step['type'] ?? null,
                    'order' => $step['order'] ?? null,
                    'depends_on' => $step['depends_on'] ?? [],
                    'assignee_rule' => $step['assignee_rule'] ?? null,
                    'sla_hours' => $step['sla_hours'] ?? null,
                    'fields' => $normalizedFields,
                ];
            }, $version['steps'] ?? []);

            usort($steps, static fn (array $left, array $right): int => ($left['order'] ?? 0) <=> ($right['order'] ?? 0));
            return [
                'semver' => $version['semver'] ?? null,
                'is_immutable' => (bool) ($version['is_immutable'] ?? false),
                'published_at' => $version['published_at'] ?? null,
                'steps' => array_values($steps),
            ];
        }, $template['versions'] ?? []);

        usort($versions, static function (array $left, array $right): int {
            return strcmp((string) ($left['semver'] ?? ''), (string) ($right['semver'] ?? ''));
        });

        return [
            'name' => $template['name'] ?? null,
            'description' => $template['description'] ?? null,
            'status' => $template['status'] ?? null,
            'versions' => array_values($versions),
        ];
    }
}
