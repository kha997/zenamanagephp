<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Permission as AppPermission;
use App\Models\Role as AppRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Testing\TestResponse;
use Src\RBAC\Services\AuthService;
use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaAuthFlowInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedAuthPermissions();
        $this->seedSystemAdminRole();
    }

    public function test_login_throttle_is_enforced(): void
    {
        [$tenant] = $this->newTenantContext();
        $route = $this->resolveLoginRoute();

        $middlewares = $route->gatherMiddleware();
        $this->assertContains(
            'throttle:zena-login',
            $middlewares,
            'POST api/zena/auth/login must declare throttle:zena-login middleware.'
        );

        $payload = [
            'email' => 'throttle@example.com',
            'password' => 'InvalidPass1',
        ];

        $server = ['REMOTE_ADDR' => '203.0.113.5'];
        $builder = $this->withServerVariables($server)->withHeaders($this->withTenantHeader($tenant));
        $responses = [];

        for ($attempt = 1; $attempt <= 12; $attempt++) {
            $response = $builder->postJson($this->loginUri(), $payload);
            $responses[] = $response;

            if ($attempt === 1) {
                $this->assertNotEquals(
                    429,
                    $response->status(),
                    'First login attempt must not be throttled.'
                );
            }
        }

        $this->assertTrue(
            collect($responses)->contains(fn (TestResponse $response) => $response->status() === 429),
            $this->buildThrottleDiagnostics($middlewares, $responses)
        );
    }

    public function test_login_success_returns_bearer_token(): void
    {
        [$tenant, $user, $password] = $this->createTenantWithUser();

        $response = $this->zenaPost($this->loginUri(), $tenant, [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200);

        $token = $response->json('data.token');
        $response->assertJsonPath('data.user.id', (string) $user->id);
        $response->assertJsonPath('data.user.email', $user->email);
        $this->assertIsString($token, 'Login response must include a bearer token string.');
    }

    public function test_bearer_token_can_call_me_with_tenant_header(): void
    {
        [$tenant, $user, $password] = $this->createTenantWithUser();
        $token = $this->loginAndReturnToken($tenant, $user->email, $password);

        $this->grantPermissionToUser($user, 'auth.me');

        $response = $this->zenaGet($this->meUri(), $tenant, $token);
        $response->assertStatus(200);
        $response->assertJsonPath('data.user.id', (string) $user->id);
        $response->assertJsonPath('data.user.email', $user->email);
    }

    public function test_logout_revokes_token(): void
    {
        [$tenant, $user, $password] = $this->createTenantWithUser();
        $token = $this->loginAndReturnToken($tenant, $user->email, $password);

        $this->grantPermissionToUser($user, 'auth.me');
        $this->grantPermissionToUser($user, 'auth.logout');

        $this->zenaGet($this->meUri(), $tenant, $token)->assertStatus(200);

        $this->zenaPost($this->logoutUri(), $tenant, [], $token)->assertStatus(200);

        app('auth')->forgetGuards();
        $this->flushSession();
        $this->flushHeaders();

        $finalHeaders = $this->withTenantHeader($tenant, $token);
        $finalResponse = $this->withHeaders($finalHeaders)->getJson($this->meUri());

        if ($finalResponse->status() === 200) {
            dump([
                'status' => $finalResponse->status(),
                'body' => $finalResponse->json(),
                'authorization_header_present' => isset($finalHeaders['Authorization']),
            ]);
        }

        $finalResponse->assertStatus(401);
    }

    public function test_me_requires_auth_and_tenant(): void
    {
        [$tenant, $user, $password] = $this->createTenantWithUser();

        $this->zenaGet($this->meUri(), $tenant)->assertStatus(401);

        $token = $this->loginAndReturnToken($tenant, $user->email, $password);

        $missingTenantResponse = $this
            ->flushHeaders()
            ->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ])
            ->getJson($this->meUri());

        $missingTenantResponse->assertStatus(403);

        $this->zenaGet($this->meUri(), $tenant, $token)->assertStatus(403);

        $this->grantPermissionToUser($user, 'auth.me');

        $this->zenaGet($this->meUri(), $tenant, $token)->assertStatus(200);
    }

    public function test_logout_requires_auth_and_respects_rbac_if_present(): void
    {
        [$tenant, $user, $password] = $this->createTenantWithUser();

        $this->zenaPost($this->logoutUri(), $tenant)->assertStatus(401);

        $token = $this->loginAndReturnToken($tenant, $user->email, $password);

        $denied = $this->zenaPost($this->logoutUri(), $tenant, [], $token);
        $denied->assertStatus(403);
        $this->assertSame('E403.AUTHORIZATION', $denied->json('error.code'));

        $this->grantPermissionToUser($user, 'auth.logout');

        $this->zenaPost($this->logoutUri(), $tenant, [], $token)->assertStatus(200);
    }

    private function resolveLoginRoute(): Route
    {
        $route = RouteFacade::getRoutes()->getByName('api.zena.auth.login');
        $this->assertNotNull($route, 'POST api/zena/auth/login is not registered - update routes/api_zena.php.');

        return $route;
    }

    /**
     * @return array{Tenant, User}
     */
    private function newTenantContext(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    /**
     * @return array{Tenant, User, string}
     */
    private function createTenantWithUser(string $password = 'Secret123!'): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make($password),
        ]);

        return [$tenant, $user, $password];
    }

    private function loginAndReturnToken(Tenant $tenant, string $email, string $password): string
    {
        $response = $this->zenaPost($this->loginUri(), $tenant, [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertStatus(200);

        $token = $response->json('data.token');
        $this->assertIsString($token, 'Login must return a string token.');

        return $token;
    }

    private function loginUri(): string
    {
        return route('api.zena.auth.login', [], false);
    }

    private function meUri(): string
    {
        return route('api.zena.auth.me', [], false);
    }

    private function logoutUri(): string
    {
        return route('api.zena.auth.logout', [], false);
    }

    private function withTenantHeader(Tenant $tenant, ?string $token = null): array
    {
        $headers = [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenant->id,
        ];

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    private function zenaGet(string $uri, Tenant $tenant, ?string $token = null, array $server = []): TestResponse
    {
        $request = $this->withHeaders($this->withTenantHeader($tenant, $token));

        if ($server !== []) {
            $request = $request->withServerVariables($server);
        }

        return $request->getJson($uri);
    }

    private function zenaPost(string $uri, Tenant $tenant, array $payload = [], ?string $token = null, array $server = []): TestResponse
    {
        $request = $this->withHeaders($this->withTenantHeader($tenant, $token));

        if ($server !== []) {
            $request = $request->withServerVariables($server);
        }

        return $request->postJson($uri, $payload);
    }

    private function createAuthToken(User $user): string
    {
        return app(AuthService::class)->createTokenForUser($user);
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
                    'description' => 'Invariant test permission for ' . $code,
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
                'description' => 'System administrator',
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
                'description' => 'Invariant user permission for ' . $permissionKey,
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

        $hasPermission = $user->hasPermission($permission->name);
        $this->assertTrue(
            $hasPermission,
            sprintf(
                'User %s still missing %s despite seeding (roles=%s)',
                $user->id,
                $permission->name,
                implode(', ', $user->roles()->pluck('name')->toArray())
            )
        );

        return $permission;
    }

    /**
     * Build a helpful diagnostic message when throttle never returns 429.
     *
     * @param array<int, string> $middlewares
     * @param array<int, TestResponse> $responses
     */
    private function buildThrottleDiagnostics(array $middlewares, array $responses): string
    {
        $statusCounts = collect($responses)
            ->map(fn (TestResponse $response) => $response->status())
            ->countBy()
            ->map(fn (int $count, $status) => "{$status}x{$count}")
            ->values()
            ->implode(', ');

        if ($statusCounts === '') {
            $statusCounts = 'none';
        }

        return sprintf(
            'Login throttle never returned 429 (middleware=%s, statuses=%s).',
            implode(', ', $middlewares),
            $statusCounts
        );
    }
}
