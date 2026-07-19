<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ProjectBaselineTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $manager;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->manager = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['project.view', 'project.update']
        );

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'status' => 'active',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
        ]);

        // Real session for CSRF (TestCase refuses to fabricate one — 2026-07-15 note).
        $this->get('/login');
    }

    public function test_manager_commits_baseline(): void
    {
        $response = $this->actingAs($this->manager)->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'execution', 'note' => 'Chốt theo hợp đồng ký 01/07']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('baselines', [
            'project_id' => (string) $this->project->id,
            'type' => 'execution',
            'version' => 1,
        ]);
    }

    public function test_second_commit_appends_new_version(): void
    {
        $this->actingAs($this->manager)->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'execution']
        );
        $this->actingAs($this->manager)->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'execution', 'note' => 'Dời do phát sinh CR-12']
        );

        $this->assertDatabaseCount('baselines', 2);
        $this->assertDatabaseHas('baselines', ['project_id' => (string) $this->project->id, 'version' => 1]);
        $this->assertDatabaseHas('baselines', ['project_id' => (string) $this->project->id, 'version' => 2]);
    }

    public function test_viewer_cannot_commit(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['member'], ['project.view']);

        $this->actingAs($viewer)->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'execution']
        )->assertStatus(403);

        $this->assertDatabaseCount('baselines', 0);
    }

    public function test_cross_tenant_commit_is_404_and_writes_nothing(): void
    {
        $otherTenant = Tenant::factory()->create();
        $outsider = $this->createTenantUser($otherTenant, [], ['admin'], ['project.view', 'project.update']);

        $this->actingAs($outsider)->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'execution']
        )->assertStatus(404);

        $this->assertDatabaseCount('baselines', 0);
    }

    public function test_invalid_type_is_rejected(): void
    {
        $this->actingAs($this->manager)->from(route('app.projects.show', $this->project->id))->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'wishful']
        )->assertSessionHasErrors('type');

        $this->assertDatabaseCount('baselines', 0);
    }

    public function test_project_show_renders_baseline_card_and_delay_badge(): void
    {
        $this->actingAs($this->manager)->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'execution', 'note' => 'Chốt lần đầu']
        );

        $response = $this->actingAs($this->manager)->get(
            route('app.projects.show', $this->project->id)
        );

        $response->assertOk();
        $response->assertSee('Kế hoạch gốc');
        $response->assertSee('Chốt lần đầu');
        // Project end (2026-12-31) equals committed end → on track.
        $response->assertSee('Đúng tiến độ');
    }

    public function test_project_show_offers_commit_button_when_no_baseline(): void
    {
        $response = $this->actingAs($this->manager)->get(
            route('app.projects.show', $this->project->id)
        );

        $response->assertOk();
        $response->assertSee('Chưa chốt kế hoạch gốc');
        $response->assertSee('Chốt kế hoạch từ ngày hiện tại');
    }
}
