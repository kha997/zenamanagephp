<?php

declare(strict_types=1);

namespace Tests\Feature\Recovery;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * UAT RECOVERY 04d — O-15 "A-minimal" (Owner-approved): independent authority to appoint / replace / clear a project's PM.
 * Real DatabaseSeeder. `project.assign_manager` is seeded as a DEFINITION ONLY and granted to nobody; the only place a grant is ever
 * made is the explicitly labelled fixture `assigner()` below (a test-owned role), never the seed.
 */
class UatRecovery04dPmAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $foreignTenant;

    private User $pm;          // seeded PM, current PM of $managed

    private User $other;       // seeded PM of nothing

    private User $admin;       // seeded System Admin

    private User $member;

    private Project $managed;

    private Project $pmLess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->tenant = Tenant::factory()->create();
        $this->foreignTenant = Tenant::factory()->create();
        $this->pm = $this->actor('Project Manager');
        $this->other = $this->actor('Project Manager');
        $this->admin = $this->actor('System Admin');
        $this->member = $this->actor('Project Member');
        $this->managed = Project::factory()->create(['tenant_id' => $this->tenant->id, 'pm_id' => $this->pm->id, 'name' => 'managed']);
        $this->pmLess = Project::factory()->create(['tenant_id' => $this->tenant->id, 'pm_id' => null, 'name' => 'pm-less']);
    }

    private function actor(string $role, ?Tenant $tenant = null): User
    {
        $u = User::factory()->create(['tenant_id' => ($tenant ?? $this->tenant)->id, 'role' => null]);
        DB::table('user_roles')->insert(['user_id' => $u->id, 'role_id' => DB::table('roles')->where('name', $role)->value('id'), 'created_at' => now(), 'updated_at' => now()]);

        return $u;
    }

    /** EXPLICIT FIXTURE (not a seed grant): a test-owned role carrying the given permissions. */
    private function assigner(array $permissionNames, ?Tenant $tenant = null): User
    {
        $role = (string) Str::ulid();
        DB::table('roles')->insert(['id' => $role, 'name' => 'S4d fixture ' . $role, 'scope' => 'system', 'allow_override' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($permissionNames as $name) {
            DB::table('role_permissions')->insert(['role_id' => $role, 'permission_id' => DB::table('permissions')->where('name', $name)->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        }
        $u = User::factory()->create(['tenant_id' => ($tenant ?? $this->tenant)->id, 'role' => null]);
        DB::table('user_roles')->insert(['user_id' => $u->id, 'role_id' => $role, 'created_at' => now(), 'updated_at' => now()]);

        return $u;
    }

    private function putAs(User $u, Project $p, array $extra = [])
    {
        Sanctum::actingAs($u);

        return $this->withHeaders(['X-Tenant-ID' => (string) $u->tenant_id])
            ->putJson("/api/projects/{$p->id}", ['name' => $p->name, 'start_date' => '2026-12-01', 'end_date' => '2027-02-01'] + $extra);
    }

    private function pmOf(Project $p): ?string
    {
        return DB::table('projects')->where('id', $p->id)->value('pm_id');
    }

    private function audits(?Project $p = null)
    {
        return DB::table('audit_logs')->where('action', 'project.manager.changed')->when($p, fn ($q) => $q->where('entity_id', $p->id))->get();
    }

    // ------------------------------------------------------------------ permission definition

    #[Group('uat-recovery-04d')]
    public function test_O16_the_permission_is_granted_to_exactly_the_canonical_system_admin_role_and_nobody_else(): void
    {
        $perm = DB::table('permissions')->where('name', 'project.assign_manager')->first();
        $this->assertNotNull($perm);
        $this->assertSame('project.assign_manager', $perm->code);

        $holders = DB::table('role_permissions')->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->where('role_permissions.permission_id', $perm->id)->get(['roles.id', 'roles.name', 'roles.scope', 'roles.tenant_id']);
        $this->assertCount(1, $holders, 'exactly one role holds project.assign_manager');
        $this->assertSame('System Admin', $holders[0]->name);
        $this->assertSame('system', $holders[0]->scope);
        $this->assertNull($holders[0]->tenant_id, 'the canonical GLOBAL system role');
        $this->assertSame(1, DB::table('roles')->where('name', 'System Admin')->count(), 'verified role identity: single System Admin role');

        $this->assertTrue($this->admin->hasPermission('project.assign_manager'));
        foreach ([$this->pm, $this->other, $this->member] as $u) {
            $this->assertFalse($u->hasPermission('project.assign_manager'));
        }
        // legacy admin value alone confers nothing
        $legacy = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->assertFalse($legacy->hasPermission('project.assign_manager'));
        foreach (['Project Manager', 'Project Member'] as $r) {
            $this->assertSame(0, DB::table('role_permissions')->join('roles', 'roles.id', '=', 'role_permissions.role_id')->where('roles.name', $r)->where('role_permissions.permission_id', $perm->id)->count());
        }
    }

    #[Group('uat-recovery-04d')]
    public function test_O16_seeding_the_grant_is_idempotent_and_never_spreads_to_other_roles(): void
    {
        $pid = DB::table('permissions')->where('name', 'project.assign_manager')->value('id');
        $holders = fn () => DB::table('role_permissions')->join('roles', 'roles.id', '=', 'role_permissions.role_id')->where('role_permissions.permission_id', $pid)->pluck('roles.name')->all();
        $this->assertSame(['System Admin'], $holders());
        $permissionRows = DB::table('permissions')->count();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['System Admin'], $holders(), 'still exactly one holder row after repeated seeding');
        $this->assertSame(1, DB::table('permissions')->where('code', 'project.assign_manager')->count());
        $this->assertSame($permissionRows, DB::table('permissions')->count(), 'no duplicate permission rows');
        // note: unrelated *.view grants to Project Member grow on re-seed from OTHER seeders (pre-existing, out of O-16 scope; see report)
    }

    #[Group('uat-recovery-04d')]
    public function test_O16_system_admin_can_appoint_a_pm_to_a_pm_less_project_in_its_tenant_and_it_is_audited(): void
    {
        $res = $this->putAs($this->admin, $this->pmLess, ['pm_id' => $this->other->id]);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame($this->other->id, $this->pmOf($this->pmLess));
        $row = $this->audits($this->pmLess)->first();
        $this->assertNotNull($row);
        $this->assertSame($this->admin->id, $row->user_id);
        $this->assertSame($this->tenant->id, $row->tenant_id);
        $this->assertSame($this->pmLess->id, $row->entity_id);
        $this->assertSame(['pm_id' => null], json_decode($row->old_data, true));
        $this->assertSame(['pm_id' => $this->other->id], json_decode($row->new_data, true));
        $this->assertNotNull($row->created_at);
    }

    #[Group('uat-recovery-04d')]
    public function test_O16_a_holder_cannot_self_appoint_as_pm_but_can_appoint_someone_else(): void
    {
        $this->putAs($this->admin, $this->pmLess, ['pm_id' => $this->admin->id]);
        $this->assertNull($this->pmOf($this->pmLess), 'System Admin may not appoint itself (O-15 no self-claim)');
        $this->assertCount(0, $this->audits());

        $this->putAs($this->admin, $this->pmLess, ['pm_id' => $this->other->id]);
        $this->assertSame($this->other->id, $this->pmOf($this->pmLess));
    }

    #[Group('uat-recovery-04d')]
    public function test_O16_system_admin_has_no_cross_tenant_reach_and_pm_member_remain_denied(): void
    {
        $foreignAdmin = $this->actor('System Admin', $this->foreignTenant);
        $foreignProject = Project::factory()->create(['tenant_id' => $this->foreignTenant->id, 'pm_id' => null]);
        $foreignUser = $this->actor('Project Manager', $this->foreignTenant);

        $this->putAs($this->admin, $foreignProject, ['pm_id' => $foreignUser->id]);          // act on another tenant's project
        $this->putAs($this->admin, $this->pmLess, ['pm_id' => $foreignUser->id]);            // appoint another tenant's user
        $this->putAs($foreignAdmin, $this->pmLess, ['pm_id' => $foreignUser->id]);           // foreign admin on our project
        $this->putAs($this->other, $this->pmLess, ['pm_id' => $this->other->id]);            // PM self-claim
        $this->putAs($this->member, $this->pmLess, ['pm_id' => $this->member->id]);          // Member

        $this->assertNull($this->pmOf($foreignProject));
        $this->assertNull($this->pmOf($this->pmLess));
        $this->assertCount(0, $this->audits());
    }

    // ------------------------------------------------------------------ the independent authority

    #[Group('uat-recovery-04d')]
    public function test_a_holder_of_project_assign_manager_can_appoint_for_a_pm_less_project_and_it_is_audited(): void
    {
        $holder = $this->assigner(['project.update', 'project.assign_manager']);

        $res = $this->putAs($holder, $this->pmLess, ['pm_id' => $this->other->id]);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame($this->other->id, $this->pmOf($this->pmLess));
        $row = $this->audits($this->pmLess)->first();
        $this->assertNotNull($row, 'audit event project.manager.changed written');
        $this->assertSame(['pm_id' => null], json_decode($row->old_data, true));
        $this->assertSame(['pm_id' => $this->other->id], json_decode($row->new_data, true));
        $this->assertSame($holder->id, $row->user_id);
        $this->assertSame($this->tenant->id, $row->tenant_id);
    }

    #[Group('uat-recovery-04d')]
    public function test_the_holder_can_replace_and_clear_a_managed_projects_pm(): void
    {
        $holder = $this->assigner(['project.update', 'project.assign_manager']);

        $this->putAs($holder, $this->managed, ['pm_id' => $this->other->id]);
        $this->assertSame($this->other->id, $this->pmOf($this->managed));
        $this->putAs($holder, $this->managed, ['pm_id' => null]);
        $this->assertNull($this->pmOf($this->managed));
        $this->assertCount(2, $this->audits($this->managed));
    }

    #[Group('uat-recovery-04d')]
    public function test_the_permission_alone_is_not_enough_project_update_is_still_required(): void
    {
        $onlyAssign = $this->assigner(['project.assign_manager']);

        $res = $this->putAs($onlyAssign, $this->pmLess, ['pm_id' => $this->other->id]);

        $this->assertSame(403, $res->getStatusCode());
        $this->assertNull($this->pmOf($this->pmLess));
        $this->assertCount(0, $this->audits());
    }

    #[Group('uat-recovery-04d')]
    public function test_holders_are_tenant_bound_in_both_directions(): void
    {
        $holder = $this->assigner(['project.update', 'project.assign_manager']);
        $foreignUser = $this->actor('Project Manager', $this->foreignTenant);
        $foreignProject = Project::factory()->create(['tenant_id' => $this->foreignTenant->id, 'pm_id' => null]);

        $this->putAs($holder, $this->pmLess, ['pm_id' => $foreignUser->id]);            // appoint another tenant's user
        $this->putAs($holder, $foreignProject, ['pm_id' => $this->other->id]);          // act on another tenant's project

        $this->assertNull($this->pmOf($this->pmLess));
        $this->assertNull($this->pmOf($foreignProject));
        $this->assertCount(0, $this->audits());
    }

    #[Group('uat-recovery-04d')]
    public function test_O16_system_admin_can_appoint_through_the_bulk_path_and_it_is_audited(): void
    {
        $this->admin->forceFill(['role' => 'admin'])->save(); // legacy value only passes the parameterless gate; authority is the DB role
        Sanctum::actingAs($this->admin);

        $this->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])->postJson('/api/auth/bulk/projects/update', ['updates' => [['id' => $this->pmLess->id, 'data' => ['pm_id' => $this->other->id]]]]);

        $this->assertSame($this->other->id, $this->pmOf($this->pmLess));
        $this->assertCount(1, $this->audits($this->pmLess));
    }

    // ------------------------------------------------------------------ everyone else is denied (and leaves no trace)

    #[Group('uat-recovery-04d')]
    public function test_non_holders_cannot_appoint_on_any_path_and_nothing_is_audited(): void
    {
        foreach ([$this->other, $this->member] as $who) {
            $this->putAs($who, $this->pmLess, ['pm_id' => $who->id]);
            $this->putAs($who, $this->managed, ['pm_id' => $who->id]);
            Sanctum::actingAs($who);
            $this->actingAs($who)->withSession(['_token' => 't'])->call('PUT', "/app/projects/{$this->pmLess->id}", ['name' => 'pm-less', 'start_date' => '2026-12-01', 'end_date' => '2027-02-01', 'pm_id' => $who->id, '_token' => 't']);
        }
        // bulk path: EXPLICIT FIXTURE with bulk + project.update but WITHOUT project.assign_manager (legacy value only passes the parameterless gate)
        $bulkUser = $this->assigner(['auth.bulk.manage', 'project.update']);
        $bulkUser->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($bulkUser);
        $this->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])->postJson('/api/auth/bulk/projects/update', ['updates' => [['id' => $this->pmLess->id, 'data' => ['pm_id' => $bulkUser->id]]]]);

        $this->assertNull($this->pmOf($this->pmLess));
        $this->assertSame($this->pm->id, $this->pmOf($this->managed));
        $this->assertCount(0, $this->audits(), 'denied attempts leave no audit record and no change');
    }

    #[Group('uat-recovery-04d')]
    public function test_the_current_pm_can_hand_over_or_clear_and_it_is_audited_with_before_and_after(): void
    {
        $this->putAs($this->pm, $this->managed, ['pm_id' => $this->other->id]);

        $this->assertSame($this->other->id, $this->pmOf($this->managed));
        $row = $this->audits($this->managed)->first();
        $this->assertSame(['pm_id' => $this->pm->id], json_decode($row->old_data, true));
        $this->assertSame(['pm_id' => $this->other->id], json_decode($row->new_data, true));

        // the former PM has lost authority; the new PM may clear it
        $this->putAs($this->pm, $this->managed, ['pm_id' => $this->pm->id]);
        $this->assertSame($this->other->id, $this->pmOf($this->managed));
        $this->putAs($this->other, $this->managed, ['pm_id' => null]);
        $this->assertNull($this->pmOf($this->managed));
    }

    #[Group('uat-recovery-04d')]
    public function test_metadata_edits_without_a_pm_change_need_no_extra_authority_and_write_no_pm_audit(): void
    {
        $this->putAs($this->other, $this->pmLess, ['description' => 'edited']);

        $this->assertSame('edited', DB::table('projects')->where('id', $this->pmLess->id)->value('description'));
        $this->assertNull($this->pmOf($this->pmLess));
        $this->assertCount(0, $this->audits());
        // sending the unchanged PM (the web form always posts it) is also fine
        $this->putAs($this->pm, $this->managed, ['pm_id' => $this->pm->id, 'description' => 'edited too']);
        $this->assertSame($this->pm->id, $this->pmOf($this->managed));
    }

    // ------------------------------------------------------------------ creation

    #[Group('uat-recovery-04d')]
    public function test_the_creator_names_the_first_pm_at_creation_and_it_is_audited_cross_tenant_pm_rejected(): void
    {
        $creator = $this->actor('Project Manager');
        Sanctum::actingAs($creator);
        $h = ['X-Tenant-ID' => (string) $creator->tenant_id];
        $payload = ['name' => 'S4d created', 'start_date' => '2026-12-01', 'end_date' => '2027-02-01'];

        $this->withHeaders($h)->postJson('/api/projects', $payload + ['pm_id' => $this->other->id])->assertStatus(201);
        $id = DB::table('projects')->where('name', 'S4d created')->value('id');
        $this->assertSame($this->other->id, DB::table('projects')->where('id', $id)->value('pm_id'));
        $row = DB::table('audit_logs')->where('action', 'project.manager.changed')->where('entity_id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame(['pm_id' => null], json_decode($row->old_data, true));
        $this->assertSame(['pm_id' => $this->other->id], json_decode($row->new_data, true));

        $foreign = $this->actor('Project Manager', $this->foreignTenant);
        $count = DB::table('projects')->count();
        $this->assertSame(422, $this->withHeaders($h)->postJson('/api/projects', ['name' => 'S4d foreign pm'] + $payload + ['pm_id' => $foreign->id])->getStatusCode());
        $this->assertSame($count, DB::table('projects')->count());
    }

    // ------------------------------------------------------------------ web form

    #[Group('uat-recovery-04d')]
    public function test_the_edit_form_shows_the_pm_selector_only_to_actors_who_may_change_it(): void
    {
        $holder = $this->assigner(['project.update', 'project.assign_manager']);
        $selector = '<select id="pm_id"';

        $this->assertStringNotContainsString($selector, $this->actingAs($this->other)->get("/app/projects/{$this->pmLess->id}/edit")->getContent(), 'a plain PM cannot appoint → no selector');
        $this->assertStringContainsString($selector, $this->actingAs($this->pm)->get("/app/projects/{$this->managed->id}/edit")->getContent(), 'the current PM sees it');
        $this->assertStringContainsString($selector, $this->actingAs($holder)->get("/app/projects/{$this->pmLess->id}/edit")->getContent(), 'a project.assign_manager holder sees it');
    }

    // ------------------------------------------------------------------ report command / no backfill

    #[Group('uat-recovery-04d')]
    public function test_the_pm_less_report_is_read_only_and_never_assigns_anyone(): void
    {
        $before = [DB::table('projects')->whereNull('pm_id')->count(), DB::table('audit_logs')->count(), DB::table('projects')->pluck('pm_id', 'id')->all()];

        $code = Artisan::call('projects:pm-less-report');
        $out = Artisan::output();

        $this->assertSame(0, $code);
        $this->assertStringContainsString($this->pmLess->id, $out);
        $this->assertStringNotContainsString($this->managed->id, $out, 'managed projects are not listed');
        $this->assertSame($before, [DB::table('projects')->whereNull('pm_id')->count(), DB::table('audit_logs')->count(), DB::table('projects')->pluck('pm_id', 'id')->all()]);
        $this->seed(DatabaseSeeder::class);
        $this->assertNull($this->pmOf($this->pmLess), 're-seeding does not adopt PM-less projects either');
    }
}
