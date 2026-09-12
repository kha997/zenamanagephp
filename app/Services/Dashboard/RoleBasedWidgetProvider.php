<?php

namespace App\Services\Dashboard;

use App\Contracts\Dashboard\WidgetDataProvider;
use App\Models\DashboardWidget;

final class RoleBasedWidgetProvider implements WidgetDataProvider
{
    /** @var list<string> */
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
        $widgetCode = (string) $widget->getAttribute('code');

        if (! $this->supports($widgetCode)) {
            return WidgetDataResult::unsupported();
        }

        $data = match ($widgetCode) {
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
            default => null,
        };

        if ($data === null) {
            return WidgetDataResult::unsupported();
        }

        return WidgetDataResult::ready($data);
    }

    /** @return list<string> */
    public static function supportedCodes(): array
    {
        return self::SUPPORTED_CODES;
    }
}
