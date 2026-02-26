<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;
use Tests\Traits\RouteNameTrait;

class SettingsGeneralApiTest extends TestCase
{
    use RefreshDatabase, AuthenticationTrait, RouteNameTrait;

    public function test_general_requires_authentication(): void
    {
        $this->getJson($this->v1('settings.general'))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'E401.AUTHENTICATION');
    }

    public function test_general_requires_read_permission(): void
    {
        $tenant = Tenant::factory()->create();
        [, $headers] = $this->createApiUserWithPermissions($tenant, []);

        $this->getJson($this->v1('settings.general'), $headers)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'E403.AUTHORIZATION');
    }

    public function test_general_returns_default_settings_with_permission(): void
    {
        $tenant = Tenant::factory()->create();
        [, $headers] = $this->createApiUserWithPermissions($tenant, ['settings.general.read']);

        $this->getJson($this->v1('settings.general'), $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'success',
                'status',
                'data' => [
                    'siteName',
                    'siteUrl',
                    'adminEmail',
                    'timezone',
                    'language',
                    'maintenanceMode',
                    'registrationEnabled',
                    'emailVerificationRequired',
                    'maxFileUploadSize',
                    'sessionTimeout',
                ],
                'message',
            ]);
    }

    public function test_patch_updates_general_settings_and_persists(): void
    {
        $tenant = Tenant::factory()->create();
        [$user, $headers] = $this->createApiUserWithPermissions(
            $tenant,
            ['settings.general.read', 'settings.general.update']
        );

        $this->patchJson($this->v1('settings.general.update'), [
            'siteName' => 'ZENA Updated',
            'maintenanceMode' => true,
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.siteName', 'ZENA Updated')
            ->assertJsonPath('data.maintenanceMode', true);

        $this->getJson($this->v1('settings.general'), $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.siteName', 'ZENA Updated')
            ->assertJsonPath('data.maintenanceMode', true);

        $user->refresh();
        $settings = data_get($user->preferences, 'general', []);

        $this->assertSame('ZENA Updated', (string) data_get($settings, 'siteName'));
        $this->assertTrue((bool) data_get($settings, 'maintenanceMode', false));
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

        $token = $user->createToken('settings-general-test')->plainTextToken;

        return [$user, $this->authHeadersForUser($user, $token)];
    }
}
