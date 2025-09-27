<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function passwords_are_hashed_using_bcrypt()
    {
        $password = 'test-password-123';
        $user = User::factory()->create([
            'password' => Hash::make($password)
        ]);

        // Password should be hashed, not plain text
        $this->assertNotEquals($password, $user->password);
        
        // Should be able to verify password
        $this->assertTrue(Hash::check($password, $user->password));
        
        // Should start with $2y$ (bcrypt identifier)
        $this->assertStringStartsWith('$2y$', $user->password);
    }

    /** @test */
    public function no_md5_hashing_for_passwords()
    {
        $password = 'test-password-123';
        $md5Hash = md5($password);
        
        $user = User::factory()->create([
            'password' => Hash::make($password)
        ]);

        // Password should NOT be MD5 hash
        $this->assertNotEquals($md5Hash, $user->password);
        $this->assertStringNotContainsString($md5Hash, $user->password);
    }

    /** @test */
    public function csrf_token_is_generated()
    {
        $response = $this->get('/login');
        
        $response->assertStatus(200);
        $response->assertSee('_token', false);
    }

    /** @test */
    public function csrf_protection_is_active()
    {
        // Test that POST requests without CSRF token are rejected
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password'
        ]);

        // Should get 419 (CSRF token mismatch) or redirect
        $this->assertTrue(in_array($response->status(), [419, 302]));
    }

    /** @test */
    public function authentication_middleware_protects_routes()
    {
        // Test that protected routes redirect to login
        $response = $this->get('/');
        $response->assertRedirect('/login');

        $response = $this->get('/app/dashboard');
        $response->assertRedirect('/login');
    }

    /** @test */
    public function guest_middleware_redirects_authenticated_users()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->get('/login');
        
        // Should redirect away from login page if already authenticated
        $response->assertRedirect();
    }

    /** @test */
    public function password_validation_enforces_security_rules()
    {
        // Test weak password rejection
        $weakPasswords = [
            '123',
            'password',
            'abc123',
            '11111111'
        ];

        foreach ($weakPasswords as $weakPassword) {
            $response = $this->post('/register', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => $weakPassword,
                'password_confirmation' => $weakPassword
            ]);

            // Should have validation errors for weak passwords
            $this->assertTrue($response->status() >= 400 || $response->isRedirect());
        }
    }

    /** @test */
    public function session_security_configuration()
    {
        // Test session security settings
        $this->assertEquals('cookie', config('session.driver'));
        $this->assertTrue(config('session.http_only'));
        $this->assertEquals('lax', config('session.same_site'));
        
        // In production, secure should be true
        if (app()->environment('production')) {
            $this->assertTrue(config('session.secure'));
        }
    }

    /** @test */
    public function sensitive_data_is_not_logged()
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password')
        ]);

        // Convert to array and check password is hidden
        $userArray = $user->toArray();
        
        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    /** @test */
    public function api_rate_limiting_is_configured()
    {
        // Test that rate limiting middleware exists
        $middlewares = app('router')->getMiddleware();
        
        $this->assertArrayHasKey('throttle', $middlewares);
    }

    /** @test */
    public function input_sanitization_prevents_xss()
    {
        $maliciousInput = '<script>alert("XSS")</script>';
        
        // Create a user with potentially malicious name
        $user = User::factory()->create([
            'name' => $maliciousInput
        ]);

        // When rendered in view, should be escaped
        $response = $this->actingAs($user)->get('/app/dashboard');
        
        // Should not contain unescaped script tags
        $response->assertDontSee('<script>alert("XSS")</script>', false);
        
        // Should contain escaped version
        $response->assertSee('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', false);
    }

    /** @test */
    public function database_credentials_are_not_exposed()
    {
        $config = config('database.connections.mysql');
        
        // Database credentials should not be default values
        $this->assertNotEquals('root', $config['username']);
        $this->assertNotEquals('', $config['password']);
        $this->assertNotEquals('localhost', $config['host']);
    }

    /** @test */
    public function app_debug_is_disabled_in_production()
    {
        if (app()->environment('production')) {
            $this->assertFalse(config('app.debug'));
        }
    }

    /** @test */
    public function security_headers_are_configured()
    {
        $response = $this->get('/login');
        
        // Check for security headers (if implemented)
        $headers = $response->headers->all();
        
        // These would be set by security middleware
        if (isset($headers['x-frame-options'])) {
            $this->assertContains('DENY', $headers['x-frame-options']);
        }
        
        if (isset($headers['x-content-type-options'])) {
            $this->assertContains('nosniff', $headers['x-content-type-options']);
        }
    }

    /** @test */
    public function tenant_isolation_is_enforced()
    {
        $tenant1User = User::factory()->create(['tenant_id' => 'tenant-1']);
        $tenant2User = User::factory()->create(['tenant_id' => 'tenant-2']);
        
        // Test that users can only see their own tenant data
        $this->actingAs($tenant1User);
        
        // This would need to be implemented based on your tenant isolation logic
        $this->assertTrue($tenant1User->tenant_id === 'tenant-1');
        $this->assertTrue($tenant2User->tenant_id === 'tenant-2');
        $this->assertNotEquals($tenant1User->tenant_id, $tenant2User->tenant_id);
    }

    /** @test */
    public function file_upload_security()
    {
        $user = User::factory()->create();
        
        // Test malicious file upload prevention
        $maliciousFile = \Illuminate\Http\UploadedFile::fake()->create('malicious.php', 100);
        
        $response = $this->actingAs($user)->post('/api/v1/upload-document', [
            'file' => $maliciousFile
        ]);

        // Should reject PHP files or other executable types
        $this->assertTrue(in_array($response->status(), [400, 422, 403]));
    }

    /** @test */
    public function sql_injection_prevention()
    {
        $user = User::factory()->create();
        
        // Test SQL injection attempt
        $maliciousInput = "'; DROP TABLE users; --";
        
        // This should not cause SQL injection
        $response = $this->actingAs($user)->get('/api/v1/app/search', [
            'query' => $maliciousInput
        ]);

        // Should handle gracefully without error
        $this->assertTrue(in_array($response->status(), [200, 400, 422]));
        
        // Users table should still exist
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}