<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class BoqBadgeComponentsTest extends TestCase
{
    public function test_calibration_badge_renders_distinct_markup_for_each_state(): void
    {
        $uncalibrated = Blade::render('<x-ui.calibration-badge status="UNCALIBRATED" />');
        $calibrated = Blade::render('<x-ui.calibration-badge status="CALIBRATED" />');

        $this->assertStringContainsString('bg-rose-600', $uncalibrated);
        $this->assertStringContainsString('Chưa hiệu chỉnh', $uncalibrated);
        $this->assertStringContainsString('bg-emerald-600', $calibrated);
        $this->assertStringContainsString('Đã hiệu chỉnh', $calibrated);
        $this->assertStringNotContainsString('bg-rose-600', $calibrated);
        $this->assertStringNotContainsString('bg-emerald-600', $uncalibrated);
    }

    public function test_status_badge_renders_new_quote_status_values(): void
    {
        $issued = Blade::render('<x-ui.status-badge status="issued" />');
        $accepted = Blade::render('<x-ui.status-badge status="accepted" />');
        $superseded = Blade::render('<x-ui.status-badge status="superseded" />');

        $this->assertStringContainsString('Đã phát hành', $issued);
        $this->assertStringContainsString('Đã chấp nhận', $accepted);
        $this->assertStringContainsString('Đã thay thế', $superseded);
    }
}
