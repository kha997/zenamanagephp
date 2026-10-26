<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\Project;
use App\Models\Role;
use App\Models\Submittal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Tests\Traits\RouteNameTrait;

/**
 * GAP-013: first submit notifies the project PM; approve/reject notify the submitter;
 * nobody is notified about their own action.
 */
class SubmittalNotificationTest extends TestCase
{
    use RefreshDatabase;
    use RouteNameTrait;

    protected User $user;

    protected User $pm;

    protected Project $project;

    protected string $token;

    protected array $zenaAuthHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->pm = User::factory()->create(['tenant_id' => $this->user->tenant_id]);
        $this->project = Project::factory()->create([
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'pm_id' => $this->pm->id,
        ]);
        $this->syncZenaProjectRecord($this->project);

        $this->assignSuperAdminRole($this->user);
        $this->assignSuperAdminRole($this->pm);
        $this->token = $this->loginZenaUser($this->user);
        $this->zenaAuthHeaders = $this->buildZenaAuthHeaders();
    }

    private function createDraft(): string
    {
        $create = $this->withZenaAuth()->postJson($this->zena('submittals.store'), [
            'project_id' => $this->project->id,
            'title' => 'Structural steel shop drawing',
            'description' => 'Level 3 framing package.',
            'submittal_type' => 'shop_drawing',
        ]);
        $create->assertStatus(201);

        return (string) $create->json('data.id');
    }

    private function actAsPm(): void
    {
        $this->token = $this->loginZenaUser($this->pm);
        $this->zenaAuthHeaders['Authorization'] = 'Bearer ' . $this->token;
    }

    public function test_first_submit_notifies_project_pm(): void
    {
        $id = $this->createDraft();

        $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $id]))->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => (string) $this->user->tenant_id,
            'project_id' => (string) $this->project->id,
            'user_id' => (string) $this->pm->id,
            'type' => 'submittal_submitted',
        ]);
        $this->assertSame(1, Notification::query()->count());
    }

    public function test_approve_notifies_submitter(): void
    {
        $id = $this->createDraft();
        $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $id]))->assertStatus(200);

        $this->actAsPm();
        $this->withZenaAuth()->postJson($this->zena('submittals.approve', ['id' => $id]))->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => (string) $this->user->id,
            'type' => 'submittal_approved',
        ]);
    }

    public function test_reject_notifies_submitter(): void
    {
        $id = $this->createDraft();
        $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $id]))->assertStatus(200);

        $this->actAsPm();
        $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $id]), [
            'rejection_reason' => 'Missing bolt schedule',
        ])->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => (string) $this->user->id,
            'type' => 'submittal_rejected',
        ]);
    }

    public function test_no_self_notification(): void
    {
        $this->project->forceFill(['pm_id' => $this->user->id])->save();
        $id = $this->createDraft();

        $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $id]))->assertStatus(200);
        $this->withZenaAuth()->postJson($this->zena('submittals.approve', ['id' => $id]))->assertStatus(200);

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_pm_lookup_failure_is_logged_and_submit_still_succeeds(): void
    {
        $id = $this->createDraft();

        Log::spy();
        DB::listen(function ($query): void {
            if (str_contains($query->sql, 'pm_id') && str_contains($query->sql, 'projects')) {
                throw new \RuntimeException('simulated PM lookup failure');
            }
        });

        $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $id]))->assertStatus(200);

        $this->assertSame(Submittal::STATUS_SUBMITTED, Submittal::query()->findOrFail($id)->status);
        $this->assertSame(0, Notification::query()->count());
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context) => $message === 'submittal.notification_failed'
                && ($context['submittal_id'] ?? null) === $id);
    }

    public function test_refused_transition_sends_nothing(): void
    {
        $id = $this->createDraft();

        $this->actAsPm();
        $this->withZenaAuth()->postJson($this->zena('submittals.approve', ['id' => $id]))->assertStatus(400);

        $this->assertSame(0, Notification::query()->count());
        $this->assertSame(Submittal::STATUS_DRAFT, Submittal::query()->findOrFail($id)->status);
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
