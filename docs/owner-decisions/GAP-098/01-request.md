---
work_id: GAP-098
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-28-gap-098-unseeded-v1-permissions-evidence.md
  plan: null
  branch: docs/GAP-098-unseeded-v1-permissions
  pr: null
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
  created_at: "2026-10-28T20:51:43+07:00"
  updated_at: "2026-10-28T20:51:43+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

**69 route API cũ (`/api/v1`) đòi 32 mã quyền mà hệ thống không tạo ra**, nên chỉ super admin dùng được; mọi vai trò
khác (kể cả Admin công ty, PM) bị từ chối 403. Cùng loại lỗi GAP-021 vừa sửa cho công việc.

Sáu nhóm bị ảnh hưởng:

- **Baseline tiến độ** (13 route) — không có mã quyền baseline nào được tạo.
- **Change request API cũ** (7 route) — dùng `change_request.view/edit/...`, trong khi quyền được tạo là
  `change_request.read/update/...`.
- **Compensation** (8 route) — không có mã nào được tạo.
- **Quản trị vai trò / quyền** (28 route) — dùng `role.view/create/...`, `permission.*`, `user.view`, `audit.view`;
  hệ thống chỉ tạo `role.manage`, `user.read`, `user.manage`…
- **Sửa dự án / hạng mục API cũ** (3 route) — `project.edit`, `component.edit` thay vì `project.update`,
  `component.update`.
- **Mẫu công việc API cũ** (10 route) — `template.create/edit` thay vì các mã `template.*` đã có.

## Vấn đề vận hành

Ai dùng các API này mà không phải super admin đều bị chặn, dù vai trò của họ có quyền tương ứng. Không phải lỗ hổng (chặn
nhiều hơn, không cho thêm quyền).

## Người dùng bị ảnh hưởng

Người không phải super admin gọi API cũ. Hiện **không trang web nào đang dùng** các route này (các view gọi chúng không được
nối). Đã có API chuẩn thay thế cho change request, dự án, mẫu công việc; **chưa có** cho baseline, compensation, quản trị
quyền. Khách hàng ngoài: chưa rõ.

## Bằng chứng

`docs/audits/2026-10-28-gap-098-unseeded-v1-permissions-evidence.md`.

## Phạm vi đề xuất

Gate 2: với từng nhóm, chọn đổi route sang mã quyền đã có (khi có mã cùng nghĩa) hay tạo mã mới và gán cho vai trò nào;
test đỏ trước / xanh sau với user không phải super admin.

## Loại trừ rõ ràng

Không xoá API cũ; không đổi controller hay dạng dữ liệu trả về; không đụng `rbac:admin` (nhóm universal-frame,
performance, final-integration — thuộc GAP-018/019); không deploy.

## Khả năng hoàn tác

Revert commit; nếu Gate 2 chọn tạo mã quyền mới thì kèm cách gỡ dữ liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-098 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn cách sửa cho từng nhóm, chưa duyệt Gate 2, merge hay phát hành.
