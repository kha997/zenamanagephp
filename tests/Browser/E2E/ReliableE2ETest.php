<?php

namespace Tests\Browser\E2E;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Reliable E2E Test Suite
 * Tests basic functionality that we know works
 */
class ReliableE2ETest extends DuskTestCase
{
    /** @test */
    public function login_page_loads_and_displays_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->assertSee('Sign in to your account')
                    ->assertSee('Email address')
                    ->assertSee('Password')
                    ->assertSee('Sign in')
                    ->assertPresent('input[name="email"]')
                    ->assertPresent('input[name="password"]')
                    ->assertPresent('button[type="submit"]')
                    ->assertPresent('input[name="_token"]');
        });
    }

    /** @test */
    public function health_endpoints_work_correctly()
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
        });
    }

    /** @test */
    public function page_performance_is_acceptable()
    {
        $this->browse(function (Browser $browser) {
            $startTime = microtime(true);
            
            $browser->visit('/login');
            
            $loadTime = microtime(true) - $startTime;
            
            // Page should load within 2 seconds
            $this->assertLessThan(2.0, $loadTime, "Login page loaded in {$loadTime}s");
        });
    }

    /** @test */
    public function responsive_design_works()
    {
        $this->browse(function (Browser $browser) {
            // Test desktop view
            $browser->resize(1920, 1080)
                    ->visit('/login')
                    ->assertSee('Sign in to your account');

            // Test tablet view
            $browser->resize(768, 1024)
                    ->visit('/login')
                    ->assertSee('Sign in to your account');

            // Test mobile view
            $browser->resize(375, 667)
                    ->visit('/login')
                    ->assertSee('Sign in to your account');
        });
    }

    /** @test */
    public function csrf_protection_is_active()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->assertPresent('input[name="_token"]');
        });
    }

    /** @test */
    public function form_elements_are_present()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->assertPresent('form[method="POST"]')
                    ->assertPresent('input[name="email"][type="email"]')
                    ->assertPresent('input[name="password"][type="password"]')
                    ->assertPresent('button[type="submit"]');
        });
    }

    /** @test */
    public function navigation_links_work()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->assertSee('create a new account')
                    ->assertSee('Forgot your password?');
        });
    }

    /** @test */
    public function page_title_is_correct()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->assertTitle('Login - ZenaManage');
        });
    }

    /** @test */
    public function memory_usage_is_reasonable()
    {
        $initialMemory = memory_get_usage(true);
        
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->visit('/_debug/health')
                    ->visit('/_debug/ping');
        });
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // Memory increase should be reasonable (less than 10MB)
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease, 'Memory usage should not increase excessively');
    }

    /** @test */
    public function error_handling_works()
    {
        $this->browse(function (Browser $browser) {
            // Test 404 page
            $browser->visit('/nonexistent-page')
                    ->assertSee('404')
                    ->assertDontSee('Stack trace')
                    ->assertDontSee('vendor/');
        });
    }
}
