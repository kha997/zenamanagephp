<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\Traits\AuthenticationTestTrait;

/**
 * Feature tests for Authentication endpoints
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTestTrait;

    /**
     * Test user registration
     */
    public function test_user_registration(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'Test Company',
            'company_domain' => 'test-company.local',
            'company_phone' => '0123456789',
            'company_address' => '123 Main St'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'tenant_id'
                        ],
                        'token'
                    ]
                ]);

        $tenantId = $response->json('data.tenant.id');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'tenant_id' => $tenantId
        ]);
    }

    /**
     * Test user login
     */
    public function test_user_login(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'john@example.com',
            'password' => Hash::make('password123')
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'password123'
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'tenant_id'
                    ],
                    'token'
                ]);
    }

    /**
     * Test user login with invalid credentials
     */
    public function test_user_login_invalid_credentials(): void
    {
        $loginData = [
            'email' => 'john@example.com',
            'password' => 'wrongpassword'
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(401)
                ->assertJsonPath('error.message', 'Invalid credentials')
                ->assertJsonPath('error.code', 'E401.AUTHENTICATION');
    }

    /**
     * Test user logout
     */
    public function test_user_logout(): void
    {
        $tenant = Tenant::factory()->create();
        $password = 'logout-password';
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make($password),
        ]);

        $loginResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $token = $loginResponse->json('token')
            ?? $loginResponse->json('data.token')
            ?? $loginResponse->json('data.access_token');
        $this->assertNotNull($token, 'Login response did not return a token.');

        $this->actingAs($user);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenant->id,
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');


        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Logged out successfully'
                ]);
    }

    /**
     * Test getting authenticated user
     */
    public function test_get_authenticated_user(): void
    {
        $tenant = Tenant::factory()->create();
        $password = 'me-password';
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make($password),
        ]);

        $loginResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $token = $loginResponse->json('token')
            ?? $loginResponse->json('data.token')
            ?? $loginResponse->json('data.access_token');
        $this->assertNotNull($token, 'Login response did not return a token.');

        $response = $this
            ->actingAsTenantUser($user, (string) $tenant->id, $token)
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'tenant_id'
                    ]
                ]);
    }

    /**
     * Test accessing protected route without authentication
     */
    public function test_access_protected_route_without_auth(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    /**
     * Test password reset request
     */
    public function test_password_reset_request(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'john@example.com'
        ]);

        Password::shouldReceive('sendResetLink')
                ->with(['email' => 'john@example.com'])
                ->andReturn(Password::RESET_LINK_SENT);

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'john@example.com'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Password reset link has been sent to your email.',
                    'status' => 'success'
                ]);
    }

    /**
     * Test password reset with invalid email
     */
    public function test_password_reset_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'nonexistent@example.com'
        ]);

        $response->assertStatus(422)
                ->assertJsonPath('error.code', 'E422.VALIDATION')
                ->assertJsonPath('error.message', 'No account found with this email address.')
                ->assertJsonPath('error.details.validation.email.0', 'No account found with this email address.');
    }
}
