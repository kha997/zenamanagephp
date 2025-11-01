<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class TaskAssignmentTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant;
    protected $user;
    protected $project;
    protected $task;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create demo tenant and user for testing
        $this->tenant = Tenant::factory()->create([
            'id' => '01k5kzpfwd618xmwdwq3rej3jz',
            'name' => 'Demo Tenant'
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'demo@zenamanage.com',
            'name' => 'Demo User',
            'role' => 'admin'
        ]);
        
        // Authenticate user for all tests
        $this->actingAs($this->user);
        
        // Create a project manually
        $this->project = Project::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
            'code' => 'TEST-001',
            'description' => 'Test project for task assignment',
            'status' => 'active',
            'priority' => 'normal',
            'owner_id' => $this->user->id,
            'tags' => json_encode(['test']),
            'start_date' => now(),
            'progress_pct' => 0,
            'budget_total' => 10000,
            'budget_planned' => 10000,
            'budget_actual' => 0,
            'estimated_hours' => 40,
            'actual_hours' => 0,
            'risk_level' => 'low',
            'is_template' => false,
            'last_activity_at' => now(),
            'completion_percentage' => 0,
            'settings' => json_encode([
                'notifications' => true,
                'auto_assign' => false,
                'require_approval' => false,
            ]),
        ]);

        // Create a task manually
        $this->task = Task::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Test Task for Assignment',
            'title' => 'Test Task for Assignment',
            'description' => 'This task is for testing assignment functionality',
            'status' => 'backlog',
            'priority' => 'normal',
            'tags' => json_encode(['test', 'assignment']),
            'watchers' => json_encode([]),
            'dependencies' => json_encode([]),
            'progress_percent' => 0,
        ]);
    }

    /** @test */
    public function user_can_assign_task_to_another_user()
    {
        // Create another user in the same tenant
        $assignee = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'assignee@zenamanage.com',
            'name' => 'Assignee User',
            'role' => 'member'
        ]);

        // Make request to assign task
        $response = $this->postJson("/app/tasks/{$this->task->id}/assign", [
            'assignee_id' => $assignee->id
        ]);

        // Assert response
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Task đã được gán thành công'
        ]);

        // Assert task was updated in database
        $this->assertDatabaseHas('tasks', [
            'id' => $this->task->id,
            'assignee_id' => $assignee->id,
            'assigned_to' => $assignee->id,
        ]);

        // Assert task model has correct assignee
        $this->task->refresh();
        $this->assertEquals($assignee->id, $this->task->assignee_id);
        $this->assertEquals($assignee->name, $this->task->assignee->name);
    }

    /** @test */
    public function user_can_unassign_task()
    {
        // First assign the task
        $assignee = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'assignee@zenamanage.com',
            'name' => 'Assignee User',
            'role' => 'member'
        ]);

        $this->task->update([
            'assignee_id' => $assignee->id,
            'assigned_to' => $assignee->id,
        ]);

        // Make request to unassign task
        $response = $this->postJson("/app/tasks/{$this->task->id}/unassign");

        // Assert response
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Task đã được hủy gán thành công'
        ]);

        // Assert task was updated in database
        $this->assertDatabaseHas('tasks', [
            'id' => $this->task->id,
            'assignee_id' => null,
            'assigned_to' => null,
        ]);

        // Assert task model has no assignee
        $this->task->refresh();
        $this->assertNull($this->task->assignee_id);
        $this->assertNull($this->task->assignee);
    }

    /** @test */
    public function assignment_requires_valid_user()
    {
        // Try to assign to non-existent user
        $response = $this->postJson("/app/tasks/{$this->task->id}/assign", [
            'assignee_id' => 'non-existent-id'
        ]);

        // Assert validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['assignee_id']);
    }

    /** @test */
    public function assignment_requires_assignee_id()
    {
        // Try to assign without assignee_id
        $response = $this->postJson("/app/tasks/{$this->task->id}/assign", []);

        // Assert validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['assignee_id']);
    }

    /** @test */
    public function cannot_assign_task_from_different_tenant()
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant'
        ]);
        
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'other@zenamanage.com',
            'name' => 'Other User',
            'role' => 'member'
        ]);

        // Try to assign task to user from different tenant
        $response = $this->postJson("/app/tasks/{$this->task->id}/assign", [
            'assignee_id' => $otherUser->id
        ]);

        // Should fail because user doesn't exist in current tenant context
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['assignee_id']);
    }

    /** @test */
    public function assignment_updates_both_assignee_fields()
    {
        // Create assignee user
        $assignee = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'assignee@zenamanage.com',
            'name' => 'Assignee User',
            'role' => 'member'
        ]);

        // Assign task
        $response = $this->postJson("/app/tasks/{$this->task->id}/assign", [
            'assignee_id' => $assignee->id
        ]);

        $response->assertStatus(200);

        // Check both fields are updated
        $this->task->refresh();
        $this->assertEquals($assignee->id, $this->task->assignee_id);
        $this->assertEquals($assignee->id, $this->task->assigned_to);
    }

    /** @test */
    public function unassignment_clears_both_assignee_fields()
    {
        // First assign the task
        $assignee = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'assignee@zenamanage.com',
            'name' => 'Assignee User',
            'role' => 'member'
        ]);

        $this->task->update([
            'assignee_id' => $assignee->id,
            'assigned_to' => $assignee->id,
        ]);

        // Unassign task
        $response = $this->postJson("/app/tasks/{$this->task->id}/unassign");

        $response->assertStatus(200);

        // Check both fields are cleared
        $this->task->refresh();
        $this->assertNull($this->task->assignee_id);
        $this->assertNull($this->task->assigned_to);
    }
}
