<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class ZenaRouteSurfaceInvariantTest extends TestCase
{
    public const PUBLIC_ALLOWLIST_URIS = [
        'api/zena',
        'api/zena/auth/login',
        'api/zena/health',
    ];

    /**
     * @group zena-invariants
     */
    public function test_zena_route_surface_area_invariant(): void
    {
        $routes = collect(RouteFacade::getRoutes())
            ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/zena'))
            ->map(fn (RoutingRoute $route) => $this->describeRoute($route))
            ->values();

        $errors = [];

        $publicRoutes = $routes->filter(fn (array $route) => !$route['has_auth']);
        $publicUris = $publicRoutes->pluck('uri')->unique()->values()->sort()->all();
        $expectedPublic = collect(self::PUBLIC_ALLOWLIST_URIS)->sort()->values()->all();

        $missingPublic = array_diff($expectedPublic, $publicUris);
        if (!empty($missingPublic)) {
            $errors[] = 'Expected public allowlist missing: ' . implode(', ', $missingPublic);
        }

        $unexpectedPublic = array_diff($publicUris, $expectedPublic);
        foreach ($unexpectedPublic as $uri) {
            $metadata = $publicRoutes->first(fn (array $route) => $route['uri'] === $uri) ?? ['middleware_string' => ''];
            $errors[] = "Unexpected public route {$uri} ({$metadata['middleware_string']}); protect it or add to allowlist.";
        }

        foreach ($routes as $route) {
            if (!$route['has_auth']) {
                continue;
            }

            if (!$route['has_tenant']) {
                $errors[] = "{$route['uri']} missing tenant.isolation ({$route['middleware_string']})";
            }

            if (empty($route['explicit_rbac'])) {
                $errors[] = "{$route['uri']} missing explicit rbac:<permission> ({$route['middleware_string']})";
            }

            if ($route['has_general_rbac'] && !empty($route['explicit_rbac'])) {
                $errors[] = "{$route['uri']} mixes generic RoleBasedAccessControlMiddleware with explicit rbac:<permission> ({$route['middleware_string']})";
            }
        }

        $this->assertEmpty($errors, implode("\n", $errors));
    }

    private function describeRoute(RoutingRoute $route): array
    {
        $middleware = $this->normalizeMiddleware($route);

        return [
            'uri' => $route->uri(),
            'middleware' => $middleware,
            'middleware_string' => implode(', ', $middleware),
            'has_auth' => $this->hasAuthMiddleware($middleware),
            'has_tenant' => $this->hasTenantMiddleware($middleware),
            'explicit_rbac' => $this->explicitRbacMiddleware($middleware),
            'has_general_rbac' => $this->hasGeneralRbacMiddleware($middleware),
        ];
    }

    private function normalizeMiddleware(RoutingRoute $route): array
    {
        return array_values(array_filter(
            array_map(fn (?string $entry) => trim((string) $entry), $route->gatherMiddleware()),
            fn (string $entry) => $entry !== ''
        ));
    }

    private function hasAuthMiddleware(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if (str_contains($entry, 'Authenticate:sanctum') || str_contains($entry, 'auth:sanctum')) {
                return true;
            }
        }

        return false;
    }

    private function hasTenantMiddleware(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if (str_contains($entry, 'TenantIsolationMiddleware') || str_contains($entry, 'tenant.isolation')) {
                return true;
            }
        }

        return false;
    }

    private function explicitRbacMiddleware(array $middleware): array
    {
        return array_values(array_filter($middleware, fn (string $entry) => $this->isExplicitRbacEntry($entry)));
    }

    private function hasGeneralRbacMiddleware(array $middleware): bool
    {
        foreach ($middleware as $entry) {
            if ($this->isGeneralRbacEntry($entry)) {
                return true;
            }
        }

        return false;
    }

    private function isExplicitRbacEntry(string $entry): bool
    {
        if (str_starts_with($entry, 'rbac:') && strlen($entry) > strlen('rbac:')) {
            return true;
        }

        if (str_contains($entry, 'RoleBasedAccessControlMiddleware:')) {
            $parts = explode(':', $entry, 2);
            return isset($parts[1]) && $parts[1] !== '';
        }

        return false;
    }

    private function isGeneralRbacEntry(string $entry): bool
    {
        $entry = trim($entry);

        if ($entry === '' || str_contains($entry, ':')) {
            return $entry === 'rbac';
        }

        $normalized = ltrim($entry, '\\');
        return $normalized === 'App\\Http\\Middleware\\RoleBasedAccessControlMiddleware'
            || str_ends_with($normalized, 'RoleBasedAccessControlMiddleware');
    }
}
