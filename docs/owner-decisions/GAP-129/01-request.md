---
work_id: GAP-129
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-18-gap-129-admin-ui-evidence.md
  plan: null
  branch: docs/GAP-129-admin-ui
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-18T22:15:00+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-18, verbatim: 'APPROVE GAP-129 Gate 1'. Canonical base a3743ac6289e4ba4c21ee6d6352c4922a1628c81. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-18T22:10:00+07:00"
  updated_at: "2026-11-18T22:15:00+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-129 Gate 1 in-session on 2026-11-18. This authorizes preparation of Gate 2 only.

## Owner Summary

Anh/chị cần một **trang quản trị** để quản lý công ty, vai trò và tài khoản ngay trên web, không phải SSH vào máy chủ.

Hiện nay:
- **Tạo tài khoản, cấp lại mật khẩu:** chỉ làm được bằng lệnh `production:user` trên máy chủ.
- **Đổi vai trò, khoá tài khoản nhân viên nghỉ việc:** không có cách nào ngoài sửa thẳng database.
- **Xem vai trò có những quyền gì:** không có trang nào.
- Trang `/admin/users` và `/admin/tenants` có sẵn nhưng **toàn dữ liệu giả**, không đọc database.

## Vấn đề vận hành

- Mỗi lần có nhân viên mới hay nghỉ việc đều phải nhờ người biết SSH.
- Nhân viên nghỉ việc vẫn đăng nhập được cho tới khi có người sửa database.

## Người dùng bị ảnh hưởng

Owner (super_admin) của công ty.

## Bằng chứng

`docs/audits/2026-11-18-gap-129-admin-ui-evidence.md`.

## Phạm vi đề xuất

Gate 2 sẽ chọn:
- trang **Tài khoản** (danh sách; tạo mới; đổi vai trò; khoá / mở khoá; cấp lại mật khẩu);
- trang **Vai trò** (xem quyền từng vai trò; có cho sửa hay không);
- trang **Công ty** (sửa thông tin công ty mình; có quản lý nhiều công ty hay không);
- thay các trang giả hiện có.

## Loại trừ rõ ràng

- Gửi email mời / quên mật khẩu (máy chủ chưa có mail).
- Nhân sự & lương (đã có trang riêng).
- Deploy.

## Khả năng hoàn tác

Revert PR. Không đổi cấu trúc database dự kiến (Gate 2 xác nhận).

## Decision Needed

Owner chọn một: `APPROVE GAP-129 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Phương án chi tiết (Gate 2), merge, deploy.
