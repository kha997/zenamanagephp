<?php declare(strict_types=1);

namespace Tests\Browser\Buttons;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Dusk\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;

/**
 * Button Form Submission Test
 * 
 * Tests form interactions and submissions
 */
class ButtonFormSubmissionTest extends TestCase
{
    use DatabaseMigrations;

    protected $tenant;
    protected $user;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'status' => 'active'
        ]);

        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@test-' . uniqid() . '.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenant->id
        ]);

        // Create test project
        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TEST-' . uniqid(),
            'name' => 'Test Project',
            'description' => 'Test project for form submission',
            'status' => 'active',
            'budget_total' => 100000.00
        ]);
    }

    /**
     * Test project creation form
     */
    public function test_project_creation_form(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects/create')
                    ->type('name', 'New Project')
                    ->type('description', 'New project description')
                    ->type('code', 'NEW-' . uniqid())
                    ->select('status', 'active')
                    ->type('budget_total', '75000')
                    ->click('.submit-button')
                    ->assertPathIs('/projects')
                    ->assertSee('New Project');
        });
    }

    /**
     * Test project edit form
     */
    public function test_project_edit_form(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects/' . $this->project->id . '/edit')
                    ->clear('name')
                    ->type('name', 'Updated Project')
                    ->clear('description')
                    ->type('description', 'Updated project description')
                    ->click('.submit-button')
                    ->assertPathIs('/projects/' . $this->project->id)
                    ->assertSee('Updated Project');
        });
    }

    /**
     * Test task creation form
     */
    public function test_task_creation_form(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/tasks/create')
                    ->type('name', 'New Task')
                    ->type('description', 'New task description')
                    ->select('project_id', $this->project->id)
                    ->select('status', 'pending')
                    ->select('priority', 'medium')
                    ->type('estimated_hours', '8')
                    ->click('.submit-button')
                    ->assertPathIs('/tasks')
                    ->assertSee('New Task');
        });
    }

    /**
     * Test form validation
     */
    public function test_form_validation(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects/create')
                    ->click('.submit-button')
                    ->assertSee('The name field is required')
                    ->assertSee('The code field is required')
                    ->assertSee('The budget total field is required');
        });
    }

    /**
     * Test form reset
     */
    public function test_form_reset(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects/create')
                    ->type('name', 'Test Project')
                    ->type('description', 'Test description')
                    ->click('.reset-button')
                    ->assertInputValue('name', '')
                    ->assertInputValue('description', '');
        });
    }

    /**
     * Test form cancel
     */
    public function test_form_cancel(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects/create')
                    ->type('name', 'Test Project')
                    ->click('.cancel-button')
                    ->assertPathIs('/projects');
        });
    }

    /**
     * Test file upload form
     */
    public function test_file_upload_form(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents/create')
                    ->type('name', 'Test Document')
                    ->type('description', 'Test document description')
                    ->select('project_id', $this->project->id)
                    ->attach('file', __DIR__ . '/test-file.txt')
                    ->click('.submit-button')
                    ->assertPathIs('/documents')
                    ->assertSee('Test Document');
        });
    }

    /**
     * Test bulk action form
     */
    public function test_bulk_action_form(): void
    {
        // Create multiple tasks
        $tasks = [];
        for ($i = 0; $i < 3; $i++) {
            $task = Task::create([
                'tenant_id' => $this->tenant->id,
                'project_id' => $this->project->id,
                'name' => "Bulk Task {$i}",
                'description' => "Bulk task description {$i}",
                'status' => 'pending',
                'priority' => 'medium',
                'estimated_hours' => 8.0
            ]);
            $tasks[] = $task;
        }

        $this->browse(function ($browser) use ($tasks) {
            $browser->loginAs($this->user)
                    ->visit('/tasks')
                    ->click('.select-all-checkbox')
                    ->click('.bulk-actions-button')
                    ->assertVisible('.bulk-actions-menu')
                    ->click('.bulk-status-change')
                    ->select('.bulk-status-select', 'completed')
                    ->click('.apply-bulk-action')
                    ->assertSee('Tasks updated successfully');
        });
    }

    /**
     * Test search form
     */
    public function test_search_form(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->type('.search-input', $this->project->name)
                    ->click('.search-button')
                    ->assertSee($this->project->name)
                    ->clear('.search-input')
                    ->type('.search-input', 'Non-existent Project')
                    ->click('.search-button')
                    ->assertSee('No projects found');
        });
    }

    /**
     * Test filter form
     */
    public function test_filter_form(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->click('.filter-button')
                    ->assertVisible('.filter-form')
                    ->select('.status-filter', 'active')
                    ->select('.priority-filter', 'high')
                    ->click('.apply-filter')
                    ->assertSee($this->project->name);
        });
    }

    /**
     * Test form loading states
     */
    public function test_form_loading_states(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects/create')
                    ->type('name', 'Test Project')
                    ->type('description', 'Test description')
                    ->type('code', 'TEST-' . uniqid())
                    ->select('status', 'active')
                    ->type('budget_total', '50000')
                    ->click('.submit-button')
                    ->assertVisible('.loading-spinner')
                    ->waitUntilMissing('.loading-spinner')
                    ->assertPathIs('/projects');
        });
    }

    /**
     * Test form error handling
     */
    public function test_form_error_handling(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects/create')
                    ->type('name', 'Test Project')
                    ->type('description', 'Test description')
                    ->type('code', 'INVALID_CODE_FORMAT')
                    ->select('status', 'invalid_status')
                    ->type('budget_total', '-1000')
                    ->click('.submit-button')
                    ->assertSee('The code format is invalid')
                    ->assertSee('The status field is invalid')
                    ->assertSee('The budget total must be positive');
        });
    }
}
