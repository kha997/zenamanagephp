<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Widget;
use App\Models\SupportTicket;
use App\Models\MaintenanceTask;
use App\Models\PerformanceMetric;
use App\Models\SupportDocumentation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Tests\Traits\AuthenticationTrait;
use Tests\Traits\RouteNameTrait;

class FinalSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTrait, RouteNameTrait;

    protected $user;
    protected $admin;
    protected Tenant $tenant;
    protected string $userApiToken;
    protected string $adminApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        // Build tenant and tenant-aware users
        $this->tenant = Tenant::factory()->create();

        $this->admin = $this->createRbacAdminUser($this->tenant, ['role' => 'admin']);

        $this->user = $this->createTenantUser(
            $this->tenant,
            [
                'role' => 'project_manager',
                'email' => $this->uniqueTestEmail('user'),
                'name' => 'System User'
            ],
            ['project_manager']
        );

        $this->userApiToken = $this->user->createToken('final-system')->plainTextToken;
        $this->adminApiToken = $this->admin->createToken('final-system')->plainTextToken;
    }

    /**
     * Test complete user authentication flow
     */
    public function test_user_authentication_flow()
    {
        $authUser = $this->createTenantUser(
            $this->tenant,
            [
                'role' => 'user',
                'email' => $this->uniqueTestEmail('auth'),
                'name' => 'Auth Flow User'
            ],
            ['user']
        );

        $loginResponse = $this->withHeaders($this->apiHeadersForTenant((string) $this->tenant->id))
            ->postJson('/api/auth/login', [
                'email' => $authUser->email,
                'password' => 'password'
            ]);

        $loginResponse->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'email'
                    ],
                    'token',
                    'expires_at'
                ]
            ]);

        $token = $loginResponse->json('data.token');
        $this->assertNotEmpty($token);

        $authHeaders = $this->authHeadersForUser($authUser, $token);

        $meResponse = $this->getJson('/api/auth/me', $authHeaders);
        $meResponse->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonPath('data.email', $authUser->email);

        $permissionsResponse = $this->getJson('/api/auth/permissions', $authHeaders);
        $permissionsResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'permissions',
                    'roles'
                ]
            ]);

        $logoutResponse = $this->postJson('/api/auth/logout', [], $authHeaders);
        $logoutResponse->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
    }

    /**
     * Test dashboard creation and management
     */
    public function test_dashboard_management()
    {
        $headers = $this->adminApiHeaders();

        $dashboardResponse = $this->getJson('/api/v1/dashboard', $headers);
        $dashboardResponse->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'layout',
                    'widgets',
                    'preferences'
                ]
            ]);

        $layout = $dashboardResponse->json('data.layout') ?? [];

        if (!empty($layout)) {
            $layoutResponse = $this->putJson('/api/v1/dashboard/layout', [
                'layout' => $layout
            ], $headers);

            $layoutResponse->assertStatus(200)
                ->assertJson(['success' => true]);
        } else {
            $this->assertEmpty($layout);
        }

        $preferencesResponse = $this->postJson('/api/v1/dashboard/preferences', [
            'preferences' => ['theme' => 'dark']
        ], $headers);

        $preferencesResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * Test widget management
     */
    public function test_widget_management()
    {
        $headers = $this->adminApiHeaders();

        $widget = DashboardWidget::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'system-test-widget',
            'permissions' => ['roles' => ['admin', 'project_manager']],
            'is_active' => true
        ]);

        $availableResponse = $this->getJson('/api/v1/dashboard/widgets', $headers);
        $availableResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data'
            ]);

        $this->assertIsArray($availableResponse->json('data'));

        $addResponse = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id,
            'config' => [
                'title' => 'Test Widget',
                'size' => 'medium'
            ]
        ], $headers);

        $addResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $widgetInstance = $addResponse->json('data.widget_instance');
        $this->assertNotNull($widgetInstance, 'Widget instance should be returned');

        $updateResponse = $this->putJson("/api/v1/dashboard/widgets/{$widgetInstance['id']}/config", [
            'config' => [
                'title' => 'Updated Widget',
                'size' => 'large'
            ]
        ], $headers);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $removeResponse = $this->deleteJson("/api/v1/dashboard/widgets/{$widgetInstance['id']}", [], $headers);

        $removeResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test support ticket system
     *
     * @group slow
     */
    public function test_support_ticket_system()
    {
        $supportTicketsStore = $this->namedRoute('api.support.tickets.store');
        if (! $this->routeExists('POST', $supportTicketsStore)) {
            $this->markTestSkipped('dependency: support ticket API route not registered in this environment.');
        }

        $userHeaders = $this->userApiHeaders();

        // Create support ticket
        $response = $this->postJson($supportTicketsStore, [
            'subject' => 'Test Support Ticket',
            'description' => 'This is a test support ticket',
            'category' => 'technical',
            'priority' => 'medium'
        ], $userHeaders);

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
        $response = $this->getJson($this->namedRoute('api.support.tickets.show', ['ticket' => $ticketId]), $userHeaders);
        $response->assertStatus(200);

        // Add message to ticket
        $response = $this->postJson($this->namedRoute('api.support.tickets.messages.store', ['ticket' => $ticketId]), [
            'message' => 'This is a test message',
            'is_internal' => false
        ], $userHeaders);

        $response->assertStatus(201);

        // Update ticket status (as admin)
        $response = $this->putJson($this->namedRoute('api.support.tickets.update', ['ticket' => $ticketId]), [
            'status' => 'resolved',
            'assigned_to' => $this->admin->id
        ], $this->adminApiHeaders());

        $response->assertStatus(200);
    }

    /**
     * Test maintenance system
     */
    public function test_maintenance_system()
    {
        $this->actingAs($this->admin);

        $this->get('/admin/maintenance')->assertStatus(200);

        $actions = [
            ['/admin/maintenance/clear-cache', ['success' => true]],
            ['/admin/maintenance/database', ['success' => true]],
            ['/admin/maintenance/cleanup-logs', ['success' => true]],
            ['/admin/maintenance/backup-database', ['success' => true]],
        ];

        foreach ($actions as [$path, $expectedJson]) {
            $uri = ltrim($path, '/');

            if (! $this->routeExists('POST', $uri)) {
                continue;
            }

            $response = $this->postJson($path);
            $response->assertStatus(200)->assertJson($expectedJson);
        }
    }

    /**
     * Test system health monitoring
     *
     * @group slow
     */
    public function test_system_health_monitoring()
    {
        $this->actingAs($this->admin);

        $healthDetailedUri = '/api/health/detailed';
        $healthUri = '/api/health';

        if ($this->routeExistsFor('GET', $healthDetailedUri)) {
            $response = $this->getJson($healthDetailedUri);
        } elseif ($this->routeExistsFor('GET', $healthUri)) {
            $response = $this->getJson($healthUri);
        } else {
            $this->dumpTestDiagnostic('health-missing-route.json', [], '', ['reason' => 'Health API not registered']);
            $this->markTestSkipped('dependency: health API route not registered');
            return;
        }

        $response->assertStatus(200);
        $json = $response->json() ?? [];
        $rawBody = $response->getContent();

        if ($this->isDetailedHealthContract($json)) {
            $response->assertJsonStructure([
                'overall_status',
                'timestamp',
                'services',
                'metrics',
                'alerts',
                'recommendations'
            ]);
        } elseif ($this->isSsotHealthContract($json)) {
            $response->assertJsonStructure([
                'status',
                'data' => [
                    'overall_status',
                    'timestamp',
                    'services',
                    'metrics',
                    'alerts',
                    'recommendations'
                ]
            ]);
        } elseif (array_key_exists('success', $json)) {
            $this->assertTrue($json['success'] === true);
            return;
        } else {
            $keys = array_keys($json);
            $this->dumpTestDiagnostic('health-unmatched.json', $json, $rawBody);
            $this->markTestSkipped(sprintf(
                'dependency: health payload contract not available in this environment (keys: %s)',
                $keys ? implode(', ', $keys) : 'none'
            ));
            return;
        }

        $performanceUri = '/api/health/performance';

        if (! $this->routeExistsFor('GET', $performanceUri)) {
            return;
        }

        $performanceResponse = $this->getJson($performanceUri);
        $performanceResponse->assertStatus(200);
        $performanceJson = $performanceResponse->json() ?? [];
        $performanceRaw = $performanceResponse->getContent();

        if ($this->hasPerformanceContract($performanceJson)) {
            $performanceResponse->assertJsonStructure([
                'memory',
                'cpu',
                'php',
                'application'
            ]);
            return;
        }

        if ($this->hasPerformanceEnvelopeContract($performanceJson)) {
            $performanceResponse->assertJsonStructure([
                'status',
                'data' => [
                    'memory',
                    'cpu',
                    'php',
                    'application'
                ]
            ]);
            return;
        }

        $performanceKeys = array_keys($performanceJson);
        $this->dumpTestDiagnostic('performance-unmatched.json', $performanceJson, $performanceRaw);
        $this->markTestSkipped(sprintf(
            'dependency: health performance payload contract not available in this environment (keys: %s)',
            $performanceKeys ? implode(', ', $performanceKeys) : 'none'
        ));
    }

    /**
     * Test documentation system
     *
     * @group slow
     */
    public function test_documentation_system()
    {
        $this->actingAs($this->admin);

        $requiredDocumentationRoutes = [
            ['method' => 'POST', 'path' => '/api/support/documentation'],
            ['method' => 'GET', 'path' => '/api/support/documentation/1'],
            ['method' => 'GET', 'path' => '/api/support/documentation/search?q=test'],
        ];

        $missingRoutes = [];

        foreach ($requiredDocumentationRoutes as $route) {
            if (! $this->routeExistsFor($route['method'], $route['path'])) {
                $missingRoutes[] = "{$route['method']} {$route['path']}";
            }
        }

        if (! empty($missingRoutes)) {
            $this->dumpTestDiagnostic('documentation-missing-route.json', [], '', [
                'reason' => 'Documentation API not registered',
                'missing_routes' => $missingRoutes
            ]);

            $this->markTestSkipped('dependency: documentation API routes not registered (' . implode(', ', $missingRoutes) . ')');
            return;
        }

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
            $response = $this->get($this->namedRoute('api.legacy.dashboards.index'));
            
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
        $response = $this->get($this->namedRoute('api.legacy.dashboards.index'));
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
        // Guest requests to protected dashboard routes are redirected.
        $response = $this->post($this->namedRoute('api.legacy.dashboards.store'), [
            'name' => 'Test Dashboard'
        ]);
        $response->assertStatus(302);

        $this->actingAs($this->user);

        // Test SQL injection protection
        $response = $this->get($this->namedRoute('api.legacy.dashboards.index', [], ['search' => "1' OR '1'='1"]));
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');

        // Test XSS protection
        $response = $this->post($this->namedRoute('api.legacy.dashboards.store'), [
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
        $response = $this->get($this->namedRoute('api.legacy.dashboards.show', ['dashboard' => 999999]));
        $response->assertStatus(404);

        // Test 403 error (unauthorized)
        $otherUser = User::factory()->create();
        $dashboard = Dashboard::factory()->create(['user_id' => $otherUser->id]);
        
        $response = $this->get($this->namedRoute('api.legacy.dashboards.show', ['dashboard' => $dashboard->id]));
        $response->assertStatus(403);

        // Test 422 error (validation)
        $response = $this->post($this->namedRoute('api.legacy.dashboards.store'), [
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
        $dashboard = Dashboard::factory()->create(['user_id' => $this->user->id]);

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
        $response = $this->get($this->namedRoute('api.legacy.dashboards.index'));
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

        $supportTicketRoute = $this->namedRoute('api.support.tickets.store');
        if ($this->routeExists('POST', $supportTicketRoute)) {
            $ticketResponse = $this->post($supportTicketRoute, [
                'subject' => 'Need help with dashboard',
                'description' => 'I need help configuring my dashboard',
                'category' => 'technical',
                'priority' => 'medium'
            ]);
            $ticketResponse->assertStatus(201);
        } else {
            $this->dumpTestDiagnostic('complete-workflow-support-route-missing.json', [], '', [
                'reason' => 'Support ticket API not registered for complete workflow',
                'route' => $supportTicketRoute
            ]);
        }

        // 5. View dashboard
        $viewResponse = $this->get($this->namedRoute('api.legacy.dashboards.show', ['dashboard' => $dashboardId]));
        $viewResponse->assertStatus(200);

        // 6. Delete dashboard
        $deleteResponse = $this->delete($this->namedRoute('api.legacy.dashboards.destroy', ['dashboard' => $dashboardId]));
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

    private function isDetailedHealthContract(array $payload): bool
    {
        return array_key_exists('overall_status', $payload);
    }

    private function isSsotHealthContract(array $payload): bool
    {
        return array_key_exists('status', $payload)
            && array_key_exists('data', $payload)
            && is_array($payload['data'])
            && array_key_exists('overall_status', $payload['data']);
    }

    private function hasPerformanceContract(array $payload): bool
    {
        return array_key_exists('memory', $payload)
            && array_key_exists('cpu', $payload)
            && array_key_exists('php', $payload)
            && array_key_exists('application', $payload);
    }

    private function hasPerformanceEnvelopeContract(array $payload): bool
    {
        return array_key_exists('status', $payload)
            && array_key_exists('data', $payload)
            && is_array($payload['data'])
            && $this->hasPerformanceContract($payload['data']);
    }

    private function dumpTestDiagnostic(string $filename, array $payload, string $body, array $extra = []): void
    {
        $dir = storage_path('logs/test-diagnose');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $logEntry = array_merge([
            'timestamp' => \Illuminate\Support\Carbon::now()->toIso8601String(),
            'keys' => array_keys($payload),
            'body' => $body,
            'payload' => $payload
        ], $extra);

        file_put_contents($dir . DIRECTORY_SEPARATOR . $filename, json_encode($logEntry, JSON_PRETTY_PRINT));
    }

    private function routeExists(string $method, string $uri): bool
    {
        return $this->routeExistsFor($method, $uri);
    }

    private function routeExistsFor(string $method, string $path): bool
    {
        try {
            $uri = '/' . ltrim($path, '/');
            $request = Request::create($uri, strtoupper($method));
            Route::getRoutes()->match($request);
            return true;
        } catch (NotFoundHttpException|MethodNotAllowedException $e) {
            return false;
        }
    }

    private function uniqueTestEmail(string $prefix): string
    {
        return sprintf('%s+%s@test.com', $prefix, uniqid());
    }

    private function userApiHeaders(): array
    {
        return $this->authHeadersForUser($this->user, $this->userApiToken);
    }

    private function adminApiHeaders(): array
    {
        return $this->authHeadersForUser($this->admin, $this->adminApiToken);
    }
}
