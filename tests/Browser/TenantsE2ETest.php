<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Tenant;

class TenantsE2ETest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $user;
    protected $tenants;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create([
            'email' => 'admin@test.com',
            'is_super_admin' => true
        ]);

        // Create test tenants
        $this->tenants = Tenant::factory()->count(5)->create();
    }

    /**
     * Test complete tenant management workflow
     */
    public function test_complete_tenant_management_workflow()
    {
        $this->browse(function (Browser $browser) {
            // Login
            $browser->visit('/login')
                    ->type('email', $this->user->email)
                    ->type('password', 'password')
                    ->press('Login')
                    ->assertPathIs('/app/dashboard');

            // Navigate to tenants page
            $browser->visit('/admin/tenants')
                    ->assertSee('Tenants')
                    ->assertSee('Create Tenant')
                    ->assertSee('Export');

            // Test KPI cards
            $browser->assertSee('Total')
                    ->assertSee('Active')
                    ->assertSee('Suspended')
                    ->assertSee('New 30d')
                    ->assertSee('Trial Expiring');

            // Test search functionality
            $browser->type('#search-input', $this->tenants->first()->name)
                    ->pause(500)
                    ->assertSee($this->tenants->first()->name);

            // Test filter chips
            $browser->click('[data-filter-type="status"][data-filter-value="active"]')
                    ->pause(500)
                    ->assertSee('Active');

            // Test pagination
            $browser->assertSee('25 per page')
                    ->click('#per-page-select')
                    ->select('#per-page-select', '10')
                    ->pause(500);

            // Test bulk actions
            $browser->check('#select-all-checkbox')
                    ->assertSee('bulk-actions-bar')
                    ->assertSee('Export Selected')
                    ->assertSee('Suspend')
                    ->assertSee('Resume')
                    ->assertSee('Change Plan')
                    ->assertSee('Delete');

            // Test row actions
            $browser->click('.action-menu-btn')
                    ->assertSee('View')
                    ->assertSee('Edit')
                    ->assertSee('Suspend')
                    ->assertSee('Resume')
                    ->assertSee('Impersonate')
                    ->assertSee('Delete');

            // Test create tenant modal
            $browser->click('#create-tenant-btn')
                    ->assertSee('Create Tenant')
                    ->type('name', 'Test Tenant')
                    ->type('domain', 'test.com')
                    ->select('plan', 'pro')
                    ->press('Create Tenant')
                    ->pause(1000)
                    ->assertSee('Test Tenant');

            // Test tenant detail page
            $browser->click('.tenant-name')
                    ->assertPathIs('/admin/tenants/' . $this->tenants->first()->id)
                    ->assertSee('Overview')
                    ->assertSee('Actions')
                    ->assertSee('Recent Activity');

            // Test back to list
            $browser->click('#back-to-list-btn')
                    ->assertPathIs('/admin/tenants');
        });
    }

    /**
     * Test KPI functionality
     */
    public function test_kpi_functionality()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/admin/tenants');

            // Test KPI click actions
            $browser->click('[data-kpi-action="filter-active"]')
                    ->pause(500)
                    ->assertSee('Active');

            $browser->click('[data-kpi-action="filter-disabled"]')
                    ->pause(500)
                    ->assertSee('Suspended');

            $browser->click('[data-kpi-action="view-recent"]')
                    ->pause(500)
                    ->assertSee('Last 30 days');

            $browser->click('[data-kpi-action="view-expiring"]')
                    ->pause(500)
                    ->assertSee('Trial');
        });
    }

    /**
     * Test bulk operations
     */
    public function test_bulk_operations()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/admin/tenants');

            // Select multiple tenants
            $browser->check('.tenant-checkbox')
                    ->check('.tenant-checkbox')
                    ->assertSee('bulk-actions-bar')
                    ->assertSee('2 selected');

            // Test bulk suspend
            $browser->click('#bulk-suspend-btn')
                    ->whenAvailable('.modal', function ($modal) {
                        $modal->press('Confirm');
                    })
                    ->pause(1000)
                    ->assertSee('Successfully suspended');

            // Test bulk resume
            $browser->check('.tenant-checkbox')
                    ->click('#bulk-resume-btn')
                    ->whenAvailable('.modal', function ($modal) {
                        $modal->press('Confirm');
                    })
                    ->pause(1000)
                    ->assertSee('Successfully resumed');
        });
    }

    /**
     * Test form validation
     */
    public function test_form_validation()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/admin/tenants')
                    ->click('#create-tenant-btn');

            // Test required field validation
            $browser->press('Create Tenant')
                    ->assertSee('Tenant name is required')
                    ->assertSee('Domain is required')
                    ->assertSee('Please select a valid plan');

            // Test invalid domain
            $browser->type('name', 'Test')
                    ->type('domain', 'invalid-domain')
                    ->select('plan', 'pro')
                    ->press('Create Tenant')
                    ->assertSee('Please enter a valid domain');

            // Test valid form submission
            $browser->type('name', 'Valid Tenant')
                    ->type('domain', 'valid.com')
                    ->select('plan', 'pro')
                    ->press('Create Tenant')
                    ->pause(1000)
                    ->assertSee('Valid Tenant');
        });
    }

    /**
     * Test responsive design
     */
    public function test_responsive_design()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/admin/tenants');

            // Test mobile view
            $browser->resize(375, 667)
                    ->assertSee('Tenants')
                    ->assertSee('Create Tenant')
                    ->assertSee('Export');

            // Test tablet view
            $browser->resize(768, 1024)
                    ->assertSee('Tenants')
                    ->assertSee('Create Tenant')
                    ->assertSee('Export');

            // Test desktop view
            $browser->resize(1920, 1080)
                    ->assertSee('Tenants')
                    ->assertSee('Create Tenant')
                    ->assertSee('Export');
        });
    }

    /**
     * Test error handling
     */
    public function test_error_handling()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/admin/tenants');

            // Test network error simulation
            $browser->script('window.fetch = () => Promise.reject(new Error("Network error"))');
            
            $browser->click('#create-tenant-btn')
                    ->type('name', 'Test')
                    ->type('domain', 'test.com')
                    ->select('plan', 'pro')
                    ->press('Create Tenant')
                    ->pause(1000)
                    ->assertSee('Failed to create tenant');
        });
    }

    /**
     * Test performance
     */
    public function test_performance()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user);

            // Measure page load time
            $startTime = microtime(true);
            $browser->visit('/admin/tenants');
            $endTime = microtime(true);
            $loadTime = $endTime - $startTime;

            // Assert page loads within 2 seconds
            $this->assertLessThan(2, $loadTime, 'Page should load within 2 seconds');

            // Test KPI loading
            $browser->assertSee('Total')
                    ->assertSee('Active')
                    ->assertSee('Suspended');

            // Test table loading
            $browser->assertSee('Tenant')
                    ->assertSee('Domain')
                    ->assertSee('Plan')
                    ->assertSee('Status');
        });
    }

    /**
     * Test accessibility
     */
    public function test_accessibility()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/admin/tenants');

            // Test keyboard navigation
            $browser->keys('', ['tab'])
                    ->assertFocused('#search-input')
                    ->keys('', ['tab'])
                    ->assertFocused('#create-tenant-btn');

            // Test ARIA labels
            $browser->assertAttribute('[data-kpi-action="filter-active"]', 'aria-label')
                    ->assertAttribute('#select-all-checkbox', 'aria-label')
                    ->assertAttribute('.action-menu-btn', 'aria-label');

            // Test screen reader support
            $browser->assertSee('Tenants')
                    ->assertSee('Create Tenant')
                    ->assertSee('Export');
        });
    }

    /**
     * Test soft refresh functionality
     */
    public function test_soft_refresh()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/admin/tenants');

            // Test soft refresh from sidebar
            $browser->click('[data-soft-refresh="tenants"]')
                    ->pause(500)
                    ->assertSee('Tenants');

            // Test manual refresh
            $browser->script('window.Tenants.refresh()');
            $browser->pause(500)
                    ->assertSee('Tenants');
        });
    }

    /**
     * Test export functionality
     */
    public function test_export_functionality()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/admin/tenants');

            // Test export all
            $browser->click('#export-all-btn')
                    ->pause(1000)
                    ->assertSee('Export completed');

            // Test export selected
            $browser->check('.tenant-checkbox')
                    ->click('#export-selected-btn')
                    ->pause(1000)
                    ->assertSee('Export completed');
        });
    }
}
