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

        // Only enforce on high-confidence business prefixes (min false positives).
        $enforcedPrefixes = [
            'api/zena/',
            'api/v1/settings/',
            'api/user-preferences',
        ];

        // Explicit allowlist (public endpoints)
        $allowlistPatterns = [
            '#^api/zena/health$#',
            '#^api/zena/auth/login$#',
            '#^api/v1/public/health(?:/.*)?$#',
            '#^api/auth/login$#',
            '#^api/auth/register$#',
            '#^api/auth/password/.*$#',
        ];

        $violations = [];

        foreach ($decoded as $route) {
            $uri = (string)($route['uri'] ?? '');
            if ($uri === '') {
                continue;
            }

            // Normalize
            $uriNorm = ltrim($uri, '/');

            // Enforce only selected prefixes
            $shouldEnforce = false;
            foreach ($enforcedPrefixes as $prefix) {
                if (str_starts_with($uriNorm, $prefix) || $uriNorm === $prefix) {
                    $shouldEnforce = true;
                    break;
                }
            }
            if (!$shouldEnforce) {
                continue;
            }

            // Allowlist
            $isAllowed = false;
            foreach ($allowlistPatterns as $pattern) {
                if (preg_match($pattern, $uriNorm) === 1) {
                    $isAllowed = true;
                    break;
                }
            }
            if ($isAllowed) {
                continue;
            }

            $middleware = $route['middleware'] ?? [];
            if (is_string($middleware)) {
                $middleware = [$middleware];
            }
            if (!is_array($middleware)) {
                $middleware = [];
            }

            $mw = implode(' | ', array_map('strval', $middleware));
            $method = (string)($route['method'] ?? '');
            $action = (string)($route['action'] ?? '');

            $hasSanctum = str_contains($mw, 'Authenticate:sanctum') || str_contains($mw, 'auth:sanctum');
            $hasTenant = str_contains($mw, 'TenantIsolationMiddleware') || str_contains($mw, 'tenant.isolation');
            $hasRbac   = str_contains($mw, 'RoleBasedAccessControlMiddleware') || str_contains($mw, 'rbac');

            if (!$hasSanctum || !$hasTenant || !$hasRbac) {
                $violations[] = [
                    'method' => $method,
                    'uri' => $uriNorm,
                    'action' => $action,
                    'middleware' => $mw,
                    'missing' => implode(',', array_filter([
                        !$hasSanctum ? 'auth:sanctum' : null,
                        !$hasTenant ? 'tenant.isolation' : null,
                        !$hasRbac ? 'rbac' : null,
                    ])),
                ];
            }
        }

        if ($violations !== []) {
            $lines = array_map(
                fn ($v) => "{$v['method']} {$v['uri']} missing={$v['missing']} action={$v['action']} mw={$v['middleware']}",
                array_slice($violations, 0, 20)
            );

            $this->fail("Found unprotected business API routes:\n" . implode("\n", $lines));
        }

        $this->assertTrue(true); // explicit pass
    }

    public function test_check_project_permission_bypass_middleware_is_not_registered_or_used(): void
    {
        $forbiddenMiddlewareClass = 'App\\Http\\Middleware\\CheckProjectPermission';

        /** @var array<string, string> $aliases */
        $aliases = app('router')->getMiddleware();
        $forbiddenAliasKeys = [
            'project.permission',
            'project_permission',
            'check.project.permission',
            'check_project_permission',
            'check.project',
        ];

        foreach ($aliases as $alias => $middlewareClass) {
            $this->assertNotSame(
                $forbiddenMiddlewareClass,
                $middlewareClass,
                "Forbidden middleware class is still registered under alias [{$alias}]"
            );
        }

        foreach ($forbiddenAliasKeys as $aliasKey) {
            $this->assertArrayNotHasKey(
                $aliasKey,
                $aliases,
                "Forbidden middleware alias [{$aliasKey}] must not be registered"
            );
        }

        Artisan::call('route:list', [
            '--json' => true,
            '--except-vendor' => true,
        ]);

        /** @var mixed $decoded */
        $decoded = json_decode(Artisan::output(), true);
        $this->assertIsArray($decoded, 'route:list --json must return a JSON array');

        $violations = [];

        foreach ($decoded as $route) {
            $uri = ltrim((string)($route['uri'] ?? ''), '/');
            $method = (string)($route['method'] ?? '');
            $action = (string)($route['action'] ?? '');
            $middleware = $route['middleware'] ?? [];

            if (is_string($middleware)) {
                $middleware = [$middleware];
            }
            if (!is_array($middleware)) {
                $middleware = [];
            }

            $middlewareStrings = array_map('strval', $middleware);

            foreach ($middlewareStrings as $mw) {
                if (str_contains($mw, 'CheckProjectPermission')
                    || str_contains($mw, 'project.permission')
                    || str_contains($mw, 'project_permission')
                    || str_contains($mw, 'check.project.permission')
                    || str_contains($mw, 'check_project_permission')
                    || str_contains($mw, 'check.project')) {
                    $violations[] = sprintf(
                        '%s %s action=%s middleware=%s',
                        $method,
                        $uri,
                        $action,
                        implode(' | ', $middlewareStrings)
                    );
                    break;
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Forbidden project-permission bypass middleware found on routes:\n" . implode("\n", array_slice($violations, 0, 20))
        );
    }
}
