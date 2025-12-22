<?php

namespace Tests\Feature\Routes;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RouteSnapshotTest extends TestCase
{
    /** @test */
    public function route_list_matches_snapshot(): void
    {
        Artisan::call('route:list', ['--json' => true]);
        $output = Artisan::output();
        
        // Normalize generated names to make snapshot stable
        $routes = json_decode($output, true);
        foreach ($routes as &$route) {
            if (isset($route['name']) && str_starts_with($route['name'], 'generated::')) {
                $route['name'] = 'generated::stable';
            }
        }
        $normalizedOutput = json_encode($routes, JSON_PRETTY_PRINT);
        
        $snapshot = base_path('tests/__snapshots__/routes.json');

        if (!file_exists($snapshot)) {
            if (!is_dir(dirname($snapshot))) {
                mkdir(dirname($snapshot), 0777, true);
            }
            file_put_contents($snapshot, $normalizedOutput);
            $this->markTestSkipped('Snapshot created. Re-run tests.');
        } else {
            $this->assertJsonStringEqualsJsonFile(
                $snapshot,
                $normalizedOutput,
                'Route list changed — verify duplicates or unintended drift.'
            );
        }
    }
}
