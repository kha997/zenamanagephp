<?php

namespace Tests\Feature\Dashboard;

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
use Laravel\Sanctum\Sanctum;

class DashboardApiTest extends TestCase
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
            'code' => 'TEST-' . time(),
            'name' => 'Test Project',
            'description' => 'Test project description',
            'status' => 'active',
            'budget_total' => 100000,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create test widgets
        $this->createTestWidgets();
        
        // Create test metrics
        $this->createTestMetrics();
        
        // Create test data
        $this->createTestData();
        
        // Authenticate user
        Sanctum::actingAs($this->user);
    }

    protected function createTestWidgets(): void
    {
        DashboardWidget::create([
            'name' => 'Project Overview',
            'code' => 'project_overview',
            'type' => 'card',
            'category' => 'overview',
            'description' => 'Project overview widget',
            'config' => json_encode(['default_size' => 'large']),
            'permissions' => json_encode(['project_manager', 'site_engineer']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        DashboardWidget::create([
            'name' => 'Task Progress',
            'code' => 'task_progress',
            'type' => 'chart',
            'category' => 'tasks',
            'description' => 'Task progress widget',
            'config' => json_encode(['default_size' => 'medium']),
            'permissions' => json_encode(['project_manager', 'site_engineer']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);

        DashboardWidget::create([
            'name' => 'RFI Status',
            'code' => 'rfi_status',
            'type' => 'table',
            'category' => 'communication',
            'description' => 'RFI status widget',
            'config' => json_encode(['default_size' => 'medium']),
            'permissions' => json_encode(['project_manager', 'design_lead']),
            'is_active' => true,
            'tenant_id' => $this->tenant->id
        ]);
    }

    protected function createTestMetrics(): void
    {
        DashboardMetric::create([
            'name' => 'Project Progress',
            'code' => 'project_progress',
            'description' => 'Overall project progress percentage',
            'unit' => '%',
            'type' => 'gauge',
            'is_active' => true,
            'permissions' => json_encode(['project_manager', 'site_engineer', 'client_rep']),
            'tenant_id' => $this->tenant->id
        ]);

        DashboardMetric::create([
            'name' => 'Budget Utilization',
            'code' => 'budget_utilization',
            'description' => 'Budget utilization percentage',
            'unit' => '%',
            'type' => 'gauge',
            'is_active' => true,
            'permissions' => json_encode(['project_manager', 'client_rep']),
            'tenant_id' => $this->tenant->id
        ]);
    }

    protected function createTestData(): void
    {
        // Create test tasks
        Task::create([
            'name' => 'Test Task 1',
            'title' => 'Test Task 1',
            'description' => 'Test task description',
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => now()->addDays(7),
            'assigned_to' => $this->user->id,
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        Task::create([
            'name' => 'Test Task 2',
            'title' => 'Test Task 2',
            'description' => 'Test task description',
            'status' => 'completed',
            'priority' => 'medium',
            'due_date' => now()->subDays(1),
            'assigned_to' => $this->user->id,
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Create test RFIs
        RFI::create([
            'title' => 'Test RFI 1',
            'subject' => 'Test RFI 1',
            'question' => 'Test RFI question',
            'description' => 'Test RFI description',
            'rfi_number' => 'RFI-001',
            'status' => 'open',
            'priority' => 'high',
            'due_date' => now()->addDays(3),
            'asked_by' => $this->user->id,
            'created_by' => $this->user->id,
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Create test alerts
        DashboardAlert::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'message' => 'Test Alert 1',
            'type' => 'info',
            'severity' => 'low',
            'is_read' => false,
            'triggered_at' => now(),
            'context' => json_encode(['project_id' => $this->project->id])
        ]);
    }

    /** @test */
    public function it_can_get_user_dashboard()
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'name',
                        'layout',
                        'preferences',
                        'is_default',
                        'created_at',
                        'updated_at'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_available_widgets()
    {
        $response = $this->getJson('/api/v1/dashboard/widgets');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'type',
                            'category',
                            'description',
                            'config',
                            'permissions',
                            'is_active'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_widget_data()
    {
        $widget = DashboardWidget::first(); // Use first widget instead of searching by code
        
        $response = $this->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ]);
    }

    /** @test */
    public function it_can_add_widget_to_dashboard()
    {
        $widget = DashboardWidget::first();
        
        // First create a dashboard for the user
        $this->getJson('/api/v1/dashboard'); // This will create a default dashboard
        
        $response = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id,
            'position' => ['x' => 0, 'y' => 0],
            'size' => ['width' => 2, 'height' => 1],
            'config' => [
                'title' => 'Custom Project Overview',
                'size' => 'large'
            ]
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'widget_instance' => [
                        'id',
                        'widget_id',
                        'type',
                        'title',
                        'size',
                        'position',
                        'config',
                        'is_customizable',
                        'created_at'
                    ]
                ]);
    }

    /** @test */
    public function it_can_remove_widget_from_dashboard()
    {
        // First create a dashboard for the user
        $this->getJson('/api/v1/dashboard'); // This will create a default dashboard
        
        // Then add a widget
        $widget = DashboardWidget::first();
        $addResponse = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id,
            'position' => ['x' => 0, 'y' => 0],
            'size' => ['width' => 2, 'height' => 1]
        ]);
        
        $widgetInstanceId = $addResponse->json('widget_instance.id');

        // Then remove it
        $response = $this->deleteJson("/api/v1/dashboard/widgets/{$widgetInstanceId}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_update_widget_configuration()
    {
        // First create a dashboard for the user
        $this->getJson('/api/v1/dashboard'); // This will create a default dashboard
        
        // Then add a widget
        $widget = DashboardWidget::first();
        $addResponse = $this->postJson('/api/v1/dashboard/widgets', [
            'widget_id' => $widget->id,
            'position' => ['x' => 0, 'y' => 0],
            'size' => ['width' => 2, 'height' => 1]
        ]);
        
        $widgetInstanceId = $addResponse->json('widget_instance.id');

        // Update configuration
        $response = $this->putJson("/api/v1/dashboard/widgets/{$widgetInstanceId}", [
            'config' => [
                'title' => 'Updated Title',
                'size' => 'extra-large'
            ]
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_update_dashboard_layout()
    {
        // Add multiple widgets first
        $widget1 = DashboardWidget::first();
        $widget2 = DashboardWidget::skip(1)->first();
        
        $this->postJson('/api/v1/dashboard/widgets', ['widget_id' => $widget1->id]);
        $this->postJson('/api/v1/dashboard/widgets', ['widget_id' => $widget2->id]);

        // Get current dashboard
        $dashboardResponse = $this->getJson('/api/v1/dashboard');
        $layout = $dashboardResponse->json('data.layout');

        // Update layout positions
        $layout[0]['position'] = ['x' => 0, 'y' => 0];
        $layout[1]['position'] = ['x' => 6, 'y' => 0];

        $response = $this->putJson('/api/v1/dashboard/layout', [
            'layout_config' => $layout
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_get_user_alerts()
    {
        $response = $this->getJson('/api/v1/dashboard/alerts');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'type',
                            'severity',
                            'message',
                            'is_read',
                            'triggered_at',
                            'context'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_mark_alert_as_read()
    {
        $alert = DashboardAlert::where('user_id', $this->user->id)->first();
        
        $response = $this->putJson("/api/v1/dashboard/alerts/{$alert->id}/read");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_mark_all_alerts_as_read()
    {
        $response = $this->putJson('/api/v1/dashboard/alerts/read-all');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_get_dashboard_metrics()
    {
        $response = $this->getJson('/api/v1/dashboard/metrics');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'code',
                            'name',
                            'category',
                            'unit',
                            'value',
                            'display_config',
                            'recorded_at'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_save_user_preferences()
    {
        $preferences = [
            'theme' => 'dark',
            'refresh_interval' => 60,
            'compact_mode' => true,
            'show_widget_borders' => false
        ];

        $response = $this->postJson('/api/v1/dashboard/preferences', [
            'preferences' => $preferences
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_reset_dashboard_to_default()
    {
        // Add custom widgets first
        $widget = DashboardWidget::first();
        $this->postJson('/api/v1/dashboard/widgets', ['widget_id' => $widget->id]);

        $response = $this->postJson('/api/v1/dashboard/reset');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_get_role_based_dashboard()
    {
        $response = $this->getJson('/api/v1/dashboard/role-based');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'dashboard',
                        'widgets',
                        'metrics',
                        'alerts',
                        'permissions',
                        'role_config',
                        'project_context'
                    ],
                    'meta' => [
                        'user_role',
                        'project_context',
                        'generated_at'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_role_specific_widgets()
    {
        $response = $this->getJson('/api/v1/dashboard/role-based/widgets');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'widgets',
                        'role_config',
                        'total_count'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_role_specific_metrics()
    {
        $response = $this->getJson('/api/v1/dashboard/role-based/metrics');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'metrics',
                        'time_range',
                        'total_count'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_role_specific_alerts()
    {
        $response = $this->getJson('/api/v1/dashboard/role-based/alerts');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'alerts',
                        'total_count',
                        'filters'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_role_permissions()
    {
        $response = $this->getJson('/api/v1/dashboard/role-based/permissions');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'permissions',
                        'user_role',
                        'role_name'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_role_configuration()
    {
        $response = $this->getJson('/api/v1/dashboard/role-based/role-config');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'role_config',
                        'user_role',
                        'tenant_id'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_available_projects()
    {
        $response = $this->getJson('/api/v1/dashboard/role-based/projects');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'projects',
                        'total_count',
                        'role_access'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_dashboard_summary()
    {
        $response = $this->getJson('/api/v1/dashboard/role-based/summary');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user_info',
                        'project_context',
                        'generated_at'
                    ]
                ]);
    }

    /** @test */
    public function it_can_switch_project_context()
    {
        $response = $this->postJson('/api/v1/dashboard/role-based/switch-project', [
            'project_id' => $this->project->id
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'dashboard',
                        'project_context',
                        'switched_at'
                    ],
                    'message'
                ]);
    }

    /** @test */
    public function it_can_get_customizable_dashboard()
    {
        $response = $this->getJson('/api/v1/dashboard/customization/');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'dashboard',
                        'available_widgets',
                        'widget_categories',
                        'layout_templates',
                        'customization_options',
                        'permissions'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_available_widgets_for_customization()
    {
        $response = $this->getJson('/api/v1/dashboard/customization/widgets');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'widgets',
                        'categories'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_layout_templates()
    {
        $response = $this->getJson('/api/v1/dashboard/customization/templates');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'templates',
                        'permissions'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_customization_options()
    {
        $response = $this->getJson('/api/v1/dashboard/customization/options');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'widget_sizes',
                        'layout_grid',
                        'themes',
                        'refresh_intervals',
                        'permissions'
                    ]
                ]);
    }

    /** @test */
    public function it_can_add_widget_via_customization()
    {
        $widget = DashboardWidget::first();
        
        $response = $this->postJson('/api/v1/dashboard/customization/widgets', [
            'widget_id' => $widget->id,
            'config' => [
                'title' => 'Custom Project Overview',
                'size' => 'large'
            ]
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'widget_instance',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_remove_widget_via_customization()
    {
        // First add a widget
        $widget = DashboardWidget::first();
        $addResponse = $this->postJson('/api/v1/dashboard/customization/widgets', [
            'widget_id' => $widget->id
        ]);
        
        $widgetInstanceId = $addResponse->json('widget_instance.id');

        // Then remove it
        $response = $this->deleteJson("/api/v1/dashboard/customization/widgets/{$widgetInstanceId}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_update_widget_config_via_customization()
    {
        // First add a widget
        $widget = DashboardWidget::first();
        $addResponse = $this->postJson('/api/v1/dashboard/customization/widgets', [
            'widget_id' => $widget->id
        ]);
        
        $widgetInstanceId = $addResponse->json('widget_instance.id');

        // Update configuration
        $response = $this->putJson("/api/v1/dashboard/customization/widgets/{$widgetInstanceId}/config", [
            'config' => [
                'title' => 'Updated Title',
                'size' => 'extra-large'
            ]
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_update_layout_via_customization()
    {
        // Add multiple widgets first
        $widget1 = DashboardWidget::first();
        $widget2 = DashboardWidget::skip(1)->first();
        
        $this->postJson('/api/v1/dashboard/customization/widgets', ['widget_id' => $widget1->id]);
        $this->postJson('/api/v1/dashboard/customization/widgets', ['widget_id' => $widget2->id]);

        // Get current dashboard
        $dashboardResponse = $this->getJson('/api/v1/dashboard/customization/');
        $layout = $dashboardResponse->json('data.dashboard.layout');

        // Update layout positions
        $layout[0]['position'] = ['x' => 0, 'y' => 0];
        $layout[1]['position'] = ['x' => 6, 'y' => 0];

        $response = $this->putJson('/api/v1/dashboard/customization/layout', [
            'layout' => $layout
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'layout',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_apply_layout_template()
    {
        $response = $this->postJson('/api/v1/dashboard/customization/apply-template', [
            'template_id' => 'project_manager'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'layout',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_save_preferences_via_customization()
    {
        $preferences = [
            'theme' => 'dark',
            'refresh_interval' => 60,
            'compact_mode' => true,
            'show_widget_borders' => false
        ];

        $response = $this->postJson('/api/v1/dashboard/customization/preferences', [
            'preferences' => $preferences
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'preferences',
                    'message'
                ]);
    }

    /** @test */
    public function it_can_export_dashboard()
    {
        $response = $this->getJson('/api/v1/dashboard/customization/export');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'version',
                        'exported_at',
                        'user_role',
                        'dashboard',
                        'widgets'
                    ]
                ]);
    }

    /** @test */
    public function it_can_import_dashboard()
    {
        $dashboardConfig = [
            'version' => '1.0',
            'exported_at' => now()->toISOString(),
            'user_role' => 'project_manager',
            'dashboard' => [
                'name' => 'Imported Dashboard',
                'layout' => [],
                'preferences' => ['theme' => 'dark']
            ],
            'widgets' => []
        ];

        $response = $this->postJson('/api/v1/dashboard/customization/import', [
            'dashboard_config' => $dashboardConfig
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data'
                ]);
    }

    /** @test */
    public function it_can_reset_dashboard_via_customization()
    {
        // Add custom widgets first
        $widget = DashboardWidget::first();
        $this->postJson('/api/v1/dashboard/customization/widgets', ['widget_id' => $widget->id]);

        $response = $this->postJson('/api/v1/dashboard/customization/reset');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'layout',
                    'message'
                ]);
    }

    /** @test */
    public function it_validates_widget_permissions()
    {
        // Create QC Inspector user
        $qcUser = User::create([
            'name' => 'QC Inspector',
            'email' => 'qc@example.com',
            'password' => Hash::make('password'),
            'role' => 'qc_inspector',
            'tenant_id' => $this->tenant->id
        ]);

        Sanctum::actingAs($qcUser);

        $widget = DashboardWidget::first();
        
        $response = $this->postJson('/api/v1/dashboard/customization/widgets', [
            'widget_id' => $widget->id
        ]);

        $response->assertStatus(500)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function it_handles_invalid_project_context()
    {
        $response = $this->postJson('/api/v1/dashboard/role-based/switch-project', [
            'project_id' => 'invalid-project-id'
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors'
                ]);
    }

    /** @test */
    public function it_handles_unauthorized_access()
    {
        // Create user without project access
        $unauthorizedUser = User::create([
            'name' => 'Unauthorized User',
            'email' => 'unauthorized@example.com',
            'password' => Hash::make('password'),
            'role' => 'client_rep',
            'tenant_id' => $this->tenant->id
        ]);

        Sanctum::actingAs($unauthorizedUser);

        $response = $this->postJson('/api/v1/dashboard/role-based/switch-project', [
            'project_id' => $this->project->id
        ]);

        $response->assertStatus(403)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function it_handles_missing_widget()
    {
        $response = $this->postJson('/api/v1/dashboard/customization/widgets', [
            'widget_id' => 'non-existent-widget-id'
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors'
                ]);
    }

    /** @test */
    public function it_handles_invalid_layout_template()
    {
        $response = $this->postJson('/api/v1/dashboard/customization/apply-template', [
            'template_id' => 'non-existent-template'
        ]);

        $response->assertStatus(500)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    /** @test */
    public function it_handles_invalid_import_data()
    {
        $response = $this->postJson('/api/v1/dashboard/customization/import', [
            'dashboard_config' => [
                'version' => 'invalid-version'
            ]
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors'
                ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        // Clear authentication by not calling Sanctum::actingAs()
        // Also clear any existing authentication
        $this->app['auth']->forgetGuards();
        
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_database_errors_gracefully()
    {
        // Mock database error
        \DB::shouldReceive('beginTransaction')->andThrow(new \Exception('Database error'));

        $widget = DashboardWidget::first();
        
        $response = $this->postJson('/api/v1/dashboard/customization/widgets', [
            'widget_id' => $widget->id
        ]);

        $response->assertStatus(500)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }
}
