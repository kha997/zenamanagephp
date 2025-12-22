<?php declare(strict_types=1);

namespace Tests\Contract;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Tasks API Contract Tests
 * 
 * Verifies that Tasks API responses match the OpenAPI specification.
 */
class TasksContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'tenant_id' => 'test_tenant_' . uniqid(),
        ]);
        
        $this->project = Project::factory()->create([
            'tenant_id' => $this->user->tenant_id,
        ]);
    }

    /**
     * Test GET /api/v1/app/tasks response structure
     */
    public function test_get_tasks_response_structure(): void
    {
        Task::factory()->count(3)->create([
            'tenant_id' => $this->user->tenant_id,
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/app/tasks');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Should have success and data fields
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        
        // Data should be an array
        $this->assertIsArray($data['data']);
        
        // Each task should have required fields
        if (!empty($data['data'])) {
            $task = $data['data'][0];
            $this->assertArrayHasKey('id', $task);
            $this->assertArrayHasKey('name', $task);
            $this->assertArrayHasKey('status', $task);
        }
    }

    /**
     * Test POST /api/v1/app/tasks response structure
     */
    public function test_create_task_response_structure(): void
    {
        $taskData = [
            'name' => 'Test Task',
            'project_id' => $this->project->id,
            'status' => 'todo',
            'priority' => 'medium',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeader('Idempotency-Key', 'test_' . uniqid())
            ->postJson('/api/v1/app/tasks', $taskData);

        $response->assertStatus(201);
        
        $data = $response->json();
        
        // Should have success and data fields
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        
        // Task data should have required fields
        $task = $data['data'];
        $this->assertArrayHasKey('id', $task);
        $this->assertArrayHasKey('name', $task);
        $this->assertArrayHasKey('status', $task);
    }
}

