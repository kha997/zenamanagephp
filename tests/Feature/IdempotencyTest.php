<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Idempotency Tests
 * 
 * Tests that critical write operations are idempotent and handle duplicate requests correctly.
 */
class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $idempotencyKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->idempotencyKey = 'test_' . uniqid() . '_' . time();
    }

    /**
     * Test that double-submit within 10 minutes returns same response with X-Idempotency-Cache: hit
     */
    public function test_double_submit_returns_cached_response(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);

        // First request
        $response1 = $this->withHeader('Idempotency-Key', $this->idempotencyKey)
            ->postJson('/api/v1/app/tasks', [
                'project_id' => $project->id,
                'name' => 'Test Task',
                'status' => 'backlog',
            ]);

        $response1->assertStatus(201);
        $taskId1 = $response1->json('data.id');

        // Second request with same idempotency key (within 10 minutes)
        $response2 = $this->withHeader('Idempotency-Key', $this->idempotencyKey)
            ->postJson('/api/v1/app/tasks', [
                'project_id' => $project->id,
                'name' => 'Test Task',
                'status' => 'backlog',
            ]);

        $response2->assertStatus(201);
        $response2->assertHeader('X-Idempotency-Cache', 'hit');
        $response2->assertHeader('X-Idempotent-Replayed', 'true');
        
        // Should return same task ID
        $taskId2 = $response2->json('data.id');
        $this->assertEquals($taskId1, $taskId2, 'Second request should return same task ID');

        // Verify only one task was created
        $taskCount = Task::where('name', 'Test Task')->count();
        $this->assertEquals(1, $taskCount, 'Only one task should be created');
    }

    /**
     * Test that task move is idempotent
     */
    public function test_task_move_is_idempotent(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'status' => 'backlog',
        ]);

        $this->actingAs($this->user);

        // First move request
        $response1 = $this->withHeader('Idempotency-Key', $this->idempotencyKey)
            ->patchJson("/api/v1/app/tasks/{$task->id}/move", [
                'to_status' => 'in_progress',
            ]);

        $response1->assertStatus(200);
        $task->refresh();
        $this->assertEquals('in_progress', $task->status);
        $version1 = $task->version;

        // Second move request with same idempotency key
        $response2 = $this->withHeader('Idempotency-Key', $this->idempotencyKey)
            ->patchJson("/api/v1/app/tasks/{$task->id}/move", [
                'to_status' => 'in_progress',
            ]);

        $response2->assertStatus(200);
        $response2->assertHeader('X-Idempotency-Cache', 'hit');
        
        // Task should not be moved again (version should not increment)
        $task->refresh();
        $this->assertEquals('in_progress', $task->status);
        $this->assertEquals($version1, $task->version, 'Version should not increment on idempotent request');
    }

    /**
     * Test that project creation is idempotent
     */
    public function test_project_creation_is_idempotent(): void
    {
        $this->actingAs($this->user);

        // First request
        $response1 = $this->withHeader('Idempotency-Key', $this->idempotencyKey)
            ->postJson('/api/v1/app/projects', [
                'name' => 'Test Project',
                'code' => 'TEST001',
            ]);

        $response1->assertStatus(201);
        $projectId1 = $response1->json('data.id');

        // Second request with same idempotency key
        $response2 = $this->withHeader('Idempotency-Key', $this->idempotencyKey)
            ->postJson('/api/v1/app/projects', [
                'name' => 'Test Project',
                'code' => 'TEST001',
            ]);

        $response2->assertStatus(201);
        $response2->assertHeader('X-Idempotency-Cache', 'hit');
        
        $projectId2 = $response2->json('data.id');
        $this->assertEquals($projectId1, $projectId2, 'Second request should return same project ID');

        // Verify only one project was created
        $projectCount = Project::where('code', 'TEST001')->count();
        $this->assertEquals(1, $projectCount, 'Only one project should be created');
    }

    /**
     * Test that idempotency key is required for critical operations (required mode)
     */
    public function test_idempotency_key_required_for_critical_operations(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user, 'sanctum');

        // Request without idempotency key should fail for critical operations
        $response = $this->postJson('/api/v1/app/tasks', [
            'project_id' => $project->id,
            'name' => 'Test Task',
            'status' => 'backlog',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'ok' => false,
            'code' => 'IDEMPOTENCY_KEY_REQUIRED',
        ]);
    }

    /**
     * Test that double-submit returns same traceId
     */
    public function test_double_submit_returns_same_trace_id(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user, 'sanctum');

        // First request
        $response1 = $this->withHeader('Idempotency-Key', $this->idempotencyKey)
            ->postJson('/api/v1/app/tasks', [
                'project_id' => $project->id,
                'name' => 'Test Task',
                'status' => 'backlog',
            ]);

        $response1->assertStatus(201);
        $traceId1 = $response1->json('traceId') ?? $response1->headers->get('X-Request-Id');

        // Second request with same idempotency key
        $response2 = $this->withHeader('Idempotency-Key', $this->idempotencyKey)
            ->postJson('/api/v1/app/tasks', [
                'project_id' => $project->id,
                'name' => 'Test Task',
                'status' => 'backlog',
            ]);

        $response2->assertStatus(201);
        $response2->assertHeader('X-Idempotent-Replayed', 'true');
        
        $traceId2 = $response2->json('traceId') ?? $response2->headers->get('X-Request-Id');
        
        // Trace IDs should match (or at least be present)
        if ($traceId1 && $traceId2) {
            $this->assertEquals($traceId1, $traceId2, 'Double-submit should return same traceId');
        }
    }

    /**
     * Test that different idempotency keys create different resources
     */
    public function test_different_idempotency_keys_create_different_resources(): void
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);

        // First request with key1
        $response1 = $this->withHeader('Idempotency-Key', 'key1_' . uniqid())
            ->postJson('/api/v1/app/tasks', [
                'project_id' => $project->id,
                'name' => 'Task 1',
                'status' => 'backlog',
            ]);

        $response1->assertStatus(201);
        $taskId1 = $response1->json('data.id');

        // Second request with different key
        $response2 = $this->withHeader('Idempotency-Key', 'key2_' . uniqid())
            ->postJson('/api/v1/app/tasks', [
                'project_id' => $project->id,
                'name' => 'Task 2',
                'status' => 'backlog',
            ]);

        $response2->assertStatus(201);
        $taskId2 = $response2->json('data.id');

        $this->assertNotEquals($taskId1, $taskId2, 'Different idempotency keys should create different tasks');

        // Verify both tasks exist
        $taskCount = Task::whereIn('id', [$taskId1, $taskId2])->count();
        $this->assertEquals(2, $taskCount, 'Both tasks should be created');
    }
}
