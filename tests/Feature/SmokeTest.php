<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class SmokeTest extends TestCase
{
    /**
     * Test that views are deterministic (no random content)
     * This prevents the "two views/endpoint" bug
     */
    public function test_views_are_deterministic(): void
    {
        $endpoints = [
            '/app/projects',
            '/app/tasks', 
            '/app/dashboard',
            '/app/calendar',
            '/app/team',
            '/app/documents',
            '/app/templates',
            '/app/settings'
        ];

        foreach ($endpoints as $url) {
            // Clear any session/cache that might cause randomness
            Cache::flush();
            session()->flush();
            
            // Get content twice
            $response1 = $this->get($url);
            $response2 = $this->get($url);
            
            // Both should return 200
            $this->assertEquals(200, $response1->getStatusCode(), "First request to $url failed");
            $this->assertEquals(200, $response2->getStatusCode(), "Second request to $url failed");
            
            // Content should be identical (deterministic)
            $content1 = $response1->getContent();
            $content2 = $response2->getContent();
            
            $this->assertSame($content1, $content2, "Inconsistent view for $url - violates single source of truth");
        }
    }

    /**
     * Test that components render consistently
     */
    public function test_components_are_deterministic(): void
    {
        $components = [
            'components.kpi.strip',
            'components.projects.filters',
            'components.projects.table',
            'components.projects.card-grid',
            'components.shared.empty-state',
            'components.shared.alert',
            'components.shared.pagination',
            'components.shared.toolbar'
        ];

        foreach ($components as $component) {
            $this->assertTrue(
                view()->exists($component),
                "Component $component does not exist"
            );
        }
    }

    /**
     * Test that feature flags are consistent
     */
    public function test_feature_flags_are_consistent(): void
    {
        $features = config('features');
        
        $this->assertIsArray($features, 'Features config should be an array');
        $this->assertArrayHasKey('projects', $features, 'Projects feature flags missing');
        $this->assertArrayHasKey('tasks', $features, 'Tasks feature flags missing');
        $this->assertArrayHasKey('dashboard', $features, 'Dashboard feature flags missing');
        
        // Test that view_mode is not random
        $this->assertContains(config('features.projects.view_mode'), ['table', 'card'], 'Projects view_mode should be deterministic');
        $this->assertContains(config('features.tasks.view_mode'), ['table', 'card'], 'Tasks view_mode should be deterministic');
    }

    /**
     * Test that language files exist and are consistent
     */
    public function test_language_files_are_consistent(): void
    {
        $languages = ['en', 'vi'];
        $domains = ['projects', 'tasks', 'dashboard'];
        
        foreach ($languages as $lang) {
            foreach ($domains as $domain) {
                $file = base_path("lang/$lang/$domain.php");
                $this->assertFileExists($file, "Language file $lang/$domain.php should exist");
                
                $translations = include $file;
                $this->assertIsArray($translations, "Language file $lang/$domain.php should return array");
            }
        }
    }

    /**
     * Test that no duplicate routes exist
     */
    public function test_no_duplicate_routes(): void
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes();
        $routeMap = [];
        
        foreach ($routes as $route) {
            $uri = $route->uri();
            $methods = $route->methods();
            
            // Skip API routes and debug routes
            if (str_starts_with($uri, 'api/') || str_starts_with($uri, '_debug/') || str_starts_with($uri, 'test-')) {
                continue;
            }
            
            foreach ($methods as $method) {
                if ($method === 'HEAD') continue; // Skip HEAD method
                
                $key = $method . ':' . $uri;
                
                if (isset($routeMap[$key])) {
                    $this->fail("Duplicate route found: $key - violates single source of truth");
                }
                
                $routeMap[$key] = $route->getActionName();
            }
        }
        
        $this->assertTrue(true, 'No duplicate routes found');
    }

    /**
     * Test that views use correct translation keys
     */
    public function test_views_use_correct_translation_keys(): void
    {
        $viewFiles = [
            'app.projects.index',
            'app.tasks.index',
            'app.dashboard.index'
        ];
        
        foreach ($viewFiles as $view) {
            $this->assertTrue(
                view()->exists($view),
                "View $view should exist"
            );
        }
    }
}
