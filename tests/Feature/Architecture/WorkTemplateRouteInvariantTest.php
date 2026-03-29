<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkTemplateRouteInvariantTest extends TestCase
{
    public function test_work_template_v1_routes_stay_mounted_once_without_prefix_drift(): void
    {
        $routes = collect(Route::getRoutes())->filter(function ($route) {
            $uri = $route->uri();

            return $uri === 'api' . '/v1/work' . '-template'
                || Str::startsWith($uri, 'api' . '/v1/work' . '-template/');
        })->values();

        $this->assertCount(30, $routes, 'Expected the current WorkTemplate compatibility surface to stay mounted.');

        $duplicateKeys = $routes
            ->map(fn ($route) => implode('|', array_diff($route->methods(), ['HEAD'])) . ' ' . $route->uri())
            ->duplicates()
            ->values()
            ->all();

        $prefixDrift = $routes
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => Str::contains($uri, ['api' . '/v1/api' . '/v1', 'v1/v1']))
            ->values()
            ->all();

        $this->assertSame([], $duplicateKeys, 'WorkTemplate compatibility routes must not duplicate METHOD+URI pairs.');
        $this->assertSame([], $prefixDrift, 'WorkTemplate compatibility routes must not drift into double prefixes.');
    }
}
