<?php declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Tests\Helpers\AuthHelper;
use Tests\Helpers\TestDataSeeder;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Sanctum\Sanctum;

/**
 * @group auth
 * Password Change Test
 * 
 * Tests for authenticated password change functionality
 * 
 * Uses seedAuthDomain() for reproducible test data
 */
class PasswordChangeTest extends TestCase
{
    use RefreshDatabase, WithFaker, DomainTestIsolation;

    protected User $user;
    protected Tenant $tenant;
    protected string $currentPassword = 'CurrentPassword123!';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation (trait provides $domainSeed and $domainName)
        $this->setDomainSeed(12345);
        $this->setDomainName('auth');
        $this->setupDomainIsolation();

        // Clear cache to reset rate limiting
        Cache::flush();

        // Seed auth domain test data
        $data = TestDataSeeder::seedAuthDomain($this->getDomainSeed());
        $this->tenant = $data['tenant'];
        $this->storeTestData('tenant', $this->tenant);
        
        // Find member user from seed data (seedAuthDomain returns array_values, so find by email)
        $this->user = collect($data['users'])->firstWhere('email', 'member@auth-test.test');
        
        // Fallback to first user if member not found
        if (!$this->user) {
            $this->user = $data['users'][0];
        }
        
        // Update user password to known value for testing
        $this->user->update([
            'password' => Hash::make($this->currentPassword),
        ]);

        // Authenticate user using Sanctum
        Sanctum::actingAs(
            $this->user,
            [],
            'sanctum'
        );
    }

    /**
     * Test successful password change
     */
    public function test_user_can_change_password_successfully(): void
    {
        // Use password without sequential characters to pass password policy
        $newPassword = 'NewSecurePass@2024';

        $response = $this->postJson(
            '/api/auth/password/change', // Fixed: Added /api prefix
            [
                'current_password' => $this->currentPassword,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]
        );

        $response->assertStatus(200)
            ->assertJson(fn (AssertableJson $json) =>
                $json->where('success', true)
                    ->has('message') // Message exists (could be 'Success' or 'Password changed successfully.')
                    ->etc()
            );

        // Verify the password has been updated in the database
        $this->assertTrue(Hash::check($newPassword, $this->user->fresh()->password));
    }

    /**
     * Test authentication is required to change password
     */
    public function test_authentication_is_required_to_change_password(): void
    {
        // Don't authenticate for this test - create a new test case without authentication
        $this->refreshApplication();
        
        $response = $this->postJson(
            '/api/auth/password/change', // Fixed: Added /api prefix
            [
                'current_password' => 'SomePassword123!',
                'password' => 'NewSecurePass@2024',
                'password_confirmation' => 'NewSecurePass@2024',
            ]
        );

        $response->assertStatus(401);
        
        // Check for either Laravel's default message or custom API response
        $json = $response->json();
        $this->assertTrue(
            isset($json['message']) && (
                $json['message'] === 'Unauthenticated.' ||
                str_contains($json['message'] ?? '', 'Unauthenticated') ||
                str_contains($json['message'] ?? '', 'Authentication required')
            ) ||
            isset($json['error']['code']) && ($json['error']['code'] === 'AUTH_REQUIRED' || $json['error']['code'] === 'UNAUTHENTICATED')
        );
    }

    /**
     * Test current password validation
     */
    public function test_current_password_validation(): void
    {
        $response = $this->postJson(
            '/api/auth/password/change', // Fixed: Added /api prefix
            [
                'current_password' => 'wrong_password',
                'password' => 'NewSecurePass@2024',
                'password_confirmation' => 'NewSecurePass@2024',
            ]
        );

        $response->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('errors.current_password')
                    ->etc()
            );
    }

    /**
     * Test password confirmation validation
     */
    public function test_password_confirmation_validation(): void
    {
        $response = $this->postJson(
            '/api/auth/password/change', // Fixed: Added /api prefix
            [
                'current_password' => $this->currentPassword,
                'password' => 'NewSecurePass@2024',
                'password_confirmation' => 'DifferentPass@2024',
            ]
        );

        $response->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('errors.password')
                    ->etc()
            );
    }

    /**
     * Test password minimum length validation
     */
    public function test_password_minimum_length_validation(): void
    {
        $response = $this->postJson(
            '/api/auth/password/change', // Fixed: Added /api prefix
            [
                'current_password' => $this->currentPassword,
                'password' => 'Short!',
                'password_confirmation' => 'Short!',
            ]
        );

        $response->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('errors.password')
                    ->etc()
            );
    }

    /**
     * Test new password must be different from current password
     */
    public function test_new_password_must_be_different_from_current_password(): void
    {
        $response = $this->postJson(
            '/api/auth/password/change', // Fixed: Added /api prefix
            [
                'current_password' => $this->currentPassword,
                'password' => $this->currentPassword,
                'password_confirmation' => $this->currentPassword,
            ]
        );

        $response->assertStatus(422)
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('errors.password')
                    ->etc()
            );
    }

    /**
     * Test that all tokens are revoked after password change
     */
    public function test_all_tokens_revoked_after_password_change(): void
    {
        // Create multiple tokens for the user
        $token1 = $this->user->createToken('test-token-1')->plainTextToken;
        $token2 = $this->user->createToken('test-token-2')->plainTextToken;
        $token3 = $this->user->createToken('test-token-3')->plainTextToken;

        // Verify tokens exist
        $this->assertEquals(3, $this->user->tokens()->count());

        // Change password
        $newPassword = 'NewSecurePass@2024';
        $response = $this->postJson(
            '/api/auth/password/change',
            [
                'current_password' => $this->currentPassword,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]
        );

        $response->assertStatus(200);

        // Verify all tokens are deleted
        $this->assertEquals(0, $this->user->fresh()->tokens()->count());

        // Verify tokens are no longer valid by trying to use them
        // Note: After password change, tokens should be invalid
        // We can't easily test this without making API calls, but the count check above is sufficient
    }

    /**
     * Test that user must login again after password change
     */
    public function test_user_must_login_again_after_password_change(): void
    {
        // Create a token before password change
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Change password
        $newPassword = 'NewSecurePass@2024';
        $response = $this->postJson(
            '/api/auth/password/change',
            [
                'current_password' => $this->currentPassword,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]
        );

        $response->assertStatus(200);

        // Verify token is deleted
        $this->assertEquals(0, $this->user->fresh()->tokens()->count());

        // Note: The current request token is also revoked, so subsequent requests
        // with the same token should fail. However, since we're using Sanctum::actingAs(),
        // the test framework handles authentication differently.
        // In a real scenario, the user would need to login again.
    }
}
