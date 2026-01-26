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
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Smoke test suite that exercises the key /api/zena endpoints over a SQLite
 * harness to ensure the envelope contracts stay intact and no fatal errors
 * occur when the happy path data is present.
 */
class SmokeZenaEndpointsTest extends TestCase
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
            ->create([
                'tenant_id' => $this->tenant->id,
                'type' => 'task_assigned',
                'title' => 'Smoke notification',
                'body' => 'Smoke notification body',
                'priority' => 'normal',
            ]);
    }

    public function test_zena_smoke_endpoints_have_valid_envelopes_and_entities(): void
    {
        // Listing endpoints
        $projectList = $this->assertStatusEnvelope($this->getJson('/api/zena/projects'));
        $this->assertIdMatches($this->project->id, Arr::get($projectList, 'data.0.id'));

        $taskList = $this->assertSuccessEnvelope($this->getJson('/api/zena/tasks'));
        $this->assertIdMatches($this->task->id, Arr::get($taskList, 'data.data.0.id'));

        $rfiList = $this->assertStatusEnvelope($this->getJson('/api/zena/rfis'));
        $this->assertIdMatches($this->rfi->id, Arr::get($rfiList, 'data.data.0.id'));

        $submittalList = $this->assertStatusEnvelope($this->getJson('/api/zena/submittals'));
        $this->assertIdMatches($this->submittal->id, Arr::get($submittalList, 'data.data.0.id'));

        $changeRequestList = $this->assertStatusEnvelope($this->getJson('/api/zena/change-requests'));
        $this->assertIdMatches($this->changeRequest->id, Arr::get($changeRequestList, 'data.data.0.id'));

        $notificationList = $this->assertStatusEnvelope($this->getJson('/api/zena/notifications'));
        $this->assertIdMatches($this->notification->id, Arr::get($notificationList, 'data.data.0.id'));

    }

    private function assertStatusEnvelope(TestResponse $response): array
    {
        $response->assertStatus(200);
        $payload = $response->json();

        $status = $payload['status'] ?? null;
        $successFlag = $payload['success'] ?? null;

        $this->assertTrue(
            $status === 'success' || $successFlag === true,
            'Envelope did not declare success via status or success flag'
        );
        $this->assertArrayHasKey('data', $payload, 'Envelope did not contain data payload');

        return $payload;
    }

    private function assertSuccessEnvelope(TestResponse $response): array
    {
        $response->assertStatus(200);
        $payload = $response->json();

        $this->assertTrue($payload['success'] ?? false, 'Endpoint did not respond with success flag');
        $this->assertArrayHasKey('data', $payload, 'Envelope did not contain data payload');

        return $payload;
    }

    private function assertIdMatches(mixed $expected, mixed $actual): void
    {
        $this->assertSame((string) $expected, (string) $actual, 'ID value in response did not match the created resource');
    }

    private function attachSmokeRole(): void
    {
        $permissionCodes = [
            'project.view',
            'task.view',
            'rfi.view',
            'submittal.view',
            'change-request.view',
            'notification.view',
        ];

        $permissionIds = [];
        foreach ($permissionCodes as $code) {
            $parts = explode('.', $code, 2);
            $module = $parts[0];
            $action = $parts[1] ?? 'view';

            $permission = Permission::firstOrCreate(
                ['code' => $code],
                [
                    'module' => $module,
                    'action' => $action,
                    'description' => 'Smoke test permission for ' . $code,
                ]
            );

            $permissionIds[] = $permission->id;
        }

        $role = Role::firstOrCreate(
            ['name' => 'Admin', 'scope' => Role::SCOPE_SYSTEM],
            ['description' => 'Smoke test admin role']
        );

        $role->permissions()->syncWithoutDetaching($permissionIds);
        $this->user->roles()->syncWithoutDetaching($role->id);
    }
}
