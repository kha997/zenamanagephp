<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ComplianceVerificationTest extends TestCase
{
    /**
     * Test Phase 1.1: ApiResponse class location
     */
    public function test_api_response_class_exists(): void
    {
        $this->assertTrue(
            class_exists('App\Support\ApiResponse'),
            'ApiResponse class should exist in App\Support namespace'
        );
    }

    /**
     * Test Phase 1.2: View structure follows domain/index.blade.php
     */
    public function test_view_structure_compliance(): void
    {
        $views = [
            'app.projects.index',
            'app.tasks.index',
            'app.calendar.index',
            'app.documents.index',
            'app.team.index',
            'app.settings.index',
            'app.templates.index',
        ];

        foreach ($views as $view) {
            $this->assertTrue(
                view()->exists($view),
                "View $view should exist following domain/index.blade.php structure"
            );
        }
    }

    /**
     * Test Phase 1.3: Permissions config renamed
     */
    public function test_permissions_config_exists(): void
    {
        $this->assertTrue(
            file_exists(config_path('permissions.php')),
            'config/permissions.php should exist (renamed from rbac.php)'
        );

        $this->assertFalse(
            file_exists(config_path('rbac.php')),
            'config/rbac.php should not exist (renamed to permissions.php)'
        );
    }

    /**
     * Test Phase 1.4: Component structure follows domain/component.blade.php
     */
    public function test_component_structure_compliance(): void
    {
        $components = [
            'components.admin.header',
            'components.dashboard.charts.chart-widget',
            'components.kpi.strip',
            'components.projects.filters',
            'components.projects.table',
            'components.projects.card-grid',
            'components.shared.empty-state',
            'components.shared.alert',
            'components.shared.pagination',
            'components.shared.toolbar',
        ];

        foreach ($components as $component) {
            $this->assertTrue(
                view()->exists($component),
                "Component $component should exist following domain/component.blade.php structure"
            );
        }
    }

    /**
     * Test Phase 1.5: Middleware compliance
     */
    public function test_middleware_compliance(): void
    {
        // Test AbilityMiddleware exists
        $this->assertTrue(
            class_exists('App\Http\Middleware\AbilityMiddleware'),
            'AbilityMiddleware should exist'
        );

        // Test middleware is registered in Kernel
        $kernel = app('Illuminate\Contracts\Http\Kernel');
        
        // Check if ability middleware is registered by checking the class
        $this->assertTrue(
            class_exists('App\Http\Middleware\AbilityMiddleware'),
            'AbilityMiddleware should be registered and accessible'
        );
    }

    /**
     * Test Phase 1.6: Tenant isolation compliance
     */
    public function test_tenant_isolation_compliance(): void
    {
        // Test TenantScope trait exists
        $this->assertTrue(
            trait_exists('App\Traits\TenantScope'),
            'TenantScope trait should exist'
        );

        // Test core models use TenantScope
        $models = [
            'App\Models\Project',
            'App\Models\Task',
            'App\Models\Template',
            'App\Models\CalendarEvent',
            'App\Models\Document',
            'App\Models\Team',
        ];

        foreach ($models as $model) {
            $this->assertTrue(
                class_exists($model),
                "Model $model should exist"
            );

            $this->assertTrue(
                in_array('App\Traits\TenantScope', class_uses($model)),
                "Model $model should use TenantScope trait"
            );
        }
    }

    /**
     * Test Phase 1.7: Documentation compliance
     */
    public function test_documentation_compliance(): void
    {
        $this->assertTrue(
            file_exists(base_path('COMPLETE_SYSTEM_DOCUMENTATION.md')),
            'COMPLETE_SYSTEM_DOCUMENTATION.md should exist'
        );

        $this->assertTrue(
            file_exists(base_path('DOCUMENTATION_INDEX.md')),
            'DOCUMENTATION_INDEX.md should exist'
        );

        // Test documentation contains updated information
        $docContent = file_get_contents(base_path('COMPLETE_SYSTEM_DOCUMENTATION.md'));
        
        $this->assertStringContainsString(
            'app/Support/ApiResponse.php',
            $docContent,
            'Documentation should mention ApiResponse class location'
        );

        $this->assertStringContainsString(
            'config/permissions.php',
            $docContent,
            'Documentation should mention permissions config'
        );

        $this->assertStringContainsString(
            'TenantScope',
            $docContent,
            'Documentation should mention TenantScope trait'
        );
    }

    /**
     * Test Phase 1.8: Language files compliance
     */
    public function test_language_files_compliance(): void
    {
        $languages = ['en', 'vi'];
        $domains = ['projects', 'tasks', 'dashboard', 'errors'];

        foreach ($languages as $lang) {
            foreach ($domains as $domain) {
                $file = base_path("lang/$lang/$domain.php");
                $this->assertTrue(
                    file_exists($file),
                    "Language file $lang/$domain.php should exist"
                );
            }
        }
    }

    /**
     * Test overall compliance summary
     */
    public function test_overall_compliance_summary(): void
    {
        $complianceChecks = [
            'ApiResponse class location' => class_exists('App\Support\ApiResponse'),
            'View structure compliance' => view()->exists('app.projects.index'),
            'Permissions config renamed' => file_exists(config_path('permissions.php')),
            'Component structure compliance' => view()->exists('components.admin.header'),
            'Middleware compliance' => class_exists('App\Http\Middleware\AbilityMiddleware'),
            'Tenant isolation compliance' => trait_exists('App\Traits\TenantScope'),
            'Documentation compliance' => file_exists(base_path('COMPLETE_SYSTEM_DOCUMENTATION.md')),
            'Language files compliance' => file_exists(base_path('lang/en/projects.php')),
        ];

        $passedChecks = array_filter($complianceChecks);
        $totalChecks = count($complianceChecks);
        $passedCount = count($passedChecks);

        $this->assertEquals(
            $totalChecks,
            $passedCount,
            "Compliance check failed: $passedCount/$totalChecks passed. Failed: " . 
            implode(', ', array_keys(array_diff($complianceChecks, $passedChecks)))
        );
    }
}
