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
use App\Models\SupportDocumentation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class FinalSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant first
        $tenant = \App\Models\Tenant::factory()->create();
        
        // Create test users
        $this->user = User::factory()->create([
            'role' => 'user',
            'email' => 'user@test.com',
            'tenant_id' => $tenant->id
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com',
            'tenant_id' => $tenant->id
        ]);

        // Run migrations (already done by RefreshDatabase trait)
        // Artisan::call('migrate:fresh');
    }

    /**
     * Test complete user authentication flow
     */
    public function test_user_authentication_flow()
    {
        // Test registration
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'newuser@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');

        // Test login
        $response = $this->post('/login', [
            'email' => 'newuser@test.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');

        // Test logout
        $response = $this->post('/logout');
        $response->assertStatus(302);
        $response->assertRedirect('/');
    }

    /**
     * Test dashboard creation and management
     */
    public function test_dashboard_management()
    {
        $this->actingAs($this->user);

        // Create dashboard
        $response = $this->post('/api/dashboards', [
            'name' => 'Test Dashboard',
            'description' => 'Test dashboard description',
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

        // Get dashboard
        $response = $this->get("/api/dashboards/{$dashboardId}");
        $response->assertStatus(200);

        // Update dashboard
        $response = $this->put("/api/dashboards/{$dashboardId}", [
            'name' => 'Updated Dashboard',
            'description' => 'Updated description'
        ]);

        $response->assertStatus(200);

        // Delete dashboard
        $response = $this->delete("/api/dashboards/{$dashboardId}");
        $response->assertStatus(204);
    }

    /**
     * Test widget management
     */
    public function test_widget_management()
    {
        $this->actingAs($this->user);

        // Create dashboard first
        $dashboard = Dashboard::factory()->create(['user_id' => $this->user->id]);

        // Create widget
        $response = $this->post('/api/widgets', [
            'dashboard_id' => $dashboard->id,
            'type' => 'chart',
            'title' => 'Test Widget',
            'config' => ['chart_type' => 'line'],
            'position' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'dashboard_id',
            'type',
            'title',
            'config',
            'position',
            'created_at',
            'updated_at'
        ]);

        $widgetId = $response->json('id');

        // Get widget
        $response = $this->get("/api/widgets/{$widgetId}");
        $response->assertStatus(200);

        // Update widget
        $response = $this->put("/api/widgets/{$widgetId}", [
            'title' => 'Updated Widget',
            'config' => ['chart_type' => 'bar']
        ]);

        $response->assertStatus(200);

        // Delete widget
        $response = $this->delete("/api/widgets/{$widgetId}");
        $response->assertStatus(204);
    }

    /**
     * Test support ticket system
     */
    public function test_support_ticket_system()
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
        $response->assertJsonStructure([
            'id',
            'ticket_number',
            'subject',
            'description',
            'category',
            'priority',
            'status',
            'user_id',
            'created_at',
            'updated_at'
        ]);

        $ticketId = $response->json('id');

        // Get ticket
        $response = $this->get("/api/support/tickets/{$ticketId}");
        $response->assertStatus(200);

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
     * Test maintenance system
     */
    public function test_maintenance_system()
    {
        $this->actingAs($this->admin);

        // Test maintenance dashboard access
        $response = $this->get('/admin/maintenance');
        $response->assertStatus(200);

        // Test cache clearing
        $response = $this->post('/admin/maintenance/clear-cache');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Test database maintenance
        $response = $this->post('/admin/maintenance/database');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Test log cleanup
        $response = $this->post('/admin/maintenance/cleanup-logs');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Test backup creation
        $response = $this->post('/admin/maintenance/backup-database');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test system health monitoring
     */
    public function test_system_health_monitoring()
    {
        $this->actingAs($this->admin);

        // Test health check endpoint
        $response = $this->get('/api/health');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'overall_status',
            'timestamp',
            'services',
            'metrics',
            'alerts',
            'recommendations'
        ]);

        // Test performance metrics endpoint
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
     * Test documentation system
     */
    public function test_documentation_system()
    {
        $this->actingAs($this->admin);

        // Create documentation
        $response = $this->post('/api/support/documentation', [
            'title' => 'Test Documentation',
            'content' => 'This is test documentation content',
            'category' => 'getting_started',
            'status' => 'published',
            'tags' => 'test,documentation'
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'title',
            'slug',
            'content',
            'category',
            'status',
            'tags',
            'author_id',
            'created_at',
            'updated_at'
        ]);

        $docId = $response->json('id');

        // Get documentation
        $response = $this->get("/api/support/documentation/{$docId}");
        $response->assertStatus(200);

        // Search documentation
        $response = $this->get('/api/support/documentation/search?q=test');
        $response->assertStatus(200);
    }

    /**
     * Test API rate limiting
     */
    public function test_api_rate_limiting()
    {
        $this->actingAs($this->user);

        // Make multiple requests to test rate limiting
        for ($i = 0; $i < 15; $i++) {
            $response = $this->get('/api/dashboards');
            
            if ($i < 10) {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(429); // Too Many Requests
            }
        }
    }

    /**
     * Test file upload functionality
     */
    public function test_file_upload()
    {
        $this->actingAs($this->user);

        // Create a test file
        $file = \Illuminate\Http\UploadedFile::fake()->create('test.txt', 100);

        // Test file upload
        $response = $this->post('/api/upload', [
            'file' => $file
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'filename',
            'path',
            'size'
        ]);
    }

    /**
     * Test WebSocket functionality
     */
    public function test_websocket_functionality()
    {
        $this->actingAs($this->user);

        // Test WebSocket connection endpoint
        $response = $this->get('/api/websocket/auth');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'token',
            'socket_id',
            'channel'
        ]);
    }

    /**
     * Test backup and restore functionality
     */
    public function test_backup_restore()
    {
        $this->actingAs($this->admin);

        // Test backup creation
        $response = $this->post('/admin/maintenance/backup-database');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify backup file exists
        $backupDir = storage_path('backups');
        $backups = glob($backupDir . '/backup_*.sql');
        $this->assertNotEmpty($backups);
    }

    /**
     * Test performance under load
     */
    public function test_performance_under_load()
    {
        $this->actingAs($this->user);

        $startTime = microtime(true);

        // Create multiple dashboards and widgets
        for ($i = 0; $i < 10; $i++) {
            $dashboard = Dashboard::factory()->create(['user_id' => $this->user->id]);
            
            for ($j = 0; $j < 5; $j++) {
                Widget::factory()->create(['dashboard_id' => $dashboard->id]);
            }
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert that creation time is reasonable (less than 5 seconds)
        $this->assertLessThan(5, $executionTime);

        // Test bulk retrieval
        $startTime = microtime(true);
        $response = $this->get('/api/dashboards');
        $endTime = microtime(true);
        $responseTime = $endTime - $startTime;

        $response->assertStatus(200);
        $this->assertLessThan(1, $responseTime); // Less than 1 second
    }

    /**
     * Test security features
     */
    public function test_security_features()
    {
        // Test CSRF protection
        $response = $this->post('/api/dashboards', [
            'name' => 'Test Dashboard'
        ]);
        $response->assertStatus(419); // CSRF token mismatch

        // Test SQL injection protection
        $response = $this->get('/api/dashboards?search=1\' OR \'1\'=\'1');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');

        // Test XSS protection
        $response = $this->post('/api/dashboards', [
            'name' => '<script>alert("xss")</script>',
            'description' => 'Test'
        ]);
        $response->assertStatus(422); // Validation error
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

        // Test 403 error (unauthorized)
        $otherUser = User::factory()->create();
        $dashboard = Dashboard::factory()->create(['user_id' => $otherUser->id]);
        
        $response = $this->get("/api/dashboards/{$dashboard->id}");
        $response->assertStatus(403);

        // Test 422 error (validation)
        $response = $this->post('/api/dashboards', [
            'name' => '', // Empty name should fail validation
            'description' => 'Test'
        ]);
        $response->assertStatus(422);
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

        // Delete dashboard and verify cascade
        $dashboard->delete();
        $this->assertDatabaseMissing('widgets', ['id' => $widget->id]);
    }

    /**
     * Test concurrent access
     */
    public function test_concurrent_access()
    {
        $this->actingAs($this->user);

        $dashboard = Dashboard::factory()->create(['user_id' => $this->user->id]);

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

        // Simulate system error by creating invalid data
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
     * Test complete user workflow
     */
    public function test_complete_user_workflow()
    {
        $this->actingAs($this->user);

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

        // 4. Create support ticket
        $ticketResponse = $this->post('/api/support/tickets', [
            'subject' => 'Need help with dashboard',
            'description' => 'I need help configuring my dashboard',
            'category' => 'technical',
            'priority' => 'medium'
        ]);
        $ticketResponse->assertStatus(201);

        // 5. View dashboard
        $viewResponse = $this->get("/api/dashboards/{$dashboardId}");
        $viewResponse->assertStatus(200);

        // 6. Delete dashboard
        $deleteResponse = $this->delete("/api/dashboards/{$dashboardId}");
        $deleteResponse->assertStatus(204);
    }

    /**
     * Test system performance metrics
     */
    public function test_system_performance_metrics()
    {
        $this->actingAs($this->admin);

        // Create some performance metrics
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
     * Test maintenance task execution
     */
    public function test_maintenance_task_execution()
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
     * Test backup command execution
     */
    public function test_backup_command_execution()
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
}
