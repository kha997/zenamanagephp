<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\File;

class ComponentStructureTest extends TestCase
{
    /**
     * Test that KPI components are properly structured
     */
    public function test_kpi_components_structure(): void
    {
        // Test that base KPI component exists
        $this->assertFileExists(
            base_path('resources/views/components/kpi/strip.blade.php'),
            'Base KPI strip component should exist'
        );
        
        // Test that dashboard KPI card exists (separate component)
        $this->assertFileExists(
            base_path('resources/views/components/dashboard/charts/dashboard-kpi-card.blade.php'),
            'Dashboard KPI card component should exist'
        );
        
        // Test that old dashboard kpi-strip is removed
        $this->assertFileDoesNotExist(
            base_path('resources/views/components/dashboard/charts/kpi-strip.blade.php'),
            'Old dashboard kpi-strip should be removed'
        );
        
        // Test that we have only one global KPI strip component
        $this->assertFileExists(
            base_path('resources/views/components/kpi/strip.blade.php'),
            'Only one global KPI strip component should exist'
        );
    }
    
    /**
     * Test that header components are properly structured
     */
    public function test_header_components_structure(): void
    {
        $headerComponents = [
            'resources/views/components/admin/header.blade.php',
            'resources/views/components/shared/header.blade.php',
        ];
        
        foreach ($headerComponents as $component) {
            $this->assertFileExists(
                base_path($component),
                "Header component {$component} should exist"
            );
        }
        
        // Test that header components have different purposes
        $adminHeader = File::get(base_path('resources/views/components/admin/header.blade.php'));
        $sharedHeader = File::get(base_path('resources/views/components/shared/header.blade.php'));
        
        $this->assertStringContainsString('Admin Panel', $adminHeader, 'Admin header should contain admin branding');
        $this->assertStringContainsString('Hello,', $sharedHeader, 'Shared header should contain greeting');
        $this->assertStringContainsString('notificationsOpen', $sharedHeader, 'Shared header should contain notifications');
    }
    
    /**
     * Test that export component is properly renamed
     */
    public function test_export_component_naming(): void
    {
        // Test that old name doesn't exist
        $this->assertFileDoesNotExist(
            base_path('resources/views/components/shared/export-component.blade.php'),
            'Old export-component.blade.php should not exist'
        );
        
        // Test that new name exists
        $this->assertFileExists(
            base_path('resources/views/components/shared/export.blade.php'),
            'New export.blade.php should exist'
        );
        
        // Test that export component has proper content
        $exportContent = File::get(base_path('resources/views/components/shared/export.blade.php'));
        $this->assertStringContainsString('export-component', $exportContent, 'Export component should contain export-component class');
    }
    
    /**
     * Test that navigation components are properly structured
     */
    public function test_navigation_components_structure(): void
    {
        // Test layouts navigation
        $this->assertFileExists(
            base_path('resources/views/layouts/navigation.blade.php'),
            'Layouts navigation should exist'
        );
        
        // Test components navigation
        $navigationComponents = [
            'resources/views/components/shared/navigation/navigation.blade.php',
            'resources/views/components/shared/navigation/admin-nav.blade.php',
            'resources/views/components/shared/navigation/tenant-nav.blade.php',
            'resources/views/components/shared/navigation/breadcrumb.blade.php',
            'resources/views/components/shared/navigation/sidebar.blade.php',
            'resources/views/components/shared/navigation/dynamic-sidebar.blade.php',
            'resources/views/components/shared/navigation/universal-navigation.blade.php',
        ];
        
        foreach ($navigationComponents as $component) {
            $this->assertFileExists(
                base_path($component),
                "Navigation component {$component} should exist"
            );
        }
        
        // Test that layouts and components navigation serve different purposes
        $layoutsNav = File::get(base_path('resources/views/layouts/navigation.blade.php'));
        $componentsNav = File::get(base_path('resources/views/components/shared/navigation/navigation.blade.php'));
        
        $this->assertStringContainsString('Enhanced Navigation', $layoutsNav, 'Layouts navigation should be simple');
        $this->assertStringContainsString('zena-main-nav', $componentsNav, 'Components navigation should be comprehensive');
    }
    
    /**
     * Test that documentation files exist
     */
    public function test_component_documentation_exists(): void
    {
        $docsFiles = [
            'resources/views/components/HEADER_COMPONENTS_DOCS.md',
            'resources/views/components/NAVIGATION_COMPONENTS_DOCS.md',
        ];
        
        foreach ($docsFiles as $docFile) {
            $this->assertFileExists(
                base_path($docFile),
                "Documentation file {$docFile} should exist"
            );
        }
    }
    
    /**
     * Test component naming consistency
     */
    public function test_component_naming_consistency(): void
    {
        $sharedComponents = [
            'alert.blade.php',
            'empty-state.blade.php',
            'export.blade.php', // Should be renamed from export-component.blade.php
            'pagination.blade.php',
            'toolbar.blade.php',
        ];
        
        foreach ($sharedComponents as $component) {
            $this->assertFileExists(
                base_path("resources/views/components/shared/{$component}"),
                "Shared component {$component} should exist"
            );
        }
        
        // Test that export component was properly renamed
        $this->assertFileDoesNotExist(
            base_path('resources/views/components/shared/export-component.blade.php'),
            'Old export-component.blade.php should not exist'
        );
        
        $this->assertFileExists(
            base_path('resources/views/components/shared/export.blade.php'),
            'New export.blade.php should exist'
        );
    }
    
    /**
     * Test overall component structure compliance
     */
    public function test_component_structure_compliance(): void
    {
        $complianceChecks = [
            'KPI Components Structured' => $this->checkKpiStructure(),
            'Header Components Clarified' => $this->checkHeaderStructure(),
            'Export Component Renamed' => $this->checkExportNaming(),
            'Navigation Components Organized' => $this->checkNavigationStructure(),
            'Documentation Created' => $this->checkDocumentation(),
            'Naming Consistency Applied' => $this->checkNamingConsistency(),
        ];
        
        $passedChecks = array_filter($complianceChecks);
        $totalChecks = count($complianceChecks);
        $passedCount = count($passedChecks);
        
        $this->assertEquals(
            $totalChecks,
            $passedCount,
            "Component structure compliance check failed: $passedCount/$totalChecks passed. Failed: " . 
            implode(', ', array_keys(array_diff($complianceChecks, $passedChecks)))
        );
    }
    
    /**
     * Check KPI structure
     */
    private function checkKpiStructure(): bool
    {
        return File::exists(base_path('resources/views/components/kpi/strip.blade.php')) &&
               File::exists(base_path('resources/views/components/dashboard/charts/dashboard-kpi-card.blade.php')) &&
               !File::exists(base_path('resources/views/components/dashboard/charts/kpi-strip.blade.php'));
    }
    
    /**
     * Check header structure
     */
    private function checkHeaderStructure(): bool
    {
        return File::exists(base_path('resources/views/components/admin/header.blade.php')) &&
               File::exists(base_path('resources/views/components/shared/header.blade.php')) &&
               !File::exists(base_path('resources/views/components/shared/universal-header.blade.php'));
    }
    
    /**
     * Check export naming
     */
    private function checkExportNaming(): bool
    {
        return !File::exists(base_path('resources/views/components/shared/export-component.blade.php')) &&
               File::exists(base_path('resources/views/components/shared/export.blade.php'));
    }
    
    /**
     * Check navigation structure
     */
    private function checkNavigationStructure(): bool
    {
        return File::exists(base_path('resources/views/layouts/navigation.blade.php')) &&
               File::exists(base_path('resources/views/components/shared/navigation/navigation.blade.php'));
    }
    
    /**
     * Check documentation
     */
    private function checkDocumentation(): bool
    {
        return File::exists(base_path('resources/views/components/HEADER_COMPONENTS_DOCS.md')) &&
               File::exists(base_path('resources/views/components/NAVIGATION_COMPONENTS_DOCS.md'));
    }
    
    /**
     * Check naming consistency
     */
    private function checkNamingConsistency(): bool
    {
        $sharedComponents = ['alert.blade.php', 'empty-state.blade.php', 'export.blade.php', 'pagination.blade.php', 'toolbar.blade.php'];
        
        foreach ($sharedComponents as $component) {
            if (!File::exists(base_path("resources/views/components/shared/{$component}"))) {
                return false;
            }
        }
        
        return true;
    }
}
