---
work_id: GAP-013
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-013/02-design.md
---

# GAP-013 — Submittal notifications: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-013/02-design.md`).

1. **Tests first** — add `tests/Feature/Api/SubmittalNotificationTest.php` (first submit → PM; approve/reject →
   submitter; no self-notification; refused transition sends nothing) and show it fails on the base.
2. **Service** — in `SubmittalLifecycleService`, add a shared notify helper (try/catch + log); call it after the
   transaction in `submit()` (first submit, project PM) and `decide()` (submitter); route the existing resubmit
   notification through the helper without changing its behaviour.
3. **Verify** — Submittal tests (including `SubmittalResubmitLifecycleTest` unchanged), `tests/Feature/Api`, PHPStan,
   php-cs-fixer, SSOT lint, exact-head CI, governance and docs lints.
