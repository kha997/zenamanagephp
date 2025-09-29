<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Models\User;

class DashboardSoftRefreshTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test admin user
        $this->user = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password')
        ]);
    }

    /**
     * Test dashboard loads without global overlay
     */
    public function test_dashboard_loads_without_global_overlay(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin')
                    ->waitFor('.space-y-6', 5)
                    ->assertMissing('.global-overlay')
                    ->assertMissing('[aria-busy="true"]')
                    ->assertPresent('.min-h-chart')
                    ->assertPresent('.min-h-table');
        });
    }

    /**
     * Test dashboard KPI cards have data-testid attributes
     */
    public function test_kpi_cards_have_testid_attributes(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin')
                    ->waitFor('.space-y-6', 5)
                    ->assertPresent('[data-testid="kpi-tenants"]')
                    ->assertPresent('[data-testid="kpi-users"]')
                    ->assertPresent('[data-testid="kpi-errors"]')
                    ->assertPresent('[data-testid="kpi-queue"]')
                    ->assertPresent('[data-testid="kpi-storage"]');
        });
    }

    /**
     * Test sparklines render in KPI cards
     */
    public function test_sparklines_render_in_kpi_cards(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin')
                    ->waitFor('canvas[id*="Sparkline"]', 5)
                    ->assertPresent('#tenantsSparkline')
                    ->assertPresent('#usersSparkline')
                    ->assertPresent('#errorsSparkline')
                    ->assertPresent('#queueSparkline')
                    ->assertPresent('#storageSparkline');
        });
    }

    /**
     * Test dashboard charts have proper accessibility attributes
     */
    public function test_charts_have_accessibility_attributes(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin')
                    ->waitFor('#charts-section', 5)
                    ->assertAttribute('#charts-section', 'role', 'img')
                    ->assertAttribute('#signupsChart', 'role', 'img')
                    ->assertAttribute('#errorsChart', 'role', 'img')
                    ->assertAttribute('#signupsChart', 'aria-label')
                    ->assertAttribute('#errorsChart', 'aria-label');
        });
    }

    /**
     * Test dashboard soft refresh activates on refresh button
     */
    public function test_refresh_button_triggers_soft_refresh(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin')
                    ->waitFor('.space-y-6', 5)
                    ->click('[data-testid="refresh-data"]')
                    
                    // Should show loading state but not global overlay
                    ->assertMissing('.global-overlay')
                    ->assertPresent('.soft-dim')
                    
                    // Wait for refresh to complete
                    ->waitUntilMissing('.soft-dim', 5)
                    ->assertMissing('.soft-dim');
        });
    }

    /**
     * Test dashboard can export charts
     */
    public function test_chart_export_functionality(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin')
                    ->waitFor('#charts-section', 5)
                    ->click('[data-export="signups"]')
                    
                    // Wait for export to complete
                    ->pause(1000)
                    
                    // Button should not remain disabled
                    ->assertAttribute('[data-export="signups"]', 'disabled', false);
        });
    }

    /**
     * Test dashboard activity section has proper ARIA live regions
     */
    public function test_activity_section_has_aria_live(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin')
                    ->waitFor('#activity-section', 5)
                    ->assertAttribute('#activity-section', 'aria-live', 'polite')
                    ->assertAttribute('#activity-section', 'role', 'log');
        });
    }

    /**
     * Test soft refresh shows proper timestamps
     */
    public function test_refresh_up<｜tool▁sep｜>timestamp(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin')
                    ->waitFor('.refresh-indicator', 5)
                    ->assertVisible('.refresh-indicator')
                    ->click('[data-testid="refresh-data"]')
                    
                    // Wait for refresh to complete
                    ->waitUntilMissing('.soft-dim', 5)
                    
                    // Check timestamp updated
                    ->assertVisible('.refresh-indicator')
                    ->assertSee('Last updated:');
        });
    }

    /**
     * Test dashboard handles different timeframe selections
     */
    public function test_chart_timeframe_selection(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin')
                    ->waitFor('#charts-section', 5)
                    
                    // Change signups range
                    ->select('[x-model="signupsRange"]', '90')
                    ->pause(500)
                    
                    // Change errors range  
                    ->select('[x-model="errorsRange"]', '7')
                    ->pause(500)
                    
                    // Charts should still be visible
                    ->assertPresent('#signupsChart')
                    ->assertPresent('#errorsChart');
        });
    }

    /**
     * Test dashboard responsive layout
     */
    public function test_dashboard_responsive_layout(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin')
                    ->waitFor('.space-y-6', 5)
                    
                    // Test mobile viewport
                    ->resize(375, 667)
                    ->assertVisible('#kpi-strip')
                    ->assertVisible('#charts-section')
                    ->assertVisible('#activity-section')
                    
                    // Test desktop viewport
                    ->resize(1920, 1080)
                    ->assertVisible('#kpi-strip')
                    ->assertVisible('#charts-section')
                    ->assertVisible('#activity-section');
        });
    }

    /**
     * Test dashboard accessibility with keyboard navigation
     */
    public function test_dashboard_keyboard_navigation(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin')
                    ->waitFor('.space-y-6', 5)
                    
                    // Tab through KPI cards
                    ->keys('', ['{tab}'])
                    ->assertFocused('[data-testid="kpi-tenants"]')
                    
                    ->keys('', ['{tab}'])
                    ->assertFocused('[data-testid="kpi-users"]')
                    
                    ->keys('', ['{tab}']) 
                    ->assertFocused('[data-testid="kpi-errors"]')
                    
                    // Test Enter key activation
                    ->keys('[data-testid="kpi-tenants"]', ['{enter}'])
                    ->pause(500);
        });
    }

    /**
     * Test error handling and graceful fallbacks
     */
    public function test_dashboard_error_handling(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin')
                    ->waitFor('.space-y-6', 5)
                    
                    // Disable JavaScript network requests to simulate error
                    $browser->script("window.fetch = () => Promise.reject(new Error('Network error'));")
                    
                    ->click('[data-testid="refresh-data"]')
                    ->pause(1000)
                    
                    // Should handle error gracefully
                    ->assertNotPresent('.global-overlay')
                    ->assertVisible('.refresh-indicator');
        });
    }

    /**
     * Test performance - charts should render smoothly
     */
    public function test_dashboard_performance(): void
    {
        $this->browse(function (Browser $browser) {
            $startTime = microtime(true);
            
            $browser->visit('/admin')
                    ->waitFor('.space-y-6', 10)
                    ->waitFor('canvas[id*="Sparkline"]', 5)
                    ->assertPresent('canvas[id*="Sparkline"]');
            
            $loadTime = microtime(true) - $startTime;
            
            // Dashboard should load within 3 seconds
            $this->assertLessThan(3.0, $loadTime, 'Dashboard load time should be under 3 seconds');
        });
    }

    /**
     * Test dark mode and high contrast support
     */
    public function test_dashboard_theme_support(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/admin')
                    ->waitFor('.space-y-6', 5)
                    
                    // Simulate high contrast mode
                    $browser->script("document.body.style.filter = 'contrast(2)';")
                    
                    // Content should still be accessible
                    ->assertVisible('#kpi-strip')
                    ->assertVisible('#charts-section')
                    ->assertVisible('#activity-section')
                    
 hover
                    // Test reduced motion support
                    $browser->script("document.body.style.setProperty('--transition', 'none');")
                    
                    ->click('[data-testid="refresh-data"]')
                    ->pause(500)
                    
                    // Updates should still work
                    ->assertVisible('.refresh-indicator');
        });
    }
}
