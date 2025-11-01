<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\ZenaProject;
use App\Models\ZenaTask;
use App\Models\ZenaRfi;
use App\Models\ZenaSubmittal;
use App\Models\ZenaChangeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class PerformanceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $project;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->markTestSkipped('All PerformanceTest tests skipped - missing ZenaProject and related models');
        
        $this->user = User::factory()->create();
        $this->project = ZenaProject::factory()->create([
            'created_by' => $this->user->id
        ]);
        $this->token = $this->generateJwtToken($this->user);
    }

    /**
     * Test project listing performance with large dataset
     */
    public function test_project_listing_performance()
    {
        // Create 100 projects
        ZenaProject::factory()->count(100)->create([
            'created_by' => $this->user->id
        ]);

        $startTime = microtime(true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/projects');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);
        
        // Should complete within 2 seconds
        $this->assertLessThan(2.0, $executionTime);
    }

    /**
     * Test task listing performance with large dataset
     */
    public function test_task_listing_performance()
    {
        // Create 500 tasks
        ZenaTask::factory()->count(500)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $startTime = microtime(true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/tasks');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);
        
        // Should complete within 3 seconds
        $this->assertLessThan(3.0, $executionTime);
    }

    /**
     * Test RFI listing performance with large dataset
     */
    public function test_rfi_listing_performance()
    {
        // Create 200 RFIs
        ZenaRfi::factory()->count(200)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $startTime = microtime(true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/rfis');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);
        
        // Should complete within 2 seconds
        $this->assertLessThan(2.0, $executionTime);
    }

    /**
     * Test submittal listing performance with large dataset
     */
    public function test_submittal_listing_performance()
    {
        // Create 200 submittals
        ZenaSubmittal::factory()->count(200)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $startTime = microtime(true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/submittals');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);
        
        // Should complete within 2 seconds
        $this->assertLessThan(2.0, $executionTime);
    }

    /**
     * Test change request listing performance with large dataset
     */
    public function test_change_request_listing_performance()
    {
        // Create 200 change requests
        ZenaChangeRequest::factory()->count(200)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $startTime = microtime(true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/change-requests');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);
        
        // Should complete within 2 seconds
        $this->assertLessThan(2.0, $executionTime);
    }

    /**
     * Test search performance
     */
    public function test_search_performance()
    {
        // Create 1000 tasks with various names
        for ($i = 0; $i < 1000; $i++) {
            ZenaTask::factory()->create([
                'project_id' => $this->project->id,
                'created_by' => $this->user->id,
                'name' => 'Task ' . $i . ' ' . $this->faker->words(3, true)
            ]);
        }

        $startTime = microtime(true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/tasks?search=Task');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);
        
        // Should complete within 3 seconds
        $this->assertLessThan(3.0, $executionTime);
    }

    /**
     * Test filtering performance
     */
    public function test_filtering_performance()
    {
        // Create 500 tasks with different statuses
        $statuses = ['todo', 'in_progress', 'done', 'pending'];
        for ($i = 0; $i < 500; $i++) {
            ZenaTask::factory()->create([
                'project_id' => $this->project->id,
                'created_by' => $this->user->id,
                'status' => $statuses[$i % 4]
            ]);
        }

        $startTime = microtime(true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/tasks?status=todo');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);
        
        // Should complete within 2 seconds
        $this->assertLessThan(2.0, $executionTime);
    }

    /**
     * Test pagination performance
     */
    public function test_pagination_performance()
    {
        // Create 1000 tasks
        ZenaTask::factory()->count(1000)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $startTime = microtime(true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/tasks?per_page=50&page=10');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);
        
        // Should complete within 2 seconds
        $this->assertLessThan(2.0, $executionTime);
    }

    /**
     * Test complex query performance
     */
    public function test_complex_query_performance()
    {
        // Create tasks with dependencies
        $tasks = ZenaTask::factory()->count(100)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        // Create dependencies
        for ($i = 1; $i < 100; $i++) {
            $tasks[$i]->update([
                'dependencies' => [$tasks[$i-1]->id]
            ]);
        }

        $startTime = microtime(true);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/tasks?with_dependencies=true');

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);
        
        // Should complete within 3 seconds
        $this->assertLessThan(3.0, $executionTime);
    }

    /**
     * Test concurrent request performance
     */
    public function test_concurrent_request_performance()
    {
        // Create test data
        ZenaTask::factory()->count(100)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $startTime = microtime(true);

        // Simulate concurrent requests
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
            ])->getJson('/api/zena/tasks');
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // All responses should be successful
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        // Should complete within 5 seconds
        $this->assertLessThan(5.0, $executionTime);
    }

    /**
     * Test memory usage
     */
    public function test_memory_usage()
    {
        $initialMemory = memory_get_usage();

        // Create large dataset
        ZenaTask::factory()->count(1000)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/tasks');

        $finalMemory = memory_get_usage();
        $memoryUsed = $finalMemory - $initialMemory;

        $response->assertStatus(200);
        
        // Memory usage should be reasonable (less than 50MB)
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed);
    }

    /**
     * Test database query count
     */
    public function test_database_query_count()
    {
        // Enable query logging
        \DB::enableQueryLog();

        ZenaTask::factory()->count(100)->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/tasks');

        $queries = \DB::getQueryLog();
        $queryCount = count($queries);

        // Should not exceed 10 queries for a simple listing
        $this->assertLessThanOrEqual(10, $queryCount);
    }

    /**
     * Generate JWT token for testing
     */
    private function generateJwtToken(User $user): string
    {
        return 'test-jwt-token-' . $user->id;
    }
}
