<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DebugRouteDocumentationInvariantTest extends TestCase
{
    public function test_current_page_tree_is_explicitly_marked_historical_for_debug_routes(): void
    {
        $doc = $this->currentPageTreeDocument();

        $this->assertStringContainsString('HISTORICAL SNAPSHOT - NON-CANONICAL', $doc);
        $this->assertStringContainsString('Do not use this file, or the 2026-03-19 audit above, as the runtime source of truth for `/_debug/*` claims', $doc);
        $this->assertStringContainsString('`ZENAMANAGE_PAGE_TREE_DIAGRAM.md`', $doc);
        $this->assertStringContainsString('`docs/audits/2026-03-19-debug-route-inventory.md`', $doc);
    }

    /**
     * The document's own annotation must state the GAP-011 removal — this
     * is what makes the two runtime tests above (active claims shrunk to 2,
     * the 3 removed claims absent) a correspondence check against the
     * document rather than an isolated runtime assertion with no document
     * counterpart.
     */
    public function test_current_page_tree_documents_the_gap011_removal_with_provenance(): void
    {
        $doc = $this->currentPageTreeDocument();

        $this->assertStringContainsString('Removed by GAP-011', $doc);
        $this->assertStringContainsString('_debug/test-permissions', $doc);
        $this->assertStringContainsString('_debug/test-login-simple', $doc);
        $this->assertStringContainsString('_debug/test-session-auth', $doc);
        $this->assertStringContainsString('docs/owner-decisions/GAP-011/', $doc);

        // The "still active" annotation must no longer claim the 3 removed
        // routes, and must still claim the 2 survivors.
        $this->assertStringContainsString('/_debug/dashboard-data', $doc);
        $this->assertStringContainsString('/_debug/test-login/{email}', $doc);
    }

    /**
     * The document must state *current* `_debug/*` ownership accurately —
     * `routes/debug.php`, registered solely from `RouteServiceProvider`,
     * `DebugGateMiddleware` on Class A — and must not repeat the stale
     * pre-GAP-011 claim that `routes/web.php` mounts the `_debug/*`
     * surface (true before GAP-011, false after: routes/web.php no longer
     * declares any `_debug/*` route). It must also stop presenting the
     * frozen `docs/audits/2026-03-19-debug-route-inventory.md` snapshot as
     * the current runtime source of truth — that document is untouched
     * (see the dedicated architecture test asserting it), but this file's
     * own annotation of it must be corrected to "historical/pre-GAP-011
     * baseline."
     */
    public function test_current_page_tree_states_current_debug_ownership_accurately(): void
    {
        $doc = $this->currentPageTreeDocument();

        $this->assertStringContainsString('routes/debug.php', $doc);
        $this->assertStringContainsString('RouteServiceProvider', $doc);
        $this->assertStringContainsString('DebugGateMiddleware', $doc);

        $this->assertStringNotContainsString(
            'routes/web.php` van mount `/_debug/*`',
            $doc,
            'The stale pre-GAP-011 claim that routes/web.php mounts /_debug/* must not remain in the document.'
        );
        $this->assertStringNotContainsString(
            'current `routes/web.php` still mounts the active `/_debug/*` surface',
            $doc,
            'The stale pre-GAP-011 claim that routes/web.php mounts /_debug/* must not remain in the document.'
        );

        $this->assertStringContainsString(
            'Historical `_debug/*` runtime baseline (pre-GAP-011, as of 2026-03-19)',
            $doc,
            'The 2026-03-19 audit must be labeled a historical/pre-GAP-011 baseline, not the current runtime source of truth.'
        );
        $this->assertStringContainsString(
            'no longer the current runtime source of truth',
            $doc
        );
    }

    public function test_current_page_tree_active_debug_claims_have_runtime_route_evidence(): void
    {
        $routes = $this->debugRoutesByUri();

        // GAP-011 retention matrix: exactly 2 Class A routes survive.
        // See docs/owner-decisions/GAP-011/02-design-v4.md.
        $expectedActiveClaims = [
            '_debug/dashboard-data',
            '_debug/test-login/{email}',
        ];

        foreach ($expectedActiveClaims as $uri) {
            $this->assertArrayHasKey($uri, $routes, "Expected active debug claim [{$uri}] to exist in route:list output.");
        }
    }

    public function test_current_page_tree_archived_debug_claims_do_not_have_runtime_route_evidence(): void
    {
        $routes = $this->debugRoutesByUri();

        $archivedClaims = [
            '_debug/info',
            '_debug/projects-test',
            '_debug/users-debug',
            '_debug/tasks-debug',
            '_debug/frontend-test',
            '_debug/login-test',
            '_debug/simple-test',
            '_debug/navigation-test',
            '_debug/api-docs',
            '_debug/api-docs.json',
            '_debug/test-api-admin-dashboard',
        ];

        foreach ($archivedClaims as $uri) {
            $this->assertArrayNotHasKey($uri, $routes, "Archived debug claim [{$uri}] unexpectedly exists in route:list output.");
        }
    }

    /**
     * GAP-011 deliberately removed 3 of the 5 claims this test previously
     * asserted were "active" — this is not the same category as the
     * pre-existing "archived/unsupported" claims above (those were already
     * unsupported as of the 2026-03-19 snapshot; these three were
     * runtime-backed as of that snapshot and were removed later, by
     * GAP-011). Kept as its own test so the document ↔ runtime
     * correspondence this suite protects stays meaningful — this proves the
     * removal actually happened at the runtime level, not merely that the
     * test's own expectation array was shrunk.
     *
     * See ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md's "Removed by GAP-011"
     * annotation and docs/owner-decisions/GAP-011/02-design-v4.md §9.
     */
    public function test_current_page_tree_gap011_removed_claims_do_not_have_runtime_route_evidence(): void
    {
        $routes = $this->debugRoutesByUri();

        $gap011RemovedClaims = [
            '_debug/test-permissions',
            '_debug/test-login-simple',
            '_debug/test-session-auth',
        ];

        foreach ($gap011RemovedClaims as $uri) {
            $this->assertArrayNotHasKey($uri, $routes, "Claim [{$uri}] was removed by GAP-011 and must not exist in route:list output.");
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function debugRoutesByUri(): array
    {
        Artisan::call('route:list', [
            '--json' => true,
            '--path' => '_debug',
            '--except-vendor' => true,
        ]);

        /** @var mixed $decoded */
        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded, 'route:list --json --path=_debug must return a JSON array');

        $byUri = [];

        foreach ($decoded as $route) {
            $uri = (string) ($route['uri'] ?? '');
            if ($uri === '') {
                continue;
            }

            $byUri[$uri] = [
                'method' => (string) ($route['method'] ?? ''),
                'name' => $route['name'] ?? null,
                'action' => $route['action'] ?? null,
            ];
        }

        return $byUri;
    }

    private function currentPageTreeDocument(): string
    {
        $contents = file_get_contents(base_path('ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md'));

        $this->assertIsString($contents, 'Expected ZENAMANAGE_PAGE_TREE_DIAGRAM_CURRENT.md to be readable.');

        return $contents;
    }
}
