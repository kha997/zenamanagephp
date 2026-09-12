<?php

namespace App\Services\Dashboard;

use App\Contracts\Dashboard\WidgetDataProvider;
use App\Contracts\Dashboard\WidgetDataResolver;
use App\Models\DashboardWidget;
use App\Services\ErrorEnvelopeService;
use Illuminate\Support\Facades\Log;

final class DashboardWidgetDataResolver implements WidgetDataResolver
{
    /** @param iterable<WidgetDataProvider> $providers */
    public function __construct(private readonly iterable $providers)
    {
    }

    public function canResolve(DashboardWidget $widget): bool
    {
        $widgetCode = (string) $widget->getAttribute('code');

        foreach ($this->providers as $provider) {
            if ($provider->supports($widgetCode)) {
                return true;
            }
        }

        return false;
    }

    public function resolve(DashboardWidget $widget, WidgetDataContext $context): WidgetDataResult
    {
        $widgetCode = (string) $widget->getAttribute('code');
        $role = (string) $context->user->getAttribute('role');

        foreach ($this->providers as $provider) {
            if (! $provider->supports($widgetCode)) {
                continue;
            }

            try {
                return $provider->provide($widget, $context);
            } catch (\Throwable $exception) {
                Log::error('Dashboard widget provider failed.', [
                    'widget_code' => $widgetCode,
                    'user_id' => $context->user->id,
                    'tenant_id' => $context->tenantId,
                    'role' => $role,
                    'project_id' => $context->projectId,
                    'request_id' => ErrorEnvelopeService::getCurrentRequestId(),
                    'failure_reason' => 'provider_failure',
                ]);

                return WidgetDataResult::degraded(
                    'DASHBOARD.WIDGET_DATA_UNAVAILABLE',
                    'Widget data is temporarily unavailable.',
                    true,
                );
            }
        }

        return WidgetDataResult::unsupported();
    }
}
