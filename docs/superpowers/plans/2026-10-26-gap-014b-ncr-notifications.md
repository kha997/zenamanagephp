---
work_id: GAP-014b
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-014b/02-design.md
---

# GAP-014b — NCR notifications: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-014b/02-design.md`).

1. **Tests first** — add `tests/Feature/Api/InspectionNcrNotificationTest.php` and show it fails on the base.
2. **Controller** — in `Api\InspectionController`, add a private best-effort notify helper and call it from `storeNcr()`
   (assignee) and `updateNcrStatus()` (creator on resolved, assignee on closed), skipping the actor.
3. **Dead code** — delete `app/Listeners/NcrEventListener.php` and its `phpstan-baseline.neon` entries.
4. **Verify** — NCR tests, `tests/Feature/Api`, `tests/Feature/Web`, PHPStan, php-cs-fixer, SSOT lint, exact-head CI,
   governance and docs lints.
