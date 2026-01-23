<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

final class ProjectsRouteSwitchTest extends TestCase
{
    private function projectRouteAction(): string
    {
        $request = Request::create('/api/projects', 'GET');
        $route = app('router')->getRoutes()->match($request);

        return $route->getActionName();
    }

    public function test_default_routes_use_legacy_project_controller(): void
    {
        putenv('API_CANONICAL_PROJECTS=0');
        $this->refreshApplication();

        $this->assertSame(
            \App\Http\Controllers\Api\ProjectController::class . '@index',
            $this->projectRouteAction()
        );
    }

    public function test_canonical_flag_switches_to_src_controller(): void
    {
        putenv('API_CANONICAL_PROJECTS=1');
        $this->refreshApplication();

        $this->assertSame(
            \Src\CoreProject\Controllers\ProjectController::class . '@index',
            $this->projectRouteAction()
        );

        putenv('API_CANONICAL_PROJECTS=0');
    }
}
