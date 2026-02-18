<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\SidebarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SidebarConfigTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_authentication_for_sidebar_admin_endpoints(): void
    {
        $this->getJson('/api/admin/sidebar-configs')->assertStatus(401);
        $this->postJson('/api/admin/sidebar-configs', [])->assertStatus(401);
    }

    /** @test */
    public function it_requires_authentication_for_user_preferences_endpoints(): void
    {
        $this->getJson('/api/user-preferences')->assertStatus(401);
        $this->putJson('/api/user-preferences', [
            'pinned_items' => ['dashboard'],
        ])->assertStatus(401);
    }

    /** @test */
    public function it_can_get_sidebar_for_role_from_service_defaults(): void
    {
        $sidebar = app(SidebarService::class)->getSidebarForRole('project_manager');

        $this->assertIsArray($sidebar);
        $this->assertArrayHasKey('items', $sidebar);
        $this->assertNotEmpty($sidebar['items']);
    }

    /** @test */
    public function it_returns_default_sidebar_when_user_is_not_authenticated(): void
    {
        $sidebar = app(SidebarService::class)->getSidebarForUser(null);

        $this->assertIsArray($sidebar);
        $this->assertArrayHasKey('items', $sidebar);
        $this->assertSame('dashboard', $sidebar['items'][0]['id'] ?? null);
    }

    /** @test */
    public function it_rejects_tenant_header_mismatch_for_sidebar_routes(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $token = $this->loginAndGetToken($tenant, $user, 'password');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => (string) $otherTenant->id,
        ])->getJson('/api/admin/sidebar-configs');

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'TENANT_INVALID');
    }

    private function loginAndGetToken(Tenant $tenant, User $user, string $password): string
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200);

        $token = data_get($response->json(), 'data.token')
            ?? data_get($response->json(), 'token')
            ?? data_get($response->json(), 'data.access_token')
            ?? data_get($response->json(), 'access_token');

        $this->assertIsString($token);

        return $token;
    }
}
