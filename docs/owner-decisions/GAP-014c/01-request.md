---
work_id: GAP-014c
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-29-gap-014c-ncr-capa-link-evidence.md
  plan: null
  branch: docs/GAP-014c-ncr-capa-link
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T09:35:41+07:00"
  updated_at: "2026-10-29T09:35:41+07:00"
generated_by: agent
---

## Owner Summary

Khi một NCR (phiếu không phù hợp) cần khắc phục, hệ thống chỉ **gợi ý** nội dung công việc CAPA; ứng dụng gọi API phải tự
tạo task. Task tạo ra chỉ gắn một **nhãn chung** `inspection-ncr-capa`, **không lưu NCR nào**. Vì vậy: mở NCR không thấy task
khắc phục và tiến độ của nó; mở task không biết thuộc NCR nào; NCR có thể đóng khi task khắc phục còn dở. Trang web NCR
cũng không có nút tạo task CAPA.

## Vấn đề vận hành

Không truy vết được NCR → hành động khắc phục → kết quả, là yêu cầu cơ bản của quản lý chất lượng (kiểm tra, nghiệm thu,
audit).

## Người dùng bị ảnh hưởng

Kỹ sư hiện trường / QA-QC tạo và theo dõi NCR; quản lý dự án xem tiến độ khắc phục; người audit chất lượng.

## Bằng chứng

`docs/audits/2026-10-29-gap-014c-ncr-capa-link-evidence.md`: không có cột/quan hệ nối NCR với task; luồng tạo task CAPA hiện
tại (chỉ qua API, phía client); 2 nơi dùng nhãn chung (leo thang task quá hạn, dashboard kỹ sư hiện trường).

## Phạm vi đề xuất

Gate 2 thiết kế: lưu liên kết bền (cột trên task, cột trên NCR, hoặc bảng nối); cách tạo liên kết (API và/hoặc nút trên
trang web NCR); NCR hiển thị task CAPA; có chặn đóng NCR khi task CAPA chưa xong không; xử lý dữ liệu cũ; kế hoạch hoàn tác
migration.

## Loại trừ rõ ràng

Không đổi thông báo NCR (GAP-014b đã xong), dashboard (GAP-014a), quy trình kiểm tra; không deploy, không chạy migration
trên môi trường thật.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu. Gate 2 sẽ nêu cách hoàn tác migration.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-014c Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn cách lưu liên kết, chưa duyệt Gate 2, merge hay phát hành.
