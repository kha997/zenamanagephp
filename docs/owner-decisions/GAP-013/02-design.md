---
work_id: GAP-013
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-25-gap-013-submittal-notifications-evidence.md
  plan: docs/superpowers/plans/2026-10-25-gap-013-submittal-notifications.md
  branch: docs/GAP-013-submittal-notifications
  pr: https://github.com/kha997/zenamanagephp/pull/371
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-25T22:22:44+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-25, verbatim: 'APPROVE GAP-013 Gate 2 Option A'. Reviewed design head: a469fe6e97719c8c4061f82d613277f05c29ccc3. Approves Option A and its exact allowlist (SubmittalLifecycleService: first submit notifies the project PM, approve/reject notify submitted_by, no self-notification, after commit with logged failures, shared helper with the unchanged resubmit notification; new tests/Feature/Api/SubmittalNotificationTest.php; docs); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-25T22:00:23+07:00"
  updated_at: "2026-10-25T22:22:44+07:00"
generated_by: agent
---

# GAP-013 — Submittal notifications: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-25 against reviewed design head
`a469fe6e97719c8c4061f82d613277f05c29ccc3`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

**Phương án A (khuyến nghị):**
- **Duyệt / từ chối:** người nộp (`submitted_by`) nhận thông báo `submittal_approved` hoặc `submittal_rejected`.
- **Nộp lần đầu:** **PM dự án** (`pm_id`) nhận thông báo `submittal_submitted` là có submittal chờ duyệt. Đây là người
  đã được báo ở các luồng khác (CR), và chỉ một người nên không làm phiền cả tenant.
- **Nộp lại:** giữ nguyên như hiện tại (báo người đã từ chối lần trước).
- Không báo cho chính người vừa thao tác (ví dụ PM tự nộp thì không tự báo cho mình).
- Gửi sau khi commit, lỗi gửi chỉ ghi log, giống cách nộp lại đang làm. Áp dụng cho cả API và trang web vì cùng dùng một
  service.

**Phương án B:** như A, nhưng khi nộp lần đầu thì báo **mọi người có quyền `submittal.approve`** trong tenant. Ai duyệt
được cũng biết, nhưng có thể nhiều thông báo.

**Phương án C:** chỉ báo kết quả duyệt / từ chối cho người nộp, chưa báo khi nộp lần đầu.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. `app/Services/SubmittalLifecycleService.php`:
   - `submit()`: after the transaction, on a first submit (not a resubmit), notify the project's `pm_id` when set and
     different from the actor: type `submittal_submitted`.
   - `decide()` (used by `approve()` and `reject()`): after the transaction, notify `submitted_by` when set and different
     from the actor: type `submittal_approved` or `submittal_rejected`.
   - One private helper shared with the existing resubmit notification: `Notification` row with tenant, user, type,
     Vietnamese title with the submittal number, body = title, project; wrapped in try/catch that logs
     `submittal.notification_failed` (as today). The existing resubmit behaviour and its type do not change.
2. New test `tests/Feature/Api/SubmittalNotificationTest.php`: first submit notifies the PM; approve notifies the
   submitter; reject notifies the submitter; no self-notification; a refused transition sends nothing. Existing
   `SubmittalResubmitLifecycleTest` must stay green unchanged.
3. Docs: plan, this packet, 03-release.

**Not changed:** submittal schema (no reviewer field), RBAC, email, other modules.

## Verification

- New tests fail on the base and pass after; all Submittal tests pass; `tests/Feature/Api` passes.
- PHPStan, php-cs-fixer, SSOT test lint, governance and docs lints; exact-head CI green.

## Rollback

Revert the squash commit.

## Decision Needed

Owner chọn: `APPROVE GAP-013 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy; GAP-014b.
