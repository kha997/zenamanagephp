<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Web Routes Return Views Test
 * 
 * Verifies all Web routes return views (not JSON):
 * - All GET routes should return View instances
 * - No JSON responses from Web controllers
 * - Proper view names and data passing
 * 
 * @group web-routes
 */
class WebRoutesReturnViewsTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected User $user;
    protected Tenant $tenant;
    protected Project $project;
    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDomainSeed(88888);
        $this->setDomainName('web-routes');
        $this->setupDomainIsolation();

        $this->tenant = TestDataSeeder::createTenant();
        $this->storeTestData('tenant', $this->tenant);

        $this->user = TestDataSeeder::createUser($this->tenant, [
            'name' => 'Test User',
            'email' => 'user@web-routes-test.test',
            'role' => 'admin',
            'password' => Hash::make('password123'),
        ]);

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
        ]);

        $this->actingAs($this->user);
    }

    /**
     * Test projects index returns view
     */
    public function test_projects_index_returns_view(): void
    {
        $response = $this->get('/app-legacy/projects');

        $response->assertStatus(200);
        $response->assertViewIs('app.projects.index');
        $this->assertFalse($response->headers->contains('Content-Type', 'application/json'));
    }

    /**
     * Test projects show returns view
     */
    public function test_projects_show_returns_view(): void
    {
        $response = $this->get("/app-legacy/projects/{$this->project->id}");

        $response->assertStatus(200);
        $response->assertViewIs('app.projects.show');
        $this->assertFalse($response->headers->contains('Content-Type', 'application/json'));
    }

    /**
     * Test tasks index returns view
     */
    public function test_tasks_index_returns_view(): void
    {
        $response = $this->get('/app-legacy/tasks');

        $response->assertStatus(200);
        $response->assertViewIs('app.tasks.index');
        $this->assertFalse($response->headers->contains('Content-Type', 'application/json'));
    }

    /**
     * Test tasks show returns view
     */
    public function test_tasks_show_returns_view(): void
    {
        $response = $this->get("/app-legacy/tasks/{$this->task->id}");

        $response->assertStatus(200);
        $response->assertViewIs('app.tasks.show');
        $this->assertFalse($response->headers->contains('Content-Type', 'application/json'));
    }

    /**
     * Test tasks kanban returns view
     */
    public function test_tasks_kanban_returns_view(): void
    {
        $response = $this->get('/app-legacy/tasks/kanban');

        $response->assertStatus(200);
        $response->assertViewIs('app.tasks.kanban');
        $this->assertFalse($response->headers->contains('Content-Type', 'application/json'));
    }

    /**
     * Test clients index returns view
     */
    public function test_clients_index_returns_view(): void
    {
        $response = $this->get('/app-legacy/clients');

        $response->assertStatus(200);
        $response->assertViewIs('app.clients.index');
        $this->assertFalse($response->headers->contains('Content-Type', 'application/json'));
    }

    /**
     * Test quotes index returns view
     */
    public function test_quotes_index_returns_view(): void
    {
        $response = $this->get('/app-legacy/quotes');

        $response->assertStatus(200);
        $response->assertViewIs('app.quotes.index');
        $this->assertFalse($response->headers->contains('Content-Type', 'application/json'));
    }

    /**
     * Test documents create returns view
     */
    public function test_documents_create_returns_view(): void
    {
        $response = $this->get('/app-legacy/documents/create');

        $response->assertStatus(200);
        $response->assertViewIs('documents.create');
        $this->assertFalse($response->headers->contains('Content-Type', 'application/json'));
    }

    /**
     * Test Web routes do not return JSON
     */
    public function test_web_routes_do_not_return_json(): void
    {
        $routes = [
            '/app-legacy/projects',
            '/app-legacy/tasks',
            '/app-legacy/clients',
            '/app-legacy/quotes',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            
            $this->assertFalse(
                $response->headers->contains('Content-Type', 'application/json'),
                "Route {$route} should not return JSON"
            );
            
            // Verify response is HTML (view)
            $contentType = $response->headers->get('Content-Type');
            $this->assertStringContainsString('text/html', $contentType, "Route {$route} should return HTML");
        }
    }

    /**
     * Test Web routes return proper view names
     */
    public function test_web_routes_return_proper_view_names(): void
    {
        $routeViewMap = [
            '/app-legacy/projects' => 'app.projects.index',
            "/app-legacy/projects/{$this->project->id}" => 'app.projects.show',
            '/app-legacy/tasks' => 'app.tasks.index',
            "/app-legacy/tasks/{$this->task->id}" => 'app.tasks.show',
            '/app-legacy/tasks/kanban' => 'app.tasks.kanban',
            '/app-legacy/clients' => 'app.clients.index',
            '/app-legacy/quotes' => 'app.quotes.index',
        ];

        foreach ($routeViewMap as $route => $expectedView) {
            $response = $this->get($route);
            
            $response->assertStatus(200);
            $response->assertViewIs($expectedView);
        }
    }

    /**
     * Test Web routes pass data to views
     */
    public function test_web_routes_pass_data_to_views(): void
    {
        // Test projects index passes data
        $response = $this->get('/app-legacy/projects');
        $response->assertViewHas('projects');
        $response->assertViewHas('filters');

        // Test tasks index passes data
        $response = $this->get('/app-legacy/tasks');
        $response->assertViewHas('tasks');
        $response->assertViewHas('filters');

        // Test project show passes data
        $response = $this->get("/app-legacy/projects/{$this->project->id}");
        $response->assertViewHas('project');

        // Test task show passes data
        $response = $this->get("/app-legacy/tasks/{$this->task->id}");
        $response->assertViewHas('task');
    }

    /**
     * Test POST routes redirect after action (not return JSON)
     */
    public function test_post_routes_redirect_after_action(): void
    {
        // Note: This test may need adjustment based on actual POST routes
        // Most POST routes should redirect after successful action
        
        // Example: If there's a task creation route
        // $response = $this->post('/app-legacy/tasks', [...]);
        // $response->assertRedirect();
        // $this->assertFalse($response->headers->contains('Content-Type', 'application/json'));
        
        $this->markTestSkipped('POST routes test - adjust based on actual routes');
    }

    /**
     * Test Web controllers extend Controller (not BaseApiV1Controller)
     */
    public function test_web_controllers_extend_controller(): void
    {
        $webControllers = [
            \App\Http\Controllers\Web\ProjectsController::class,
            \App\Http\Controllers\Web\TasksController::class,
            \App\Http\Controllers\Web\UsersController::class,
            \App\Http\Controllers\Web\SubtasksController::class,
            \App\Http\Controllers\Web\TaskCommentsController::class,
            \App\Http\Controllers\Web\TaskAttachmentsController::class,
        ];

        foreach ($webControllers as $controllerClass) {
            $reflection = new \ReflectionClass($controllerClass);
            $parent = $reflection->getParentClass();
            
            $this->assertTrue(
                $parent->getName() === \App\Http\Controllers\Controller::class ||
                $parent->getName() === \Illuminate\Routing\Controller::class,
                "Controller {$controllerClass} should extend Controller, not BaseApiV1Controller"
            );
            
            $this->assertNotEquals(
                \App\Http\Controllers\Api\V1\BaseApiV1Controller::class,
                $parent->getName(),
                "Controller {$controllerClass} should not extend BaseApiV1Controller"
            );
        }
    }
}

