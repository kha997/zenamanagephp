<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use Database\Seeders\ZenaPermissionsSeeder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Tests\TestCase;

class ZenaRouteStackHygieneInvariantTest extends TestCase
{
    private const PUBLIC_ALLOWLIST_URIS = ZenaRouteSurfaceInvariantTest::PUBLIC_ALLOWLIST_URIS;
    private const DISALLOWED_SESSION_MIDDLEWARE = [
        'web',
        'StartSession',
        'ShareErrorsFromSession',
        'VerifyCsrfToken',
        'EncryptCookies',
        'AddQueuedCookiesToResponse',
    ];

    /**
     * @group zena-invariants
     */
    public function test_zena_routes_shy_of_web_and_session_stack(): void
    {
        $errors = [];

        foreach ($this->getZenaRoutes() as $route) {
            $middleware = $this->normalizeMiddleware($route);

            $webViolations = array_values(array_filter($middleware, fn (string $entry) => $this->isDisallowedSessionMiddleware($entry)));
            if (!empty($webViolations)) {
                $errors[] = "{$route->uri()} exposes forbidden web/session middleware: " . implode(', ', $webViolations);
            }

            $disallowedAuth = array_values(array_filter($middleware, fn (string $entry) => $this->isDisallowedAuthEntry($entry)));
            if (!empty($disallowedAuth)) {
                $errors[] = "{$route->uri()} exposes non-sanctum auth middleware: " . implode(', ', $disallowedAuth);
            }
        }

        $this->assertEmpty($errors, implode("\n", $errors));
    }

    /**
     * @group zena-invariants
     */
    public function test_public_allowlist_routes_preserve_api_stack_and_login_is_throttled(): void
    {
        $routes = $this->getZenaRoutes()->keyBy(fn (RoutingRoute $route) => $route->uri());
        $errors = [];

        foreach (self::PUBLIC_ALLOWLIST_URIS as $uri) {
            if (!isset($routes[$uri])) {
                $errors[] = "Public allowlist route {$uri} disappeared from route collection.";
                continue;
            }

            $middleware = $this->normalizeMiddleware($routes[$uri]);

            if (!in_array('api', $middleware, true)) {
                $errors[] = "{$uri} no longer resolves through the api middleware group stack ({$routes[$uri]->uri()}); actual: " . implode(', ', $middleware);
            }

        }

        $loginUri = 'api/zena/auth/login';
        if (isset($routes[$loginUri])) {
            $loginMiddleware = $this->normalizeMiddleware($routes[$loginUri]);
            $hasThrottle = array_reduce($loginMiddleware, fn ($carry, string $entry) => $carry || $this->isThrottleEntry($entry), false);
            if (!$hasThrottle) {
                $errors[] = "{$loginUri} must expose throttle middleware; actual stack: " . implode(', ', $loginMiddleware);
            }
        }

        $this->assertEmpty($errors, implode("\n", $errors));
    }

    /**
     * @group zena-invariants
     */
    public function test_protected_routes_use_only_canonical_rbac_permissions(): void
    {
        $canonical = $this->getCanonicalPermissionKeys();
        $errors = [];

        foreach ($this->getZenaRoutes() as $route) {
            if ($this->routeIsPublic($route)) {
                continue;
            }

            $permissions = $this->extractRbacPermissions($this->normalizeMiddleware($route));

            foreach ($permissions as $permission) {
                if (!in_array($permission, $canonical, true)) {
                    $errors[] = "{$route->uri()} references unknown RBAC permission {$permission}";
                }
            }
        }

        $this->assertEmpty($errors, implode("\n", $errors));
    }

    private function getZenaRoutes(): \Illuminate\Support\Collection
    {
        return collect(RouteFacade::getRoutes())
            ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/zena'))
            ->values();
    }

    private function normalizeMiddleware(RoutingRoute $route): array
    {
        return array_values(array_filter(array_map(fn (?string $entry) => trim((string) $entry), $route->gatherMiddleware()), fn (string $entry) => $entry !== '',));
    }

    private function isDisallowedSessionMiddleware(string $entry): bool
    {
        if ($entry === '') {
            return false;
        }

        foreach (self::DISALLOWED_SESSION_MIDDLEWARE as $forbidden) {
            if (str_contains($entry, $forbidden)) {
                return true;
            }
        }

        return false;
    }

    private function isDisallowedAuthEntry(string $entry): bool
    {
        $lower = Str::lower($entry);

        if ($lower === 'auth') {
            return true;
        }

        if (str_starts_with($lower, 'auth:') && $lower !== 'auth:sanctum') {
            return true;
        }

        if (str_contains($lower, 'authenticate') && !str_contains($lower, 'sanctum')) {
            return true;
        }

        return false;
    }

    private function isThrottleConfigured(): bool
    {
        foreach ($this->getZenaRoutes() as $route) {
            foreach ($this->normalizeMiddleware($route) as $entry) {
                if ($this->isThrottleEntry($entry)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isThrottleEntry(string $entry): bool
    {
        $lower = Str::lower($entry);

        return str_starts_with($lower, 'throttle:') || str_contains($lower, 'throttlerequests');
    }

    private function routeIsPublic(RoutingRoute $route): bool
    {
        return in_array($route->uri(), self::PUBLIC_ALLOWLIST_URIS, true);
    }

    private function extractRbacPermissions(array $middleware): array
    {
        $permissions = [];

        foreach ($middleware as $entry) {
            if (Str::startsWith($entry, 'rbac:')) {
                $parts = explode(':', $entry, 2);
                if (isset($parts[1]) && $parts[1] !== '') {
                    $permissions[] = $parts[1];
                }
            }

            if (str_contains($entry, 'RoleBasedAccessControlMiddleware:')) {
                $parts = explode(':', $entry, 2);
                if (isset($parts[1]) && $parts[1] !== '') {
                    $permissions[] = $parts[1];
                }
            }
        }

        return array_unique($permissions);
    }

    private function getCanonicalPermissionKeys(): array
    {
        return array_values(array_filter(array_map(fn (array $permission) => $permission['code'] ?? $permission['name'] ?? null, ZenaPermissionsSeeder::CANONICAL_PERMISSIONS)));
    }
}
