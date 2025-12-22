<?php

namespace Tests\Browser\Smoke;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ProjectsFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function user_can_view_projects_list()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        $this->browse(function (Browser $browser) use ($user, $project) {
            $browser->loginAs($user)
                    ->visit('/app/projects')
                    ->assertSee('Projects')
                    ->assertSee($project->name);
        });
    }

    /** @test */
    public function user_can_create_new_project()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/app/projects')
                    ->click('@create-project-button')
                    ->assertPathIs('/app/projects/create')
                    ->type('name', 'Test Project')
                    ->type('description', 'Test Description')
                    ->press('Create Project')
                    ->assertPathIs('/app/projects')
                    ->assertSee('Test Project');
        });
    }

    /** @test */
    public function user_can_view_project_details()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        $this->browse(function (Browser $browser) use ($user, $project) {
            $browser->loginAs($user)
                    ->visit('/app/projects')
                    ->clickLink($project->name)
                    ->assertPathIs('/app/projects/' . $project->id)
                    ->assertSee($project->name)
                    ->assertSee($project->description);
        });
    }

    /** @test */
    public function user_can_edit_project()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        $this->browse(function (Browser $browser) use ($user, $project) {
            $browser->loginAs($user)
                    ->visit('/app/projects/' . $project->id)
                    ->click('@edit-project-button')
                    ->assertPathIs('/app/projects/' . $project->id . '/edit')
                    ->type('name', 'Updated Project Name')
                    ->press('Update Project')
                    ->assertPathIs('/app/projects/' . $project->id)
                    ->assertSee('Updated Project Name');
        });
    }
}
