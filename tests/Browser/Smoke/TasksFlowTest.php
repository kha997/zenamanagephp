<?php

namespace Tests\Browser\Smoke;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Task;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class TasksFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function user_can_view_tasks_list()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $task = Task::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);

        $this->browse(function (Browser $browser) use ($user, $task) {
            $browser->loginAs($user)
                    ->visit('/app/tasks')
                    ->assertSee('Tasks')
                    ->assertSee($task->name);
        });
    }

    /** @test */
    public function user_can_create_new_task()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        $this->browse(function (Browser $browser) use ($user, $project) {
            $browser->loginAs($user)
                    ->visit('/app/tasks')
                    ->click('@create-task-button')
                    ->assertPathIs('/app/tasks/create')
                    ->type('name', 'Test Task')
                    ->type('description', 'Test Task Description')
                    ->select('project_id', $project->id)
                    ->press('Create Task')
                    ->assertPathIs('/app/tasks')
                    ->assertSee('Test Task');
        });
    }

    /** @test */
    public function user_can_update_task_progress()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $task = Task::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);

        $this->browse(function (Browser $browser) use ($user, $task) {
            $browser->loginAs($user)
                    ->visit('/app/tasks/' . $task->id)
                    ->click('@update-progress-button')
                    ->type('progress_percent', '50')
                    ->press('Update Progress')
                    ->assertSee('50%');
        });
    }

    /** @test */
    public function user_can_edit_task()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $task = Task::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);

        $this->browse(function (Browser $browser) use ($user, $task) {
            $browser->loginAs($user)
                    ->visit('/app/tasks/' . $task->id)
                    ->click('@edit-task-button')
                    ->assertPathIs('/app/tasks/' . $task->id . '/edit')
                    ->type('name', 'Updated Task Name')
                    ->press('Update Task')
                    ->assertPathIs('/app/tasks/' . $task->id)
                    ->assertSee('Updated Task Name');
        });
    }
}
