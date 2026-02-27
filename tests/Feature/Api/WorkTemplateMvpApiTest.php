<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateField;
use App\Models\WorkTemplateStep;
use App\Models\WorkTemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
