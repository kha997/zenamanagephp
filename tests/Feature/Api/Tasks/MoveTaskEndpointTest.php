<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Feature tests for PATCH /api/tasks/{id}/move endpoint
 * 
 * Tests all scenarios for moving tasks between Kanban columns.
 * Uses seedTasksDomain() for reproducible test data.
 * 
 * @group feature
 * @group api
 * @group tasks
 */
class MoveTaskEndpointTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;
    
    protected array $seedData;
    private $user;
    private $tenant;
    private $project;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setDomainSeed(56789);
        $this->setDomainName('tasks');
        $this->setupDomainIsolation();
        
        $this->seedData = TestDataSeeder::seedTasksDomain($this->getDomainSeed());
        $this->tenant = $this->seedData['tenant'];
        $this->user = $this->seedData['users'][0];
        $this->project = $this->seedData['projects'][0];
        
        Sanctum::actingAs($this->user);
    }
    
    private function createTaskWithStatus(TaskStatus $status, array $attributes = []): Task
    {
        return Task::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'title' => 'Test Task',
            'status' => $status->value,
            'created_by' => $this->user->id,
            'version' => 1,
            'order' => 1.0,
        ], $attributes));
    }
    
    //region Happy Path
    
    /**
     * @test
     */
    public function test_can_move_task_from_backlog_to_in_progress(): void
    {
        $task = $this->createTaskWithStatus(TaskStatus::BACKLOG);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'to_status' => TaskStatus::IN_PROGRESS->value,
            'version' => $task->version,
        ]);
        
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', TaskStatus::IN_PROGRESS->value);
        
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'version' => 2, // Version should increment
        ]);
    }
    
    /**
     * @test
     */
    public function test_returns_422_for_invalid_transition(): void
    {
        $task = $this->createTaskWithStatus(TaskStatus::BACKLOG);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'to_status' => TaskStatus::DONE->value,
            'version' => $task->version,
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'invalid_transition');
        $response->assertJsonHasPath('error.details.allowed_transitions');
    }
    
    /**
     * @test
     */
    public function test_returns_422_when_dependencies_incomplete(): void
    {
        $dependency = $this->createTaskWithStatus(TaskStatus::BACKLOG);
        $task = $this->createTaskWithStatus(TaskStatus::BACKLOG, [
            'dependencies' => [$dependency->id],
        ]);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'to_status' => TaskStatus::IN_PROGRESS->value,
            'version' => $task->version,
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'dependencies_incomplete');
        $response->assertJsonHasPath('error.details.dependencies');
    }
    
    /**
     * @test
     */
    public function test_returns_422_when_project_archived(): void
    {
        $this->project->update(['status' => 'archived']);
        $task = $this->createTaskWithStatus(TaskStatus::BACKLOG);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'to_status' => TaskStatus::IN_PROGRESS->value,
            'version' => $task->version,
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'project_status_restricted');
    }
    
    /**
     * @test
     */
    public function test_returns_422_when_reason_missing_for_blocked(): void
    {
        $task = $this->createTaskWithStatus(TaskStatus::IN_PROGRESS);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'to_status' => TaskStatus::BLOCKED->value,
            'version' => $task->version,
            // No reason provided
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'reason_required');
    }
    
    /**
     * @test
     */
    public function test_returns_409_for_optimistic_lock_conflict(): void
    {
        $task = $this->createTaskWithStatus(TaskStatus::BACKLOG);
        
        // Simulate another process updating the task
        $task->update(['version' => 2]);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'to_status' => TaskStatus::IN_PROGRESS->value,
            'version' => 1, // Stale version
        ]);
        
        $response->assertStatus(409);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'CONFLICT');
    }
    
    /**
     * @test
     */
    public function test_calculates_position_correctly(): void
    {
        $task1 = $this->createTaskWithStatus(TaskStatus::IN_PROGRESS, ['order' => 1.0]);
        $task2 = $this->createTaskWithStatus(TaskStatus::IN_PROGRESS, ['order' => 2.0]);
        $task3 = $this->createTaskWithStatus(TaskStatus::IN_PROGRESS, ['order' => 3.0]);
        
        // Move task2 between task1 and task3 (should be at position 1.5)
        $response = $this->patchJson("/api/tasks/{$task2->id}/move", [
            'to_status' => TaskStatus::IN_PROGRESS->value,
            'before_id' => $task3->id,
            'after_id' => $task1->id,
            'version' => $task2->version,
        ]);
        
        $response->assertOk();
        $task2->refresh();
        // Position should be between task1 and task3
        $this->assertGreaterThan($task1->order, $task2->order);
        $this->assertLessThan($task3->order, $task2->order);
    }
    
    /**
     * @test
     */
    public function test_updates_progress_to_100_when_moved_to_done(): void
    {
        $task = $this->createTaskWithStatus(TaskStatus::IN_PROGRESS, [
            'progress_percent' => 50.0,
        ]);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'to_status' => TaskStatus::DONE->value,
            'version' => $task->version,
        ]);
        
        $response->assertOk();
        $task->refresh();
        $this->assertEquals(100.0, $task->progress_percent);
    }
    
    /**
     * @test
     */
    public function test_updates_progress_to_0_when_moved_to_backlog(): void
    {
        $task = $this->createTaskWithStatus(TaskStatus::IN_PROGRESS, [
            'progress_percent' => 50.0,
        ]);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'to_status' => TaskStatus::BACKLOG->value,
            'version' => $task->version,
        ]);
        
        $response->assertOk();
        $task->refresh();
        $this->assertEquals(0.0, $task->progress_percent);
    }
    
    /**
     * @test
     */
    public function test_requires_authentication(): void
    {
        $task = $this->createTaskWithStatus(TaskStatus::BACKLOG);
        
        // Clear authentication
        Sanctum::actingAs(null);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'to_status' => TaskStatus::IN_PROGRESS->value,
            'version' => $task->version,
        ]);
        
        $response->assertUnauthorized();
    }
    
    /**
     * @test
     */
    public function test_enforces_tenant_isolation(): void
    {
        // Create another tenant and user
        $otherTenant = \App\Models\Tenant::factory()->create();
        $otherUser = \App\Models\User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherProject = \App\Models\Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'owner_id' => $otherUser->id,
        ]);
        $otherTask = Task::create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'name' => 'Other Task',
            'title' => 'Other Task',
            'status' => TaskStatus::BACKLOG->value,
            'created_by' => $otherUser->id,
            'version' => 1,
            'order' => 1.0,
        ]);
        
        // Try to move task from different tenant
        $response = $this->patchJson("/api/tasks/{$otherTask->id}/move", [
            'to_status' => TaskStatus::IN_PROGRESS->value,
            'version' => $otherTask->version,
        ]);
        
        $response->assertStatus(403);
    }
    
    /**
     * @test
     */
    public function test_can_move_with_reason_for_blocked(): void
    {
        $task = $this->createTaskWithStatus(TaskStatus::IN_PROGRESS);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'to_status' => TaskStatus::BLOCKED->value,
            'reason' => 'Waiting for client feedback',
            'version' => $task->version,
        ]);
        
        $response->assertOk();
        $response->assertJsonPath('data.status', TaskStatus::BLOCKED->value);
    }
    
    /**
     * @test
     */
    public function test_can_move_with_reason_for_canceled(): void
    {
        $task = $this->createTaskWithStatus(TaskStatus::IN_PROGRESS);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'to_status' => TaskStatus::CANCELED->value,
            'reason' => 'No longer needed',
            'version' => $task->version,
        ]);
        
        $response->assertOk();
        $response->assertJsonPath('data.status', TaskStatus::CANCELED->value);
    }
    
    /**
     * @test
     */
    public function test_returns_404_for_non_existent_task(): void
    {
        $response = $this->patchJson("/api/tasks/99999/move", [
            'to_status' => TaskStatus::IN_PROGRESS->value,
            'version' => 1,
        ]);
        
        $response->assertNotFound();
    }
    
    /**
     * @test
     */
    public function test_validates_to_status_required(): void
    {
        $task = $this->createTaskWithStatus(TaskStatus::BACKLOG);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'version' => $task->version,
            // Missing to_status
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('to_status');
    }
    
    /**
     * @test
     */
    public function test_validates_to_status_is_valid_enum_value(): void
    {
        $task = $this->createTaskWithStatus(TaskStatus::BACKLOG);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'to_status' => 'invalid-status',
            'version' => $task->version,
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('to_status');
    }
    
    /**
     * @test
     */
    public function test_validates_reason_max_length(): void
    {
        $task = $this->createTaskWithStatus(TaskStatus::IN_PROGRESS);
        
        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'to_status' => TaskStatus::BLOCKED->value,
            'reason' => str_repeat('a', 501), // Exceeds max 500
            'version' => $task->version,
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('reason');
    }
    
    //endregion
}
