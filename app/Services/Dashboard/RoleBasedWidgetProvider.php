<?php

namespace App\Services\Dashboard;

use App\Contracts\Dashboard\WidgetDataProvider;
use App\Models\DashboardWidget;
use Closure;

final class RoleBasedWidgetProvider implements WidgetDataProvider
{
    private const SUPPORTED_CODES = [
        'project_overview', 'task_progress', 'rfi_status', 'budget_tracking',
        'schedule_timeline', 'team_performance', 'quality_metrics', 'safety_summary',
        'inspection_schedule', 'ncr_tracking', 'system_health', 'user_management',
    ];

    public function __construct(private readonly Closure $dataLoader)
    {
    }

    public function supports(string $widgetCode): bool
    {
        return in_array($widgetCode, self::SUPPORTED_CODES, true);
    }

    public function provide(DashboardWidget $widget, WidgetDataContext $context): WidgetDataResult
    {
        if (! $this->supports((string) $widget->code)) {
            return WidgetDataResult::unsupported();
        }

        $data = ($this->dataLoader)($widget, $context);

        return WidgetDataResult::ready($data);
    }

    public static function supportedCodes(): array
    {
        return self::SUPPORTED_CODES;
    }
}
