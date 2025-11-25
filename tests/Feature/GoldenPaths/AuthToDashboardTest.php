<?php declare(strict_types=1);

namespace Tests\Feature\GoldenPaths;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Golden Path 1: Auth + Tenant Selection + Dashboard
 * 
 * Tests the critical flow: Login → Get user context → Get navigation → Load dashboard
 */
class AuthToDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant
        $this->tenantId = '01K83FPK5XGPXF3V7ANJQRGX5X';
        
        // Create user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenantId,
            'role' => 'member',
        ]);
    }

    /** @test */
    public function user_can_login_and_get_token(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'user',
                'token',
            ],
        ]);
        
        $data = $response->json('data');
        $this->assertEquals($this->user->id, $data['user']['id']);
        $this->assertNotEmpty($data['token']);
    }

    /** @test */
    public function user_can_get_own_context(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'user' => [
                'id',
                'email',
                'tenant_id',
                'role',
            ],
            'permissions',
            'abilities',
        ]);
        
        $data = $response->json();
        $this->assertEquals($this->user->id, $data['user']['id']);
        $this->assertEquals($this->tenantId, $data['user']['tenant_id']);
        $this->assertContains('tenant', $data['abilities']);
    }

    /** @test */
    public function regular_user_does_not_have_admin_ability(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $data = $response->json();
        $this->assertNotContains('admin', $data['abilities']);
    }

    /** @test */
    public function super_admin_has_admin_ability(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@zena.local',
            'password' => Hash::make('password'),
            'tenant_id' => null,
            'is_admin' => true,
            'role' => 'super_admin',
        ]);
        
        $token = $admin->createToken('test')->plainTextToken;
        
        $response = $this->getJson('/api/v1/me', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $data = $response->json();
        $this->assertContains('admin', $data['abilities']);
    }

    /** @test */
    public function user_can_get_navigation_menu(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->getJson('/api/v1/me/nav', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'menu' => [
                '*' => [
                    'label',
                    'path',
                ],
            ],
        ]);
    }

    /** @test */
    public function regular_user_navigation_excludes_admin_items(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->getJson('/api/v1/me/nav', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $menu = $response->json('menu');
        
        // Regular user should not see admin menu items
        $adminItems = array_filter($menu, function ($item) {
            return str_contains($item['path'] ?? '', '/admin') 
                || ($item['permission'] ?? '') === 'admin.access';
        });
        
        $this->assertEmpty($adminItems, 'Regular user should not see admin menu items');
    }

    /** @test */
    public function user_can_access_dashboard_metrics(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;
        
        $response = $this->getJson('/api/v1/dashboard/metrics', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'data' => [
                'metrics',
            ],
        ]);
    }

    /** @test */
    public function user_without_tenant_gets_403_on_dashboard(): void
    {
        $userWithoutTenant = User::factory()->create([
            'email' => 'notenant@example.com',
            'password' => Hash::make('password'),
            'tenant_id' => null,
        ]);
        
        $token = $userWithoutTenant->createToken('test')->plainTextToken;
        
        $response = $this->getJson('/api/v1/dashboard/metrics', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        // Should get 403 or 401
        $this->assertContains($response->status(), [401, 403]);
    }

    /** @test */
    public function invalid_credentials_returns_proper_error(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'invalid@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure([
            'ok',
            'code',
            'message',
        ]);
        
        $this->assertFalse($response->json('ok'));
        $this->assertEquals('UNAUTHORIZED', $response->json('code'));
    }
}

