<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Forward-guard for docs/architecture/module-ownership-ssot.md's compatibility
 * policy: Src\CoreProject\Models\Project is accepted, frozen debt behind the
 * live /api/v1/* compatibility runtime — this test does not shrink that debt,
 * it only fails loudly the moment a NEW file (outside this fixed allowlist)
 * starts importing it, so a future app-owned module doesn't silently grow the
 * legacy surface this codebase is trying to converge away from.
 *
 * The allowlist below is the exact 2026-07-12 file list from
 * docs/architecture/project-model-reference-inventory.md. When a future
 * consolidation slice removes a reference, remove it from this list too —
 * do not add to this list without updating that inventory document first.
 */
class ProjectModelReferenceAllowlistTest extends TestCase
{
    private const ALLOWED_FILES = [
        'app/Console/Commands/CleanMockData.php',
        'app/Http/Controllers/Api/AnalyticsController.php',
        'app/Http/Controllers/Api/ExportController.php',
        'app/Http/Controllers/TaskController.php',
        'app/Http/Controllers/Web/DocumentController.php',
        'app/Http/Controllers/Web/ProjectController.php',
        'app/Http/Controllers/Web/TaskController.php',
        'app/Http/Controllers/WorkTemplateController.php',
        'app/Models/InteractionLog.php',
        'app/Models/NotificationRule.php',
        'app/Models/UserRoleProject.php',
        'app/Services/ConditionalTagService.php',
        'src/ChangeRequest/Listeners/ChangeRequestEventListener.php',
        'src/ChangeRequest/Models/ChangeRequest.php',
        'src/Compensation/Models/Contract.php',
        'src/Compensation/Services/CompensationService.php',
        'src/CoreProject/Controllers/ProjectController.php',
        'src/CoreProject/Controllers/TaskController.php',
        'src/CoreProject/Controllers/WorkTemplateController.php',
        'src/CoreProject/Events/ProjectCreated.php',
        'src/CoreProject/Listeners/ConditionalTagListener.php',
        'src/CoreProject/Listeners/NotificationListener.php',
        'src/CoreProject/Listeners/ProgressCalculationListener.php',
        'src/CoreProject/Listeners/ProjectCalculationListener.php',
        'src/CoreProject/Listeners/ProjectProgressListener.php',
        'src/CoreProject/Requests/StoreProjectRequest.php',
        'src/CoreProject/Requests/UpdateProjectRequest.php',
        'src/CoreProject/Services/BaselineService.php',
        'src/CoreProject/Services/ComponentService.php',
        'src/CoreProject/Services/ConditionalTagService.php',
        'src/CoreProject/Services/ProjectService.php',
        'src/CoreProject/Services/TaskService.php',
        'src/CoreProject/Services/WorkTemplateApplicationService.php',
        'src/DocumentManagement/Models/Document.php',
        'src/InteractionLogs/Models/InteractionLog.php',
        'src/InteractionLogs/Services/InteractionLogService.php',
        'src/Notification/Models/NotificationRule.php',
        'src/RBAC/Models/UserRoleProject.php',
        'src/WorkTemplate/Events/TaskConditionalToggled.php',
        'src/WorkTemplate/Events/TemplateApplied.php',
        'src/WorkTemplate/Models/ProjectTask.php',
        'src/WorkTemplate/Requests/ApplyTemplateRequest.php',
        'src/WorkTemplate/Services/TemplateService.php',
    ];

    public function test_no_file_outside_the_allowlist_imports_src_coreproject_models_project(): void
    {
        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in([base_path('app'), base_path('src')]);

        $allowedRealPaths = array_map(
            static fn (string $relative): string => base_path($relative),
            self::ALLOWED_FILES
        );

        $unexpected = [];

        foreach ($finder as $file) {
            $realPath = $file->getRealPath();

            if ($realPath === false) {
                continue;
            }

            if (in_array($realPath, $allowedRealPaths, true)) {
                continue;
            }

            $contents = file_get_contents($realPath);

            if ($contents === false) {
                continue;
            }

            if (preg_match('/(?:\\\\)?Src\\\\CoreProject\\\\Models\\\\Project(?![A-Za-z0-9_])/', $contents)) {
                $unexpected[] = str_replace(base_path() . '/', '', $realPath);
            }
        }

        $this->assertSame(
            [],
            $unexpected,
            "New file(s) import Src\\CoreProject\\Models\\Project outside the allowlist: " . implode(', ', $unexpected)
            . ". If this is intentional, add the file to docs/architecture/project-model-reference-inventory.md first, then to this test's ALLOWED_FILES."
        );
    }

    public function test_allowlist_files_that_still_exist_still_reference_the_class(): void
    {
        foreach (self::ALLOWED_FILES as $relativePath) {
            $fullPath = base_path($relativePath);

            if (!file_exists($fullPath)) {
                continue;
            }

            $contents = file_get_contents($fullPath);
            $this->assertIsString($contents);
            $this->assertStringContainsString(
                'CoreProject\\Models\\Project',
                $contents,
                "{$relativePath} is in the allowlist but no longer references Src\\CoreProject\\Models\\Project — remove it from the allowlist and the inventory doc."
            );
        }
    }
}
