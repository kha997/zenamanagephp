# GAP-012 — Evidence: applying a change request notifies nobody

Base: `b810ed921b82edf2c83d222287f4d55826826950` (main after OWN-2026-019).

## Current behaviour

`app/Http/Controllers/Api/ChangeRequestController.php` (`/api/zena/change-requests`):
- `submit` notifies the approver (`assigned_to`); `approve` notifies the requester (`requested_by`, type
  `change_request_approved`) and the project PM (`projects.pm_id`, type `change_request_approved_pm`) when different;
  `reject` notifies the requester. All use the private helper `createWorkflowNotification()` (lines 47-69: in-app
  `Notification` row with tenant, project, `event_key`, CR id and status).
- `apply()` (lines 842-893) moves `approved -> implemented`, creates the delta task, the task link-back and the baseline
  snapshot, and writes the audit log `zena.change_request.apply`. It creates **no** notification.
- This was a deliberate deferral when S3.2a was closed: `docs/roadmap/backlog.yaml` S3.2a notes "Apply notification
  explicitly deferred", and `tests/Feature/Api/ChangeRequestStakeholderNotificationTest.php::test_apply_does_not_send_any_notification`
  (line 171) asserts the notification count is unchanged after apply.

## Consequence

The requester and the PM are told the CR was approved, but not that it was implemented (delta task created, baseline
changed). They only find out by opening the CR.

## Notes for Gate 2

- Who receives the apply notification (requester, PM, assignee of the new delta task), and whether the person who applied
  it is excluded.
- Reuse `createWorkflowNotification()` with a new type (e.g. `change_request_implemented`) and `event_key`
  `change_request.implemented`, inside the existing transaction.
- The existing test that asserts no notification on apply must be replaced, which needs the Owner's decision because it
  encodes the old deferral.
