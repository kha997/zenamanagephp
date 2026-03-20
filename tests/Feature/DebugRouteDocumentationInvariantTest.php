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
        $this->assertStringContainsString('Do not use this file as the runtime source of truth for `/_debug/*` claims.', $doc);
        $this->assertStringContainsString('`ZENAMANAGE_PAGE_TREE_DIAGRAM.md`', $doc);
        $this->assertStringContainsString('`docs/audits/2026-03-19-debug-route-inventory.md`', $doc);
    }

    public function test_current_page_tree_active_debug_claims_have_runtime_route_evidence(): void
    {
        $routes = $this->debugRoutesByUri();

        $expectedActiveClaims = [
            '_debug/dashboard-data',
            '_debug/test-permissions',
            '_debug/test-session-auth',
            '_debug/test-login/{email}',
        ];

        foreach ($expectedActiveClaims as $uri) {
            $this->assertArrayHasKey($uri, $routes, "Expected active debug claim [{$uri}] to exist in route:list output.");
        }

        $this->assertArrayHasKey('_debug/test-login-simple', $routes, 'Expected active debug claim [_debug/test-login-simple] to exist in route:list output.');
        $this->assertSame('POST', $routes['_debug/test-login-simple']['method'] ?? null, 'Expected [_debug/test-login-simple] to remain POST-only.');
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
