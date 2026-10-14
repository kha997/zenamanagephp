---
work_id: GAP-078
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-078/02-design.md
---

# GAP-078 — Remove unrouted sidebar builders: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-078/02-design.md`).

1. **Baseline** — save `php artisan route:list --json` before the change.
2. **Delete** — the two `Admin/*SidebarBuilderController` classes and the four `admin/sidebar-*` views; drop their
   `phpstan-baseline.neon` entries; reword the test comment that cites the deleted controller.
3. **Verify** — identical route list, PHPStan clean, `view:cache`, Unit/Feature/Integration suites, lints, exact-head CI.
