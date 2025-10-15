<?php

namespace Tests\Browser\E2E;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Simple E2E Test without Database Migrations
 * Tests basic functionality without complex database setup
 */
class SimpleE2ETest extends DuskTestCase
{
    /** @test */
    public function login_page_loads_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->assertSee('Sign in to your account')
                    ->assertSee('Email address')
                    ->assertSee('Password')
                    ->assertSee('Sign in')
                    ->assertPresent('input[name="email"]')
                    ->assertPresent('input[name="password"]')
                    ->assertPresent('button[type="submit"]');
        });
    }

    /** @test */
    public function login_form_validation_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->press('Sign in')
                    ->pause(1000) // Wait for validation
                    ->assertSee('email')
                    ->assertSee('password');
        });
    }

    /** @test */
    public function invalid_login_shows_error()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->type('email', 'invalid@test.com')
                    ->type('password', 'wrongpassword')
                    ->press('Sign in')
                    ->assertSee('Invalid credentials');
        });
    }

    /** @test */
    public function protected_routes_redirect_to_login()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/app/dashboard')
                    ->pause(1000) // Wait for redirect
                    ->assertPathIs('/login');
        });
    }

    /** @test */
    public function health_endpoint_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/_debug/health')
                    ->assertSee('"status":"healthy"')
                    ->assertSee('"database"')
                    ->assertSee('"application"')
                    ->assertSee('"system"');
        });
    }

    /** @test */
    public function ping_endpoint_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/_debug/ping')
                    ->assertSee('"status":"ok"')
                    ->assertSee('"timestamp"');
        });
    }

    /** @test */
    public function security_headers_are_present()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->assertSee('Content-Security-Policy')
                    ->assertSee('X-Frame-Options')
                    ->assertSee('X-XSS-Protection');
        });
    }

    /** @test */
    public function csrf_token_is_present()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->assertPresent('input[name="_token"]');
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
}
