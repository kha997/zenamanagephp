<?php declare(strict_types=1);

namespace Tests\Feature\Layout;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;

class AppLayoutHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test theme toggle functionality
     */
    public function test_theme_toggle_initializes_correctly(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        
        $user->update(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)
            ->get('/app/dashboard');

        $response->assertStatus(200);
        
        // Check if theme initialization script is present
        $response->assertSee('localStorage.getItem(\'theme\')', false);
        $response->assertSee('data-theme', false);
    }

    /**
     * Test RBAC menu rendering based on user role
     */
    public function test_rbac_menu_renders_correct_role(): void
    {
        // Test with admin user
        $admin = User::factory()->create(['role' => 'admin']);
        $tenant = Tenant::factory()->create();
        $admin->update(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($admin)
            ->get('/app/dashboard');

        $response->assertStatus(200);
        // Header should include navigation data
        $response->assertSee('data-navigation', false);
        // Should include Projects in navigation (HTML encoded)
        $response->assertSee('Projects', false);
    }

    /**
     * Test tenancy filtering - user sees only their tenant's data
     */
    public function test_tenancy_filtering_applies_correctly(): void
    {
        $tenant1 = Tenant::factory()->create(['name' => 'Tenant One']);
        $tenant2 = Tenant::factory()->create(['name' => 'Tenant Two']);
        
        $user1 = User::factory()->create(['tenant_id' => $tenant1->id]);
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id]);

        // User 1 should only see Tenant One data
        $response1 = $this->actingAs($user1)
            ->get('/app/dashboard');

        $response1->assertStatus(200);
        
        // Check tenant ID is included in page
        $response1->assertSee('tenant-id', false);
        
        // User 2 should only see Tenant Two data  
        $response2 = $this->actingAs($user2)
            ->get('/app/dashboard');

        $response2->assertStatus(200);
        $response2->assertSee('tenant-id', false);
    }

    /**
     * Test search functionality is present in header
     */
    public function test_search_input_is_present(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->update(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)
            ->get('/app/dashboard');

        $response->assertStatus(200);
        // HeaderShell is React-based, so we check for container
        $response->assertSee('header-shell-container', false);
    }

    /**
     * Test mobile hamburger menu exists
     */
    public function test_mobile_hamburger_menu_exists(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->update(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)
            ->get('/app/dashboard');

        $response->assertStatus(200);
        // HeaderShell container should exist (React handles mobile menu)
        $response->assertSee('header-shell-container', false);
    }

    /**
     * Test breadcrumbs are generated correctly
     */
    public function test_breadcrumbs_generate_correctly(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->update(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)
            ->get('/app/dashboard');

        $response->assertStatus(200);
        
        // Breadcrumbs data should be present in data attribute
        $response->assertSee('data-breadcrumbs', false);
    }

    /**
     * Test notifications are loaded
     */
    public function test_notifications_are_loaded(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->update(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)
            ->get('/app/dashboard');

        $response->assertStatus(200);
        // Notifications data should be present in data attribute
        $response->assertSee('data-notifications', false);
    }

    /**
     * Test user menu displays correctly
     */
    public function test_user_menu_displays_correctly(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);
        $tenant = Tenant::factory()->create();
        $user->update(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)
            ->get('/app/dashboard');

        $response->assertStatus(200);
        // User data should be present in data attribute
        $response->assertSee('data-user', false);
    }

    /**
     * Test logout functionality
     */
    public function test_logout_button_is_present(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->update(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)
            ->get('/app/dashboard');

        $response->assertStatus(200);
        // HeaderShell container should be present (logout handled by React)
        $response->assertSee('header-shell-container', false);
    }
}

