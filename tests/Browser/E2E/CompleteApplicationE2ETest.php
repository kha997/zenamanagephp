<?php

namespace Tests\Browser\E2E;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Template;
use Illuminate\Foundation\Testing\DatabaseMigrations;

/**
 * Comprehensive End-to-End Test Suite
 * Tests the complete user journey through the ZenaManage application
 */
class CompleteApplicationE2ETest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant and user
        $this->tenant = Tenant::factory()->create([
            'name' => 'E2E Test Tenant',
            'domain' => 'e2e-test.zenamanage.com',
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'E2E Test User',
            'email' => 'e2e@test.com',
            'password' => bcrypt('password'),
            'role' => 'pm',
        ]);
    }

    /** @test */
    public function complete_user_journey_from_login_to_logout()
    {
        $this->browse(function (Browser $browser) {
            // Step 1: Login
            $browser->visit('/login')
                    ->assertSee('Login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/app/dashboard')
                    ->assertPathIs('/app/dashboard')
                    ->assertSee('Dashboard')
                    ->assertSee('Welcome back, ' . $this->user->name);

            // Step 2: Navigate to Projects
            $browser->clickLink('Projects')
                    ->waitForLocation('/app/projects')
                    ->assertPathIs('/app/projects')
                    ->assertSee('Projects')
                    ->assertSee('Create New Project');

            // Step 3: Navigate to Tasks
            $browser->clickLink('Tasks')
                    ->waitForLocation('/app/tasks')
                    ->assertPathIs('/app/tasks')
                    ->assertSee('Tasks')
                    ->assertSee('Create New Task');

            // Step 4: Navigate to Calendar
            $browser->clickLink('Calendar')
                    ->waitForLocation('/app/calendar')
                    ->assertPathIs('/app/calendar')
                    ->assertSee('Calendar')
                    ->assertSee('Today');

            // Step 5: Navigate to Team
            $browser->clickLink('Team')
                    ->waitForLocation('/app/team')
                    ->assertPathIs('/app/team')
                    ->assertSee('Team')
                    ->assertSee('Team Members');

            // Step 6: Navigate to Documents
            $browser->clickLink('Documents')
                    ->waitForLocation('/app/documents')
                    ->assertPathIs('/app/documents')
                    ->assertSee('Documents')
                    ->assertSee('Upload Document');

            // Step 7: Navigate to Templates
            $browser->clickLink('Templates')
                    ->waitForLocation('/app/templates')
                    ->assertPathIs('/app/templates')
                    ->assertSee('Templates')
                    ->assertSee('Create Template');

            // Step 8: Navigate to Clients
            $browser->clickLink('Clients')
                    ->waitForLocation('/app/clients')
                    ->assertPathIs('/app/clients')
                    ->assertSee('Clients')
                    ->assertSee('Add Client');

            // Step 9: Navigate to Quotes
            $browser->clickLink('Quotes')
                    ->waitForLocation('/app/quotes')
                    ->assertPathIs('/app/quotes')
                    ->assertSee('Quotes')
                    ->assertSee('Create Quote');

            // Step 10: Return to Dashboard
            $browser->clickLink('Dashboard')
                    ->waitForLocation('/app/dashboard')
                    ->assertPathIs('/app/dashboard')
                    ->assertSee('Dashboard');

            // Step 11: Logout
            $browser->click('@logout-button')
                    ->waitForLocation('/login')
                    ->assertPathIs('/login')
                    ->assertSee('Login');
        });
    }

    /** @test */
    public function authentication_security_tests()
    {
        $this->browse(function (Browser $browser) {
            // Test 1: Invalid credentials
            $browser->visit('/login')
                    ->type('email', 'invalid@test.com')
                    ->type('password', 'wrongpassword')
                    ->press('Login')
                    ->waitForText('Invalid credentials')
                    ->assertSee('Invalid credentials');

            // Test 2: Empty credentials
            $browser->visit('/login')
                    ->press('Login')
                    ->assertSee('The email field is required')
                    ->assertSee('The password field is required');

            // Test 3: Access protected route without authentication
            $browser->visit('/app/dashboard')
                    ->waitForLocation('/login')
                    ->assertPathIs('/login')
                    ->assertSee('Login');

            // Test 4: Valid login
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/app/dashboard')
                    ->assertPathIs('/app/dashboard');
        });
    }

    /** @test */
    public function tenant_isolation_test()
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create([
            'name' => 'Other Tenant',
            'domain' => 'other.zenamanage.com',
        ]);
        
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other User',
            'email' => 'other@test.com',
            'password' => bcrypt('password'),
        ]);

        // Create projects for both tenants
        $ourProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Our Project',
        ]);
        
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Project',
        ]);

        $this->browse(function (Browser $browser) use ($ourProject, $otherProject) {
            // Login as our user
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/app/dashboard');

            // Navigate to projects
            $browser->visit('/app/projects')
                    ->assertSee('Our Project')
                    ->assertDontSee('Other Project');

            // Test that we can't access other tenant's data
            $browser->visit('/app/projects/' . $otherProject->id)
                    ->assertSee('404')
                    ->assertDontSee('Other Project');
        });
    }

    /** @test */
    public function responsive_design_test()
    {
        $this->browse(function (Browser $browser) {
            // Login first
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/app/dashboard');

            // Test desktop view
            $browser->resize(1920, 1080)
                    ->visit('/app/dashboard')
                    ->assertSee('Dashboard')
                    ->assertVisible('@desktop-nav');

            // Test tablet view
            $browser->resize(768, 1024)
                    ->visit('/app/dashboard')
                    ->assertSee('Dashboard')
                    ->assertVisible('@tablet-nav');

            // Test mobile view
            $browser->resize(375, 667)
                    ->visit('/app/dashboard')
                    ->assertSee('Dashboard')
                    ->assertVisible('@mobile-menu-button')
                    ->click('@mobile-menu-button')
                    ->assertVisible('@mobile-nav');
        });
    }

    /** @test */
    public function performance_test()
    {
        $this->browse(function (Browser $browser) {
            $startTime = microtime(true);

            // Login
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/app/dashboard');

            $loginTime = microtime(true) - $startTime;
            $this->assertLessThan(3.0, $loginTime, 'Login should complete within 3 seconds');

            // Test page load times
            $pages = [
                '/app/dashboard',
                '/app/projects',
                '/app/tasks',
                '/app/calendar',
                '/app/team',
                '/app/documents',
                '/app/templates',
                '/app/clients',
                '/app/quotes',
            ];

            foreach ($pages as $page) {
                $pageStartTime = microtime(true);
                $browser->visit($page)
                        ->waitForText('Dashboard', 5);
                $pageLoadTime = microtime(true) - $pageStartTime;
                
                $this->assertLessThan(2.0, $pageLoadTime, "Page {$page} should load within 2 seconds");
            }
        });
    }

    /** @test */
    public function error_handling_test()
    {
        $this->browse(function (Browser $browser) {
            // Login first
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/app/dashboard');

            // Test 404 page
            $browser->visit('/app/nonexistent-page')
                    ->assertSee('404')
                    ->assertSee('Page Not Found');

            // Test invalid project ID
            $browser->visit('/app/projects/invalid-id')
                    ->assertSee('404')
                    ->assertSee('Project not found');
        });
    }

    /** @test */
    public function security_headers_test()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->assertHeader('X-Content-Type-Options', 'nosniff')
                    ->assertHeader('X-Frame-Options', 'DENY')
                    ->assertHeader('X-XSS-Protection', '1; mode=block');
        });
    }

    /** @test */
    public function csrf_protection_test()
    {
        $this->browse(function (Browser $browser) {
            // Test that CSRF token is present in forms
            $browser->visit('/login')
                    ->assertPresent('input[name="_token"]')
                    ->assertAttribute('input[name="_token"]', 'value', function ($value) {
                        return !empty($value);
                    });
        });
    }
}
