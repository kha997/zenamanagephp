<?php declare(strict_types=1);

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
 * O-15 SECURITY GATE (planning evidence only — NO runtime change in this round).
 * Owner-approved principles: project.update does not imply authority to appoint a PM; no self-claim of an unassigned project;
 * PM assignment needs independent authority; no implicit System Admin bypass; tenant-bound; PM changes auditable; existing
 * PM-less projects are never auto-assigned.
 *
 * -red tests state the EXPECTED-SECURE behaviour and FAIL on current main (that is the retained evidence);
 * -guard tests must stay green before and after any O-15 remedy (metadata editing, tenant limits, legitimate hand-over).
 * No permission is created or granted here: the remedy's new permission does not exist and is deliberately not simulated.
 */
class UatRecovery04cO15GateTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $pm;       // seeded PM, PM of $managed
    private User $other;    // seeded PM of nothing (holds project.update)
    private User $admin;    // seeded System Admin
    private User $member;
    private Project $managed;
    private Project $unmanaged;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->tenant = Tenant::factory()->create();
        $this->pm = $this->actor('Project Manager');
        $this->other = $this->actor('Project Manager');
        $this->admin = $this->actor('System Admin');
        $this->member = $this->actor('Project Member');
        $this->managed = Project::factory()->create(['tenant_id' => $this->tenant->id, 'pm_id' => $this->pm->id, 'name' => 'managed']);
        $this->unmanaged = Project::factory()->create(['tenant_id' => $this->tenant->id, 'pm_id' => null, 'name' => 'unmanaged']);
    }

    private function actor(string $role): User
    {
        $u = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => null]);
        DB::table('user_roles')->insert(['user_id' => $u->id, 'role_id' => DB::table('roles')->where('name', $role)->value('id'), 'created_at' => now(), 'updated_at' => now()]);

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

    // ------------------------------------------------------------------ RED (expected-secure; fails today)

    #[Group('uat-recovery-04c-o15-red')]
    public function test_a_system_admin_cannot_self_claim_an_unassigned_project(): void
    {
        $this->putAs($this->admin, $this->unmanaged, ['pm_id' => $this->admin->id]);

        $this->assertNull($this->pmOf($this->unmanaged), 'System Admin holds project.update but has no implicit authority to appoint a PM (O-14/O-15)');
    }

    #[Group('uat-recovery-04c-o15-red')]
    public function test_a_pm_of_another_project_cannot_self_claim_an_unassigned_project(): void
    {
        $this->putAs($this->other, $this->unmanaged, ['pm_id' => $this->other->id]);

        $this->assertNull($this->pmOf($this->unmanaged));
    }

    #[Group('uat-recovery-04c-o15-red')]
    public function test_a_pm_change_is_auditable_with_previous_and_new_manager(): void
    {
        $this->putAs($this->pm, $this->managed, ['pm_id' => $this->other->id]); // current PM hands over (legitimate today)
        $this->assertSame($this->other->id, $this->pmOf($this->managed), 'precondition: hand-over by the current PM works');

        $rows = DB::table('audit_logs')->get()->filter(fn ($r) => str_contains(strtolower(json_encode([$r->old_data ?? null, $r->new_data ?? null, $r->meta ?? null])), 'pm_id'));
        $this->assertGreaterThan(0, $rows->count(), 'a PM change must leave an audit record naming pm_id');
        $blob = $rows->map(fn ($r) => json_encode([$r->old_data, $r->new_data, $r->meta]))->implode(' ');
        $this->assertStringContainsString($this->pm->id, $blob, 'previous PM recorded');
        $this->assertStringContainsString($this->other->id, $blob, 'new PM recorded');
    }

    // ------------------------------------------------------------------ GUARDS (must hold now and after any remedy)

    #[Group('uat-recovery-04c-o15-guard')]
    public function test_project_metadata_can_still_be_edited_without_touching_the_pm(): void
    {
        $this->putAs($this->other, $this->unmanaged, ['description' => 'edited by a project.update holder']);

        $this->assertSame('edited by a project.update holder', DB::table('projects')->where('id', $this->unmanaged->id)->value('description'));
        $this->assertNull($this->pmOf($this->unmanaged), 'editing metadata must not appoint a PM');
    }

    #[Group('uat-recovery-04c-o15-guard')]
    public function test_nothing_assigns_existing_pm_less_projects_automatically(): void
    {
        $before = DB::table('projects')->whereNull('pm_id')->count();
        $this->seed(DatabaseSeeder::class); // re-seeding must not adopt projects
        $this->putAs($this->member, $this->unmanaged, ['pm_id' => $this->member->id]); // a Member cannot either

        $this->assertSame($before, DB::table('projects')->whereNull('pm_id')->count());
    }

    #[Group('uat-recovery-04c-o15-guard')]
    public function test_cross_tenant_and_third_party_appointments_stay_denied(): void
    {
        $foreignTenant = Tenant::factory()->create();
        $foreign = User::factory()->create(['tenant_id' => $foreignTenant->id, 'role' => null]);

        $this->putAs($this->pm, $this->managed, ['pm_id' => $foreign->id]);          // other tenant's user
        $this->putAs($this->other, $this->managed, ['pm_id' => $this->other->id]);   // someone else's managed project

        $this->assertSame($this->pm->id, $this->pmOf($this->managed));
    }
}
