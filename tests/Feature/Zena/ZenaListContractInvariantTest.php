<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Permission as AppPermission;
use App\Models\Role as AppRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaListContractInvariantTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_ALLOWLIST = [
        'api/zena',
        'api/zena/auth/login',
        'api/zena/health',
    ];

    private const SENSITIVE_SUBSTRINGS = [
        'stack',
        'trace',
        'exception',
        'password',
        'authorization',
        'bearer',
        'token',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthPermissions();
        $this->seedSystemAdminRole();
    }

    public function test_zena_index_routes_use_list_contract(): void
    {
        $routes = $this->collectIndexRoutes();

        $this->assertNotEmpty($routes, 'No ZENA index routes were discovered to verify.');

        [$tenant, $user, $password] = $this->createTenantWithUser();
        [$token, $userTenantId] = $this->loginAndReturnToken($tenant, $user->email, $password);

        foreach ($routes as $route) {
            $this->assertRouteReturnsListEnvelope($route, $user, $token, $userTenantId);
        }
    }

    private function collectIndexRoutes(): \Illuminate\Support\Collection
    {
        return collect(RouteFacade::getRoutes())
            ->filter(fn (RoutingRoute $route) => Str::startsWith($route->uri(), 'api/zena/'))
            ->filter(fn (RoutingRoute $route) => $this->shouldTestRoute($route))
            ->values();
    }

    private function shouldTestRoute(RoutingRoute $route): bool
    {
        if (in_array($route->uri(), self::PUBLIC_ALLOWLIST, true)) {
            return false;
        }

        $action = $route->getActionName();
        if ($action === 'Closure' || !Str::endsWith($action, '@index')) {
            return false;
        }

        return $this->supportsGetOrHead($route);
    }

    private function supportsGetOrHead(RoutingRoute $route): bool
    {
        $methods = $route->methods();
        return in_array('GET', $methods, true) || in_array('HEAD', $methods, true);
    }

    private function assertRouteReturnsListEnvelope(RoutingRoute $route, User $user, string $token, string $userTenantId): void
    {
        $permissions = $this->extractPermissionCodes($route->gatherMiddleware());
        foreach ($permissions as $permission) {
            $this->grantPermissionToUser($user, $permission);
        }

        $response = $this->withHeaders($this->buildRouteHeaders($userTenantId, $token))
            ->getJson($route->uri());

        $response->assertStatus(200, sprintf('Route %s must respond with 200 OK', $route->uri()));
        $response->assertJson([
            'success' => true,
            'status' => 'success',
        ]);

        $this->assertIsArray(
            $response->json('data'),
            sprintf('Route %s must return data as array.', $route->uri())
        );

        $pagination = $response->json('meta.pagination');
        if (is_array($pagination)) {
            foreach (['page', 'per_page', 'total', 'last_page'] as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $pagination,
                    sprintf('meta.pagination.%s missing for %s', $key, $route->uri())
                );
                $this->assertIsNumeric(
                    $pagination[$key],
                    sprintf('meta.pagination.%s must be numeric for %s', $key, $route->uri())
                );
            }
        }

        $body = strtolower($response->getContent());
        foreach (self::SENSITIVE_SUBSTRINGS as $substring) {
            $this->assertStringNotContainsString(
                $substring,
                $body,
                sprintf('Route %s response must not leak %s.', $route->uri(), $substring)
            );
        }
    }

    private function extractPermissionCodes(array $middlewares): array
    {
        $codes = [];
        $rbacPrefix = 'rbac:';
        $classPrefix = RoleBasedAccessControlMiddleware::class . ':';

        foreach ($middlewares as $middleware) {
            if (Str::startsWith($middleware, $rbacPrefix)) {
                $codes[] = Str::after($middleware, $rbacPrefix);
                continue;
            }

            if (Str::startsWith($middleware, $classPrefix)) {
                $codes[] = Str::after($middleware, $classPrefix);
            }
        }

        return array_values(array_filter(array_unique($codes)));
    }

    private function buildRouteHeaders(string $tenantId, string $token, bool $includeToken = true): array
    {
        $headers = [
            'Accept' => 'application/json',
            'X-Tenant-ID' => $tenantId,
        ];

        if ($includeToken && $token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * @return array{string, string} [token, tenantId]
     */
    private function loginAndReturnToken(Tenant $tenant, string $email, string $password): array
    {
        Auth::shouldUse('web');
        $response = $this->withHeaders($this->buildRouteHeaders((string) $tenant->id, '', false))
            ->postJson('api/zena/auth/login', [
                'email' => $email,
                'password' => $password,
            ]);

        $response->assertStatus(200);

        $token = $response->json('data.token');
        $this->assertIsString($token, 'Login must return a bearer token to iterate index routes.');

        $accessToken = PersonalAccessToken::findToken($token);
        $this->assertNotNull($accessToken, 'Personal access token should exist after login.');

        $tenantId = (string) $accessToken->tokenable->tenant_id;

        return [$token, $tenantId];
    }

    private function createTenantWithUser(string $password = 'Secret123!'): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make($password),
        ]);

        return [$tenant, $user, $password];
    }

    private function seedAuthPermissions(): void
    {
        $codes = ['auth.me', 'auth.logout'];

        foreach ($codes as $code) {
            [$module, $action] = array_pad(explode('.', $code, 2), 2, 'access');

            AppPermission::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => $module,
                    'action' => $action,
                    'description' => 'Invariant seed for ' . $code,
                ]
            );
        }
    }

    private function seedSystemAdminRole(): void
    {
        AppRole::firstOrCreate(
            ['name' => 'System Admin'],
            [
                'scope' => AppRole::SCOPE_SYSTEM,
                'description' => 'System admin for invariants',
                'allow_override' => true,
            ]
        );
    }

    private function grantPermissionToUser(User $user, string $permissionKey): AppPermission
    {
        [$module, $action] = array_pad(explode('.', $permissionKey, 2), 2, 'access');

        $permission = AppPermission::firstOrCreate(
            ['code' => $permissionKey],
            [
                'name' => $permissionKey,
                'module' => $module,
                'action' => $action,
                'description' => 'Invariant grant for ' . $permissionKey,
            ]
        );

        $role = AppRole::firstOrCreate(
            ['name' => 'System Admin'],
            [
                'scope' => AppRole::SCOPE_SYSTEM,
                'description' => 'System administrator',
                'allow_override' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->assertTrue(
            $user->hasPermission($permission->name),
            sprintf('Failed to grant %s to user %s', $permission->name, $user->id)
        );

        return $permission;
    }
}
