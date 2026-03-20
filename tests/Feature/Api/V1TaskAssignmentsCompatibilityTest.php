<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\TenantIsolationMiddleware;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use Tests\TestCase;

class V1TaskAssignmentsCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private User $otherUser;
    private Project $project;
    private Task $task;
    private string $assignmentId;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->project = Project::query()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => 'Compatibility Project',
            'status' => 'active',
        ]);
        $this->task = Task::query()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Compatibility Task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);
        $this->assignmentId = $this->insertAssignment($this->task->id, $this->user->id, 'assignee');
    }

    public function test_mounted_routes_stay_flat_with_current_owner_actions(): void
    {
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskAssignmentController@index',
            $this->routeByUriAndMethod('api/v1/task-assignments', 'GET')?->getActionName()
        );
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskAssignmentController@store',
            $this->routeByUriAndMethod('api/v1/task-assignments', 'POST')?->getActionName()
        );
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskAssignmentController@show',
            $this->routeByUriAndMethod('api/v1/task-assignments/{taskAssignment}', 'GET')?->getActionName()
        );
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskAssignmentController@update',
            $this->routeByUriAndMethod('api/v1/task-assignments/{taskAssignment}', 'PUT')?->getActionName()
        );
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskAssignmentController@update',
            $this->routeByUriAndMethod('api/v1/task-assignments/{taskAssignment}', 'PATCH')?->getActionName()
        );
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskAssignmentController@destroy',
            $this->routeByUriAndMethod('api/v1/task-assignments/{taskAssignment}', 'DELETE')?->getActionName()
        );
    }

    public function test_index_uses_flat_task_id_query_contract_and_keeps_jsend_envelope(): void
    {
        $otherTask = Task::query()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Other Task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $this->insertAssignment($otherTask->id, $this->otherUser->id, 'reviewer');

        $response = $this
            ->withHeaders($this->tenantHeaders())
            ->getJson("/api/v1/task-assignments?task_id={$this->task->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data' => [
                    'assignments',
                ],
            ]);

        $assignments = $response->json('data.assignments');

        $this->assertIsArray($assignments);
        $this->assertCount(1, $assignments);
        $this->assertSame((string) $this->task->id, $assignments[0]['task_id']);
    }

    public function test_store_requires_task_id_in_body_without_nested_route_context(): void
    {
        $response = $this
            ->withHeaders($this->tenantHeaders())
            ->postJson('/api/v1/task-assignments', [
                'user_id' => $this->otherUser->id,
                'split_percent' => 50,
                'role' => 'reviewer',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'fail')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'validation_errors' => ['task_id'],
                ],
            ]);
    }

    public function test_show_only_requires_task_assignment_route_parameter(): void
    {
        $response = $this
            ->withHeaders($this->tenantHeaders())
            ->getJson("/api/v1/task-assignments/{$this->assignmentId}");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.assignment.task_id', (string) $this->task->id);
    }

    public function test_put_only_requires_task_assignment_route_parameter(): void
    {
        $response = $this
            ->withHeaders($this->tenantHeaders())
            ->putJson("/api/v1/task-assignments/{$this->assignmentId}", [
                'role' => 'reviewer',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSame('reviewer', DB::table('task_assignments')->where('id', $this->assignmentId)->value('role'));
    }

    public function test_patch_only_requires_task_assignment_route_parameter(): void
    {
        $response = $this
            ->withHeaders($this->tenantHeaders())
            ->patchJson("/api/v1/task-assignments/{$this->assignmentId}", [
                'role' => 'watcher',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSame('watcher', DB::table('task_assignments')->where('id', $this->assignmentId)->value('role'));
    }

    public function test_delete_only_requires_task_assignment_route_parameter(): void
    {
        $response = $this
            ->withHeaders($this->tenantHeaders())
            ->deleteJson("/api/v1/task-assignments/{$this->assignmentId}");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.message', 'Task assignment đã được xóa thành công.');

        $this->assertDatabaseMissing('task_assignments', ['id' => $this->assignmentId]);
    }

    public function test_tenant_mismatch_is_rejected_before_assignment_enumeration(): void
    {
        $otherTenant = Tenant::factory()->create();
        $request = \Illuminate\Http\Request::create("/api/v1/task-assignments/{$this->assignmentId}", 'GET');
        $request->headers->set('X-Tenant-ID', (string) $otherTenant->id);
        Auth::shouldUse('api');
        Auth::setUser($this->user);

        $response = app(TenantIsolationMiddleware::class)->handle(
            $request,
            static fn () => response()->json(['status' => 'success'])
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('error', data_get($response->getData(true), 'status'));
        $this->assertSame('TENANT_INVALID', data_get($response->getData(true), 'error.code'));
    }

    private function insertAssignment(string $taskId, string $userId, string $role): string
    {
        $id = (string) Str::ulid();

        DB::table('task_assignments')->insert([
            'id' => $id,
            'tenant_id' => $this->tenant->id,
            'task_id' => $taskId,
            'user_id' => $userId,
            'role' => $role,
            'status' => 'assigned',
            'assignment_type' => 'user',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function tenantHeaders(array $override = []): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenant->id,
        ], $override);
    }

    private function routeByUriAndMethod(string $uri, string $method): ?RoutingRoute
    {
        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                return $route;
            }
        }

        return null;
    }
}
