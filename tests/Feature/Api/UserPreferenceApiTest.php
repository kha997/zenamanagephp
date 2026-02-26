<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSidebarPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;

class UserPreferenceApiTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTrait;

    /**
     * @return array<int, array{method: string, uri: string, permission: string, payload: array<string, mixed>}>
     */
    private function userPreferenceRoutes(): array
    {
        return [
            ['method' => 'GET', 'uri' => '/api/user-preferences', 'permission' => 'user-preferences.read', 'payload' => []],
            ['method' => 'PUT', 'uri' => '/api/user-preferences', 'permission' => 'user-preferences.update', 'payload' => $this->validUpdatePayload()],
            ['method' => 'POST', 'uri' => '/api/user-preferences/pin', 'permission' => 'user-preferences.update', 'payload' => ['item_id' => 'dashboard']],
            ['method' => 'POST', 'uri' => '/api/user-preferences/unpin', 'permission' => 'user-preferences.update', 'payload' => ['item_id' => 'dashboard']],
            ['method' => 'POST', 'uri' => '/api/user-preferences/hide', 'permission' => 'user-preferences.update', 'payload' => ['item_id' => 'tasks']],
            ['method' => 'POST', 'uri' => '/api/user-preferences/show', 'permission' => 'user-preferences.update', 'payload' => ['item_id' => 'tasks']],
            ['method' => 'POST', 'uri' => '/api/user-preferences/custom-order', 'permission' => 'user-preferences.update', 'payload' => ['item_ids' => ['dashboard', 'tasks', 'calendar']]],
            ['method' => 'POST', 'uri' => '/api/user-preferences/theme', 'permission' => 'user-preferences.update', 'payload' => ['theme' => 'dark']],
            ['method' => 'POST', 'uri' => '/api/user-preferences/toggle-compact', 'permission' => 'user-preferences.update', 'payload' => []],
            ['method' => 'POST', 'uri' => '/api/user-preferences/toggle-badges', 'permission' => 'user-preferences.update', 'payload' => []],
            ['method' => 'POST', 'uri' => '/api/user-preferences/toggle-auto-expand', 'permission' => 'user-preferences.update', 'payload' => []],
            ['method' => 'POST', 'uri' => '/api/user-preferences/reset', 'permission' => 'user-preferences.update', 'payload' => []],
            ['method' => 'GET', 'uri' => '/api/user-preferences/stats', 'permission' => 'user-preferences.read', 'payload' => []],
            ['method' => 'POST', 'uri' => '/api/user-preferences/bulk-update', 'permission' => 'user-preferences.update', 'payload' => ['updates' => ['theme' => 'dark', 'compact_mode' => true]]],
        ];
    }

    public function test_all_user_preferences_routes_require_authentication(): void
    {
        foreach ($this->userPreferenceRoutes() as $route) {
            $this->callUserPreferenceRoute($route)->assertStatus(401);
        }
    }

    public function test_all_user_preferences_routes_require_permission(): void
    {
        $tenant = Tenant::factory()->create();
        [, $headers] = $this->createApiUserWithPermissions($tenant, []);

        foreach ($this->userPreferenceRoutes() as $route) {
            $this->callUserPreferenceRoute($route, $headers)
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'E403.AUTHORIZATION');
        }
    }

    public function test_all_user_preferences_routes_allow_when_permission_is_granted(): void
    {
        $tenant = Tenant::factory()->create();
        [, $headers] = $this->createApiUserWithPermissions($tenant, [
            'user-preferences.read',
            'user-preferences.update',
        ]);

        foreach ($this->userPreferenceRoutes() as $route) {
            $this->callUserPreferenceRoute($route, $headers)
                ->assertStatus(200)
                ->assertJsonPath('success', true);
        }
    }

    public function test_update_preferences_persists_data(): void
    {
        $tenant = Tenant::factory()->create();
        [$user, $headers] = $this->createApiUserWithPermissions($tenant, [
            'user-preferences.read',
            'user-preferences.update',
        ]);

        $payload = $this->validUpdatePayload();
        $this->putJson('/api/user-preferences', $payload, $headers)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.compact_mode', true)
            ->assertJsonPath('data.auto_expand_groups', true);

        $this->getJson('/api/user-preferences', $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.compact_mode', true)
            ->assertJsonPath('data.show_badges', false)
            ->assertJsonPath('data.auto_expand_groups', true);

        $stored = UserSidebarPreference::query()
            ->where('user_id', $user->id)
            ->where('is_enabled', true)
            ->first();

        $this->assertNotNull($stored);
        $this->assertSame($payload['pinned_items'], $stored->preferences['pinned_items']);
        $this->assertSame($payload['hidden_items'], $stored->preferences['hidden_items']);
        $this->assertSame($payload['custom_order'], $stored->preferences['custom_order']);
        $this->assertSame($payload['theme'], $stored->preferences['theme']);
        $this->assertSame($payload['compact_mode'], $stored->preferences['compact_mode']);
        $this->assertSame($payload['show_badges'], $stored->preferences['show_badges']);
        $this->assertSame($payload['auto_expand_groups'], $stored->preferences['auto_expand_groups']);
    }

    public function test_update_preferences_invalid_payload_returns_422_envelope(): void
    {
        $tenant = Tenant::factory()->create();
        [, $headers] = $this->createApiUserWithPermissions($tenant, [
            'user-preferences.update',
        ]);

        $this->putJson('/api/user-preferences', [
            'theme' => 'purple',
            'compact_mode' => 'yes',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'E422.VALIDATION')
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'error' => ['id', 'code', 'message', 'details' => ['validation' => ['theme', 'compact_mode']]],
            ]);
    }

    /**
     * @param array{method: string, uri: string, permission: string, payload: array<string, mixed>} $route
     * @param array<string, string> $headers
     */
    private function callUserPreferenceRoute(array $route, array $headers = []): TestResponse
    {
        if ($route['method'] === 'GET') {
            return $this->getJson($route['uri'], $headers);
        }

        if ($route['method'] === 'PUT') {
            return $this->putJson($route['uri'], $route['payload'], $headers);
        }

        return $this->postJson($route['uri'], $route['payload'], $headers);
    }

    /**
     * @param list<string> $permissions
     * @return array{0: User, 1: array<string, string>}
     */
    private function createApiUserWithPermissions(Tenant $tenant, array $permissions): array
    {
        $user = $this->createTenantUser(
            $tenant,
            ['email' => 'user-pref+' . Str::random(8) . '@example.com'],
            ['admin'],
            $permissions
        );

        $token = $user->createToken('user-pref-test')->plainTextToken;

        return [$user, $this->authHeadersForUser($user, $token)];
    }

    /**
     * @return array<string, mixed>
     */
    private function validUpdatePayload(): array
    {
        return [
            'pinned_items' => ['dashboard', 'tasks'],
            'hidden_items' => ['billing'],
            'custom_order' => ['dashboard', 'tasks', 'calendar'],
            'theme' => 'dark',
            'compact_mode' => true,
            'show_badges' => false,
            'auto_expand_groups' => true,
        ];
    }
}
