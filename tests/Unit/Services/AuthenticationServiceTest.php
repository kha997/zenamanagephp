<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Services\AuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Authentication Service Test
 * 
 * Unit tests for the AuthenticationService class.
 */
class AuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthenticationService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new AuthenticationService();
    }

    /**
     * Test successful authentication
     */
    public function test_successful_authentication()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $result = $this->authService->authenticate(
            $user->email,
            'password123',
            false
        );

        $this->assertTrue($result['success']);
        $this->assertEquals($user->id, $result['user']['id']);
        $this->assertNotEmpty($result['token']);
    }

    /**
     * Test authentication with invalid credentials
     */
    public function test_authentication_with_invalid_credentials()
    {
        $result = $this->authService->authenticate(
            'nonexistent@example.com',
            'wrongpassword',
            false
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $result['code']);
    }

    /**
     * Test authentication with inactive user
     */
    public function test_authentication_with_inactive_user()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $result = $this->authService->authenticate(
            $user->email,
            'password123',
            false
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_CREDENTIALS', $result['code']);
    }

    /**
     * Test authentication with user without tenant
     */
    public function test_authentication_with_user_without_tenant()
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $result = $this->authService->authenticate(
            $user->email,
            'password123',
            false
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('NO_TENANT_ACCESS', $result['code']);
    }

    /**
     * Test token generation
     */
    public function test_token_generation()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $token = $this->authService->generateToken($user, false);

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    /**
     * Test token validation
     */
    public function test_token_validation()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;
        $validatedUser = $this->authService->validateToken($token);

        $this->assertNotNull($validatedUser);
        $this->assertEquals($user->id, $validatedUser->id);
    }

    /**
     * Test token validation with invalid token
     */
    public function test_token_validation_with_invalid_token()
    {
        $validatedUser = $this->authService->validateToken('invalid-token');

        $this->assertNull($validatedUser);
    }

    /**
     * Test logout
     */
    public function test_logout()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $result = $this->authService->logout($user, $token);

        $this->assertTrue($result['success']);
        $this->assertEquals('Logged out successfully', $result['message']);
    }

    /**
     * Test logout all tokens
     */
    public function test_logout_all_tokens()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $user->createToken('test-token-1');
        $user->createToken('test-token-2');

        $result = $this->authService->logout($user);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $user->tokens()->count());
    }

    /**
     * Test token refresh
     */
    public function test_token_refresh()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $result = $this->authService->refreshToken($token);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['token']);
        $this->assertNotEquals($token, $result['token']);
    }

    /**
     * Test token refresh with invalid token
     */
    public function test_token_refresh_with_invalid_token()
    {
        $result = $this->authService->refreshToken('invalid-token');

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_TOKEN', $result['code']);
    }
}
