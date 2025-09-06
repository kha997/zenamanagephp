<?php declare(strict_types=1);

namespace Tests\Traits;

use App\Models\User;
use Src\RBAC\Services\AuthService;
use Illuminate\Support\Facades\Auth;

/**
 * Trait để xử lý authentication trong tests
 * Cung cấp các helper methods để login/logout users trong test environment
 */
trait AuthenticatesUsers
{
    /**
     * Login user và return JWT token
     */
    protected function loginUser(User $user): string
    {
        $authService = app(AuthService::class);
        $result = $authService->createTokenForUser($user);
        
        return $result['access_token'];
    }

    /**
     * Set authorization header với JWT token
     */
    protected function withAuthToken(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
    }

    /**
     * Login user và return headers với JWT token
     */
    protected function actingAsUser(User $user): array
    {
        $token = $this->loginUser($user);
        return $this->withAuthToken($token);
    }

    /**
     * Login admin user và return headers
     */
    protected function actingAsAdmin(?User $admin = null): array
    {
        if (!$admin) {
            $admin = $this->createAdminUser();
        }
        
        return $this->actingAsUser($admin);
    }

    /**
     * Logout current user
     */
    protected function logoutUser(): void
    {
        Auth::logout();
    }

    /**
     * Assert user is authenticated
     */
    protected function assertAuthenticated(): void
    {
        $this->assertTrue(Auth::check(), 'User should be authenticated');
    }

    /**
     * Assert user is not authenticated
     */
    protected function assertGuest(): void
    {
        $this->assertFalse(Auth::check(), 'User should not be authenticated');
    }
}