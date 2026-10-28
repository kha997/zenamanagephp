<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\CoreProject\Models\Project as CoreProjectProject;
use Src\WorkTemplate\Models\ProjectPhase;
use Src\WorkTemplate\Models\ProjectTask;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

/**
 * GAP-021 — task edits on the compatibility and work-template routes use the seeded
 * `task.update` permission, and `/api/v1/tasks*` has the canonical input guards.
 * Users here are deliberately not super admins (super admins bypass RBAC).
 */
class V1TaskUpdatePermissionTest extends TestCase
{
    use AuthenticationTestTrait;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_task_update_holder_can_put_and_patch_a_v1_task(): void
    {
        $user = $this->userWith(['task.view', 'task.update']);
        $headers = $this->headersFor($user);
        $task = $this->taskFor($user);

        $this->apiPut("/api/v1/tasks/{$task->id}", ['name' => 'Through PUT', 'status' => 'pending'], $headers) // ssot-allow-hardcode (unnamed v1 compatibility route)
            ->assertOk()
            ->assertJsonPath('data.task.name', 'Through PUT');
        $this->apiPatch("/api/v1/tasks/{$task->id}", ['name' => 'Through PATCH', 'status' => 'pending'], $headers) // ssot-allow-hardcode (unnamed v1 compatibility route)
            ->assertOk()
            ->assertJsonPath('data.task.name', 'Through PATCH');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'name' => 'Through PATCH']);
    }

    public function test_user_without_task_update_is_still_denied(): void
    {
        $user = $this->userWith(['task.view']);
        $task = $this->taskFor($user);

        $this->apiPut("/api/v1/tasks/{$task->id}", ['name' => 'Denied', 'status' => 'pending'], $this->headersFor($user)) // ssot-allow-hardcode (unnamed v1 compatibility route)
            ->assertForbidden();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id, 'name' => 'Denied']);
    }

    public function test_task_update_holder_can_update_work_template_task_progress(): void
    {
        $user = $this->userWith(['task.view', 'task.update']);
        $project = CoreProjectProject::factory()->create(['tenant_id' => $this->tenant->id]);
        $phase = ProjectPhase::factory()->create(['project_id' => $project->id]);
        $task = ProjectTask::factory()->create(['project_id' => $project->id, 'phase_id' => $phase->id, 'progress_percent' => 0]);

        $this->apiPut(
            "/api/v1/work-template/projects/{$project->id}/tasks/{$task->id}/progress", // ssot-allow-hardcode (unnamed v1 compatibility route)
            ['progress_percent' => 25],
            $this->headersFor($user)
        )
            ->assertOk();

        $this->assertSame(25.0, (float) $task->fresh()?->progress_percent);
    }

    public function test_v1_task_create_rejects_suspicious_input_like_the_canonical_api(): void
    {
        $user = $this->userWith(['task.view', 'task.create']);
        $project = $this->projectFor($user);

        $this->apiPost('/api/v1/tasks', [ // ssot-allow-hardcode (unnamed v1 compatibility route)
            'project_id' => $project->id,
            'name' => '<script>alert(1)</script>',
            'status' => 'pending',
        ], $this->headersFor($user))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'SUSPICIOUS_INPUT');

        $this->assertDatabaseMissing('tasks', ['project_id' => $project->id]);
    }

    /**
     * @param list<string> $permissions
     */
    private function userWith(array $permissions): User
    {
        return $this->createTenantUser($this->tenant, [], ['Admin'], $permissions);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        return array_merge(
            $this->apiHeadersForTenant((string) $this->tenant->id),
            ['Authorization' => 'Bearer ' . $this->apiLoginToken($user, $this->tenant)]
        );
    }

    private function projectFor(User $user): Project
    {
        return Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $user->id,
            'created_by' => $user->id,
            'status' => 'active',
        ]);
    }

    private function taskFor(User $user): Task
    {
        return Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->projectFor($user)->id,
            'created_by' => $user->id,
            'name' => 'Original',
            'status' => 'pending',
            'progress_percent' => 0,
        ]);
    }
}
