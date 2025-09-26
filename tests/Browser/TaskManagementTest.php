<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class TaskManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $tenant;
    protected $user;
    protected $project;
    protected $task;

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
        
        $this->task = Task::factory()->create([
            'project_id' => $this->project->id,
            'assignee_id' => $this->user->id
        ]);
    }

    /** @test */
    public function user_can_create_new_task()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/tasks')
                    ->clickLink('Create Task')
                    ->assertPathIs('/tasks/create')
                    ->type('name', 'Test Task')
                    ->type('description', 'Test Description')
                    ->select('project_id', $this->project->id)
                    ->select('assignee_id', $this->user->id)
                    ->select('status', 'pending')
                    ->select('priority', 'high')
                    ->type('due_date', '2023-12-31')
                    ->press('Create Task')
                    ->assertPathIs('/tasks')
                    ->assertSee('Test Task');
        });
    }

    /** @test */
    public function user_can_edit_existing_task()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/tasks')
                    ->clickLink('Edit')
                    ->assertPathIs('/tasks/' . $this->task->id . '/edit')
                    ->type('name', 'Updated Task')
                    ->type('description', 'Updated Description')
                    ->press('Update Task')
                    ->assertPathIs('/tasks')
                    ->assertSee('Updated Task');
        });
    }

    /** @test */
    public function user_can_view_task_details()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/tasks')
                    ->clickLink('View')
                    ->assertPathIs('/tasks/' . $this->task->id)
                    ->assertSee($this->task->name)
                    ->assertSee($this->task->description);
        });
    }

    /** @test */
    public function user_can_delete_task()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/tasks')
                    ->clickLink('Delete')
                    ->acceptDialog()
                    ->assertPathIs('/tasks')
                    ->assertDontSee($this->task->name);
        });
    }

    /** @test */
    public function user_can_filter_tasks_by_status()
    {
        Task::factory()->create(['status' => 'completed', 'project_id' => $this->project->id]);
        
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/tasks')
                    ->select('status_filter', 'completed')
                    ->press('Filter')
                    ->assertSee('completed');
        });
    }

    /** @test */
    public function user_can_filter_tasks_by_project()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/tasks')
                    ->select('project_filter', $this->project->id)
                    ->press('Filter')
                    ->assertSee($this->project->name);
        });
    }

    /** @test */
    public function user_can_search_tasks()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/tasks')
                    ->type('search', $this->task->name)
                    ->press('Search')
                    ->assertSee($this->task->name);
        });
    }

    /** @test */
    public function user_can_update_task_status()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/tasks')
                    ->clickLink('Update Status')
                    ->select('status', 'in_progress')
                    ->press('Update')
                    ->assertSee('in_progress');
        });
    }

    /** @test */
    public function task_page_is_responsive()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/tasks')
                    ->resize(375, 667) // Mobile size
                    ->assertSee('Tasks')
                    ->resize(1920, 1080) // Desktop size
                    ->assertSee('Tasks');
        });
    }
}
