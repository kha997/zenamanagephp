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
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Traits\RouteNameTrait;

class QualityAssuranceTest extends TestCase
{
    use RefreshDatabase, WithFaker, RouteNameTrait;

    protected $user;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $tenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'role' => 'user',
            'tenant_id' => $tenant->id,
        ]);
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        $this->user->assignRole('client');
        $this->admin->assignRole('admin');
    }

    /**
     * Test data integrity
     */
    public function test_data_integrity()
    {
        $this->actingAs($this->user);

        // Create dashboard
        $dashboard = Dashboard::factory()->create(['user_id' => $this->user->id]);

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
        $response = $this->post($this->namedRoute('api.legacy.dashboards.store'), [
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
        $getResponse = $this->get($this->namedRoute('api.legacy.dashboards.show', ['dashboard' => $dashboardId]));
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
        $response = $this->get($this->namedRoute('api.legacy.dashboards.show', ['dashboard' => 999999]));
        $response->assertStatus(404);

        // Test 422 error (validation)
        $response = $this->post($this->namedRoute('api.legacy.dashboards.store'), [
            'name' => '', // Empty name should fail validation
            'description' => 'Test'
        ]);
        $response->assertStatus(422);

        // Test 403 error (unauthorized)
        $otherUser = User::factory()->create([
            'tenant_id' => $this->user->tenant_id,
        ]);
        $dashboard = Dashboard::factory()->create(['user_id' => $otherUser->id]);
        
        $response = $this->get($this->namedRoute('api.legacy.dashboards.show', ['dashboard' => $dashboard->id]));
        $response->assertStatus(403);
    }

    /**
     * Test validation rules
     */
    public function test_validation_rules()
    {
        $this->actingAs($this->user);

        // Test required fields
        $response = $this->post($this->namedRoute('api.legacy.dashboards.store'), []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);

        // Test field length limits
        $response = $this->post($this->namedRoute('api.legacy.dashboards.store'), [
            'name' => str_repeat('a', 1000), // Too long
            'description' => 'Test'
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);

        // Test data types
        $response = $this->post($this->namedRoute('api.legacy.dashboards.store'), [
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
            'user_id' => $this->user->id,
            'name' => 'Unique Dashboard'
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Dashboard::create([
            'user_id' => $this->user->id,
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

        $dashboard = Dashboard::factory()->create(['user_id' => $this->user->id]);

        // Simulate concurrent updates
        $response1 = $this->put($this->namedRoute('api.legacy.dashboards.update', ['dashboard' => $dashboard->id]), [
            'name' => 'Updated Name 1'
        ]);

        $response2 = $this->put($this->namedRoute('api.legacy.dashboards.update', ['dashboard' => $dashboard->id]), [
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
        $response = $this->get($this->namedRoute('api.legacy.dashboards.index'));
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
        $response = $this->post($this->namedRoute('api.support.tickets.store'), [
            'subject' => 'Test Support Ticket',
            'description' => 'This is a test support ticket',
            'category' => 'technical',
            'priority' => 'medium'
        ]);

        $response->assertStatus(201);
        $ticketId = $response->json('id');

        // Add message to ticket
        $response = $this->post($this->namedRoute('api.support.tickets.messages.store', ['ticket' => $ticketId]), [
            'message' => 'This is a test message',
            'is_internal' => false
        ]);

        $response->assertStatus(201);

        // Update ticket status (as admin)
        $this->actingAs($this->admin);
        $response = $this->put($this->namedRoute('api.support.tickets.update', ['ticket' => $ticketId]), [
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

        // 1. Create dashboard
        $dashboardResponse = $this->post($this->namedRoute('api.legacy.dashboards.store'), [
            'name' => 'My Dashboard',
            'description' => 'My personal dashboard',
            'layout' => 'grid',
            'is_public' => false
        ]);
        $dashboardResponse->assertStatus(201);
        $dashboardId = $dashboardResponse->json('id');

        // 2. Add widgets
        $widgetResponse = $this->post($this->namedRoute('api.legacy.widgets.store'), [
            'dashboard_id' => $dashboardId,
            'type' => 'chart',
            'title' => 'Sales Chart',
            'config' => ['chart_type' => 'line'],
            'position' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]
        ]);
        $widgetResponse->assertStatus(201);

        // 3. Update widget
        $widgetId = $widgetResponse->json('id');
        $updateResponse = $this->put($this->namedRoute('api.legacy.widgets.update', ['widget' => $widgetId]), [
            'title' => 'Updated Sales Chart',
            'config' => ['chart_type' => 'bar']
        ]);
        $updateResponse->assertStatus(200);

        // 4. View dashboard
        $viewResponse = $this->get($this->namedRoute('api.legacy.dashboards.show', ['dashboard' => $dashboardId]));
        $viewResponse->assertStatus(200);

        // 5. Delete dashboard
        $deleteResponse = $this->delete($this->namedRoute('api.legacy.dashboards.destroy', ['dashboard' => $dashboardId]));
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
            $response = $this->post($this->namedRoute('api.legacy.dashboards.store'), [
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
            $this->delete($this->namedRoute('api.legacy.dashboards.destroy', ['dashboard' => $dashboardId]));
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

        DB::disableQueryLog();
        gc_collect_cycles();
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $baseline = memory_get_usage(false);

        // Create large dataset
        for ($i = 0; $i < 100; $i++) {
            $dashboard = Dashboard::factory()->create(['user_id' => $this->user->id]);

            for ($j = 0; $j < 10; $j++) {
                Widget::factory()->create(['dashboard_id' => $dashboard->id]);
            }
        }

        gc_collect_cycles();
        $peak = memory_get_peak_usage(false);
        $deltaMB = ($peak - $baseline) / 1024 / 1024;

        // Assert memory usage is reasonable
        $this->assertLessThan(80, $deltaMB, "Memory delta should not exceed 80MB for 1000 records (delta={$deltaMB}MB)");

        // Test memory cleanup
        gc_collect_cycles();

        $finalMemory = memory_get_usage(false);
        $finalDeltaMB = ($finalMemory - $baseline) / 1024 / 1024;

        // Assert memory remains near baseline after cleanup.
        $this->assertLessThan(20, $finalDeltaMB, "Final memory after cleanup should remain under +20MB from baseline (delta={$finalDeltaMB}MB)");
    }

    /**
     * Test database performance
     */
    public function test_database_performance()
    {
        $this->actingAs($this->user);

        // Create test data
        $dashboards = Dashboard::factory()->count(50)->create(['user_id' => $this->user->id]);
        
        foreach ($dashboards as $dashboard) {
            Widget::factory()->count(5)->create(['dashboard_id' => $dashboard->id]);
        }

        // Test query performance
        $startTime = microtime(true);
        $dashboards = Dashboard::with('widgets')->where('user_id', $this->user->id)->get();
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
        $response = $this->get($this->namedRoute('api.legacy.dashboards.index'));
        $endTime = microtime(true);
        
        $responseTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(500, $responseTime);

        // Test widget list endpoint
        $startTime = microtime(true);
        $response = $this->get('/api/dashboard/widgets');
        $endTime = microtime(true);
        
        $responseTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(300, $responseTime);
    }
}
