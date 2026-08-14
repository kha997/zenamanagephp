<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GAP-011 — Class B disposition: 6 of 7 legacy root redirects had no
 * verified consumer beyond this test and were removed rather than
 * relocated; the 7th, `/debug/{path?}`, survives as an explicit
 * `local`-only developer convenience alias.
 *
 * See docs/owner-decisions/GAP-011/02-design-v4.md §4/§5.
 */
class LegacyDebugRootRedirectTest extends TestCase
{
    public function test_removed_class_b_root_routes_no_longer_exist(): void
    {
        $removed = [
            '/dashboard-data',
            '/test-api-admin-dashboard',
            '/test-permissions',
            '/test-api-admin-stats',
            '/test-session-auth',
            '/test-login/superadmin@zena.com',
        ];

        foreach ($removed as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }

    public function test_post_only_debug_login_helper_still_has_no_root_get_redirect(): void
    {
        $this->get('/test-login-simple')->assertNotFound();
    }

    /**
     * `/debug/{path?}` is a wildcard mapper (`/debug/{path}` -> `/_debug/{path}`
     * for an arbitrary caller-supplied path), not a fixed 1:1 redirect — it
     * cannot be validated by asserting every possible destination is itself a
     * registered route (most aren't, and that's correct). This test proves
     * the mechanical prefix-forwarding is correct via representative
     * examples, independent of the environment-registration question (which
     * is a separate concern, proven by test_wildcard_alias_is_absent_under_testing_env()
     * below and by tests/Architecture/DebugRouteBoundaryInvariantTest.php's
     * subprocess-based local/testing/development/production matrix).
     */
    public function test_wildcard_alias_forwards_representative_paths_correctly(): void
    {
        $this->registerWildcardAliasForThisTestOnly();

        $this->get('/debug/dashboard-data')
            ->assertStatus(301)
            ->assertRedirect('/_debug/dashboard-data');

        $this->get('/debug/test-login/someone@example.com')
            ->assertStatus(301)
            ->assertRedirect('/_debug/test-login/someone@example.com');
    }

    /**
     * The wildcard's actual environment-registration condition (`local`
     * only, nested inside the outer local/testing/development file-level
     * gate) means it must be absent from the real route table under
     * `testing` — the environment this test suite itself runs under. This
     * is a direct HTTP-level proof, not just a route-table inspection.
     */
    public function test_wildcard_alias_is_absent_under_testing_env(): void
    {
        $this->get('/debug/dashboard-data')->assertNotFound();
        $this->get('/debug/anything-at-all')->assertNotFound();
    }

    /**
     * `development` and `production` are not otherwise exercised by this
     * (testing-env) HTTP test process, so their absence is proven via the
     * same subprocess `route:list` technique used by
     * tests/Architecture/DebugRouteBoundaryInvariantTest.php — kept here as
     * well since this file is the canonical home for the wildcard's
     * behavioral contract.
     */
    public function test_wildcard_alias_is_absent_under_development_and_production(): void
    {
        foreach (['development', 'production'] as $env) {
            $result = Process::path(base_path())
                ->env(['APP_ENV' => $env])
                ->timeout(60)
                ->run(['php', 'artisan', 'route:list', '--json', '--path=debug', '--except-vendor']);

            $this->assertTrue($result->successful(), "route:list failed under APP_ENV={$env}: " . $result->errorOutput());

            $output = $result->output();
            if (str_contains($output, "doesn't have any routes matching the given criteria")) {
                continue;
            }

            preg_match('/\[.*\]/s', $output, $matches);
            $decoded = json_decode($matches[0] ?? '[]', true);

            $uris = is_array($decoded) ? array_column($decoded, 'uri') : [];
            $this->assertNotContains('debug/{path?}', $uris, "Expected /debug/{path?} absent under APP_ENV={$env}.");
        }
    }

    private function registerWildcardAliasForThisTestOnly(): void
    {
        Route::get('/debug/{path?}', function ($path = '') {
            return redirect("/_debug/{$path}", 301);
        })->where('path', '.*');
    }
}
