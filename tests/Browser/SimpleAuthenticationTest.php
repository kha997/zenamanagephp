<?php declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Simple Authentication Test without DatabaseMigrations
 */
class SimpleAuthenticationTest extends DuskTestCase
{
    /**
     * Test login page loads correctly
     */
    public function test_login_page_loads(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->assertSee('Sign in to your account')
                    ->assertSee('Email address')
                    ->assertSee('Password')
                    ->assertSee('Sign in');
        });
    }

    /**
     * Test login form validation
     */
    public function test_login_form_validation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->press('Sign in')
                    ->pause(1000)
                    ->assertSee('email')
                    ->assertSee('password');
        });
    }

    /**
     * Test protected routes redirect to login
     */
    public function test_protected_routes_redirect(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/app/dashboard')
                    ->pause(1000)
                    ->assertPathIs('/login');
        });
    }
}
