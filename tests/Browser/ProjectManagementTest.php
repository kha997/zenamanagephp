<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ProjectManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $tenant;
    protected $user;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);
        
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'manager_id' => $this->user->id
        ]);
    }

    /** @test */
    public function user_can_create_new_project()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->clickLink('Create Project')
                    ->assertPathIs('/projects/create')
                    ->type('name', 'Test Project')
                    ->type('description', 'Test Description')
                    ->select('status', 'active')
                    ->select('priority', 'high')
                    ->type('start_date', '2023-01-01')
                    ->type('end_date', '2023-12-31')
                    ->press('Create Project')
                    ->assertPathIs('/projects')
                    ->assertSee('Test Project');
        });
    }

    /** @test */
    public function user_can_edit_existing_project()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->clickLink('Edit')
                    ->assertPathIs('/projects/' . $this->project->id . '/edit')
                    ->type('name', 'Updated Project')
                    ->type('description', 'Updated Description')
                    ->press('Update Project')
                    ->assertPathIs('/projects')
                    ->assertSee('Updated Project');
        });
    }

    /** @test */
    public function user_can_view_project_details()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->clickLink('View')
                    ->assertPathIs('/projects/' . $this->project->id)
                    ->assertSee($this->project->name)
                    ->assertSee($this->project->description);
        });
    }

    /** @test */
    public function user_can_delete_project()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->clickLink('Delete')
                    ->acceptDialog()
                    ->assertPathIs('/projects')
                    ->assertDontSee($this->project->name);
        });
    }

    /** @test */
    public function user_can_filter_projects_by_status()
    {
        Project::factory()->create(['status' => 'completed', 'tenant_id' => $this->tenant->id]);
        
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->select('status_filter', 'completed')
                    ->press('Filter')
                    ->assertSee('completed');
        });
    }

    /** @test */
    public function user_can_search_projects()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->type('search', $this->project->name)
                    ->press('Search')
                    ->assertSee($this->project->name);
        });
    }

    /** @test */
    public function project_page_is_responsive()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->resize(375, 667) // Mobile size
                    ->assertSee('Projects')
                    ->resize(1920, 1080) // Desktop size
                    ->assertSee('Projects');
        });
    }
}