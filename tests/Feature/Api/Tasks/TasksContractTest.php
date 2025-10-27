<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TasksContractTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Tenant $tenant;
    protected Project $project;
    protected $tasks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->tasks = Task::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
        ]);
    }

    /** @test */
    public function tasks_api_returns_correct_response_format()
    {
        Sanctum::actingAs($this->user);

        $response = $this->json('GET', "/api/projects/{$this->project->id}/tasks");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'status',
                        'priority',
                        'start_date',
                        'end_date',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ]
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /** @test */
    public function tasks_api_supports_pagination()
    {
        Sanctum::actingAs($this->user);

        $response = $this->json('GET', "/api/projects/{$this->project->id}/tasks?per_page=2");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals(1, $response->json('meta.current_page'));
    }

    /** @test */
    public function tasks_api_supports_status_filtering()
    {
        Sanctum::actingAs($this->user);

        // Update one task to in_progress status
        $this->tasks->first()->update(['status' => 'in_progress']);
        // Ensure other tasks have different status
        $this->tasks->skip(1)->each(function($task) {
            $task->update(['status' => 'backlog']);
        });

        $response = $this->json('GET', "/api/projects/{$this->project->id}/tasks?status=in_progress");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('in_progress', $data[0]['status']);
    }

    /** @test */
    public function tasks_api_supports_search()
    {
        Sanctum::actingAs($this->user);

        $this->tasks->first()->update(['name' => 'Unique Task Name']);

        $response = $this->json('GET', "/api/projects/{$this->project->id}/tasks?search=Unique Task Name");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Unique Task Name', $data[0]['name']);
    }

    /** @test */
    public function single_task_api_returns_correct_format()
    {
        Sanctum::actingAs($this->user);

        $task = $this->tasks->first();
        $response = $this->json('GET', "/api/projects/{$this->project->id}/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'status',
                    'priority',
                    'start_date',
                    'end_date',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $task->id,
                    'name' => $task->name,
                ]
            ]);
    }

    /** @test */
    public function create_task_api_returns_correct_format()
    {
        Sanctum::actingAs($this->user);

        $taskData = [
            'name' => 'New Test Task',
            'description' => 'Test task description',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
            'priority' => 'normal',
            'project_id' => $this->project->id,
            'assignee_ids' => [$this->user->id],
        ];

        $response = $this->json('POST', "/api/projects/{$this->project->id}/tasks", $taskData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'status',
                    'priority',
                    'start_date',
                    'end_date',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Test Task',
                ]
            ]);

        $this->assertDatabaseHas('tasks', [
            'name' => 'New Test Task',
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function update_task_api_returns_correct_format()
    {
        Sanctum::actingAs($this->user);

        $task = $this->tasks->first();
        $updatedData = [
            'name' => 'Updated Task Name',
            'status' => 'done',
        ];

        $response = $this->json('PUT', "/api/projects/{$this->project->id}/tasks/{$task->id}", $updatedData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $task->id,
                    'name' => 'Updated Task Name',
                    'status' => 'done',
                ]
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'name' => 'Updated Task Name',
            'status' => 'done',
        ]);
    }

    /** @test */
    public function update_task_status_api_works_correctly()
    {
        Sanctum::actingAs($this->user);

        $task = $this->tasks->first();
        $response = $this->json('PATCH', "/api/projects/{$this->project->id}/tasks/{$task->id}", [
            'status' => 'in_progress'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $task->id,
                    'status' => 'in_progress',
                ]
            ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'in_progress',
        ]);
    }

    /** @test */
    public function delete_task_api_works_correctly()
    {
        Sanctum::actingAs($this->user);

        $task = $this->tasks->first();
        $response = $this->json('DELETE', "/api/projects/{$this->project->id}/tasks/{$task->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('tasks', [
            'id' => $task->id,
        ]);
    }

    /** @test */
    public function tasks_api_respects_tenant_isolation()
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role' => 'member',
            'is_active' => true,
        ]);
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);
        Task::factory()->count(2)->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->json('GET', "/api/projects/{$this->project->id}/tasks");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(5, $data); // Only tasks for $this->project
    }

    /** @test */
    public function tasks_api_requires_project_access()
    {
        Sanctum::actingAs($this->user);

        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->json('GET', "/api/projects/{$otherProject->id}/tasks");

        $response->assertStatus(403);
    }
}
