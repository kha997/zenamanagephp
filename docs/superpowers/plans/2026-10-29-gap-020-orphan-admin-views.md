---
work_id: GAP-020
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-020/02-design.md
---

# GAP-020 — Orphan admin views: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-020/02-design.md`).

1. **Red:** add `tests/Feature/Architecture/OrphanAdminViewsRemovedTest.php` (orphan `admin.users.index` /
   `admin.tenants.index` absent, live `admin.users` / `admin.tenants` present); it fails while the orphans exist.
2. **Green:** delete `resources/views/admin/users/index.blade.php` and `resources/views/admin/tenants/index.blade.php`.
3. Verify: `php artisan view:cache`; Architecture, Web and Feature suites; SSOT lint; route guard; governance and docs lints.
