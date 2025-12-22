<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;

/**
 * Feature tests for Authentication endpoints
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user registration
     */
    public function test_user_registration(): void
    {
        $tenant = Tenant::factory()->create();
        
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_name' => 'Test Tenant',
            'terms' => true
        ];

        $response = $this->postJson('/api/public/auth/register', $userData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'success',
                    'message',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'tenant_id'
                        ]
                    ],
                    'timestamp'
                ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com'
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
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'id' => 'INVALID_CREDENTIALS',
                        'message' => 'Invalid credentials',
                        'status' => 401,
                    ],
                ]);
    }

    /**
     * Test user logout
     */
    public function test_user_logout(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        // Create a token for the user
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'success' => true,
                    'message' => 'Success',
                    'data' => [
                        'message' => 'Logged out successfully'
                    ]
                ]);
    }

    /**
     * Test getting authenticated user
     */
    public function test_get_authenticated_user(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        // Create a token for the user
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/user');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'message',
                        'timestamp'
                    ]
                ]);
    }

    /**
     * Test accessing protected route without authentication
     */
    public function test_access_protected_route_without_auth(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    /**
     * Test password reset request
     */
    public function test_password_reset_request(): void
    {
        $this->markTestSkipped('Password reset functionality not fully implemented');
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'john@example.com'
        ]);

        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => 'john@example.com'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Password reset link sent to your email'
                ]);
    }

    /**
     * Test password reset with invalid email
     */
    public function test_password_reset_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => 'nonexistent@example.com'
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'message' => 'No account found with this email address.',
                    'errors' => [
                        'email' => [
                            'No account found with this email address.'
                        ]
                    ]
                ]);
    }
}