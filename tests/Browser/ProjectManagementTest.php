<?php declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;

/**
 * Browser testing cho Project Management
 */
class ProjectManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    private $tenant;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com'
        ]);
    }

    /**
     * Test login flow
     */
    public function test_user_can_login()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->waitForLocation('/dashboard')
                    ->assertSee('Dashboard')
                    ->assertSee($this->user->name);
        });
    }

    /**
     * Test project creation flow
     */
    public function test_user_can_create_project()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->clickLink('Create Project')
                    ->waitForText('Create New Project')
                    ->type('name', 'Test Project from Browser')
                    ->type('description', 'This project was created via browser test')
                    ->type('start_date', '2024-01-01')
                    ->type('end_date', '2024-12-31')
                    ->select('status', 'active')
                    ->press('Create Project')
                    ->waitForText('Project created successfully')
                    ->assertSee('Test Project from Browser');
        });
    }

    /**
     * Test project listing and search
     */
    public function test_user_can_search_projects()
    {
        // Tạo test projects
        Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Searchable Project'
        ]);
        
        Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Another Project'
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->type('search', 'Searchable')
                    ->keys('#search', '{enter}')
                    ->waitForText('Searchable Project')
                    ->assertSee('Searchable Project')
                    ->assertDontSee('Another Project');
        });
    }

    /**
     * Test task management
     */
    public function test_user_can_manage_tasks()
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $this->browse(function (Browser $browser) use ($project) {
            $browser->loginAs($this->user)
                    ->visit("/projects/{$project->id}")
                    ->clickLink('Tasks')
                    ->clickLink('Add Task')
                    ->waitForText('Create New Task')
                    ->type('name', 'Browser Test Task')
                    ->type('description', 'Task created via browser test')
                    ->type('start_date', '2024-02-01')
                    ->type('end_date', '2024-02-28')
                    ->select('priority', 'high')
                    ->select('status', 'pending')
                    ->press('Create Task')
                    ->waitForText('Task created successfully')
                    ->assertSee('Browser Test Task');
        });
    }

    /**
     * Test responsive design
     */
    public function test_responsive_design()
    {
        $this->browse(function (Browser $browser) {
            // Test desktop view
            $browser->loginAs($this->user)
                    ->resize(1200, 800)
                    ->visit('/dashboard')
                    ->assertVisible('.sidebar')
                    ->assertVisible('.main-content');
            
            // Test tablet view
            $browser->resize(768, 1024)
                    ->visit('/dashboard')
                    ->assertVisible('.mobile-menu-toggle');
            
            // Test mobile view
            $browser->resize(375, 667)
                    ->visit('/dashboard')
                    ->assertVisible('.mobile-menu-toggle')
                    ->click('.mobile-menu-toggle')
                    ->waitFor('.mobile-menu')
                    ->assertVisible('.mobile-menu');
        });
    }

    /**
     * Test form validation
     */
    public function test_form_validation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects/create')
                    ->press('Create Project')
                    ->waitForText('The name field is required')
                    ->assertSee('The name field is required')
                    ->assertSee('The start date field is required');
        });
    }

    /**
     * Test AJAX functionality
     */
    public function test_ajax_functionality()
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        Task::factory(5)->create([
            'project_id' => $project->id
        ]);

        $this->browse(function (Browser $browser) use ($project) {
            $browser->loginAs($this->user)
                    ->visit("/projects/{$project->id}")
                    ->select('status_filter', 'completed')
                    ->waitFor('.task-list')
                    ->pause(1000) // Wait for AJAX
                    ->assertVisible('.task-list');
        });
    }

    /**
     * Test file upload functionality
     */
    public function test_file_upload()
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $this->browse(function (Browser $browser) use ($project) {
            $browser->loginAs($this->user)
                    ->visit("/projects/{$project->id}/documents")
                    ->clickLink('Upload Document')
                    ->waitForText('Upload New Document')
                    ->type('title', 'Test Document')
                    ->attach('file', __DIR__.'/fixtures/test-document.pdf')
                    ->press('Upload')
                    ->waitForText('Document uploaded successfully')
                    ->assertSee('Test Document');
        });
    }

    /**
     * Test keyboard navigation
     */
    public function test_keyboard_navigation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->keys('body', '{tab}') // Tab to first focusable element
                    ->keys('body', '{enter}') // Press enter
                    ->pause(500)
                    ->keys('body', '{escape}') // Press escape
                    ->pause(500);
        });
    }

    /**
     * Test accessibility features
     */
    public function test_accessibility_features()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    // Check for ARIA labels
                    ->assertAttribute('nav', 'role', 'navigation')
                    ->assertAttribute('main', 'role', 'main')
                    // Check for alt text on images
                    ->script('return document.querySelectorAll("img:not([alt])").length === 0');
        });
    }
}