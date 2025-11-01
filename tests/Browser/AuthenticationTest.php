<?php declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Models\User;
use App\Models\Tenant;

/**
 * Browser testing cho Authentication
 */
class AuthenticationTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test login with valid credentials
     */
    public function test_login_with_valid_credentials()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password')
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                    ->type('email', $user->email)
                    ->type('password', 'password')
                    ->press('Sign in')
                    ->waitForLocation('/app/dashboard')
                    ->assertPathIs('/app/dashboard')
                    ->assertSee('Dashboard');
        });
    }

    /**
     * Test login with invalid credentials
     */
    public function test_login_with_invalid_credentials()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->type('email', 'invalid@example.com')
                    ->type('password', 'wrongpassword')
                    ->press('Sign in')
                    ->waitForText('These credentials do not match our records')
                    ->assertSee('These credentials do not match our records')
                    ->assertPathIs('/login');
        });
    }

    /**
     * Test logout functionality
     */
    public function test_logout_functionality()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/dashboard')
                    ->clickLink('Logout')
                    ->waitForLocation('/login')
                    ->assertPathIs('/login')
                    ->visit('/dashboard')
                    ->assertPathIs('/login'); // Should redirect to login
        });
    }

    /**
     * Test registration flow
     */
    public function test_user_registration()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                    ->type('name', 'Test User')
                    ->type('email', 'newuser@example.com')
                    ->type('password', 'password123')
                    ->type('password_confirmation', 'password123')
                    ->press('Create Account')
                    ->waitForLocation('/dashboard')
                    ->assertPathIs('/dashboard')
                    ->assertSee('Test User');
        });
    }

    /**
     * Test password reset flow
     */
    public function test_password_reset_flow()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'reset@example.com'
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                    ->clickLink('Forgot Password?')
                    ->waitForLocation('/password/reset')
                    ->type('email', $user->email)
                    ->press('Send Password Reset Link')
                    ->waitForText('We have emailed your password reset link')
                    ->assertSee('We have emailed your password reset link');
        });
    }
}