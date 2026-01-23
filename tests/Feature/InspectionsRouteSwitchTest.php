<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

final class InspectionsRouteSwitchTest extends TestCase
{
    private function inspectionRouteAction(string $uri, string $method = 'GET'): string
    {
        $request = Request::create($uri, $method);
        $route = app('router')->getRoutes()->match($request);

        return $route->getActionName();
    }

    private function assertInspectionsRoutesResolveTo(string $controllerClass): void
    {
        $actionPrefix = $controllerClass . '@';

        $this->assertSame($actionPrefix . 'index', $this->inspectionRouteAction('/api/zena/inspections'));
        $this->assertSame($actionPrefix . 'store', $this->inspectionRouteAction('/api/zena/inspections', 'POST'));
        $this->assertSame($actionPrefix . 'show', $this->inspectionRouteAction('/api/zena/inspections/123'));
        $this->assertSame($actionPrefix . 'update', $this->inspectionRouteAction('/api/zena/inspections/123', 'PUT'));
        $this->assertSame($actionPrefix . 'destroy', $this->inspectionRouteAction('/api/zena/inspections/123', 'DELETE'));
        $this->assertSame($actionPrefix . 'schedule', $this->inspectionRouteAction('/api/zena/inspections/123/schedule', 'POST'));
        $this->assertSame($actionPrefix . 'conduct', $this->inspectionRouteAction('/api/zena/inspections/123/conduct', 'POST'));
        $this->assertSame($actionPrefix . 'complete', $this->inspectionRouteAction('/api/zena/inspections/123/complete', 'POST'));
    }

    public function test_default_routes_use_legacy_inspection_controller(): void
    {
        putenv('API_CANONICAL_INSPECTIONS=0');
        $this->refreshApplication();

        $this->assertInspectionsRoutesResolveTo(
            \App\Http\Controllers\Api\InspectionController::class
        );
    }

    public function test_canonical_flag_switches_to_src_controller(): void
    {
        putenv('API_CANONICAL_INSPECTIONS=1');
        $this->refreshApplication();

        $this->assertInspectionsRoutesResolveTo(
            \Src\Quality\Controllers\InspectionController::class
        );

        putenv('API_CANONICAL_INSPECTIONS=0');
    }
}
