<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * GAP-020 — /admin/users and /admin/tenants render admin.users and admin.tenants;
 * the look-alike admin/{users,tenants}/index views were orphans and were removed.
 */
class OrphanAdminViewsRemovedTest extends TestCase
{
    public function test_orphan_admin_index_views_are_removed_and_live_views_remain(): void
    {
        foreach (['admin.users.index', 'admin.tenants.index'] as $view) {
            $this->assertFalse(view()->exists($view), "View {$view} must stay removed (GAP-020).");
        }
        foreach (['admin.users', 'admin.tenants'] as $view) {
            $this->assertTrue(view()->exists($view), "Live view {$view} must remain.");
        }
    }
}
