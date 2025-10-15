<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class TaskCreationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function task_can_be_created_directly()
    {
        // Create demo tenant and user
        $tenant = Tenant::factory()->create([
            'id' => '01k5kzpfwd618xmwdwq3rej3jz',
            'name' => 'Demo Tenant'
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'demo@zenamanage.com',
            'name' => 'Demo User',
            'role' => 'admin'
        ]);
        
        // Create a project manually
        $project = Project::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $tenant->id,
            'name' => 'Test Project',
            'code' => 'TEST-001',
            'description' => 'Test project for task creation',
            'status' => 'active',
            'priority' => 'normal',
            'owner_id' => $user->id,
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

        // Test task creation directly
        $taskData = [
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Test Task Creation',
            'title' => 'Test Task Creation',
            'description' => 'This is a test task created via feature test',
            'status' => 'pending',
            'priority' => 'high',
            'assignee_id' => $user->id,
            'tags' => json_encode(['test', 'feature', 'automation']),
            'watchers' => json_encode([]),
            'dependencies' => json_encode([]),
            'estimated_hours' => 8,
            'progress_percent' => 0,
        ];

        // Create task directly
        $task = Task::create($taskData);

        // Assertions
        $this->assertNotNull($task);
        $this->assertEquals('Test Task Creation', $task->name);
        $this->assertEquals('Test Task Creation', $task->title);
        $this->assertEquals($project->id, $task->project_id);
        $this->assertEquals($tenant->id, $task->tenant_id);
        $this->assertEquals('pending', $task->status);
        $this->assertEquals('high', $task->priority);
        $this->assertEquals($user->id, $task->assignee_id);

        // Assert tags were processed correctly
        $tags = json_decode($task->tags, true);
        $this->assertContains('test', $tags);
        $this->assertContains('feature', $tags);
        $this->assertContains('automation', $tags);

        // Assert task exists in database
        $this->assertDatabaseHas('tasks', [
            'name' => 'Test Task Creation',
            'title' => 'Test Task Creation',
            'project_id' => $project->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending',
            'priority' => 'high',
            'assignee_id' => $user->id,
        ]);
    }

    /** @test */
    public function task_creation_with_minimal_data()
    {
        // Create demo tenant and user
        $tenant = Tenant::factory()->create([
            'id' => '01k5kzpfwd618xmwdwq3rej3jz',
            'name' => 'Demo Tenant'
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'demo@zenamanage.com',
            'name' => 'Demo User',
            'role' => 'admin'
        ]);
        
        // Create a project manually
        $project = Project::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $tenant->id,
            'name' => 'Test Project',
            'code' => 'TEST-001',
            'description' => 'Test project for task creation',
            'status' => 'active',
            'priority' => 'normal',
            'owner_id' => $user->id,
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

        // Test minimal task creation
        $taskData = [
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Minimal Task',
            'title' => 'Minimal Task',
            'tags' => json_encode([]),
            'watchers' => json_encode([]),
            'dependencies' => json_encode([]),
            'progress_percent' => 0,
        ];

        // Create task directly
        $task = Task::create($taskData);

        // Assertions
        $this->assertNotNull($task);
        $this->assertEquals('Minimal Task', $task->name);
        $this->assertEquals('Minimal Task', $task->title);
        $this->assertEquals($project->id, $task->project_id);
        $this->assertEquals($tenant->id, $task->tenant_id);
        $this->assertEquals('backlog', $task->status); // Default status
        $this->assertEquals('normal', $task->priority); // Default priority
        $this->assertEquals(0, $task->progress_percent);

        // Assert task exists in database
        $this->assertDatabaseHas('tasks', [
            'name' => 'Minimal Task',
            'title' => 'Minimal Task',
            'project_id' => $project->id,
            'tenant_id' => $tenant->id,
            'status' => 'backlog',
            'priority' => 'normal',
            'progress_percent' => 0,
        ]);
    }

    /** @test */
    public function task_creation_with_progress_percentage()
    {
        // Create demo tenant and user
        $tenant = Tenant::factory()->create([
            'id' => '01k5kzpfwd618xmwdwq3rej3jz',
            'name' => 'Demo Tenant'
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'demo@zenamanage.com',
            'name' => 'Demo User',
            'role' => 'admin'
        ]);
        
        // Create a project manually
        $project = Project::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $tenant->id,
            'name' => 'Test Project',
            'code' => 'TEST-001',
            'description' => 'Test project for task creation',
            'status' => 'active',
            'priority' => 'normal',
            'owner_id' => $user->id,
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

        // Test task creation with progress
        $taskData = [
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Task with Progress',
            'title' => 'Task with Progress',
            'status' => 'in_progress',
            'priority' => 'high',
            'progress_percent' => 75,
            'tags' => json_encode([]),
            'watchers' => json_encode([]),
            'dependencies' => json_encode([]),
        ];

        // Create task directly
        $task = Task::create($taskData);

        // Assertions
        $this->assertNotNull($task);
        $this->assertEquals('Task with Progress', $task->name);
        $this->assertEquals('Task with Progress', $task->title);
        $this->assertEquals('in_progress', $task->status);
        $this->assertEquals('high', $task->priority);
        $this->assertEquals(75, $task->progress_percent);

        // Assert task exists in database
        $this->assertDatabaseHas('tasks', [
            'name' => 'Task with Progress',
            'title' => 'Task with Progress',
            'project_id' => $project->id,
            'tenant_id' => $tenant->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'progress_percent' => 75,
        ]);
    }
}