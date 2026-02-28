<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
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

class WorkInstanceDashboardApiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    public function test_index_and_metrics_require_authentication(): void
    {
        $this->getJson('/api/zena/work-instances')->assertStatus(401);
        $this->getJson('/api/zena/work-instances/metrics')->assertStatus(401);
    }

    public function test_index_and_metrics_require_work_view_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], []);

        $this->getJson('/api/zena/work-instances', $this->authHeaders($user))->assertStatus(403);
        $this->getJson('/api/zena/work-instances/metrics', $this->authHeaders($user))->assertStatus(403);
    }

    public function test_index_and_metrics_respect_tenant_isolation(): void
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

        $instanceA1 = WorkInstance::create([
            'tenant_id' => (string) $tenantA->id,
            'project_id' => (string) $projectA->id,
            'work_template_version_id' => $versionA,
            'status' => 'pending',
            'created_by' => (string) $actorA->id,
        ]);
        $instanceA2 = WorkInstance::create([
            'tenant_id' => (string) $tenantA->id,
            'project_id' => (string) $projectA->id,
            'work_template_version_id' => $versionA,
            'status' => 'approved',
            'created_by' => (string) $actorA->id,
        ]);
        $instanceB = WorkInstance::create([
            'tenant_id' => (string) $tenantB->id,
            'project_id' => (string) $projectB->id,
            'work_template_version_id' => $versionB,
            'status' => 'pending',
            'created_by' => (string) $actorB->id,
        ]);

        WorkInstanceStep::create([
            'tenant_id' => (string) $tenantA->id,
            'work_instance_id' => (string) $instanceA1->id,
            'step_key' => 'a1-step-1',
            'name' => 'A1 Step 1',
            'type' => 'task',
            'step_order' => 1,
            'status' => 'pending',
            'deadline_at' => now()->subDay(),
        ]);
        WorkInstanceStep::create([
            'tenant_id' => (string) $tenantA->id,
            'work_instance_id' => (string) $instanceA1->id,
            'step_key' => 'a1-step-2',
            'name' => 'A1 Step 2',
            'type' => 'task',
            'step_order' => 2,
            'status' => 'completed',
            'completed_at' => now()->subHour(),
        ]);
        WorkInstanceStep::create([
            'tenant_id' => (string) $tenantA->id,
            'work_instance_id' => (string) $instanceA2->id,
            'step_key' => 'a2-step-1',
            'name' => 'A2 Step 1',
            'type' => 'approval',
            'step_order' => 1,
            'status' => 'approved',
            'completed_at' => now()->subHour(),
        ]);
        WorkInstanceStep::create([
            'tenant_id' => (string) $tenantB->id,
            'work_instance_id' => (string) $instanceB->id,
            'step_key' => 'b-step-1',
            'name' => 'B Step 1',
            'type' => 'task',
            'step_order' => 1,
            'status' => 'pending',
            'deadline_at' => now()->subDay(),
        ]);

        $indexResponse = $this->getJson('/api/zena/work-instances', $this->authHeaders($actorA));
        $indexResponse
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonMissing(['id' => (string) $instanceB->id]);

        $returnedIds = array_column($indexResponse->json('data') ?? [], 'id');
        $this->assertEqualsCanonicalizing([(string) $instanceA1->id, (string) $instanceA2->id], $returnedIds);

        $metricsResponse = $this->getJson('/api/zena/work-instances/metrics', $this->authHeaders($actorA));
        $metricsResponse
            ->assertOk()
            ->assertJsonPath('data.metrics.total_instances', 2)
            ->assertJsonPath('data.metrics.total_steps', 3)
            ->assertJsonPath('data.metrics.overdue_steps', 1);

        $instanceStatusCounts = collect($metricsResponse->json('data.metrics.instances_by_status'))
            ->mapWithKeys(fn (array $item): array => [(string) $item['status'] => (int) $item['count']])
            ->all();
        $stepStatusCounts = collect($metricsResponse->json('data.metrics.steps_by_status'))
            ->mapWithKeys(fn (array $item): array => [(string) $item['status'] => (int) $item['count']])
            ->all();

        $this->assertSame(['approved' => 1, 'pending' => 1], $instanceStatusCounts);
        $this->assertSame(['approved' => 1, 'completed' => 1, 'pending' => 1], $stepStatusCounts);
    }

    public function test_metrics_response_has_expected_shape_and_types(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['work.view']);

        $response = $this->getJson('/api/zena/work-instances/metrics', $this->authHeaders($user));
        $response
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'status',
                'data' => [
                    'metrics' => [
                        'total_instances',
                        'instances_by_status',
                        'total_steps',
                        'steps_by_status',
                        'overdue_steps',
                    ],
                ],
            ]);

        $metrics = $response->json('data.metrics');

        $this->assertIsInt($metrics['total_instances']);
        $this->assertIsArray($metrics['instances_by_status']);
        $this->assertIsInt($metrics['total_steps']);
        $this->assertIsArray($metrics['steps_by_status']);
        $this->assertIsInt($metrics['overdue_steps']);
    }

    private function createDraftTemplate(Tenant $tenant, User $user): WorkTemplate
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

        $version = WorkTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'work_template_id' => (string) $template->id,
            'semver' => 'draft-initial',
            'content_json' => [
                'steps' => [[
                    'key' => 'step-1',
                    'name' => 'Initial Step',
                    'type' => 'task',
                    'order' => 1,
                    'fields' => [[
                        'key' => 'remark',
                        'label' => 'Remark',
                        'type' => 'string',
                        'required' => false,
                    ]],
                ]],
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
            'step_key' => 'step-1',
            'name' => 'Initial Step',
            'type' => 'task',
            'step_order' => 1,
            'depends_on' => [],
            'assignee_rule_json' => ['role' => 'project_manager'],
            'sla_hours' => 24,
        ]);

        WorkTemplateField::create([
            'tenant_id' => (string) $tenant->id,
            'work_template_step_id' => (string) $templateStep->id,
            'field_key' => 'remark',
            'label' => 'Remark',
            'type' => 'string',
            'is_required' => false,
        ]);

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
