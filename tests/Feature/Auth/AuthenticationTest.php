<?php declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\AuthenticationTrait;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Feature tests cho Authentication endpoints
 */
class AuthenticationTest extends TestCase
{
    use DatabaseTrait, AuthenticationTrait, WithFaker;
    
    /**
     * Test user login với valid credentials
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);
        
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'token',
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'tenant_id'
                        ]
                    ]
                ])
                ->assertJson([
                    'status' => 'success'
                ]);
        
        $this->assertNotEmpty($response->json('data.token'));
    }
    
    /**
     * Test user login với invalid credentials
     */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);
        
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ]);
        
        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Invalid credentials'
                ]);
    }
    
    /**
     * Test user logout
     */
    public function test_user_can_logout(): void
    {
        $user = $this->actingAsUser();
        
        $response = $this->postJson('/api/v1/auth/logout');
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'message' => 'Successfully logged out'
                    ]
                ]);
    }
    
    /**
     * Test get authenticated user profile
     */
    public function test_can_get_authenticated_user_profile(): void
    {
        $user = $this->actingAsUser([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
        
        $response = $this->getJson('/api/v1/auth/me');
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'id' => $user->id,
                        'name' => 'Test User',
                        'email' => 'test@example.com'
                    ]
                ]);
    }
    
    /**
     * Test access protected endpoint without token
     */
    public function test_cannot_access_protected_endpoint_without_token(): void
    {
        $response = $this->getJson('/api/v1/auth/me');
        
        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ]);
    }
}