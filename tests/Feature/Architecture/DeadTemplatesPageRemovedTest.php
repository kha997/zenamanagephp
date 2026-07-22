<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Data-entry #5 (2026-07-22): `/app/templates*` là trang demo giả hoàn toàn
 * chết — card hardcode "Marketing"/"Event management", nút không nối gì,
 * `templates.create`/`templates.show` trỏ view còn không tồn tại (500 nếu
 * bấm vào). Không liên quan tới App\Models\Template (vẫn dùng qua
 * Policy/API TemplateController) hay WorkTemplate/DeliverableTemplate
 * (hệ thống mẫu thật). Guard này chặn route/view chết tái sinh.
 */
class DeadTemplatesPageRemovedTest extends TestCase
{
    public function test_dead_app_templates_routes_are_removed(): void
    {
        foreach (['app.templates', 'app.templates.builder', 'app.templates.construction-builder', 'app.templates.analytics', 'app.templates.create', 'app.templates.show'] as $routeName) {
            $this->assertFalse(Route::has($routeName), "Route {$routeName} phải bị gỡ (trang demo giả chết).");
        }
    }

    public function test_dead_templates_mock_views_are_removed(): void
    {
        foreach (['templates.index', 'templates.builder', 'templates.construction-builder', 'templates.analytics'] as $view) {
            $this->assertFalse(view()->exists($view), "View {$view} phải bị gỡ (mock demo, không dữ liệu thật).");
        }
    }
}
