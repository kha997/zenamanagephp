<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;
use Tests\Traits\RouteNameTrait;

class SettingsSecurityApiTest extends TestCase
{
    use RefreshDatabase, AuthenticationTrait, RouteNameTrait;

    public function test_security_requires_authentication(): void
    {
        $this->getJson($this->v1('settings.security'))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'E401.AUTHENTICATION');
    }

    public function test_security_requires_read_permission(): void
    {
        $tenant = Tenant::factory()->create();
        [, $headers] = $this->createApiUserWithPermissions($tenant, []);

        $this->getJson($this->v1('settings.security'), $headers)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'E403.AUTHORIZATION');
    }

    public function test_security_returns_default_settings_with_permission(): void
    {
        $tenant = Tenant::factory()->create();
        [, $headers] = $this->createApiUserWithPermissions($tenant, ['settings.security.read']);

        $this->getJson($this->v1('settings.security'), $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'success',
                'status',
                'data' => [
                    'passwordMinLength',
                    'passwordRequireUppercase',
                    'passwordRequireNumbers',
                    'passwordRequireSymbols',
                    'maxLoginAttempts',
                    'lockoutDuration',
                    'twoFactorEnabled',
                    'ipWhitelist',
                    'sessionSecure',
                ],
                'message',
            ]);
    }

    public function test_patch_updates_security_settings_and_persists(): void
    {
        $tenant = Tenant::factory()->create();
        [$user, $headers] = $this->createApiUserWithPermissions(
            $tenant,
            ['settings.security.read', 'settings.security.update']
        );

        $this->patchJson($this->v1('settings.security.update'), [
            'passwordMinLength' => 10,
            'twoFactorEnabled' => true,
            'ipWhitelist' => ['127.0.0.1'],
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.passwordMinLength', 10)
            ->assertJsonPath('data.twoFactorEnabled', true)
            ->assertJsonPath('data.ipWhitelist.0', '127.0.0.1');

        $this->getJson($this->v1('settings.security'), $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.passwordMinLength', 10)
            ->assertJsonPath('data.twoFactorEnabled', true)
            ->assertJsonPath('data.ipWhitelist.0', '127.0.0.1');

        $user->refresh();
        $settings = data_get($user->preferences, 'security', []);

        $this->assertSame(10, (int) data_get($settings, 'passwordMinLength'));
        $this->assertTrue((bool) data_get($settings, 'twoFactorEnabled', false));
        $this->assertSame(['127.0.0.1'], data_get($settings, 'ipWhitelist'));
    }

    /**
     * @param list<string> $permissions
     * @param list<string> $roles
     * @return array{0: User, 1: array<string, string>}
     */
    private function createApiUserWithPermissions(Tenant $tenant, array $permissions, array $roles = ['admin']): array
    {
        $user = $this->createTenantUser(
            $tenant,
            [],
            $roles,
            $permissions
        );

        $token = $user->createToken('settings-security-test')->plainTextToken;

        return [$user, $this->authHeadersForUser($user, $token)];
    }
}
