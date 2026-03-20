<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class ModuleOwnershipSourceInvariantTest extends TestCase
{
    public function test_projects_canonical_controller_uses_app_project_model_not_legacy_adapter(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/ProjectController.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('use App\\Models\\Project;', $source);
        $this->assertStringContainsString('use App\\Services\\ProjectService;', $source);
        $this->assertStringNotContainsString('LegacyProjectAdapter as Project', $source);
        $this->assertStringNotContainsString('LegacyProjectServiceAdapter', $source);
    }

    public function test_projects_legacy_service_adapter_file_is_not_present(): void
    {
        $this->assertFileDoesNotExist(
            base_path('src/CoreProject/Services/LegacyProjectServiceAdapter.php'),
            'LegacyProjectServiceAdapter must stay removed once canonical projects use App\\Services\\ProjectService directly.'
        );
    }

    public function test_project_ownership_docs_match_current_ssot(): void
    {
        $domainOwnership = file_get_contents(base_path('docs/engineering/domain-ownership.md'));
        $moduleOwnership = file_get_contents(base_path('docs/architecture/module-ownership-ssot.md'));
        $systemReview = file_get_contents(base_path('docs/audits/2026-03-19-system-review-roadmap-repair.md'));
        $apiDocumentation = file_get_contents(base_path('docs/api/API_DOCUMENTATION.md'));
        $repoArchitectureReview = file_get_contents(base_path('docs/repo-architecture-review.md'));
        $webRouteProposal = file_get_contents(base_path('docs/change-proposals/2026-03-19-p0-runtime-ownership-reconciliation-web-routes.md'));

        $this->assertIsString($domainOwnership);
        $this->assertIsString($moduleOwnership);
        $this->assertIsString($systemReview);
        $this->assertIsString($apiDocumentation);
        $this->assertIsString($repoArchitectureReview);
        $this->assertIsString($webRouteProposal);

        $this->assertStringContainsString('Route family: `/api/zena/projects`', $domainOwnership);
        $this->assertStringContainsString('Controller/service/model owner: `app/Http/Controllers/Api/ProjectController.php`, `app/Services/ProjectService.php`, `app/Models/Project.php`', $domainOwnership);
        $this->assertStringContainsString('Route family: `/api/v1/projects`', $domainOwnership);
        $this->assertStringContainsString('Do not reintroduce `LegacyProjectServiceAdapter`', $domainOwnership);

        $this->assertStringContainsString('| Projects | `/api/zena/projects` | `App\\Http\\Controllers\\Api\\ProjectController` | `App\\Models\\Project` |', $moduleOwnership);
        $this->assertStringContainsString('/api/v1/projects` -> `Src\\CoreProject\\Controllers\\ProjectController`', $moduleOwnership);
        $this->assertStringContainsString('`LegacyProjectServiceAdapter` is removed and must not be reintroduced', $moduleOwnership);

        $this->assertStringContainsString('Canonical business API is `/api/zena/projects`; `/api/v1/projects` remains mounted as `Src\\CoreProject` compatibility runtime', $systemReview);

        $this->assertStringContainsString('Canonical business API: `/api/zena/projects`', $apiDocumentation);
        $this->assertStringContainsString('Compatibility runtime still mounted: `/api/v1/projects` in `Src\\CoreProject\\Controllers\\ProjectController`', $apiDocumentation);
        $this->assertStringContainsString('Removed adapter policy: `LegacyProjectServiceAdapter` is not active runtime and must not be reintroduced', $apiDocumentation);
        $this->assertStringContainsString('curl -X POST http://localhost:8000/api/zena/projects', $apiDocumentation);

        $this->assertStringContainsString('For Projects specifically, `/api/zena/projects` is the canonical business API owned by `App\\Http\\Controllers\\Api\\ProjectController`, `App\\Services\\ProjectService`, and `App\\Models\\Project`, while `/api/v1/projects` remains a mounted compatibility runtime in `Src\\CoreProject\\Controllers\\ProjectController`', $repoArchitectureReview);
        $this->assertStringContainsString('compatibility Projects surface still mounted at `/api/v1/projects` via `Src\\CoreProject\\Controllers\\ProjectController`', $repoArchitectureReview);

        $this->assertStringContainsString('move canonical JSON detail contract to `/api/zena/projects/{project}` while leaving `/api/v1/projects/{project}` mounted only as compatibility runtime', $webRouteProposal);
        $this->assertStringContainsString('keep Project create/update/delete ownership under `/api/zena/projects*`; preserve `/api/v1/projects*` as mounted compatibility runtime', $webRouteProposal);
    }

    public function test_documents_canonical_controller_uses_app_models_not_legacy_adapters(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/SimpleDocumentController.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('use App\\Models\\Document;', $source);
        $this->assertStringContainsString('use App\\Models\\Project;', $source);
        $this->assertStringNotContainsString('LegacyDocumentAdapter as Document', $source);
        $this->assertStringNotContainsString('LegacyProjectAdapter as Project', $source);
    }

    public function test_change_requests_remain_split_between_canonical_and_compatibility_route_owners(): void
    {
        $routeInvariantSource = file_get_contents(base_path('tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php'));

        $this->assertIsString($routeInvariantSource);
        $this->assertStringContainsString("'api.zena.change-requests.index' => 'App\\\\Http\\\\Controllers\\\\Api\\\\ChangeRequestController'", $routeInvariantSource);
        $this->assertStringContainsString("'api/v1/change-requests' => 'Src\\\\ChangeRequest\\\\Controllers\\\\ChangeRequestController'", $routeInvariantSource);
    }

    public function test_tasks_canonical_controller_and_docs_match_current_ssot(): void
    {
        $taskController = file_get_contents(app_path('Http/Controllers/Api/TaskController.php'));
        $zenaTaskModel = file_get_contents(app_path('Models/ZenaTask.php'));
        $domainOwnership = file_get_contents(base_path('docs/engineering/domain-ownership.md'));
        $moduleOwnership = file_get_contents(base_path('docs/architecture/module-ownership-ssot.md'));
        $apiDocumentation = file_get_contents(base_path('docs/api/API_DOCUMENTATION.md'));
        $repoArchitectureReview = file_get_contents(base_path('docs/repo-architecture-review.md'));
        $routeInvariantSource = file_get_contents(base_path('tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php'));

        $this->assertIsString($taskController);
        $this->assertIsString($zenaTaskModel);
        $this->assertIsString($domainOwnership);
        $this->assertIsString($moduleOwnership);
        $this->assertIsString($apiDocumentation);
        $this->assertIsString($repoArchitectureReview);
        $this->assertIsString($routeInvariantSource);

        $this->assertStringContainsString('use App\\Models\\Task;', $taskController);
        $this->assertStringNotContainsString('use Src\\CoreProject\\Models\\Task;', $taskController);
        $this->assertStringNotContainsString('use Src\\CoreProject\\Services\\TaskService;', $taskController);

        $this->assertStringContainsString('@deprecated Use {@see Task} instead.', $zenaTaskModel);

        $this->assertStringContainsString('Route family: `/api/zena/tasks`', $domainOwnership);
        $this->assertStringContainsString('Controller/model owner: `app/Http/Controllers/Api/TaskController.php`, `app/Models/Task.php`', $domainOwnership);
        $this->assertStringContainsString('Compatibility runtime still mounted: `/api/v1/tasks` in `Src/CoreProject/Controllers/TaskController.php`', $domainOwnership);
        $this->assertStringContainsString('Projection runtime still mounted: `/api/v1/work-template/projects/{projectId}/tasks` in `Src/WorkTemplate/Controllers/ProjectTaskController.php`', $domainOwnership);
        $this->assertStringContainsString('Do not route canonical `/api/zena/tasks` changes back through `Src/CoreProject/Services/TaskService.php`', $domainOwnership);

        $this->assertStringContainsString('| Tasks | `/api/zena/tasks` | `App\\Http\\Controllers\\Api\\TaskController` | `App\\Models\\Task` |', $moduleOwnership);
        $this->assertStringContainsString('/api/v1/tasks` -> `Src\\CoreProject\\Controllers\\TaskController`', $moduleOwnership);
        $this->assertStringContainsString('/api/v1/work-template/projects/*/tasks` -> `Src\\WorkTemplate\\Controllers\\ProjectTaskController` projection routes', $moduleOwnership);
        $this->assertStringContainsString('`App\\Models\\ZenaTask` alias', $moduleOwnership);

        $this->assertStringContainsString('Canonical business API: `/api/zena/tasks`', $apiDocumentation);
        $this->assertStringContainsString('Compatibility runtime still mounted: `/api/v1/tasks` in `Src\\CoreProject\\Controllers\\TaskController`', $apiDocumentation);
        $this->assertStringContainsString('Adjacent projection runtime still mounted: `/api/v1/work-template/projects/{projectId}/tasks` in `Src\\WorkTemplate\\Controllers\\ProjectTaskController`', $apiDocumentation);
        $this->assertStringContainsString('Thin alias policy: `App\\Models\\ZenaTask` is compatibility/test alias only; do not add new behavior there', $apiDocumentation);

        $this->assertStringContainsString('compatibility Tasks surface still mounted at `/api/v1/tasks` via `Src\\CoreProject\\Controllers\\TaskController`', $repoArchitectureReview);
        $this->assertStringContainsString('work-template project-task routes remain adjacent projections at `/api/v1/work-template/projects/{projectId}/tasks` via `Src\\WorkTemplate\\Controllers\\ProjectTaskController`', $repoArchitectureReview);

        $this->assertStringContainsString("'api.zena.tasks.index' => 'App\\\\Http\\\\Controllers\\\\Api\\\\TaskController'", $routeInvariantSource);
        $this->assertStringContainsString("'api/v1/tasks' => 'Src\\\\CoreProject\\\\Controllers\\\\TaskController'", $routeInvariantSource);
        $this->assertStringContainsString("'api/v1/work-template/projects/{projectId}/tasks' => 'Src\\\\WorkTemplate\\\\Controllers\\\\ProjectTaskController'", $routeInvariantSource);
    }
}
