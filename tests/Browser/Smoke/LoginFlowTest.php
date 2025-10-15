<?php

namespace Tests\Browser\Smoke;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class LoginFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function user_can_login_and_see_dashboard()
    {
        // Create test data
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                    ->type('email', $user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->assertPathIs('/app/dashboard')
                    ->assertSee('Dashboard')
                    ->assertSee($user->name);
        });
    }

    /** @test */
    public function invalid_credentials_show_error()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->type('email', 'invalid@example.com')
                    ->type('password', 'wrongpassword')
                    ->press('Login')
                    ->assertPathIs('/login')
                    ->assertSee('These credentials do not match our records');
        });
    }

    /** @test */
    public function user_can_logout()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/app/dashboard')
                    ->click('@logout-button')
                    ->assertPathIs('/login')
                    ->assertSee('Login');
        });
    }
}
