---
work_id: GAP-021
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-28-gap-021-tasks-api-parity-evidence.md
  plan: null
  branch: docs/GAP-021-tasks-api-parity
  pr: https://github.com/kha997/zenamanagephp/pull/378
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
  created_at: "2026-10-28T19:20:14+07:00"
  updated_at: "2026-10-28T19:20:14+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

API công việc cũ (`/api/v1/tasks`) và API chuẩn (`/api/zena/tasks`) khác nhau 3 điểm. Đã kiểm tra lại cả 3 trên `main`:

- **Lỗi thật:** sửa công việc qua API cũ đòi quyền `task.edit`, nhưng **quyền này không được tạo ở đâu**. Kết quả: chỉ
  super admin sửa được; người có quyền "sửa công việc" (`task.update`) bị từ chối 403. API chuẩn với cùng người thì sửa được.
  3 route của mẫu công việc (sửa, cập nhật tiến độ, bật/tắt điều kiện) cũng bị y hệt.
- **Khác biệt bảo vệ:** API cũ thiếu bước làm sạch dữ liệu đầu vào và định dạng lỗi chuẩn mà API chuẩn có.
- **Khác dạng dữ liệu trả về:** cố ý giữ để tương thích, đã có test khoá; không đề xuất đổi.

## Vấn đề vận hành

Ai dùng API cũ hoặc màn hình mẫu công việc để sửa việc sẽ bị chặn dù có quyền. Không phải lỗ hổng (chặn nhiều hơn, không
cho thêm quyền).

## Người dùng bị ảnh hưởng

Người không phải super admin có quyền sửa công việc, khi gọi API cũ hoặc API mẫu công việc. Hiện không có trang web nào
đang dùng API cũ để sửa (trang `tasks/form` không được nối). Khách hàng ngoài: chưa rõ.

## Bằng chứng

`docs/audits/2026-10-28-gap-021-tasks-api-parity-evidence.md` (gồm lần chạy thử: API cũ 403, API chuẩn 200).

## Phạm vi đề xuất

Gate 2 thiết kế: cách sửa quyền (đổi route sang `task.update`, hay tạo thêm quyền `task.edit`), có bổ sung 2 middleware
cho API cũ hay không, và test chứng minh đỏ trước / xanh sau.

## Loại trừ rõ ràng

Không đổi dạng dữ liệu trả về của API cũ; không gộp 4 controller giao việc; không xoá API cũ; không deploy.

## Khả năng hoàn tác

Revert một commit.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-021 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn cách sửa, chưa duyệt Gate 2, merge hay phát hành.
