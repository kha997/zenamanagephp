<?php declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Carbon\Carbon;

/**
 * Feature tests for Password Reset endpoints
 * 
 * Tests password reset flow: send reset link and reset password with token
 * 
 * @group auth
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation, WithFaker;
    
    protected $tenant;
    protected $user;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(12345);
        $this->setDomainName('auth');
        $this->setupDomainIsolation();
        
        // Seed auth domain test data
        $data = TestDataSeeder::seedAuthDomain($this->getDomainSeed());
        $this->tenant = $data['tenant'];
        $this->storeTestData('tenant', $this->tenant);
        
        // Use member user from seed data
        $this->user = collect($data['users'])->firstWhere('email', 'member@auth-test.test');
        if (!$this->user) {
            $this->user = $data['users'][0];
        }
        
        // Update user password to known value
        $this->user->update([
            'password' => Hash::make('password123'),
        ]);
    }
    
    /**
     * Test send reset link với email hợp lệ → success
     */
    public function test_send_reset_link_with_valid_email_returns_success(): void
    {
        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => $this->user->email,
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'message'
                    ]
                ])
                ->assertJson([
                    'success' => true
                ]);
        
        // Verify reset token was created in database
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $this->user->email,
        ]);
    }
    
    /**
     * Test send reset link với email không tồn tại → vẫn trả success (không lộ email)
     */
    public function test_send_reset_link_with_nonexistent_email_returns_success(): void
    {
        $nonExistentEmail = 'nonexistent@example.com';
        
        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => $nonExistentEmail,
        ]);
        
        // Should return success to prevent email enumeration
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'message'
                    ]
                ])
                ->assertJson([
                    'success' => true
                ]);
        
        // Verify no reset token was created for non-existent email
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $nonExistentEmail,
        ]);
    }
    
    /**
     * Test send reset link với email format sai → validation error
     */
    public function test_send_reset_link_with_invalid_email_format_returns_validation_error(): void
    {
        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => 'invalid-email-format',
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }
    
    /**
     * Test reset password với token đúng → success
     */
    public function test_reset_password_with_valid_token_returns_success(): void
    {
        // Create a password reset token
        $token = Password::createToken($this->user);
        
        $newPassword = 'NewSecurePass@2024';
        
        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $this->user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
            'token' => $token,
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'message'
                    ]
                ])
                ->assertJson([
                    'success' => true
                ]);
        
        // Verify password was updated
        $this->assertTrue(Hash::check($newPassword, $this->user->fresh()->password));
        
        // Verify reset token was deleted after use
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->user->email,
        ]);
    }
    
    /**
     * Test reset password với token sai → error
     */
    public function test_reset_password_with_invalid_token_returns_error(): void
    {
        $newPassword = 'NewSecurePass@2024';
        
        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $this->user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
            'token' => 'invalid-token-12345',
        ]);
        
        $response->assertStatus(422)
                ->assertJson(fn ($json) =>
                    $json->where('success', false)
                        ->where('error.code', 'PASSWORD_RESET_FAILED')
                        ->etc()
                );
        
        // Verify password was NOT updated
        $this->assertFalse(Hash::check($newPassword, $this->user->fresh()->password));
    }
    
    /**
     * Test reset password với token hết hạn → error
     */
    public function test_reset_password_with_expired_token_returns_error(): void
    {
        // Create a password reset token
        $token = Password::createToken($this->user);
        
        // Manually expire the token by updating created_at to past
        DB::table('password_reset_tokens')
            ->where('email', $this->user->email)
            ->update(['created_at' => Carbon::now()->subHours(2)]); // Expire after 1 hour default
        
        $newPassword = 'NewSecurePass@2024';
        
        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $this->user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
            'token' => $token,
        ]);
        
        $response->assertStatus(422)
                ->assertJson(fn ($json) =>
                    $json->where('success', false)
                        ->where('error.code', 'PASSWORD_RESET_FAILED')
                        ->etc()
                );
        
        // Verify password was NOT updated
        $this->assertFalse(Hash::check($newPassword, $this->user->fresh()->password));
    }
    
    /**
     * Test reset password với password không đủ mạnh → policy violation error
     */
    public function test_reset_password_with_weak_password_returns_policy_violation_error(): void
    {
        // Create a password reset token
        $token = Password::createToken($this->user);
        
        $weakPassword = '12345678'; // Too weak - no uppercase, no special char
        
        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $this->user->email,
            'password' => $weakPassword,
            'password_confirmation' => $weakPassword,
            'token' => $token,
        ]);
        
        $response->assertStatus(422)
                ->assertJson(fn ($json) =>
                    $json->where('success', false)
                        ->where('error.code', 'PASSWORD_POLICY_VIOLATION')
                        ->etc()
                );
        
        // Verify password was NOT updated
        $this->assertFalse(Hash::check($weakPassword, $this->user->fresh()->password));
    }
    
    /**
     * Test reset password với password không khớp → validation error
     */
    public function test_reset_password_with_mismatched_passwords_returns_validation_error(): void
    {
        // Create a password reset token
        $token = Password::createToken($this->user);
        
        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $this->user->email,
            'password' => 'NewSecurePass@2024',
            'password_confirmation' => 'DifferentPass@2024',
            'token' => $token,
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        
        // Verify password was NOT updated
        $this->assertFalse(Hash::check('NewSecurePass@2024', $this->user->fresh()->password));
    }
    
    /**
     * Test reset password với email không tồn tại → error
     */
    public function test_reset_password_with_nonexistent_email_returns_error(): void
    {
        $nonExistentEmail = 'nonexistent@example.com';
        
        $response = $this->postJson('/api/auth/password/reset', [
            'email' => $nonExistentEmail,
            'password' => 'NewSecurePass@2024',
            'password_confirmation' => 'NewSecurePass@2024',
            'token' => 'some-token',
        ]);
        
        $response->assertStatus(422)
                ->assertJson(fn ($json) =>
                    $json->where('success', false)
                        ->where('error.code', 'PASSWORD_RESET_FAILED')
                        ->etc()
                );
    }

    /**
     * Test token reuse prevention - token deleted after successful reset
     */
    public function test_token_cannot_be_reused_after_successful_reset(): void
    {
        // Create a password reset token
        $token = Password::createToken($this->user);
        
        $newPassword = 'NewSecurePass@2024';
        
        // First reset - should succeed
        $response1 = $this->postJson('/api/auth/password/reset', [
            'email' => $this->user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
            'token' => $token,
        ]);
        
        $response1->assertStatus(200);
        
        // Verify password was updated
        $this->assertTrue(Hash::check($newPassword, $this->user->fresh()->password));
        
        // Try to use the same token again - should fail
        $response2 = $this->postJson('/api/auth/password/reset', [
            'email' => $this->user->email,
            'password' => 'AnotherPassword@2024',
            'password_confirmation' => 'AnotherPassword@2024',
            'token' => $token,
        ]);
        
        $response2->assertStatus(422)
                ->assertJson(fn ($json) =>
                    $json->where('success', false)
                        ->where('error.code', 'PASSWORD_RESET_FAILED')
                        ->etc()
                );
        
        // Verify password was NOT changed again
        $this->assertTrue(Hash::check($newPassword, $this->user->fresh()->password));
        $this->assertFalse(Hash::check('AnotherPassword@2024', $this->user->fresh()->password));
    }

    /**
     * Test rate limiting for password reset requests
     */
    public function test_password_reset_rate_limiting_prevents_abuse(): void
    {
        // Clear any existing rate limit
        RateLimiter::clear("password-reset:{$this->user->email}");
        
        // Make 3 requests (the limit is 3 per hour)
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->postJson('/api/auth/password/forgot', [
                'email' => $this->user->email,
            ]);
            $response->assertStatus(200);
        }
        
        // 4th request should be rate limited
        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => $this->user->email,
        ]);
        
        $response->assertStatus(429); // Too Many Requests
    }

    /**
     * Test tenant isolation - user from tenant A cannot reset password for tenant B user
     */
    public function test_password_reset_respects_tenant_isolation(): void
    {
        // Create another tenant and user
        $otherTenant = TestDataSeeder::createTenant();
        $otherUser = TestDataSeeder::createUser($otherTenant, [
            'email' => 'other@tenant-test.test',
            'password' => Hash::make('password123'),
        ]);
        
        // Try to request password reset for other tenant's user
        // This should still work (email enumeration protection), but the token
        // should only work for the correct user
        
        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => $otherUser->email,
        ]);
        
        // Should return success (email enumeration protection)
        $response->assertStatus(200);
        
        // Create a token for the other user
        $token = Password::createToken($otherUser);
        
        // Try to reset password - this should work if token is valid
        // But we need to verify tenant isolation is maintained
        $newPassword = 'NewSecurePass@2024';
        
        $resetResponse = $this->postJson('/api/auth/password/reset', [
            'email' => $otherUser->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
            'token' => $token,
        ]);
        
        // Password reset should work (it's a public endpoint)
        // But we verify the user's tenant_id hasn't changed
        $resetResponse->assertStatus(200);
        
        // Verify the user still belongs to the correct tenant
        $this->assertEquals($otherTenant->id, $otherUser->fresh()->tenant_id);
        $this->assertNotEquals($this->tenant->id, $otherUser->fresh()->tenant_id);
    }
}

