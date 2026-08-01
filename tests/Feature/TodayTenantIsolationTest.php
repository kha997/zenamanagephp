<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class TodayTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $viewer;
    private Project $projectA;
    private Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->viewer = $this->createTenantUser($this->tenantA, [], ['admin'], ['task.view']);
        $this->projectA = Project::factory()->create(['tenant_id' => (string) $this->tenantA->id]);
        $this->projectB = Project::factory()->create(['tenant_id' => (string) $this->tenantB->id]);
    }

    public function test_milestone_of_other_tenant_never_appears_even_with_matching_project_id_collision_risk(): void
    {
        Task::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'assigned_to' => (string) $this->viewer->id,
            'status' => Task::STATUS_PENDING,
            'name' => 'Việc A', 'title' => 'Việc A',
        ]);
        ProjectMilestone::create([
            'project_id' => (string) $this->projectB->id,
            'name' => 'Milestone tenant B',
            'target_date' => now()->addDays(2)->toDateString(),
            'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('Milestone tenant B');
    }

    public function test_notification_of_other_tenant_never_appears(): void
    {
        $userB = User::factory()->create(['tenant_id' => (string) $this->tenantB->id]);
        Notification::factory()->create([
            'tenant_id' => (string) $this->tenantB->id,
            'user_id' => (string) $userB->id,
            'title' => 'Thông báo tenant B',
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('Thông báo tenant B');
    }

    public function test_team_exception_of_other_tenant_never_appears(): void
    {
        $leadA = $this->viewer;
        $memberB = User::factory()->create(['tenant_id' => (string) $this->tenantB->id, 'name' => 'Thành viên B']);
        $teamB = Team::factory()->create(['tenant_id' => (string) $this->tenantB->id, 'team_lead_id' => (string) $leadA->id]);
        $teamB->members()->attach($memberB->id, ['role' => 'member', 'joined_at' => now()]);

        $response = $this->actingAs($leadA)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('Thành viên B');
    }

    public function test_hidden_navigation_does_not_weaken_route_rbac_across_tenants(): void
    {
        $noPerm = $this->createTenantUser($this->tenantA, [], ['member'], []);

        $this->actingAs($noPerm)->get(route('app.today'))->assertStatus(302);
        $this->actingAs($noPerm)->get(route('app.workload.index'))->assertStatus(302);
    }
}
