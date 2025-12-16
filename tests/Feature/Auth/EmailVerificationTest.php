<?php declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Tests\Helpers\AuthHelper;

/**
 * @group auth
 * Email Verification Test
 * 
 * Tests for email verification resend functionality
 * 
 * Uses seedAuthDomain() for reproducible test data where applicable.
 * Some tests create users with specific verification states.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation, WithFaker;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // Clear cache to reset rate limiting
        Cache::flush();
        
        // Setup domain isolation
        $this->setDomainSeed(12345);
        $this->setDomainName('auth');
        $this->setupDomainIsolation();
        
        // Seed auth domain test data
        $data = TestDataSeeder::seedAuthDomain($this->getDomainSeed());
        $this->tenant = $data['tenant'];
        $this->storeTestData('tenant', $this->tenant);
        
        // Use member user from seed data (for tests that need existing user)
        $this->user = collect($data['users'])->firstWhere('email', 'member@auth-test.test');
        if (!$this->user) {
            $this->user = $data['users'][0];
        }
        
        // Update user password to known value
        $this->user->update([
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * Test resend verification email for unverified user (unauthenticated)
     */
    public function test_resend_verification_email_for_unverified_user_unauthenticated()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->unverified()->create([
            'tenant_id' => $tenant->id,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/email/resend', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'status',
                'message',
                'data' => [
                    'message',
                ],
                'timestamp',
            ])
            ->assertJson([
                'success' => true,
                'status' => 'success',
            ]);

        // Verify email was sent
        Mail::assertSent(\App\Mail\EmailVerificationMail::class);
    }

    /**
     * Test resend verification email for unverified user (authenticated)
     */
    public function test_resend_verification_email_for_unverified_user_authenticated()
    {
        // Create unverified user for this test
        $user = User::factory()->unverified()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Get auth headers for user
        $authHeaders = AuthHelper::getAuthHeaders($this, $user->email, 'password');
        
        $response = $this->withHeaders($authHeaders)
            ->postJson('/api/auth/email/resend');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify email was sent
        Mail::assertSent(\App\Mail\EmailVerificationMail::class);
    }

    /**
     * Test resend verification fails for already verified user
     */
    public function test_resend_verification_fails_for_already_verified_user()
    {
        // Create verified user for this test using seed tenant
        $user = User::factory()->verified()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/email/resend', [
            'email' => $user->email,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'status',
                'message',
            ])
            ->assertJson([
                'status' => 'error',
            ])
            ->assertJsonPath('message', 'Email is already verified.');

        // Verify email was not sent
        Mail::assertNothingSent();
    }

    /**
     * Test resend verification fails for non-existent email
     */
    public function test_resend_verification_fails_for_non_existent_email()
    {
        $response = $this->postJson('/api/auth/email/resend', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(404)
            ->assertJsonStructure([
                'status',
                'message',
            ])
            ->assertJson([
                'status' => 'error',
                'message' => 'No account found with this email address.',
            ]);
    }

    /**
     * Test resend verification requires email when unauthenticated
     */
    public function test_resend_verification_requires_email_when_unauthenticated()
    {
        $response = $this->postJson('/api/auth/email/resend', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test resend verification respects rate limiting
     */
    public function test_resend_verification_respects_rate_limiting()
    {
        // Create unverified user for this test
        $user = User::factory()->unverified()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Make 4 requests (limit is 3 per hour)
        for ($i = 0; $i < 4; $i++) {
            $response = $this->postJson('/api/auth/email/resend', [
                'email' => $user->email,
            ]);
        }

        // Last request should be rate limited
        $response->assertStatus(429);
    }

    /**
     * Test resend verification for authenticated user uses their email
     */
    public function test_resend_verification_for_authenticated_user_uses_their_email()
    {
        // Create unverified user for this test
        $user = User::factory()->unverified()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Get auth headers for user
        $authHeaders = AuthHelper::getAuthHeaders($this, $user->email, 'password');
        
        // Don't provide email in request - should use authenticated user's email
        $response = $this->withHeaders($authHeaders)
            ->postJson('/api/auth/email/resend');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify email was sent to authenticated user
        Mail::assertSent(\App\Mail\EmailVerificationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /**
     * Test resend verification email validation
     */
    public function test_resend_verification_email_validation()
    {
        // Invalid email format
        $response = $this->postJson('/api/auth/email/resend', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Email too long
        // Clear cache to avoid rate limiting from previous requests
        Cache::flush();
        
        $response = $this->postJson('/api/auth/email/resend', [
            'email' => str_repeat('a', 250) . '@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}

