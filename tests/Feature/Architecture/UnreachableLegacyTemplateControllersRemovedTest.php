<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * 2026-07-22 audit tiếp nối (sau PR#218): 2 controller "Template" khác được
 * xác nhận chết hoàn toàn, bằng chứng chắc hơn cả vụ ProjectMilestone/
 * ProjectTemplate (không cần suy luận schema/route thiếu tham số):
 *
 * - App\Http\Controllers\TemplateController: KHÔNG route nào trong toàn bộ
 *   repo mount tới nó (grep routes/ zero kết quả) — unreachable tuyệt đối.
 * - App\Http\Controllers\Api\App\TemplateController: chỉ mount trong
 *   routes/legacy/api_v1.php — chính routes/README.md của repo ghi rõ file
 *   này "legacy reference only and is not loaded by
 *   App\Providers\RouteServiceProvider".
 *
 * Còn 3 mảnh khác trong cụm "template graveyard" (Src\WorkTemplate\Controllers\
 * TemplateController có test thật nhưng 0 UI caller, Src\CoreProject\Controllers\
 * WorkTemplateController 0 consumer, App\Http\Controllers\ProjectTemplateController
 * bị che route) — CỐ TÌNH không đụng, rủi ro cao hơn (có thể có consumer ngoài
 * codebase này), cần hỏi người phụ trách nghiệp vụ trước khi quyết định.
 */
class UnreachableLegacyTemplateControllersRemovedTest extends TestCase
{
    public function test_unmounted_template_controllers_are_removed(): void
    {
        $this->assertFalse(
            File::exists(app_path('Http/Controllers/TemplateController.php')),
            'App\\Http\\Controllers\\TemplateController phải bị gỡ (không route nào mount).'
        );
        $this->assertFalse(
            File::exists(app_path('Http/Controllers/Api/App/TemplateController.php')),
            'App\\Http\\Controllers\\Api\\App\\TemplateController phải bị gỡ (chỉ mount trong routes/legacy/api_v1.php, file không được load).'
        );
    }

    public function test_dead_legacy_route_file_still_documented_as_unloaded(): void
    {
        // Guard này canh chừng README — nếu ai đó lỡ load lại legacy/api_v1.php
        // (routes/README.md cấm rõ), controller vừa xoá sẽ trở thành route
        // thật bị mất mà không ai biết.
        $readme = File::get(base_path('routes/README.md'));
        $this->assertStringContainsString(
            'routes/legacy/api_v1.php` is legacy reference only and is not loaded',
            $readme,
            'routes/README.md phải tiếp tục xác nhận legacy/api_v1.php không được load — nếu dòng này biến mất, kiểm tra lại trước khi tin controller đã xoá vẫn an toàn.'
        );
    }
}
