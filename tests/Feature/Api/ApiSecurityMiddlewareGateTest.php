<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Artisan;
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
            [
                'pattern' => '#^api/zena/auth/login$#',
                'reason' => 'Public bootstrap login endpoint by design.',
            ],
            [
                'pattern' => '#^api/zena/health$#',
                'reason' => 'Public health endpoint by design.',
            ],
            [
                'pattern' => '#^api/v1/public/health$#',
                'reason' => 'Public health endpoint by design.',
            ],
            [
                'pattern' => '#^api/v1/public/health/liveness$#',
                'reason' => 'Public liveness probe endpoint by design.',
            ],
            [
                'pattern' => '#^api/v1/public/health/readiness$#',
                'reason' => 'Public readiness probe endpoint by design.',
            ],
            [
                'pattern' => '#^api/zena$#',
                'reason' => 'Public Zena API info endpoint by design (aligned with Zena route invariants).',
            ],
        ];

        $violations = [];

        foreach ($decoded as $route) {
            $uri = (string)($route['uri'] ?? '');
            if ($uri === '') {
                continue;
            }

            $uriNorm = ltrim($uri, '/');
            if (!$this->isApiV1OrZena($uriNorm)) {
                continue;
            }

            $middleware = $this->normalizeMiddleware($route['middleware'] ?? []);
            if ($this->matchesAllowlist($uriNorm, $allowlist)) {
                continue;
            }

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
                $violations[] = [
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
            }
        }

        if ($violations !== []) {
            usort(
                $violations,
                static fn (array $a, array $b): int => [$a['uri'], $a['method']] <=> [$b['uri'], $b['method']]
            );

            $lines = array_map(
                static fn (array $v): string => "{$v['method']} {$v['uri']} missing={$v['missing']} action={$v['action']} mw={$v['middleware']}",
                $violations
            );

            $this->fail("Found unprotected business API routes:\n" . implode("\n", $lines));
        }

        $this->assertTrue(true); // explicit pass
    }

    private function isApiV1OrZena(string $uri): bool
    {
        return str_starts_with($uri, 'api/v1/') || $uri === 'api/zena' || str_starts_with($uri, 'api/zena/');
    }

    private function matchesAllowlist(string $uri, array $allowlist): bool
    {
        foreach ($allowlist as $entry) {
            $pattern = (string)($entry['pattern'] ?? '');
            $reason = (string)($entry['reason'] ?? '');

            $this->assertNotSame('', $reason, 'Allowlist entry must include rationale.');

            if (preg_match($pattern, $uri) === 1) {
                return true;
            }
        }

        return false;
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
