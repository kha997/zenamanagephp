<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Illuminate\Support\Facades\Hash;

class TaskApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $tenant;
    protected $project;
    protected $token;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create([
            'id' => 1,
            'name' => 'Test Company',
            'domain' => 'test.com'
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password123'),
        ]);
        
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        $this->createRolesAndPermissions();
        
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);
        
        $this->token = $loginResponse->json('data.token');
    }

    /**
     * Tạo roles và permissions cho test
     */
    private function createRolesAndPermissions()
    {
        $permissions = [
            'task.create',
            'task.read',
            'task.update',
            'task.delete',
        ];
        
        foreach ($permissions as $permissionCode) {
            Permission::create([
                'code' => $permissionCode,
                'module' => 'task',
                'action' => explode('.', $permissionCode)[1],
                'description' => 'Permission for ' . $permissionCode
            ]);
        }
        
        $adminRole = Role::create([
            'name' => 'Admin',
            'scope' => 'system',
            'description' => 'System Administrator'
        ]);
        
        $adminRole->permissions()->attach(
            Permission::whereIn('code', $permissions)->pluck('id')
        );
        
        $this->user->systemRoles()->attach($adminRole->id);
    }

    /**
     * Test get all tasks
     */
    public function test_can_get_all_tasks()
    {
        Task::factory()->count(3)->create([
            'project_id' => $this->project->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/tasks');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'tasks' => [
                             '*' => [
                                 'id',
                                 'name',
                                 'description',
                                 'start_date',
                                 'end_date',
                                 'status',
                                 'priority',
                                 'project_id',
                                 'created_at',
                                 'updated_at'
                             ]
                         ],
                         'pagination'
                     ]
                 ]);
    }

    /**
     * Test create task
     */
    public function test_can_create_task()
    {
        $taskData = [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph,
            'project_id' => $this->project->id,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addWeeks(2)->format('Y-m-d'),
            'status' => 'pending',
            'priority' => 'medium'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/tasks', $taskData);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'task' => [
                             'id',
                             'name',
                             'description',
                             'project_id',
                             'start_date',
                             'end_date',
                             'status',
                             'priority',
                             'created_at',
                             'updated_at'
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('tasks', [
            'name' => $taskData['name'],
            'project_id' => $this->project->id
        ]);
    }

    /**
     * Test update task status
     */
    public function test_can_update_task_status()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->patchJson("/api/v1/tasks/{$task->id}/status", [
            'status' => 'in_progress'
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'task' => [
                             'id' => $task->id,
                             'status' => 'in_progress'
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'in_progress'
        ]);
    }

    /**
     * Test assign task to user
     */
    public function test_can_assign_task_to_user()
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id
        ]);

        $assignee = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/tasks/{$task->id}/assign", [
            'user_id' => $assignee->id,
            'split_percentage' => 100
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'message' => 'Công việc đã được gán thành công'
                     ]
                 ]);

        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'user_id' => $assignee->id,
            'split_percentage' => 100
        ]);
    }

    /**
     * Test task dependencies
     */
    public function test_can_set_task_dependencies()
    {
        $task1 = Task::factory()->create(['project_id' => $this->project->id]);
        $task2 = Task::factory()->create(['project_id' => $this->project->id]);
        $task3 = Task::factory()->create(['project_id' => $this->project->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/tasks/{$task3->id}/dependencies", [
            'dependencies' => [$task1->id, $task2->id]
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'message' => 'Phụ thuộc công việc đã được cập nhật'
                     ]
                 ]);

        $task3->refresh();
        $this->assertEquals([$task1->id, $task2->id], json_decode($task3->dependencies, true));
    }

    /**
     * Test validation errors
     */
    public function test_create_task_validation_errors()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/tasks', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'project_id', 'start_date', 'end_date']);
    }
}