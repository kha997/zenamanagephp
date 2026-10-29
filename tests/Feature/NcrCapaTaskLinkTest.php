<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Ncr;
use App\Models\Project;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

/**
 * GAP-014c — durable NCR ↔ CAPA task link: tasks.ncr_id, "create CAPA task" from an NCR (API and web), and the link
 * visible from both sides.
 */
class NcrCapaTaskLinkTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTestTrait;

    private Tenant $tenant;

    private User $actor;

    /** @var array<string, string> */
    private array $headers;

    private Project $project;

    private QcInspection $inspection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->actor = $this->createTenantUser($this->tenant, [], ['admin'], [
            'inspection.view',
            'inspection.create',
            'inspection.edit',
            'task.create',
            'task.view',
        ]);
        $this->headers = $this->authHeadersForUser($this->actor, $this->apiLoginToken($this->actor, $this->tenant));

        $this->project = $this->projectFor($this->tenant, $this->actor);
        $this->inspection = $this->inspectionFor($this->tenant, $this->project, $this->actor);
    }

    public function test_capa_task_created_from_an_ncr_is_linked_and_listed_on_the_ncr(): void
    {
        $ncr = $this->ncr(['severity' => 'high', 'corrective_action' => 'Rework the cage.']);

        $response = $this->withHeaders($this->headers)->postJson($this->capaRoute($ncr));

        $response->assertCreated()
            ->assertJsonPath('data.ncr_id', (string) $ncr->id)
            ->assertJsonPath('data.project_id', (string) $this->project->id)
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.tags.0', 'inspection-ncr-capa');

        $taskId = (string) $response->json('data.id');
        $this->assertDatabaseHas('tasks', ['id' => $taskId, 'ncr_id' => (string) $ncr->id, 'tenant_id' => (string) $this->tenant->id]);

        $this->withHeaders($this->headers)->getJson($this->ncrShowRoute($ncr))
            ->assertOk()
            ->assertJsonPath('data.capa_tasks.0.id', $taskId)
            ->assertJsonPath('data.capa_tasks.0.status', 'pending')
            ->assertJsonPath('data.task_handoff.payload.ncr_id', (string) $ncr->id);

        $this->withHeaders($this->headers)->getJson(route('api.zena.tasks.show', ['id' => $taskId], false))
            ->assertOk()
            ->assertJsonPath('data.ncr_id', (string) $ncr->id);

        Task::query()->whereKey($taskId)->update(['assigned_to' => (string) $this->actor->id]);

        $this->withHeaders($this->headers)->getJson($this->ncrShowRoute($ncr))
            ->assertOk()
            ->assertJsonPath('data.capa_tasks.0.assignee_id', (string) $this->actor->id);
    }

    public function test_capa_task_fits_task_limits_for_a_long_ncr(): void
    {
        $ncr = $this->ncr([
            'title' => str_repeat('T', 255),
            'description' => str_repeat('D', 3000),
            'corrective_action' => str_repeat('C', 500),
        ]);

        $response = $this->withHeaders($this->headers)->postJson($this->capaRoute($ncr));

        $response->assertCreated()->assertJsonPath('data.ncr_id', (string) $ncr->id);
        $this->assertSame(255, mb_strlen((string) $response->json('data.name')));
        $this->assertSame(2000, mb_strlen((string) $response->json('data.description')));
    }

    public function test_task_store_accepts_ncr_id_from_the_handoff_payload(): void
    {
        $ncr = $this->ncr();
        $payload = $this->withHeaders($this->headers)->getJson($this->ncrShowRoute($ncr))->json('data.task_handoff.payload');

        $this->withHeaders($this->headers)->postJson(route('api.zena.tasks.store', [], false), $payload)
            ->assertCreated()
            ->assertJsonPath('data.ncr_id', (string) $ncr->id);
    }

    public function test_task_store_rejects_an_ncr_from_another_tenant_or_project(): void
    {
        $otherProject = $this->projectFor($this->tenant, $this->actor);
        $otherProjectNcr = $this->ncr([], $otherProject);

        $foreignTenant = Tenant::factory()->create();
        $foreignUser = $this->createTenantUser($foreignTenant, [], ['admin'], []);
        $foreignProject = $this->projectFor($foreignTenant, $foreignUser);
        $foreignNcr = Ncr::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'inspection_id' => (string) $this->inspectionFor($foreignTenant, $foreignProject, $foreignUser)->id,
            'created_by' => (string) $foreignUser->id,
            'status' => 'open',
        ]);

        foreach ([$otherProjectNcr, $foreignNcr] as $ncr) {
            $this->withHeaders($this->headers)->postJson(route('api.zena.tasks.store', [], false), [
                'project_id' => (string) $this->project->id,
                'name' => 'Wrong NCR link',
                'ncr_id' => (string) $ncr->id,
            ])->assertStatus(422);
        }

        $this->assertDatabaseMissing('tasks', ['name' => 'Wrong NCR link']);
    }

    public function test_closed_ncr_cannot_get_a_new_capa_task(): void
    {
        $ncr = $this->ncr(['status' => 'closed']);

        $this->withHeaders($this->headers)->postJson($this->capaRoute($ncr))
            ->assertStatus(422);

        $this->assertDatabaseMissing('tasks', ['ncr_id' => (string) $ncr->id]);
    }

    public function test_capa_task_creation_requires_task_create_permission(): void
    {
        $ncr = $this->ncr();
        $inspector = $this->createTenantUser($this->tenant, [], ['member'], ['inspection.view', 'inspection.edit']);
        $headers = $this->authHeadersForUser($inspector, $this->apiLoginToken($inspector, $this->tenant));

        $this->withHeaders($headers)->postJson($this->capaRoute($ncr))
            ->assertStatus(403);

        $this->assertDatabaseMissing('tasks', ['ncr_id' => (string) $ncr->id]);
    }

    public function test_web_ncr_page_creates_and_lists_capa_tasks(): void
    {
        $ncr = $this->ncr();
        $this->flushHeaders(); // drop the API JSON headers left by apiLoginToken() in setUp()
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $showUrl = route('operator.inspections.ncrs.show', ['inspection' => $this->inspection->id, 'ncr' => $ncr->id]);

        $this->actingAs($this->actor)->get($showUrl, $headers)
            ->assertOk()
            ->assertSee('Tạo task CAPA');

        $this->actingAs($this->actor)
            ->post(route('operator.inspections.ncrs.capa-tasks.store', ['inspection' => $this->inspection->id, 'ncr' => $ncr->id]), [], $headers)
            ->assertRedirect($showUrl)
            ->assertSessionHas('success', 'Đã tạo task CAPA');

        $task = Task::query()->where('ncr_id', (string) $ncr->id)->firstOrFail();

        $this->actingAs($this->actor)->get($showUrl, $headers)
            ->assertOk()
            ->assertSee((string) $task->name);
    }

    public function test_migration_down_removes_the_link_column(): void
    {
        $migration = require database_path('migrations/2026_10_29_000000_add_ncr_id_to_tasks_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('tasks', 'ncr_id'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('tasks', 'ncr_id'));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function ncr(array $attributes = [], ?Project $project = null): Ncr
    {
        $project ??= $this->project;
        $inspection = $project->is($this->project) ? $this->inspection : $this->inspectionFor($this->tenant, $project, $this->actor);

        return Ncr::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'inspection_id' => (string) $inspection->id,
            'created_by' => (string) $this->actor->id,
            'status' => 'open',
        ], $attributes));
    }

    private function projectFor(Tenant $tenant, User $user): Project
    {
        return Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);
    }

    private function inspectionFor(Tenant $tenant, Project $project, User $user): QcInspection
    {
        $plan = QcPlan::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $user->id,
        ]);

        return QcInspection::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'qc_plan_id' => (string) $plan->id,
            'inspector_id' => (string) $user->id,
            'status' => 'failed',
        ]);
    }

    private function capaRoute(Ncr $ncr): string
    {
        return route('api.zena.inspections.ncrs.capa-tasks.store', [
            'inspection' => (string) $ncr->inspection_id,
            'ncr' => (string) $ncr->id,
        ], false);
    }

    private function ncrShowRoute(Ncr $ncr): string
    {
        return route('api.zena.inspections.ncrs.show', [
            'inspection' => (string) $ncr->inspection_id,
            'ncr' => (string) $ncr->id,
        ], false);
    }
}
