<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\Project;
use App\Models\User;
use App\Models\Tenant;

class TaskEditTest extends TestCase
{
    use RefreshDatabase;

    protected $task;
    protected $project;
    protected $user;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        // Create test data
        $this->project = Project::factory()->create([
            'id' => '01k5e2kkwynze0f37a8a4d3435',
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
            'description' => 'Test project for testing',
            'code' => 'TEST001',
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        $this->user = User::factory()->create([
            'id' => '01k5e5nty3m1059pcyymbkgqt9',
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->task = Task::create([
            'id' => '01k5e5nty3m1059pcyymbkgqt8',
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'description' => 'Test task description',
            'status' => 'in_progress',
            'priority' => 'low',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'assignee_id' => $this->user->id,
            'progress_percent' => 50,
            'estimated_hours' => 8,
        ]);
    }

    /** @test */
    public function test_task_edit_page_loads_correctly()
    {
        $response = $this->actingAs($this->user)->get(route('app.tasks.edit', $this->task->id));

        $response->assertStatus(200);
        $response->assertViewIs('tasks.edit');
        $response->assertViewHas('task');
        $response->assertViewHas('projects');

        // Check if task data is passed correctly
        $task = $response->viewData('task');
        $this->assertEquals($this->task->id, $task->id);
        $this->assertEquals('in_progress', $task->status);
        $this->assertEquals('low', $task->priority);
    }

    /** @test */
    public function test_task_edit_page_redirects_unauthenticated_users_to_login()
    {
        $response = $this->get(route('app.tasks.edit', $this->task->id));

        $response->assertRedirect('/login');
    }

    /** @test */
    public function test_task_edit_deep_link_for_missing_task_still_loads_app_shell()
    {
        $response = $this->actingAs($this->user)->get('/app/tasks/non-existent-task-id/edit');

        $response->assertStatus(200);
        $response->assertViewIs('tasks.edit');
        $response->assertViewHas('task', null);
    }
}
