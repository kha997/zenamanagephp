<?php declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GAP-035 — permanent duplicate route-name guard and the 27-entry
 * behavior-preservation contract.
 *
 * Design: docs/owner-decisions/GAP-035/02-design-v2.md
 *
 * The core guard (test_no_duplicate_route_names) is fully generic — it
 * inspects the entire live route collection dynamically and is not
 * limited to an enumerated allowlist of the 7 groups GAP-035 fixed. A
 * future route declared anywhere with a name that collides with any
 * other route's name fails this test, regardless of what that name is.
 */
final class RouteNameCollisionInvariantTest extends TestCase
{
    /**
     * Baseline captured after GAP-035's renames landed: name => [method, uri, handler].
     * Middleware is asserted separately via gatherMiddleware() (§ below),
     * since it must reflect fully-resolved runtime middleware, not a
     * static string easily kept in sync by hand.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const EXPECTED_ROUTES = [
        // Groups 1-5 — preserved API-side names, unchanged.
        'projects.store' => ['POST', 'api/projects', 'App\Http\Controllers\Api\ProjectController@store'],
        'projects.show' => ['GET', 'api/projects/{project}', 'App\Http\Controllers\Api\ProjectController@show'],
        'projects.update' => ['PUT', 'api/projects/{project}', 'App\Http\Controllers\Api\ProjectController@update'],
        'projects.destroy' => ['DELETE', 'api/projects/{project}', 'App\Http\Controllers\Api\ProjectController@destroy'],
        'tasks.store' => ['POST', 'api/tasks', 'App\Http\Controllers\Api\TaskController@store'],

        // Groups 1-5 — renamed web-side (web.* prefix), method/URI/handler unchanged.
        'web.projects.store' => ['POST', 'projects', 'Closure'],
        'web.projects.show' => ['GET', 'projects/{project}', 'Closure'],
        'web.projects.update' => ['PUT', 'projects/{project}', 'Closure'],
        'web.projects.destroy' => ['DELETE', 'projects/{project}', 'Closure'],
        'web.tasks.store' => ['POST', 'tasks', 'App\Http\Controllers\Web\TaskController@store'],

        // Group 6 — api.v1.dashboard.* (12 previously-unnamed leaves).
        'api.v1.dashboard.users-v2.index' => ['GET', 'api/v1/dashboard/users-v2', 'App\Http\Controllers\UserControllerV2@index'],
        'api.v1.dashboard.users-v2.store' => ['POST', 'api/v1/dashboard/users-v2', 'App\Http\Controllers\UserControllerV2@store'],
        'api.v1.dashboard.users-v2.profile' => ['GET', 'api/v1/dashboard/users-v2/profile', 'App\Http\Controllers\UserControllerV2@profile'],
        'api.v1.dashboard.users-v2.show' => ['GET', 'api/v1/dashboard/users-v2/{id}', 'App\Http\Controllers\UserControllerV2@show'],
        'api.v1.dashboard.users-v2.update' => ['PUT', 'api/v1/dashboard/users-v2/{id}', 'App\Http\Controllers\UserControllerV2@update'],
        'api.v1.dashboard.users-v2.destroy' => ['DELETE', 'api/v1/dashboard/users-v2/{id}', 'App\Http\Controllers\UserControllerV2@destroy'],
        'api.v1.dashboard.tasks.assignments.index' => ['GET', 'api/v1/dashboard/tasks/{taskId}/assignments', 'App\Http\Controllers\Api\TaskAssignmentController@getTaskAssignments'],
        'api.v1.dashboard.tasks.assignments.store' => ['POST', 'api/v1/dashboard/tasks/{taskId}/assignments', 'App\Http\Controllers\Api\TaskAssignmentController@store'],
        'api.v1.dashboard.assignments.update' => ['PUT', 'api/v1/dashboard/assignments/{assignmentId}', 'App\Http\Controllers\Api\TaskAssignmentController@update'],
        'api.v1.dashboard.assignments.destroy' => ['DELETE', 'api/v1/dashboard/assignments/{assignmentId}', 'App\Http\Controllers\Api\TaskAssignmentController@destroy'],
        'api.v1.dashboard.users.assignments.index' => ['GET', 'api/v1/dashboard/users/{userId}/assignments', 'App\Http\Controllers\Api\TaskAssignmentController@getUserAssignments'],
        'api.v1.dashboard.users.assignments.stats' => ['GET', 'api/v1/dashboard/users/{userId}/assignments/stats', 'App\Http\Controllers\Api\TaskAssignmentController@getUserStats'],

        // Group 7 — api.zena.debug.* (5 previously-unnamed leaves).
        'api.zena.debug.simple-test' => ['GET', 'api/zena/simple-test', 'Closure'],
        'api.zena.debug.minimal-auth-test' => ['GET', 'api/zena/minimal-auth-test', 'Closure'],
        'api.zena.debug.sanctum-auth-test' => ['GET', 'api/zena/sanctum-auth-test', 'Closure'],
        'api.zena.debug.me-test' => ['GET', 'api/zena/me-test', 'Closure'],
        'api.zena.debug.auth-test' => ['GET', 'api/zena/auth-test', 'Closure'],
    ];

    /**
     * Middleware baseline, verified live via gatherMiddleware() (resolved
     * stack, not the raw declared string) after GAP-035's renames landed.
     *
     * @var array<string, list<string>>
     */
    private const EXPECTED_MIDDLEWARE = [
        'projects.store' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac'],
        'projects.show' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac'],
        'projects.update' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac'],
        'projects.destroy' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac'],
        'tasks.store' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac'],
        'web.projects.store' => ['web', 'auth', 'tenant.isolation'],
        'web.projects.show' => ['web', 'auth', 'tenant.isolation', 'rbac:project.view'],
        'web.projects.update' => ['web', 'auth', 'tenant.isolation', 'rbac:project.update'],
        'web.projects.destroy' => ['web', 'auth', 'tenant.isolation', 'rbac:project.delete'],
        'web.tasks.store' => ['web', 'auth', 'tenant.isolation', 'rbac:task.create'],
        'api.v1.dashboard.users-v2.index' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac', 'input.sanitization', 'error.envelope', 'production.security', 'simple.jwt.auth'],
        'api.v1.dashboard.users-v2.store' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac', 'input.sanitization', 'error.envelope', 'production.security', 'simple.jwt.auth'],
        'api.v1.dashboard.users-v2.profile' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac', 'input.sanitization', 'error.envelope', 'production.security', 'simple.jwt.auth'],
        'api.v1.dashboard.users-v2.show' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac', 'input.sanitization', 'error.envelope', 'production.security', 'simple.jwt.auth'],
        'api.v1.dashboard.users-v2.update' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac', 'input.sanitization', 'error.envelope', 'production.security', 'simple.jwt.auth'],
        'api.v1.dashboard.users-v2.destroy' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac', 'input.sanitization', 'error.envelope', 'production.security', 'simple.jwt.auth'],
        'api.v1.dashboard.tasks.assignments.index' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac', 'input.sanitization', 'error.envelope'],
        'api.v1.dashboard.tasks.assignments.store' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac', 'input.sanitization', 'error.envelope'],
        'api.v1.dashboard.assignments.update' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac', 'input.sanitization', 'error.envelope'],
        'api.v1.dashboard.assignments.destroy' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac', 'input.sanitization', 'error.envelope'],
        'api.v1.dashboard.users.assignments.index' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac', 'input.sanitization', 'error.envelope'],
        'api.v1.dashboard.users.assignments.stats' => ['api', 'auth:sanctum', 'tenant.isolation', 'rbac', 'input.sanitization', 'error.envelope'],
        'api.zena.debug.simple-test' => ['api', 'auth:sanctum', 'tenant.isolation', 'input.sanitization', 'error.envelope', 'rbac:auth.test.simple'],
        'api.zena.debug.minimal-auth-test' => ['api', 'auth:sanctum', 'tenant.isolation', 'input.sanitization', 'error.envelope', 'rbac:auth.test.minimal'],
        'api.zena.debug.sanctum-auth-test' => ['api', 'auth:sanctum', 'tenant.isolation', 'input.sanitization', 'error.envelope', 'rbac:auth.test.sanctum'],
        'api.zena.debug.me-test' => ['api', 'auth:sanctum', 'tenant.isolation', 'input.sanitization', 'error.envelope', 'rbac:auth.test.me'],
        'api.zena.debug.auth-test' => ['api', 'auth:sanctum', 'tenant.isolation', 'input.sanitization', 'error.envelope', 'rbac:auth.test.auth'],
    ];

    /**
     * The 5 API-side names GAP-035 deliberately left unchanged — the
     * currently-winning named-route resolution target for each, verified
     * live in Gate 2 before the design was approved.
     *
     * @var array<string, string>
     */
    private const PRESERVED_NAME_URIS = [
        'projects.store' => 'http://localhost/api/projects',
        'projects.show' => 'http://localhost/api/projects/X',
        'projects.update' => 'http://localhost/api/projects/X',
        'projects.destroy' => 'http://localhost/api/projects/X',
        'tasks.store' => 'http://localhost/api/tasks',
    ];

    // ------------------------------------------------------------------
    // The permanent, generic duplicate-name guard — GAP-035's core
    // deliverable. Not limited to the 7 groups this design fixed.
    // ------------------------------------------------------------------

    public function test_no_duplicate_route_names(): void
    {
        $byName = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null || $name === '') {
                continue;
            }
            $byName[$name][] = $route->uri();
        }

        $duplicates = array_filter($byName, fn (array $uris) => count($uris) > 1);

        $this->assertSame(
            [],
            array_keys($duplicates),
            'Duplicate route name(s) found: ' . implode(', ', array_map(
                fn (string $name) => "{$name} (" . implode(', ', $byName[$name]) . ')',
                array_keys($duplicates)
            ))
        );
    }

    // ------------------------------------------------------------------
    // 27-entry behavior-preservation contract
    // ------------------------------------------------------------------

    public function test_the_27_gap035_routes_preserve_method_uri_and_handler(): void
    {
        $this->assertCount(27, self::EXPECTED_ROUTES, 'Expected exactly 27 tracked route entries.');

        foreach (self::EXPECTED_ROUTES as $name => [$method, $uri, $handler]) {
            $route = collect(Route::getRoutes())->first(fn ($r) => $r->getName() === $name);

            $this->assertNotNull($route, "Expected a route named [{$name}] to exist.");
            $this->assertContains($method, $route->methods(), "Route [{$name}] method changed.");
            $this->assertSame($uri, $route->uri(), "Route [{$name}] URI changed.");
            $this->assertSame($handler, $route->getActionName(), "Route [{$name}] handler changed.");
        }
    }

    public function test_the_27_gap035_routes_preserve_middleware(): void
    {
        foreach (self::EXPECTED_MIDDLEWARE as $name => $expectedMiddleware) {
            $route = collect(Route::getRoutes())->first(fn ($r) => $r->getName() === $name);

            $this->assertNotNull($route, "Expected a route named [{$name}] to exist.");
            $this->assertSame(
                $expectedMiddleware,
                $route->gatherMiddleware(),
                "Route [{$name}] middleware stack changed."
            );
        }
    }

    public function test_preserved_names_resolve_to_the_same_api_side_endpoints(): void
    {
        $paramsByName = [
            'projects.store' => [],
            'projects.show' => ['project' => 'X'],
            'projects.update' => ['project' => 'X'],
            'projects.destroy' => ['project' => 'X'],
            'tasks.store' => [],
        ];

        foreach (self::PRESERVED_NAME_URIS as $name => $expectedUrl) {
            $this->assertSame($expectedUrl, route($name, $paramsByName[$name]), "route('{$name}') no longer resolves to its pre-GAP-035 endpoint.");
        }
    }

    public function test_already_unique_zena_names_are_untouched(): void
    {
        foreach (['api.zena.projects.store', 'api.zena.projects.show', 'api.zena.projects.update', 'api.zena.projects.destroy', 'api.zena.tasks.store'] as $name) {
            $route = collect(Route::getRoutes())->first(fn ($r) => $r->getName() === $name);
            $this->assertNotNull($route, "Expected the already-unique [{$name}] to still exist, unrenamed.");
        }
    }

    // ------------------------------------------------------------------
    // Zero duplicates including vendor routes, under testing + production
    // ------------------------------------------------------------------

    public function test_zero_duplicate_names_under_testing_and_production_including_vendor(): void
    {
        foreach (['testing', 'production'] as $env) {
            $result = Process::path(base_path())
                ->env(['APP_ENV' => $env])
                ->timeout(60)
                ->run(['php', 'artisan', 'route:list', '--json']);

            $this->assertTrue($result->successful(), "route:list failed under APP_ENV={$env}: " . $result->errorOutput());

            $decoded = $this->extractJsonArray($result->output());
            $this->assertIsArray($decoded, "route:list --json under APP_ENV={$env} did not return a JSON array.");

            $byName = [];
            foreach ($decoded as $r) {
                $name = $r['name'] ?? null;
                if ($name === null || $name === '') {
                    continue;
                }
                $byName[$name][] = $r['uri'];
            }
            $duplicates = array_filter($byName, fn (array $uris) => count($uris) > 1);

            $this->assertSame([], array_keys($duplicates), "Duplicate route name(s) found under APP_ENV={$env} (including vendor routes): " . implode(', ', array_keys($duplicates)));
        }
    }

    // ------------------------------------------------------------------
    // route:cache lifecycle — isolated, self-cleaning, both environments
    // ------------------------------------------------------------------

    /**
     * @group stress
     */
    public function test_route_cache_succeeds_and_preserves_the_27_entries_under_testing_and_production(): void
    {
        foreach (['testing', 'production'] as $env) {
            $this->assertRouteCacheSucceedsAndPreservesEntries($env);
        }
    }

    private function assertRouteCacheSucceedsAndPreservesEntries(string $env): void
    {
        $cachePath = base_path('bootstrap/cache/routes-v7.php');
        $preExisting = File::exists($cachePath);
        $backupPath = $preExisting ? $cachePath . '.gap035-test-backup' : null;

        if ($preExisting) {
            File::move($cachePath, $backupPath);
        }

        try {
            $cacheResult = Process::path(base_path())
                ->env(['APP_ENV' => $env])
                ->timeout(60)
                ->run(['php', 'artisan', 'route:cache']);

            $this->assertTrue($cacheResult->successful(), "route:cache failed under APP_ENV={$env}: " . $cacheResult->errorOutput() . $cacheResult->output());
            $this->assertTrue(File::exists($cachePath), "Expected a route cache file to be generated under APP_ENV={$env}.");

            $listResult = Process::path(base_path())
                ->env(['APP_ENV' => $env])
                ->timeout(60)
                ->run(['php', 'artisan', 'route:list', '--json']);

            $this->assertTrue($listResult->successful(), "Cached route:list failed under APP_ENV={$env}: " . $listResult->errorOutput());

            $decoded = $this->extractJsonArray($listResult->output());
            $this->assertIsArray($decoded, "Cached route:list --json under APP_ENV={$env} did not return a JSON array.");

            $namesPresent = collect($decoded)->pluck('name')->filter()->all();
            foreach (array_keys(self::EXPECTED_ROUTES) as $expectedName) {
                $this->assertContains($expectedName, $namesPresent, "Cached route table under APP_ENV={$env} is missing expected name [{$expectedName}].");
            }
        } finally {
            Process::path(base_path())->env(['APP_ENV' => $env])->run(['php', 'artisan', 'route:clear']);

            if (File::exists($cachePath)) {
                File::delete($cachePath);
            }

            if ($preExisting && $backupPath !== null && File::exists($backupPath)) {
                File::move($backupPath, $cachePath);
            }
        }
    }

    /**
     * `php artisan route:list --json` output can be preceded by unrelated
     * PHP startup warnings on some local toolchains — extract the JSON
     * array robustly by locating its outermost `[` ... `]` span.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function extractJsonArray(string $output): ?array
    {
        if (!preg_match('/\[.*\]/s', $output, $matches)) {
            return null;
        }

        $decoded = json_decode($matches[0], true);

        return is_array($decoded) ? $decoded : null;
    }
}
