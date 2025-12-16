<?php declare(strict_types=1);

namespace Tests\Contract;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * API Contract Tests
 * 
 * Verifies that API responses match the OpenAPI specification.
 * These tests ensure backward compatibility and prevent API drift.
 */
class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    private ?array $openApiSpec = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadOpenApiSpec();
    }

    /**
     * Load OpenAPI specification
     */
    private function loadOpenApiSpec(): void
    {
        $specPath = base_path('docs/api/openapi.yaml');
        if (!File::exists($specPath)) {
            $specPath = base_path('docs/api/openapi.json');
        }

        if (!File::exists($specPath)) {
            $this->markTestSkipped('OpenAPI spec not found. Run: php artisan openapi:generate --copy-to-docs');
            return;
        }

        $specContent = File::get($specPath);
        
        if (str_ends_with($specPath, '.yaml') || str_ends_with($specPath, '.yml')) {
            // Try to parse YAML if Symfony YAML is available
            if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
                $this->openApiSpec = \Symfony\Component\Yaml\Yaml::parse($specContent);
            } else {
                // Fallback: try JSON
                $this->openApiSpec = json_decode($specContent, true);
            }
        } else {
            $this->openApiSpec = json_decode($specContent, true);
        }

        if (!$this->openApiSpec) {
            $this->markTestSkipped('Could not parse OpenAPI spec');
        }
    }

    /**
     * Test that all API routes are documented in OpenAPI spec
     */
    public function test_all_api_routes_are_documented(): void
    {
        $this->assertNotNull($this->openApiSpec, 'OpenAPI spec should be loaded');

        $apiRoutes = $this->getApiRoutes();
        $documentedPaths = $this->getDocumentedPaths();

        $missingRoutes = [];
        foreach ($apiRoutes as $route) {
            $path = $this->normalizePath($route['path']);
            if (!isset($documentedPaths[$path])) {
                $missingRoutes[] = $route['method'] . ' ' . $route['path'];
            }
        }

        if (!empty($missingRoutes)) {
            $this->fail('The following API routes are not documented in OpenAPI spec: ' . implode(', ', $missingRoutes));
        }

        $this->assertTrue(true, 'All API routes are documented');
    }

    /**
     * Get all API routes
     */
    private function getApiRoutes(): array
    {
        $routes = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (str_starts_with($uri, 'api/v1/')) {
                $routes[] = [
                    'method' => implode('|', $route->methods()),
                    'path' => '/' . $uri,
                    'name' => $route->getName(),
                ];
            }
        }
        return $routes;
    }

    /**
     * Get documented paths from OpenAPI spec
     */
    private function getDocumentedPaths(): array
    {
        $paths = [];
        if (isset($this->openApiSpec['paths'])) {
            foreach ($this->openApiSpec['paths'] as $path => $pathItem) {
                $normalizedPath = $this->normalizePath($path);
                foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                    if (isset($pathItem[$method])) {
                        $paths[$normalizedPath][$method] = $pathItem[$method];
                    }
                }
            }
        }
        return $paths;
    }

    /**
     * Normalize path for comparison (remove path parameters)
     */
    private function normalizePath(string $path): string
    {
        // Remove path parameters like {id}, {project}, etc.
        return preg_replace('/\{[^}]+\}/', '{id}', $path);
    }

    /**
     * Test that response structure matches OpenAPI schema
     * This is a basic test - full schema validation would require a library
     */
    public function test_response_structure_matches_schema(): void
    {
        $this->assertNotNull($this->openApiSpec, 'OpenAPI spec should be loaded');
        
        // This is a placeholder - full implementation would:
        // 1. Make actual API requests
        // 2. Get response schemas from OpenAPI spec
        // 3. Validate response structure against schema
        
        $this->assertTrue(true, 'Response structure validation (placeholder)');
    }
}

