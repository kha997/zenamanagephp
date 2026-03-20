<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

class V1TasksCompatibilityCrudTest extends TestCase
{
    use AuthenticationTestTrait;
    use RefreshDatabase;

    protected User $user;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->apiActingAsTenantAdmin();
        $this->tenant = $this->apiFeatureTenant;
    }

    public function test_mounted_v1_tasks_crud_routes_keep_canonical_owner_actions(): void
    {
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskController@index',
            $this->routeByUriAndMethod('api/v1/tasks', 'GET')?->getActionName()
        );
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskController@store',
            $this->routeByUriAndMethod('api/v1/tasks', 'POST')?->getActionName()
        );
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskController@show',
            $this->routeByUriAndMethod('api/v1/tasks/{task}', 'GET')?->getActionName()
        );
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskController@update',
            $this->routeByUriAndMethod('api/v1/tasks/{task}', 'PUT')?->getActionName()
        );
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskController@update',
            $this->routeByUriAndMethod('api/v1/tasks/{task}', 'PATCH')?->getActionName()
        );
        $this->assertSame(
            'Src\\CoreProject\\Controllers\\TaskController@destroy',
            $this->routeByUriAndMethod('api/v1/tasks/{task}', 'DELETE')?->getActionName()
        );
    }

    public function test_index_keeps_jsend_list_envelope_with_tasks_and_pagination(): void
    {
        $project = $this->createProject();
        $firstTask = $this->createTask($project, ['name' => 'Compatibility Task A']);
        $secondTask = $this->createTask($project, ['name' => 'Compatibility Task B']);

        $response = $this->apiGet('/api/v1/tasks?per_page=50');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'data' => [
                    'tasks',
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);

        $taskIds = array_column($response->json('data.tasks'), 'id');

        $this->assertContains($firstTask->id, $taskIds);
        $this->assertContains($secondTask->id, $taskIds);
    }

    public function test_show_uses_task_route_param_as_source_of_truth(): void
    {
        $primaryProject = $this->createProject(['name' => 'Primary Project']);
        $secondaryProject = $this->createProject(['name' => 'Secondary Project']);
        $task = $this->createTask($primaryProject, ['name' => 'Route Param Show Task']);
        $this->createTask($secondaryProject, ['name' => 'Other Project Task']);

        $response = $this->apiGet("/api/v1/tasks/{$task->id}?project_id={$secondaryProject->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.task.id', $task->id)
            ->assertJsonPath('data.task.project_id', $primaryProject->id)
            ->assertJsonPath('data.task.name', 'Route Param Show Task');
    }

    public function test_put_uses_task_route_param_as_source_of_truth(): void
    {
        $primaryProject = $this->createProject(['name' => 'PUT Primary Project']);
        $secondaryProject = $this->createProject(['name' => 'PUT Secondary Project']);
        $task = $this->createTask($primaryProject, ['name' => 'Original PUT Name']);
        $otherTask = $this->createTask($secondaryProject, ['name' => 'Untouched PUT Name']);

        $response = $this->apiPut("/api/v1/tasks/{$task->id}?project_id={$secondaryProject->id}", [
            'name' => 'Updated Through PUT',
            'status' => 'pending',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.task.id', $task->id)
            ->assertJsonPath('data.task.name', 'Updated Through PUT');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'name' => 'Updated Through PUT',
            'project_id' => $primaryProject->id,
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $otherTask->id,
            'name' => 'Untouched PUT Name',
            'project_id' => $secondaryProject->id,
        ]);
    }

    public function test_patch_shares_same_contract_as_put(): void
    {
        $primaryProject = $this->createProject(['name' => 'PATCH Primary Project']);
        $secondaryProject = $this->createProject(['name' => 'PATCH Secondary Project']);
        $task = $this->createTask($primaryProject, ['name' => 'Original PATCH Name']);
        $otherTask = $this->createTask($secondaryProject, ['name' => 'Untouched PATCH Name']);

        $response = $this->apiPatch("/api/v1/tasks/{$task->id}?project_id={$secondaryProject->id}", [
            'name' => 'Updated Through PATCH',
            'status' => 'pending',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.task.id', $task->id)
            ->assertJsonPath('data.task.name', 'Updated Through PATCH');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'name' => 'Updated Through PATCH',
            'project_id' => $primaryProject->id,
        ]);
        $this->assertDatabaseHas('tasks', [
            'id' => $otherTask->id,
            'name' => 'Untouched PATCH Name',
            'project_id' => $secondaryProject->id,
        ]);
    }

    public function test_delete_uses_task_route_param_as_source_of_truth(): void
    {
        $primaryProject = $this->createProject(['name' => 'DELETE Primary Project']);
        $secondaryProject = $this->createProject(['name' => 'DELETE Secondary Project']);
        $task = $this->createTask($primaryProject, ['name' => 'Delete Me']);
        $otherTask = $this->createTask($secondaryProject, ['name' => 'Keep Me']);

        $response = $this->apiDelete("/api/v1/tasks/{$task->id}?project_id={$secondaryProject->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.message', 'Task đã được xóa thành công');

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('tasks', ['id' => $otherTask->id]);
    }

    public function test_show_respects_tenant_boundary_for_foreign_task(): void
    {
        $task = $this->createTask($this->createProject(), ['name' => 'Tenant A Task']);

        $foreignTenant = Tenant::factory()->create();
        $foreignUser = $this->createTenantUser($foreignTenant, [], null, ['task.view']);
        $foreignToken = $this->apiLoginToken($foreignUser, $foreignTenant);

        $response = $this->withHeaders($this->authHeadersForUser($foreignUser, $foreignToken))
            ->getJson("/api/v1/tasks/{$task->id}");

        $response->assertStatus(404);
    }

    private function createProject(array $attributes = []): Project
    {
        return Project::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id,
            'created_by' => $this->user->id,
            'status' => 'active',
        ], $attributes));
    }

    private function createTask(Project $project, array $attributes = []): Task
    {
        return Task::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'created_by' => $this->user->id,
            'assignee_id' => $this->user->id,
            'status' => 'pending',
            'progress_percent' => 0,
            'priority' => 'medium',
            'visibility' => 'internal',
            'dependencies_json' => [],
            'watchers' => [],
            'tags' => [],
            'title' => $attributes['name'] ?? 'Test Task',
        ], $attributes));
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
