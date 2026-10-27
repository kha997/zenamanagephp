# GAP-013 — Evidence: submittal workflow sends almost no notifications

Base: `588daf8e1a881d500e12801572933cd025de4d8e` (main after GAP-012).

## Where the workflow lives

All status changes go through `app/Services/SubmittalLifecycleService.php`, used by the API
(`app/Http/Controllers/Api/SubmittalController.php`) and the web pages (`app/Http/Controllers/Web/SubmittalPageController.php`).
Transitions (`app/Models/Submittal.php:107-111`): draft → submitted → approved | rejected; rejected → revising → submitted.

## What is notified today

| Step | Notification | Source |
|---|---|---|
| First submit | none | `submit()` lines 69-134 |
| Resubmit (after revising) | **yes** — the last rejector (`submittal_revisions.decided_by`), type `submittal_resubmitted`, after commit, failures logged | `notifyLastRejector()` lines 282-313; test `SubmittalResubmitLifecycleTest::test_resubmit_notifies_last_rejector` |
| Approve | none | `approve()` → `decide()` |
| Reject | none | `reject()` → `decide()` |
| Start revision | none | `startRevision()` |

The register row (written 2026-07-23) says there is no fan-out at all; the resubmit notification was added since, so the
row is partly stale.

## Who could receive

- The submittal has `submitted_by`, `created_by`, `reviewed_by`, `approved_by`, `rejected_by`, but **no designated
  reviewer/approver field** (same situation as Document before GAP-033). Approval permission is role-wide
  (`submittal.approve`).
- The project has `pm_id`.

## Consequence

The submitter is not told when the submittal is approved or rejected, and nobody is told that a new submittal awaits
review. Material approval speed depends on people checking the list.

## Notes for Gate 2

- Approve/reject: notify `submitted_by` (and `created_by` if different?).
- First submit: there is no designated reviewer; options are the project PM, everyone holding `submittal.approve` in the
  tenant, or nobody until a reviewer field exists.
- Reuse the existing after-commit, failure-logged pattern of `notifyLastRejector()`.
