<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Models\AuditLog;
use App\Models\Permission as AppPermission;
use App\Models\Role as AppRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaAuditPiiInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_audit_logs_never_record_sensitive_strings(): void
    {
        [$tenant, $user, $password] = $this->createTenantWithUser();

        $loginResponse = $this->zenaPost('api/zena/auth/login', $tenant, [
            'email' => $user->email,
            'password' => $password,
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('data.token');
        $this->assertIsString($token);

        $loginLog = AuditLog::where('action', 'zena.auth.login')->firstOrFail();
        $this->assertAuditLogIsPiiSafe($loginLog, $token);

        $this->grantPermissionToUser($user, 'auth.me');
        $this->grantPermissionToUser($user, 'auth.logout');

        $this->zenaGet('api/zena/auth/me', $tenant, $token)->assertStatus(200);
        $this->zenaPost('api/zena/auth/logout', $tenant, [], $token)->assertStatus(200);

        $logoutLog = AuditLog::where('action', 'zena.auth.logout')->firstOrFail();
        $this->assertAuditLogIsPiiSafe($logoutLog, $token);
    }

    private function zenaPost(string $uri, Tenant $tenant, array $payload = [], ?string $token = null): TestResponse
    {
        return $this->withHeaders($this->withTenantHeader($tenant, $token))->postJson($uri, $payload);
    }

    private function zenaGet(string $uri, Tenant $tenant, string $token): TestResponse
    {
        return $this->withHeaders($this->withTenantHeader($tenant, $token))->getJson($uri);
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

    private function assertAuditLogIsPiiSafe(AuditLog $log, string $token): void
    {
        $forbidden = ['password', 'token', 'authorization', 'bearer', 'personalaccesstoken'];
        $metaJson = json_encode($log->meta ?? []) ?: '';
        $fields = array_filter(
            [$log->route, $log->method, $metaJson, (string) $log->status_code],
            fn ($value) => $value !== null && $value !== ''
        );

        foreach ($fields as $value) {
            $lowerValue = strtolower((string) $value);
            foreach ($forbidden as $keyword) {
                $this->assertStringNotContainsStringIgnoringCase($keyword, $lowerValue);
            }
        }

        foreach ((array) ($log->meta ?? []) as $key => $value) {
            $lowerKey = strtolower((string) $key);
            foreach ($forbidden as $keyword) {
                $this->assertStringNotContainsStringIgnoringCase($keyword, $lowerKey);

                $valueToCheck = is_array($value) ? json_encode($value) : (string) $value;
                $this->assertStringNotContainsStringIgnoringCase($keyword, strtolower($valueToCheck));
            }
        }

        $this->assertStringNotContainsString($token, json_encode($log->toArray()));
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
