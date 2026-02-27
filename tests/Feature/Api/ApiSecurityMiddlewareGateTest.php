<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ApiSecurityMiddlewareGateTest extends TestCase
{
    public function test_business_api_routes_are_protected_by_auth_tenant_rbac_middleware(): void
    {
        Artisan::call('route:list', [
            '--json' => true,
            '--except-vendor' => true,
        ]);

        /** @var mixed $decoded */
        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded, 'route:list --json must return a JSON array');

        $allowlist = [
            'api/zena' => 'Public Zena API info endpoint by design.',
            'api/zena/auth/login' => 'Public bootstrap login endpoint by design.',
            'api/zena/health' => 'Public health endpoint by design.',
            'api/v1/public/health' => 'Public health endpoint by design.',
            'api/v1/public/health/liveness' => 'Public liveness probe endpoint by design.',
            'api/v1/public/health/readiness' => 'Public readiness probe endpoint by design.',
        ];

        $strictOffenders = [];
        $baselineOffenders = [];

        foreach ($decoded as $route) {
            $uri = (string)($route['uri'] ?? '');
            if ($uri === '') {
                continue;
            }

            $uriNorm = ltrim($uri, '/');
            $tier = $this->determineTier($uriNorm, $allowlist);
            if ($tier === null) {
                continue;
            }

            $middleware = $this->normalizeMiddleware($route['middleware'] ?? []);
            $method = (string)($route['method'] ?? '');
            $action = (string)($route['action'] ?? '');

            $hasAuth = $this->hasMiddleware($middleware, [
                'auth',
                'auth:',
                'Authenticate',
            ]);
            $hasTenant = $this->hasMiddleware($middleware, [
                'tenant.isolation',
                'TenantIsolationMiddleware',
            ]);
            $hasRbac = $this->hasMiddleware($middleware, [
                'rbac',
                'RoleBasedAccessControlMiddleware',
            ]);

            if (!$hasAuth || !$hasTenant || !$hasRbac) {
                $offender = [
                    'method' => $method,
                    'uri' => $uriNorm,
                    'action' => $action,
                    'middleware' => implode(' | ', $middleware),
                    'missing' => implode(',', array_filter([
                        !$hasAuth ? 'auth' : null,
                        !$hasTenant ? 'tenant.isolation' : null,
                        !$hasRbac ? 'rbac' : null,
                    ])),
                ];

                if ($tier === 'strict') {
                    $strictOffenders[] = $offender;
                } else {
                    $baselineOffenders[] = $offender;
                }
            }
        }

        $this->sortOffenders($strictOffenders);
        $this->sortOffenders($baselineOffenders);

        if ($strictOffenders !== []) {
            $strictLines = array_map(
                static fn (array $v): string => "[STRICT] {$v['method']} {$v['uri']} missing={$v['missing']} action={$v['action']} mw={$v['middleware']}",
                $strictOffenders
            );

            $this->fail("Found strict-tier API routes without required middleware:\n" . implode("\n", $strictLines));
        }

        $baselineFixture = $this->loadBaselineOffenders();

        $baselineCurrent = array_values(array_unique(array_map(
            static fn (array $v): string => trim((string)$v['method']) . ' ' . $v['uri'],
            $baselineOffenders
        )));
        sort($baselineCurrent);

        $newBaseline = array_values(array_diff($baselineCurrent, $baselineFixture));
        sort($newBaseline);

        if ($newBaseline !== []) {
            $newLines = array_values(array_map(
                function (string $key) use ($baselineOffenders): string {
                    foreach ($baselineOffenders as $offender) {
                        $offenderKey = trim((string)$offender['method']) . ' ' . $offender['uri'];
                        if ($offenderKey === $key) {
                            return "[BASELINE-NEW] {$offender['method']} {$offender['uri']} missing={$offender['missing']} action={$offender['action']} mw={$offender['middleware']}";
                        }
                    }

                    return "[BASELINE-NEW] {$key}";
                },
                $newBaseline
            ));

            $this->fail("Found new baseline-tier API middleware regressions:\n" . implode("\n", $newLines));
        }

        $removedCount = count(array_diff($baselineFixture, $baselineCurrent));
        if ($removedCount > 0) {
            fwrite(STDOUT, "Baseline improvement: {$removedCount} legacy offender(s) no longer missing required middleware.\n");
        }

        $this->assertTrue(true);
    }

    private function determineTier(string $uri, array $allowlist): ?string
    {
        if (array_key_exists($uri, $allowlist)) {
            return null;
        }

        if (str_starts_with($uri, 'api/zena/')) {
            return 'strict';
        }

        if (str_starts_with($uri, 'api/v1/work-template/')) {
            return 'strict';
        }

        if (str_starts_with($uri, 'api/v1/')) {
            return 'baseline';
        }

        return null;
    }

    private function sortOffenders(array &$offenders): void
    {
        usort(
            $offenders,
            static fn (array $a, array $b): int => [$a['uri'], $a['method']] <=> [$b['uri'], $b['method']]
        );
    }

    private function loadBaselineOffenders(): array
    {
        $path = base_path('tests/Fixtures/middleware_gate_baseline.json');
        $this->assertTrue(File::exists($path), 'Missing baseline fixture: tests/Fixtures/middleware_gate_baseline.json');

        $raw = File::get($path);
        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);

        $this->assertIsArray($decoded, 'Baseline fixture must decode to a JSON array.');
        $this->assertSame($decoded, array_values($decoded), 'Baseline fixture must be a JSON list.');

        $items = array_values(array_unique(array_map(static fn ($item): string => trim((string)$item), $decoded)));
        sort($items);

        return $items;
    }

    private function normalizeMiddleware(mixed $middleware): array
    {
        if (is_string($middleware)) {
            $middleware = [$middleware];
        }

        if (!is_array($middleware)) {
            return [];
        }

        return array_values(array_map('strval', $middleware));
    }

    private function hasMiddleware(array $middleware, array $aliases): bool
    {
        foreach ($middleware as $entry) {
            $value = (string)$entry;
            $normalized = strtolower($value);
            $classPortion = strtolower((string)strtok($value, ':'));
            $classBase = strtolower((string)basename(str_replace('\\', '/', $classPortion)));

            foreach ($aliases as $alias) {
                $aliasNorm = strtolower($alias);
                if (
                    $normalized === $aliasNorm
                    || str_starts_with($normalized, $aliasNorm . ':')
                    || $classPortion === $aliasNorm
                    || $classBase === $aliasNorm
                    || str_starts_with($classBase, $aliasNorm)
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
