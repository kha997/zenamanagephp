<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\TenantIsolationMiddleware;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Src\CoreProject\Models\Project;
use Tests\TestCase;

class V1DashboardTaskAssignmentsCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private User $otherUser;
    private Project $project;
    private Task $task;
    private Task $otherTask;
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
            'name' => 'Dashboard Compatibility Project',
            'status' => 'active',
        ]);
        $this->task = Task::query()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Dashboard Compatibility Task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);
        $this->otherTask = Task::query()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'name' => 'Secondary Dashboard Task',
            'status' => 'in_progress',
            'priority' => 'high',
        ]);
        $this->assignmentId = $this->insertAssignment($this->task->id, $this->user->id, 'assignee', 'assigned');

        $this->actingAs($this->user);
        app()->instance('current_tenant_id', (string) $this->tenant->id);
        app()->instance('tenant', $this->tenant);
    }

    public function test_mounted_dashboard_routes_keep_owner_family_and_expected_actions(): void
    {
        $this->assertSame(
            'App\\Http\\Controllers\\Api\\TaskAssignmentController@getTaskAssignments',
            $this->routeByUriAndMethod('api/v1/dashboard/tasks/{taskId}/assignments', 'GET')?->getActionName()
        );
        $this->assertSame(
            'App\\Http\\Controllers\\Api\\TaskAssignmentController@store',
            $this->routeByUriAndMethod('api/v1/dashboard/tasks/{taskId}/assignments', 'POST')?->getActionName()
        );
        $this->assertSame(
            'App\\Http\\Controllers\\Api\\TaskAssignmentController@update',
            $this->routeByUriAndMethod('api/v1/dashboard/assignments/{assignmentId}', 'PUT')?->getActionName()
        );
        $this->assertSame(
            'App\\Http\\Controllers\\Api\\TaskAssignmentController@destroy',
            $this->routeByUriAndMethod('api/v1/dashboard/assignments/{assignmentId}', 'DELETE')?->getActionName()
        );
        $this->assertSame(
            'App\\Http\\Controllers\\Api\\TaskAssignmentController@getUserAssignments',
            $this->routeByUriAndMethod('api/v1/dashboard/users/{userId}/assignments', 'GET')?->getActionName()
        );
        $this->assertSame(
            'App\\Http\\Controllers\\Api\\TaskAssignmentController@getUserStats',
            $this->routeByUriAndMethod('api/v1/dashboard/users/{userId}/assignments/stats', 'GET')?->getActionName()
        );
    }

    public function test_get_task_assignments_uses_route_task_context(): void
    {
        $this->insertAssignment($this->otherTask->id, $this->otherUser->id, 'reviewer', 'in_progress');

        $response = $this
            ->withHeaders($this->tenantHeaders())
            ->getJson("/api/v1/dashboard/tasks/{$this->task->id}/assignments?task_id={$this->otherTask->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $assignments = $response->json('data');

        $this->assertIsArray($assignments);
        $this->assertCount(1, $assignments);
        $this->assertSame((string) $this->task->id, $assignments[0]['task_id']);
    }

    public function test_store_uses_route_task_id_as_source_of_truth_and_keeps_current_envelope_family(): void
    {
        $response = $this
            ->withHeaders($this->tenantHeaders())
            ->postJson("/api/v1/dashboard/tasks/{$this->task->id}/assignments", [
                'task_id' => $this->otherTask->id,
                'user_id' => $this->otherUser->id,
                'role' => 'reviewer',
                'notes' => 'route task context wins',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.task_id', (string) $this->task->id);

        $createdAssignmentId = (string) $response->json('data.id');

        $this->assertDatabaseHas('task_assignments', [
            'id' => $createdAssignmentId,
            'task_id' => $this->task->id,
            'user_id' => $this->otherUser->id,
        ]);
        $this->assertDatabaseMissing('task_assignments', [
            'id' => $createdAssignmentId,
            'task_id' => $this->otherTask->id,
        ]);
    }

    public function test_put_route_uses_exact_assignment_id_param_name(): void
    {
        $response = $this
            ->withHeaders($this->tenantHeaders())
            ->putJson("/api/v1/dashboard/assignments/{$this->assignmentId}", [
                'role' => 'reviewer',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $this->assignmentId);

        $this->assertSame('reviewer', DB::table('task_assignments')->where('id', $this->assignmentId)->value('role'));
    }

    public function test_delete_route_uses_exact_assignment_id_param_name(): void
    {
        $response = $this
            ->withHeaders($this->tenantHeaders())
            ->deleteJson("/api/v1/dashboard/assignments/{$this->assignmentId}");

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('task_assignments', ['id' => $this->assignmentId]);
    }

    public function test_stats_returns_minimal_payload_for_user_assignment_family(): void
    {
        $this->insertAssignment($this->otherTask->id, $this->user->id, 'reviewer', 'completed');
        $this->insertAssignment($this->otherTask->id, $this->otherUser->id, 'assignee', 'assigned');

        $response = $this
            ->withHeaders($this->tenantHeaders())
            ->getJson("/api/v1/dashboard/users/{$this->user->id}/assignments/stats");

        $response->assertOk()
            ->assertExactJson([
                'success' => true,
                'status' => 'success',
                'data' => [
                    'total_assignments' => 2,
                ],
            ]);
    }

    public function test_stats_tenant_mismatch_is_rejected_before_enumeration(): void
    {
        $otherTenant = Tenant::factory()->create();
        $request = \Illuminate\Http\Request::create("/api/v1/dashboard/users/{$this->user->id}/assignments/stats", 'GET');
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

    private function insertAssignment(string $taskId, string $userId, string $role, string $status): string
    {
        $id = (string) Str::ulid();

        DB::table('task_assignments')->insert([
            'id' => $id,
            'tenant_id' => $this->tenant->id,
            'task_id' => $taskId,
            'user_id' => $userId,
            'role' => $role,
            'status' => $status,
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
