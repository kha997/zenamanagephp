---
work_id: GAP-012
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-25-gap-012-cr-apply-notification-evidence.md
  plan: docs/superpowers/plans/2026-10-25-gap-012-cr-apply-notification.md
  branch: docs/GAP-012-cr-apply-notification
  pr: https://github.com/kha997/zenamanagephp/pull/370
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-25T19:53:46+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-25, verbatim: 'APPROVE GAP-012 Gate 2 Option A'. Reviewed design head: 23ffb992e0cc015a777417e284679d8a661f42f6. Approves Option A and its exact allowlist (ChangeRequestController::apply notifies requested_by and the project PM when different, types change_request_implemented / change_request_implemented_pm, event key change_request.implemented, inside the apply transaction; ChangeRequestStakeholderNotificationTest replaces the no-notification-on-apply test; docs); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-25T19:49:44+07:00"
  updated_at: "2026-10-25T19:53:46+07:00"
generated_by: agent
---

# GAP-012 — Change request apply notification: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-25 against reviewed design head
`23ffb992e0cc015a777417e284679d8a661f42f6`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Task bù tạo ra khi apply CR **không có người được giao**, nên chỉ có thể báo cho những người đã gắn với CR.

**Phương án A (khuyến nghị): báo giống lúc duyệt.**
- Khi apply thành công, gửi thông báo trong app cho **người yêu cầu** (`requested_by`) và **PM dự án** (`pm_id`, nếu
  khác người yêu cầu). Đúng hai người nhận như khi duyệt.
- Loại thông báo mới: `change_request_implemented` (người yêu cầu) và `change_request_implemented_pm` (PM); mã sự
  kiện `change_request.implemented`.
- Dùng lại hàm tạo thông báo sẵn có của CR, trong cùng transaction với apply: apply lỗi thì không có thông báo.
- Thay test "apply không gửi thông báo" bằng test mới kiểm tra đúng người nhận.

**Phương án B:** như A nhưng **không báo cho chính người bấm apply** (nếu người đó là người yêu cầu hoặc PM). Khác với
cách đang làm ở bước duyệt.

**Phương án C:** chỉ báo cho người yêu cầu.

Đề xuất **Phương án A**: giống hành vi đã có ở bước duyệt, người dùng dễ hiểu.

## Design: Option A (exact allowlist)

1. `app/Http/Controllers/Api/ChangeRequestController.php`, `apply()`: after the delta task, link-back and baseline
   snapshot and before `DB::commit()`, call `createWorkflowNotification()`:
   - to `requested_by` (when a non-empty string): type `change_request_implemented`, title `Change request implemented`,
     body = CR title, event key `change_request.implemented`;
   - to the project `pm_id` when set and different from `requested_by`: type `change_request_implemented_pm`, title
     `Change request implemented (project update)`, same body and event key.
   The status in the notification data is `implemented` (the helper reads it after the update).
2. `tests/Feature/Api/ChangeRequestStakeholderNotificationTest.php`: replace
   `test_apply_does_not_send_any_notification` with tests that apply notifies the requester and the PM (two rows, right
   types, tenant, project, event key), that only the requester is notified when the PM is the requester, and that a
   rejected apply (CR not approved) creates no notification. Update the class doc comment that says apply notification is
   deferred.
3. Docs: plan, this packet, 03-release.

**Not changed:** submit/approve/reject notifications, email, the notification model, other controllers,
`docs/roadmap/backlog.yaml` (its S3.2a note is history; the register row is updated in the next reconciliation).

## Owner amendment 1 (2026-10-25)

CI on `0765bfa9` showed a second test encoding the old deferral:
`tests/Feature/Api/ChangeRequestApiTest.php::test_can_implement_change_request` asserted that apply creates **no**
`change_request_implemented` notification (line 294). The file was outside the allowlist, so the Owner was asked via
AskUserQuestion and chose, verbatim: "Sửa test trong PR này (Recommended)". Allowlist extended by exactly that assertion:
`assertDatabaseMissing` becomes `assertDatabaseHas` with `user_id` = the requester. No other change to that file.

## Verification

- New tests fail on the base and pass after the change; the whole ChangeRequest test files pass.
- PHPStan, php-cs-fixer, governance and docs lints; exact-head CI green.

## Rollback

Revert the squash commit.

## Decision Needed

Owner chọn: `APPROVE GAP-012 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy; GAP-013 và GAP-014b.
