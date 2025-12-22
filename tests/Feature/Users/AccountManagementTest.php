<?php declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\User;
use App\Models\Tenant;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\Helpers\AuthHelper;
use Tests\TestCase;

/**
 * Account Management Test
 * 
 * Tests for user account and session management functionality
 */
class AccountManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Tenant $tenant;
    protected array $authHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Get auth headers for API requests
        $this->authHeaders = AuthHelper::getAuthHeaders($this, $this->user->email, 'password');
    }

    /**
     * Test get user sessions
     */
    public function test_user_can_get_their_sessions()
    {
        // Create some sessions
        UserSession::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->withHeaders($this->authHeaders)
            ->getJson('/api/users/sessions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'sessions' => [
                        '*' => [
                            'id',
                            'ip_address',
                            'user_agent',
                            'last_activity',
                            'expires_at',
                            'created_at',
                        ],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Test get sessions requires authentication
     */
    public function test_get_sessions_requires_authentication()
    {
        $response = $this->getJson('/api/users/sessions');

        $response->assertStatus(401);
    }

    /**
     * Test revoke specific session
     */
    public function test_user_can_revoke_specific_session()
    {
        $session = UserSession::factory()->create([
            'user_id' => $this->user->id,
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->withHeaders($this->authHeaders)
            ->deleteJson("/api/users/sessions/{$session->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify session was revoked
        $session->refresh();
        $this->assertTrue($session->expires_at->isPast());
    }

    /**
     * Test revoke session requires authentication
     */
    public function test_revoke_session_requires_authentication()
    {
        $session = UserSession::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/users/sessions/{$session->id}");

        $response->assertStatus(401);
    }

    /**
     * Test revoke session fails for other user's session
     */
    public function test_revoke_session_fails_for_other_user_session()
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('password'),
        ]);

        $session = UserSession::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->withHeaders($this->authHeaders)
            ->deleteJson("/api/users/sessions/{$session->id}");

        $response->assertStatus(404);
    }

    /**
     * Test revoke all sessions
     */
    public function test_user_can_revoke_all_sessions()
    {
        // Create multiple sessions
        UserSession::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->withHeaders($this->authHeaders)
            ->deleteJson('/api/users/sessions');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.sessions_revoked', 3);

        // Verify all sessions were revoked
        $activeSessions = UserSession::where('user_id', $this->user->id)
            ->where(function ($query) {
                $query->where('expires_at', '>', now())
                      ->orWhereNull('expires_at');
            })
            ->count();

        $this->assertEquals(0, $activeSessions);
    }

    /**
     * Test revoke all sessions requires authentication
     */
    public function test_revoke_all_sessions_requires_authentication()
    {
        $response = $this->deleteJson('/api/users/sessions');

        $response->assertStatus(401);
    }

    /**
     * Test delete account
     */
    public function test_user_can_delete_their_account()
    {
        // Create sessions for the user
        UserSession::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->withHeaders($this->authHeaders)
            ->deleteJson('/api/users/account');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify user was soft deleted
        $this->assertSoftDeleted('users', [
            'id' => $this->user->id,
        ]);
    }

    /**
     * Test delete account requires authentication
     */
    public function test_delete_account_requires_authentication()
    {
        $response = $this->deleteJson('/api/users/account');

        $response->assertStatus(401);
    }

    /**
     * Test multi-tenant isolation for sessions
     */
    public function test_sessions_respect_tenant_isolation()
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('password'),
        ]);

        // Create sessions for both users
        UserSession::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'expires_at' => now()->addHours(24),
        ]);

        UserSession::factory()->count(2)->create([
            'user_id' => $otherUser->id,
            'expires_at' => now()->addHours(24),
        ]);

        // User from tenant A can only see their own sessions
        $response = $this->withHeaders($this->authHeaders)
            ->getJson('/api/users/sessions');

        $response->assertStatus(200);
        $sessions = $response->json('data.sessions');
        $this->assertCount(2, $sessions);
        $this->assertTrue(collect($sessions)->every(fn ($s) => $s['id'] !== null));
    }
}

