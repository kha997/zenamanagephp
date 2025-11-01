<?php declare(strict_types=1);

namespace Tests\Feature\Buttons;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Button Authentication Test
 * 
 * Tests authentication flows for all interactive elements
 */
class ButtonAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'status' => 'active'
        ]);

        // Create users for each role
        $roles = ['super_admin', 'admin', 'pm', 'designer', 'engineer', 'guest'];
        
        foreach ($roles as $role) {
            $this->users[$role] = User::create([
                'name' => ucfirst($role) . ' User',
                'email' => $role . '@test-' . uniqid() . '.com',
                'password' => Hash::make('password'),
                'tenant_id' => $this->tenant->id
            ]);
        }
    }

    /**
     * Test login button functionality
     */
    public function test_login_button_works(): void
    {
        $response = $this->post('/login', [
            'email' => $this->users['pm']->email,
            'password' => 'password'
        ]);

        $response->assertRedirect('/dashboard');
    }

    /**
     * Test logout button functionality
     */
    public function test_logout_button_works(): void
    {
        $this->actingAs($this->users['pm']);
        
        $response = $this->post('/logout');
        
        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /**
     * Test authentication required for protected routes
     */
    public function test_protected_routes_require_authentication(): void
    {
        $protectedRoutes = [
            '/projects',
            '/tasks',
            '/documents',
            '/team',
            '/admin'
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login');
        }
    }

    /**
     * Test API authentication
     */
    public function test_api_authentication(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->users['pm']->email,
            'password' => 'password'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'user'
                ]);
    }

    /**
     * Test session management
     */
    public function test_session_management(): void
    {
        $this->actingAs($this->users['pm']);
        
        // Test session persistence
        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        
        // Test session timeout (simulate)
        $this->app['session']->flush();
        
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    /**
     * Test password reset functionality
     */
    public function test_password_reset_button(): void
    {
        $response = $this->post('/api/auth/password/reset', [
            'email' => $this->users['pm']->email
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test invalid credentials handling
     */
    public function test_invalid_credentials_handling(): void
    {
        $response = $this->post('/login', [
            'email' => $this->users['pm']->email,
            'password' => 'wrong_password'
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /**
     * Test rate limiting on login attempts
     */
    public function test_login_rate_limiting(): void
    {
        // Make multiple failed login attempts
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/login', [
                'email' => $this->users['pm']->email,
                'password' => 'wrong_password'
            ]);
            
            if ($i < 5) {
                $response->assertSessionHasErrors(['email']);
            } else {
                $response->assertStatus(429); // Too Many Requests
            }
        }
    }
}
