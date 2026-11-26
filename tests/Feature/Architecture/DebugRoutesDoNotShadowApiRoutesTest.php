<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * DBG-1 guard (OWN-2026-024 diagnostics, 2026-11-26).
 *
 * RouteServiceProvider loads routes/debug_api.php AFTER routes/api.php (same `api` middleware + prefix) whenever the
 * environment is local/testing and app.debug is on — as in CI. A debug route with the same METHOD + URI REPLACES the real
 * route, silently dropping its permission gate and policy for every test run in such an environment. Production never loads
 * the debug file, so this is a test-truthfulness and local-safety guard, not a production behaviour change.
 *
 * Both files are loaded into throw-away routers (the Route facade is swapped back afterwards), with app.debug forced on only
 * for the debug file, so the guard is independent of the current environment. Any collision not recorded below fails, and a
 * recorded collision that disappears fails too, so the list cannot go stale.
 */
class DebugRoutesDoNotShadowApiRoutesTest extends TestCase
{
    /** Known collisions (DBG-1). Removing them needs an Owner decision on the debug stubs; never add to this list silently. */
    private const KNOWN_SHADOWED = [
        'GET api/documents', // real: SimpleDocumentController@index + rbac:document.view
        'POST api/documents', // real: SimpleDocumentController@store + rbac:document.create
        'GET api/documents-simple', // real: SimpleDocumentController@index (policy viewAny)
        'GET api/test', // real: diagnostic closure
    ];

    public function test_debug_api_routes_do_not_replace_real_api_routes(): void
    {
        $api = $this->routeKeys('routes/api.php', false);
        $debug = $this->routeKeys('routes/debug_api.php', true);
        $this->assertNotSame([], $api, 'routes/api.php produced no routes — the guard would be vacuous');
        $this->assertNotSame([], $debug, 'routes/debug_api.php produced no routes with debug on — the guard would be vacuous');

        $collisions = array_keys(array_intersect_key($debug, $api));
        sort($collisions);
        $known = self::KNOWN_SHADOWED;
        sort($known);

        $this->assertSame([], array_values(array_diff($collisions, $known)), 'NEW debug route(s) replace a real API route in debug-enabled environments: ' . implode(', ', array_diff($collisions, $known)));
        $this->assertSame([], array_values(array_diff($known, $collisions)), 'A recorded DBG-1 collision no longer exists — remove it from KNOWN_SHADOWED: ' . implode(', ', array_diff($known, $collisions)));
    }

    /** @return array<string, true> "METHOD uri" keys registered by $file under the api middleware + prefix */
    private function routeKeys(string $file, bool $debug): array
    {
        $original = Route::getFacadeRoot();
        $originalDebug = config('app.debug');
        $router = new Router(app('events'), app());
        Route::swap($router);
        config(['app.debug' => $debug]);

        try {
            $router->middleware('api')->prefix('api')->group(base_path($file));
        } finally {
            Route::swap($original);
            config(['app.debug' => $originalDebug]);
        }

        $keys = [];
        foreach ($router->getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                if ($method !== 'HEAD') {
                    $keys[$method . ' ' . $route->uri()] = true;
                }
            }
        }

        return $keys;
    }
}
