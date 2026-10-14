---
work_id: GAP-079
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-14-gap-079-roleseeder-permission-name-evidence.md
  plan: null
  branch: docs/GAP-079-roleseeder-permission-name
  pr: https://github.com/kha997/zenamanagephp/pull/352
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-14T22:26:16+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-14T22:26:16+07:00"
  updated_at: "2026-10-14T22:26:16+07:00"
generated_by: agent
---

## Owner Summary

`RoleSeeder` tạo quyền `project.read` và `user.manage` mà không đặt cột
`name`. Sau khi chạy toàn bộ seed, 2 quyền này có `name = NULL` (AUD-28,
đã tái hiện lại trên main).

Hậu quả: vai trò `Project Manager` có quyền `project.read`, nhưng
`User::hasPermission('project.read')` (kiểm tra theo `name`) trả về sai.
Test audit hiện phải bỏ qua 2 quyền này.

Đề xuất: phê duyệt Gate 1 để thiết kế cách sửa seeder và dữ liệu đã seed.

## Vấn đề vận hành

Kiểm tra quyền theo tên cho `project.read` luôn thất bại; từng gây lỗi test
ở GAP-044. Database đã seed trước đây vẫn giữ 2 dòng NULL.

## Người dùng bị ảnh hưởng

Người dùng có quyền `project.read` khi đoạn code kiểm tra theo tên; quản trị
viên xem danh sách quyền (tên trống). Kiểm tra theo `code` không bị ảnh
hưởng.

## Phạm vi đề xuất

Gate 2 thiết kế: sửa `RoleSeeder` đặt `name`; có thêm migration điền
`name = code` cho dòng NULL đã có hay không; xử lý `user.manage` (không còn
dùng); bỏ ngoại lệ trong test audit.

## Loại trừ rõ ràng

Không đổi cách kiểm tra quyền (`name`/`code`), không gộp các seeder, không
đổi quyền của vai trò; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, sửa code, merge, phát hành hay deploy.
