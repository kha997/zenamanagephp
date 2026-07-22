<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\ProjectMilestone;
use App\Models\ProjectTemplate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 2026-07-22 audit: Api\ProjectMilestoneController và Api\ProjectTemplateController
 * bị gỡ vì cả hai đều chưa từng hoạt động đúng (route milestone thiếu {project};
 * ProjectTemplateController viết cho schema project_templates chưa từng tồn tại
 * thật trên DB — xem docs/superpowers/specs/2026-07-22-dead-milestone-template-api-removal-design.md)
 * và zero consumer thật (không blade/JS/test nào gọi). Model + mọi nơi đọc model
 * qua Eloquent trực tiếp vẫn giữ nguyên — guard này xác nhận cả hai vế.
 */
class DeadMilestoneTemplateApiRemovedTest extends TestCase
{
    public function test_dead_milestone_api_routes_are_removed(): void
    {
        foreach (['milestones.index', 'milestones.store', 'milestones.show', 'milestones.update', 'milestones.destroy'] as $routeName) {
            $this->assertFalse(Route::has($routeName), "Route {$routeName} phải bị gỡ (milestone write-API chết).");
        }

        $unnamedUris = [
            'api/projects/milestones/reorder', // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
            'api/projects/milestones/{milestone}/mark-completed', // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
            'api/projects/milestones/{milestone}/mark-cancelled', // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ];
        foreach (Route::getRoutes() as $route) {
            $this->assertNotContains($route->uri(), $unnamedUris, "URI {$route->uri()} phải bị gỡ (milestone write-API chết).");
        }
    }

    public function test_dead_project_template_api_routes_are_removed(): void
    {
        foreach (['project-templates.index', 'project-templates.store', 'project-templates.show', 'project-templates.update', 'project-templates.destroy'] as $routeName) {
            $this->assertFalse(Route::has($routeName), "Route {$routeName} phải bị gỡ (Api\\ProjectTemplateController chết).");
        }

        $unnamedUris = [
            'api/project-templates/categories', // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
            'api/project-templates/{template}/create-project', // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
            'api/project-templates/{template}/duplicate', // SSOT_ALLOW_ORPHAN(reason=NEGATIVE_PROBE_LEGACY_SURFACE)
        ];
        foreach (Route::getRoutes() as $route) {
            $this->assertNotContains($route->uri(), $unnamedUris, "URI {$route->uri()} phải bị gỡ (Api\\ProjectTemplateController chết).");
        }
    }

    public function test_kept_models_still_instantiate_and_query(): void
    {
        // Xoá controller không được đụng tới model — cả hai vẫn query bình thường.
        $this->assertIsIterable(ProjectMilestone::query()->limit(1)->get());
        $this->assertIsIterable(ProjectTemplate::query()->limit(1)->get());
    }

    public function test_kept_non_api_project_template_controller_route_untouched(): void
    {
        // App\Http\Controllers\ProjectTemplateController (khác namespace, giữ lại
        // để audit riêng) mount ở /api/templates — path khác hẳn /api/project-templates
        // vừa gỡ, phải không bị ảnh hưởng.
        $this->assertTrue(Route::has('templates.show'), 'Route templates.show (Src\\WorkTemplate legacy, giữ nguyên) không được bị gỡ nhầm.');
    }

    public function test_duplicate_noop_project_templates_migration_is_removed(): void
    {
        $this->assertFalse(
            File::exists(database_path('migrations/2025_09_16_081512_create_project_templates_table.php')),
            'Migration 2025_09_16_081512 phải bị gỡ (guard if (!Schema::hasTable) khiến up() không bao giờ thực thi — no-op vĩnh viễn từ khi tạo, bảng project_templates thật đã được migration 2024_02_15 tạo trước).'
        );
        $this->assertTrue(
            File::exists(database_path('migrations/2024_02_15_000001_create_project_templates_table.php')),
            'Migration 2024_02_15 là schema thật đang dùng — không được xoá.'
        );
    }

    /**
     * Bug thật tự bắt trong CI (MySQL SQLSTATE HY000 error 3730) sau khi gỡ
     * migration 2025_09_16: up() của nó là no-op vĩnh viễn (đã verify), nhưng
     * down() của nó lại có logic thật — drop template_tasks/zena_template_tasks
     * (có FK trỏ vào project_templates) TRƯỚC KHI drop project_templates, tránh
     * lỗi FK constraint. Logic đó đã chuyển sang down() của migration 2024_02_15
     * (giữ lại) — guard này chặn ai đó lỡ tay rút gọn về Schema::dropIfExists
     * đơn thuần một lần nữa.
     */
    public function test_kept_migration_down_drops_dependent_tables_before_project_templates(): void
    {
        $source = File::get(database_path('migrations/2024_02_15_000001_create_project_templates_table.php'));

        $downStart = strpos($source, 'public function down');
        $this->assertIsInt($downStart, 'Migration phải có method down().');
        $downBody = substr($source, $downStart);

        $templateTasksPos = strpos($downBody, "'template_tasks'");
        $zenaTemplateTasksPos = strpos($downBody, "'zena_template_tasks'");
        $projectTemplatesDropPos = strpos($downBody, "dropIfExists('project_templates')");

        $this->assertNotFalse($templateTasksPos, 'down() phải drop template_tasks trước project_templates (FK dependency).');
        $this->assertNotFalse($zenaTemplateTasksPos, 'down() phải drop zena_template_tasks trước project_templates (FK dependency).');
        $this->assertNotFalse($projectTemplatesDropPos, 'down() phải drop project_templates.');
        $this->assertLessThan($projectTemplatesDropPos, $templateTasksPos, 'template_tasks phải drop TRƯỚC project_templates, không thì MySQL ném lỗi 3730.');
        $this->assertLessThan($projectTemplatesDropPos, $zenaTemplateTasksPos, 'zena_template_tasks phải drop TRƯỚC project_templates, không thì MySQL ném lỗi 3730.');
    }
}
