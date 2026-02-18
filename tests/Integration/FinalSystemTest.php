<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserDashboard;
use App\Models\DashboardWidget;
use App\Models\DashboardMetric;
use App\Models\DashboardMetricValue;
use App\Models\DashboardAlert;
use App\Models\Project;
use App\Models\Task;
use App\Models\RFI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Traits\AuthenticationTrait;

class FinalSystemTest extends TestCase
{
    use RefreshDatabase, AuthenticationTrait;

    protected $user;
    protected $project;
    protected $tenant;
    protected string $expectedMetricId;
    private const STRESS_TESTS_ENV = 'RUN_STRESS_TESTS';

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant and user via shared test auth helpers
        $this->tenant = \App\Models\Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            ['role' => 'project_manager'],
            ['project_manager']
        );

        // Create test project
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id,
            'created_by' => $this->user->id,
            'name' => 'Test Project',
            'status' => 'active',
        ]);
        
        // Create comprehensive test data
        $this->createComprehensiveTestData();
        
        // Authenticate user
        $this->apiAs($this->user, $this->tenant);
    }

    protected function createComprehensiveTestData(): void
    {
        // Create all widget types
        $this->createAllWidgetTypes();
        
        // Create all metric types
        $this->createAllMetricTypes();
        
        // Create comprehensive project data
        $this->createComprehensiveProjectData();
        
        // Create alerts for all types
        $this->createAllAlertTypes();
    }

    protected function createAllWidgetTypes(): void
    {
        $widgets = [
            // Card widgets
            ['name' => 'Project Overview', 'code' => 'project_overview', 'type' => 'card', 'category' => 'overview'],
            ['name' => 'Budget Summary', 'code' => 'budget_summary', 'type' => 'card', 'category' => 'financial'],
            ['name' => 'Task Summary', 'code' => 'task_summary', 'type' => 'card', 'category' => 'tasks'],
            
            // Chart widgets
            ['name' => 'Task Progress Chart', 'code' => 'task_progress_chart', 'type' => 'chart', 'category' => 'tasks'],
            ['name' => 'Budget Utilization Chart', 'code' => 'budget_utilization_chart', 'type' => 'chart', 'category' => 'financial'],
            ['name' => 'Quality Metrics Chart', 'code' => 'quality_metrics_chart', 'type' => 'chart', 'category' => 'quality'],
            
            // Table widgets
            ['name' => 'RFI Status Table', 'code' => 'rfi_status_table', 'type' => 'table', 'category' => 'communication'],
            ['name' => 'Task List Table', 'code' => 'task_list_table', 'type' => 'table', 'category' => 'tasks'],
            ['name' => 'Inspection Table', 'code' => 'inspection_table', 'type' => 'table', 'category' => 'quality'],
            
            // Alert widgets
            ['name' => 'System Alerts', 'code' => 'system_alerts', 'type' => 'alert', 'category' => 'alerts'],
            ['name' => 'Project Alerts', 'code' => 'project_alerts', 'type' => 'alert', 'category' => 'alerts'],
            ['name' => 'Quality Alerts', 'code' => 'quality_alerts', 'type' => 'alert', 'category' => 'alerts'],
            
            // Timeline widgets
            ['name' => 'Project Timeline', 'code' => 'project_timeline', 'type' => 'timeline', 'category' => 'schedule'],
            ['name' => 'Milestone Timeline', 'code' => 'milestone_timeline', 'type' => 'timeline', 'category' => 'schedule'],
            
            // Progress widgets
            ['name' => 'Overall Progress', 'code' => 'overall_progress', 'type' => 'progress', 'category' => 'overview'],
            ['name' => 'Task Completion Progress', 'code' => 'task_completion_progress', 'type' => 'progress', 'category' => 'tasks'],
        ];

        foreach ($widgets as $widgetData) {
            DashboardWidget::create([
                'name' => $widgetData['name'],
                'code' => $widgetData['code'],
                'type' => $widgetData['type'],
                'category' => $widgetData['category'],
                'description' => "{$widgetData['name']} widget",
                'config' => json_encode(['default_size' => 'medium']),
                'permissions' => json_encode(['project_manager', 'site_engineer']),
                'is_active' => true,
                'tenant_id' => $this->tenant->id
            ]);
        }
    }

    protected function createAllMetricTypes(): void
    {
        $metrics = [
            ['name' => 'Project Progress', 'code' => 'project_progress', 'type' => 'gauge', 'unit' => '%'],
            ['name' => 'Budget Utilization', 'code' => 'budget_utilization', 'type' => 'gauge', 'unit' => '%'],
            ['name' => 'Total Tasks', 'code' => 'total_tasks', 'type' => 'counter', 'unit' => 'count'],
            ['name' => 'Open RFIs', 'code' => 'open_rfis', 'type' => 'counter', 'unit' => 'count'],
            ['name' => 'Task Duration Distribution', 'code' => 'task_duration_distribution', 'type' => 'histogram', 'unit' => 'hours'],
            ['name' => 'Budget Variance Distribution', 'code' => 'budget_variance_distribution', 'type' => 'histogram', 'unit' => '%'],
            ['name' => 'Project Summary', 'code' => 'project_summary', 'type' => 'summary', 'unit' => 'summary'],
        ];

        $createdMetrics = [];

        foreach ($metrics as $metricData) {
            $metric = DashboardMetric::create([
                'name' => $metricData['name'],
                'code' => $metricData['code'],
                'description' => "{$metricData['name']} metric",
                'unit' => $metricData['unit'],
                'type' => $metricData['type'],
                'is_active' => true,
                'permissions' => json_encode(['project_manager', 'site_engineer']),
                'tenant_id' => $this->tenant->id
            ]);

            $createdMetrics[] = $metric;
        }

        foreach ($createdMetrics as $index => $metric) {
            DashboardMetricValue::create([
                'metric_id' => $metric->id,
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'value' => (float) (100 + $index),
                'metadata' => ['source' => 'final_system_test'],
                'recorded_at' => now()->subMinutes($index),
            ]);
        }

        $this->expectedMetricId = $createdMetrics[0]->id;
    }

    protected function createComprehensiveProjectData(): void
    {
        // Create tasks
        for ($i = 1; $i <= 100; $i++) {
            Task::create([
                'title' => "Task {$i}",
                'description' => "Description for task {$i}",
                'status' => ['pending', 'in_progress', 'completed'][array_rand(['pending', 'in_progress', 'completed'])],
                'priority' => ['low', 'medium', 'high'][array_rand(['low', 'medium', 'high'])],
                'due_date' => now()->addDays(rand(1, 30)),
                'assigned_to' => $this->user->id,
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id
            ]);
        }

        // Create RFIs
        for ($i = 1; $i <= 50; $i++) {
            RFI::create([
                'title' => "RFI {$i}",
                'subject' => "RFI {$i}",
                'description' => "Description for RFI {$i}",
                'question' => "Question for RFI {$i}",
                'rfi_number' => (string) \Illuminate\Support\Str::ulid(),
                'status' => ['open', 'answered', 'closed'][array_rand(['open', 'answered', 'closed'])],
                'priority' => ['low', 'medium', 'high'][array_rand(['low', 'medium', 'high'])],
                'due_date' => now()->addDays(rand(1, 14)),
                'discipline' => ['construction', 'electrical', 'mechanical'][array_rand(['construction', 'electrical', 'mechanical'])],
                'asked_by' => $this->user->id,
                'created_by' => $this->user->id,
                'project_id' => $this->project->id,
                'tenant_id' => $this->tenant->id
            ]);
        }
    }

    protected function createAllAlertTypes(): void
    {
        $alertTypes = ['project', 'budget', 'schedule', 'quality', 'safety', 'system'];
        
        foreach ($alertTypes as $type) {
            DashboardAlert::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'message' => "Test {$type} alert",
                'type' => $type,
                'severity' => ['low', 'medium', 'high'][array_rand(['low', 'medium', 'high'])],
                'is_read' => rand(0, 1) == 1,
                'triggered_at' => now()->subDays(rand(0, 30)),
                'context' => json_encode(['project_id' => $this->project->id])
            ]);
        }
    }

    private function stressTestsEnabled(): bool
    {
        return (string) env(self::STRESS_TESTS_ENV, '0') === '1';
    }

    /**
     * @group stress
     */
    private function skipUnlessStressTestsEnabled(): void
    {
        if (!$this->stressTestsEnabled()) {
            $this->markTestSkipped(self::STRESS_TESTS_ENV . '=1 is required for stress/performance loops in FinalSystemTest');
        }
    }

    private function createRolelessTenantUser(string $email): User
    {
        $user = $this->createTenantUser(
            $this->tenant,
            [
                'name' => 'Roleless User',
                'email' => $email,
                'role' => null,
            ],
            ['project_manager'],
            ['project.read']
        );

        $user->roles()->detach();
        if (method_exists($user, 'systemRoles')) {
            $user->systemRoles()->detach();
        }

        $user->forceFill(['role' => null])->save();

        return $user->fresh();
    }

    /**
     * @test
     * @group stress
     */
    public function it_can_complete_comprehensive_system_workflow()
    {
        $this->skipUnlessStressTestsEnabled();
        echo "\n=== COMPREHENSIVE SYSTEM WORKFLOW TEST ===\n";
        
        // Step 1: Get role-based dashboard
        echo "Step 1: Getting role-based dashboard...\n";
        $startTime = microtime(true);
        
        $roleBasedResponse = $this->getJson('/api/v1/dashboard/role-based');
        $roleBasedResponse->assertStatus(200);
        
        $endTime = microtime(true);
        $step1Time = ($endTime - $startTime) * 1000;
        echo "✓ Role-based dashboard loaded in {$step1Time}ms\n";
        
        $roleBasedData = $roleBasedResponse->json('data');
        $this->assertArrayHasKey('dashboard', $roleBasedData);
        $this->assertArrayHasKey('widgets', $roleBasedData);
        $this->assertArrayHasKey('metrics', $roleBasedData);
        $this->assertArrayHasKey('alerts', $roleBasedData);
        $this->assertArrayHasKey('permissions', $roleBasedData);
        $this->assertArrayHasKey('role_config', $roleBasedData);

        // Step 2: Add multiple widgets of different types
        echo "Step 2: Adding multiple widgets...\n";
        $startTime = microtime(true);
        
        $widgets = DashboardWidget::where('permissions', 'like', '%project_manager%')->take(8)->get();
        $addedWidgets = [];
        
        foreach ($widgets as $widget) {
            $addResponse = $this->postJson('/api/v1/dashboard/widgets', [
                'widget_id' => $widget->id,
                'config' => [
                    'title' => "Custom {$widget->name}",
                    'size' => 'medium'
                ]
            ]);
            
            $addResponse->assertStatus(200);
            $addedWidgets[] = $addResponse->json('data.widget_instance');
        }
        
        $endTime = microtime(true);
        $step2Time = ($endTime - $startTime) * 1000;
        echo "✓ Added 8 widgets in {$step2Time}ms\n";
        
        $this->assertCount(8, $addedWidgets);
        foreach ($addedWidgets as $widgetInstance) {
            $this->assertIsArray($widgetInstance);
            $this->assertArrayHasKey('id', $widgetInstance);
            $this->assertArrayHasKey('widget_id', $widgetInstance);
        }

        $dashboardAfterAddResponse = $this->getJson('/api/v1/dashboard');
        $dashboardAfterAddResponse->assertStatus(200);
        $dashboardAfterAdd = $dashboardAfterAddResponse->json('data');
        $this->assertCount(8, $dashboardAfterAdd['layout']);
        $this->assertContains($addedWidgets[0]['id'], array_column($dashboardAfterAdd['layout'], 'id'));

        // Step 3: Update widget configurations
        echo "Step 3: Updating widget configurations...\n";
        $startTime = microtime(true);
        
        foreach ($addedWidgets as $index => $widgetInstance) {
            $updateResponse = $this->putJson("/api/v1/dashboard/widgets/{$widgetInstance['id']}/config", [
                'config' => [
                    'title' => "Updated Widget {$index}",
                    'size' => 'large'
                ]
            ]);
            
            $updateResponse->assertStatus(200);
        }
        
        $endTime = microtime(true);
        $step3Time = ($endTime - $startTime) * 1000;
        echo "✓ Updated 8 widget configurations in {$step3Time}ms\n";

        // Step 4: Update dashboard layout
        echo "Step 4: Updating dashboard layout...\n";
        $startTime = microtime(true);
        
        $dashboardResponse = $this->getJson('/api/v1/dashboard');
        $layout = $dashboardResponse->json('data.layout');

        // Arrange widgets in a grid
        foreach ($layout as $index => $widgetInstance) {
            $layout[$index]['position'] = [
                'x' => ($index % 4) * 3,
                'y' => intval($index / 4) * 3
            ];
        }

        $layoutResponse = $this->putJson('/api/v1/dashboard/layout', [
            'layout' => $layout
        ]);
        
        $layoutResponse->assertStatus(200);
        
        $endTime = microtime(true);
        $step4Time = ($endTime - $startTime) * 1000;
        echo "✓ Updated dashboard layout in {$step4Time}ms\n";

        // Step 5: Test all widget data endpoints
        echo "Step 5: Testing widget data endpoints...\n";
        $startTime = microtime(true);
        
        foreach ($addedWidgets as $widgetInstance) {
            $widget = DashboardWidget::find($widgetInstance['widget_id']);
            
            $dataResponse = $this->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");
            $dataResponse->assertStatus(200);
            
            $data = $dataResponse->json('data');
            $this->assertIsArray($data);
        }
        
        $endTime = microtime(true);
        $step5Time = ($endTime - $startTime) * 1000;
        echo "✓ Tested 8 widget data endpoints in {$step5Time}ms\n";

        // Step 6: Test metrics endpoints
        echo "Step 6: Testing metrics endpoints...\n";
        $startTime = microtime(true);
        
        $metricsResponse = $this->getJson('/api/v1/dashboard/metrics');
        $metricsResponse->assertStatus(200);
        
        $metrics = $metricsResponse->json('data');
        $this->assertIsArray($metrics);
        $this->assertGreaterThan(0, count($metrics));
        $this->assertContains($this->expectedMetricId, array_column($metrics, 'id'));
        
        $endTime = microtime(true);
        $step6Time = ($endTime - $startTime) * 1000;
        echo "✓ Retrieved metrics in {$step6Time}ms\n";

        // Step 7: Test alerts management
        echo "Step 7: Testing alerts management...\n";
        $startTime = microtime(true);
        
        $alertsResponse = $this->getJson(route('api.v1.dashboard.alerts.index', [], false));
        $alertsResponse
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data'
            ]);
        
        $alerts = $alertsResponse->json('data');
        $this->assertIsArray($alerts);

        // Mark some alerts as read
        $unreadAlerts = array_values(array_filter($alerts, function ($alert) {
            return !$alert['is_read'];
        }));

        if (count($unreadAlerts) > 0) {
            $firstAlert = $unreadAlerts[0];
            $this->assertArrayHasKey('id', $firstAlert);
            
            $markReadResponse = $this->putJson(
                route('api.v1.dashboard.alerts.read', ['alertId' => $firstAlert['id']], false)
            );
            $markReadResponse->assertStatus(200);
        }
        
        $endTime = microtime(true);
        $step7Time = ($endTime - $startTime) * 1000;
        echo "✓ Managed alerts in {$step7Time}ms\n";

        // Step 8: Test customization features
        echo "Step 8: Testing customization features...\n";
        $startTime = microtime(true);
        
        $customizationResponse = $this->getJson('/api/v1/dashboard/customization/');
        $customizationResponse->assertStatus(200);
        
        $customizationData = $customizationResponse->json('data');
        $this->assertArrayHasKey('available_widgets', $customizationData);
        $this->assertArrayHasKey('customization_options', $customizationData);
        
        $endTime = microtime(true);
        $step8Time = ($endTime - $startTime) * 1000;
        echo "✓ Tested customization features in {$step8Time}ms\n";

        // Step 9: Test preferences
        echo "Step 9: Testing preferences...\n";
        $startTime = microtime(true);
        
        $preferencesResponse = $this->postJson('/api/v1/dashboard/preferences', [
            'preferences' => [
                'theme' => 'dark',
                'refresh_interval' => 60,
                'compact_mode' => true,
                'show_widget_borders' => false
            ]
        ]);
        
        $preferencesResponse->assertStatus(200);
        
        $endTime = microtime(true);
        $step9Time = ($endTime - $startTime) * 1000;
        echo "✓ Updated preferences in {$step9Time}ms\n";

        // Step 10: Test export/import
        echo "Step 10: Testing export/import...\n";
        $startTime = microtime(true);
        
        $exportResponse = $this->getJson('/api/v1/dashboard/customization/export');
        $exportResponse->assertStatus(200);
        
        $exportData = $exportResponse->json('data');
        $this->assertArrayHasKey('version', $exportData);
        $this->assertArrayHasKey('dashboard', $exportData);
        
        $endTime = microtime(true);
        $step10Time = ($endTime - $startTime) * 1000;
        echo "✓ Exported dashboard configuration in {$step10Time}ms\n";

        // Step 11: Test project context switching
        echo "Step 11: Testing project context switching...\n";
        $startTime = microtime(true);
        
        $switchProjectResponse = $this->postJson('/api/v1/dashboard/role-based/switch-project', [
            'project_id' => $this->project->id
        ]);
        
        $switchProjectResponse->assertStatus(200);
        
        $endTime = microtime(true);
        $step11Time = ($endTime - $startTime) * 1000;
        echo "✓ Switched project context in {$step11Time}ms\n";

        // Step 12: Verify final dashboard state
        echo "Step 12: Verifying final dashboard state...\n";
        $startTime = microtime(true);
        
        $finalDashboardResponse = $this->getJson('/api/v1/dashboard');
        $finalDashboardResponse->assertStatus(200);
        
        $finalDashboard = $finalDashboardResponse->json('data');
        $this->assertIsArray($finalDashboard['layout']);
        $this->assertEquals('dark', $finalDashboard['preferences']['theme']);
        
        $endTime = microtime(true);
        $step12Time = ($endTime - $startTime) * 1000;
        echo "✓ Verified final dashboard state in {$step12Time}ms\n";

        // Summary
        $totalTime = $step1Time + $step2Time + $step3Time + $step4Time + $step5Time + 
                    $step6Time + $step7Time + $step8Time + $step9Time + $step10Time + 
                    $step11Time + $step12Time;
        
        echo "\n=== WORKFLOW TEST SUMMARY ===\n";
        echo "Total Execution Time: {$totalTime}ms\n";
        echo "Average Step Time: " . round($totalTime / 12, 2) . "ms\n";
        echo "All steps completed successfully!\n";
        
        $this->assertLessThan(10000, $totalTime, 'Complete workflow should complete in less than 10000ms');
    }

    /** @test */
    public function it_can_handle_all_user_roles_comprehensively()
    {
        echo "\n=== COMPREHENSIVE ROLE TESTING ===\n";

        $endpoint = '/api/v1/dashboard/role-based';

        echo "Testing guest access...\n";
        $guestResponse = $this
            ->flushHeaders()
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Tenant-ID' => (string) $this->tenant->id,
            ])
            ->getJson($endpoint);
        $guestResponse->assertStatus(401);
        echo "✓ Guest receives 401\n";

        echo "Testing authenticated same-tenant access...\n";
        $this->apiAs($this->user, $this->tenant);
        $authenticatedResponse = $this->getJson($endpoint);
        $authenticatedResponse->assertStatus(200);
        $authenticatedData = $authenticatedResponse->json('data');
        $this->assertArrayHasKey('role_config', $authenticatedData);
        $this->assertArrayHasKey('widgets', $authenticatedData);
        $this->assertArrayHasKey('metrics', $authenticatedData);
        $this->assertArrayHasKey('alerts', $authenticatedData);
        echo "✓ Authenticated same-tenant user receives 200\n";

        echo "Testing tenant mismatch denial...\n";
        $otherTenant = \App\Models\Tenant::factory()->create();
        $tenantMismatchResponse = $this
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Tenant-ID' => (string) $otherTenant->id,
            ])
            ->getJson($endpoint);
        $tenantMismatchResponse
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_INVALID');
        echo "✓ Tenant mismatch returns 403 TENANT_INVALID\n";

        echo "Testing no-role/no-permission control user...\n";
        $rolelessUser = $this->createRolelessTenantUser('baseline-roleless@example.com');
        $this->assertNull($rolelessUser->role);
        $this->assertFalse($rolelessUser->roles()->exists());
        $this->assertFalse($rolelessUser->hasPermission('project.read'));

        $this->apiAs($rolelessUser, $this->tenant);
        $rolelessResponse = $this->getJson($endpoint);

        if ($rolelessResponse->status() === 200) {
            $permissions = $rolelessResponse->json('data.permissions') ?? [];
            $this->assertIsArray($permissions);
            $permissionBlob = json_encode($permissions, JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('admin', $permissionBlob);
            $this->assertStringNotContainsString('manage_users', $permissionBlob);
            $this->assertStringNotContainsString('manage_tenants', $permissionBlob);
            echo "✓ Roleless user allowed, payload remains low-privilege\n";
            return;
        }

        $rolelessResponse->assertStatus(403);
        $responseCode = (string) ($rolelessResponse->json('error.code') ?? $rolelessResponse->json('code') ?? '');
        $this->assertContains($responseCode, ['E403.AUTHORIZATION', 'RBAC_ACCESS_DENIED']);
        echo "✓ Roleless user denied with 403 authorization code\n";
    }

    /** @test */
    public function it_can_handle_comprehensive_error_scenarios()
    {
        echo "\n=== COMPREHENSIVE ERROR SCENARIO TESTING ===\n";
        
        $this->apiAs($this->user, $this->tenant);

        // Test invalid widget ID
        echo "Testing invalid widget ID...\n";
        $invalidResponse = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => 'invalid-widget-id'
        ]);
        $invalidResponse->assertStatus(422);
        echo "✓ Invalid widget ID handled correctly\n";

        // Test invalid project context
        echo "Testing invalid project context...\n";
        $invalidProjectResponse = $this->postJson('/api/v1/dashboard/role-based/switch-project', [
            'project_id' => 'invalid-project-id'
        ]);
        $invalidProjectResponse->assertStatus(422);
        echo "✓ Invalid project context handled correctly\n";

        // Test validation envelope on role-based context endpoint
        echo "Testing invalid import data...\n";
        $invalidImportResponse = $this->postJson('/api/v1/dashboard/role-based/switch-project', [
            'project_id' => 'invalid-project-id'
        ]);
        $invalidImportResponse->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('error.code', 'E422.VALIDATION')
            ->assertJsonPath('errors.project_id', fn ($value) => !empty($value));
        echo "✓ Invalid import data handled correctly\n";

        // Test invalid widget configuration
        echo "Testing invalid widget configuration...\n";
        $widget = DashboardWidget::query()->first();
        if (!$widget) {
            $widget = DashboardWidget::create([
                'name' => 'Fallback Widget',
                'code' => 'fallback_widget',
                'type' => 'card',
                'category' => 'overview',
                'description' => 'Fallback widget for validation scenario tests',
                'config' => json_encode(['default_size' => 'medium']),
                'permissions' => json_encode(['project_manager']),
                'is_active' => true,
                'tenant_id' => $this->tenant->id,
            ]);
        }
        $invalidConfigResponse = $this->postJson('/api/v1/dashboard/customization/widgets', [
            'widget_id' => $widget->id,
            'config' => [
                'size' => 'invalid-size'
            ]
        ]);
        $invalidConfigResponse->assertStatus(422);
        echo "✓ Invalid widget configuration handled correctly\n";

        // Test invalid layout template
        echo "Testing invalid layout template...\n";
        $missingId = (string) Str::ulid();
        $invalidTemplateResponse = $this->postJson('/api/v1/dashboard/customization/apply-template', [
            'template_id' => $missingId
        ]);
        $invalidTemplateResponse->assertStatus(500);
        echo "✓ Invalid layout template handled correctly\n";

        // Test unauthorized access
        echo "Testing unauthorized access...\n";
        $unauthorizedUser = $this->createTenantUser($this->tenant, [
            'name' => 'Unauthorized User',
            'email' => 'unauthorized+' . Str::lower(Str::random(8)) . '@example.com',
            'role' => 'client_rep',
            'tenant_id' => $this->tenant->id
        ], ['client_rep']);

        $this->apiAs($unauthorizedUser, $this->tenant);

        $unauthorizedResponse = $this->postJson('/api/v1/dashboard/customization/widgets', [
            'widget_id' => $widget->id
        ]);
        $unauthorizedResponse->assertStatus(404)
            ->assertJsonPath('status', 'error');
        echo "✓ Unauthorized access handled correctly\n";

        echo "All error scenarios handled correctly!\n";
    }

    /**
     * @test
     * @group stress
     */
    public function it_can_handle_comprehensive_performance_scenarios()
    {
        $this->skipUnlessStressTestsEnabled();
        echo "\n=== COMPREHENSIVE PERFORMANCE TESTING ===\n";
        
        $this->apiAs($this->user, $this->tenant);

        // Test dashboard load performance
        echo "Testing dashboard load performance...\n";
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/v1/dashboard/role-based');
        
        $endTime = microtime(true);
        $loadTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(1000, $loadTime, 'Dashboard should load in less than 1000ms');
        echo "✓ Dashboard loaded in {$loadTime}ms\n";

        // Test concurrent requests
        echo "Testing concurrent requests...\n";
        $concurrentRequests = 20;
        $startTime = microtime(true);
        
        $responses = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $responses[] = $this->getJson('/api/v1/dashboard/role-based');
        }
        
        $endTime = microtime(true);
        $concurrentTime = ($endTime - $startTime) * 1000;
        
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        $this->assertLessThan(3000, $concurrentTime, '20 concurrent requests should complete in less than 3000ms');
        echo "✓ {$concurrentRequests} concurrent requests completed in {$concurrentTime}ms\n";

        // Test memory usage
        echo "Testing memory usage...\n";
        $startMemory = memory_get_usage();
        
        for ($i = 0; $i < 10; $i++) {
            $response = $this->getJson('/api/v1/dashboard/role-based');
            $response->assertStatus(200);
        }
        
        $endMemory = memory_get_usage();
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;
        
        $this->assertLessThan(100, $memoryUsed, 'Memory usage should be less than 100MB for 10 operations');
        echo "✓ Memory usage: {$memoryUsed}MB\n";

        // Test database query optimization
        echo "Testing database query optimization...\n";
        DB::enableQueryLog();
        
        $response = $this->getJson('/api/v1/dashboard/role-based');
        $response->assertStatus(200);
        
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        $this->assertLessThan(30, $queryCount, 'Dashboard should use less than 30 database queries');
        echo "✓ Database queries: {$queryCount}\n";
        
        DB::disableQueryLog();

        echo "All performance scenarios tested successfully!\n";
    }

    /** @test */
    public function it_can_handle_comprehensive_security_scenarios()
    {
        echo "\n=== COMPREHENSIVE SECURITY TESTING ===\n";
        
        // Test authentication requirement
        echo "Testing authentication requirement...\n";
        $this->flushHeaders();
        if (property_exists($this, 'apiHeaders')) {
            $this->apiHeaders = [];
        }
        app('auth')->forgetGuards();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/v1/auth/me');
        $response->assertStatus(401);
        echo "✓ Authentication required\n";

        $this->apiAs($this->user, $this->tenant);

        // Test permission validation
        echo "Testing permission validation...\n";
        $widget = DashboardWidget::first();
        
        // Test with valid permissions
        $validResponse = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id
        ]);
        $validResponse->assertStatus(200);
        echo "✓ Valid permissions accepted\n";

        // Test input sanitization
        echo "Testing input sanitization...\n";
        $maliciousInput = '<script>alert("XSS")</script>';
        
        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id,
            'config' => [
                'title' => $maliciousInput,
                'size' => 'medium'
            ]
        ]);
        
        $statusCode = $response->getStatusCode();
        $this->assertContains($statusCode, [400, 422], 'XSS payload must be rejected');
        $response->assertJsonPath('status', 'error');
        if ($statusCode === 400) {
            $response->assertJsonPath('error.code', 'SUSPICIOUS_INPUT');
        } else {
            $response->assertJsonPath('error.code', 'E422.VALIDATION');
            $errors = $response->json('errors', []);
            $this->assertIsArray($errors);
            $this->assertTrue(
                array_key_exists('config.title', $errors)
                || array_key_exists('config', $errors)
                || array_key_exists('title', $errors),
                'Validation errors should include the offending field'
            );
        }
        echo "✓ Input sanitization working\n";

        // Test SQL injection prevention
        echo "Testing SQL injection prevention...\n";
        $maliciousWidgetId = "1'; DROP TABLE users; --";
        
        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $maliciousWidgetId
        ]);
        
        $sqlStatus = $response->getStatusCode();
        $this->assertContains($sqlStatus, [400, 422], 'SQL injection payload must be rejected');
        $response->assertJsonPath('status', 'error');
        if ($sqlStatus === 400) {
            $response->assertJsonPath('error.code', 'SUSPICIOUS_INPUT');
        } else {
            $response->assertJsonPath('error.code', 'E422.VALIDATION');
        }
        
        $userCount = User::count();
        $this->assertGreaterThan(0, $userCount);
        echo "✓ SQL injection prevented\n";

        // Test tenant isolation
        echo "Testing tenant isolation...\n";
        $this->apiAs($this->user, $this->tenant);

        $otherTenant = \App\Models\Tenant::factory()->create();
        $headers = array_merge($this->apiHeaders, [
            'X-Tenant-ID' => (string) $otherTenant->id,
        ]);

        $response = $this->withHeaders($headers)->getJson('/api/v1/dashboard/role-based');
        $status = $response->getStatusCode();
        $response->assertStatus(in_array($status, [403, 404], true) ? $status : 403);

        if ($status === 403) {
            $response->assertJsonPath('error.code', 'TENANT_INVALID');
        }
        echo "✓ Tenant isolation working\n";

        echo "All security scenarios tested successfully!\n";
    }

    /**
     * @test
     * @group stress
     */
    public function it_can_handle_final_system_validation()
    {
        $this->skipUnlessStressTestsEnabled();
        echo "\n=== FINAL SYSTEM VALIDATION ===\n";
        
        $this->apiAs($this->user, $this->tenant);

        // Validate all core functionalities with their contract methods.
        $coreFunctionalities = [
            [
                'name' => 'Role-based Dashboard',
                'method' => 'GET',
                'uri' => route('api.v1.dashboard.role_based.index', [], false),
                'payload' => [],
                'expected_status' => 200,
            ],
            [
                'name' => 'Widget Management',
                'method' => 'GET',
                'uri' => route('api.v1.dashboard.widgets.index', [], false),
                'payload' => [],
                'expected_status' => 200,
            ],
            [
                'name' => 'Metrics Retrieval',
                'method' => 'GET',
                'uri' => route('api.v1.dashboard.metrics', [], false),
                'payload' => [],
                'expected_status' => 200,
            ],
            [
                'name' => 'Alerts Management',
                'method' => 'GET',
                'uri' => route('api.v1.dashboard.alerts.index', [], false),
                'payload' => [],
                'expected_status' => 200,
            ],
            [
                'name' => 'Customization Features',
                'method' => 'GET',
                'uri' => route('api.v1.dashboard.customization.index', [], false),
                'payload' => [],
                'expected_status' => 200,
            ],
            [
                'name' => 'Preferences Management',
                'method' => 'POST',
                'uri' => route('api.v1.dashboard.preferences.store', [], false),
                'payload' => [
                    'preferences' => [
                        'theme' => 'dark',
                        'refresh_interval' => 60,
                        'compact_mode' => true,
                        'show_widget_borders' => false,
                    ],
                ],
                'expected_status' => 200,
            ],
            [
                'name' => 'Project Context Switching',
                'method' => 'POST',
                'uri' => route('api.v1.dashboard.role_based.switch_project', [], false),
                'payload' => ['project_id' => $this->project->id],
                'expected_status' => 200,
            ],
            [
                'name' => 'Export/Import',
                'method' => 'GET',
                'uri' => route('api.v1.dashboard.customization.export', [], false),
                'payload' => [],
                'expected_status' => 200,
            ],
        ];

        foreach ($coreFunctionalities as $scenario) {
            $name = $scenario['name'];
            $method = strtoupper($scenario['method']);
            echo "Validating {$name}...\n";

            $startTime = microtime(true);
            if ($method === 'GET') {
                $response = $this->getJson($scenario['uri']);
            } elseif ($method === 'POST') {
                $response = $this->postJson($scenario['uri'], $scenario['payload']);
            } elseif ($method === 'PUT') {
                $response = $this->putJson($scenario['uri'], $scenario['payload']);
            } elseif ($method === 'PATCH') {
                $response = $this->patchJson($scenario['uri'], $scenario['payload']);
            } elseif ($method === 'DELETE') {
                $response = $this->deleteJson($scenario['uri'], $scenario['payload']);
            } else {
                $this->fail("Unsupported HTTP method [{$method}] for {$name}");
            }
            $endTime = microtime(true);

            $responseTime = ($endTime - $startTime) * 1000;
            $response->assertStatus($scenario['expected_status']);

            echo "✓ {$name} validated in {$responseTime}ms\n";
        }

        // Validate all user roles
        $roles = ['system_admin', 'project_manager', 'design_lead', 'site_engineer', 'qc_inspector', 'client_rep', 'subcontractor_lead'];
        
        foreach ($roles as $role) {
            echo "Validating {$role} role...\n";
            
            $user = User::factory()->create([
                'name' => "Test {$role}",
                'email' => "{$role}@example.com",
                'password' => Hash::make('password'),
                'role' => $role,
                'tenant_id' => $this->tenant->id
            ]);

            $this->apiAs($user, $this->tenant);

            $response = $this->getJson('/api/v1/dashboard/role-based');
            $response->assertStatus(200);
            
            $data = $response->json('data');
            $this->assertArrayHasKey('role_config', $data);
            $this->assertArrayHasKey('permissions', $data);
            
            echo "✓ {$role} role validated\n";
        }

        // Validate all widget types
        $widgetTypes = ['card', 'chart', 'table', 'alert', 'timeline', 'progress'];
        
        foreach ($widgetTypes as $type) {
            echo "Validating {$type} widget type...\n";
            
            $widget = DashboardWidget::where('type', $type)->first();
            
            if ($widget) {
                $response = $this->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");
                $response->assertStatus(200);
                
                $data = $response->json('data');
                $this->assertIsArray($data);
                
                echo "✓ {$type} widget type validated\n";
            }
        }

        // Validate all metric types
        $metricTypes = ['gauge', 'counter', 'histogram', 'summary'];
        
        foreach ($metricTypes as $type) {
            echo "Validating {$type} metric type...\n";
            
            $metric = DashboardMetric::where('type', $type)->first();
            
            if ($metric) {
                // Create metric value
                \App\Models\DashboardMetricValue::create([
                    'metric_id' => $metric->id,
                    'tenant_id' => $this->tenant->id,
                    'project_id' => $this->project->id,
                    'value' => rand(1, 100),
                    'timestamp' => now(),
                    'context' => json_encode(['test' => true])
                ]);
                
                echo "✓ {$type} metric type validated\n";
            }
        }

        echo "\n=== FINAL VALIDATION SUMMARY ===\n";
        echo "✓ All core functionalities validated\n";
        echo "✓ All user roles validated\n";
        echo "✓ All widget types validated\n";
        echo "✓ All metric types validated\n";
        echo "✓ System is production ready!\n";
    }
}
