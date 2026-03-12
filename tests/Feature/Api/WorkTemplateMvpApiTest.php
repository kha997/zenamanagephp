<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\AuditLog;
use App\Models\Component;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleProject;
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
        $published = WorkTemplateVersion::factory()->create([
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

        $publishedStep = WorkTemplateStep::factory()->create([
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
            WorkTemplateField::factory()->create([
                'tenant_id' => (string) $tenantB->id,
                'work_template_step_id' => (string) $publishedStep->id,
                'field_key' => $sourceField->field_key,
                'label' => $sourceField->label,
                'type' => $sourceField->type,
                'is_required' => $sourceField->is_required,
            ]);
        }

        $instanceB = WorkInstance::factory()->create([
            'tenant_id' => (string) $tenantB->id,
            'project_id' => (string) $projectB->id,
            'work_template_version_id' => (string) $published->id,
            'status' => 'pending',
            'created_by' => (string) $actorB->id,
        ]);

        WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $tenantB->id,
            'work_instance_id' => (string) $instanceB->id,
            'work_template_step_id' => (string) $publishedStep->id,
            'step_key' => $publishedStep->step_key,
            'name' => $publishedStep->name,
            'type' => $publishedStep->type,
            'step_order' => 1,
            'status' => 'pending',
        ]);

        $this->getJson($this->workTemplateRoute('show', ['id' => $templateB->id]), $this->authHeaders($actorA))
            ->assertStatus(404);

        $this->postJson($this->workInstanceRoute('export', ['id' => $instanceB->id]), [], $this->authHeaders($actorA))
            ->assertStatus(404);
    }

    public function test_rbac_denies_publish_apply_and_approve_without_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft', 'work.update']);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id, 'pm_id' => $user->id]);

        $template = $this->createDraftTemplate($tenant, $user);

        $this->postJson($this->workTemplateRoute('publish', ['id' => $template->id]), [], $this->authHeaders($user))
            ->assertStatus(403);

        $this->postJson($this->projectApplyTemplateRoute((string) $project->id), [
            'work_template_id' => (string) $template->id,
        ], $this->authHeaders($user))->assertStatus(403);

        $instance = WorkInstance::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'work_template_version_id' => (string) WorkTemplateVersion::query()->where('work_template_id', $template->id)->value('id'),
            'status' => 'pending',
            'created_by' => (string) $user->id,
        ]);

        $step = WorkInstanceStep::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_id' => (string) $instance->id,
            'step_key' => 'approval-step',
            'name' => 'Approval Step',
            'type' => 'approval',
            'step_order' => 1,
            'status' => 'in_progress',
        ]);

        $this->postJson($this->workInstanceStepRoute('approve', ['id' => $instance->id, 'stepId' => $step->id]), [
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

        WorkInstance::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'work_template_version_id' => $versionId,
            'status' => 'pending',
            'created_by' => (string) $user->id,
        ]);

        $this->getJson($this->projectWorkInstancesRoute((string) $project->id), $this->authHeaders($user))
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

        WorkInstance::factory()->create([
            'tenant_id' => (string) $tenantA->id,
            'project_id' => (string) $projectA->id,
            'work_template_version_id' => $versionA,
            'status' => 'pending',
            'created_by' => (string) $actorA->id,
        ]);
        WorkInstance::factory()->create([
            'tenant_id' => (string) $tenantB->id,
            'project_id' => (string) $projectB->id,
            'work_template_version_id' => $versionB,
            'status' => 'pending',
            'created_by' => (string) $actorB->id,
        ]);

        $this->getJson($this->projectWorkInstancesRoute((string) $projectA->id), $this->authHeaders($actorA))
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1);

        $this->getJson($this->projectWorkInstancesRoute((string) $projectB->id), $this->authHeaders($actorA))
            ->assertStatus(404);
    }

    public function test_publish_immutability_keeps_published_version_content_unchanged_after_template_update(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft', 'template.publish']);

        $template = $this->createDraftTemplate($tenant, $user);

        $this->postJson($this->workTemplateRoute('publish', ['id' => $template->id]), [], $this->authHeaders($user))
            ->assertStatus(200);

        $published = WorkTemplateVersion::query()
            ->where('tenant_id', $tenant->id)
            ->where('work_template_id', $template->id)
            ->whereNotNull('published_at')
            ->latest('created_at')
            ->firstOrFail();

        $originalContent = $published->content_json;

        $this->putJson($this->workTemplateRoute('update', ['id' => $template->id]), [
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

        $this->postJson($this->workTemplateRoute('publish', ['id' => $template->id]), [], $this->authHeaders($user))
            ->assertStatus(200);

        $applyResponse = $this->postJson($this->projectApplyTemplateRoute((string) $project->id), [
            'work_template_id' => (string) $template->id,
        ], $this->authHeaders($user));
        $applyResponse->assertStatus(201);

        $workInstanceId = (string) $applyResponse->json('data.id');

        $this->putJson($this->workTemplateRoute('update', ['id' => $template->id]), [
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

        $this->postJson($this->workTemplateRoute('publish', ['id' => $template->id]), [], $this->authHeaders($user))
            ->assertStatus(200);

        $snapshotStep = WorkInstanceStep::query()
            ->where('tenant_id', $tenant->id)
            ->where('work_instance_id', $workInstanceId)
            ->orderBy('step_order')
            ->firstOrFail();

        $this->assertSame('initial-step', $snapshotStep->step_key);
        $this->assertSame('Initial Step', $snapshotStep->name);
    }

    public function test_apply_project_generates_tasks_assignments_due_dates_and_checklist_snapshot(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft', 'template.publish', 'template.apply']);
        $reviewer = User::factory()->create(['tenant_id' => (string) $tenant->id, 'is_active' => true]);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
            'start_date' => now()->toDateString(),
        ]);

        $role = Role::query()->create([
            'name' => 'qc_lead',
            'scope' => 'project',
            'allow_override' => false,
            'is_active' => true,
        ]);

        UserRoleProject::query()->create([
            'project_id' => (string) $project->id,
            'user_id' => (string) $reviewer->id,
            'role_id' => (string) $role->id,
        ]);

        $template = $this->createDraftTemplate($tenant, $user, [
            'key' => 'qa-check',
            'name' => 'QA Check',
            'sla_hours' => 24,
            'config' => [
                'description' => 'Checklist task',
                'checklist_items' => [
                    ['key' => 'safety', 'label' => 'Safety checklist complete', 'required' => true],
                ],
                'required_docs' => [
                    ['key' => 'inspection-report', 'label' => 'Inspection Report', 'required' => true],
                ],
                'assignment_rules' => [
                    'reviewers' => [
                        ['project_role' => 'qc_lead'],
                    ],
                    'watchers' => [],
                ],
            ],
        ]);

        $this->postJson($this->workTemplateRoute('publish', ['id' => $template->id]), [], $this->authHeaders($user))
            ->assertStatus(200);

        $apply = $this->postJson($this->projectApplyTemplateRoute((string) $project->id), [
            'work_template_id' => (string) $template->id,
        ], $this->authHeaders($user));

        $apply->assertStatus(201)
            ->assertJsonPath('data.scope_type', 'project')
            ->assertJsonPath('data.duplicate', false)
            ->assertJsonPath('data.tasks_created', 1);

        $instanceId = (string) $apply->json('data.id');
        $task = Task::query()->where('work_instance_id', $instanceId)->firstOrFail();
        $step = WorkInstanceStep::query()->where('work_instance_id', $instanceId)->firstOrFail();

        $this->assertSame((string) $project->id, (string) $task->project_id);
        $this->assertNull($task->component_id);
        $this->assertNotNull($task->end_date);
        $this->assertSame((string) $user->id, (string) $task->assigned_to);

        $assignments = TaskAssignment::query()->where('task_id', $task->id)->orderBy('role')->get();
        $this->assertCount(2, $assignments);
        $this->assertTrue($assignments->pluck('role')->contains('assignee'));
        $this->assertTrue($assignments->pluck('role')->contains('reviewer'));

        $snapshot = $step->snapshot_fields_json ?? [];
        $this->assertNotEmpty(array_filter($snapshot, static fn (array $field): bool => str_starts_with((string) ($field['field_key'] ?? ''), 'checklist:')));
        $this->assertNotEmpty(array_filter($snapshot, static fn (array $field): bool => str_starts_with((string) ($field['field_key'] ?? ''), 'required-doc:')));
    }

    public function test_apply_component_generates_component_scoped_tasks(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft', 'template.publish', 'template.apply']);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);
        $component = Component::factory()->create([
            'project_id' => (string) $project->id,
            'tenant_id' => (string) $tenant->id,
        ]);

        $template = $this->createDraftTemplate($tenant, $user);
        $this->postJson($this->workTemplateRoute('publish', ['id' => $template->id]), [], $this->authHeaders($user))
            ->assertStatus(200);

        $apply = $this->postJson($this->componentApplyTemplateRoute((string) $component->id), [
            'work_template_id' => (string) $template->id,
        ], $this->authHeaders($user));

        $apply->assertStatus(201)
            ->assertJsonPath('data.scope_type', 'component')
            ->assertJsonPath('data.scope_id', (string) $component->id)
            ->assertJsonPath('data.project_id', (string) $project->id);

        $instanceId = (string) $apply->json('data.id');
        $task = Task::query()->where('work_instance_id', $instanceId)->firstOrFail();
        $this->assertSame((string) $component->id, (string) $task->component_id);
        $this->assertSame((string) $project->id, (string) $task->project_id);
    }

    public function test_apply_remains_idempotent_even_with_different_idempotency_keys(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft', 'template.publish', 'template.apply']);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);
        $template = $this->createDraftTemplate($tenant, $user);
        $this->postJson($this->workTemplateRoute('publish', ['id' => $template->id]), [], $this->authHeaders($user))
            ->assertStatus(200);

        $first = $this->postJson($this->projectApplyTemplateRoute((string) $project->id), [
            'work_template_id' => (string) $template->id,
            'idempotency_key' => 'k1',
        ], $this->authHeaders($user));
        $first->assertStatus(201);

        $second = $this->postJson($this->projectApplyTemplateRoute((string) $project->id), [
            'work_template_id' => (string) $template->id,
            'idempotency_key' => 'k2',
        ], $this->authHeaders($user));

        $second->assertStatus(200)
            ->assertJsonPath('data.duplicate', true)
            ->assertJsonPath('data.tasks_created', 0)
            ->assertJsonPath('data.assignments_created', 0);

        $instanceId = (string) $first->json('data.id');
        $this->assertSame($instanceId, (string) $second->json('data.id'));
        $this->assertSame(1, WorkInstance::query()->where('tenant_id', (string) $tenant->id)->count());
        $this->assertSame(1, Task::query()->where('tenant_id', (string) $tenant->id)->count());
    }

    public function test_apply_fails_atomically_when_secondary_assignment_rule_unresolved(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft', 'template.publish', 'template.apply']);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);

        $template = $this->createDraftTemplate($tenant, $user, [
            'key' => 'qa-check',
            'name' => 'QA Check',
            'config' => [
                'assignment_rules' => [
                    'reviewers' => [
                        ['project_role' => 'missing-role'],
                    ],
                    'watchers' => [],
                ],
            ],
        ]);

        $this->postJson($this->workTemplateRoute('publish', ['id' => $template->id]), [], $this->authHeaders($user))
            ->assertStatus(200);

        $this->postJson($this->projectApplyTemplateRoute((string) $project->id), [
            'work_template_id' => (string) $template->id,
        ], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unresolved assignment rule for step "qa-check".');

        $this->assertSame(0, WorkInstance::query()->where('tenant_id', (string) $tenant->id)->count());
        $this->assertSame(0, Task::query()->where('tenant_id', (string) $tenant->id)->count());
        $this->assertSame(0, TaskAssignment::query()->where('tenant_id', (string) $tenant->id)->count());
    }

    public function test_export_import_schema_version_2_round_trip_preserves_generator_metadata(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft', 'template.publish']);

        $template = $this->createDraftTemplate($tenant, $user, [
            'key' => 'qa-check',
            'name' => 'QA Check',
            'config' => [
                'checklist_items' => [
                    ['key' => 'safety', 'label' => 'Safety checklist complete', 'required' => true],
                ],
                'required_docs' => [
                    ['key' => 'inspection-report', 'label' => 'Inspection Report', 'required' => true],
                ],
                'assignment_rules' => [
                    'reviewers' => [['project_role' => 'qc_lead']],
                    'watchers' => [['project_role' => 'site_engineer']],
                ],
            ],
        ]);

        $this->postJson($this->workTemplateRoute('publish', ['id' => $template->id]), [], $this->authHeaders($user))
            ->assertStatus(200);

        $export = $this->getJson(
            route('api.zena.work-templates.package.export', ['wtId' => $template->id, 'schema_version' => WorkTemplatePackageService::SCHEMA_VERSION_V2]),
            $this->authHeaders($user)
        );

        $export->assertStatus(200)
            ->assertJsonPath('data.schema_version', WorkTemplatePackageService::SCHEMA_VERSION_V2);

        $package = $export->json('data');
        $this->assertIsArray($package['manifest']['capabilities'] ?? null);

        $import = $this->postJson($this->workTemplatePackageImportRoute(), $package, $this->authHeaders($user));
        $import->assertStatus(201);

        $importedTemplateId = (string) $import->json('data.id');
        $reExport = $this->getJson(
            route('api.zena.work-templates.package.export', ['wtId' => $importedTemplateId, 'schema_version' => WorkTemplatePackageService::SCHEMA_VERSION_V2]),
            $this->authHeaders($user)
        );
        $reExport->assertStatus(200);

        $stepConfig = $reExport->json('data.template.versions.0.steps.0.config');
        $this->assertIsArray($stepConfig['checklist_items'] ?? null);
        $this->assertIsArray($stepConfig['required_docs'] ?? null);
        $this->assertIsArray($stepConfig['assignment_rules'] ?? null);
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

        $this->postJson($this->workTemplateRoute('publish', ['id' => $template->id]), [], $this->authHeaders($user))
            ->assertStatus(200);

        $apply = $this->postJson($this->projectApplyTemplateRoute((string) $project->id), [
            'work_template_id' => (string) $template->id,
        ], $this->authHeaders($user));

        $apply->assertStatus(201);
        $instanceId = (string) $apply->json('data.id');

        $stepId = (string) WorkInstanceStep::query()
            ->where('tenant_id', $tenant->id)
            ->where('work_instance_id', $instanceId)
            ->value('id');

        $this->postJson($this->workInstanceStepRoute('approve', ['id' => $instanceId, 'stepId' => $stepId]), [
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

        $this->postJson($this->workTemplateRoute('publish', ['id' => $templateA->id]), [], $this->authHeaders($actorA))
            ->assertStatus(200);

        $apply = $this->postJson($this->projectApplyTemplateRoute((string) $projectA->id), [
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
            ->post($this->workInstanceStepRoute('attachments.store', ['id' => $instanceId, 'stepId' => $stepId]), [
                'file' => $file,
            ]);

        $upload->assertStatus(201)
            ->assertJsonPath('data.attachment.file_name', 'qa-checklist.txt');

        $attachmentId = (string) $upload->json('data.attachment.id');

        $this->getJson($this->workInstanceStepRoute('attachments.index', ['id' => $instanceId, 'stepId' => $stepId]), $this->authHeaders($actorA))
            ->assertStatus(200)
            ->assertJsonPath('data.attachments.0.id', $attachmentId);

        $record = WorkInstanceStepAttachment::query()->whereKey($attachmentId)->firstOrFail();
        Storage::disk('local')->assertExists($record->file_path);

        $this->deleteJson($this->workInstanceStepRoute('attachments.destroy', ['id' => $instanceId, 'stepId' => $stepId, 'attachmentId' => $attachmentId]), [], $this->authHeaders($actorA))
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

        $this->getJson($this->workInstanceStepRoute('attachments.index', ['id' => $instanceId, 'stepId' => $stepId]), $this->authHeaders($actorB))
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

        $this->postJson($this->workTemplateRoute('publish', ['id' => $template->id]), [], $this->authHeaders($sourceUser))
            ->assertStatus(200);

        $export = $this->getJson(route('api.zena.work-templates.package.export', ['wtId' => $template->id]), $this->authHeaders($sourceUser));
        $export->assertStatus(200)
            ->assertJsonPath('data.schema_version', WorkTemplatePackageService::SCHEMA_VERSION);

        $package = $export->json('data');

        $import = $this->postJson($this->workTemplatePackageImportRoute(), $package, $this->authHeaders($sourceUser));
        $import->assertStatus(201);

        $importedTemplateId = (string) $import->json('data.id');

        $this->assertNotSame((string) $template->id, $importedTemplateId);
        $this->assertDatabaseHas('work_templates', [
            'id' => $importedTemplateId,
            'tenant_id' => (string) $sourceTenant->id,
        ]);

        $reExport = $this->getJson(route('api.zena.work-templates.package.export', ['wtId' => $importedTemplateId]), $this->authHeaders($sourceUser));
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

        $this->postJson($this->workTemplatePackageImportRoute(), $payload, $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unsupported schema_version "0.9.0". Expected one of: "' . WorkTemplatePackageService::SCHEMA_VERSION . '", "' . WorkTemplatePackageService::SCHEMA_VERSION_V2 . '".');
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

        $this->getJson(route('api.zena.work-templates.package.export', ['wtId' => $template->id]), $this->authHeaders($userWithoutRbac))
            ->assertStatus(403);

        $this->postJson($this->workTemplatePackageImportRoute(), $this->minimalTemplatePackagePayload(), $this->authHeaders($userWithoutRbac))
            ->assertStatus(403);
    }

    public function test_template_package_import_export_write_audit_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft']);
        $template = $this->createDraftTemplate($tenant, $user);

        $export = $this->getJson(route('api.zena.work-templates.package.export', ['wtId' => $template->id]), $this->authHeaders($user));
        $export->assertStatus(200);

        $this->postJson($this->workTemplatePackageImportRoute(), $export->json('data'), $this->authHeaders($user))
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
        $template = WorkTemplate::factory()->create([
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

        $version = WorkTemplateVersion::factory()->create([
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

        $templateStep = WorkTemplateStep::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_template_version_id' => (string) $version->id,
            'step_key' => (string) $step['key'],
            'name' => (string) $step['name'],
            'type' => (string) $step['type'],
            'step_order' => (int) $step['order'],
            'depends_on' => $step['depends_on'] ?? [],
            'assignee_rule_json' => $step['assignee_rule'] ?? null,
            'sla_hours' => $step['sla_hours'] ?? null,
            'config_json' => $step['config'] ?? null,
        ]);

        foreach (($step['fields'] ?? []) as $field) {
            WorkTemplateField::factory()->create([
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

    private function workTemplateRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.work-templates.' . $name, $parameters, false);
    }

    private function projectApplyTemplateRoute(string $projectId): string
    {
        return route('api.zena.projects.apply-template', ['id' => $projectId], false);
    }

    private function projectWorkInstancesRoute(string $projectId): string
    {
        return route('api.zena.projects.work-instances.index', ['project' => $projectId], false);
    }

    private function componentApplyTemplateRoute(string $componentId): string
    {
        return route('api.zena.components.apply-template', ['id' => $componentId], false);
    }

    private function workInstanceRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.work-instances.' . $name, $parameters, false);
    }

    private function workInstanceStepRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.work-instances.steps.' . $name, $parameters, false);
    }

    private function workTemplatePackageImportRoute(): string
    {
        return route('api.zena.work-templates.package.import', [], false);
    }
}
