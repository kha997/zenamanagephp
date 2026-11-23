<?php

declare(strict_types=1);

namespace Tests\Feature\Recovery;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * UAT RECOVERY 03b — S3b: the 7 project WRITE routes under `api/projects` whose controller already enforces a
 * specific permission (project.create / project.update / project.delete) move from the parameterless `rbac` gate to
 * `rbac:<that same permission>`.
 *
 * STATUS: APPLIED (Owner O-9 approved amending the 4 pinned assertions). History: the runtime change was first
 * (evidence/s3b-routes-api.patch) broke 4 pinned,
 * previously-green tests (GAP-035 `RouteNameCollisionInvariantTest::test_the_27_gap035_routes_preserve_middleware`
 * and 3 `ProjectApiTest::*_rejects_missing_permission` that pin error code RBAC_ACCESS_DENIED). Applying the patch makes
 * all 14 tests here pass but requires Owner approval to amend those pinned assertions.
 * Read routes and every other parameterless-only route are NOT touched (O-8).
 *
 * Approved mapping (derived only from permissions Api\ProjectController already enforces):
 *   POST   api/projects                                   -> project.create
 *   PUT    api/projects/{project}                         -> project.update
 *   PATCH  api/projects/{project}                         -> project.update
 *   DELETE api/projects/{project}                         -> project.delete
 *   POST   api/projects/{project}/status                  -> project.update
 *   POST   api/projects/{projectId}/recalculate-progress  -> project.update
 *   POST   api/projects/{projectId}/recalculate-actual-cost -> project.update
 */
class UatRecovery03bProjectWriteRouteGateTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
    }

    private function actor(?string $seededRole, ?string $legacy = null, ?Tenant $tenant = null): User
    {
        $user = User::factory()->create(['tenant_id' => ($tenant ?? $this->tenantA)->id, 'role' => $legacy]);
        if ($seededRole !== null) {
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => DB::table('roles')->where('name', $seededRole)->value('id'),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $user;
    }

    private function call_as(User $u, string $method, string $uri, array $data = [])
    {
        Sanctum::actingAs($u);

        return $this->withHeaders(['X-Tenant-ID' => (string) $u->tenant_id])->json($method, $uri, $data);
    }

    private function project(?Tenant $t = null): Project
    {
        return Project::factory()->create(['tenant_id' => ($t ?? $this->tenantA)->id, 'name' => 'S3b original', 'status' => 'planning']);
    }

    private function snapshot(Project $p): array
    {
        $r = Project::withoutGlobalScopes()->withTrashed()->find($p->id);

        return $r === null ? ['gone'] : [$r->name, $r->status, $r->deleted_at ? 'deleted' : 'live'];
    }

    private const PAYLOAD = ['name' => 'S3b renamed', 'start_date' => '2026-12-01', 'end_date' => '2027-01-31'];

    /** @return array<string,array{0:string,1:string,2:string}> label => [method, uri-template, permission] */
    public static function writeRoutes(): array
    {
        return [
            'store' => ['POST', 'api/projects', 'project.create'],
            'update PUT' => ['PUT', 'api/projects/{p}', 'project.update'],
            'update PATCH' => ['PATCH', 'api/projects/{p}', 'project.update'],
            'destroy' => ['DELETE', 'api/projects/{p}', 'project.delete'],
            'status' => ['POST', 'api/projects/{p}/status', 'project.update'],
            'recalculate-progress' => ['POST', 'api/projects/{p}/recalculate-progress', 'project.update'],
            'recalculate-actual-cost' => ['POST', 'api/projects/{p}/recalculate-actual-cost', 'project.update'],
        ];
    }

    // ------------------------------------------------------------------ route table

    #[Group('uat-recovery-03b-red')]
    public function test_route_table_exactly_matches_the_approved_mapping(): void
    {
        foreach (self::writeRoutes() as $label => [$method, $uri, $perm]) {
            $pattern = str_replace('{p}', '{project}', $uri);
            $route = collect(Route::getRoutes()->getRoutes())->first(function ($r) use ($method, $pattern) {
                return in_array($method, $r->methods(), true) && ($r->uri() === $pattern || $r->uri() === str_replace('{project}', '{projectId}', $pattern));
            });
            $this->assertNotNull($route, "{$label}: route exists");
            // resolved stack = what the router really executes (applies withoutMiddleware exclusions)
            $mw = array_map(fn ($m) => is_string($m) ? $m : 'closure', app('router')->gatherRouteMiddleware($route));
            $perms = array_values(array_filter($mw, fn ($m) => str_contains($m, 'RoleBasedAccessControlMiddleware:') || str_starts_with($m, 'rbac:')));
            $generic = array_values(array_filter($mw, fn ($m) => preg_match('/RoleBasedAccessControlMiddleware$/', $m) || $m === 'rbac'));

            $this->assertCount(1, $perms, "{$label}: exactly one specific gate");
            $this->assertStringEndsWith(':' . $perm, $perms[0], "{$label}: permission");
            $this->assertSame([], $generic, "{$label}: parameterless gate removed");
            $this->assertNotEmpty(array_filter($mw, fn ($m) => str_contains($m, 'Authenticate') || str_contains($m, 'auth:')), "{$label}: authentication kept");
            $this->assertNotEmpty(array_filter($mw, fn ($m) => str_contains($m, 'TenantIsolation') || str_contains($m, 'tenant.isolation')), "{$label}: tenant isolation kept");
        }
    }

    #[Group('uat-recovery-03b-guard')]
    public function test_route_names_and_every_other_parameterless_route_are_unchanged(): void
    {
        foreach (['projects.index', 'projects.show', 'projects.store', 'projects.update', 'projects.destroy'] as $name) {
            $this->assertTrue(Route::has($name), "route name {$name} preserved");
        }
        // Read routes keep the parameterless gate (O-8): exactly as before.
        foreach ([['GET', 'api/projects'], ['GET', 'api/projects/{project}'], ['GET', 'api/projects/statistics'], ['GET', 'api/projects/dropdown'], ['GET', 'api/projects/{projectId}/tasks']] as [$m, $uri]) {
            $route = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => in_array($m, $r->methods(), true) && $r->uri() === $uri);
            $this->assertNotNull($route, "{$m} {$uri}");
            $generic = array_filter(app('router')->gatherRouteMiddleware($route), fn ($x) => is_string($x) && (preg_match('/RoleBasedAccessControlMiddleware$/', $x) || $x === 'rbac'));
            $this->assertNotEmpty($generic, "{$m} {$uri} still behind the parameterless gate");
        }
    }

    // ------------------------------------------------------------------ behaviour per actor

    /** Permission holders with ONLY the seeded DB role pass; they hold the permission the controller already required. */
    #[Group('uat-recovery-03b-red')]
    public function test_seeded_system_admin_reaches_every_write_route(): void
    {
        $admin = $this->actor('System Admin');
        $p = $this->project();

        $this->assertSame(201, $this->call_as($admin, 'POST', '/api/projects', self::PAYLOAD)->getStatusCode(), 'store');
        $this->assertContains($this->call_as($admin, 'PUT', "/api/projects/{$p->id}", self::PAYLOAD)->getStatusCode(), [200]);
        $this->assertContains($this->call_as($admin, 'PATCH', "/api/projects/{$p->id}", self::PAYLOAD)->getStatusCode(), [200]);
        $this->assertContains($this->call_as($admin, 'POST', "/api/projects/{$p->id}/status", ['status' => 'active'])->getStatusCode(), [200]);
        $this->assertContains($this->call_as($admin, 'POST', "/api/projects/{$p->id}/recalculate-progress")->getStatusCode(), [200]);
        $this->assertContains($this->call_as($admin, 'POST', "/api/projects/{$p->id}/recalculate-actual-cost")->getStatusCode(), [200]);
        $this->assertContains($this->call_as($admin, 'DELETE', "/api/projects/{$p->id}")->getStatusCode(), [200, 204]);
    }

    #[Group('uat-recovery-03b-red')]
    public function test_seeded_project_manager_can_create_update_but_not_delete(): void
    {
        $pm = $this->actor('Project Manager');
        $p = $this->project();

        $this->assertSame(201, $this->call_as($pm, 'POST', '/api/projects', self::PAYLOAD)->getStatusCode());
        $this->assertSame(200, $this->call_as($pm, 'PUT', "/api/projects/{$p->id}", self::PAYLOAD)->getStatusCode());
        $this->assertSame('S3b renamed', Project::withoutGlobalScopes()->find($p->id)->name);

        $before = $this->snapshot($p);
        $res = $this->call_as($pm, 'DELETE', "/api/projects/{$p->id}");
        $this->assertSame(403, $res->getStatusCode(), 'PM does not hold project.delete (no grant added)');
        $this->assertSame($before, $this->snapshot($p));
    }

    /**
     * @dataProvider writeRoutes
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('writeRoutes')]
    #[Group('uat-recovery-03b-guard')]
    public function test_actors_without_the_permission_are_denied_with_zero_mutation(string $method, string $uri, string $perm): void
    {
        $denied = [
            'DB-only Project Member' => $this->actor('Project Member'),
            'legacy team_member Member' => $this->actor('Project Member', 'team_member'),
            'legacy admin, no DB role' => $this->actor(null, 'admin'),
            'legacy project_manager, no DB role' => $this->actor(null, 'project_manager'),
            'no role at all' => $this->actor(null),
            'cross-tenant System Admin' => $this->actor('System Admin', 'admin', $this->tenantB),
        ];
        // PM lacks project.delete → also denied on destroy only.
        $denied['seeded PM (delete only)'] = $this->actor('Project Manager');

        foreach ($denied as $who => $user) {
            if ($who === 'seeded PM (delete only)' && $perm !== 'project.delete') {
                continue;
            }
            if ($who === 'cross-tenant System Admin' && $uri === 'api/projects') {
                continue; // creating a project in the user's OWN tenant is legitimate; isolation is asserted on existing projects
            }
            $p = $this->project();
            $before = $this->snapshot($p);
            $count = Project::withoutGlobalScopes()->count();
            $target = str_replace('{p}', (string) $p->id, $uri);

            $res = $this->call_as($user, $method, '/' . $target, $method === 'DELETE' ? [] : (str_contains($uri, '/status') ? ['status' => 'active'] : self::PAYLOAD));

            $this->assertGreaterThanOrEqual(400, $res->getStatusCode(), "{$method} {$uri} / {$who}: got {$res->getStatusCode()}");
            $this->assertLessThan(500, $res->getStatusCode(), "{$method} {$uri} / {$who}: must be a clean denial");
            $this->assertSame($before, $this->snapshot($p), "{$method} {$uri} / {$who}: no mutation");
            $this->assertSame($count, Project::withoutGlobalScopes()->count(), "{$method} {$uri} / {$who}: no row created/removed");
        }
    }

    #[Group('uat-recovery-03b-guard')]
    public function test_users_who_already_worked_still_work(): void
    {
        // legacy-valued PM that ALSO holds the permission: worked before, must work after.
        $pm = $this->actor('Project Manager', 'project_manager');
        $p = $this->project();
        $this->assertSame(201, $this->call_as($pm, 'POST', '/api/projects', self::PAYLOAD)->getStatusCode());
        $this->assertSame(200, $this->call_as($pm, 'PUT', "/api/projects/{$p->id}", self::PAYLOAD)->getStatusCode());
        $this->assertSame(200, $this->call_as($pm, 'POST', "/api/projects/{$p->id}/status", ['status' => 'active'])->getStatusCode());
    }

    #[Group('uat-recovery-03b-guard')]
    public function test_cross_tenant_write_is_denied_even_for_a_permission_holder(): void
    {
        $foreign = $this->project($this->tenantB);
        $holder = $this->actor('System Admin', 'admin'); // tenant A, holds every project.* permission
        $before = $this->snapshot($foreign);

        foreach ([['PUT', "/api/projects/{$foreign->id}", self::PAYLOAD], ['POST', "/api/projects/{$foreign->id}/status", ['status' => 'active']], ['DELETE', "/api/projects/{$foreign->id}", []], ['POST', "/api/projects/{$foreign->id}/recalculate-progress", []]] as [$m, $u, $d]) {
            $res = $this->call_as($holder, $m, $u, $d);
            $this->assertContains($res->getStatusCode(), [403, 404], "{$m} {$u}");
        }
        $this->assertSame($before, $this->snapshot($foreign));
    }

    // ------------------------------------------------------------------ O-8: reads untouched

    #[Group('uat-recovery-03b-guard')]
    public function test_read_routes_keep_the_parameterless_gate_semantics(): void
    {
        $this->assertSame(403, $this->call_as($this->actor('Project Manager'), 'GET', '/api/projects')->getStatusCode(), 'DB-only PM still denied on reads (O-8)');
        $this->assertSame(403, $this->call_as($this->actor('System Admin'), 'GET', '/api/projects')->getStatusCode());
        $this->assertSame(200, $this->call_as($this->actor('Project Member', 'team_member'), 'GET', '/api/projects')->getStatusCode(), 'legacy-valued Member read unchanged');
    }
}
