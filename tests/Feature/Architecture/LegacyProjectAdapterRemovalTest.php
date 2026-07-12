<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Confirms the 6 non-canonical, non-v1-compatibility-runtime controllers
 * that historically used Src\CoreProject\Models\LegacyProjectAdapter now
 * reference App\Models\Project directly. LegacyProjectAdapter is an empty
 * subclass of App\Models\Project (zero behavior change), and none of
 * these 6 files are part of the /api/v1/* Src\CoreProject compatibility
 * runtime (see docs/architecture/module-ownership-ssot.md) — safe to
 * consolidate, unlike anything under src/CoreProject/Controllers.
 */
class LegacyProjectAdapterRemovalTest extends TestCase
{
    private const MIGRATED_FILES = [
        'app/Http/Controllers/Web/ProjectBulkController.php',
        'app/Http/Controllers/Web/DocumentManagementController.php',
        'app/Http/Controllers/Api/ProjectTemplateController.php',
        'app/Http/Controllers/Api/ProjectAnalyticsController.php',
        'app/Http/Controllers/Api/ProjectManagerController.php',
        'app/Http/Controllers/Api/ProjectMilestoneController.php',
    ];

    public function test_migrated_controllers_no_longer_reference_legacy_project_adapter(): void
    {
        foreach (self::MIGRATED_FILES as $relativePath) {
            $source = file_get_contents(base_path($relativePath));

            $this->assertIsString($source, "Unable to read {$relativePath}");
            $this->assertStringNotContainsString(
                'LegacyProjectAdapter',
                $source,
                "{$relativePath} should no longer reference Src\\CoreProject\\Models\\LegacyProjectAdapter."
            );
            $this->assertStringContainsString(
                'use App\\Models\\Project;',
                $source,
                "{$relativePath} should import App\\Models\\Project directly."
            );
        }
    }

    public function test_legacy_project_adapter_class_itself_is_untouched(): void
    {
        $source = file_get_contents(base_path('src/CoreProject/Models/LegacyProjectAdapter.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('class LegacyProjectAdapter extends BaseProject', $source);
    }
}
