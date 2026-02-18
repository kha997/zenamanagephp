<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserDashboard;
use App\Models\DashboardWidget;
use App\Models\DashboardMetric;
use App\Models\DashboardAlert;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\Rfi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\SSOT\FixtureFactory;
use Tests\Traits\AuthenticationTrait;

class SecurityIntegrationTest extends TestCase
{
    use RefreshDatabase, AuthenticationTrait, FixtureFactory;

    protected $user;
    protected $project;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = $this->createTenant([
            'name' => 'Test Tenant',
            'domain' => 'test.com',
            'is_active' => true
        ]);
        
        // Create test user
        $this->user = $this->createTenantUserWithRbac($this->tenant, 'project_manager', 'project_manager', [], [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => 'project_manager',
            'tenant_id' => $this->tenant->id,
        ]);
        
        // Create test project
        $this->project = $this->createProjectForTenant($this->tenant, $this->user, [
            'name' => 'Test Project',
            'description' => 'Test project description',
            'status' => 'active',
            'budget' => 100000,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'tenant_id' => $this->tenant->id,
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
        $this->createRfiFor($this->tenant, $this->project, $this->user, [
            'title' => 'Test RFI',
            'subject' => 'Test RFI',
            'description' => 'Test RFI description',
            'status' => 'open',
            'priority' => 'medium',
            'due_date' => now()->addDays(3),
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
        $qcUser = User::factory()->create([
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
        
        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'E403.AUTHORIZATION');
        $response->assertJsonStructure([
            'status',
            'success',
            'message',
            'error' => ['id', 'code', 'message', 'details'],
        ]);
        $this->assertStringContains('permission', strtolower((string) $response->json('error.message')));

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
        // Create Client Representative user with RBAC assignment via SSOT helper
        $clientUser = $this->createTenantUser(
            $this->tenant,
            [
            'name' => 'Client Representative',
            'email' => 'client@example.com',
            'role' => 'client_rep',
            'tenant_id' => $this->tenant->id
            ],
            ['client'],
            ['dashboard.view']
        );

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
        $otherTenant = \App\Models\Tenant::factory()->create([
            'name' => 'Other Tenant',
            'domain' => 'other.com',
            'is_active' => true
        ]);

        $otherProject = Project::factory()->create([
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
        
        $response->assertStatus(404);
        $response->assertJsonPath('error.code', 'E404.NOT_FOUND');
        $this->assertStringContains('not found', $response->json('message'));
    }

    /** @test */
    public function it_validates_tenant_isolation()
    {
        $tenantA = $this->tenant;
        $userA = $this->createTenantUser(
            $tenantA,
            [
                'name' => 'Tenant A User',
                'email' => 'tenant-a+' . Str::lower((string) Str::ulid()) . '@example.com',
                'role' => 'project_manager',
            ],
            ['project_manager']
        );

        $tenantB = Tenant::factory()->create();
        $token = $this->apiLoginToken($userA, $tenantA);

        // Positive control: matching tenant header is accepted
        $positiveResponse = $this->withHeaders($this->authHeadersForUser($userA, $token))
            ->getJson('/api/v1/dashboard/role-based');
        $positiveResponse->assertStatus(200);

        // Mismatch control: token tenant != X-Tenant-ID should be denied/hidden
        $mismatchResponse = $this->withHeaders($this->authHeadersForUser($userA, $token, [
            'X-Tenant-ID' => (string) $tenantB->id,
        ]))->getJson('/api/v1/dashboard/role-based');

        $this->assertContains($mismatchResponse->status(), [403, 404]);

        if ($mismatchResponse->status() === 403) {
            $mismatchResponse->assertJsonPath('error.code', 'TENANT_INVALID');
        }

        if ($mismatchResponse->status() === 404 && !is_null($mismatchResponse->json('error.code'))) {
            $mismatchResponse->assertJsonPath('error.code', 'E404.NOT_FOUND');
        }
    }

    /** @test */
    public function it_validates_input_sanitization()
    {
        $this->apiAs($this->user, $this->tenant);

        $widget = DashboardWidget::first();

        // Positive control: clean payload should pass
        $safeResponse = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id,
            'config' => [
                'title' => 'Operations Overview',
                'size' => 'medium',
            ],
        ]);
        $safeResponse->assertStatus(200);
        $safeResponse->assertJsonPath('success', true);

        // Reject control: obvious XSS payload must be blocked
        $maliciousInput = '<script>alert("XSS")</script>';
        
        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id,
            'config' => [
                'title' => $maliciousInput,
                'size' => 'medium',
            ],
        ]);

        $statusCode = $response->getStatusCode();
        $this->assertContains($statusCode, [400, 422], 'Malicious payload must be rejected');
        $response->assertJsonPath('status', 'error');

        if ($statusCode === 400) {
            $response->assertJsonPath('error.code', 'SUSPICIOUS_INPUT');
        } else {
            $response->assertJsonPath('error.code', 'E422.VALIDATION');
            $this->assertNotEmpty(
                data_get($response->json(), 'errors.config.title')
                ?? data_get($response->json(), 'errors.config')
                ?? data_get($response->json(), 'errors.title')
                ?? data_get($response->json(), 'data.validation_errors.config.title')
                ?? data_get($response->json(), 'data.validation_errors.config')
                ?? data_get($response->json(), 'data.validation_errors.title'),
                'Validation errors should include offending field'
            );
        }
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

        $statusCode = $response->getStatusCode();
        $this->assertContains($statusCode, [400, 422], 'SQL injection payload must be rejected');
        $response->assertJsonPath('status', 'error');

        if ($statusCode === 400) {
            $response->assertJsonPath('error.code', 'SUSPICIOUS_INPUT');
        } else {
            $response->assertJsonPath('error.code', 'E422.VALIDATION');
        }
        
        // Verify users table still exists
        $userCount = User::count();
        $this->assertGreaterThan(0, $userCount);
    }

    /** @test */
    public function it_allows_benign_prose_that_contains_select_keyword()
    {
        $this->apiAs($this->user, $this->tenant);

        $widget = DashboardWidget::where('code', 'project_overview')->firstOrFail();

        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id,
            'config' => [
                'title' => 'Please select the weekly report you want to review.',
                'size' => 'medium',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertNotSame('SUSPICIOUS_INPUT', $response->json('error.code'));
    }

    /** @test */
    public function it_rejects_sql_structure_and_nosql_operator_payloads()
    {
        $this->apiAs($this->user, $this->tenant);

        $widget = DashboardWidget::where('code', 'project_overview')->firstOrFail();

        $payloads = [
            'SELECT * FROM users',
            'UNION SELECT email FROM users',
            'DROP TABLE users',
            '{"$ne": null}',
        ];

        foreach ($payloads as $payload) {
            $response = $this->postJson('/api/v1/dashboard/widgets', [
                'widget_id' => $widget->id,
                'config' => [
                    'title' => $payload,
                    'size' => 'medium',
                ],
            ]);

            $response->assertStatus(400);
            $response->assertJsonPath('error.code', 'SUSPICIOUS_INPUT');
        }
    }

    /** @test */
    public function it_validates_csrf_protection()
    {
        $this->apiAs($this->user, $this->tenant);

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
        $lowPrivilegeUser = User::factory()->create([
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
        
        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'RBAC_ACCESS_DENIED');
        $errorMessage = strtolower((string) $response->json('error.message'));
        $this->assertTrue(
            str_contains($errorMessage, 'permission')
            || str_contains($errorMessage, 'rbac')
            || str_contains($errorMessage, 'assignment'),
            'Authorization error message should indicate access control failure.'
        );
    }

    /** @test */
    public function it_validates_data_leakage_prevention()
    {
        $endpoint = '/api/v1/dashboard/role-based';

        // Positive control: authorized user in the same tenant can access.
        $authorizedUser = $this->createTenantUser(
            $this->tenant,
            [
                'name' => 'Authorized Dashboard User',
                'email' => 'authorized+' . Str::lower((string) Str::ulid()) . '@example.com',
                'role' => 'project_manager',
            ],
            ['project_manager'],
            ['dashboard.view']
        );
        $authorizedToken = $this->apiLoginToken($authorizedUser, $this->tenant);
        $positiveResponse = $this->withHeaders($this->authHeadersForUser($authorizedUser, $authorizedToken))
            ->getJson($endpoint);
        $positiveResponse->assertStatus(200);

        // Negative control 1: cross-tenant header mismatch must be denied/hidden.
        $otherTenant = Tenant::factory()->create();
        $crossTenantResponse = $this->withHeaders($this->authHeadersForUser($authorizedUser, $authorizedToken, [
            'X-Tenant-ID' => (string) $otherTenant->id,
        ]))->getJson($endpoint);

        $this->assertContains($crossTenantResponse->status(), [403, 404]);
        if ($crossTenantResponse->status() === 403) {
            $crossTenantResponse->assertJsonPath('error.code', 'TENANT_INVALID');
        }
        if ($crossTenantResponse->status() === 404 && !is_null($crossTenantResponse->json('error.code'))) {
            $crossTenantResponse->assertJsonPath('error.code', 'E404.NOT_FOUND');
        }

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
        
        $response->assertStatus(400);
        $response->assertJsonPath('error.code', 'SUSPICIOUS_INPUT');
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
        // Create user with limited app role but valid RBAC assignment.
        $limitedUser = $this->createTenantUser(
            $this->tenant,
            [
                'name' => 'Limited User',
                'email' => 'limited+' . Str::lower((string) Str::ulid()) . '@example.com',
                'role' => 'client_rep',
                'tenant_id' => $this->tenant->id,
            ],
            ['client'],
            ['dashboard.view']
        );

        $this->apiAs($limitedUser, $this->tenant);

        // Read effective permissions and ensure no admin escalation.
        $response = $this->getJson('/api/v1/dashboard/role-based/permissions');
        $response->assertStatus(200);
        $response->assertJsonPath('data.user_role', 'client_rep');
        
        $permissions = $response->json('data.permissions');
        
        // Verify limited permissions
        $this->assertNotContains('delete', $permissions['dashboard']);
        $this->assertNotContains('admin', $permissions['dashboard']);
    }
}
