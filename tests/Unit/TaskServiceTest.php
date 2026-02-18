<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\TaskService;
use App\Services\AuditService;
use App\Services\TaskRepository;
use App\Models\Task;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Mockery;

class TaskServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $taskService;
    protected TaskRepository $taskRepository;
    protected $auditService;
    protected $project;
    protected $user;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();

        $this->user = User::factory()
            ->forTenant($this->tenant->id)
            ->create([
                'name' => 'Test User',
                'email' => 'test-tenant-user-' . \Illuminate\Support\Str::ulid() . '@example.com',
                'password' => bcrypt('password'),
            ]);

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Project',
            'description' => 'Test project for testing',
            'code' => 'TEST001',
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'pm_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $this->taskRepository = Mockery::mock(TaskRepository::class)->makePartial();
        $this->taskRepository->shouldAllowMockingMethod('getQuery');
        $this->taskRepository
            ->shouldReceive('getQuery')
            ->andReturnUsing(fn () => Task::query());

        $this->app->instance(TaskRepository::class, $this->taskRepository);
        $this->auditService = Mockery::mock(AuditService::class);
        $this->app->instance(AuditService::class, $this->auditService);
        $this->taskService = $this->app->make(TaskService::class);
    }

    /** @test */
    public function test_create_task_with_all_fields()
    {
        $userId = $this->user->id;
        $tenantId = $this->tenant->id;
        $projectId = $this->project->id;

        $this->auditService
            ->shouldReceive('log')
            ->once()
            ->with('task_created', $userId, $tenantId, Mockery::type('array'))
            ->andReturnNull();

        $taskData = [
            'title' => 'Test Task',
            'description' => 'Test task description',
            'project_id' => $projectId,
            'priority' => 'low',
            'due_date' => now()->addDays(7)->toDateString(),
        ];

        $task = $this->taskService->createTask($taskData, $userId, $tenantId);

        $this->assertInstanceOf(Task::class, $task);
        $this->assertEquals('Test Task', $task->title);
        $this->assertEquals('pending', $task->status);
        $this->assertEquals('low', $task->priority);
        $this->assertSame($this->tenant->id, $task->tenant_id);
        $this->assertSame($projectId, $task->project_id);
    }

    /** @test */
    public function test_get_tasks_with_filters()
    {
        $userId = $this->user->id;
        $tenantId = $this->tenant->id;

        // Create multiple tasks with different statuses
        Task::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Pending Task',
            'status' => 'pending',
            'priority' => 'low',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
        ]);

        Task::create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Completed Task',
            'status' => 'completed',
            'priority' => 'high',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
        ]);

        // Test status filter
        $pendingTasks = $this->taskService->getTasks(['status' => 'pending'], $userId, $tenantId);
        $this->assertCount(1, $pendingTasks);

        $completedTasks = $this->taskService->getTasks(['status' => 'completed'], $userId, $tenantId);
        $this->assertCount(1, $completedTasks);

        // Test project filter
        $projectTasks = $this->taskService->getTasks(['project_id' => $this->project->id], $userId, $tenantId);
        $this->assertCount(2, $projectTasks);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
