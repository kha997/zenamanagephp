<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * GAP-018 — the smart-tools Blade components and their demo page called
 * /api/universal-frame/*, a prefix with no routes, and nothing rendered them.
 * They were removed; this guard keeps them from coming back.
 */
class DeadSmartToolsComponentsRemovedTest extends TestCase
{
    public function test_dead_smart_tools_views_are_removed(): void
    {
        foreach ([
            'components.smart-search',
            'components.smart-filters',
            'components.analysis-drawer',
            'components.export-component',
            'test-smart-tools',
        ] as $view) {
            $this->assertFalse(view()->exists($view), "View {$view} must stay removed (GAP-018).");
        }
    }
}
