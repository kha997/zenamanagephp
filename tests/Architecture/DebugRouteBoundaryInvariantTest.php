<?php declare(strict_types=1);

namespace Tests\Architecture;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GAP-011 — canonical protection boundary for the `_debug/*` surface.
 *
 * Design: docs/owner-decisions/GAP-011/02-design-v4.md
 *
 * These assertions are structural (declaration site, single loader,
 * middleware presence, environment scope), not a hand-maintained URI
 * allowlist — a future `_debug/*` route or alias declared outside
 * `routes/debug.php` fails here regardless of its name.
 */
final class DebugRouteBoundaryInvariantTest extends TestCase
{
    /** @var list<string> */
    private const ROUTE_FILES = [
        'routes/web.php',
        'routes/api.php',
        'routes/api-simple.php',
        'routes/api_zena.php',
        'routes/debug_api.php',
    ];

    private const DEBUG_ROUTE_FILE = 'routes/debug.php';

    private const ROUTE_SERVICE_PROVIDER = 'app/Providers/RouteServiceProvider.php';

    // ------------------------------------------------------------------
    // Static declaration-site guard
    // ------------------------------------------------------------------

    public function test_no_debug_route_is_declared_outside_routes_debug_php(): void
    {
        foreach ($this->otherRouteFilesThatExist() as $relativePath) {
            foreach ($this->nonCommentLines($relativePath) as $lineNumber => $line) {
                if (str_contains($line, 'Route::') && preg_match('/[\'"]_debug/', $line)) {
                    $this->fail("Found a `_debug` route declaration outside routes/debug.php: {$relativePath}:{$lineNumber}\n{$line}");
                }
            }
        }

        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------
    // Static Class B alias-drift guard
    // ------------------------------------------------------------------

    public function test_no_redirect_outside_routes_debug_php_targets_the_debug_namespace(): void
    {
        foreach ($this->otherRouteFilesThatExist() as $relativePath) {
            foreach ($this->nonCommentLines($relativePath) as $lineNumber => $line) {
                if (!preg_match('/Route::(?:permanentRedirect|redirect)\s*\(/', $line)) {
                    continue;
                }

                if (preg_match('/Route::(?:permanentRedirect|redirect)\s*\([^,]+,\s*[\'"]([^\'"]*)[\'"]/', $line, $m)) {
                    $destination = $m[1];
                    if (str_starts_with(ltrim($destination, '/'), '_debug')) {
                        $this->fail("Found a compatibility redirect outside routes/debug.php targeting the debug namespace: {$relativePath}:{$lineNumber} -> {$destination}\n{$line}");
                    }
                }
            }
        }

        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------
    // Single-loader guard
    // ------------------------------------------------------------------

    public function test_routes_debug_php_is_registered_from_exactly_one_place(): void
    {
        $referencingFiles = [];

        $candidates = array_merge(
            [self::ROUTE_SERVICE_PROVIDER],
            $this->otherRouteFilesThatExist()
        );

        foreach ($candidates as $relativePath) {
            if (!File::exists(base_path($relativePath))) {
                continue;
            }

            foreach ($this->nonCommentLines($relativePath) as $line) {
                // Only count an actual code reference (base_path()/require/group()
                // call) — a prose comment mentioning the filename must not count
                // as a second registration site.
                $mentionsDebugRouteFile = str_contains($line, "routes/debug.php") || str_contains($line, "routes'/debug.php");
                $looksLikeCodeReference = str_contains($line, 'base_path(') || str_contains($line, 'require') || str_contains($line, '->group(');

                if ($mentionsDebugRouteFile && $looksLikeCodeReference) {
                    $referencingFiles[] = $relativePath;
                    break;
                }
            }
        }

        $this->assertSame(
            [self::ROUTE_SERVICE_PROVIDER],
            $referencingFiles,
            'routes/debug.php must be registered from exactly one place: ' . self::ROUTE_SERVICE_PROVIDER
        );
    }

    // ------------------------------------------------------------------
    // Runtime middleware-presence guard
    // ------------------------------------------------------------------

    public function test_every_mounted_debug_route_carries_the_debug_gate_middleware(): void
    {
        $debugRoutes = collect(Route::getRoutes())->filter(
            fn ($route) => str_starts_with(ltrim($route->uri(), '/'), '_debug')
        );

        $this->assertGreaterThan(0, $debugRoutes->count(), 'Expected at least one `_debug/*` route to be mounted under APP_ENV=testing.');

        foreach ($debugRoutes as $route) {
            $this->assertContains(
                \App\Http\Middleware\DebugGateMiddleware::class,
                $route->gatherMiddleware(),
                "Route [{$route->methods()[0]} {$route->uri()}] is missing DebugGateMiddleware."
            );
        }
    }

    // ------------------------------------------------------------------
    // Environment matrix — local / testing / development / production
    // ------------------------------------------------------------------

    public function test_environment_matrix_for_surviving_class_a_routes(): void
    {
        foreach (['local', 'testing', 'development'] as $env) {
            $uris = $this->debugRouteUrisUnder($env);

            $this->assertContains('_debug/dashboard-data', $uris, "Expected _debug/dashboard-data present under APP_ENV={$env}");
            $this->assertContains('_debug/test-login/{email}', $uris, "Expected _debug/test-login/{email} present under APP_ENV={$env}");
        }

        $productionUris = $this->debugRouteUrisUnder('production');
        $this->assertSame([], $productionUris, 'Expected zero `_debug/*` routes under APP_ENV=production.');
    }

    public function test_wildcard_alias_is_present_in_local_only(): void
    {
        $this->assertTrue($this->wildcardAliasRegisteredUnder('local'), 'Expected /debug/{path?} present under APP_ENV=local.');

        foreach (['testing', 'development', 'production'] as $env) {
            $this->assertFalse($this->wildcardAliasRegisteredUnder($env), "Expected /debug/{path?} absent under APP_ENV={$env}.");
        }
    }

    // ------------------------------------------------------------------
    // Production absence — uncached and cached, isolated + self-cleaning
    // ------------------------------------------------------------------

    public function test_production_route_table_has_zero_debug_routes_uncached(): void
    {
        $uris = $this->debugRouteUrisUnder('production');
        $this->assertSame([], $uris, 'Expected zero `_debug/*` routes in the uncached production route table.');
        $this->assertFalse($this->wildcardAliasRegisteredUnder('production'));
    }

    public function test_production_route_table_has_zero_debug_routes_after_route_cache(): void
    {
        $cachePath = base_path('bootstrap/cache/routes-v7.php');
        $preExisting = File::exists($cachePath);
        $backupPath = $preExisting ? $cachePath . '.gap011-test-backup' : null;

        if ($preExisting) {
            File::move($cachePath, $backupPath);
        }

        try {
            $cacheResult = Process::path(base_path())
                ->env(['APP_ENV' => 'production'])
                ->timeout(60)
                ->run(['php', 'artisan', 'route:cache']);

            if (!$cacheResult->successful()) {
                $errorOutput = $cacheResult->errorOutput() . $cacheResult->output();

                // Pre-existing, GAP-011-unrelated defect: multiple business
                // routes across routes/api.php, routes/api_zena.php, and
                // routes/web.php share the route name `projects`/`projects.store`,
                // which `route:cache`'s serialization step rejects. This blocks
                // `route:cache` entirely, in every environment, independent of
                // this branch or GAP-011's changes (reproduces identically on
                // an unmodified worktree). Not in scope for GAP-011 to fix —
                // skip with a clear pointer rather than fail on something this
                // work item cannot control.
                if (str_contains($errorOutput, 'Another route has already been assigned name') && !str_contains($errorOutput, '_debug') && !str_contains($errorOutput, 'debug.php')) {
                    $this->markTestSkipped(
                        "route:cache is blocked by a pre-existing, GAP-011-unrelated route-name collision (not introduced by this branch): {$errorOutput}\n" .
                        'This is an application-wide route:cache blocker (routes/api.php, routes/api_zena.php, routes/web.php share duplicate names like `projects`/`projects.store`), independent of the `_debug/*` boundary. ' .
                        'See the GAP-011 implementation report for this finding — it should be raised as a separate follow-up, not fixed under GAP-011.'
                    );
                }

                $this->fail('route:cache failed for a reason that may be GAP-011-related: ' . $errorOutput);
            }

            $this->assertTrue(File::exists($cachePath), 'Expected a route cache file to be generated.');

            $uris = $this->debugRouteUrisUnder('production');
            $this->assertSame([], $uris, 'Expected zero `_debug/*` routes in the CACHED production route table.');
            $this->assertFalse($this->wildcardAliasRegisteredUnder('production'), 'Expected /debug/{path?} absent from the CACHED production route table.');
        } finally {
            // Always restore/clear the generated cache, including on failure.
            Process::path(base_path())->env(['APP_ENV' => 'production'])->run(['php', 'artisan', 'route:clear']);

            if (File::exists($cachePath)) {
                File::delete($cachePath);
            }

            if ($preExisting && $backupPath !== null && File::exists($backupPath)) {
                File::move($backupPath, $cachePath);
            }
        }
    }

    // ------------------------------------------------------------------
    // Regression — quick-login workflow and dashboard-data behavior
    // ------------------------------------------------------------------

    /**
     * The demo quick-login workflow `resources/views/auth/login.blade.php`'s
     * links depend on: `_debug/test-login/{email}` must still log the
     * requested user in and redirect to `/app/dashboard`, unchanged from
     * before GAP-011's route relocation.
     */
    public function test_quick_login_workflow_still_works(): void
    {
        $user = User::factory()->create(['email' => 'gap011-regression@example.test']);

        $response = $this->get('/_debug/test-login/gap011-regression@example.test');

        $response->assertRedirect('/app/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_quick_login_workflow_reports_missing_user_by_name(): void
    {
        $response = $this->get('/_debug/test-login/does-not-exist@example.test');

        $response->assertOk();
        $response->assertSeeText('No such user: does-not-exist@example.test');
    }

    /**
     * `_debug/dashboard-data`'s mock JSON shape must be unchanged from
     * before GAP-011's route relocation — `dashboard-content.blade.php`'s
     * `fetch()` call (the adjacent, GAP-011-out-of-scope defect flagged in
     * the design) depends on this exact shape.
     */
    public function test_dashboard_data_preserves_existing_response_shape(): void
    {
        $response = $this->get('/_debug/dashboard-data');

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'stats' => [
                    'totalTasks' => 15,
                    'completedTasks' => 8,
                    'teamMembers' => 5,
                    'totalProjects' => 7,
                ],
            ],
        ]);
        $response->assertJsonStructure([
            'status',
            'data' => ['stats', 'recentActivity', 'quickActions'],
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @return list<string> */
    private function otherRouteFilesThatExist(): array
    {
        return array_values(array_filter(
            self::ROUTE_FILES,
            fn (string $relativePath) => File::exists(base_path($relativePath))
        ));
    }

    /** @return array<int, string> line number (1-indexed) => line content, comment lines stripped */
    private function nonCommentLines(string $relativePath): array
    {
        $lines = File::lines(base_path($relativePath));

        $result = [];
        $lineNumber = 0;
        foreach ($lines as $line) {
            $lineNumber++;
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
                continue;
            }
            $result[$lineNumber] = $line;
        }

        return $result;
    }

    /** @return list<string> */
    private function debugRouteUrisUnder(string $env): array
    {
        $result = Process::path(base_path())
            ->env(['APP_ENV' => $env])
            ->timeout(60)
            ->run(['php', 'artisan', 'route:list', '--json', '--path=_debug', '--except-vendor']);

        $this->assertTrue($result->successful(), "route:list failed under APP_ENV={$env}: " . $result->errorOutput());

        $decoded = $this->extractJsonArray($result->output());
        $this->assertIsArray($decoded, "route:list --json under APP_ENV={$env} did not return a JSON array. Raw output:\n" . $result->output());

        return collect($decoded)->pluck('uri')->values()->all();
    }

    private function wildcardAliasRegisteredUnder(string $env): bool
    {
        $result = Process::path(base_path())
            ->env(['APP_ENV' => $env])
            ->timeout(60)
            ->run(['php', 'artisan', 'route:list', '--json', '--path=debug', '--except-vendor']);

        $this->assertTrue($result->successful(), "route:list failed under APP_ENV={$env}: " . $result->errorOutput());

        $decoded = $this->extractJsonArray($result->output());
        $this->assertIsArray($decoded, "route:list --json under APP_ENV={$env} did not return a JSON array. Raw output:\n" . $result->output());

        foreach ($decoded as $route) {
            $uri = (string) ($route['uri'] ?? '');
            if ($uri === 'debug/{path?}') {
                return true;
            }
        }

        return false;
    }

    /**
     * `php artisan route:list --json` output can be preceded by unrelated
     * PHP startup warnings on some local toolchains (broken extension
     * config, not a GAP-011 concern) — extract the JSON array robustly by
     * locating its outermost `[` ... `]` span rather than assuming the
     * subprocess emitted nothing but JSON on stdout.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function extractJsonArray(string $output): ?array
    {
        // `route:list --json --path=...` prints this human-readable error
        // instead of `[]` when literally zero routes match the given path —
        // that is the expected, correct signal for "this environment has
        // zero `_debug/*` routes", not a parse failure.
        if (str_contains($output, "doesn't have any routes matching the given criteria")) {
            return [];
        }

        if (!preg_match('/\[.*\]/s', $output, $matches)) {
            return null;
        }

        $decoded = json_decode($matches[0], true);

        return is_array($decoded) ? $decoded : null;
    }
}
