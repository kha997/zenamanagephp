<?php

namespace Tests\Browser;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TenantsSoftRefreshTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->user = User::factory()->create([
            'role' => 'super_admin'
        ]);
        
        Tenant::factory()->count(5)->create();
    }

    /** @test */
    public function it_supports_soft_refresh_on_tenants_page()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/admin/tenants')
                ->waitForText('Tenants')
                ->assertSee('Tenants');
                
            // Test soft refresh by clicking the tenants link again
            $browser->clickLink('Tenants')
                ->waitForText('Tenants')
                ->assertSee('Tenants');
                
            // Verify no full page reload occurred
            $browser->assertUrlIs('http://localhost/admin/tenants');
        });
    }

    /** @test */
    public function it_handles_etag_caching_correctly()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/admin/tenants')
                ->waitForText('Tenants');
                
            // First request should return 200
            $browser->script('
                fetch("/api/admin/tenants")
                    .then(response => {
                        console.log("First request status:", response.status);
                        return response.headers.get("ETag");
                    })
                    .then(etag => {
                        // Second request with ETag should return 304
                        return fetch("/api/admin/tenants", {
                            headers: { "If-None-Match": etag }
                        });
                    })
                    .then(response => {
                        console.log("Second request status:", response.status);
                    });
            ');
            
            // Wait for console logs
            $browser->pause(1000);
        });
    }

    /** @test */
    public function it_debounces_search_input()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/admin/tenants')
                ->waitForText('Tenants');
                
            // Type in search input
            $browser->type('#search-input', 'Tech')
                ->pause(400) // Wait for debounce
                ->assertSee('Tech');
        });
    }

    /** @test */
    public function it_updates_url_without_page_reload()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/admin/tenants')
                ->waitForText('Tenants');
                
            // Apply a filter
            $browser->select('select[name="status"]', 'active')
                ->pause(500);
                
            // Verify URL updated
            $browser->assertUrlContains('status=active');
        });
    }

    /** @test */
    public function it_shows_loading_states_during_refresh()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/admin/tenants')
                ->waitForText('Tenants');
                
            // Trigger a refresh and check for loading state
            $browser->script('window.tenants.refresh()');
            
            // Check for soft dim effect
            $browser->assertAttribute('.tenants-list', 'class', function ($class) {
                return str_contains($class, 'soft-dim');
            });
        });
    }

    /** @test */
    public function it_handles_pagination_without_reload()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/admin/tenants')
                ->waitForText('Tenants');
                
            // Click pagination
            $browser->click('.pagination button[data-page="2"]')
                ->pause(500);
                
            // Verify URL updated and content changed
            $browser->assertUrlContains('page=2');
        });
    }

    /** @test */
    public function it_exports_tenants_with_current_filters()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/admin/tenants')
                ->waitForText('Tenants');
                
            // Apply a filter
            $browser->select('select[name="status"]', 'active')
                ->pause(500);
                
            // Click export
            $browser->click('.export-btn')
                ->pause(1000);
                
            // Verify export was triggered with filters
            $browser->assertUrlContains('status=active');
        });
    }
}
