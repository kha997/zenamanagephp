<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;
use Tests\Traits\RouteNameTrait;

class SettingsNotificationsApiTest extends TestCase
{
    use RefreshDatabase, AuthenticationTrait, RouteNameTrait;

    public function test_notifications_requires_authentication(): void
    {
        $this->getJson($this->v1('settings.notifications'))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'E401.AUTHENTICATION');
    }

    public function test_notifications_requires_notification_read_permission(): void
    {
        $tenant = Tenant::factory()->create();
        [, $headers] = $this->createApiUserWithPermissions($tenant, []);

        $this->getJson($this->v1('settings.notifications'), $headers)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'E403.AUTHORIZATION');
    }

    public function test_notifications_returns_default_settings_with_permission(): void
    {
        $tenant = Tenant::factory()->create();
        [, $headers] = $this->createApiUserWithPermissions($tenant, ['notification.read']);

        $this->getJson($this->v1('settings.notifications'), $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'success',
                'status',
                'data' => [
                    'project_updates',
                    'milestone_completions',
                    'task_updates',
                    'team_changes',
                    'document_uploads',
                    'status_changes',
                    'email_notifications',
                    'push_notifications',
                    'real_time_updates',
                ],
                'message',
            ]);
    }

    public function test_notifications_returns_default_settings_when_preferences_is_null(): void
    {
        $tenant = Tenant::factory()->create();
        [$user, $headers] = $this->createApiUserWithPermissions($tenant, ['notification.read']);
        $user->forceFill(['preferences' => null])->save();

        $this->getJson($this->v1('settings.notifications'), $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.project_updates', true)
            ->assertJsonPath('data.milestone_completions', true)
            ->assertJsonPath('data.task_updates', true)
            ->assertJsonPath('data.team_changes', true)
            ->assertJsonPath('data.document_uploads', true)
            ->assertJsonPath('data.status_changes', true)
            ->assertJsonPath('data.email_notifications', true)
            ->assertJsonPath('data.push_notifications', true)
            ->assertJsonPath('data.real_time_updates', true);
    }

    public function test_patch_updates_notification_settings_and_persists(): void
    {
        $tenant = Tenant::factory()->create();
        [$user, $headers] = $this->createApiUserWithPermissions(
            $tenant,
            ['notification.read', 'notification.manage_rules']
        );

        $this->patchJson($this->v1('settings.notifications.update'), [
            'email_notifications' => false,
            'task_updates' => false,
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.email_notifications', false)
            ->assertJsonPath('data.task_updates', false);

        $this->getJson($this->v1('settings.notifications'), $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.email_notifications', false)
            ->assertJsonPath('data.task_updates', false);

        $user->refresh();
        $settings = data_get($user->preferences, 'notifications', []);

        $this->assertFalse((bool) data_get($settings, 'email_notifications', true));
        $this->assertFalse((bool) data_get($settings, 'task_updates', true));
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

        $token = $user->createToken('settings-notifications-test')->plainTextToken;

        return [$user, $this->authHeadersForUser($user, $token)];
    }
}
