---
work_id: GAP-012
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-012/02-design.md
---

# GAP-012 — Change request apply notification: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-012/02-design.md`).

1. **Tests first** — in `tests/Feature/Api/ChangeRequestStakeholderNotificationTest.php`, replace
   `test_apply_does_not_send_any_notification` with: apply notifies requester and PM; only the requester when the PM is
   the requester; no notification when apply is refused. Show they fail on the base.
2. **Controller** — in `ChangeRequestController::apply()`, before `DB::commit()`, call `createWorkflowNotification()` for
   `requested_by` (`change_request_implemented`) and for a different PM (`change_request_implemented_pm`), event key
   `change_request.implemented`.
3. **Verify** — ChangeRequest test files, PHPStan, php-cs-fixer, exact-head CI, governance and docs lints.
