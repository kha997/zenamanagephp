<?php

namespace Tests\Browser\Smoke;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class NavFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function user_can_navigate_between_main_sections()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/app/dashboard')
                    ->assertSee('Dashboard')
                    
                    // Navigate to Projects
                    ->click('@nav-projects')
                    ->assertPathIs('/app/projects')
                    ->assertSee('Projects')
                    
                    // Navigate to Tasks
                    ->click('@nav-tasks')
                    ->assertPathIs('/app/tasks')
                    ->assertSee('Tasks')
                    
                    // Navigate to Clients
                    ->click('@nav-clients')
                    ->assertPathIs('/app/clients')
                    ->assertSee('Clients')
                    
                    // Navigate to Quotes
                    ->click('@nav-quotes')
                    ->assertPathIs('/app/quotes')
                    ->assertSee('Quotes')
                    
                    // Navigate back to Dashboard
                    ->click('@nav-dashboard')
                    ->assertPathIs('/app/dashboard')
                    ->assertSee('Dashboard');
        });
    }

    /** @test */
    public function user_can_access_team_and_settings()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/app/dashboard')
                    
                    // Navigate to Team
                    ->click('@nav-team')
                    ->assertPathIs('/app/team')
                    ->assertSee('Team')
                    
                    // Navigate to Settings
                    ->click('@nav-settings')
                    ->assertPathIs('/app/settings')
                    ->assertSee('Settings');
        });
    }

    /** @test */
    public function user_can_access_calendar()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/app/dashboard')
                    
                    // Navigate to Calendar
                    ->click('@nav-calendar')
                    ->assertPathIs('/app/calendar')
                    ->assertSee('Calendar');
        });
    }
}
