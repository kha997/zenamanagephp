<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Services\BulkOperationsService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Test Bulk Operations System
 * 
 * Kịch bản: Test các thao tác hàng loạt cho users, projects, tasks
 * - Bulk create users, projects, tasks
 * - Bulk update users, projects, tasks
 * - Bulk delete users
 * - Bulk status updates
 * - Import/Export operations
 * - Queue operations
 * - Error handling và validation
 * - Multi-tenant isolation
 */
class BulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $project;
    private $bulkService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set cache driver to array for testing
        config(['cache.default' => 'array']);
        Cache::flush();
        
        // Disable foreign key constraints for testing
        \DB::statement('PRAGMA foreign_keys=OFF;');
        
        // Tạo tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'status' => 'active',
        ]);

        // Tạo user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        // Tạo project
        $this->project = Project::create([
            'name' => 'Test Project',
            'code' => 'TEST001',
            'description' => 'Test project description',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        // Mock BulkOperationsService
        $this->bulkService = $this->app->make(BulkOperationsService::class);
    }

    /**
     * Test bulk create users
     */
    public function test_can_bulk_create_users(): void
    {
        $userData = [
            [
                'name' => 'User 1',
                'email' => 'user1@example.com',
                'password' => 'password123',
                'status' => 'active',
            ],
            [
                'name' => 'User 2',
                'email' => 'user2@example.com',
                'password' => 'password123',
                'status' => 'active',
            ],
            [
                'name' => 'User 3',
                'email' => 'user3@example.com',
                'password' => 'password123',
                'status' => 'active',
            ],
        ];

        $results = $this->bulkService->bulkCreateUsers($userData, $this->tenant->id);

        $this->assertEquals(3, $results['success']);
        $this->assertEquals(0, $results['failed']);
        $this->assertCount(3, $results['created_users']);

        // Kiểm tra users được tạo trong database
        foreach ($results['created_users'] as $userId) {
            $this->assertDatabaseHas('users', [
                'id' => $userId,
                'tenant_id' => $this->tenant->id,
            ]);
        }

        // Kiểm tra total users count
        $totalUsers = User::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(4, $totalUsers); // 3 new + 1 original
    }

    /**
     * Test bulk update users
     */
    public function test_can_bulk_update_users(): void
    {
        // Tạo users để update
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $users[] = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => bcrypt('password'),
                'tenant_id' => $this->tenant->id,
                'status' => 'active',
            ]);
        }

        $updates = [];
        foreach ($users as $user) {
            $updates[] = [
                'id' => $user->id,
                'data' => [
                    'name' => $user->name . ' Updated',
                    'status' => 'inactive',
                ]
            ];
        }

        $results = $this->bulkService->bulkUpdateUsers($updates);

        $this->assertEquals(3, $results['success']);
        $this->assertEquals(0, $results['failed']);
        $this->assertCount(3, $results['updated_users']);

        // Kiểm tra users được update
        foreach ($users as $user) {
            $user->refresh();
            $this->assertStringContainsString('Updated', $user->name);
            $this->assertEquals('inactive', $user->status);
        }
    }

    /**
     * Test bulk delete users
     */
    public function test_can_bulk_delete_users(): void
    {
        // Tạo users để delete
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $users[] = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => bcrypt('password'),
                'tenant_id' => $this->tenant->id,
                'status' => 'active',
            ]);
        }

        $userIds = array_map(fn($user) => $user->id, $users);

        $results = $this->bulkService->bulkDeleteUsers($userIds);

        $this->assertEquals(3, $results['success']);
        $this->assertEquals(0, $results['failed']);
        $this->assertCount(3, $results['deleted_users']);

        // Kiểm tra users bị xóa
        foreach ($users as $user) {
            $this->assertSoftDeleted('users', ['id' => $user->id]);
        }

        // Kiểm tra total users count
        $totalUsers = User::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(1, $totalUsers); // Chỉ còn original user
    }

    /**
     * Test bulk create projects
     */
    public function test_can_bulk_create_projects(): void
    {
        $projectData = [
            [
                'name' => 'Project 1',
                'code' => 'PROJ001',
                'description' => 'Project 1 description',
                'status' => 'active',
            ],
            [
                'name' => 'Project 2',
                'code' => 'PROJ002',
                'description' => 'Project 2 description',
                'status' => 'planning',
            ],
            [
                'name' => 'Project 3',
                'code' => 'PROJ003',
                'description' => 'Project 3 description',
                'status' => 'active',
            ],
        ];

        $results = $this->bulkService->bulkCreateProjects($projectData, $this->tenant->id);

        $this->assertEquals(3, $results['success']);
        $this->assertEquals(0, $results['failed']);
        $this->assertCount(3, $results['created_projects']);

        // Kiểm tra projects được tạo trong database
        foreach ($results['created_projects'] as $projectId) {
            $this->assertDatabaseHas('projects', [
                'id' => $projectId,
                'tenant_id' => $this->tenant->id,
            ]);
        }

        // Kiểm tra total projects count
        $totalProjects = Project::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(4, $totalProjects); // 3 new + 1 original
    }

    /**
     * Test bulk create tasks
     */
    public function test_can_bulk_create_tasks(): void
    {
        $taskData = [
            [
                'name' => 'Task 1',
                'description' => 'Task 1 description',
                'status' => 'open',
                'priority' => 'high',
            ],
            [
                'name' => 'Task 2',
                'description' => 'Task 2 description',
                'status' => 'open',
                'priority' => 'medium',
            ],
            [
                'name' => 'Task 3',
                'description' => 'Task 3 description',
                'status' => 'open',
                'priority' => 'low',
            ],
        ];

        $results = $this->bulkService->bulkCreateTasks($taskData, $this->project->id, $this->tenant->id);

        $this->assertEquals(3, $results['success']);
        $this->assertEquals(0, $results['failed']);
        $this->assertCount(3, $results['created_tasks']);

        // Kiểm tra tasks được tạo trong database
        foreach ($results['created_tasks'] as $taskId) {
            $this->assertDatabaseHas('tasks', [
                'id' => $taskId,
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id,
            ]);
        }

        // Kiểm tra total tasks count
        $totalTasks = Task::where('project_id', $this->project->id)->count();
        $this->assertEquals(3, $totalTasks);
    }

    /**
     * Test bulk update task status
     */
    public function test_can_bulk_update_task_status(): void
    {
        // Tạo tasks để update
        $tasks = [];
        for ($i = 1; $i <= 3; $i++) {
            $tasks[] = Task::create([
                'name' => "Task {$i}",
                'description' => "Task {$i} description",
                'status' => 'open',
                'priority' => 'medium',
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'assigned_to' => $this->user->id,
                'created_by' => $this->user->id,
            ]);
        }

        $taskIds = array_map(fn($task) => $task->id, $tasks);

        $results = $this->bulkService->bulkUpdateTaskStatus($taskIds, 'completed');

        $this->assertEquals(3, $results['success']);
        $this->assertEquals(0, $results['failed']);
        $this->assertCount(3, $results['updated_tasks']);

        // Kiểm tra tasks được update
        foreach ($tasks as $task) {
            $task->refresh();
            $this->assertEquals('completed', $task->status);
        }
    }

    /**
     * Test bulk operations validation
     */
    public function test_bulk_operations_validation(): void
    {
        // Test quá nhiều operations
        $tooManyUsers = array_fill(0, 1001, [
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $this->expectException(\Exception::class);
        $this->bulkService->bulkCreateUsers($tooManyUsers, $this->tenant->id);
    }

    /**
     * Test bulk operations với invalid data
     */
    public function test_bulk_operations_with_invalid_data(): void
    {
        $invalidUserData = [
            [
                'name' => 'Valid User',
                'email' => 'valid@example.com',
                'password' => 'password',
            ],
            [
                'name' => '', // Invalid: empty name
                'email' => 'invalid@example.com',
                'password' => 'password',
            ],
            [
                'name' => 'Another User',
                'email' => 'another@example.com',
                'password' => 'password',
            ],
        ];

        $results = $this->bulkService->bulkCreateUsers($invalidUserData, $this->tenant->id);

        $this->assertEquals(2, $results['success']); // 2 valid users
        $this->assertEquals(1, $results['failed']); // 1 invalid user
        $this->assertCount(1, $results['errors']);
    }

    /**
     * Test multi-tenant isolation trong bulk operations
     */
    public function test_bulk_operations_tenant_isolation(): void
    {
        // Tạo tenant khác
        $otherTenant = Tenant::create([
            'name' => 'Other Company',
            'slug' => 'other-company',
            'status' => 'active',
        ]);

        // Tạo users cho tenant khác
        $otherUsers = [];
        for ($i = 1; $i <= 2; $i++) {
            $otherUsers[] = User::create([
                'name' => "Other User {$i}",
                'email' => "other{$i}@example.com",
                'password' => bcrypt('password'),
                'tenant_id' => $otherTenant->id,
                'status' => 'active',
            ]);
        }

        // Tạo users cho tenant hiện tại
        $currentUsers = [];
        for ($i = 1; $i <= 2; $i++) {
            $currentUsers[] = User::create([
                'name' => "Current User {$i}",
                'email' => "current{$i}@example.com",
                'password' => bcrypt('password'),
                'tenant_id' => $this->tenant->id,
                'status' => 'active',
            ]);
        }

        // Bulk update chỉ users của tenant hiện tại
        $updates = [];
        foreach ($currentUsers as $user) {
            $updates[] = [
                'id' => $user->id,
                'data' => ['status' => 'inactive']
            ];
        }

        $results = $this->bulkService->bulkUpdateUsers($updates);

        $this->assertEquals(2, $results['success']);

        // Kiểm tra chỉ users của tenant hiện tại được update
        foreach ($currentUsers as $user) {
            $user->refresh();
            $this->assertEquals('inactive', $user->status);
        }

        // Kiểm tra users của tenant khác không bị ảnh hưởng
        foreach ($otherUsers as $user) {
            $user->refresh();
            $this->assertEquals('active', $user->status);
        }
    }

    /**
     * Test bulk operations với empty data
     */
    public function test_bulk_operations_with_empty_data(): void
    {
        $results = $this->bulkService->bulkCreateUsers([], $this->tenant->id);

        $this->assertEquals(0, $results['success']);
        $this->assertEquals(0, $results['failed']);
        $this->assertCount(0, $results['created_users']);
    }

    /**
     * Test bulk operations performance với large dataset
     */
    public function test_bulk_operations_performance(): void
    {
        // Tạo 100 users để test performance
        $userData = [];
        for ($i = 1; $i <= 100; $i++) {
            $userData[] = [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'password',
                'status' => 'active',
            ];
        }

        $startTime = microtime(true);
        $results = $this->bulkService->bulkCreateUsers($userData, $this->tenant->id);
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        $this->assertEquals(100, $results['success']);
        $this->assertEquals(0, $results['failed']);
        
        // Performance check: should complete within reasonable time (5 seconds)
        $this->assertLessThan(5.0, $executionTime, 'Bulk operation took too long');
    }

    /**
     * Test bulk operations với database transaction rollback
     */
    public function test_bulk_operations_transaction_rollback(): void
    {
        // Mock một exception trong quá trình bulk operation
        $this->mock(BulkOperationsService::class, function ($mock) {
            $mock->shouldReceive('bulkCreateUsers')
                ->andThrow(new \Exception('Database error'));
        });

        $userData = [
            [
                'name' => 'User 1',
                'email' => 'user1@example.com',
                'password' => 'password',
            ],
        ];

        $this->expectException(\Exception::class);
        $this->bulkService->bulkCreateUsers($userData, $this->tenant->id);
    }

    /**
     * Test bulk operations với mixed success/failure
     */
    public function test_bulk_operations_mixed_results(): void
    {
        // Tạo users với mixed valid/invalid data
        $mixedUserData = [
            [
                'name' => 'Valid User 1',
                'email' => 'valid1@example.com',
                'password' => 'password',
            ],
            [
                'name' => '', // Invalid
                'email' => 'invalid@example.com',
                'password' => 'password',
            ],
            [
                'name' => 'Valid User 2',
                'email' => 'valid2@example.com',
                'password' => 'password',
            ],
            [
                'name' => 'Valid User 3',
                'email' => 'valid3@example.com',
                'password' => 'password',
            ],
        ];

        $results = $this->bulkService->bulkCreateUsers($mixedUserData, $this->tenant->id);

        $this->assertEquals(3, $results['success']);
        $this->assertEquals(1, $results['failed']);
        $this->assertCount(3, $results['created_users']);
        $this->assertCount(1, $results['errors']);

        // Kiểm tra error details
        $error = $results['errors'][0];
        $this->assertArrayHasKey('index', $error);
        $this->assertArrayHasKey('error', $error);
    }
}
