<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\AuditLog;
use App\Models\Permission as AppPermission;
use App\Models\Project;
use App\Models\Role as AppRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaAuditInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedAuthPermissions();
        $this->seedSystemAdminRole();
    }

    public function test_login_success_writes_audit_log(): void
    {
        [$tenant, $user, $password] = $this->createTenantWithUser();

        $response = $this->zenaPost('api/zena/auth/login', $tenant, [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200);
        $this->assertIsString($response->json('data.token'));

        $log = AuditLog::where('action', 'zena.auth.login')->firstOrFail();

        $this->assertSame('api/zena/auth/login', $log->route);
        $this->assertSame('POST', $log->method);
        $this->assertSame(200, $log->status_code);
        $this->assertSame((string) $tenant->id, $log->tenant_id);
        $this->assertSame((string) $user->id, $log->user_id);
        $this->assertSame('auth', $log->entity_type);
        $this->assertSame((string) $user->id, $log->entity_id);
    }

    public function test_logout_writes_audit_log(): void
    {
        [$tenant, $user, $password] = $this->createTenantWithUser();
        $token = $this->loginAndReturnToken($tenant, $user->email, $password);
        $this->grantPermissionToUser($user, 'auth.logout');

        $this->zenaPost('api/zena/auth/logout', $tenant, [], $token)->assertStatus(200);

        $log = AuditLog::where('action', 'zena.auth.logout')->firstOrFail();

        $this->assertSame('api/zena/auth/logout', $log->route);
        $this->assertSame('POST', $log->method);
        $this->assertSame(200, $log->status_code);
        $this->assertSame((string) $tenant->id, $log->tenant_id);
        $this->assertSame((string) $user->id, $log->user_id);
        $this->assertSame('auth', $log->entity_type);
        $this->assertSame((string) $user->id, $log->entity_id);
    }

    public function test_rfi_create_update_delete_write_audit_logs(): void
    {
        [$tenant, $user, $password] = $this->createTenantWithUser();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $token = $this->loginAndReturnToken($tenant, $user->email, $password);

        foreach (['rfi.create', 'rfi.edit', 'rfi.delete'] as $permission) {
            $this->grantPermissionToUser($user, $permission);
        }

        $createPayload = [
            'project_id' => $project->id,
            'title' => 'Audit RFI',
            'subject' => 'Audit subject',
            'description' => 'Describe the issue',
            'priority' => 'medium',
            'due_date' => now()->addDay()->toDateString(),
        ];

        $createResponse = $this->zenaPost('api/zena/rfis', $tenant, $createPayload, $token);
        $createResponse->assertStatus(201);
        $rfiId = $createResponse->json('data.id');

        $createLog = AuditLog::where('action', 'zena.rfi.create')->firstOrFail();
        $this->assertSame('api/zena/rfis', $createLog->route);
        $this->assertSame('POST', $createLog->method);
        $this->assertSame(201, $createLog->status_code);
        $this->assertSame((string) $tenant->id, $createLog->tenant_id);
        $this->assertSame((string) $user->id, $createLog->user_id);
        $this->assertSame('rfi', $createLog->entity_type);
        $this->assertSame((string) $project->id, $createLog->project_id);
        $this->assertSame((string) $rfiId, $createLog->entity_id);

        $updateResponse = $this->zenaPut('api/zena/rfis/' . $rfiId, $tenant, ['title' => 'Audit RFI v2'], $token);
        $updateResponse->assertStatus(200);

        $updateLog = AuditLog::where('action', 'zena.rfi.update')->firstOrFail();
        $this->assertStringStartsWith('api/zena/rfis/', $updateLog->route);
        $this->assertSame('PUT', $updateLog->method);
        $this->assertSame(200, $updateLog->status_code);
        $this->assertSame((string) $tenant->id, $updateLog->tenant_id);
        $this->assertSame((string) $user->id, $updateLog->user_id);
        $this->assertSame('rfi', $updateLog->entity_type);
        $this->assertSame((string) $project->id, $updateLog->project_id);
        $this->assertSame((string) $rfiId, $updateLog->entity_id);

        $deleteResponse = $this->zenaDelete('api/zena/rfis/' . $rfiId, $tenant, $token);
        $deleteResponse->assertStatus(200);

        $deleteLog = AuditLog::where('action', 'zena.rfi.delete')->firstOrFail();
        $this->assertStringStartsWith('api/zena/rfis/', $deleteLog->route);
        $this->assertSame('DELETE', $deleteLog->method);
        $this->assertSame(200, $deleteLog->status_code);
        $this->assertSame((string) $tenant->id, $deleteLog->tenant_id);
        $this->assertSame((string) $user->id, $deleteLog->user_id);
        $this->assertSame('rfi', $deleteLog->entity_type);
        $this->assertSame((string) $project->id, $deleteLog->project_id);
        $this->assertSame((string) $rfiId, $deleteLog->entity_id);
    }

    private function zenaPost(string $uri, Tenant $tenant, array $payload = [], ?string $token = null): TestResponse
    {
        return $this->withHeaders($this->withTenantHeader($tenant, $token))->postJson($uri, $payload);
    }

    private function zenaPut(string $uri, Tenant $tenant, array $payload = [], ?string $token = null): TestResponse
    {
        return $this->withHeaders($this->withTenantHeader($tenant, $token))->putJson($uri, $payload);
    }

    private function zenaDelete(string $uri, Tenant $tenant, ?string $token = null): TestResponse
    {
        return $this->withHeaders($this->withTenantHeader($tenant, $token))->deleteJson($uri);
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

    private function seedAuthPermissions(): void
    {
        $codes = ['auth.logout'];

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
