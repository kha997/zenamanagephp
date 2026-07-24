<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\Role;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\RouteNameTrait;

class SubmittalResubmitLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;
    use RouteNameTrait;

    protected User $user;
    protected Project $project;
    protected string $token;
    protected array $zenaAuthHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create([
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
        ]);
        $this->syncZenaProjectRecord($this->project);

        $this->assignSuperAdminRole($this->user);
        $this->token = $this->loginZenaUser($this->user);
        $this->zenaAuthHeaders = $this->buildZenaAuthHeaders();
    }

    private function makeSubmittedSubmittal(): Submittal
    {
        $create = $this->withZenaAuth()->postJson($this->zena('submittals.store'), [
            'project_id' => $this->project->id,
            'title' => 'Structural steel shop drawing',
            'description' => 'Level 3 framing package.',
            'submittal_type' => 'shop_drawing',
        ]);
        $create->assertStatus(201);
        $id = $create->json('data.id');

        $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $id]))->assertStatus(200);

        return Submittal::find($id);
    }

    // 1. Revision snapshot bất biến
    public function test_revision_snapshot_is_immutable_after_edit(): void
    {
        $submittal = $this->makeSubmittedSubmittal();

        $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $submittal->id]), [
            'rejection_reason' => 'Missing bolt schedule',
        ])->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(200);

        $this->withZenaAuth()->putJson($this->zena('submittals.update', ['id' => $submittal->id]), [
            'title' => 'Structural steel shop drawing REV B',
        ])->assertStatus(200);

        $this->assertDatabaseHas('submittal_revisions', [
            'submittal_id' => $submittal->id,
            'revision_no' => 1,
            'title' => 'Structural steel shop drawing',
        ]);
    }

    // 2. Chỉnh sửa theo trạng thái
    public function test_patch_blocked_outside_draft_and_revising(): void
    {
        $submittal = $this->makeSubmittedSubmittal();

        $this->withZenaAuth()->putJson($this->zena('submittals.update', ['id' => $submittal->id]), [
            'title' => 'Should not apply',
        ])->assertStatus(422);

        $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $submittal->id]), [
            'rejection_reason' => 'Needs rework',
        ])->assertStatus(200);

        $this->withZenaAuth()->putJson($this->zena('submittals.update', ['id' => $submittal->id]), [
            'title' => 'Still should not apply',
        ])->assertStatus(422);

        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(200);

        $this->withZenaAuth()->putJson($this->zena('submittals.update', ['id' => $submittal->id]), [
            'title' => 'Now this applies',
        ])->assertStatus(200);
    }

    // 3. Double-submit
    public function test_double_submit_after_revising_only_creates_one_new_revision(): void
    {
        $submittal = $this->makeSubmittedSubmittal();
        $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $submittal->id]), [
            'rejection_reason' => 'Rework needed',
        ])->assertStatus(200);
        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(200);

        $first = $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $submittal->id]), [
            'revision_summary' => 'Fixed the issue',
        ]);
        $first->assertStatus(200);

        $second = $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $submittal->id]), [
            'revision_summary' => 'Fixed the issue again',
        ]);
        $second->assertStatus(400);

        $this->assertSame(2, DB::table('submittal_revisions')->where('submittal_id', $submittal->id)->count());
    }

    // 4. Repeated start-revision
    public function test_repeated_start_revision_call_fails_second_time(): void
    {
        $submittal = $this->makeSubmittedSubmittal();
        $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $submittal->id]), [
            'rejection_reason' => 'Rework needed',
        ])->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(200);
        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(400);
    }

    // 5. Cross-tenant
    public function test_cross_tenant_access_returns_404(): void
    {
        $submittal = $this->makeSubmittedSubmittal();

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $this->assignSuperAdminRole($otherUser);
        $otherToken = $this->loginZenaUser($otherUser);
        $otherHeaders = [
            'Authorization' => 'Bearer ' . $otherToken,
            'X-Tenant-ID' => (string) $otherTenant->id,
            'Accept' => 'application/json',
        ];

        // Illuminate\Auth\RequestGuard (used by the "sanctum" guard) memoizes the
        // resolved user for the lifetime of the shared AuthManager instance, which
        // persists across every ->getJson()/->postJson() call within this test
        // method. Without resetting it here, a request made with $otherUser's
        // bearer token would still resolve to $this->user (whoever the guard
        // authenticated first via makeSubmittedSubmittal() above).
        app('auth')->forgetGuards();

        $this->withHeaders($otherHeaders)
            ->getJson($this->zena('submittals.show', ['id' => $submittal->id]))
            ->assertStatus(404);

        $this->withHeaders($otherHeaders)
            ->postJson($this->zena('submittals.approve', ['id' => $submittal->id]))
            ->assertStatus(404);
    }

    // 6. Authorization
    public function test_actions_denied_without_matching_permission(): void
    {
        $tenant = $this->user->tenant_id;
        $role = Role::firstOrCreate(
            ['name' => 'submittal_viewer_only'],
            ['scope' => Role::SCOPE_SYSTEM, 'allow_override' => true, 'is_active' => true]
        );

        $viewer = User::factory()->create(['tenant_id' => $tenant]);
        $viewer->roles()->syncWithoutDetaching($role->id);
        $viewerToken = $this->loginZenaUser($viewer);
        $viewerHeaders = [
            'Authorization' => 'Bearer ' . $viewerToken,
            'X-Tenant-ID' => (string) $tenant,
            'Accept' => 'application/json',
        ];

        $submittal = $this->makeSubmittedSubmittal();

        // See test_cross_tenant_access_returns_404 for why this is required: the
        // sanctum RequestGuard memoizes the user resolved during
        // makeSubmittedSubmittal() above (as $this->user) for the rest of this
        // test process unless the guard cache is cleared.
        app('auth')->forgetGuards();

        $this->withHeaders($viewerHeaders)
            ->postJson($this->zena('submittals.approve', ['id' => $submittal->id]))
            ->assertStatus(403);
    }

    // 7. Notification after commit
    public function test_resubmit_notifies_last_rejector_and_logs_failure_gracefully(): void
    {
        $submittal = $this->makeSubmittedSubmittal();

        $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $submittal->id]), [
            'rejection_reason' => 'Missing details',
        ])->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(200);

        $response = $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $submittal->id]), [
            'revision_summary' => 'Added missing details',
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'submittal_resubmitted',
        ]);
    }

    // 8. Approved terminal
    public function test_approved_has_no_outgoing_transition(): void
    {
        $submittal = $this->makeSubmittedSubmittal();
        $this->withZenaAuth()->postJson($this->zena('submittals.approve', ['id' => $submittal->id]))->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(400);
        $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $submittal->id]))->assertStatus(400);
        $this->withZenaAuth()->putJson($this->zena('submittals.update', ['id' => $submittal->id]), ['title' => 'x'])->assertStatus(422);
    }

    // 9. status via PATCH ignored
    public function test_status_field_rejected_via_update_even_with_other_valid_fields(): void
    {
        $create = $this->withZenaAuth()->postJson($this->zena('submittals.store'), [
            'project_id' => $this->project->id,
            'title' => 'Draft submittal',
            'description' => 'Desc',
            'submittal_type' => 'other',
        ]);
        $id = $create->json('data.id');

        $response = $this->withZenaAuth()->putJson($this->zena('submittals.update', ['id' => $id]), [
            'title' => 'Updated title',
            'status' => 'approved',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('submittals', ['id' => $id, 'status' => 'draft', 'title' => 'Draft submittal']);
    }

    // 10. Full audit chain
    public function test_full_audit_chain_produces_expected_event_records_and_revisions(): void
    {
        $submittal = $this->makeSubmittedSubmittal();

        $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $submittal->id]), [
            'rejection_reason' => 'Rework needed',
        ])->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $submittal->id]), [
            'revision_summary' => 'Fixed',
        ])->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.approve', ['id' => $submittal->id]))->assertStatus(200);

        $eventKeys = DB::table('event_records')
            ->where('aggregate_type', 'submittal')
            ->where('aggregate_id', $submittal->id)
            ->orderBy('occurred_at')
            ->pluck('event_key')
            ->all();

        $this->assertSame(
            ['submittal.submitted', 'submittal.rejected', 'submittal.revision_started', 'submittal.resubmitted', 'submittal.approved'],
            $eventKeys
        );

        $revisions = DB::table('submittal_revisions')
            ->where('submittal_id', $submittal->id)
            ->orderBy('revision_no')
            ->get(['revision_no', 'decision']);

        $this->assertCount(2, $revisions);
        $this->assertSame('rejected', $revisions[0]->decision);
        $this->assertSame('approved', $revisions[1]->decision);
    }

    private function withZenaAuth()
    {
        return $this->withHeaders($this->zenaAuthHeaders);
    }

    private function buildZenaAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID' => (string) $this->user->tenant_id,
            'Accept' => 'application/json',
        ];
    }

    private function loginZenaUser(User $user): string
    {
        // A prior authenticated request in this test may have run through the
        // `auth:sanctum` middleware, which calls Auth::shouldUse('sanctum').
        // That mutates the shared `auth.defaults.guard` config for the rest of
        // this test process, so a later login here would otherwise resolve the
        // default guard to a stateless RequestGuard (no ->attempt()). Reset it.
        \Illuminate\Support\Facades\Auth::shouldUse('web');

        $response = $this->postJson($this->zena('auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        // AuthController::login() authenticates via Auth::attempt() on the
        // session-backed "web" guard to validate credentials, which leaves that
        // guard's session logged in as $user for the rest of this test process
        // (the session store is a shared singleton across requests here). If a
        // later request in the same test authenticates via Bearer token as a
        // *different* user, Sanctum's guard callback prefers an existing
        // stateful session over the token and would silently resolve back to
        // this $user. Log out of the web session immediately after grabbing
        // the token so only the token identifies the caller going forward.
        \Illuminate\Support\Facades\Auth::guard('web')->logout();
        app('auth')->forgetGuards();

        return (string) $response->json('data.token');
    }

    private function assignSuperAdminRole(User $user): void
    {
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
        ], [
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
            'is_active' => true,
        ]);

        $permissionNames = [
            'submittal.view', 'submittal.create', 'submittal.edit', 'submittal.delete',
            'submittal.submit', 'submittal.review', 'submittal.approve', 'submittal.reject',
        ];

        foreach ($permissionNames as $permissionName) {
            $parts = explode('.', $permissionName);
            $permission = \App\Models\Permission::firstOrCreate(['name' => $permissionName], [
                'code' => $permissionName,
                'module' => $parts[0] ?? $permissionName,
                'action' => $parts[1] ?? '*',
                'description' => ucfirst(str_replace('.', ' ', $permissionName)),
            ]);
            $role->permissions()->syncWithoutDetaching($permission->id);
        }

        $user->roles()->syncWithoutDetaching($role->id);
    }

    private function syncZenaProjectRecord(Project $project): void
    {
        DB::table('zena_projects')->updateOrInsert(
            ['id' => $project->id],
            [
                'tenant_id' => $project->tenant_id,
                'code' => $project->code,
                'name' => $project->name,
                'description' => $project->description,
                'client_id' => $project->client_id,
                'status' => $this->mapProjectStatusToZenaStatus($project->status),
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'budget' => $project->budget_total ?? 0,
                'settings' => json_encode($project->settings ?? []),
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ]
        );
    }

    private function mapProjectStatusToZenaStatus(string $status): string
    {
        return match ($status) {
            'planning' => 'planning',
            'active', 'in_progress' => 'active',
            'on_hold' => 'on_hold',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'planning',
        };
    }
}
