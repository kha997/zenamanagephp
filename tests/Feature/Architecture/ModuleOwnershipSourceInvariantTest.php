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
        $this->assertStringNotContainsString('LegacyProjectAdapter as Project', $source);
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
}
