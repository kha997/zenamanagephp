<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Services\LaunchChecklistService;
use Tests\TestCase;

/**
 * GAP-019 — the universal-frame demo shell and the components only it used had no route, and the launch checklist
 * scored three items by checking that those demo files exist. Both were removed; this guard keeps them from coming back.
 */
class UniversalFrameShellRemovedTest extends TestCase
{
    public function test_universal_frame_shell_views_are_removed(): void
    {
        foreach ([
            'layouts.universal-frame',
            'test-universal-frame',
            'components.activity-panel',
            'components.alert-bar',
            'components.kpi-strip',
            'components.mobile-drawer',
            'components.mobile-fab',
            'components.mobile-navigation',
            'components.universal-header',
            'components.universal-navigation',
            'components.navigation.admin-nav',
            'components.navigation.mobile-admin-nav',
            'components.navigation.mobile-tenant-nav',
            'components.navigation.tenant-nav',
        ] as $view) {
            $this->assertFalse(view()->exists($view), "View {$view} must stay removed (GAP-019).");
        }
    }

    public function test_launch_checklist_no_longer_scores_demo_file_existence(): void
    {
        $checks = $this->app->make(LaunchChecklistService::class)->runSystemIntegrationChecks();

        foreach (['universal_page_frame', 'smart_tools', 'mobile_optimization'] as $removed) {
            $this->assertArrayNotHasKey($removed, $checks);
        }

        $this->assertSame(['accessibility', 'performance_optimization', 'api_integration'], array_keys($checks));
    }
}
