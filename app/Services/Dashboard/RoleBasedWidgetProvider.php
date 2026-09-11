<?php

namespace App\Services\Dashboard;

use App\Contracts\Dashboard\WidgetDataProvider;
use App\Models\DashboardWidget;

final class RoleBasedWidgetProvider implements WidgetDataProvider
{
    private const SUPPORTED_CODES = [
        'project_overview', 'task_progress', 'rfi_status', 'budget_tracking',
        'schedule_timeline', 'team_performance', 'quality_metrics', 'safety_summary',
        'inspection_schedule', 'ncr_tracking', 'system_health', 'user_management',
    ];

    public function __construct(private readonly RoleBasedWidgetDataCalculator $calculator)
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

        $data = match ((string) $widget->code) {
            'project_overview' => $this->calculator->projectOverview($context->user, $context->projectId),
            'task_progress' => $this->calculator->taskProgress($context->user, $context->projectId),
            'rfi_status' => $this->calculator->rfiStatus($context->user, $context->projectId),
            'budget_tracking' => $this->calculator->budgetTracking($context->user, $context->projectId),
            'schedule_timeline' => $this->calculator->scheduleTimeline($context->user, $context->projectId),
            'team_performance' => $this->calculator->teamPerformance($context->user, $context->projectId),
            'quality_metrics' => $this->calculator->qualityMetrics($context->user, $context->projectId),
            'safety_summary' => $this->calculator->safetySummary($context->user, $context->projectId),
            'inspection_schedule' => $this->calculator->inspectionSchedule($context->user, $context->projectId),
            'ncr_tracking' => $this->calculator->ncrTracking($context->user, $context->projectId),
            'system_health' => $this->calculator->systemHealth($context->user),
            'user_management' => $this->calculator->userManagement($context->user),
        };

        return WidgetDataResult::ready($data);
    }

    public static function supportedCodes(): array
    {
        return self::SUPPORTED_CODES;
    }
}
