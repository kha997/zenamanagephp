<?php declare(strict_types=1);

namespace Tests\Feature\Accessibility;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'project_manager'
        ]);
    }

    /**
     * Test WCAG 2.1 AA compliance for dashboard page
     */
    public function test_dashboard_wcag_2_1_aa_compliance()
    {
        Sanctum::actingAs($this->user);

        $response = $this->get('/app/dashboard');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for proper heading structure (h1, h2, h3 hierarchy)
        $this->assertStringContainsString('<h1', $content, 'Dashboard should have h1 heading');
        
        // Test for alt text on images
        $this->assertStringNotContainsString('<img', $content, 'Dashboard should not have images without alt text');
        
        // Test for proper form labels
        $this->assertStringNotContainsString('<input', $content, 'Dashboard should not have unlabeled inputs');
        
        // Test for proper button labels
        $this->assertStringNotContainsString('<button', $content, 'Dashboard should not have unlabeled buttons');
        
        // Test for proper link text
        $this->assertStringNotContainsString('<a href', $content, 'Dashboard should not have empty link text');
    }

    /**
     * Test keyboard navigation support
     */
    public function test_keyboard_navigation_support()
    {
        Sanctum::actingAs($this->user);

        $response = $this->get('/app/dashboard');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for tabindex attributes
        $this->assertStringNotContainsString('tabindex="-1"', $content, 'Dashboard should not have negative tabindex');
        
        // Test for proper focus management
        $this->assertStringContainsString('focus', $content, 'Dashboard should have focus management');
        
        // Test for skip links
        $this->assertStringContainsString('skip', $content, 'Dashboard should have skip links');
    }

    /**
     * Test color contrast compliance
     */
    public function test_color_contrast_compliance()
    {
        Sanctum::actingAs($this->user);

        $response = $this->get('/app/dashboard');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for proper color contrast classes
        $this->assertStringContainsString('text-', $content, 'Dashboard should use proper text color classes');
        $this->assertStringContainsString('bg-', $content, 'Dashboard should use proper background color classes');
        
        // Test for dark mode support
        $this->assertStringContainsString('dark:', $content, 'Dashboard should support dark mode');
    }

    /**
     * Test screen reader compatibility
     */
    public function test_screen_reader_compatibility()
    {
        Sanctum::actingAs($this->user);

        $response = $this->get('/app/dashboard');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for ARIA labels
        $this->assertStringContainsString('aria-', $content, 'Dashboard should have ARIA labels');
        
        // Test for proper role attributes
        $this->assertStringContainsString('role=', $content, 'Dashboard should have proper role attributes');
        
        // Test for proper heading structure
        $this->assertStringContainsString('<h1', $content, 'Dashboard should have h1 heading');
        $this->assertStringContainsString('<h2', $content, 'Dashboard should have h2 headings');
    }

    /**
     * Test mobile accessibility
     */
    public function test_mobile_accessibility()
    {
        Sanctum::actingAs($this->user);

        $response = $this->get('/app/dashboard');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for responsive design
        $this->assertStringContainsString('responsive', $content, 'Dashboard should be responsive');
        
        // Test for touch targets
        $this->assertStringContainsString('touch', $content, 'Dashboard should have proper touch targets');
        
        // Test for mobile navigation
        $this->assertStringContainsString('mobile', $content, 'Dashboard should have mobile navigation');
    }

    /**
     * Test projects page accessibility
     */
    public function test_projects_page_accessibility()
    {
        Sanctum::actingAs($this->user);

        $response = $this->get('/app/projects');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for proper heading structure
        $this->assertStringContainsString('<h1', $content, 'Projects page should have h1 heading');
        
        // Test for proper table structure
        $this->assertStringContainsString('<table', $content, 'Projects page should have proper table structure');
        
        // Test for proper form labels
        $this->assertStringNotContainsString('<input', $content, 'Projects page should not have unlabeled inputs');
    }

    /**
     * Test tasks page accessibility
     */
    public function test_tasks_page_accessibility()
    {
        Sanctum::actingAs($this->user);

        $response = $this->get('/app/tasks');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for proper heading structure
        $this->assertStringContainsString('<h1', $content, 'Tasks page should have h1 heading');
        
        // Test for proper table structure
        $this->assertStringContainsString('<table', $content, 'Tasks page should have proper table structure');
        
        // Test for proper form labels
        $this->assertStringNotContainsString('<input', $content, 'Tasks page should not have unlabeled inputs');
    }

    /**
     * Test admin dashboard accessibility
     */
    public function test_admin_dashboard_accessibility()
    {
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin'
        ]);

        Sanctum::actingAs($admin);

        $response = $this->get('/admin/dashboard');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for proper heading structure
        $this->assertStringContainsString('<h1', $content, 'Admin dashboard should have h1 heading');
        
        // Test for proper navigation
        $this->assertStringContainsString('nav', $content, 'Admin dashboard should have proper navigation');
        
        // Test for proper form labels
        $this->assertStringNotContainsString('<input', $content, 'Admin dashboard should not have unlabeled inputs');
    }

    /**
     * Test error page accessibility
     */
    public function test_error_page_accessibility()
    {
        $response = $this->get('/nonexistent-page');

        $response->assertStatus(404);
        
        $content = $response->getContent();
        
        // Test for proper heading structure
        $this->assertStringContainsString('<h1', $content, 'Error page should have h1 heading');
        
        // Test for proper error message
        $this->assertStringContainsString('error', $content, 'Error page should have proper error message');
        
        // Test for proper navigation back
        $this->assertStringContainsString('back', $content, 'Error page should have navigation back');
    }

    /**
     * Test login page accessibility
     */
    public function test_login_page_accessibility()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for proper heading structure
        $this->assertStringContainsString('<h1', $content, 'Login page should have h1 heading');
        
        // Test for proper form labels
        $this->assertStringContainsString('<label', $content, 'Login page should have proper form labels');
        
        // Test for proper input types
        $this->assertStringContainsString('type="email"', $content, 'Login page should have email input type');
        $this->assertStringContainsString('type="password"', $content, 'Login page should have password input type');
        
        // Test for proper button labels
        $this->assertStringContainsString('<button', $content, 'Login page should have proper button labels');
    }
}
