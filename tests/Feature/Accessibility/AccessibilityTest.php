<?php declare(strict_types=1);

namespace Tests\Feature\Accessibility;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tenant;
    protected array $sessionTenantData;

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

        $this->actingAs($this->user);
        $this->sessionTenantData = ['tenant_id' => (string) $this->tenant->id];
    }

    /**
     * Test WCAG 2.1 AA compliance for dashboard page
     */
    public function test_dashboard_wcag_2_1_aa_compliance()
    {
        $response = $this->visitAppRoute('/app/dashboard');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for proper heading structure (h1, h2, h3 hierarchy)
        $this->assertStringContainsString('<h1', $content, 'Dashboard should have h1 heading');
        
        // Test for alt text on images
        $this->assertStringContainsString('alt="', $content, 'Dashboard images should have alt text');
        
    }

    /**
     * Test keyboard navigation support
     */
    public function test_keyboard_navigation_support()
    {
        $response = $this->visitAppRoute('/app/dashboard');

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
        $response = $this->visitAppRoute('/app/dashboard');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for proper color contrast classes
        $this->assertStringContainsString('text-', $content, 'Dashboard should use proper text color classes');
        $this->assertStringContainsString('bg-', $content, 'Dashboard should use proper background color classes');
        
    }

    /**
     * Test screen reader compatibility
     */
    public function test_screen_reader_compatibility()
    {
        $response = $this->visitAppRoute('/app/dashboard');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        
        // Test for proper heading structure
        $this->assertStringContainsString('<h1', $content, 'Dashboard should have h1 heading');
        $this->assertStringContainsString('<h2', $content, 'Dashboard should have h2 headings');
    }

    /**
     * Test mobile accessibility
     */
    public function test_mobile_accessibility()
    {
        $response = $this->visitAppRoute('/app/dashboard');

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
        $response = $this->visitAppRoute('/app/projects');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for proper heading structure
        $this->assertStringContainsString('<h1', $content, 'Projects page should have h1 heading');
        
        // Test for proper grid structure
        $this->assertStringContainsString('grid grid-cols', $content, 'Projects page should use a responsive grid layout');
        
    }

    /**
     * Test tasks page accessibility
     */
    public function test_tasks_page_accessibility()
    {
        $response = $this->visitAppRoute('/app/tasks');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for proper heading structure
        $this->assertStringContainsString('<h1', $content, 'Tasks page should have h1 heading');
        
        // Test for proper grid structure
        $this->assertStringContainsString('grid grid-cols', $content, 'Tasks page should use a responsive grid layout');
        
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

        $response = $this->actingAs($admin)
            ->withSession(['tenant_id' => (string) $admin->tenant_id])
            ->get('/admin/dashboard');

        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for proper heading structure
        $this->assertStringContainsString('<h1', $content, 'Admin dashboard should have h1 heading');
        
        // Test for proper navigation
        $this->assertStringContainsString('nav', $content, 'Admin dashboard should have proper navigation');
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

    protected function visitAppRoute(string $uri): TestResponse
    {
        return $this->withSession($this->sessionTenantData)->get($uri);
    }
}
