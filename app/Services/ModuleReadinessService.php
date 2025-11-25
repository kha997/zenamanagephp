<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

/**
 * ModuleReadinessService
 * 
 * Provides readiness checklists for each module to ensure all components
 * are properly implemented before enabling feature flags.
 */
class ModuleReadinessService
{
    /**
     * Get readiness checklist for a module
     * 
     * @param string $module Module name (e.g., 'projects', 'tasks', 'documents')
     * @return array Readiness checklist with status for each item
     */
    public function getReadinessChecklist(string $module): array
    {
        $checklist = $this->getModuleChecklist($module);
        $results = [];
        
        foreach ($checklist as $item) {
            $results[] = [
                'id' => $item['id'],
                'category' => $item['category'],
                'description' => $item['description'],
                'status' => $this->checkItem($module, $item),
                'details' => $item['details'] ?? null,
            ];
        }
        
        return [
            'module' => $module,
            'total_items' => count($checklist),
            'completed_items' => count(array_filter($results, fn($r) => $r['status'] === 'completed')),
            'pending_items' => count(array_filter($results, fn($r) => $r['status'] === 'pending')),
            'blocking_items' => count(array_filter($results, fn($r) => $r['status'] === 'blocking')),
            'items' => $results,
        ];
    }
    
    /**
     * Check if a module is ready for production
     * 
     * @param string $module
     * @return bool
     */
    public function isModuleReady(string $module): bool
    {
        $checklist = $this->getReadinessChecklist($module);
        
        // Module is ready if:
        // 1. All blocking items are completed
        // 2. At least 80% of items are completed
        $blockingCompleted = $checklist['blocking_items'] === 0;
        $completionRate = $checklist['total_items'] > 0 
            ? ($checklist['completed_items'] / $checklist['total_items']) * 100 
            : 0;
        
        return $blockingCompleted && $completionRate >= 80;
    }
    
    /**
     * Get module-specific checklist
     * 
     * @param string $module
     * @return array
     */
    private function getModuleChecklist(string $module): array
    {
        $baseChecklist = $this->getBaseChecklist();
        $moduleSpecific = $this->getModuleSpecificChecklist($module);
        
        return array_merge($baseChecklist, $moduleSpecific);
    }
    
    /**
     * Base checklist items for all modules
     * 
     * @return array
     */
    private function getBaseChecklist(): array
    {
        return [
            [
                'id' => 'api_endpoints',
                'category' => 'API',
                'description' => 'API endpoints implemented and tested',
                'check' => 'checkApiEndpoints',
                'blocking' => true,
            ],
            [
                'id' => 'database_migrations',
                'category' => 'Database',
                'description' => 'Database migrations created and run',
                'check' => 'checkMigrations',
                'blocking' => true,
            ],
            [
                'id' => 'models',
                'category' => 'Backend',
                'description' => 'Eloquent models created with relationships',
                'check' => 'checkModels',
                'blocking' => true,
            ],
            [
                'id' => 'services',
                'category' => 'Backend',
                'description' => 'Service classes implemented',
                'check' => 'checkServices',
                'blocking' => false,
            ],
            [
                'id' => 'controllers',
                'category' => 'Backend',
                'description' => 'Controllers implemented with proper error handling',
                'check' => 'checkControllers',
                'blocking' => true,
            ],
            [
                'id' => 'routes',
                'category' => 'Routing',
                'description' => 'Routes registered and accessible',
                'check' => 'checkRoutes',
                'blocking' => true,
            ],
            [
                'id' => 'validation',
                'category' => 'Backend',
                'description' => 'Form request validation classes created',
                'check' => 'checkValidation',
                'blocking' => false,
            ],
            [
                'id' => 'policies',
                'category' => 'Security',
                'description' => 'Authorization policies implemented',
                'check' => 'checkPolicies',
                'blocking' => true,
            ],
            [
                'id' => 'frontend_components',
                'category' => 'Frontend',
                'description' => 'React components created',
                'check' => 'checkFrontendComponents',
                'blocking' => true,
            ],
            [
                'id' => 'frontend_pages',
                'category' => 'Frontend',
                'description' => 'Frontend pages implemented',
                'check' => 'checkFrontendPages',
                'blocking' => true,
            ],
            [
                'id' => 'i18n',
                'category' => 'Internationalization',
                'description' => 'Translation keys added to lang files',
                'check' => 'checkI18n',
                'blocking' => false,
            ],
            [
                'id' => 'unit_tests',
                'category' => 'Testing',
                'description' => 'Unit tests written and passing',
                'check' => 'checkUnitTests',
                'blocking' => false,
            ],
            [
                'id' => 'integration_tests',
                'category' => 'Testing',
                'description' => 'Integration tests written and passing',
                'check' => 'checkIntegrationTests',
                'blocking' => false,
            ],
            [
                'id' => 'e2e_tests',
                'category' => 'Testing',
                'description' => 'E2E tests written and passing',
                'check' => 'checkE2ETests',
                'blocking' => false,
            ],
        ];
    }
    
    /**
     * Get module-specific checklist items
     * 
     * @param string $module
     * @return array
     */
    private function getModuleSpecificChecklist(string $module): array
    {
        $checklists = [
            'projects' => [
                [
                    'id' => 'project_kpis',
                    'category' => 'Features',
                    'description' => 'Project KPI calculations implemented',
                    'check' => 'checkProjectKPIs',
                    'blocking' => false,
                ],
                [
                    'id' => 'project_templates',
                    'category' => 'Features',
                    'description' => 'Project templates functionality',
                    'check' => 'checkProjectTemplates',
                    'blocking' => false,
                ],
            ],
            'tasks' => [
                [
                    'id' => 'task_kanban',
                    'category' => 'Features',
                    'description' => 'Kanban board view implemented',
                    'check' => 'checkTaskKanban',
                    'blocking' => false,
                ],
                [
                    'id' => 'task_transitions',
                    'category' => 'Features',
                    'description' => 'Task status transition validation',
                    'check' => 'checkTaskTransitions',
                    'blocking' => true,
                ],
            ],
            'documents' => [
                [
                    'id' => 'document_upload',
                    'category' => 'Features',
                    'description' => 'Document upload and storage',
                    'check' => 'checkDocumentUpload',
                    'blocking' => true,
                ],
                [
                    'id' => 'document_versioning',
                    'category' => 'Features',
                    'description' => 'Document version control',
                    'check' => 'checkDocumentVersioning',
                    'blocking' => false,
                ],
            ],
        ];
        
        return $checklists[$module] ?? [];
    }
    
    /**
     * Check a specific checklist item
     * 
     * @param string $module
     * @param array $item
     * @return string 'completed', 'pending', or 'blocking'
     */
    private function checkItem(string $module, array $item): string
    {
        $checkMethod = $item['check'] ?? null;
        
        if (!$checkMethod || !method_exists($this, $checkMethod)) {
            return 'pending';
        }
        
        try {
            $result = $this->$checkMethod($module);
            return $result ? 'completed' : ($item['blocking'] ?? false ? 'blocking' : 'pending');
        } catch (\Exception $e) {
            Log::error("Error checking readiness item", [
                'module' => $module,
                'item' => $item['id'],
                'error' => $e->getMessage(),
            ]);
            return 'pending';
        }
    }
    
    // Check methods
    
    private function checkApiEndpoints(string $module): bool
    {
        $routes = Route::getRoutes();
        $moduleRoutes = collect($routes)->filter(function ($route) use ($module) {
            return str_contains($route->uri(), "/{$module}");
        });
        
        return $moduleRoutes->count() > 0;
    }
    
    private function checkMigrations(string $module): bool
    {
        $migrationFiles = File::glob(database_path("migrations/*_{$module}*.php"));
        return count($migrationFiles) > 0;
    }
    
    private function checkModels(string $module): bool
    {
        $modelClass = "App\\Models\\" . ucfirst(str_singular($module));
        return class_exists($modelClass);
    }
    
    private function checkServices(string $module): bool
    {
        $serviceClass = "App\\Services\\" . ucfirst(str_singular($module)) . "Service";
        return class_exists($serviceClass);
    }
    
    private function checkControllers(string $module): bool
    {
        $controllerClass = "App\\Http\\Controllers\\Api\\" . ucfirst(str_plural($module)) . "Controller";
        return class_exists($controllerClass);
    }
    
    private function checkRoutes(string $module): bool
    {
        return $this->checkApiEndpoints($module);
    }
    
    private function checkValidation(string $module): bool
    {
        $requestClass = "App\\Http\\Requests\\" . ucfirst(str_singular($module)) . "Request";
        return class_exists($requestClass);
    }
    
    private function checkPolicies(string $module): bool
    {
        $policyClass = "App\\Policies\\" . ucfirst(str_singular($module)) . "Policy";
        return class_exists($policyClass);
    }
    
    private function checkFrontendComponents(string $module): bool
    {
        $componentPath = base_path("frontend/src/features/{$module}/components");
        return File::exists($componentPath) && count(File::files($componentPath)) > 0;
    }
    
    private function checkFrontendPages(string $module): bool
    {
        $pagePath = base_path("frontend/src/features/{$module}/pages");
        return File::exists($pagePath) && count(File::files($pagePath)) > 0;
    }
    
    private function checkI18n(string $module): bool
    {
        $langFile = lang_path("en/{$module}.php");
        return File::exists($langFile);
    }
    
    private function checkUnitTests(string $module): bool
    {
        $testFile = base_path("tests/Unit/Services/{$module}ServiceTest.php");
        return File::exists($testFile);
    }
    
    private function checkIntegrationTests(string $module): bool
    {
        $testFile = base_path("tests/Feature/Api/{$module}Test.php");
        return File::exists($testFile);
    }
    
    private function checkE2ETests(string $module): bool
    {
        $testFile = base_path("tests/E2E/core/{$module}/{$module}-list.spec.ts");
        return File::exists($testFile);
    }
    
    // Module-specific checks
    
    private function checkProjectKPIs(string $module): bool
    {
        return method_exists(\App\Services\ProjectService::class, 'getKPIs');
    }
    
    private function checkProjectTemplates(string $module): bool
    {
        return DB::table('project_templates')->exists();
    }
    
    private function checkTaskKanban(string $module): bool
    {
        $kanbanComponent = base_path("frontend/src/features/tasks/components/KanbanBoard.tsx");
        return File::exists($kanbanComponent);
    }
    
    private function checkTaskTransitions(string $module): bool
    {
        $serviceClass = "App\\Services\\TaskStatusTransitionService";
        return class_exists($serviceClass);
    }
    
    private function checkDocumentUpload(string $module): bool
    {
        return method_exists(\App\Http\Controllers\Api\DocumentsController::class, 'store');
    }
    
    private function checkDocumentVersioning(string $module): bool
    {
        return DB::table('documents')->whereNotNull('version')->exists() 
            || DB::table('document_versions')->exists();
    }
}

