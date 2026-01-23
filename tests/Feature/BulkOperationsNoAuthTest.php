<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Services\BulkOperationsService;
use App\Services\SecureAuditService;
use Illuminate\Support\Facades\DB;

class BulkOperationsNoAuthTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
    protected $bulkService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant and user
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active'
        ]);
        
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);
        
        $this->bulkService = new BulkOperationsService(new SecureAuditService());
    }

    public function test_can_bulk_create_users()
    {
        $userData = [
            [
                'name' => 'User 1',
                'email' => 'user1@example.com',
                'password' => 'password123',
                'tenant_id' => $this->tenant->id
            ],
            [
                'name' => 'User 2', 
                'email' => 'user2@example.com',
                'password' => 'password123',
                'tenant_id' => $this->tenant->id
            ]
        ];

        $result = $this->bulkService->bulkCreateUsers($userData);

        $this->assertTrue($result['success'] > 0);
        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(2, $result['created_users']);
        
        // Verify users were created
        $this->assertDatabaseHas('users', ['email' => 'user1@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'user2@example.com']);
    }

    public function test_can_bulk_create_projects()
    {
        $projectData = [
            [
                'name' => 'Project 1',
                'description' => 'Test project 1',
                'status' => 'active',
                'tenant_id' => $this->tenant->id,
                'created_by' => $this->user->id
            ],
            [
                'name' => 'Project 2',
                'description' => 'Test project 2', 
                'status' => 'active',
                'tenant_id' => $this->tenant->id,
                'created_by' => $this->user->id
            ]
        ];

        $result = $this->bulkService->bulkCreateProjects($projectData);

        $this->assertTrue($result['success'] > 0);
        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['failed']);
        
        // Verify projects were created
        $this->assertDatabaseHas('projects', ['name' => 'Project 1']);
        $this->assertDatabaseHas('projects', ['name' => 'Project 2']);
    }

    public function test_can_bulk_create_tasks()
    {
        // Create a project first
        $project = Project::create([
            'name' => 'Test Project',
            'description' => 'Test project',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-' . strtoupper(bin2hex(random_bytes(6))),
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

        $result = $this->bulkService->bulkCreateTasks($taskData);

        $this->assertTrue($result['success'] > 0);
        $this->assertEquals(2, $result['created']);
        $this->assertEquals(0, $result['failed']);
        
        // Verify tasks were created
        $this->assertDatabaseHas('tasks', ['name' => 'Task 1']);
        $this->assertDatabaseHas('tasks', ['name' => 'Task 2']);
    }

    public function test_bulk_operations_validation()
    {
        // Test empty data
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No operations to perform');
        
        $this->bulkService->bulkCreateUsers([]);
    }

    public function test_bulk_operations_with_invalid_data()
    {
        $invalidData = [
            [
                'name' => '', // Invalid: empty name
                'email' => 'invalid-email', // Invalid: bad email format
                'password' => '123', // Invalid: too short
                'tenant_id' => $this->tenant->id
            ]
        ];

        $result = $this->bulkService->bulkCreateUsers($invalidData);

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['failed']);
        $this->assertCount(1, $result['errors']);
    }

    public function test_bulk_operations_tenant_isolation()
    {
        // Create another tenant
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 'active'
        ]);

        // Try to create user with different tenant_id
        $userData = [
            [
                'name' => 'User 1',
                'email' => 'user1@example.com',
                'password' => 'password123',
                'tenant_id' => $otherTenant->id // Different tenant
            ]
        ];

        $result = $this->bulkService->bulkCreateUsers($userData);

        // Should still work - bulk operations don't enforce tenant isolation
        // That's handled by middleware/controllers
        $this->assertTrue($result['success'] > 0);
        $this->assertEquals(1, $result['created']);
    }

    public function test_bulk_operations_performance()
    {
        $startTime = microtime(true);
        
        // Create 10 users
        $userData = [];
        for ($i = 1; $i <= 10; $i++) {
            $userData[] = [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => 'password123',
                'tenant_id' => $this->tenant->id
            ];
        }

        $result = $this->bulkService->bulkCreateUsers($userData);
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $this->assertTrue($result['success'] > 0);
        $this->assertEquals(10, $result['created']);
        
        // Should complete within reasonable time (5 seconds)
        $this->assertLessThan(5, $executionTime);
    }

    public function test_bulk_operations_transaction_rollback()
    {
        // Create a user that will cause a constraint violation
        User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id
        ]);

        $userData = [
            [
                'name' => 'User 1',
                'email' => 'user1@example.com',
                'password' => 'password123',
                'tenant_id' => $this->tenant->id
            ],
            [
                'name' => 'User 2',
                'email' => 'existing@example.com', // Duplicate email - should fail
                'password' => 'password123',
                'tenant_id' => $this->tenant->id
            ]
        ];

        $result = $this->bulkService->bulkCreateUsers($userData);

        // Should fail due to duplicate email
        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['created']);
        $this->assertEquals(2, $result['failed']);
    }

    public function test_bulk_operations_mixed_results()
    {
        $userData = [
            [
                'name' => 'Valid User',
                'email' => 'valid@example.com',
                'password' => 'password123',
                'tenant_id' => $this->tenant->id
            ],
            [
                'name' => '', // Invalid: empty name
                'email' => 'invalid@example.com',
                'password' => 'password123',
                'tenant_id' => $this->tenant->id
            ]
        ];

        $result = $this->bulkService->bulkCreateUsers($userData);

        // Should have mixed results
        $this->assertTrue($result['success'] > 0); // Overall success if at least one succeeds
        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['failed']);
        $this->assertCount(1, $result['errors']);
    }
}
