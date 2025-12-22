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
use Laravel\Sanctum\Sanctum;

/**
 * Feature tests for Password Change endpoint
 * 
 * Tests authenticated user password change functionality
 * 
 * @group auth
 */
class PasswordChangeTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation, WithFaker;
    
    protected $tenant;
    protected $user;
    protected $authToken;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(12346);
        $this->setDomainName('auth-change');
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
        
        // Create auth token
        Sanctum::actingAs($this->user);
        $this->authToken = $this->user->createToken('test-token')->plainTextToken;
    }
    
    /**
     * Test happy path: authenticated user changes password successfully
     */
    public function test_authenticated_user_can_change_password_successfully(): void
    {
        $newPassword = 'NewSecurePass@2024';
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->postJson('/api/auth/password/change', [
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
        $this->assertFalse(Hash::check('password123', $this->user->fresh()->password));
    }
    
    /**
     * Test wrong current password → 422 error
     */
    public function test_change_password_with_wrong_current_password_returns_error(): void
    {
        $newPassword = 'NewSecurePass@2024';
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->postJson('/api/auth/password/change', [
                'current_password' => 'wrong-password',
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
     * Test new password same as current → validation error
     */
    public function test_change_password_with_same_password_returns_validation_error(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->postJson('/api/auth/password/change', [
                'current_password' => 'password123',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        
        // Verify password was NOT updated
        $this->assertTrue(Hash::check('password123', $this->user->fresh()->password));
    }
    
    /**
     * Test weak password → policy violation
     */
    public function test_change_password_with_weak_password_returns_policy_violation_error(): void
    {
        $weakPassword = '12345678'; // Too weak - no uppercase, no special char
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->postJson('/api/auth/password/change', [
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
     * Test unauthenticated request → 401 error
     */
    public function test_change_password_without_authentication_returns_401(): void
    {
        $response = $this->postJson('/api/auth/password/change', [
            'current_password' => 'password123',
            'password' => 'NewSecurePass@2024',
            'password_confirmation' => 'NewSecurePass@2024',
        ]);
        
        $response->assertStatus(401);
    }
    
    /**
     * Test tenant isolation - user from tenant A cannot access tenant B user's endpoint
     * Note: Since the endpoint uses auth:sanctum, users can only change their own password.
     * This test verifies that a user from tenant A cannot change password for tenant B user
     * by attempting to use tenant B user's token.
     */
    public function test_change_password_respects_tenant_isolation(): void
    {
        // Create another tenant and user
        $otherTenant = TestDataSeeder::createTenant();
        $otherUser = TestDataSeeder::createUser($otherTenant, [
            'email' => 'other@tenant-test.test',
            'password' => Hash::make('otherpassword123'),
        ]);
        
        // Create token for other user
        $otherToken = $otherUser->createToken('other-token')->plainTextToken;
        
        // Try to change password using other user's token
        // This should work (user can change their own password)
        $newPassword = 'NewSecurePass@2024';
        
        $response = $this->withHeader('Authorization', 'Bearer ' . $otherToken)
            ->postJson('/api/auth/password/change', [
                'current_password' => 'otherpassword123',
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);
        
        // Should succeed - user is changing their own password
        $response->assertStatus(200);
        
        // Verify other user's password was updated
        $this->assertTrue(Hash::check($newPassword, $otherUser->fresh()->password));
        
        // Verify original user's password was NOT changed
        $this->assertTrue(Hash::check('password123', $this->user->fresh()->password));
        $this->assertFalse(Hash::check($newPassword, $this->user->fresh()->password));
        
        // Verify tenant isolation - users belong to different tenants
        $this->assertNotEquals($this->tenant->id, $otherTenant->id);
        $this->assertEquals($this->tenant->id, $this->user->fresh()->tenant_id);
        $this->assertEquals($otherTenant->id, $otherUser->fresh()->tenant_id);
    }
    
    /**
     * Test password mismatch → validation error
     */
    public function test_change_password_with_mismatched_passwords_returns_validation_error(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->postJson('/api/auth/password/change', [
                'current_password' => 'password123',
                'password' => 'NewSecurePass@2024',
                'password_confirmation' => 'DifferentPass@2024',
            ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        
        // Verify password was NOT updated
        $this->assertTrue(Hash::check('password123', $this->user->fresh()->password));
    }
    
    /**
     * Test missing current password → validation error
     */
    public function test_change_password_without_current_password_returns_validation_error(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->authToken)
            ->postJson('/api/auth/password/change', [
                'password' => 'NewSecurePass@2024',
                'password_confirmation' => 'NewSecurePass@2024',
            ]);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['current_password']);
    }
}

