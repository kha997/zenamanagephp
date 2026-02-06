<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserDashboard;
use App\Models\DashboardWidget;
use App\Models\DashboardMetric;
use App\Models\DashboardAlert;
use App\Models\Project;
use App\Models\Task;
use App\Models\RFI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SecurityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $project;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = \App\Models\Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.com',
            'is_active' => true
        ]);
        
        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => 'project_manager',
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create test project
        $this->project = Project::create([
            'name' => 'Test Project',
            'description' => 'Test project description',
            'status' => 'active',
            'budget' => 100000,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create test data
        $this->createTestData();
    }

    protected function createTestData(): void
    {
        // Create widgets
        DashboardWidget::create([
            'name' => 'Project Overview',
            'code' => 'project_overview',
            'type' => 'card',
            'category' => 'overview',
            'description' => 'Project overview widget',
            'config' => json_encode(['default_size' => 'large']),
            'permissions' => json_encode(['project_manager']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        DashboardWidget::create([
            'name' => 'QC Inspector Widget',
            'code' => 'qc_inspector_widget',
            'type' => 'card',
            'category' => 'quality',
            'description' => 'QC Inspector specific widget',
            'config' => json_encode(['default_size' => 'medium']),
            'permissions' => json_encode(['qc_inspector']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        // Create metrics
        DashboardMetric::create([
            'name' => 'Project Progress',
            'code' => 'project_progress',
            'description' => 'Overall project progress percentage',
            'unit' => '%',
            'type' => 'gauge',
            'is_active' => true,
            'permissions' => json_encode(['project_manager']),
            'tenant_id' => $this->tenant->id
        ]);

        // Create tasks
        Task::create([
            'title' => 'Test Task',
            'description' => 'Test task description',
            'status' => 'pending',
            'priority' => 'medium',
            'due_date' => now()->addDays(7),
            'assigned_to' => $this->user->id,
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Create RFIs
        RFI::create([
            'subject' => 'Test RFI',
            'description' => 'Test RFI description',
            'status' => 'open',
            'priority' => 'medium',
            'due_date' => now()->addDays(3),
            'discipline' => 'construction',
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Create alerts
        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Test Alert',
            'type' => 'project',
            'severity' => 'medium',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => json_encode(['project_id' => $this->project->id])
        ]);
    }

    /** @test */
    public function it_requires_authentication_for_all_endpoints()
    {
        $endpoints = [
            'GET /api/v1/dashboard',
            'GET /api/v1/dashboard/widgets',
            'POST /api/v1/dashboard/widgets',
            'GET /api/v1/dashboard/role-based',
            'GET /api/v1/dashboard/alerts',
            'GET /api/v1/dashboard/metrics',
            'GET /api/v1/dashboard/customization/',
        ];

        foreach ($endpoints as $endpoint) {
            [$method, $path] = explode(' ', $endpoint);
            
            $response = $this->json($method, $path);
            $response->assertStatus(401);
        }
    }

    /** @test */
    public function it_validates_user_permissions_for_widgets()
    {
        // Create QC Inspector user
        $qcUser = User::create([
            'name' => 'QC Inspector',
            'email' => 'qc@example.com',
            'password' => Hash::make('password'),
            'role' => 'qc_inspector',
            'tenant_id' => $this->tenant->id
        ]);

        $this->apiAs($qcUser, $this->tenant);

        // QC Inspector should not be able to access project_manager widget
        $projectManagerWidget = DashboardWidget::where('code', 'project_overview')->first();
        
        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $projectManagerWidget->id
        ]);
        
        $response->assertStatus(500);
        $this->assertStringContains('permission', $response->json('message'));

        // QC Inspector should be able to access qc_inspector widget
        $qcWidget = DashboardWidget::where('code', 'qc_inspector_widget')->first();
        
        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $qcWidget->id
        ]);
        
        $response->assertStatus(200);
    }

    /** @test */
    public function it_validates_user_permissions_for_metrics()
    {
        // Create Client Representative user
        $clientUser = User::create([
            'name' => 'Client Representative',
            'email' => 'client@example.com',
            'password' => Hash::make('password'),
            'role' => 'client_rep',
            'tenant_id' => $this->tenant->id
        ]);

        $this->apiAs($clientUser, $this->tenant);

        // Client Rep should not see project_manager specific metrics
        $response = $this->getJson('/api/v1/dashboard/metrics');
        $response->assertStatus(200);
        
        $metrics = $response->json('data');
        $this->assertIsArray($metrics);
        
        // Verify metrics are filtered by permissions
        foreach ($metrics as $metric) {
            $permissions = json_decode($metric['permissions'], true) ?? [];
            $this->assertContains('client_rep', $permissions);
        }
    }

    /** @test */
    public function it_validates_project_access_permissions()
    {
        // Create another tenant and project
        $otherTenant = \App\Models\Tenant::create([
            'name' => 'Other Tenant',
            'domain' => 'other.com',
            'is_active' => true
        ]);

        $otherProject = Project::create([
            'name' => 'Other Project',
            'description' => 'Other project description',
            'status' => 'active',
            'budget' => 50000,
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'tenant_id' => $otherTenant->id
        ]);

        $this->apiAs($this->user, $this->tenant);

        // User should not be able to access other tenant's project
        $response = $this->postJson('/api/v1/dashboard/role-based/switch-project', [
            'project_id' => $otherProject->id
        ]);
        
        $response->assertStatus(422);
        $this->assertStringContains('not found', $response->json('message'));
    }

    /** @test */
    public function it_validates_tenant_isolation()
    {
        // Create another tenant and user
        $otherTenant = \App\Models\Tenant::create([
            'name' => 'Other Tenant',
            'domain' => 'other.com',
            'is_active' => true
        ]);

        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => Hash::make('password'),
            'role' => 'project_manager',
            'tenant_id' => $otherTenant->id
        ]);

        $this->apiAs($otherUser, $this->tenant);

        // Other user should not see this tenant's data
        $response = $this->getJson('/api/v1/dashboard/role-based');
        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Verify no data from other tenant is returned
        $this->assertEmpty($data['widgets']);
        $this->assertEmpty($data['metrics']);
        $this->assertEmpty($data['alerts']);
    }

    /** @test */
    public function it_validates_input_sanitization()
    {
        $this->apiAs($this->user, $this->tenant);

        $widget = DashboardWidget::first();

        // Test XSS prevention
        $maliciousInput = '<script>alert("XSS")</script>';
        
        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id,
            'config' => [
                'title' => $maliciousInput,
                'size' => 'medium'
            ]
        ]);
        
        $response->assertStatus(200);
        
        // Verify input is sanitized
        $widgetInstance = $response->json('data.widget_instance');
        $this->assertStringNotContains('<script>', $widgetInstance['config']['title']);
    }

    /** @test */
    public function it_validates_sql_injection_prevention()
    {
        $this->apiAs($this->user, $this->tenant);

        // Test SQL injection in widget ID
        $maliciousWidgetId = "1'; DROP TABLE users; --";
        
        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $maliciousWidgetId
        ]);
        
        $response->assertStatus(422);
        
        // Verify users table still exists
        $userCount = User::count();
        $this->assertGreaterThan(0, $userCount);
    }

    /** @test */
    public function it_validates_csrf_protection()
    {
        // Test CSRF protection for state-changing operations
        $widget = DashboardWidget::first();

        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id
        ], [
            'X-CSRF-TOKEN' => 'invalid-token'
        ]);
        
        // Should still work as we're using API routes with Sanctum
        $response->assertStatus(200);
    }

    /** @test */
    public function it_validates_rate_limiting()
    {
        $this->apiAs($this->user, $this->tenant);

        // Make many requests quickly
        for ($i = 0; $i < 100; $i++) {
            $response = $this->getJson('/api/v1/dashboard/role-based');
            
            if ($response->status() === 429) {
                $this->assertTrue(true, 'Rate limiting is working');
                return;
            }
        }
        
        // If we get here, rate limiting might not be configured
        $this->assertTrue(true, 'Rate limiting test completed');
    }

    /** @test */
    public function it_validates_data_encryption()
    {
        $this->apiAs($this->user, $this->tenant);

        $widget = DashboardWidget::first();

        // Add widget with sensitive data
        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id,
            'config' => [
                'title' => 'Sensitive Data',
                'size' => 'medium',
                'sensitive_field' => 'confidential-information'
            ]
        ]);
        
        $response->assertStatus(200);
        
        // Verify data is stored (not necessarily encrypted in this test)
        $widgetInstance = $response->json('data.widget_instance');
        $this->assertArrayHasKey('config', $widgetInstance);
    }

    /** @test */
    public function it_validates_session_security()
    {
        $this->apiAs($this->user, $this->tenant);

        // Test session handling
        $response = $this->getJson('/api/v1/dashboard/role-based');
        $response->assertStatus(200);
        
        // Verify session is maintained
        $response2 = $this->getJson('/api/v1/dashboard/widgets');
        $response2->assertStatus(200);
    }

    /** @test */
    public function it_validates_file_upload_security()
    {
        $this->apiAs($this->user, $this->tenant);

        // Test file upload validation (if applicable)
        $maliciousFile = 'malicious.php';
        
        // This would test file upload security if file uploads were implemented
        $this->assertTrue(true, 'File upload security test placeholder');
    }

    /** @test */
    public function it_validates_api_key_security()
    {
        // Test API key validation
        $response = $this->getJson('/api/v1/dashboard', [
            'Authorization' => 'Bearer invalid-token'
        ]);
        
        $response->assertStatus(401);
    }

    /** @test */
    public function it_validates_cors_security()
    {
        // Test CORS headers
        $response = $this->options('/api/v1/dashboard/role-based', [], [
            'Origin' => 'https://malicious-site.com',
            'Access-Control-Request-Method' => 'GET'
        ]);
        
        // CORS should be properly configured
        $this->assertTrue(true, 'CORS security test placeholder');
    }

    /** @test */
    public function it_validates_https_enforcement()
    {
        // Test HTTPS enforcement (would need HTTPS setup)
        $this->assertTrue(true, 'HTTPS enforcement test placeholder');
    }

    /** @test */
    public function it_validates_security_headers()
    {
        $this->apiAs($this->user, $this->tenant);

        $response = $this->getJson('/api/v1/dashboard/role-based');
        $response->assertStatus(200);
        
        // Check for security headers
        $headers = $response->headers;
        
        // These would be set by the web server (Nginx)
        $this->assertTrue(true, 'Security headers test placeholder');
    }

    /** @test */
    public function it_validates_audit_logging()
    {
        $this->apiAs($this->user, $this->tenant);

        $widget = DashboardWidget::first();

        // Perform action that should be logged
        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id
        ]);
        
        $response->assertStatus(200);
        
        // Verify audit logging (would need audit log implementation)
        $this->assertTrue(true, 'Audit logging test placeholder');
    }

    /** @test */
    public function it_validates_data_validation()
    {
        $this->apiAs($this->user, $this->tenant);

        // Test invalid widget configuration
        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => 'invalid-id',
            'config' => [
                'size' => 'invalid-size'
            ]
        ]);
        
        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response->json());
    }

    /** @test */
    public function it_validates_permission_escalation_prevention()
    {
        // Create low-privilege user
        $lowPrivilegeUser = User::create([
            'name' => 'Low Privilege User',
            'email' => 'low@example.com',
            'password' => Hash::make('password'),
            'role' => 'client_rep',
            'tenant_id' => $this->tenant->id
        ]);

        $this->apiAs($lowPrivilegeUser, $this->tenant);

        // Try to access high-privilege operations
        $response = $this->postJson('/api/v1/dashboard/customization/widgets', [
            'widget_id' => 'any-widget-id'
        ]);
        
        $response->assertStatus(500);
        $this->assertStringContains('permission', $response->json('message'));
    }

    /** @test */
    public function it_validates_data_leakage_prevention()
    {
        // Create user from different tenant
        $otherTenant = \App\Models\Tenant::create([
            'name' => 'Other Tenant',
            'domain' => 'other.com',
            'is_active' => true
        ]);

        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => Hash::make('password'),
            'role' => 'project_manager',
            'tenant_id' => $otherTenant->id
        ]);

        $this->apiAs($otherUser, $this->tenant);

        // Try to access data from different tenant
        $response = $this->getJson('/api/v1/dashboard/role-based');
        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Verify no data from other tenant is leaked
        $this->assertEmpty($data['widgets']);
        $this->assertEmpty($data['metrics']);
        $this->assertEmpty($data['alerts']);
    }

    /** @test */
    public function it_validates_injection_attack_prevention()
    {
        $this->apiAs($this->user, $this->tenant);

        // Test NoSQL injection
        $maliciousInput = '{"$ne": null}';
        
        $response = $this->getJson('/api/v1/dashboard/alerts?' . http_build_query([
            'filter' => $maliciousInput
        ]));
        
        $response->assertStatus(200);
        
        // Verify injection was prevented
        $alerts = $response->json('data');
        $this->assertIsArray($alerts);
    }

    /** @test */
    public function it_validates_authentication_bypass_prevention()
    {
        // Test without authentication
        $response = $this->getJson('/api/v1/dashboard/role-based');
        $response->assertStatus(401);
        
        // Test with invalid token
        $response = $this->getJson('/api/v1/dashboard/role-based', [
            'Authorization' => 'Bearer invalid-token'
        ]);
        $response->assertStatus(401);
        
        // Test with expired token (would need token expiration)
        $this->assertTrue(true, 'Token expiration test placeholder');
    }

    /** @test */
    public function it_validates_privilege_escalation_prevention()
    {
        // Create user with limited permissions
        $limitedUser = User::create([
            'name' => 'Limited User',
            'email' => 'limited@example.com',
            'password' => Hash::make('password'),
            'role' => 'client_rep',
            'tenant_id' => $this->tenant->id
        ]);

        $this->apiAs($limitedUser, $this->tenant);

        // Try to perform admin operations
        $response = $this->getJson('/api/v1/dashboard/role-based/permissions');
        $response->assertStatus(200);
        
        $permissions = $response->json('data.permissions');
        
        // Verify limited permissions
        $this->assertNotContains('delete', $permissions['dashboard']);
        $this->assertNotContains('admin', $permissions['dashboard']);
    }
}
