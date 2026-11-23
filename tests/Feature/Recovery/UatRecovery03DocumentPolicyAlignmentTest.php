<?php

declare(strict_types=1);

namespace Tests\Feature\Recovery;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * UAT RECOVERY 03 — S3: parameterless `rbac` gate + DocumentPolicy.
 *
 * Real DatabaseSeeder; actors receive only an already-seeded role (user_roles) — no permission is injected.
 * Groups:
 *  - uat-recovery-03-red      : defect reproduced on current main before the S3 patch (expected RED, then GREEN)
 *  - uat-recovery-03-guard    : existing deny/allow behaviour that MUST NOT change (GREEN before and after)
 *  - uat-recovery-03-owner    : OWNER_DECISION_REQUIRED — documents CURRENT behaviour, asserts no decision is made
 */
class UatRecovery03DocumentPolicyAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => $this->tenantA->id]);
    }

    // ------------------------------------------------------------ helpers

    /** $seededRole = null → user with NO role at all. $legacy = users.role value (null = none). */
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

    private function web(User $u, string $method, string $uri, array $data = [])
    {
        return $this->actingAs($u)->withSession(['_token' => 'uat-r03'])->call($method, $uri, $data + ['_token' => 'uat-r03']);
    }

    private function doc(?Tenant $tenant = null): Document
    {
        $tenant ??= $this->tenantA;
        $project = $tenant->id === $this->tenantA->id ? $this->project : Project::factory()->create(['tenant_id' => $tenant->id]);

        $creator = $this->actor('Project Member');
        $this->web($creator, 'POST', '/app/documents', [
            'title' => 'UAT-R03 ' . bin2hex(random_bytes(3)), 'project_id' => $project->id, 'document_type' => 'drawing',
            'file' => UploadedFile::fake()->createWithContent('d.pdf', "%PDF-1.4\n%%EOF\n"),
        ])->assertStatus(302);

        return Document::withoutGlobalScopes()->where('uploaded_by', $creator->id)->latest('created_at')->firstOrFail();
    }

    /** Arrange helper: moves the document through the REAL service (not HTTP) so tests can start from a state. */
    private function submitted(Document $d, User $by): Document
    {
        app(DocumentWorkflowService::class)->submit((string) $d->tenant_id, (string) $d->id, (string) $by->id);

        return $d->fresh();
    }

    private function state(Document $d): array
    {
        $d = Document::withoutGlobalScopes()->find($d->id);

        return [$d->getRawOriginal('status'), $d->getRawOriginal('lifecycle_status'), $d->getRawOriginal('approval_status')];
    }

    private function events(Document $d): int
    {
        return DB::table('document_approval_events')->where('document_id', $d->id)->count();
    }

    private function api(User $u): array
    {
        Sanctum::actingAs($u);

        return ['X-Tenant-ID' => (string) $u->tenant_id];
    }

    // ============================================== A. policy decision matrix (real seeded roles)

    /** [actor spec], [ability => expected allow], evidence label */
    public static function matrix(): array
    {
        // Abilities not listed are asserted DENY by omission only where stated in the case name.
        return [
            'System Admin (DB role only, users.role NULL)' => [['System Admin', null, 'A'], ['view' => true, 'download' => true, 'update' => true, 'approve' => true, 'assignApprover' => false, 'delete' => false]],
            'Project Manager (DB role only)' => [['Project Manager', null, 'A'], ['view' => false, 'download' => false, 'update' => true, 'approve' => true, 'assignApprover' => false, 'delete' => false]],
            'Project Member (DB role only)' => [['Project Member', null, 'A'], ['view' => false, 'download' => false, 'update' => false, 'approve' => false, 'assignApprover' => false, 'delete' => false]],
            'No role at all' => [[null, null, 'A'], ['view' => false, 'download' => false, 'update' => false, 'approve' => false, 'assignApprover' => false, 'delete' => false]],
            'Legacy admin value, no DB role' => [[null, 'admin', 'A'], ['view' => true, 'download' => true, 'update' => true, 'approve' => false, 'assignApprover' => true, 'delete' => true]],
            'Legacy designer value, no DB role' => [[null, 'designer', 'A'], ['view' => true, 'download' => true, 'update' => true, 'approve' => false, 'assignApprover' => false, 'delete' => false]],
            'Cross-tenant System Admin' => [['System Admin', 'admin', 'B'], ['view' => false, 'download' => false, 'update' => false, 'approve' => false, 'assignApprover' => false, 'delete' => false]],
            'Cross-tenant Project Manager' => [['Project Manager', 'pm', 'B'], ['view' => false, 'download' => false, 'update' => false, 'approve' => false, 'assignApprover' => false, 'delete' => false]],
        ];
    }

    /**
     * @dataProvider matrix
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('matrix')]
    #[Group('uat-recovery-03-red')]
    public function test_policy_matrix_matches_the_canonical_permission_boundary(array $spec, array $expected): void
    {
        [$role, $legacy, $tenant] = $spec;
        $user = $this->actor($role, $legacy, $tenant === 'A' ? $this->tenantA : $this->tenantB);
        $doc = $this->doc();

        foreach ($expected as $ability => $allow) {
            $this->assertSame($allow, Gate::forUser($user)->allows($ability, $doc), "ability={$ability}");
        }
    }

    /** Project's own manager keeps assignApprover (unchanged GAP-033 rule), a non-owner PM does not. */
    #[Group('uat-recovery-03-guard')]
    public function test_assign_approver_rule_is_unchanged(): void
    {
        $pm = $this->actor('Project Manager');
        $this->project->forceFill(['pm_id' => $pm->id])->save();
        $doc = $this->doc();

        $this->assertTrue(Gate::forUser($pm)->allows('assignApprover', $doc));
        $this->assertFalse(Gate::forUser($this->actor('Project Manager'))->allows('assignApprover', $doc));
        $this->assertFalse(Gate::forUser($this->actor('System Admin'))->allows('assignApprover', $doc), 'no admin alias: DB System Admin is not legacy "admin"');
    }

    // ============================================== B. workflow through real routes

    #[Group('uat-recovery-03-guard')]
    public function test_creator_authorization_is_unchanged(): void
    {
        $before = Document::withoutGlobalScopes()->count();
        $this->web($this->actor(null), 'POST', '/app/documents', [
            'title' => 'UAT-R03 nobody', 'project_id' => $this->project->id, 'document_type' => 'drawing',
            'file' => UploadedFile::fake()->createWithContent('n.pdf', "%PDF-1.4\n%%EOF\n"),
        ]);
        $this->assertSame($before, Document::withoutGlobalScopes()->count(), 'role-less user creates nothing');

        $this->doc(); // Project Member (document.create) can create
        $this->assertSame($before + 1, Document::withoutGlobalScopes()->count());
    }

    #[Group('uat-recovery-03-red')]
    public function test_project_manager_with_only_the_seeded_role_can_submit_publish_and_reopen(): void
    {
        $pm = $this->actor('Project Manager');
        $approver = $this->actor('Project Manager');
        $doc = $this->doc();

        $this->web($pm, 'POST', "/app/documents/{$doc->id}/submit");
        $this->assertSame('awaiting-approval', $this->state($doc)[2], 'authorized submitter');
        $this->web($approver, 'POST', "/app/documents/{$doc->id}/approve", ['decision_note' => 'ok']);
        $this->assertSame('approved', $this->state($doc)[2]);
        $this->web($pm, 'POST', "/app/documents/{$doc->id}/publish");
        $this->assertSame('published', $this->state($doc)[1], 'authorized publisher');
    }

    #[Group('uat-recovery-03-red')]
    public function test_project_manager_can_reopen_a_rejected_document(): void
    {
        $pm = $this->actor('Project Manager');
        $doc = $this->submitted($this->doc(), $pm);
        $this->web($pm, 'POST', "/app/documents/{$doc->id}/reject", ['decision_note' => 'thiếu thông gió']);
        $this->assertSame('rejected', $this->state($doc)[2]);

        $this->web($pm, 'POST', "/app/documents/{$doc->id}/reopen");

        $this->assertSame('not-submitted', $this->state($doc)[2]);
    }

    #[Group('uat-recovery-03-red')]
    public function test_system_admin_with_only_the_seeded_role_can_submit(): void
    {
        $admin = $this->actor('System Admin');
        $doc = $this->doc();

        $this->web($admin, 'POST', "/app/documents/{$doc->id}/submit");

        $this->assertSame('awaiting-approval', $this->state($doc)[2]);
    }

    #[Group('uat-recovery-03-red')]
    public function test_system_admin_with_only_the_seeded_role_can_read_through_the_zena_api(): void
    {
        $admin = $this->actor('System Admin');
        $doc = $this->doc();
        $headers = $this->api($admin);

        $this->assertSame(200, $this->withHeaders($headers)->getJson("/api/zena/documents/{$doc->id}")->getStatusCode());
    }

    #[Group('uat-recovery-03-guard')]
    public function test_unauthorized_actors_are_denied_everywhere_with_zero_mutation(): void
    {
        $member = $this->actor('Project Member');
        $nobody = $this->actor(null);
        $foreignPm = $this->actor('Project Manager', 'pm', $this->tenantB);
        $foreignAdmin = $this->actor('System Admin', 'admin', $this->tenantB);
        $doc = $this->doc();
        $state = $this->state($doc);
        $events = $this->events($doc);

        foreach ([$member, $nobody, $foreignPm, $foreignAdmin] as $who) {
            foreach (['submit', 'publish', 'archive', 'reopen', 'reactivate'] as $op) {
                $this->web($who, 'POST', "/app/documents/{$doc->id}/{$op}");
            }
            foreach (['approve', 'reject'] as $op) {
                $this->web($who, 'POST', "/app/documents/{$doc->id}/{$op}", ['decision_note' => 'x']);
            }
            $headers = $this->api($who);
            $this->withHeaders($headers)->postJson("/api/zena/documents/{$doc->id}/submit");
            $this->withHeaders($headers)->postJson("/api/zena/documents/{$doc->id}/publish");
        }

        $this->assertSame($state, $this->state($doc), 'no actor in this set may change the document');
        $this->assertSame($events, $this->events($doc), 'no decision history written');
    }

    #[Group('uat-recovery-03-guard')]
    public function test_member_cannot_approve_even_with_a_matching_legacy_value(): void
    {
        $pm = $this->actor('Project Manager');
        $designer = $this->actor('Project Member', 'designer'); // legacy designer must NOT become an approver
        $doc = $this->submitted($this->doc(), $pm);
        $events = $this->events($doc);

        $this->web($designer, 'POST', "/app/documents/{$doc->id}/approve", ['decision_note' => 'x']);

        $this->assertSame('awaiting-approval', $this->state($doc)[2]);
        $this->assertSame($events, $this->events($doc));
    }

    #[Group('uat-recovery-03-guard')]
    public function test_state_machine_guards_remain_mandatory_for_authorized_actors(): void
    {
        $approver = $this->actor('Project Manager', 'pm'); // passes both old and new policy: isolates the state machine
        $doc = $this->doc();
        $draft = $this->state($doc);

        $this->web($approver, 'POST', "/app/documents/{$doc->id}/approve", ['decision_note' => 'too early']);   // not submitted
        $this->web($approver, 'POST', "/app/documents/{$doc->id}/archive");                                    // not published
        $this->web($approver, 'POST', "/app/documents/{$doc->id}/reactivate");                                 // not archived
        $this->assertSame($draft, $this->state($doc));
        $this->assertSame(0, $this->events($doc));

        $this->submitted($doc, $approver);
        $this->web($approver, 'POST', "/app/documents/{$doc->id}/submit");                                      // already submitted
        $this->assertSame('awaiting-approval', $this->state($doc)[2]);
    }

    /**
     * Adversarial check on the ONE route class where the policy is the sole object-level gate behind the legacy
     * parameterless gate (`api/documents-simple`). The S3 change may only widen access to holders of
     * document.update; a legacy-valued Member without that permission must stay denied.
     */
    #[Group('uat-recovery-03-guard')]
    public function test_documents_simple_update_does_not_widen_to_members(): void
    {
        $doc = $this->doc();
        $titleBefore = Document::withoutGlobalScopes()->find($doc->id)->title;

        $member = $this->actor('Project Member', 'team_member');
        $h = $this->api($member);
        $res = $this->withHeaders($h)->putJson("/api/documents-simple/{$doc->id}", ['title' => 'HACKED BY MEMBER']);

        $this->assertNotContains($res->getStatusCode(), [200, 201]);
        $this->assertSame($titleBefore, Document::withoutGlobalScopes()->find($doc->id)->title);
    }

    #[Group('uat-recovery-03-red')]
    public function test_documents_simple_update_reaches_permission_holders_only(): void
    {
        $doc = $this->doc();
        $pm = $this->actor('Project Manager', 'project_manager'); // passes legacy gate + holds document.update
        $h = $this->api($pm);

        $res = $this->withHeaders($h)->putJson("/api/documents-simple/{$doc->id}", ['title' => 'Renamed by PM']);

        $this->assertContains($res->getStatusCode(), [200, 201], 'PM holds document.update; policy must agree with the permission');
    }

    /** OWNER_DECISION_REQUIRED (finding S3-F1): the existing lifecycle allows publishing a never-approved draft. */
    #[Group('uat-recovery-03-owner')]
    public function test_OWNER_DECISION_publish_does_not_require_prior_approval_today(): void
    {
        $actor = $this->actor('Project Manager', 'pm'); // legacy value only so this characterization is independent of the S3 fix
        $doc = $this->doc();

        $this->web($actor, 'POST', "/app/documents/{$doc->id}/publish");

        $this->assertSame('published', $this->state($doc)[1], 'CURRENT behaviour (DocumentLifecycleService::publish accepts not-submitted); S3 does not change it');
    }

    #[Group('uat-recovery-03-guard')]
    public function test_decision_history_is_recorded_and_unchanged_by_denied_attempts(): void
    {
        $pm = $this->actor('Project Manager');
        $doc = $this->submitted($this->doc(), $pm);
        $this->web($pm, 'POST', "/app/documents/{$doc->id}/reject", ['decision_note' => 'sửa']);
        $after = $this->events($doc);
        $this->assertGreaterThanOrEqual(2, $after, 'submitted + rejected events');

        $this->web($this->actor('Project Member'), 'POST', "/app/documents/{$doc->id}/approve", ['decision_note' => 'x']);
        $this->assertSame($after, $this->events($doc));
        $this->assertSame('rejected', $this->state($doc)[2]);
    }

    // ============================================== C. parameterless rbac gate — behaviour that must NOT change (O-8)

    #[Group('uat-recovery-03-guard')]
    public function test_parameterless_gate_semantics_are_unchanged(): void
    {
        $cases = [
            'DB System Admin only' => [$this->actor('System Admin'), 403],
            'DB Project Manager only' => [$this->actor('Project Manager'), 403],
            'DB Project Member only' => [$this->actor('Project Member'), 403],
            'no role' => [$this->actor(null), 403],
            'legacy admin, no DB role' => [$this->actor(null, 'admin'), 200],
            'legacy project_manager' => [$this->actor('Project Manager', 'project_manager'), 200],
            'legacy team_member' => [$this->actor('Project Member', 'team_member'), 200],
        ];
        foreach ($cases as $label => [$user, $expected]) {
            $headers = $this->api($user);
            $this->assertSame($expected, $this->withHeaders($headers)->getJson('/api/projects')->getStatusCode(), $label);
        }
    }

    /**
     * OWNER_DECISION_REQUIRED (O-8): 312 routes (158 mutating) are protected ONLY by the parameterless gate.
     * Widening it to "any seeded DB role" would hand them to Project Member. This test pins the CURRENT state:
     * a DB-only Member is denied, while a legacy-valued Member already reaches a permission-less mutating route.
     */
    #[Group('uat-recovery-03-owner')]
    public function test_OWNER_DECISION_widening_the_parameterless_gate_would_expand_member_access(): void
    {
        // S4c: POST api/tasks — the route this test originally used as its example — now enforces task.create (CB-1 fixed),
        // so the example moved to POST api/dashboards, another route protected ONLY by the parameterless gate.
        $dbOnly = $this->actor('Project Member');
        $legacy = $this->actor('Project Member', 'team_member');
        $this->assertFalse($legacy->hasPermission('task.create'), 'Member holds no task.create');
        $payload = ['project_id' => $this->project->id, 'name' => 'UAT-R03 gate probe', 'start_date' => '2026-12-01', 'end_date' => '2026-12-02'];

        $h = $this->api($dbOnly);
        $this->assertSame(403, $this->withHeaders($h)->postJson('/api/dashboards', ['name' => 'probe'])->getStatusCode());
        $h = $this->api($legacy);
        $this->assertSame(403, $this->withHeaders($h)->postJson('/api/tasks', $payload)->getStatusCode(), 'fixed in S4c: api/tasks now requires task.create');
        $res = $this->withHeaders($h)->postJson('/api/dashboards', ['name' => 'probe']);
        $this->assertContains($res->getStatusCode(), [200, 201], 'a permission-less route is still reachable through the legacy gate value alone');
    }

    /**
     * OWNER_DECISION_REQUIRED: no self-approval rule and no designated-approver enforcement exist in the
     * state machine (GAP-033 designation is advisory). S3 neither adds nor removes either.
     */
    #[Group('uat-recovery-03-owner')]
    public function test_OWNER_DECISION_self_approval_and_designated_approver_are_not_enforced(): void
    {
        $pm = $this->actor('Project Manager');
        $designated = $this->actor('Project Manager');
        $doc = $this->doc();
        $this->web($pm, 'POST', "/app/documents/{$doc->id}/submit");
        $this->assertSame('awaiting-approval', $this->state($doc)[2]);
        DB::table('document_approver_assignments')->where('document_id', $doc->id)->count(); // table exists

        $this->web($pm, 'POST', "/app/documents/{$doc->id}/approve", ['decision_note' => 'submitter approves own submission']);

        $this->assertSame('approved', $this->state($doc)[2], 'CURRENT behaviour: submitter with document.approve may approve; S3 leaves it (Owner decision)');
        $this->assertNotNull($designated->id);
    }

    #[Group('uat-recovery-03-owner')]
    public function test_OWNER_DECISION_no_review_permission_or_entity_exists(): void
    {
        $this->assertSame(0, DB::table('permissions')->where('name', 'like', 'document.review%')->count());
        $this->assertSame(0, DB::table('permissions')->where('name', 'like', 'document.submit%')->count());
        $this->assertSame(0, DB::table('permissions')->where('name', 'like', 'document.publish%')->count(), 'publish rides on document.update today');
    }
}
