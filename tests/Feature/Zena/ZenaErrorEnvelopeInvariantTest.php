<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Permission as AppPermission;
use App\Models\Role as AppRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaErrorEnvelopeInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedAuthPermissions();
        $this->seedSystemAdminRole();
    }

    public function test_unauthenticated_rfi_endpoint_returns_zena_envelope(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withHeaders($this->withTenantHeader($tenant))->getJson('api/zena/rfis');

        $response->assertStatus(401);
        $this->assertZenaErrorEnvelope($response, 'E401.AUTHENTICATION');
    }

    public function test_rfi_endpoint_denied_without_permission_returns_envelope(): void
    {
        [$tenant, $user, $password] = $this->createTenantWithUser();
        $token = $this->loginAndReturnToken($tenant, $user->email, $password);

        $response = $this->zenaGet('api/zena/rfis', $tenant, $token);

        $response->assertStatus(403);
        $this->assertZenaErrorEnvelope($response, 'E403.AUTHORIZATION');
    }

    public function test_rfi_not_found_returns_error_envelope(): void
    {
        [$tenant, $user, $password] = $this->createTenantWithUser();
        $token = $this->loginAndReturnToken($tenant, $user->email, $password);
        $this->grantPermissionToUser($user, 'rfi.view');

        $response = $this->zenaGet('api/zena/rfis/' . Str::ulid(), $tenant, $token);

        $response->assertStatus(404);
        $this->assertZenaErrorEnvelope($response, 'E404.NOT_FOUND');
    }

    private function assertZenaErrorEnvelope(TestResponse $response, string $expectedCode): void
    {
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message',
                'details',
            ],
        ]);
        $this->assertSame($expectedCode, $response->json('error.code'));
        $this->assertIsString($response->json('error.message'));
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
        $response = $this->zenaPost('api/zena/auth/login', $tenant, [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertStatus(200);
        $token = $response->json('data.token');
        $this->assertIsString($token, 'Login must return a token string.');

        return $token;
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

    private function zenaGet(string $uri, Tenant $tenant, ?string $token = null): TestResponse
    {
        return $this->withHeaders($this->withTenantHeader($tenant, $token))->getJson($uri);
    }

    private function zenaPost(string $uri, Tenant $tenant, array $payload = [], ?string $token = null): TestResponse
    {
        return $this->withHeaders($this->withTenantHeader($tenant, $token))->postJson($uri, $payload);
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

        $hasPermission = $user->hasPermission($permission->name);
        $this->assertTrue(
            $hasPermission,
            sprintf('Failed to grant %s to user %s', $permission->name, $user->id)
        );

        return $permission;
    }
}
