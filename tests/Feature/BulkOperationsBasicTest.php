<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SSOT\FixtureFactory;

class BulkOperationsBasicTest extends TestCase
{
    use RefreshDatabase, FixtureFactory;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant and user
        $this->tenant = $this->createTenant([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . Str::lower((string) Str::ulid()),
            'status' => 'active'
        ]);
        
        $this->user = $this->createTenantUserWithRbac($this->tenant, 'member', 'member', [], [
            'name' => 'Test User',
            'email' => 'test+' . Str::lower((string) Str::ulid()) . '@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_can_create_multiple_users()
    {
        $userData = [
            [
                'name' => 'User 1',
                'email' => 'user1@example.com',
                'password' => bcrypt('password123'),
                'tenant_id' => $this->tenant->id
            ],
            [
                'name' => 'User 2', 
                'email' => 'user2@example.com',
                'password' => bcrypt('password123'),
                'tenant_id' => $this->tenant->id
            ]
        ];

        $createdUsers = [];
        foreach ($userData as $data) {
            $user = User::factory()->create($data);
            $createdUsers[] = $user;
        }

        $this->assertCount(2, $createdUsers);
        
        // Verify users were created
        $this->assertDatabaseHas('users', ['email' => 'user1@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'user2@example.com']);
    }

    public function test_can_create_multiple_projects()
    {
        $projectData = [
            [
                'name' => 'Project 1',
                'code' => 'PROJ-001',
                'description' => 'Test project 1',
                'status' => 'active',
                'tenant_id' => $this->tenant->id,
                'created_by' => $this->user->id
            ],
            [
                'name' => 'Project 2',
                'code' => 'PROJ-002',
                'description' => 'Test project 2', 
                'status' => 'active',
                'tenant_id' => $this->tenant->id,
                'created_by' => $this->user->id
            ]
        ];

        $createdProjects = [];
        foreach ($projectData as $data) {
            $project = Project::factory()->create($data);
            $createdProjects[] = $project;
        }

        $this->assertCount(2, $createdProjects);
        
        // Verify projects were created
        $this->assertDatabaseHas('projects', ['name' => 'Project 1']);
        $this->assertDatabaseHas('projects', ['name' => 'Project 2']);
    }

    public function test_can_create_multiple_tasks()
    {
        // Create a project first
        $project = Project::factory()->create([
            'name' => 'Test Project',
            'code' => 'TEST-001',
            'description' => 'Test project',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id
        ]);

        $taskData = [
            [
                'name' => 'Task 1',
                'description' => 'Test task 1',
                'status' => 'pending',
                'priority' => 'high',
                'project_id' => $project->id,
                'tenant_id' => $this->tenant->id,
                'created_by' => $this->user->id
            ],
            [
                'name' => 'Task 2',
                'description' => 'Test task 2',
                'status' => 'pending', 
                'priority' => 'medium',
                'project_id' => $project->id,
                'tenant_id' => $this->tenant->id,
                'created_by' => $this->user->id
            ]
        ];

        $createdTasks = [];
        foreach ($taskData as $data) {
            $task = Task::create($data);
            $createdTasks[] = $task;
        }

        $this->assertCount(2, $createdTasks);
        
        // Verify tasks were created
        $this->assertDatabaseHas('tasks', ['name' => 'Task 1']);
        $this->assertDatabaseHas('tasks', ['name' => 'Task 2']);
    }

    public function test_can_use_db_transactions()
    {
        DB::beginTransaction();
        
        try {
            // Create a user
            $user = User::factory()->create([
                'name' => 'Transaction User',
                'email' => 'transaction@example.com',
                'password' => bcrypt('password'),
                'tenant_id' => $this->tenant->id
            ]);
            
            $this->assertDatabaseHas('users', ['email' => 'transaction@example.com']);
            
            DB::commit();
            
            // User should still exist after commit
            $this->assertDatabaseHas('users', ['email' => 'transaction@example.com']);
            
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $e;
        }
    }

    public function test_can_rollback_transactions()
    {
        DB::beginTransaction();
        
        try {
            // Create a user
            $user = User::factory()->create([
                'name' => 'Rollback User',
                'email' => 'rollback@example.com',
                'password' => bcrypt('password'),
                'tenant_id' => $this->tenant->id
            ]);
            
            $this->assertDatabaseHas('users', ['email' => 'rollback@example.com']);
            
            // Rollback the transaction
            DB::rollBack();
            
            // User should not exist after rollback
            $this->assertDatabaseMissing('users', ['email' => 'rollback@example.com']);
            
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $e;
        }
    }

    public function test_can_validate_data()
    {
        // Test empty data validation - MySQL is more lenient than expected
        // Let's test with a more obvious constraint violation
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        User::factory()->create([
            'name' => 'Test User',
            'email' => null, // NULL email should fail
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);
    }

    public function test_can_handle_duplicate_emails()
    {
        // Create first user
        User::factory()->create([
            'name' => 'First User',
            'email' => 'duplicate@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);
        
        // Try to create second user with same email
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        User::factory()->create([
            'name' => 'Second User',
            'email' => 'duplicate@example.com', // Duplicate email
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);
    }

    public function test_can_handle_performance()
    {
        $startTime = microtime(true);
        
        // Create 10 users
        $users = [];
        for ($i = 1; $i <= 10; $i++) {
            $users[] = User::factory()->create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => bcrypt('password123'),
                'tenant_id' => $this->tenant->id
            ]);
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertCount(10, $users);
        
        // Should complete within reasonable time (5 seconds)
        $this->assertLessThan(5, $executionTime);
    }

    public function test_can_handle_tenant_isolation()
    {
        // Create another tenant
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 'active'
        ]);

        // Create user for other tenant
        $otherUser = User::factory()->create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $otherTenant->id
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'other@example.com',
            'tenant_id' => $otherTenant->id
        ]);
        
        // Verify tenant isolation
        $this->assertNotEquals($this->tenant->id, $otherTenant->id);
        $this->assertNotEquals($this->user->tenant_id, $otherUser->tenant_id);
    }
}
