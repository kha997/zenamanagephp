---
work_id: GAP-073
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-08-gap-073-phpstan-2-3-evidence.md
  plan: null
  branch: docs/GAP-073-phpstan-2-3
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T22:12:15+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T22:12:15+07:00"
  updated_at: "2026-10-08T22:12:15+07:00"
generated_by: agent
---

## Owner Summary

Nâng PHPStan từ 2.2.5 lên 2.3 (việc được tách ra từ GAP-072). Bản 2.3 kiểm kỹ
hơn và báo **10 lỗi** ở code có sẵn:

- **6 lỗi** ở 2 file có biến chưa khai báo (`BasicSidebarController`,
  `UpdateInteractionLogRequest`). Đây là bug thật, **nhưng cả hai đều không
  được route nào gọi tới** (API thật dùng lớp khác trong `src/InteractionLogs`).
  Hiện không ảnh hưởng người dùng.
- **3 lỗi** `use ($validator)` thừa trong closure ở `SettingsController` (vô
  hại).
- **1 lỗi** kiểu tham số ở `Redis::set(..., 'EX', 60)` trong kiểm tra sức khoẻ
  hệ thống; khi chạy thật Laravel vẫn hiểu đúng, có thể đổi sang `setex` cho rõ.

Đề xuất: phê duyệt Gate 1 để thiết kế cách nâng PHPStan và xử lý 10 lỗi (không
che lỗi bằng baseline).

## Vấn đề vận hành

Không nâng được công cụ kiểm tra tĩnh; bug thật đang bị ẩn trong baseline.

## Người dùng bị ảnh hưởng

Hiện không có: các bug nằm trong code không được gọi tới. Thay đổi ở
`SettingsController` và `HealthCheckService` giữ nguyên hành vi.

## Bằng chứng

`docs/audits/2026-10-08-gap-073-phpstan-2-3-evidence.md`.

## Phạm vi đề xuất

Gate 2 thiết kế: nâng `phpstan/phpstan` 2.3, xử lý 10 lỗi (sửa hoặc xoá code
không dùng — Owner chọn), cập nhật baseline cho khớp, chạy đủ test và CI.

## Loại trừ rõ ràng

Không xử lý các lỗi khác trong baseline; không nâng phiên bản chính các gói
khác; không đổi CI; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, thay đổi code, merge, phát hành hay deploy.
