<?php declare(strict_types=1);

namespace Tests\Feature\Buttons;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Button CRUD Test
 * 
 * Tests Create, Read, Update, Delete operations for all interactive elements
 */
class ButtonCRUDTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'status' => 'active'
        ]);

        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@test-' . uniqid() . '.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenant->id
        ]);

        // Create test project
        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TEST-' . uniqid(),
            'name' => 'Test Project',
            'description' => 'Test project for CRUD operations',
            'status' => 'active',
            'budget_total' => 100000.00
        ]);
    }

    /**
     * Test project creation
     */
    public function test_can_create_project(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/projects', [
            'name' => 'New Project',
            'description' => 'A new test project',
            'code' => 'NEW-' . uniqid(),
            'status' => 'active',
            'budget_total' => 75000.00
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('projects', [
            'name' => 'New Project',
            'tenant_id' => $this->tenant->id
        ]);
    }

    /**
     * Test project reading
     */
    public function test_can_read_project(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/projects/' . $this->project->id);
        
        $response->assertStatus(200);
        $response->assertSee($this->project->name);
    }

    /**
     * Test project updating
     */
    public function test_can_update_project(): void
    {
        $this->actingAs($this->user);

        $response = $this->put('/projects/' . $this->project->id, [
            'name' => 'Updated Project',
            'description' => 'Updated description',
            'code' => $this->project->code,
            'status' => 'active',
            'budget_total' => 120000.00
        ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'name' => 'Updated Project'
        ]);
    }

    /**
     * Test project deletion
     */
    public function test_can_delete_project(): void
    {
        $this->actingAs($this->user);

        $response = $this->delete('/projects/' . $this->project->id);
        
        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('projects', [
            'id' => $this->project->id
        ]);
    }

    /**
     * Test task creation
     */
    public function test_can_create_task(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/tasks', [
            'name' => 'New Task',
            'description' => 'A new test task',
            'project_id' => $this->project->id,
            'status' => 'pending',
            'priority' => 'medium',
            'estimated_hours' => 8.0
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('tasks', [
            'name' => 'New Task',
            'project_id' => $this->project->id
        ]);
    }

    /**
     * Test task reading
     */
    public function test_can_read_task(): void
    {
        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'description' => 'Test task description',
            'status' => 'pending',
            'priority' => 'medium',
            'estimated_hours' => 8.0
        ]);

        $this->actingAs($this->user);

        $response = $this->get('/tasks/' . $task->id);
        
        $response->assertStatus(200);
        $response->assertSee($task->name);
    }

    /**
     * Test task updating
     */
    public function test_can_update_task(): void
    {
        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'description' => 'Test task description',
            'status' => 'pending',
            'priority' => 'medium',
            'estimated_hours' => 8.0
        ]);

        $this->actingAs($this->user);

        $response = $this->put('/tasks/' . $task->id, [
            'name' => 'Updated Task',
            'description' => 'Updated task description',
            'project_id' => $this->project->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'estimated_hours' => 12.0
        ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'name' => 'Updated Task',
            'status' => 'in_progress'
        ]);
    }

    /**
     * Test task deletion
     */
    public function test_can_delete_task(): void
    {
        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'description' => 'Test task description',
            'status' => 'pending',
            'priority' => 'medium',
            'estimated_hours' => 8.0
        ]);

        $this->actingAs($this->user);

        $response = $this->delete('/tasks/' . $task->id);
        
        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id
        ]);
    }

    /**
     * Test form validation
     */
    public function test_form_validation(): void
    {
        $this->actingAs($this->user);

        // Test project creation with invalid data
        $response = $this->post('/projects', [
            'name' => '', // Required field empty
            'description' => 'Test description',
            'code' => 'INVALID', // Invalid code format
            'status' => 'invalid_status', // Invalid status
            'budget_total' => -1000 // Negative budget
        ]);

        $response->assertStatus(422);
        $response->assertSessionHasErrors(['name', 'code', 'status', 'budget_total']);
    }

    /**
     * Test CSRF protection
     */
    public function test_csrf_protection(): void
    {
        $this->actingAs($this->user);

        // Test POST without CSRF token
        $response = $this->post('/projects', [
            'name' => 'Test Project',
            'description' => 'Test description',
            'code' => 'TEST-' . uniqid(),
            'status' => 'active',
            'budget_total' => 50000.00
        ], [
            'X-CSRF-TOKEN' => 'invalid_token'
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    /**
     * Test idempotency for safe operations
     */
    public function test_idempotency(): void
    {
        $this->actingAs($this->user);

        // Test multiple GET requests (should be idempotent)
        $response1 = $this->get('/projects/' . $this->project->id);
        $response2 = $this->get('/projects/' . $this->project->id);
        
        $this->assertEquals($response1->getContent(), $response2->getContent());
    }

    /**
     * Test bulk operations
     */
    public function test_bulk_operations(): void
    {
        $this->actingAs($this->user);

        // Create multiple tasks
        $tasks = [];
        for ($i = 0; $i < 3; $i++) {
            $task = Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'name' => "Bulk Task {$i}",
                'description' => "Bulk task description {$i}",
                'status' => 'pending',
                'priority' => 'medium',
                'estimated_hours' => 8.0
            ]);
            $tasks[] = $task;
        }

        // Test bulk status update
        $response = $this->postJson('/api/tasks/bulk/status-change', [
            'task_ids' => array_column($tasks, 'id'),
            'status' => 'completed'
        ]);

        $response->assertStatus(200);

        // Verify all tasks were updated
        foreach ($tasks as $task) {
            $this->assertDatabaseHas('tasks', [
                'id' => $task->id,
                'status' => 'completed'
            ]);
        }
    }

    /**
     * Test error handling
     */
    public function test_error_handling(): void
    {
        $this->actingAs($this->user);

        // Test 404 for non-existent resource
        $response = $this->get('/projects/999999');
        $response->assertStatus(404);

        // Test 404 for non-existent task
        $response = $this->get('/tasks/999999');
        $response->assertStatus(404);

        // Test 404 for non-existent project update
        $response = $this->put('/projects/999999', [
            'name' => 'Non-existent Project'
        ]);
        $response->assertStatus(404);
    }
}
