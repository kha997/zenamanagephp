<?php declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

/**
 * Feature tests for Change Password endpoint
 * 
 * Tests password change flow for authenticated users
 * 
 * @group auth
 */
class ChangePasswordTest extends TestCase
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
     * Test change password với valid credentials → success
     */
    public function test_user_can_change_password_with_valid_current_password(): void
    {
        Sanctum::actingAs($this->user);
        
        $newPassword = 'NewSecurePass@2024';
        
        $response = $this->postJson('/api/auth/password/change', [
            'current_password' => 'password123',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
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
        
        // Verify old password no longer works
        $this->assertFalse(Hash::check('password123', $this->user->fresh()->password));
    }
    
    /**
     * Test change password với wrong current password → error
     */
    public function test_user_cannot_change_password_with_wrong_current_password(): void
    {
        Sanctum::actingAs($this->user);
        
        $newPassword = 'NewSecurePass@2024';
        
        $response = $this->postJson('/api/auth/password/change', [
            'current_password' => 'wrongpassword',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['current_password']);
        
        // Verify password was NOT updated
        $this->assertFalse(Hash::check($newPassword, $this->user->fresh()->password));
        $this->assertTrue(Hash::check('password123', $this->user->fresh()->password));
    }
    
    /**
     * Test change password với password không đủ mạnh → policy violation error
     */
    public function test_user_cannot_change_password_with_weak_password(): void
    {
        Sanctum::actingAs($this->user);
        
        $weakPassword = '12345678'; // Too weak - no uppercase, no special char
        
        $response = $this->postJson('/api/auth/password/change', [
            'current_password' => 'password123',
            'password' => $weakPassword,
            'password_confirmation' => $weakPassword,
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
     * Test change password với password không khớp → validation error
     */
    public function test_user_cannot_change_password_with_mismatched_passwords(): void
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/auth/password/change', [
            'current_password' => 'password123',
            'password' => 'NewSecurePass@2024',
            'password_confirmation' => 'DifferentPass@2024',
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        
        // Verify password was NOT updated
        $this->assertFalse(Hash::check('NewSecurePass@2024', $this->user->fresh()->password));
    }
    
    /**
     * Test change password với new password giống current password → error
     */
    public function test_user_cannot_change_password_to_same_password(): void
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/auth/password/change', [
            'current_password' => 'password123',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        
        // Verify password was NOT updated (still the same)
        $this->assertTrue(Hash::check('password123', $this->user->fresh()->password));
    }
    
    /**
     * Test change password revokes all tokens
     */
    public function test_change_password_revokes_all_tokens(): void
    {
        Sanctum::actingAs($this->user);
        
        // Create some tokens before password change
        $token1 = $this->user->createToken('test-token-1')->plainTextToken;
        $token2 = $this->user->createToken('test-token-2')->plainTextToken;
        
        $this->assertCount(2, $this->user->tokens);
        
        $newPassword = 'NewSecurePass@2024';
        
        $response = $this->postJson('/api/auth/password/change', [
            'current_password' => 'password123',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);
        
        $response->assertStatus(200);
        
        // Verify all tokens were revoked
        $this->assertCount(0, $this->user->fresh()->tokens);
    }
    
    /**
     * Test change password requires authentication
     */
    public function test_change_password_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/password/change', [
            'current_password' => 'password123',
            'password' => 'NewSecurePass@2024',
            'password_confirmation' => 'NewSecurePass@2024',
        ]);
        
        $response->assertStatus(401);
    }
    
    /**
     * Test change password với missing fields → validation error
     */
    public function test_change_password_with_missing_fields_returns_validation_error(): void
    {
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson('/api/auth/password/change', []);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'current_password',
                    'password',
                    'password_confirmation',
                ]);
    }
    
    /**
     * Test tenant isolation - user can only change their own password
     */
    public function test_change_password_respects_tenant_isolation(): void
    {
        // Create another tenant and user
        $otherTenant = TestDataSeeder::createTenant();
        $otherUser = TestDataSeeder::createUser($otherTenant, [
            'email' => 'other@tenant-test.test',
            'password' => Hash::make('otherpassword123'),
        ]);
        
        // Authenticate as first user
        Sanctum::actingAs($this->user);
        
        $newPassword = 'NewSecurePass@2024';
        
        // Try to change password - should work for authenticated user
        $response = $this->postJson('/api/auth/password/change', [
            'current_password' => 'password123',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);
        
        $response->assertStatus(200);
        
        // Verify only the authenticated user's password was changed
        $this->assertTrue(Hash::check($newPassword, $this->user->fresh()->password));
        $this->assertTrue(Hash::check('otherpassword123', $otherUser->fresh()->password));
        
        // Verify tenant_id hasn't changed
        $this->assertEquals($this->tenant->id, $this->user->fresh()->tenant_id);
        $this->assertEquals($otherTenant->id, $otherUser->fresh()->tenant_id);
    }
}

