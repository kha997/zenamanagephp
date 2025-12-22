<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Route;

class DeterministicViewTest extends TestCase
{
    // Skip database migrations for this test
    protected function setUp(): void
    {
        parent::setUp();
        // Don't run migrations for this test
    }

    /** @test */
    public function projects_view_is_deterministic()
    {
        $r1 = $this->get('/app/projects')->getContent();
        $r2 = $this->get('/app/projects')->getContent();
        $this->assertSame($r1, $r2, "Projects view is not deterministic - violates single source of truth");
    }

    /** @test */
    public function tasks_view_is_deterministic()
    {
        $r1 = $this->get('/app/tasks')->getContent();
        $r2 = $this->get('/app/tasks')->getContent();
        $this->assertSame($r1, $r2, "Tasks view is not deterministic - violates single source of truth");
    }

    /** @test */
    public function dashboard_view_is_deterministic()
    {
        $r1 = $this->get('/app/dashboard')->getContent();
        $r2 = $this->get('/app/dashboard')->getContent();
        $this->assertSame($r1, $r2, "Dashboard view is not deterministic - violates single source of truth");
    }

    /** @test */
    public function calendar_view_is_deterministic()
    {
        $r1 = $this->get('/app/calendar')->getContent();
        $r2 = $this->get('/app/calendar')->getContent();
        $this->assertSame($r1, $r2, "Calendar view is not deterministic - violates single source of truth");
    }

    /** @test */
    public function team_view_is_deterministic()
    {
        $r1 = $this->get('/app/team')->getContent();
        $r2 = $this->get('/app/team')->getContent();
        $this->assertSame($r1, $r2, "Team view is not deterministic - violates single source of truth");
    }

    /** @test */
    public function documents_view_is_deterministic()
    {
        $r1 = $this->get('/app/documents')->getContent();
        $r2 = $this->get('/app/documents')->getContent();
        $this->assertSame($r1, $r2, "Documents view is not deterministic - violates single source of truth");
    }

    /** @test */
    public function templates_view_is_deterministic()
    {
        $r1 = $this->get('/app/templates')->getContent();
        $r2 = $this->get('/app/templates')->getContent();
        $this->assertSame($r1, $r2, "Templates view is not deterministic - violates single source of truth");
    }

    /** @test */
    public function settings_view_is_deterministic()
    {
        $r1 = $this->get('/app/settings')->getContent();
        $r2 = $this->get('/app/settings')->getContent();
        $this->assertSame($r1, $r2, "Settings view is not deterministic - violates single source of truth");
    }

    /** @test */
    public function no_duplicate_routes_exist()
    {
        $routeMap = [];
        foreach (Route::getRoutes()->getIterator() as $route) {
            $uri = $route->uri();
            $method = collect($route->methods())->first();

            // Exclude API routes and specific test routes
            if (str_starts_with($uri, 'api/') || str_starts_with($uri, '_debug/') || str_starts_with($uri, 'test-')) {
                continue;
            }

            $key = $method . ':' . $uri;

            if (isset($routeMap[$key])) {
                $this->fail("Duplicate route found: $key - violates single source of truth");
            }

            $routeMap[$key] = $route->getActionName();
        }
        $this->assertTrue(true); // If no duplicates found, pass the test
    }

    /** @test */
    public function all_app_views_exist()
    {
        $views = [
            'app.projects.index',
            'app.tasks.index',
            'app.dashboard.index',
            'app.calendar.index',
            'app.team.index',
            'app.documents.index',
            'app.templates.index',
            'app.settings.index',
            'app.clients.index',
            'app.quotes.index',
            'layouts.app-layout',
            'layouts.navigation'
        ];

        foreach ($views as $view) {
            $this->assertTrue(
                view()->exists($view),
                "View $view does not exist"
            );
        }
    }

    /** @test */
    public function all_components_exist()
    {
        $components = [
            'components.kpi.strip',
            'components.projects.filters',
            'components.projects.table',
            'components.projects.card-grid',
            'components.shared.empty-state',
            'components.shared.alert',
            'components.shared.pagination'
        ];

        foreach ($components as $component) {
            $this->assertTrue(
                view()->exists($component),
                "Component $component does not exist"
            );
        }
    }
}
