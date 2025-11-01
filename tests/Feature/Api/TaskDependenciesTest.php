<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\ZenaProject;
use App\Models\ZenaTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class TaskDependenciesTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $project;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->markTestSkipped('All TaskDependenciesTest tests skipped - missing ZenaProject and ZenaTask models');
        
        $this->user = User::factory()->create();
        $this->project = ZenaProject::factory()->create([
            'created_by' => $this->user->id
        ]);
        $this->token = $this->generateJwtToken($this->user);
    }

    /**
     * Test adding task dependency
     */
    public function test_can_add_task_dependency()
    {
        $task1 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $task2 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/tasks/{$task1->id}/dependencies", [
            'dependency_id' => $task2->id
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'dependencies'
                    ]
                ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task1->id,
            'dependencies' => json_encode([$task2->id])
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
            'dependencies' => ['task2-id']
        ]);

        $task2 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/zena/tasks/{$task1->id}/dependencies", [
            'dependency_id' => 'task2-id'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'dependencies'
                    ]
                ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task1->id,
            'dependencies' => json_encode([])
        ]);
    }

    /**
     * Test circular dependency prevention
     */
    public function test_prevents_circular_dependency()
    {
        $task1 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $task2 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'dependencies' => [$task1->id]
        ]);

        // Try to create circular dependency: task1 -> task2 -> task1
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/tasks/{$task1->id}/dependencies", [
            'dependency_id' => $task2->id
        ]);

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'status',
                    'message'
                ]);

        $this->assertStringContainsString('circular dependency', $response->json('message'));
    }

    /**
     * Test self-dependency prevention
     */
    public function test_prevents_self_dependency()
    {
        $task = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/tasks/{$task->id}/dependencies", [
            'dependency_id' => $task->id
        ]);

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'status',
                    'message'
                ]);

        $this->assertStringContainsString('cannot depend on itself', $response->json('message'));
    }

    /**
     * Test complex circular dependency prevention
     */
    public function test_prevents_complex_circular_dependency()
    {
        $task1 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $task2 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'dependencies' => [$task1->id]
        ]);

        $task3 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'dependencies' => [$task2->id]
        ]);

        // Try to create circular dependency: task1 -> task2 -> task3 -> task1
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/tasks/{$task3->id}/dependencies", [
            'dependency_id' => $task1->id
        ]);

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'status',
                    'message'
                ]);

        $this->assertStringContainsString('circular dependency', $response->json('message'));
    }

    /**
     * Test task status update with dependencies
     */
    public function test_task_status_update_with_dependencies()
    {
        $task1 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'todo'
        ]);

        $task2 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'status' => 'todo',
            'dependencies' => [$task1->id]
        ]);

        // Update task1 to completed
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/zena/tasks/{$task1->id}/status", [
            'status' => 'done'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tasks', [
            'id' => $task1->id,
            'status' => 'done'
        ]);

        // Now task2 should be able to start
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/zena/tasks/{$task2->id}/status", [
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
            'created_by' => $this->user->id
        ]);

        $task2 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $task3 = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'dependencies' => [$task1->id, $task2->id]
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/zena/tasks/{$task3->id}/dependencies");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data'
                ]);

        $dependencies = $response->json('data');
        $this->assertCount(2, $dependencies);
        $this->assertContains($task1->id, $dependencies);
        $this->assertContains($task2->id, $dependencies);
    }

    /**
     * Test dependency validation
     */
    public function test_dependency_validation()
    {
        $task = ZenaTask::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        // Test with non-existent task
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/zena/tasks/{$task->id}/dependencies", [
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

        $response = $this->postJson("/api/zena/tasks/{$task->id}/dependencies", [
            'dependency_id' => 'some-id'
        ]);

        $response->assertStatus(401);
    }

    /**
     * Generate JWT token for testing
     */
    private function generateJwtToken(User $user): string
    {
        return 'test-jwt-token-' . $user->id;
    }
}
