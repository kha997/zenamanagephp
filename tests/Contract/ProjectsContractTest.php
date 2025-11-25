<?php declare(strict_types=1);

namespace Tests\Contract;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

/**
 * Projects API Contract Tests
 * 
 * Verifies that Projects API responses match the OpenAPI specification.
 */
class ProjectsContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ?array $openApiSpec = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'tenant_id' => 'test_tenant_' . uniqid(),
        ]);
        
        $this->loadOpenApiSpec();
    }

    /**
     * Load OpenAPI specification
     */
    private function loadOpenApiSpec(): void
    {
        $specPath = base_path('docs/api/openapi.json');
        if (!File::exists($specPath)) {
            $specPath = base_path('docs/api/openapi.yaml');
        }

        if (!File::exists($specPath)) {
            $this->markTestSkipped('OpenAPI spec not found');
            return;
        }

        $specContent = File::get($specPath);
        $this->openApiSpec = json_decode($specContent, true);
    }

    /**
     * Test GET /api/v1/app/projects response structure
     */
    public function test_get_projects_response_structure(): void
    {
        Project::factory()->count(3)->create([
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/app/projects');

        $response->assertStatus(200);
        
        // Check response structure matches OpenAPI spec
        $data = $response->json();
        
        // Should have success, data, and meta fields
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        
        // Data should be an array
        $this->assertIsArray($data['data']);
        
        // Each project should have required fields
        if (!empty($data['data'])) {
            $project = $data['data'][0];
            $this->assertArrayHasKey('id', $project);
            $this->assertArrayHasKey('name', $project);
            $this->assertArrayHasKey('code', $project);
        }
    }

    /**
     * Test POST /api/v1/app/projects response structure
     */
    public function test_create_project_response_structure(): void
    {
        $projectData = [
            'name' => 'Test Project',
            'code' => 'TEST-' . uniqid(),
            'description' => 'Test description',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeader('Idempotency-Key', 'test_' . uniqid())
            ->postJson('/api/v1/app/projects', $projectData);

        $response->assertStatus(201);
        
        $data = $response->json();
        
        // Should have success and data fields
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        
        // Project data should have required fields
        $project = $data['data'];
        $this->assertArrayHasKey('id', $project);
        $this->assertArrayHasKey('name', $project);
        $this->assertArrayHasKey('code', $project);
    }

    /**
     * Test GET /api/v1/app/projects/{id} response structure
     */
    public function test_get_project_response_structure(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/app/projects/{$project->id}");

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Should have success and data fields
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        
        // Project should have required fields
        $projectData = $data['data'];
        $this->assertArrayHasKey('id', $projectData);
        $this->assertArrayHasKey('name', $projectData);
    }
}

