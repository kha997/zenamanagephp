<?php declare(strict_types=1);

namespace Tests\Feature\RouteMiddleware;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class V1LegacyRouteHardeningContractTest extends TestCase
{
    use RefreshDatabase;

    private const UNIVERSAL_FRAME_PREFIX = 'api/v1/universal-frame';
    private const FINAL_INTEGRATION_PREFIX = 'api/v1/final-integration';

    private const EXPECTED_STACK = [
        'auth',
        'tenant.isolation',
        'rbac:admin',
        'input.sanitization',
        'error.envelope',
    ];

    public function test_universal_frame_routes_enforce_expected_hardening_stack(): void
    {
        $routes = $this->routesForPrefix(self::UNIVERSAL_FRAME_PREFIX);

        $this->assertGreaterThan(0, count($routes), 'Expected universal-frame routes to exist.');

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();

            foreach (self::EXPECTED_STACK as $required) {
                $this->assertTrue(
                    $this->hasMiddleware($middleware, $required),
                    $this->formatMissingMiddlewareMessage($route, $required, $middleware)
                );
            }
        }
    }

    public function test_final_integration_routes_enforce_expected_hardening_stack(): void
    {
        $routes = $this->routesForPrefix(self::FINAL_INTEGRATION_PREFIX);

        $this->assertGreaterThan(0, count($routes), 'Expected final-integration routes to exist.');

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();

            foreach (self::EXPECTED_STACK as $required) {
                $this->assertTrue(
                    $this->hasMiddleware($middleware, $required),
                    $this->formatMissingMiddlewareMessage($route, $required, $middleware)
                );
            }
        }
    }

    public function test_universal_frame_analysis_validation_failure_returns_error_envelope_shape(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Tenant-ID' => (string) $tenant->id,
            ])
            ->postJson('/api/v1/universal-frame/analysis', []);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'success',
            'error' => [
                'id',
                'code',
                'message',
                'details',
            ],
        ]);
    }

    public function test_final_integration_route_rejects_cross_tenant_header_with_error_envelope(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $admin = User::factory()->create([
            'tenant_id' => $tenantA->id,
        ]);
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Tenant-ID' => (string) $tenantB->id,
            ])
            ->getJson('/api/v1/final-integration/launch-status');

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'TENANT_INVALID');
        $response->assertJsonStructure([
            'success',
            'error' => [
                'id',
                'code',
                'message',
            ],
        ]);
    }

    public function test_final_integration_route_denies_non_admin_user_with_error_envelope(): void
    {
        $tenant = Tenant::factory()->create();
        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'viewer',
        ]);

        $response = $this->actingAs($member)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Tenant-ID' => (string) $tenant->id,
            ])
            ->getJson('/api/v1/final-integration/launch-status');

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'E403.AUTHORIZATION');
        $response->assertJsonStructure([
            'success',
            'error' => [
                'id',
                'code',
                'message',
            ],
        ]);
    }

    /**
     * @return list<RoutingRoute>
     */
    private function routesForPrefix(string $prefix): array
    {
        $matched = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            if (Str::startsWith($route->uri(), $prefix)) {
                $matched[] = $route;
            }
        }

        return $matched;
    }

    /**
     * @param list<string> $middleware
     */
    private function hasMiddleware(array $middleware, string $required): bool
    {
        $aliases = match ($required) {
            'auth' => ['auth', 'auth:sanctum', 'App\\Http\\Middleware\\Authenticate', 'Illuminate\\Auth\\Middleware\\Authenticate'],
            'tenant.isolation' => ['tenant.isolation', 'App\\Http\\Middleware\\TenantIsolationMiddleware'],
            'rbac:admin' => ['rbac:admin', 'App\\Http\\Middleware\\RoleBasedAccessControlMiddleware:admin'],
            'input.sanitization' => ['input.sanitization', 'App\\Http\\Middleware\\InputSanitizationMiddleware'],
            'error.envelope' => ['error.envelope', 'App\\Http\\Middleware\\ErrorEnvelopeMiddleware'],
            default => [$required],
        };

        foreach ($middleware as $entry) {
            foreach ($aliases as $alias) {
                if ($entry === $alias || Str::startsWith($entry, $alias . ':')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<string> $middleware
     */
    private function formatMissingMiddlewareMessage(RoutingRoute $route, string $required, array $middleware): string
    {
        return sprintf(
            'Route [%s] (%s) is missing [%s]. Stack: %s',
            $route->uri(),
            implode('|', array_values(array_filter($route->methods(), static fn (string $method): bool => $method !== 'HEAD'))),
            $required,
            implode(', ', $middleware)
        );
    }
}
