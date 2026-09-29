---
work_id: GAP-058
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-058/02-design.md
---

# GAP-058 — Retire the dead sidebar badge API: implementation plan

Design: `docs/owner-decisions/GAP-058/02-design.md` (Option 1, approved
2026-09-29; Owner product choice: menu badges not wanted now).

## Task 1 — RED
`tests/Feature/GAP058/BadgeApiRetiredTest.php`: no `api/badges*` URI or
`api.badges.*` name in the route table; `BadgeController`, `BadgeService`,
`App\View\Components\Sidebar` and view `components.sidebar` do not exist; no
PHP/Blade file under `app`, `resources`, `routes` references `BadgeService`,
`BadgeController` or `/api/badges`. Expect 3 failures at base.

## Task 2 — retire
Remove the `badges` route group from `routes/api.php`; delete
`app/Http/Controllers/Api/BadgeController.php`, `app/Services/BadgeService.php`,
`app/View/Components/Sidebar.php`, `resources/views/components/sidebar.blade.php`,
and `tests/Unit/GAP055/BadgeUserCacheClearIsTargetedTest.php` (tests a deleted
service; the GAP-055 invariant holds trivially without a badge surface).
Local note: a worktree with a copied optimized classmap needs
`composer dump-autoload` before `class_exists` checks; CI regenerates it.

## Task 3 — verify
GAP-058 test green; sidebar config/service, user-preference API, route
middleware contracts, GAP-055/057, OwnerGovernance, Architecture suites green;
`route:list --json | scripts/ci/route-guard.php` → ROUTE_GUARD_OK; push; CI.
