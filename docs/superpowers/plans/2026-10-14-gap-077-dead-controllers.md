---
work_id: GAP-077
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-077/02-design.md
---

# GAP-077 — Remove unreachable controllers: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-077/02-design.md`).

1. **Baseline** — save `php artisan route:list --json` before the change.
2. **Delete** — the legacy `InteractionLogController`, its two `app/Http/Requests`, `Admin/BasicSidebarController`,
   `admin/simple-sidebar-builder.blade.php`; drop their `phpstan-baseline.neon` entries.
3. **Verify** — identical route list, PHPStan clean, `composer dump-autoload`, Unit/Feature/Integration suites, lints,
   exact-head CI.
