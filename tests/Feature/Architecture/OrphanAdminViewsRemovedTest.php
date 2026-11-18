<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * GAP-020 — the look-alike admin/{users,tenants}/index views were orphans and were removed.
 *
 * GAP-129 (amendment 2) — the mock-data pages admin.users / admin.tenants that /admin/users and /admin/tenants used to
 * render were removed too; those URLs now redirect to the real pages under /app/admin (see
 * tests/Feature/Admin/AdminRolesCompanyAndSessionTest).
 */
class OrphanAdminViewsRemovedTest extends TestCase
{
    public function test_orphan_admin_index_views_and_the_mock_pages_are_removed(): void
    {
        foreach (['admin.users.index', 'admin.tenants.index'] as $view) {
            $this->assertFalse(view()->exists($view), "View {$view} must stay removed (GAP-020).");
        }
        foreach (['admin.users', 'admin.tenants'] as $view) {
            $this->assertFalse(view()->exists($view), "Mock view {$view} must stay removed (GAP-129).");
        }
    }
}
