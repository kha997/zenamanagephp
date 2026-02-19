<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use App\Models\ZenaProject;
use App\Models\ZenaTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Traits\RouteNameTrait;

class TaskDependenciesTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTestTrait, RouteNameTrait;

    protected User $user;
    protected ZenaProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiActingAsTenantAdmin();
        $this->user = $this->apiFeatureUser;
        $this->project = ZenaProject::factory()->create([
            'created_by' => $this->user->id,
            'tenant_id' => $this->apiFeatureTenant->id,
        ]);
    }

    /**
     * Test adding task dependency
     */
    public function test_can_add_task_dependency()
    {
        $task1 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        $task2 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        $response = $this->apiPost($this->zena('tasks.add-dependency', ['id' => $task1->id]), [
            'dependency_id' => $task2->id
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'dependencies'
                    ]
                ])
                ->assertJsonCount(1, 'data.dependencies')
                ->assertJsonPath('data.dependencies.0.id', $task2->id);

        $this->assertDatabaseHas('task_dependencies', [
            'tenant_id' => $this->project->tenant_id,
            'task_id' => $task1->id,
            'dependency_id' => $task2->id
        ]);
    }

    /**
     * Test removing task dependency
     */
    public function test_can_remove_task_dependency()
    {
        $task1 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        $task2 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        $this->createTaskDependencyRecord($task1, $task2);

        $response = $this->withHeaders($this->resolveApiHeaders())
                ->deleteJson($this->zena('tasks.remove-dependency', ['id' => $task1->id, 'dependencyId' => $task2->id]), [
                    'dependency_id' => $task2->id
                ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'dependencies'
                    ]
                ])
                ->assertJsonPath('data.dependencies', []);

        $this->assertDatabaseMissing('task_dependencies', [
            'tenant_id' => $this->project->tenant_id,
            'task_id' => $task1->id,
            'dependency_id' => $task2->id
        ]);
    }

    /**
     * Test circular dependency prevention
     */
    public function test_prevents_circular_dependency()
    {
        $task1 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        $task2 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        $this->createTaskDependencyRecord($task2, $task1);

        // Try to create circular dependency: task1 -> task2 -> task1
        $response = $this->apiPost($this->zena('tasks.add-dependency', ['id' => $task1->id]), [
            'dependency_id' => $task2->id
        ]);

        $response->assertStatus(400);

        $this->assertStringContainsString('circular dependency', strtolower($response->getContent()));
    }

    /**
     * Test self-dependency prevention
     */
    public function test_prevents_self_dependency()
    {
        $task = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        $response = $this->apiPost($this->zena('tasks.add-dependency', ['id' => $task->id]), [
            'dependency_id' => $task->id
        ]);

        $response->assertStatus(400);

        $this->assertStringContainsString('cannot depend on itself', strtolower($response->getContent()));
    }

    /**
     * Test complex circular dependency prevention
     */
    public function test_prevents_complex_circular_dependency()
    {
        $task1 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        $task2 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        $task3 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        $this->createTaskDependencyRecord($task2, $task1);
        $this->createTaskDependencyRecord($task3, $task2);

        // Try to create circular dependency: task1 -> task2 -> task3 -> task1
        $response = $this->apiPost($this->zena('tasks.add-dependency', ['id' => $task3->id]), [
            'dependency_id' => $task1->id
        ]);

        $response->assertStatus(400);

        $this->assertStringContainsString('circular dependency', strtolower($response->getContent()));
    }

    /**
     * Test task status update with dependencies
     */
    public function test_task_status_update_with_dependencies()
    {
        $task1 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'todo',
            'tenant_id' => $this->project->tenant_id
        ]);

        $task2 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'todo',
            'tenant_id' => $this->project->tenant_id
        ]);

        $this->createTaskDependencyRecord($task2, $task1);

        // Update task1 to completed
        $response = $this->apiPatch($this->zena('tasks.update-status', ['id' => $task1->id]), [
            'status' => 'done'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tasks', [
            'id' => $task1->id,
            'status' => 'done'
        ]);

        // Now task2 should be able to start
        $response = $this->apiPatch($this->zena('tasks.update-status', ['id' => $task2->id]), [
            'status' => 'in_progress'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tasks', [
            'id' => $task2->id,
            'status' => 'in_progress'
        ]);
    }

    /**
     * Test getting task dependencies
     */
    public function test_can_get_task_dependencies()
    {
        $task1 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        $task2 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        $task3 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        $this->createTaskDependencyRecord($task3, $task1);
        $this->createTaskDependencyRecord($task3, $task2);

        $response = $this->apiGet($this->zena('tasks.dependencies', ['id' => $task3->id]));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data'
                ]);

        $dependencies = $response->json('data');
        $dependencyIds = array_column($dependencies, 'id');

        $this->assertCount(2, $dependencyIds);
        $this->assertContains($task1->id, $dependencyIds);
        $this->assertContains($task2->id, $dependencyIds);
    }

    /**
     * Test dependency validation
     */
    public function test_dependency_validation()
    {
        $task = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id
        ]);

        // Test with non-existent task
        $response = $this->apiPost($this->zena('tasks.add-dependency', ['id' => $task->id]), [
            'dependency_id' => 'non-existent-id'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['dependency_id']);
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access_returns_401()
    {
        $task = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->postJson($this->zena('tasks.add-dependency', ['id' => $task->id]), [
            'dependency_id' => 'some-id'
        ]);

        $response->assertStatus(401);
    }

    private function createTaskDependencyRecord(Task $task, Task $dependency): TaskDependency
    {
        return TaskDependency::create([
            'tenant_id' => $this->project->tenant_id,
            'task_id' => $task->id,
            'dependency_id' => $dependency->id,
        ]);
    }

}
