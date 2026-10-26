---
work_id: GAP-014b
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-26-gap-014b-ncr-notifications-evidence.md
  plan: docs/superpowers/plans/2026-10-26-gap-014b-ncr-notifications.md
  branch: docs/GAP-014b-ncr-notifications
  pr: https://github.com/kha997/zenamanagephp/pull/372
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-26T10:54:15+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-26, verbatim: 'APPROVE GAP-014b Gate 2 Option A'. Reviewed design head: f8931bf47c9ab21ef920f682a77599ffcd36d3c8. Approves Option A and its exact allowlist (InspectionController: storeNcr notifies the assignee, updateNcrStatus notifies the creator on resolved and the assignee on closed, no self-notification, best-effort with logged failures, one private helper; delete NcrEventListener and its phpstan-baseline entries; new tests/Feature/Api/InspectionNcrNotificationTest.php; docs); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-26T10:50:05+07:00"
  updated_at: "2026-10-26T10:54:15+07:00"
generated_by: agent
---

# GAP-014b — NCR notifications: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-26 against reviewed design head
`f8931bf47c9ab21ef920f682a77599ffcd36d3c8`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

**Phương án A (khuyến nghị): gửi trực tiếp như GAP-012/013, xoá listener chết.**
- **Tạo NCR có người được giao:** người được giao (`assigned_to`) nhận `ncr_assigned`.
- **NCR chuyển "đã xử lý" (resolved):** người tạo (`created_by`) nhận `ncr_resolved` để kiểm tra.
- **NCR "đóng" (closed):** người được giao nhận `ncr_closed`.
- Không báo cho chính người thao tác. Lỗi gửi chỉ ghi log, không làm hỏng thao tác (giống GAP-013).
- Áp dụng cho cả API và trang web (trang web gọi cùng hàm).
- **Xoá `NcrEventListener`** (không chạy được, dùng sự kiện không tồn tại, ghi sai cột) và 14 mục phpstan-baseline của nó.

**Phương án B:** như A, nhưng **dựng lại 4 lớp sự kiện** và sửa listener để dùng (kiến trúc theo sự kiện). Nhiều code hơn,
cùng kết quả cho người dùng.

**Phương án C:** như A, nhưng khi tạo NCR báo thêm cho **PM dự án**.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. `app/Http/Controllers/Api/InspectionController.php`:
   - `storeNcr()`: after the NCR is created, notify `assigned_to` when set and different from the actor: type
     `ncr_assigned`.
   - `updateNcrStatus()`: after the update, on `resolved` notify `created_by`, on `closed` notify `assigned_to`, each when set
     and different from the actor: types `ncr_resolved`, `ncr_closed`. `in_progress` sends nothing.
   - One private helper: in-app `Notification` row (tenant, user, type, title with the NCR title, project, event key
     `ncr.assigned` / `ncr.resolved` / `ncr.closed`, data with the NCR and inspection ids); recipient check and creation
     inside try/catch that logs `ncr.notification_failed`.
2. Delete `app/Listeners/NcrEventListener.php` and its `phpstan-baseline.neon` entries.
3. New test `tests/Feature/Api/InspectionNcrNotificationTest.php`: create with assignee notifies the assignee; resolved
   notifies the creator; closed notifies the assignee; in_progress sends nothing; no self-notification; a refused
   transition sends nothing; a notification failure is logged and the request still succeeds.
4. Docs: plan, this packet, 03-release.

**Not changed:** NCR schema, NCR–CAPA link (GAP-014c), email, other modules.

## Owner amendment 1

After the Gate-3 approval at digest `5a24218c…`, Codex (P2, PR #372 review on `30527f7e`) reported that an NCR title may
be 255 characters while `notifications.title` is VARCHAR(255), so the prefixed notification title overflows on strict
MySQL and the notification is lost (only logged). The fix changes the tree, so the Owner was asked via AskUserQuestion and
chose, verbatim: "Sửa trong PR này (Recommended)". Change: the helper cuts the composed notification title to 255
characters with `mb_substr` (the NCR keeps its full title); new test
`test_max_length_ncr_title_is_cut_to_the_notification_title_column`. The earlier Gate-3 approval is superseded; Gate 3 is
re-presented.

## Verification

- New tests fail on the base and pass after; `InspectionNcrWorkflowTest` and `tests/Feature/Api` pass.
- PHPStan, php-cs-fixer, SSOT test lint, governance and docs lints; exact-head CI green.

## Rollback

Revert the squash commit.

## Decision Needed

Owner chọn: `APPROVE GAP-014b Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy; GAP-014c.
