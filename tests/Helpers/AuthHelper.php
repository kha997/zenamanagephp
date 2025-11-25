<?php declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Hash;

/**
 * AuthHelper - Shared authentication helper for Feature/Integration tests
 * 
 * Provides standardized methods for authentication in tests:
 * - getAuthToken(): Obtain API token via /api/auth/login
 * - authenticateAs(): Set token in request headers
 * - createTestUser(): Standardize user creation
 * 
 * @package Tests\Helpers
 */
class AuthHelper
{
    /**
     * Get authentication token via API login endpoint
     * 
     * @param TestCase $testCase The test case instance
     * @param string $email User email
     * @param string $password User password
     * @param bool $remember Remember me flag
     * @return string|null Authentication token or null on failure
     */
    public static function getAuthToken(
        TestCase $testCase,
        string $email,
        string $password,
        bool $remember = false
    ): ?string {
        $response = $testCase->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'remember' => $remember,
        ], [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        if ($response->status() !== 200) {
            return null;
        }

        $data = $response->json();
        
        // Handle both ApiResponse format (status: 'success', data: {...}) 
        // and direct format (success: true, data: {...})
        if (isset($data['status']) && $data['status'] === 'success' && isset($data['data']['token'])) {
            return $data['data']['token'];
        }
        
        if (isset($data['success']) && $data['success'] === true && isset($data['data']['token'])) {
            return $data['data']['token'];
        }

        return null;
    }

    /**
     * Authenticate test case as a user with API token
     * 
     * @param TestCase $testCase The test case instance
     * @param User|string $user User instance or email
     * @param string|null $password Password (required if $user is email)
     * @return TestCase The test case instance for method chaining
     */
    public static function authenticateAs(TestCase $testCase, $user, ?string $password = null): TestCase
    {
        // If $user is a string (email), get the user and password
        if (is_string($user)) {
            if (!$password) {
                throw new \InvalidArgumentException('Password is required when user is an email string');
            }
            
            $userModel = User::where('email', $user)->first();
            if (!$userModel) {
                throw new \RuntimeException("User with email {$user} not found");
            }
            
            $token = self::getAuthToken($testCase, $user, $password);
            if (!$token) {
                throw new \RuntimeException("Failed to get auth token for user {$user}");
            }
            
            // Set token in headers for subsequent requests
            $testCase->withHeader('Authorization', 'Bearer ' . $token);
            $testCase->withHeader('Accept', 'application/json');
            
            return $testCase;
        }

        // If $user is a User model instance
        if ($user instanceof User) {
            // Try to get token with default password 'password'
            $token = self::getAuthToken($testCase, $user->email, 'password');
            
            if (!$token) {
                throw new \RuntimeException("Failed to get auth token for user {$user->email}");
            }
            
            // Set token in headers
            $testCase->withHeader('Authorization', 'Bearer ' . $token);
            $testCase->withHeader('Accept', 'application/json');
            
            return $testCase;
        }

        throw new \InvalidArgumentException('User must be a User instance or email string');
    }

    /**
     * Create a test user with standardized attributes
     * 
     * @param array $attributes Custom attributes to override defaults
     * @param Tenant|null $tenant Tenant instance (will create one if not provided)
     * @return User Created user instance
     */
    public static function createTestUser(array $attributes = [], ?Tenant $tenant = null): User
    {
        // Create tenant if not provided
        if (!$tenant) {
            $tenant = Tenant::factory()->create([
                'name' => $attributes['tenant_name'] ?? 'Test Tenant',
                'status' => 'active',
                'is_active' => true,
            ]);
        }

        // Default attributes
        $defaults = [
            'tenant_id' => $tenant->id,
            'name' => $attributes['name'] ?? 'Test User',
            'email' => $attributes['email'] ?? 'test@example.com',
            'password' => Hash::make($attributes['password'] ?? 'password'),
            'email_verified_at' => $attributes['email_verified_at'] ?? now(),
            'is_active' => $attributes['is_active'] ?? true,
            'role' => $attributes['role'] ?? 'member',
        ];

        // Merge with provided attributes
        $userAttributes = array_merge($defaults, $attributes);
        
        // Remove tenant_name if it was provided (not a user attribute)
        unset($userAttributes['tenant_name']);

        return User::factory()->create($userAttributes);
    }

    /**
     * Create a test user with tenant and return both
     * 
     * @param array $userAttributes User attributes
     * @param array $tenantAttributes Tenant attributes
     * @return array ['user' => User, 'tenant' => Tenant]
     */
    public static function createTestUserWithTenant(
        array $userAttributes = [],
        array $tenantAttributes = []
    ): array {
        $tenant = Tenant::factory()->create($tenantAttributes);
        $user = self::createTestUser($userAttributes, $tenant);
        
        return [
            'user' => $user,
            'tenant' => $tenant,
        ];
    }

    /**
     * Get authenticated headers for API requests
     * 
     * @param TestCase $testCase The test case instance
     * @param string $email User email
     * @param string $password User password
     * @return array Headers array with Authorization token
     */
    public static function getAuthHeaders(
        TestCase $testCase,
        string $email,
        string $password
    ): array {
        $token = self::getAuthToken($testCase, $email, $password);
        
        if (!$token) {
            throw new \RuntimeException("Failed to get auth token for user {$email}");
        }

        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }
}

