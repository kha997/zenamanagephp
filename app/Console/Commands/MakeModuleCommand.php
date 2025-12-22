<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Make Module Command
 * 
 * Generates a complete module structure:
 * - Service
 * - Controller (API + Web)
 * - Policy
 * - Routes entry
 * - Test skeleton (Unit + Feature)
 * - OpenAPI spec stub
 * 
 * Usage:
 *   php artisan make:module TasksV2
 */
class MakeModuleCommand extends Command
{
    protected $signature = 'make:module {name : The name of the module}';
    protected $description = 'Generate a complete module structure (Service, Controller, Policy, Routes, Tests)';

    public function handle(): int
    {
        $name = $this->argument('name');
        $pascalName = Str::studly($name);
        $camelName = Str::camel($name);
        $pluralName = Str::plural($camelName);
        $kebabName = Str::kebab($name);
        
        $this->info("Creating module: {$pascalName}");
        
        // Create Service
        $this->createService($pascalName, $camelName);
        
        // Create API Controller
        $this->createApiController($pascalName, $camelName, $pluralName);
        
        // Create Web Controller
        $this->createWebController($pascalName, $camelName, $pluralName);
        
        // Create Policy
        $this->createPolicy($pascalName);
        
        // Create Test skeletons
        $this->createUnitTest($pascalName, $camelName);
        $this->createFeatureTest($pascalName, $camelName, $pluralName, $kebabName);
        
        // Create routes entry
        $this->createRoutesEntry($pascalName, $camelName, $pluralName, $kebabName);
        
        // Create OpenAPI spec stub
        $this->createOpenApiStub($pascalName, $camelName, $pluralName, $kebabName);
        
        $this->info("✅ Module {$pascalName} created successfully!");
        $this->info("📝 Don't forget to:");
        $this->info("   1. Register routes in routes/api_v1.php");
        $this->info("   2. Register policy in app/Providers/AuthServiceProvider.php");
        $this->info("   3. Update OpenAPI spec in docs/api/openapi.yaml");
        $this->info("   4. Add cache invalidation rules in CacheInvalidationService");
        
        return 0;
    }

    private function createService(string $pascalName, string $camelName): void
    {
        $servicePath = app_path("Services/{$pascalName}Service.php");
        
        if (file_exists($servicePath)) {
            $this->warn("Service already exists: {$servicePath}");
            return;
        }
        
        $stub = <<<PHP
<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * {$pascalName} Service
 * 
 * Business logic for {$camelName} operations.
 */
class {$pascalName}Service
{
    public function __construct()
    {
        // Inject dependencies here
    }
    
    /**
     * Get all {$camelName}s
     */
    public function get{$pascalName}s(array \$filters = [], int \$perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        // TODO: Implement
        throw new \Exception('Not implemented');
    }
    
    /**
     * Get {$camelName} by ID
     */
    public function get{$pascalName}(string \$id): ?\App\Models\{$pascalName}
    {
        // TODO: Implement
        throw new \Exception('Not implemented');
    }
    
    /**
     * Create {$camelName}
     */
    public function create{$pascalName}(array \$data, string \$userId, string \$tenantId): \App\Models\{$pascalName}
    {
        // TODO: Implement
        throw new \Exception('Not implemented');
    }
    
    /**
     * Update {$camelName}
     */
    public function update{$pascalName}(string \$id, array \$data, string \$userId, string \$tenantId): \App\Models\{$pascalName}
    {
        // TODO: Implement
        throw new \Exception('Not implemented');
    }
    
    /**
     * Delete {$camelName}
     */
    public function delete{$pascalName}(string \$id, string \$tenantId): bool
    {
        // TODO: Implement
        throw new \Exception('Not implemented');
    }
}
PHP;
        
        file_put_contents($servicePath, $stub);
        $this->info("✅ Created Service: {$servicePath}");
    }

    private function createApiController(string $pascalName, string $camelName, string $pluralName): void
    {
        $controllerPath = app_path("Http/Controllers/Api/V1/App/{$pascalName}Controller.php");
        
        if (file_exists($controllerPath)) {
            $this->warn("API Controller already exists: {$controllerPath}");
            return;
        }
        
        $stub = <<<PHP
<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Services\{$pascalName}Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * {$pascalName} API Controller
 */
class {$pascalName}Controller extends BaseApiV1Controller
{
    public function __construct(
        private {$pascalName}Service \${$camelName}Service
    ) {}
    
    /**
     * Get all {$pluralName}
     */
    public function index(Request \$request): JsonResponse
    {
        // TODO: Implement
        return \$this->successResponse([], '{$pluralName} retrieved successfully');
    }
    
    /**
     * Get {$camelName} by ID
     */
    public function show(string \$id): JsonResponse
    {
        // TODO: Implement
        return \$this->successResponse([], '{$camelName} retrieved successfully');
    }
    
    /**
     * Create {$camelName}
     */
    public function store(Request \$request): JsonResponse
    {
        // TODO: Implement
        return \$this->successResponse([], '{$camelName} created successfully', 201);
    }
    
    /**
     * Update {$camelName}
     */
    public function update(Request \$request, string \$id): JsonResponse
    {
        // TODO: Implement
        return \$this->successResponse([], '{$camelName} updated successfully');
    }
    
    /**
     * Delete {$camelName}
     */
    public function destroy(string \$id): JsonResponse
    {
        // TODO: Implement
        return \$this->successResponse([], '{$camelName} deleted successfully');
    }
}
PHP;
        
        file_put_contents($controllerPath, $stub);
        $this->info("✅ Created API Controller: {$controllerPath}");
    }

    private function createWebController(string $pascalName, string $camelName, string $pluralName): void
    {
        $controllerPath = app_path("Http/Controllers/Web/{$pascalName}Controller.php");
        
        if (file_exists($controllerPath)) {
            $this->warn("Web Controller already exists: {$controllerPath}");
            return;
        }
        
        $stub = <<<PHP
<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\{$pascalName}Service;
use Illuminate\Http\Request;

/**
 * {$pascalName} Web Controller
 * 
 * @deprecated Use React SPA for /app/* routes
 */
class {$pascalName}Controller extends Controller
{
    public function __construct(
        private {$pascalName}Service \${$camelName}Service
    ) {}
    
    // TODO: Implement if needed for legacy Blade views
}
PHP;
        
        file_put_contents($controllerPath, $stub);
        $this->info("✅ Created Web Controller: {$controllerPath}");
    }

    private function createPolicy(string $pascalName): void
    {
        $policyPath = app_path("Policies/{$pascalName}Policy.php");
        
        if (file_exists($policyPath)) {
            $this->warn("Policy already exists: {$policyPath}");
            return;
        }
        
        $stub = <<<PHP
<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\{$pascalName};

/**
 * {$pascalName} Policy
 */
class {$pascalName}Policy
{
    /**
     * Determine if user can view {$pascalName}
     */
    public function view(User \$user, {$pascalName} \${$pascalName}): bool
    {
        // TODO: Implement permission check
        return \$user->tenant_id === \${$pascalName}->tenant_id;
    }
    
    /**
     * Determine if user can create {$pascalName}
     */
    public function create(User \$user): bool
    {
        // TODO: Implement permission check
        return true;
    }
    
    /**
     * Determine if user can update {$pascalName}
     */
    public function update(User \$user, {$pascalName} \${$pascalName}): bool
    {
        // TODO: Implement permission check
        return \$user->tenant_id === \${$pascalName}->tenant_id;
    }
    
    /**
     * Determine if user can delete {$pascalName}
     */
    public function delete(User \$user, {$pascalName} \${$pascalName}): bool
    {
        // TODO: Implement permission check
        return \$user->tenant_id === \${$pascalName}->tenant_id;
    }
}
PHP;
        
        file_put_contents($policyPath, $stub);
        $this->info("✅ Created Policy: {$policyPath}");
    }

    private function createUnitTest(string $pascalName, string $camelName): void
    {
        $testPath = base_path("tests/Unit/Services/{$pascalName}ServiceTest.php");
        
        if (file_exists($testPath)) {
            $this->warn("Unit Test already exists: {$testPath}");
            return;
        }
        
        $stub = <<<PHP
<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\{$pascalName}Service;
use Tests\TestCase;

/**
 * {$pascalName} Service Unit Test
 * 
 * @group {$camelName}
 */
class {$pascalName}ServiceTest extends TestCase
{
    private {$pascalName}Service \$service;
    
    protected function setUp(): void
    {
        parent::setUp();
        \$this->service = app({$pascalName}Service::class);
    }
    
    // TODO: Add unit tests
}
PHP;
        
        file_put_contents($testPath, $stub);
        $this->info("✅ Created Unit Test: {$testPath}");
    }

    private function createFeatureTest(string $pascalName, string $camelName, string $pluralName, string $kebabName): void
    {
        $testPath = base_path("tests/Feature/Api/{$pascalName}/{$pascalName}ControllerTest.php");
        $testDir = dirname($testPath);
        
        if (!is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        
        if (file_exists($testPath)) {
            $this->warn("Feature Test already exists: {$testPath}");
            return;
        }
        
        $stub = <<<PHP
<?php declare(strict_types=1);

namespace Tests\Feature\Api\{$pascalName};

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * {$pascalName} Controller Feature Test
 * 
 * @group {$camelName}
 */
class {$pascalName}ControllerTest extends TestCase
{
    use RefreshDatabase;
    
    private Tenant \$tenant;
    private User \$user;
    
    protected function setUp(): void
    {
        parent::setUp();
        \$this->tenant = Tenant::factory()->create();
        \$this->user = User::factory()->create(['tenant_id' => \$this->tenant->id]);
        Sanctum::actingAs(\$this->user);
    }
    
    // TODO: Add feature tests
}
PHP;
        
        file_put_contents($testPath, $stub);
        $this->info("✅ Created Feature Test: {$testPath}");
    }

    private function createRoutesEntry(string $pascalName, string $camelName, string $pluralName, string $kebabName): void
    {
        $routesPath = base_path("routes/{$kebabName}.php");
        
        if (file_exists($routesPath)) {
            $this->warn("Routes file already exists: {$routesPath}");
            return;
        }
        
        $stub = <<<PHP
<?php

use Illuminate\Support\Facades\Route;

/**
 * {$pascalName} Routes
 * 
 * Register these routes in routes/api_v1.php:
 * 
 * Route::prefix('{$kebabName}')->middleware(['auth:sanctum', 'ability:tenant'])->group(function () {
 *     require __DIR__ . '/{$kebabName}.php';
 * });
 */

Route::get('/', [\App\Http\Controllers\Api\V1\App\{$pascalName}Controller::class, 'index']);
Route::get('/{id}', [\App\Http\Controllers\Api\V1\App\{$pascalName}Controller::class, 'show']);
Route::post('/', [\App\Http\Controllers\Api\V1\App\{$pascalName}Controller::class, 'store']);
Route::put('/{id}', [\App\Http\Controllers\Api\V1\App\{$pascalName}Controller::class, 'update']);
Route::delete('/{id}', [\App\Http\Controllers\Api\V1\App\{$pascalName}Controller::class, 'destroy']);
PHP;
        
        file_put_contents($routesPath, $stub);
        $this->info("✅ Created Routes: {$routesPath}");
    }

    private function createOpenApiStub(string $pascalName, string $camelName, string $pluralName, string $kebabName): void
    {
        $openApiPath = base_path("docs/api/{$kebabName}-openapi.yaml");
        
        if (file_exists($openApiPath)) {
            $this->warn("OpenAPI spec already exists: {$openApiPath}");
            return;
        }
        
        $stub = <<<YAML
# {$pascalName} API OpenAPI Specification
# 
# Add this to docs/api/openapi.yaml

paths:
  /api/v1/app/{$kebabName}:
    get:
      summary: Get all {$pluralName}
      tags:
        - {$pascalName}
      responses:
        '200':
          description: Success
    post:
      summary: Create {$camelName}
      tags:
        - {$pascalName}
      responses:
        '201':
          description: Created
  
  /api/v1/app/{$kebabName}/{id}:
    get:
      summary: Get {$camelName} by ID
      tags:
        - {$pascalName}
      responses:
        '200':
          description: Success
    put:
      summary: Update {$camelName}
      tags:
        - {$pascalName}
      responses:
        '200':
          description: Success
    delete:
      summary: Delete {$camelName}
      tags:
        - {$pascalName}
      responses:
        '200':
          description: Success
YAML;
        
        file_put_contents($openApiPath, $stub);
        $this->info("✅ Created OpenAPI Stub: {$openApiPath}");
    }
}

