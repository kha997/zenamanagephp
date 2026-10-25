---
work_id: GAP-016
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-016/02-design.md
---

# GAP-016 — Enhanced routes with missing views: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-016/02-design.md`).

1. **Test first** — add `tests/Feature/Web/EnhancedRoutesRemovedTest.php` (signed-in user gets 404 on both URLs; both
   route names gone) and show it fails on the base.
2. **Routes** — remove the `/admin-dashboard-enhanced` and `/projects-enhanced` closures and their comment lines from
   `routes/web.php`.
3. **Verify** — the new test passes; `route:list` differs only by the two routes; route/architecture tests, PHPStan,
   php-cs-fixer, exact-head CI, governance and docs lints.
