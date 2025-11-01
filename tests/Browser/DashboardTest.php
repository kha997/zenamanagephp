<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class DashboardTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);
    }

    /** @test */
    public function user_can_login_and_access_dashboard()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->type('email', 'test@example.com')
                    ->type('password', 'password')
                    ->press('Login')
                    ->assertPathIs('/dashboard')
                    ->assertSee('Dashboard');
        });
    }

    /** @test */
    public function user_can_navigate_to_projects_page()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->clickLink('Projects')
                    ->assertPathIs('/projects')
                    ->assertSee('Projects');
        });
    }

    /** @test */
    public function user_can_navigate_to_tasks_page()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->clickLink('Tasks')
                    ->assertPathIs('/tasks')
                    ->assertSee('Tasks');
        });
    }

    /** @test */
    public function user_can_navigate_to_documents_page()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->clickLink('Documents')
                    ->assertPathIs('/documents')
                    ->assertSee('Documents');
        });
    }

    /** @test */
    public function user_can_navigate_to_team_page()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->clickLink('Team')
                    ->assertPathIs('/team')
                    ->assertSee('Team');
        });
    }

    /** @test */
    public function user_can_logout()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->click('#logout-button')
                    ->assertPathIs('/login')
                    ->assertSee('Login');
        });
    }

    /** @test */
    public function dashboard_shows_project_statistics()
    {
        Project::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);
        
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->assertSee('Total Projects')
                    ->assertSee('5');
        });
    }

    /** @test */
    public function dashboard_shows_task_statistics()
    {
        $project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        Task::factory()->count(10)->create(['project_id' => $project->id]);
        
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->assertSee('Total Tasks')
                    ->assertSee('10');
        });
    }

    /** @test */
    public function dashboard_shows_recent_activities()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->assertSee('Recent Activities');
        });
    }

    /** @test */
    public function dashboard_is_responsive()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->resize(375, 667) // Mobile size
                    ->assertSee('Dashboard')
                    ->resize(1920, 1080) // Desktop size
                    ->assertSee('Dashboard');
        });
    }
}
