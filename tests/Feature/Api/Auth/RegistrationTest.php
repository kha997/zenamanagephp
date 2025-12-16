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
use Illuminate\Support\Facades\Config;

/**
 * Feature tests for Registration endpoints
 * 
 * Tests user registration with tenant creation
 * 
 * @group auth
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation, WithFaker;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(12345);
        $this->setDomainName('auth');
        $this->setupDomainIsolation();
        
        // Enable public signup by default for tests
        Config::set('features.auth.public_signup_enabled', true);
    }
    
    /**
     * Test register thành công với data hợp lệ → success, tenant created
     */
    public function test_register_with_valid_data_creates_user_and_tenant(): void
    {
        $registrationData = [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'SecurePass@2024',
            'password_confirmation' => 'SecurePass@2024',
            'tenant_name' => 'Test Company',
            'phone' => '+84123456789',
            'terms' => true,
        ];
        
        $response = $this->postJson('/api/auth/register', $registrationData);
        
        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'message',
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'tenant_id',
                        ],
                        'tenant' => [
                            'id',
                            'name',
                            'slug',
                        ],
                        'verification_sent',
                    ]
                ])
                ->assertJson([
                    'success' => true
                ]);
        
        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => $registrationData['email'],
            'name' => $registrationData['name'],
        ]);
        
        // Verify tenant was created
        $user = User::where('email', $registrationData['email'])->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->tenant_id);
        
        $this->assertDatabaseHas('tenants', [
            'id' => $user->tenant_id,
            'name' => $registrationData['tenant_name'],
        ]);
        
        // Verify password was hashed
        $this->assertTrue(Hash::check($registrationData['password'], $user->password));
    }
    
    /**
     * Test register với email đã tồn tại → validation error
     */
    public function test_register_with_existing_email_returns_validation_error(): void
    {
        // Create existing user
        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
        ]);
        
        $registrationData = [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'SecurePass@2024',
            'password_confirmation' => 'SecurePass@2024',
            'tenant_name' => 'Test Company',
            'terms' => true,
        ];
        
        $response = $this->postJson('/api/auth/register', $registrationData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
    }
    
    /**
     * Test register với password yếu → policy violation
     */
    public function test_register_with_weak_password_returns_validation_error(): void
    {
        $registrationData = [
            'name' => 'Test User',
            'email' => 'weakpass@example.com',
            'password' => '12345678', // Too weak - no uppercase, no special char
            'password_confirmation' => '12345678',
            'tenant_name' => 'Test Company',
            'terms' => true,
        ];
        
        $response = $this->postJson('/api/auth/register', $registrationData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
    }
    
    /**
     * Test register khi feature flag = false → 403
     */
    public function test_register_when_feature_flag_disabled_returns_403(): void
    {
        // Disable public signup
        Config::set('features.auth.public_signup_enabled', false);
        
        $registrationData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'SecurePass@2024',
            'password_confirmation' => 'SecurePass@2024',
            'tenant_name' => 'Test Company',
            'terms' => true,
        ];
        
        $response = $this->postJson('/api/auth/register', $registrationData);
        
        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'REGISTRATION_DISABLED'
                    ]
                ]);
        
        // Verify no user was created
        $this->assertDatabaseMissing('users', [
            'email' => $registrationData['email'],
        ]);
    }
    
    /**
     * Test register với missing required fields → validation error
     */
    public function test_register_with_missing_required_fields_returns_validation_error(): void
    {
        $response = $this->postJson('/api/auth/register', []);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'email', 'password', 'tenant_name', 'terms']);
    }
    
    /**
     * Test register với password không khớp → validation error
     */
    public function test_register_with_mismatched_passwords_returns_validation_error(): void
    {
        $registrationData = [
            'name' => 'Test User',
            'email' => 'mismatch@example.com',
            'password' => 'SecurePass@2024',
            'password_confirmation' => 'DifferentPass@2024',
            'tenant_name' => 'Test Company',
            'terms' => true,
        ];
        
        $response = $this->postJson('/api/auth/register', $registrationData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
    }
    
    /**
     * Test register với terms không được chấp nhận → validation error
     */
    public function test_register_without_accepting_terms_returns_validation_error(): void
    {
        $registrationData = [
            'name' => 'Test User',
            'email' => 'noterms@example.com',
            'password' => 'SecurePass@2024',
            'password_confirmation' => 'SecurePass@2024',
            'tenant_name' => 'Test Company',
            'terms' => false,
        ];
        
        $response = $this->postJson('/api/auth/register', $registrationData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['terms']);
    }
    
    /**
     * Test register với tenant_name quá ngắn → validation error
     */
    public function test_register_with_short_tenant_name_returns_validation_error(): void
    {
        $registrationData = [
            'name' => 'Test User',
            'email' => 'shorttenant@example.com',
            'password' => 'SecurePass@2024',
            'password_confirmation' => 'SecurePass@2024',
            'tenant_name' => 'A', // Too short (min 2)
            'terms' => true,
        ];
        
        $response = $this->postJson('/api/auth/register', $registrationData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['tenant_name']);
    }
    
    /**
     * Test register với phone không hợp lệ → validation error
     */
    public function test_register_with_invalid_phone_returns_validation_error(): void
    {
        $registrationData = [
            'name' => 'Test User',
            'email' => 'invalidphone@example.com',
            'password' => 'SecurePass@2024',
            'password_confirmation' => 'SecurePass@2024',
            'tenant_name' => 'Test Company',
            'phone' => 'invalid-phone-format',
            'terms' => true,
        ];
        
        $response = $this->postJson('/api/auth/register', $registrationData);
        
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['phone']);
    }
    
    /**
     * Test register với phone hợp lệ → success (phone is optional)
     */
    public function test_register_with_valid_phone_returns_success(): void
    {
        $registrationData = [
            'name' => 'Test User',
            'email' => 'validphone@example.com',
            'password' => 'SecurePass@2024',
            'password_confirmation' => 'SecurePass@2024',
            'tenant_name' => 'Test Company',
            'phone' => '+84123456789',
            'terms' => true,
        ];
        
        $response = $this->postJson('/api/auth/register', $registrationData);
        
        $response->assertStatus(201)
                ->assertJson([
                    'success' => true
                ]);
    }
    
    /**
     * Test register không có phone → success (phone is optional)
     */
    public function test_register_without_phone_returns_success(): void
    {
        $registrationData = [
            'name' => 'Test User',
            'email' => 'nophone@example.com',
            'password' => 'SecurePass@2024',
            'password_confirmation' => 'SecurePass@2024',
            'tenant_name' => 'Test Company',
            'terms' => true,
        ];
        
        $response = $this->postJson('/api/auth/register', $registrationData);
        
        $response->assertStatus(201)
                ->assertJson([
                    'success' => true
                ]);
    }
}

