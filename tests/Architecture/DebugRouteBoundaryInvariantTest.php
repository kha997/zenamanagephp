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
 * middleware presence, environment scope) and source-discovered — no
 * route file, provider, or bootstrap file is hard-coded. A future
 * `routes/*.php` file, a future provider, or a future `_debug/*` route
 * or alias declared anywhere outside `routes/debug.php` is caught here
 * automatically, without editing this test.
 */
final class DebugRouteBoundaryInvariantTest extends TestCase
{
    private const DEBUG_ROUTE_FILE = 'routes/debug.php';

    private const ROUTE_SERVICE_PROVIDER = 'app/Providers/RouteServiceProvider.php';

    // ------------------------------------------------------------------
    // Static declaration-site guard
    // ------------------------------------------------------------------

    public function test_no_debug_route_is_declared_outside_routes_debug_php(): void
    {
        foreach ($this->discoveredRouteFiles() as $relativePath) {
            $content = $this->scannableContent($relativePath);

            if (preg_match_all('/Route::\w+\s*\([^;]*?[\'"]_debug/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [$snippet, $offset]) {
                    $line = $this->lineNumberAtOffset($content, $offset);
                    $this->fail("Found a `_debug` route declaration outside routes/debug.php: {$relativePath}:{$line}\n" . trim($snippet));
                }
            }
        }

        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------
    // Static Class B alias-drift guard (multiline-aware)
    // ------------------------------------------------------------------

    public function test_no_redirect_outside_routes_debug_php_targets_the_debug_namespace(): void
    {
        foreach ($this->discoveredRouteFiles() as $relativePath) {
            $content = $this->scannableContent($relativePath);

            if (!preg_match_all('/Route::(?:permanentRedirect|redirect)\s*\((.*?)\)\s*;/s', $content, $calls, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($calls[1] as $index => [$argsString, $offset]) {
                preg_match_all('/[\'"]([^\'"]*)[\'"]/', $argsString, $stringLiterals);
                $destination = $stringLiterals[1][1] ?? null; // 2nd string literal = redirect destination

                if ($destination !== null && str_starts_with(ltrim($destination, '/'), '_debug')) {
                    $line = $this->lineNumberAtOffset($content, $offset);
                    $this->fail("Found a compatibility redirect outside routes/debug.php targeting the debug namespace: {$relativePath}:{$line} -> {$destination}");
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
            $this->discoveredProviderAndBootstrapFiles(),
            $this->discoveredRouteFiles()
        );

        foreach ($candidates as $relativePath) {
            if (!File::exists(base_path($relativePath))) {
                continue;
            }

            foreach ($this->nonCommentLines($relativePath) as $line) {
                // Only count an actual code reference (base_path()/require/group()
                // call) — a prose comment mentioning the filename must not count
                // as a second registration site.
                $mentionsDebugRouteFile = str_contains($line, 'routes/debug.php') || str_contains($line, "routes'/debug.php");
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
            'routes/debug.php must be registered from exactly one place: ' . self::ROUTE_SERVICE_PROVIDER .
            '. Found: ' . (empty($referencingFiles) ? '(none)' : implode(', ', $referencingFiles))
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
        $this->assertSame([], $productionUris, 'Expected zero `_debug/*` routes under APP_ENV=production (uncached).');
    }

    public function test_wildcard_alias_is_present_in_local_only(): void
    {
        $this->assertTrue($this->wildcardAliasRegisteredUnder('local'), 'Expected /debug/{path?} present under APP_ENV=local.');

        foreach (['testing', 'development', 'production'] as $env) {
            $this->assertFalse($this->wildcardAliasRegisteredUnder($env), "Expected /debug/{path?} absent under APP_ENV={$env} (uncached).");
        }
    }

    // ------------------------------------------------------------------
    // Production absence — uncached (PASS) and cached (BLOCKED, see below)
    // ------------------------------------------------------------------

    public function test_production_route_table_has_zero_debug_routes_uncached(): void
    {
        $uris = $this->debugRouteUrisUnder('production');
        $this->assertSame([], $uris, 'Expected zero `_debug/*` routes in the uncached production route table.');
        $this->assertFalse($this->wildcardAliasRegisteredUnder('production'));
    }

    /**
     * @group stress
     *
     * NOT a verification of cached production absence — this test is
     * BLOCKED, not passing. `php artisan route:cache` cannot currently
     * complete anywhere in this repository (any environment, on an
     * unmodified worktree), because of a pre-existing, GAP-011-unrelated
     * route-name collision between routes/api.php, routes/api_zena.php,
     * and routes/web.php (multiple routes assigned the name
     * `projects`/`projects.store`). Cached production absence for
     * GAP-011's `_debug/*` boundary remains technically unproven until
     * that collision is fixed and route:cache can actually run — see
     * docs/owner-decisions/GAP-011/03-release.md for the recorded
     * blocker. Do not read a SKIP here as a PASS.
     */
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

                if (str_contains($errorOutput, 'Another route has already been assigned name') && !str_contains($errorOutput, '_debug') && !str_contains($errorOutput, 'debug.php')) {
                    $this->markTestSkipped(
                        "dependency: BLOCKED (not PASS) — route:cache cannot complete due to a pre-existing, GAP-011-unrelated route-name collision (not introduced by this branch, reproduces on an unmodified worktree): {$errorOutput}\n" .
                        'This is an application-wide route:cache blocker (routes/api.php, routes/api_zena.php, routes/web.php share duplicate names like `projects`/`projects.store`), independent of the `_debug/*` boundary. ' .
                        'Cached production absence for GAP-011 is technically unproven, not verified, as a result. See docs/owner-decisions/GAP-011/03-release.md — this is recorded as a Gate 3 technical blocker, not fixed under GAP-011.'
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
    // Discovery helpers — no hard-coded file lists
    // ------------------------------------------------------------------

    /**
     * Every top-level routes/*.php file, excluding routes/debug.php
     * itself. A new route file dropped into routes/ is picked up here
     * automatically the next time this test runs — no edit required.
     *
     * @return list<string>
     */
    private function discoveredRouteFiles(): array
    {
        $files = glob(base_path('routes/*.php')) ?: [];

        $relative = array_map(
            fn (string $absolute) => 'routes/' . basename($absolute),
            $files
        );

        return array_values(array_filter(
            $relative,
            fn (string $relativePath) => $relativePath !== self::DEBUG_ROUTE_FILE
        ));
    }

    /**
     * Every app/Providers/*.php file plus bootstrap/app.php — the set of
     * places a second `routes/debug.php` loader could plausibly be
     * introduced. A new provider file is picked up automatically.
     *
     * @return list<string>
     */
    private function discoveredProviderAndBootstrapFiles(): array
    {
        $providerFiles = glob(base_path('app/Providers/*.php')) ?: [];

        $relative = array_map(
            fn (string $absolute) => 'app/Providers/' . basename($absolute),
            $providerFiles
        );

        if (File::exists(base_path('bootstrap/app.php'))) {
            $relative[] = 'bootstrap/app.php';
        }

        if (File::exists(base_path('bootstrap/providers.php'))) {
            $relative[] = 'bootstrap/providers.php';
        }

        return array_values(array_unique($relative));
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

    /**
     * Full file content with `//`/`*`/`/*`-led comment lines blanked but
     * line breaks preserved, so a multiline `preg_match` with the `s`
     * (DOTALL) modifier can span statements that wrap across lines while
     * `lineNumberAtOffset()` still reports an accurate line number.
     */
    private function scannableContent(string $relativePath): string
    {
        $lines = File::lines(base_path($relativePath))->toArray();

        foreach ($lines as $i => $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
                $lines[$i] = '';
            }
        }

        return implode("\n", $lines);
    }

    private function lineNumberAtOffset(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
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
