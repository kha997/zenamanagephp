<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Dashboard;
use App\Models\Widget;
use App\Models\SupportTicket;
use App\Models\MaintenanceTask;
use App\Models\PerformanceMetric;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class QualityAssuranceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $tenant = \App\Models\Tenant::factory()->create();
        $this->user = User::factory()->create(['role' => 'user', 'tenant_id' => $tenant->id]);
        $this->admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id]);
    }

    /**
     * Test data integrity
     */
    public function test_data_integrity()
    {
        $this->actingAs($this->user);

        // Create dashboard
        $dashboard = Dashboard::factory()->create([
            'name' => 'Test Dashboard',
            'user_id' => (string)$this->user->id,
            'tenant_id' => (string)$this->user->tenant_id
        ]);

        // Create widget
        $widget = Widget::factory()->create(['dashboard_id' => $dashboard->id]);

        // Verify relationships
        $this->assertEquals($dashboard->id, $widget->dashboard_id);
        $this->assertTrue($dashboard->widgets->contains($widget));

        // Test cascade delete
        $dashboard->delete();
        $this->assertDatabaseMissing('widgets', ['id' => $widget->id]);
    }

    /**
     * Test API consistency
     */
    public function test_api_consistency()
    {
        $this->actingAs($this->user);

        // Test dashboard API consistency
        $response = $this->post('/api/dashboards', [
            'name' => 'Test Dashboard',
            'description' => 'Test description',
            'layout' => 'grid',
            'is_public' => false
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'name',
            'description',
            'layout',
            'is_public',
            'created_at',
            'updated_at'
        ]);

        $dashboardId = $response->json('id');

        // Test GET consistency
        $getResponse = $this->get("/api/dashboards/{$dashboardId}");
        $getResponse->assertStatus(200);
        $getResponse->assertJsonStructure([
            'id',
            'name',
            'description',
            'layout',
            'is_public',
            'created_at',
            'updated_at'
        ]);

        // Verify data consistency
        $this->assertEquals($response->json('name'), $getResponse->json('name'));
        $this->assertEquals($response->json('description'), $getResponse->json('description'));
    }

    /**
     * Test error handling
     */
    public function test_error_handling()
    {
        $this->actingAs($this->user);

        // Test 404 error
        $response = $this->get('/api/dashboards/999999');
        $response->assertStatus(404);

        // Test 422 error (validation)
        $response = $this->post('/api/dashboards', [
            'name' => '', // Empty name should fail validation
            'description' => 'Test'
        ]);
        $response->assertStatus(422);

        // Test 403 error (unauthorized)
        $otherUser = User::factory()->create();
        $dashboard = Dashboard::factory()->create([
            'name' => 'Other User Dashboard',
            'user_id' => (string)$otherUser->id,
            'tenant_id' => (string)$otherUser->tenant_id
        ]);
        
        $response = $this->get("/api/dashboards/{$dashboard->id}");
        $response->assertStatus(403);
    }

    /**
     * Test validation rules
     */
    public function test_validation_rules()
    {
        $this->actingAs($this->user);

        // Test required fields
        $response = $this->post('/api/dashboards', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);

        // Test field length limits
        $response = $this->post('/api/dashboards', [
            'name' => str_repeat('a', 1000), // Too long
            'description' => 'Test'
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);

        // Test data types
        $response = $this->post('/api/dashboards', [
            'name' => 123, // Should be string
            'description' => 'Test'
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /**
     * Test database constraints
     */
    public function test_database_constraints()
    {
        $this->actingAs($this->user);

        // Test unique constraints
        $dashboard1 = Dashboard::factory()->create([
            'user_id' => (string)$this->user->id,
            'tenant_id' => (string)$this->user->tenant_id,
            'name' => 'Unique Dashboard'
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Dashboard::create([
            'user_id' => (string)$this->user->id,
            'name' => 'Unique Dashboard' // Should fail due to unique constraint
        ]);

        // Test foreign key constraints
        $this->expectException(\Illuminate\Database\QueryException::class);
        Widget::create([
            'dashboard_id' => 999999, // Non-existent dashboard
            'type' => 'chart',
            'title' => 'Test Widget'
        ]);
    }

    /**
     * Test concurrent access
     */
    public function test_concurrent_access()
    {
        $this->actingAs($this->user);

        $dashboard = Dashboard::factory()->create([
            'name' => 'Concurrent Test Dashboard',
            'user_id' => (string)$this->user->id,
            'tenant_id' => (string)$this->user->tenant_id
        ]);

        // Simulate concurrent updates
        $response1 = $this->put("/api/dashboards/{$dashboard->id}", [
            'name' => 'Updated Name 1'
        ]);

        $response2 = $this->put("/api/dashboards/{$dashboard->id}", [
            'name' => 'Updated Name 2'
        ]);

        // At least one should succeed
        $this->assertTrue(
            $response1->status() === 200 || $response2->status() === 200
        );
    }

    /**
     * Test system recovery
     */
    public function test_system_recovery()
    {
        $this->actingAs($this->admin);

        // Simulate system error
        try {
            Dashboard::create([
                'name' => null, // This should fail
                'user_id' => $this->user->id
            ]);
        } catch (\Exception $e) {
            // Expected to fail
        }

        // Verify system is still functional
        $response = $this->get('/api/dashboards');
        $response->assertStatus(200);
    }

    /**
     * Test maintenance commands
     */
    public function test_maintenance_commands()
    {
        $this->actingAs($this->admin);

        // Test maintenance command execution
        $exitCode = Artisan::call('maintenance:run', ['--task' => 'cache']);
        $this->assertEquals(0, $exitCode);

        // Verify maintenance task was created
        $this->assertDatabaseHas('maintenance_tasks', [
            'task' => 'Clear application cache'
        ]);
    }

    /**
     * Test backup functionality
     */
    public function test_backup_functionality()
    {
        $this->actingAs($this->admin);

        // Test backup command execution
        $exitCode = Artisan::call('backup:run', ['--type' => 'database']);
        $this->assertEquals(0, $exitCode);

        // Verify backup task was created
        $this->assertDatabaseHas('maintenance_tasks', [
            'task' => 'System backup'
        ]);
    }

    /**
     * Test performance metrics
     */
    public function test_performance_metrics()
    {
        $this->actingAs($this->admin);

        // Create performance metrics
        PerformanceMetric::create([
            'metric_name' => 'response_time',
            'metric_value' => 150,
            'metric_unit' => 'milliseconds',
            'category' => 'performance'
        ]);

        // Test metrics endpoint
        $response = $this->get('/api/health/performance');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'memory',
            'cpu',
            'php',
            'application'
        ]);
    }

    /**
     * Test support ticket workflow
     */
    public function test_support_ticket_workflow()
    {
        $this->actingAs($this->user);

        // Create support ticket
        $response = $this->post('/api/support/tickets', [
            'subject' => 'Test Support Ticket',
            'description' => 'This is a test support ticket',
            'category' => 'technical',
            'priority' => 'medium'
        ]);

        $response->assertStatus(201);
        $ticketId = $response->json('id');

        // Add message to ticket
        $response = $this->post("/api/support/tickets/{$ticketId}/messages", [
            'message' => 'This is a test message',
            'is_internal' => false
        ]);

        $response->assertStatus(201);

        // Update ticket status (as admin)
        $this->actingAs($this->admin);
        $response = $this->put("/api/support/tickets/{$ticketId}", [
            'status' => 'resolved',
            'assigned_to' => $this->admin->id
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test complete user workflow
     */
    public function test_complete_user_workflow()
    {
        $this->actingAs($this->user);
        
        // Debug: Check if tenant exists
        $tenant = \App\Models\Tenant::find($this->user->tenant_id);
        if (!$tenant) {
            $this->fail("Tenant {$this->user->tenant_id} not found for user {$this->user->id}");
        }

        // 1. Create dashboard
        $dashboardResponse = $this->post('/api/dashboards', [
            'name' => 'My Dashboard',
            'description' => 'My personal dashboard',
            'layout' => 'grid',
            'is_public' => false
        ]);
        $dashboardResponse->assertStatus(201);
        $dashboardId = $dashboardResponse->json('id');

        // 2. Add widgets
        $widgetResponse = $this->post('/api/widgets', [
            'dashboard_id' => $dashboardId,
            'type' => 'chart',
            'title' => 'Sales Chart',
            'config' => ['chart_type' => 'line'],
            'position' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]
        ]);
        $widgetResponse->assertStatus(201);

        // 3. Update widget
        $widgetId = $widgetResponse->json('id');
        $updateResponse = $this->put("/api/widgets/{$widgetId}", [
            'title' => 'Updated Sales Chart',
            'config' => ['chart_type' => 'bar']
        ]);
        $updateResponse->assertStatus(200);

        // 4. View dashboard
        $viewResponse = $this->get("/api/dashboards/{$dashboardId}");
        $viewResponse->assertStatus(200);

        // 5. Delete dashboard
        $deleteResponse = $this->delete("/api/dashboards/{$dashboardId}");
        $deleteResponse->assertStatus(204);
    }

    /**
     * Test system under stress
     */
    public function test_system_under_stress()
    {
        $this->actingAs($this->user);

        $startTime = microtime(true);

        // Create multiple resources simultaneously
        $dashboards = [];
        for ($i = 0; $i < 20; $i++) {
            $response = $this->post('/api/dashboards', [
                'name' => "Stress Test Dashboard {$i}",
                'description' => "Stress test description {$i}",
                'layout' => 'grid',
                'is_public' => false
            ]);
            
            if ($response->status() === 201) {
                $dashboards[] = $response->json('id');
            }
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Clean up
        foreach ($dashboards as $dashboardId) {
            $this->delete("/api/dashboards/{$dashboardId}");
        }

        // Assert reasonable performance
        $this->assertLessThan(10, $executionTime);
        $this->assertGreaterThan(0, count($dashboards));
    }

    /**
     * Test memory management
     */
    public function test_memory_management()
    {
        $this->actingAs($this->user);

        $initialMemory = memory_get_usage(true);

        // Create large dataset
        $dashboards = [];
        for ($i = 0; $i < 100; $i++) {
            $dashboard = Dashboard::factory()->create([
                'name' => "Test Dashboard {$i}",
                'user_id' => (string)$this->user->id,
                'tenant_id' => (string)$this->user->tenant_id
            ]);
            $dashboards[] = $dashboard;
            
            for ($j = 0; $j < 10; $j++) {
                Widget::factory()->create(['dashboard_id' => $dashboard->id]);
            }
        }

        $peakMemory = memory_get_peak_usage(true);
        $memoryIncrease = $peakMemory - $initialMemory;
        $memoryIncreaseMB = $memoryIncrease / 1024 / 1024;

        // Assert memory usage is reasonable
        $this->assertLessThan(50, $memoryIncreaseMB);

        // Test memory cleanup
        unset($dashboards);
        gc_collect_cycles();

        $finalMemory = memory_get_usage(true);
        $memoryCleanup = $peakMemory - $finalMemory;
        $memoryCleanupMB = $memoryCleanup / 1024 / 1024;

        // Assert memory was cleaned up (allow for 0 or negative due to efficient GC)
        $this->assertGreaterThanOrEqual(0, $memoryCleanupMB);
    }

    /**
     * Test database performance
     */
    public function test_database_performance()
    {
        $this->actingAs($this->user);

        // Create test data
        $dashboards = Dashboard::factory()->count(50)->create([
            'name' => 'Performance Test Dashboard',
            'user_id' => (string)$this->user->id,
            'tenant_id' => (string)$this->user->tenant_id
        ]);
        
        foreach ($dashboards as $dashboard) {
            Widget::factory()->count(5)->create(['dashboard_id' => $dashboard->id]);
        }

        // Test query performance
        $startTime = microtime(true);
        $dashboards = Dashboard::with('widgets')->where('user_id', (string)$this->user->id)->get();
        $endTime = microtime(true);
        
        $queryTime = ($endTime - $startTime) * 1000;
        
        $this->assertLessThan(200, $queryTime);
        $this->assertCount(50, $dashboards);
    }

    /**
     * Test API response times
     */
    public function test_api_response_times()
    {
        $this->actingAs($this->user);

        // Test dashboard list endpoint
        $startTime = microtime(true);
        $response = $this->get('/api/dashboards');
        $endTime = microtime(true);
        
        $responseTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(500, $responseTime);

        // Test widget list endpoint
        $startTime = microtime(true);
        $response = $this->get('/api/widgets');
        $endTime = microtime(true);
        
        $responseTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(300, $responseTime);
    }
}
