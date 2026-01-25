<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ChangeRequest;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Role;
use App\Models\Submittal;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmokeZenaShowAndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private Task $task;
    private Rfi $rfi;
    private Submittal $submittal;
    private ChangeRequest $changeRequest;
    private Notification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->attachSmokeRole();
        Sanctum::actingAs($this->user);

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id,
            'created_by' => $this->user->id,
            'end_date' => now()->addDays(30),
        ]);

        DB::table('project_team_members')->insert([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'role' => 'member',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'assignee_id' => $this->user->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $this->rfi = Rfi::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'open',
            'title' => 'Smoke RFI',
            'description' => 'Smoke validation RFI',
            'priority' => 'medium',
        ]);

        $this->submittal = Submittal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'submitted_by' => $this->user->id,
            'status' => 'draft',
            'submittal_type' => 'drawing',
            'title' => 'Smoke Submittal',
        ]);

        $this->changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'requested_by' => $this->user->id,
            'created_by' => $this->user->id,
            'impact_level' => 'medium',
            'priority' => 'medium',
            'change_type' => 'scope',
            'status' => 'draft',
            'title' => 'Smoke Change Request',
            'description' => 'Smoke change request description',
            'impact_analysis' => ['scope' => 'Smoke'],
        ]);

        $this->notification = Notification::factory()
            ->forUser($this->user)
            ->forProject($this->project)
            ->unread()
            ->create([
                'tenant_id' => $this->tenant->id,
                'type' => 'task_assigned',
                'title' => 'Smoke Notification',
                'body' => 'Smoke notification body',
                'priority' => 'normal',
            ]);
    }

    public function test_show_endpoints_return_valid_envelopes(): void
    {
        $this->assertResponseContainsId(
            $this->getJson("/api/zena/projects/{$this->project->id}"),
            'data.project.id',
            $this->project->id
        );

        $this->assertResponseContainsId(
            $this->getJson("/api/zena/tasks/{$this->task->id}"),
            'data.id',
            $this->task->id
        );

        $this->assertResponseContainsId(
            $this->getJson("/api/zena/rfis/{$this->rfi->id}"),
            'data.id',
            $this->rfi->id
        );

        $this->assertResponseContainsId(
            $this->getJson("/api/zena/submittals/{$this->submittal->id}"),
            'data.id',
            $this->submittal->id
        );

        $this->assertResponseContainsId(
            $this->getJson("/api/zena/change-requests/{$this->changeRequest->id}"),
            'data.id',
            $this->changeRequest->id
        );

        $this->assertResponseContainsId(
            $this->getJson("/api/zena/notifications/{$this->notification->id}"),
            'data.id',
            $this->notification->id
        );
    }

    public function test_workflow_endpoints_return_valid_envelopes(): void
    {
        $this->assertEnvelopePayload(
            $this->patchJson("/api/zena/tasks/{$this->task->id}/status", ['status' => 'in_progress'])
        );

        $this->assertEnvelopePayload(
            $this->postJson("/api/zena/rfis/{$this->rfi->id}/respond", [
                'response' => 'Smoke response',
                'status' => 'answered',
            ])
        );

        $this->assertEnvelopePayload(
            $this->postJson("/api/zena/rfis/{$this->rfi->id}/close")
        );

        $this->assertEnvelopePayload(
            $this->postJson("/api/zena/submittals/{$this->submittal->id}/submit")
        );

        $this->assertEnvelopePayload(
            $this->postJson("/api/zena/submittals/{$this->submittal->id}/review", [
                'review_status' => 'approved',
                'review_comments' => 'Smoke review',
            ])
        );

        $this->assertEnvelopePayload(
            $this->postJson("/api/zena/submittals/{$this->submittal->id}/approve", [
                'approval_comments' => 'Smoke approval',
            ])
        );

        $this->assertEnvelopePayload(
            $this->postJson("/api/zena/change-requests/{$this->changeRequest->id}/submit")
        );

        $this->assertEnvelopePayload(
            $this->postJson("/api/zena/change-requests/{$this->changeRequest->id}/approve", [
                'approval_comments' => 'Smoke approval',
                'approved_cost' => 1500.0,
                'approved_schedule_days' => 2,
            ])
        );

        $this->assertEnvelopePayload(
            $this->putJson("/api/zena/notifications/{$this->notification->id}/read")
        );

        $this->assertEnvelopePayload(
            $this->putJson('/api/zena/notifications/read-all'),
            false
        );
    }

    private function assertEnvelopePayload(TestResponse $response, bool $expectData = true): array
    {
        $response->assertStatus(200);
        $payload = $response->json();

        $status = $payload['status'] ?? null;
        $successFlag = $payload['success'] ?? null;

        $this->assertTrue(
            $status === 'success' || $successFlag === true,
            'Envelope did not declare success via status or success flag'
        );

        if ($expectData) {
            $this->assertArrayHasKey('data', $payload, 'Envelope did not contain data payload');
        }

        return $payload;
    }

    private function assertResponseContainsId(TestResponse $response, string $path, mixed $expectedId): void
    {
        $payload = $this->assertEnvelopePayload($response, true);

        $actualId = Arr::get($payload, $path);

        $this->assertSame(
            (string) $expectedId,
            (string) $actualId,
            "Envelope data at {$path} did not match expected resource id"
        );
    }

    private function attachSmokeRole(): void
    {
        $permissionCodes = [
            'project.view',
            'task.view',
            'task.update',
            'rfi.view',
            'rfi.assign',
            'rfi.answer',
            'submittal.view',
            'submittal.review',
            'submittal.approve',
            'change-request.view',
            'change-request.submit',
            'change-request.approve',
            'change-request.reject',
            'notification.read',
            'notification.manage_rules',
        ];

        $permissionIds = [];

        foreach ($permissionCodes as $code) {
            $parts = explode('.', $code, 2);
            $permission = Permission::firstOrCreate(
                ['code' => $code],
                [
                    'module' => $parts[0],
                    'action' => $parts[1] ?? 'view',
                    'description' => 'Smoke harness permission for ' . $code,
                ]
            );

            $permissionIds[] = $permission->id;
        }

        $role = Role::firstOrCreate(
            ['name' => 'Smoke Admin', 'scope' => Role::SCOPE_SYSTEM],
            [
                'description' => 'Smoke harness admin role',
                'is_active' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching($permissionIds);
        $this->user->roles()->syncWithoutDetaching($role->id);
    }
}
