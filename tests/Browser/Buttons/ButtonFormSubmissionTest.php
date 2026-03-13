<?php

declare(strict_types=1);

namespace Tests\Browser\Buttons;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Button Form Submission Test.
 *
 * LEGACY FROZEN (Phase 1).
 *
 * This mixed suite is intentionally frozen per:
 * docs/change-proposals/button-form-submission-audit-rewrite-plan.md
 *
 * Historical coverage intent preserved here:
 * - project create/edit form submission
 * - task create form submission
 * - project form validation/reset/cancel
 * - document upload form
 * - bulk/search/filter/loading/error form interactions
 */
class ButtonFormSubmissionTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $tenant;

    protected $user;

    protected $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped(
            'Frozen legacy mixed browser suite. See docs/change-proposals/button-form-submission-audit-rewrite-plan.md'
        );

        // Create test tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'status' => 'active',
        ]);

        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@test-' . uniqid() . '.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenant->id,
        ]);

        // Create test project
        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TEST-' . uniqid(),
            'name' => 'Test Project',
            'description' => 'Test project for form submission',
            'status' => 'active',
            'budget_total' => 100000.00,
        ]);
    }

    /**
     * Test project creation form.
     */
    public function test_project_creation_form(): void
    {
        $this->browse(function (Browser $browser) {
            $today = now()->toDateString();
            $endDate = now()->addDays(7)->toDateString();

            $browser->loginAs($this->user)
                    ->visitRoute('app.projects.create')
                    ->waitFor('@project-name', 15)
                    ->type('@project-name', 'New Project')
                    ->type('@project-description', 'New project description')
                    ->type('@project-code', 'NEW-' . uniqid())
                    ->type('@project-start-date', $today)
                    ->type('@project-end-date', $endDate)
                    ->select('@project-status', 'active')
                    ->type('@project-budget-total', '75000')
                    ->click('@project-submit')
                    ->waitForLocation('/app/projects')
                    ->assertPathIs('/app/projects');
        });
    }

    /**
     * Test project edit form.
     */
    public function test_project_edit_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app/projects/' . $this->project->id . '/edit')
                    ->waitFor('@project-name')
                    ->clear('@project-name')
                    ->type('@project-name', 'Updated Project')
                    ->clear('@project-description')
                    ->type('@project-description', 'Updated project description')
                    ->select('select[x-model="formData.pm_id"]', (string) $this->user->id)
                    ->scrollIntoView('@project-submit')
                    ->pause(100)
                    ->click('@project-submit')
                    ->waitForLocation('/projects/' . $this->project->id, 15)
                    ->assertPathIs('/projects/' . $this->project->id);
        });
    }

    /**
     * Test task creation form.
     */
    public function test_task_creation_form(): void
    {
        $this->browse(function (Browser $browser) {
            $today = now()->toDateString();
            $endDate = now()->addDays(7)->toDateString();

            $browser->loginAs($this->user)
                    ->visit('/app/tasks/create')
                    ->waitFor('@task-name')
                    ->type('@task-name', 'New Task')
                    ->type('@task-description', 'New task description')
                    ->select('@task-project', (string) $this->project->id)
                    ->select('@task-status', 'pending')
                    ->select('@task-priority', 'medium')
                    ->type('@task-start-date', $today)
                    ->type('@task-end-date', $endDate)
                    ->type('@task-estimated-hours', '8')
                    ->click('@task-submit')
                    ->waitForLocation('/tasks')
                    ->assertPathIs('/tasks');
        });
    }

    /**
     * Test form validation.
     */
    public function test_form_validation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app/projects/create')
                    ->click('@project-submit')
                    ->pause(500)
                    ->assertPathIs('/app/projects/create')
                    ->assertScript("return document.querySelector('input[name=\"name\"]').matches(':invalid');", true)
                    ->assertScript("return document.querySelector('input[name=\"start_date\"]').matches(':invalid');", true)
                    ->assertScript("return document.querySelector('input[name=\"end_date\"]').matches(':invalid');", true);
        });
    }

    /**
     * Test form reset.
     */
    public function test_form_reset(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app/projects/create')
                    ->type('name', 'Test Project')
                    ->type('description', 'Test description')
                    ->click('@project-cancel')
                    ->waitForLocation('/app/projects')
                    ->assertPathIs('/app/projects')
                    ->visit('/app/projects/create')
                    ->assertInputValue('name', '')
                    ->assertInputValue('description', '');
        });
    }

    /**
     * Test form cancel.
     */
    public function test_form_cancel(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/app/projects/create')
                    ->type('name', 'Test Project')
                    ->click('@project-cancel')
                    ->waitForLocation('/app/projects')
                    ->assertPathIs('/app/projects');
        });
    }

    /**
     * Test file upload form.
     */
    public function test_file_upload_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents/create')
                    ->type('name', 'Test Document')
                    ->type('description', 'Test document description')
                    ->select('project_id', $this->project->id)
                    ->attach('file', __DIR__ . '/test-file.txt')
                    ->click('form[action="/api/v1/upload-document"] button[type="submit"]')
                    ->assertPathIs('/documents')
                    ->assertSee('Test Document');
        });
    }

    /**
     * Test bulk action form.
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
                'estimated_hours' => 8.0,
            ]);
            $tasks[] = $task;
        }

        $this->browse(function (Browser $browser) use ($tasks) {
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
     * Test search form.
     */
    public function test_search_form(): void
    {
        $this->browse(function (Browser $browser) {
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
     * Test filter form.
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
     * Test form loading states.
     */
    public function test_form_loading_states(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/app/projects/create')
                    ->type('name', 'Test Project')
                    ->type('description', 'Test description')
                    ->type('code', 'TEST-' . uniqid())
                    ->select('status', 'active')
                    ->type('budget_total', '50000')
                    ->click('@project-submit')
                    ->assertVisible('.loading-spinner')
                    ->waitUntilMissing('.loading-spinner')
                    ->waitForLocation('/app/projects', 10)
                    ->assertPathIs('/app/projects');
        });
    }

    /**
     * Test form error handling.
     */
    public function test_form_error_handling(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/app/projects/create')
                    ->type('name', 'Test Project')
                    ->type('description', 'Test description')
                    ->type('code', 'INVALID_CODE_FORMAT')
                    ->select('status', 'invalid_status')
                    ->type('budget_total', '-1000')
                    ->click('@project-submit')
                    ->assertSee('The code format is invalid')
                    ->assertSee('The status field is invalid')
                    ->assertSee('The budget total must be positive');
        });
    }
}
