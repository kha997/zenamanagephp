---
work_id: GAP-077
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-14-gap-077-dead-controllers-evidence.md
  plan: null
  branch: docs/GAP-077-dead-controllers
  pr: https://github.com/kha997/zenamanagephp/pull/350
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-14T15:37:15+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-14, verbatim: 'APPROVE GAP-077 Gate 1'. Bound to reviewed Draft PR #350 head 0958a04c67f36b57cd10b9971ffda98d36b88eef, canonical base 9d794197d7a97bcfa2c79852053979ee995f5dd6. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-14T08:27:31+07:00"
  updated_at: "2026-10-14T15:37:15+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-077 Gate 1 in-session on 2026-10-14 against Draft PR #350
head `0958a04c67f36b57cd10b9971ffda98d36b88eef`. This authorizes preparation of Gate 2 only.

## Owner Summary

Có 5 file **không route nào dùng tới** (khoảng 820 dòng):

- `app/Http/Controllers/InteractionLogController.php` và 2 Request đi kèm
  (`StoreInteractionLogRequest`, `UpdateInteractionLogRequest`) — bản cũ; tất
  cả API interaction-logs đang dùng bản trong `src/InteractionLogs/`. File cũ còn
  gọi tới class không tồn tại.
- `app/Http/Controllers/Admin/BasicSidebarController.php` (ghi chú "No auth
  check for testing") và view `admin/simple-sidebar-builder.blade.php` chỉ nó
  dùng.

Đề xuất: phê duyệt Gate 1 để thiết kế việc xoá các file này và các dòng
PHPStan baseline tương ứng.

## Vấn đề vận hành

Code chết gây nhầm lẫn (2 controller cùng tên), làm PHPStan/bot phải bỏ qua
lỗi của nó, và có thể vô tình được nối route lại (controller không kiểm tra
quyền).

## Người dùng bị ảnh hưởng

Không ai — các file không được gọi tới. Chỉ ảnh hưởng người bảo trì code.

## Phạm vi đề xuất

Gate 2 thiết kế: xoá 5 file trên và các dòng baseline của chúng; kiểm chứng
bằng `route:list` không đổi, PHPStan sạch, toàn bộ test.

## Loại trừ rõ ràng

`SimpleSidebarBuilderController`/`SidebarBuilderController` (cũng không có
route nhưng có test nhắc tới — để việc riêng), code trong `src/`, các script
lịch sử, deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, xoá file, merge, phát hành hay deploy.
