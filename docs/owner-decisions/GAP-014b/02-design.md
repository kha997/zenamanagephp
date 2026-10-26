---
work_id: GAP-014b
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-26-gap-014b-ncr-notifications-evidence.md
  plan: null
  branch: docs/GAP-014b-ncr-notifications
  pr: https://github.com/kha997/zenamanagephp/pull/372
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-26T10:50:05+07:00"
  updated_at: "2026-10-26T10:50:05+07:00"
generated_by: agent
---

# GAP-014b — NCR notifications: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

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

## Verification

- New tests fail on the base and pass after; `InspectionNcrWorkflowTest` and `tests/Feature/Api` pass.
- PHPStan, php-cs-fixer, SSOT test lint, governance and docs lints; exact-head CI green.

## Rollback

Revert the squash commit.

## Decision Needed

Owner chọn: `APPROVE GAP-014b Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy; GAP-014c.
