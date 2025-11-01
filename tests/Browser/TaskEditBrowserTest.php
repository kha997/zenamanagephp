<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\Project;
use App\Models\User;

class TaskEditBrowserTest extends DuskTestCase
{
    use RefreshDatabase;

    protected $task;
    protected $project;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->project = Project::create([
            'id' => '01k5e2kkwynze0f37a8a4d3435',
            'name' => 'Test Project',
            'description' => 'Test project for testing',
            'code' => 'TEST001',
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        $this->user = User::create([
            'id' => '01k5e5nty3m1059pcyymbkgqt9',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->task = Task::create([
            'id' => '01k5e5nty3m1059pcyymbkgqt8',
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'description' => 'Test task description',
            'status' => 'in_progress',
            'priority' => 'low',
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'assignee_id' => $this->user->id,
            'progress_percent' => 50,
            'estimated_hours' => 8,
        ]);
    }

    /** @test */
    public function test_task_edit_page_loads_with_correct_data()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit("/tasks/{$this->task->id}/edit")
                    ->assertSee('Edit Task')
                    ->assertInputValue('name', 'Test Task')
                    ->assertSelected('status', 'in_progress')
                    ->assertSelected('priority', 'low')
                    ->assertInputValue('progress_percent', '50')
                    ->assertInputValue('estimated_hours', '8');
        });
    }

    /** @test */
    public function test_status_dropdown_has_correct_options()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit("/tasks/{$this->task->id}/edit")
                    ->click('select[name="status"]')
                    ->assertSee('Pending')
                    ->assertSee('In Progress')
                    ->assertSee('Review')
                    ->assertSee('Completed')
                    ->assertSee('Cancelled');
        });
    }

    /** @test */
    public function test_priority_dropdown_has_correct_options()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit("/tasks/{$this->task->id}/edit")
                    ->click('select[name="priority"]')
                    ->assertSee('Low')
                    ->assertSee('Medium')
                    ->assertSee('High')
                    ->assertSee('Urgent');
        });
    }

    /** @test */
    public function test_status_update_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit("/tasks/{$this->task->id}/edit")
                    ->select('status', 'completed')
                    ->click('button[type="button"]:contains("Update Task")')
                    ->waitForText('Task updated successfully!')
                    ->assertPathIs('/tasks');
        });

        // Verify database update
        $this->task->refresh();
        $this->assertEquals('completed', $this->task->status);
    }

    /** @test */
    public function test_priority_update_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit("/tasks/{$this->task->id}/edit")
                    ->select('priority', 'urgent')
                    ->click('button[type="button"]:contains("Update Task")')
                    ->waitForText('Task updated successfully!')
                    ->assertPathIs('/tasks');
        });

        // Verify database update
        $this->task->refresh();
        $this->assertEquals('urgent', $this->task->priority);
    }

    /** @test */
    public function test_name_update_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit("/tasks/{$this->task->id}/edit")
                    ->clear('input[name="name"]')
                    ->type('input[name="name"]', 'Updated Task Name')
                    ->click('button[type="button"]:contains("Update Task")')
                    ->waitForText('Task updated successfully!')
                    ->assertPathIs('/tasks');
        });

        // Verify database update
        $this->task->refresh();
        $this->assertEquals('Updated Task Name', $this->task->name);
    }

    /** @test */
    public function test_form_validation_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit("/tasks/{$this->task->id}/edit")
                    ->clear('input[name="name"]')
                    ->click('button[type="button"]:contains("Update Task")')
                    ->assertSee('Task name is required');
        });
    }

    /** @test */
    public function test_console_logs_are_working()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit("/tasks/{$this->task->id}/edit")
                    ->driver->executeScript('return console.log("Test log message");')
                    ->assertConsoleLogContains('Test log message');
        });
    }
}
