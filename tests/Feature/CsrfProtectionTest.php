<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);
    }

    /**
     * Test CSRF protection on login form
     */
    public function test_login_form_requires_csrf_token(): void
    {
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    /**
     * Test CSRF protection on project creation
     */
    public function test_project_creation_requires_csrf_token(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/projects', [
            'name' => 'Test Project',
            'description' => 'Test Description'
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    /**
     * Test CSRF protection on task creation
     */
    public function test_task_creation_requires_csrf_token(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/tasks', [
            'title' => 'Test Task',
            'description' => 'Test Description'
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    /**
     * Test CSRF protection on document upload
     */
    public function test_document_upload_requires_csrf_token(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/documents', [
            'title' => 'Test Document',
            'content' => 'Test Content'
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    /**
     * Test CSRF protection on profile update
     */
    public function test_profile_update_requires_csrf_token(): void
    {
        $this->actingAs($this->user);

        $response = $this->put('/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com'
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    /**
     * Test successful form submission with CSRF token
     */
    public function test_form_submission_with_csrf_token_succeeds(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/projects/create');
        $csrfToken = $this->extractCsrfToken($response->getContent());

        $response = $this->post('/projects', [
            '_token' => $csrfToken,
            'name' => 'Test Project',
            'description' => 'Test Description'
        ]);

        $response->assertStatus(302); // Redirect after successful creation
    }

    /**
     * Extract CSRF token from HTML content
     */
    private function extractCsrfToken(string $content): ?string
    {
        preg_match('/name="_token" value="([^"]+)"/', $content, $matches);
        return $matches[1] ?? null;
    }

    /**
     * Test CSRF token is present in forms
     */
    public function test_csrf_token_present_in_forms(): void
    {
        $this->actingAs($this->user);

        $response = $this->get('/projects/create');
        $response->assertSee('name="_token"');

        $response = $this->get('/tasks/create');
        $response->assertSee('name="_token"');

        $response = $this->get('/documents/create');
        $response->assertSee('name="_token"');
    }
}
