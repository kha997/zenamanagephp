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
 * Button Navigation Test
 * 
 * Tests navigation flows and user interactions
 */
class ButtonNavigationTest extends TestCase
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
            'description' => 'Test project for navigation',
            'status' => 'active',
            'budget_total' => 100000.00
        ]);
    }

    /**
     * Test main navigation
     */
    public function test_main_navigation(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->assertSee('Dashboard')
                    ->clickLink('Projects')
                    ->assertPathIs('/projects')
                    ->assertSee('Projects')
                    ->clickLink('Tasks')
                    ->assertPathIs('/tasks')
                    ->assertSee('Tasks')
                    ->clickLink('Documents')
                    ->assertPathIs('/documents')
                    ->assertSee('Documents')
                    ->clickLink('Team')
                    ->assertPathIs('/team')
                    ->assertSee('Team');
        });
    }

    /**
     * Test breadcrumb navigation
     */
    public function test_breadcrumb_navigation(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->clickLink($this->project->name)
                    ->assertPathIs('/projects/' . $this->project->id)
                    ->assertSee('Projects')
                    ->assertSee($this->project->name);
        });
    }

    /**
     * Test back/forward navigation
     */
    public function test_back_forward_navigation(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->clickLink($this->project->name)
                    ->assertPathIs('/projects/' . $this->project->id)
                    ->back()
                    ->assertPathIs('/projects')
                    ->forward()
                    ->assertPathIs('/projects/' . $this->project->id);
        });
    }

    /**
     * Test deep linking
     */
    public function test_deep_linking(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects/' . $this->project->id)
                    ->assertSee($this->project->name)
                    ->assertPathIs('/projects/' . $this->project->id);
        });
    }

    /**
     * Test mobile navigation
     */
    public function test_mobile_navigation(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->resize(375, 667) // iPhone size
                    ->visit('/dashboard')
                    ->click('.mobile-menu-toggle')
                    ->assertVisible('.mobile-menu')
                    ->clickLink('Projects')
                    ->assertPathIs('/projects');
        });
    }

    /**
     * Test sidebar navigation
     */
    public function test_sidebar_navigation(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->assertVisible('.sidebar')
                    ->click('.sidebar-item[href="/projects"]')
                    ->assertPathIs('/projects')
                    ->click('.sidebar-item[href="/tasks"]')
                    ->assertPathIs('/tasks');
        });
    }

    /**
     * Test user menu navigation
     */
    public function test_user_menu_navigation(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->click('.user-menu-toggle')
                    ->assertVisible('.user-menu')
                    ->clickLink('Profile')
                    ->assertPathIs('/profile')
                    ->clickLink('Logout')
                    ->assertPathIs('/login');
        });
    }

    /**
     * Test search navigation
     */
    public function test_search_navigation(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->type('.search-input', $this->project->name)
                    ->click('.search-button')
                    ->assertSee($this->project->name);
        });
    }

    /**
     * Test filter navigation
     */
    public function test_filter_navigation(): void
    {
        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->click('.filter-button')
                    ->assertVisible('.filter-menu')
                    ->select('.status-filter', 'active')
                    ->click('.apply-filter')
                    ->assertSee($this->project->name);
        });
    }

    /**
     * Test pagination navigation
     */
    public function test_pagination_navigation(): void
    {
        // Create multiple projects for pagination
        for ($i = 0; $i < 15; $i++) {
            Project::create([
                'tenant_id' => $this->tenant->id,
                'code' => 'PAGE-' . $i,
                'name' => "Project {$i}",
                'description' => "Project {$i} description",
                'status' => 'active',
                'budget_total' => 50000.00
            ]);
        }

        $this->browse(function ($browser) {
            $browser->loginAs($this->user)
                    ->visit('/projects')
                    ->assertSee('Next')
                    ->clickLink('Next')
                    ->assertSee('Previous')
                    ->clickLink('Previous')
                    ->assertSee('Next');
        });
    }
}
