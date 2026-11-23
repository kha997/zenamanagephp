<?php

declare(strict_types=1);

namespace Tests\Feature\Recovery;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * S4c — independent verification of the `task.create` correction (CB-1: POST api/tasks had no permission check).
 * Uses ONLY seeded roles (DatabaseSeeder); nothing is granted by the tests. Legacy `users.role` values are used solely to get past the
 * (unchanged, O-8) parameterless gate so the route's own behaviour is what is observed.
 */
class UatRecovery04cTaskCreateVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Project $project;

    private Project $foreignProject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->foreignProject = Project::factory()->create(['tenant_id' => $this->tenantB->id]);
    }

    private function actor(?string $seededRole, ?string $legacy, ?Tenant $tenant = null): User
    {
        $u = User::factory()->create(['tenant_id' => ($tenant ?? $this->tenantA)->id, 'role' => $legacy]);
        if ($seededRole !== null) {
            DB::table('user_roles')->insert(['user_id' => $u->id, 'role_id' => DB::table('roles')->where('name', $seededRole)->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        }

        return $u;
    }

    private function postAs(User $u, string $uri, array $d)
    {
        Sanctum::actingAs($u);

        return $this->withHeaders(['X-Tenant-ID' => (string) $u->tenant_id])->postJson($uri, $d);
    }

    private function payload(Project $p, string $name): array
    {
        return ['project_id' => $p->id, 'name' => $name, 'start_date' => '2026-12-01', 'end_date' => '2026-12-05'];
    }

    #[Group('uat-recovery-04c-create')]
    public function test_authorised_creator_succeeds_on_the_legacy_gate_route(): void
    {
        $pm = $this->actor('Project Manager', 'project_manager'); // seeded PM holds task.create; legacy value only passes the parameterless gate
        $this->assertTrue($pm->hasPermission('task.create'));

        $res = $this->postAs($pm, '/api/tasks', $this->payload($this->project, 'created by PM'));

        $this->assertSame(201, $res->getStatusCode());
        $row = DB::table('tasks')->where('name', 'created by PM')->first();
        $this->assertNotNull($row);
        $this->assertSame($this->tenantA->id, $row->tenant_id);
        $this->assertSame($pm->id, $row->created_by);
    }

    #[Group('uat-recovery-04c-create')]
    public function test_legacy_role_only_creators_are_denied_and_no_task_is_created(): void
    {
        $denied = [
            'Member with legacy team_member' => $this->actor('Project Member', 'team_member'),
            'legacy admin, no DB role' => $this->actor(null, 'admin'),
            'legacy project_manager, no DB role' => $this->actor(null, 'project_manager'),
            'legacy designer, no DB role' => $this->actor(null, 'designer'),
        ];
        foreach ($denied as $who => $user) {
            $this->assertFalse($user->hasPermission('task.create'), $who);
            $before = DB::table('tasks')->count();

            $res = $this->postAs($user, '/api/tasks', $this->payload($this->project, "denied {$who}"));

            $this->assertSame(403, $res->getStatusCode(), $who);
            $this->assertSame($before, DB::table('tasks')->count(), "{$who}: no task created");
        }
    }

    #[Group('uat-recovery-04c-create')]
    public function test_the_guard_holds_on_every_registered_creation_endpoint(): void
    {
        $member = $this->actor('Project Member', 'team_member');   // holds no task.create
        $before = DB::table('tasks')->count();

        $this->postAs($member, '/api/tasks', $this->payload($this->project, 'x1'));
        $this->postAs($member, '/api/zena/tasks', $this->payload($this->project, 'x2'));
        $this->actingAs($member, 'api');
        $this->postAs($member, '/api/v1/tasks', $this->payload($this->project, 'x3'));
        $this->actingAs($member)->withSession(['_token' => 't'])->call('POST', '/app/tasks', $this->payload($this->project, 'x4') + ['_token' => 't']);
        $this->actingAs($member)->withSession(['_token' => 't'])->call('POST', '/tasks', $this->payload($this->project, 'x5') + ['_token' => 't']);
        $this->actingAs($member)->withSession(['_token' => 't'])->call('POST', '/operator/schedule/tasks', $this->payload($this->project, 'x6') + ['_token' => 't']);

        $this->assertSame($before, DB::table('tasks')->count(), 'no creation endpoint lets a Member without task.create create a task');
    }

    #[Group('uat-recovery-04c-create')]
    public function test_a_task_cannot_be_created_inside_another_tenants_project(): void
    {
        $pm = $this->actor('Project Manager', 'project_manager'); // tenant A

        foreach (['/api/tasks', '/api/zena/tasks', '/api/v1/tasks'] as $uri) {
            if (str_contains($uri, '/v1/')) {
                $this->actingAs($pm, 'api');
            }
            $res = $this->postAs($pm, $uri, $this->payload($this->foreignProject, 'cross-tenant ' . $uri));
            $this->assertContains($res->getStatusCode(), [403, 404, 422], $uri);
        }
        $this->actingAs($pm)->withSession(['_token' => 't'])->call('POST', '/app/tasks', $this->payload($this->foreignProject, 'cross-tenant web') + ['_token' => 't']);

        $this->assertSame(0, DB::table('tasks')->where('project_id', $this->foreignProject->id)->count(), 'tenant A must not attach tasks to tenant B\'s project');
    }

    #[Group('uat-recovery-04c-create')]
    public function test_the_patch_grants_no_permission(): void
    {
        $granted = fn () => DB::table('role_permissions')->count();
        $before = $granted();
        $member = $this->actor('Project Member', 'team_member');

        $this->postAs($member, '/api/tasks', $this->payload($this->project, 'probe'));

        $this->assertSame($before, $granted());
        $this->assertFalse(DB::table('role_permissions')->join('roles', 'roles.id', '=', 'role_permissions.role_id')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('roles.name', 'Project Member')->where('permissions.name', 'task.create')->exists(), 'seeded Project Member still does not hold task.create');
    }
}
