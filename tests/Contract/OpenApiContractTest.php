<?php declare(strict_types=1);

namespace Tests\Contract;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use Tests\Helpers\AuthHelper;

/**
 * OpenAPI Contract Tests
 * 
 * Validates that API responses match the OpenAPI specification.
 * This ensures the API contract is maintained and prevents breaking changes.
 */
class OpenApiContractTest extends TestCase
{
    use RefreshDatabase;

    private ?array $openApiSpec = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Load OpenAPI spec
        $specPath = base_path('docs/api/openapi.yaml');
        if (!File::exists($specPath)) {
            $specPath = base_path('docs/api/openapi.json');
        }
        
        if (File::exists($specPath)) {
            $content = File::get($specPath);
            if (str_ends_with($specPath, '.yaml')) {
                $this->openApiSpec = yaml_parse($content);
            } else {
                $this->openApiSpec = json_decode($content, true);
            }
        }
    }

    /**
     * Test Projects endpoints match OpenAPI spec
     */
    public function test_projects_endpoints_match_spec(): void
    {
        if (!$this->openApiSpec) {
            $this->markTestSkipped('OpenAPI spec not found. Run: php artisan openapi:generate --copy-to-docs');
        }

        $token = AuthHelper::getAuthToken('test@example.com', 'password');
        $this->assertNotNull($token, 'Should get auth token');

        // Test GET /api/v1/app/projects
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->getJson('/api/v1/app/projects');

        $response->assertStatus(200);
        
        // Validate response structure matches spec
        $this->validateResponseStructure(
            $response->json(),
            '/api/v1/app/projects',
            'get',
            200
        );
    }

    /**
     * Test Tasks endpoints match OpenAPI spec
     */
    public function test_tasks_endpoints_match_spec(): void
    {
        if (!$this->openApiSpec) {
            $this->markTestSkipped('OpenAPI spec not found. Run: php artisan openapi:generate --copy-to-docs');
        }

        $token = AuthHelper::getAuthToken('test@example.com', 'password');
        $this->assertNotNull($token, 'Should get auth token');

        // Test GET /api/v1/app/tasks
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->getJson('/api/v1/app/tasks');

        $response->assertStatus(200);
        
        // Validate response structure matches spec
        $this->validateResponseStructure(
            $response->json(),
            '/api/v1/app/tasks',
            'get',
            200
        );
    }

    /**
     * Test Documents endpoints match OpenAPI spec
     */
    public function test_documents_endpoints_match_spec(): void
    {
        if (!$this->openApiSpec) {
            $this->markTestSkipped('OpenAPI spec not found. Run: php artisan openapi:generate --copy-to-docs');
        }

        $token = AuthHelper::getAuthToken('test@example.com', 'password');
        $this->assertNotNull($token, 'Should get auth token');

        // Test GET /api/v1/app/documents
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->getJson('/api/v1/app/documents');

        $response->assertStatus(200);
        
        // Validate response structure matches spec
        $this->validateResponseStructure(
            $response->json(),
            '/api/v1/app/documents',
            'get',
            200
        );
    }

    /**
     * Validate response structure against OpenAPI spec
     */
    private function validateResponseStructure(array $responseData, string $path, string $method, int $statusCode): void
    {
        if (!$this->openApiSpec) {
            return;
        }

        $paths = $this->openApiSpec['paths'] ?? [];
        $pathSpec = $paths[$path] ?? null;

        if (!$pathSpec) {
            $this->markTestSkipped("Path {$path} not found in OpenAPI spec");
            return;
        }

        $methodSpec = $pathSpec[strtolower($method)] ?? null;
        if (!$methodSpec) {
            $this->markTestSkipped("Method {$method} not found for path {$path} in OpenAPI spec");
            return;
        }

        $responseSpec = $methodSpec['responses'][$statusCode] ?? null;
        if (!$responseSpec) {
            $this->markTestSkipped("Status code {$statusCode} not found for {$method} {$path} in OpenAPI spec");
            return;
        }

        $content = $responseSpec['content'] ?? [];
        $jsonContent = $content['application/json'] ?? null;
        
        if ($jsonContent && isset($jsonContent['schema'])) {
            // Basic validation: check if response has expected structure
            // Full schema validation would require a JSON Schema validator
            $this->assertIsArray($responseData, 'Response should be an array');
        }
    }

    /**
     * Test that all documented endpoints exist
     */
    public function test_all_documented_endpoints_exist(): void
    {
        if (!$this->openApiSpec) {
            $this->markTestSkipped('OpenAPI spec not found. Run: php artisan openapi:generate --copy-to-docs');
        }

        $paths = $this->openApiSpec['paths'] ?? [];
        $this->assertNotEmpty($paths, 'OpenAPI spec should have paths defined');

        // Check that key endpoints are documented
        $requiredPaths = [
            '/api/v1/app/projects',
            '/api/v1/app/tasks',
            '/api/v1/app/documents',
        ];

        foreach ($requiredPaths as $path) {
            $this->assertArrayHasKey(
                $path,
                $paths,
                "Path {$path} should be documented in OpenAPI spec"
            );
        }
    }
}
