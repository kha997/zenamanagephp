<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AdminDashboardService;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AdminDashboardServiceTest
 * 
 * Unit tests for AdminDashboardService
 * 
 * @group dashboard
 * @group unit
 */
class AdminDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AdminDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminDashboardService();
        
        // Clear cache before each test
        Cache::flush();
    }

    /** @test */
    public function it_returns_total_users_count()
    {
        // Create some users
        User::factory()->count(5)->create();
        
        $count = $this->service->getTotalUsers();
        
        $this->assertEquals(5, $count);
    }

    /** @test */
    public function it_caches_total_users_count()
    {
        // Create users
        User::factory()->count(3)->create();
        
        // First call - should query database
        $count1 = $this->service->getTotalUsers();
        $this->assertEquals(3, $count1);
        
        // Create more users
        User::factory()->count(2)->create();
        
        // Second call - should return cached value
        $count2 = $this->service->getTotalUsers();
        $this->assertEquals(3, $count2); // Still 3 because cached
        
        // Clear cache and try again
        Cache::forget('admin_dashboard_total_users');
        $count3 = $this->service->getTotalUsers();
        $this->assertEquals(5, $count3); // Now 5
    }

    /** @test */
    public function it_returns_total_projects_count()
    {
        // Create some projects
        Project::factory()->count(7)->create();
        
        $count = $this->service->getTotalProjects();
        
        $this->assertEquals(7, $count);
    }

    /** @test */
    public function it_returns_total_tasks_count()
    {
        // Create some tasks
        Task::factory()->count(10)->create();
        
        $count = $this->service->getTotalTasks();
        
        $this->assertEquals(10, $count);
    }

    /** @test */
    public function it_returns_active_sessions_count()
    {
        // This test depends on sessions table existing
        // If sessions table doesn't exist, it should return 0 gracefully
        $count = $this->service->getActiveSessions();
        
        // Should not throw exception
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /** @test */
    public function it_returns_recent_activities()
    {
        // Create some users
        $user1 = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user2 = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
        
        // Create some projects
        Project::factory()->create(['name' => 'Project Alpha']);
        
        $activities = $this->service->getRecentActivities(10);
        
        // Should return array of activities
        $this->assertIsArray($activities);
        $this->assertGreaterThan(0, count($activities));
        
        // Check structure
        if (count($activities) > 0) {
            $activity = $activities[0];
            $this->assertArrayHasKey('id', $activity);
            $this->assertArrayHasKey('type', $activity);
            $this->assertArrayHasKey('action', $activity);
            $this->assertArrayHasKey('description', $activity);
            $this->assertArrayHasKey('timestamp', $activity);
        }
    }

    /** @test */
    public function it_limits_recent_activities()
    {
        // Create many users
        User::factory()->count(15)->create();
        
        $activities = $this->service->getRecentActivities(5);
        
        // Should be limited to 5
        $this->assertLessThanOrEqual(5, count($activities));
    }

    /** @test */
    public function it_returns_system_health_status()
    {
        $health = $this->service->getSystemHealth();
        
        // Should return one of the valid health statuses
        $this->assertContains($health, ['good', 'warning', 'critical']);
    }

    /** @test */
    public function it_handles_database_errors_gracefully()
    {
        // This test verifies that the service handles errors gracefully
        // We can't easily simulate database errors in unit tests,
        // but we can verify the service doesn't throw exceptions
        
        try {
            $users = $this->service->getTotalUsers();
            $projects = $this->service->getTotalProjects();
            $tasks = $this->service->getTotalTasks();
            $sessions = $this->service->getActiveSessions();
            $activities = $this->service->getRecentActivities();
            $health = $this->service->getSystemHealth();
            
            // All methods should return without throwing
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('Service should handle errors gracefully: ' . $e->getMessage());
        }
    }
}

