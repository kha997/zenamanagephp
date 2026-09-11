<?php

namespace Tests\Unit\Dashboard;

use App\Contracts\Dashboard\WidgetDataProvider;
use App\Models\DashboardWidget;
use App\Services\Dashboard\DashboardWidgetDataResolver;
use App\Services\Dashboard\RoleBasedWidgetDataCalculator;
use App\Services\Dashboard\RoleBasedWidgetProvider;
use App\Services\Dashboard\WidgetDataContext;
use App\Services\Dashboard\WidgetDataResult;
use App\Models\User;
use Tests\TestCase;

class WidgetDataResolverTest extends TestCase
{
    public function test_unsupported_widget_degrades_without_invoking_a_provider(): void
    {
        $provider = new class implements WidgetDataProvider {
            public bool $invoked = false;

            public function supports(string $widgetCode): bool { return false; }

            public function provide(DashboardWidget $widget, WidgetDataContext $context): WidgetDataResult
            {
                $this->invoked = true;
                return WidgetDataResult::ready([]);
            }
        };

        $result = (new DashboardWidgetDataResolver([$provider]))->resolve(
            new DashboardWidget(['code' => 'unsupported_code']),
            new WidgetDataContext(new User(['tenant_id' => 'tenant-1', 'id' => 'user-1']), 'tenant-1'),
        );

        self::assertSame('degraded', $result->state);
        self::assertSame('DASHBOARD.WIDGET_UNSUPPORTED', $result->error['code']);
        self::assertFalse($provider->invoked);
    }

    public function test_provider_failure_becomes_safe_degraded_result(): void
    {
        $provider = new class implements WidgetDataProvider {
            public function supports(string $widgetCode): bool { return $widgetCode === 'supported_code'; }

            public function provide(DashboardWidget $widget, WidgetDataContext $context): WidgetDataResult
            {
                throw new \RuntimeException('sensitive SQL details');
            }
        };

        $result = (new DashboardWidgetDataResolver([$provider]))->resolve(
            new DashboardWidget(['code' => 'supported_code']),
            new WidgetDataContext(new User(['tenant_id' => 'tenant-1', 'id' => 'user-1']), 'tenant-1'),
        );

        self::assertSame('degraded', $result->state);
        self::assertSame('DASHBOARD.WIDGET_DATA_UNAVAILABLE', $result->error['code']);
        self::assertStringNotContainsString('sensitive', json_encode($result->error));
    }

    public function test_role_based_provider_uses_provider_internal_calculator(): void
    {
        $provider = new RoleBasedWidgetProvider(new RoleBasedWidgetDataCalculator());
        $widget = new DashboardWidget(['code' => 'system_health']);
        $context = new WidgetDataContext(
            new User(['tenant_id' => 'tenant-1', 'id' => 'user-1']),
            'tenant-1',
        );

        self::assertSame('ready', $provider->provide($widget, $context)->state);
    }
}
