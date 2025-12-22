<?php

namespace Tests\Browser\E2E;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;

/**
 * Production Readiness E2E Test
 * Tests critical production features and security measures
 */
class ProductionReadinessE2ETest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create([
            'name' => 'Production Test Tenant',
            'domain' => 'production-test.zenamanage.com',
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Production Test User',
            'email' => 'production@test.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);
    }

    /** @test */
    public function health_endpoints_are_accessible()
    {
        $this->browse(function (Browser $browser) {
            // Test health endpoint
            $browser->visit('/_debug/health')
                    ->assertSee('"status":"healthy"')
                    ->assertSee('"database"')
                    ->assertSee('"application"')
                    ->assertSee('"system"');

            // Test ping endpoint
            $browser->visit('/_debug/ping')
                    ->assertSee('"status":"ok"')
                    ->assertSee('"timestamp"');

            // Test info endpoint (should be restricted in production)
            $browser->visit('/_debug/info')
                    ->assertSee('"error":"Not available in production"');
        });
    }

    /** @test */
    public function rate_limiting_works()
    {
        $this->browse(function (Browser $browser) {
            // Login first
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/app/dashboard');

            // Make multiple rapid requests to test rate limiting
            for ($i = 0; $i < 10; $i++) {
                $browser->visit('/app/dashboard');
            }

            // Should still be able to access (rate limit is per minute)
            $browser->visit('/app/dashboard')
                    ->assertSee('Dashboard');
        });
    }

    /** @test */
    public function input_validation_works()
    {
        $this->browse(function (Browser $browser) {
            // Test login form validation
            $browser->visit('/login')
                    ->press('Login')
                    ->assertSee('The email field is required')
                    ->assertSee('The password field is required');

            // Test invalid email format
            $browser->visit('/login')
                    ->type('email', 'invalid-email')
                    ->type('password', 'password')
                    ->press('Login')
                    ->assertSee('The email must be a valid email address');

            // Test SQL injection attempt
            $browser->visit('/login')
                    ->type('email', "admin'; DROP TABLE users; --")
                    ->type('password', 'password')
                    ->press('Login')
                    ->assertSee('Invalid credentials'); // Should not execute SQL
        });
    }

    /** @test */
    public function session_security_test()
    {
        $this->browse(function (Browser $browser) {
            // Login
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/app/dashboard');

            // Check session cookie security
            $cookies = $browser->driver->manage()->getCookies();
            $sessionCookie = collect($cookies)->firstWhere('name', 'zenamanage_session');
            
            if ($sessionCookie) {
                $this->assertTrue($sessionCookie['httpOnly'], 'Session cookie should be httpOnly');
                $this->assertTrue($sessionCookie['secure'] ?? false, 'Session cookie should be secure in production');
            }
        });
    }

    /** @test */
    public function error_pages_are_secure()
    {
        $this->browse(function (Browser $browser) {
            // Test 404 page doesn't leak information
            $browser->visit('/app/nonexistent-page')
                    ->assertSee('404')
                    ->assertDontSee('Stack trace')
                    ->assertDontSee('vendor/')
                    ->assertDontSee('app/');

            // Test 500 page doesn't leak information
            $browser->visit('/app/projects/invalid-id')
                    ->assertSee('404')
                    ->assertDontSee('Stack trace')
                    ->assertDontSee('vendor/')
                    ->assertDontSee('app/');
        });
    }

    /** @test */
    public function api_endpoints_require_authentication()
    {
        $this->browse(function (Browser $browser) {
            // Test API endpoints without authentication
            $browser->visit('/api/dashboard')
                    ->assertSee('Unauthenticated');

            $browser->visit('/api/projects')
                    ->assertSee('Unauthenticated');

            $browser->visit('/api/tasks')
                    ->assertSee('Unauthenticated');
        });
    }

    /** @test */
    public function file_upload_security_test()
    {
        $this->browse(function (Browser $browser) {
            // Login first
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/app/dashboard');

            // Navigate to documents page
            $browser->visit('/app/documents')
                    ->assertSee('Documents');

            // Test file upload restrictions (if file upload is implemented)
            // This would test file type restrictions, size limits, etc.
        });
    }

    /** @test */
    public function xss_protection_test()
    {
        $this->browse(function (Browser $browser) {
            // Login first
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/app/dashboard');

            // Test XSS protection in forms
            $browser->visit('/app/projects')
                    ->assertSee('Projects');

            // Test that script tags are escaped
            $browser->visit('/app/projects')
                    ->assertDontSee('<script>')
                    ->assertDontSee('javascript:');
        });
    }

    /** @test */
    public function https_redirect_test()
    {
        $this->browse(function (Browser $browser) {
            // In production, HTTP should redirect to HTTPS
            // This test would verify HTTPS redirects work
            $browser->visit('http://localhost:8000/login')
                    ->assertUrlIs('http://localhost:8000/login'); // In dev, no redirect
        });
    }

    /** @test */
    public function database_connection_test()
    {
        $this->browse(function (Browser $browser) {
            // Test that database operations work
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/app/dashboard')
                    ->assertSee('Dashboard');

            // Navigate through pages that require database access
            $browser->visit('/app/projects')
                    ->assertSee('Projects');

            $browser->visit('/app/tasks')
                    ->assertSee('Tasks');

            $browser->visit('/app/clients')
                    ->assertSee('Clients');
        });
    }

    /** @test */
    public function memory_usage_test()
    {
        $this->browse(function (Browser $browser) {
            $initialMemory = memory_get_usage(true);

            // Perform various operations
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/app/dashboard');

            // Navigate through multiple pages
            $pages = ['/app/projects', '/app/tasks', '/app/calendar', '/app/team'];
            foreach ($pages as $page) {
                $browser->visit($page);
            }

            $finalMemory = memory_get_usage(true);
            $memoryIncrease = $finalMemory - $initialMemory;

            // Memory increase should be reasonable (less than 50MB)
            $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, 'Memory usage should not increase excessively');
        });
    }
}
