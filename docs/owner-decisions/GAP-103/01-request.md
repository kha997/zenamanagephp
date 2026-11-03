---
work_id: GAP-103
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-11-03-gap-103-project-health-read-model-evidence.md
  plan: null
  branch: docs/GAP-103-project-health-read-model
  pr: https://github.com/kha997/zenamanagephp/pull/393
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-03T16:19:28+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-03T16:19:28+07:00"
  updated_at: "2026-11-03T16:19:28+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Bước nền cho OPPM (issue #248): một **bộ đọc "sức khoẻ dự án" dùng chung** — tiến độ, trễ hạn, việc cần chú ý, chi phí /
dòng tiền — để OPPM và các màn hình tổng hợp sau này cùng dùng một công thức (SSOT One-Page OWN-2026-009 §4, slice 2).

Khảo sát cho thấy:
- **Tiến độ có 4 cách tính khác nhau**, không có cách chung. Con số "Tiến độ" đang hiện trên trang dự án lấy từ chi phí kế
  hoạch của hạng mục (component); dự án chưa có hạng mục thì hiện **0%** — không phân biệt được "chưa làm" với "không có dữ
  liệu" (SSOT cấm điều này).
- **Trễ hạn** đã có một nguồn tốt (so với kế hoạch gốc đã chốt, có trạng thái "chưa chốt kế hoạch").
- **Việc bị chặn / quá hạn** đang tính rải rác trong từng màn hình.
- **Chi phí / tiền**: nhiều con số khác bản chất (ngân sách, chi phí thực tế, giá trị hợp đồng, đã thu, chi phí phát sinh —
  không phải tiền đã chi; Ngân quỹ S1–S6 đã có).
- Dịch vụ phân tích dự án cũ (`ProjectAnalyticsService`) gọi 3 hàm không tồn tại nên API của nó sẽ lỗi — không dùng làm nền
  được.

## Vấn đề vận hành

Không có một con số tiến độ / sức khoẻ dự án đáng tin và thống nhất; OPPM không thể xây đúng SSOT nếu thiếu lớp này.

## Người dùng bị ảnh hưởng

Owner, PM; mọi màn hình tổng hợp tương lai (OPPM, danh mục dự án, Control Tower).

## Bằng chứng

`docs/audits/2026-11-03-gap-103-project-health-read-model-evidence.md`.

## Phạm vi đề xuất

Gate 2: thiết kế một dịch vụ đọc (không ghi, không migration nếu không cần) trả về sức khoẻ một dự án — định nghĩa tiến độ
chính thức (kèm độ tin cậy), trạng thái trễ (dùng lại `ProjectDelayStatus`), cờ cần chú ý, các số tiền tách bạch bản chất —
mỗi số có trạng thái "không có dữ liệu" rõ ràng; kèm phương án cho con số "Tiến độ" đang hiển thị và cho
`ProjectAnalyticsService` hỏng. Có thể tách nhiều phương án để Owner chọn.

## Loại trừ rõ ràng

Chưa làm trang OPPM (Work ID riêng sau); không làm danh mục dự án / Control Tower; không đổi luật Ngân quỹ; không deploy.

## Khả năng hoàn tác

Revert commit.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-103 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn công thức tiến độ, thiết kế, Gate 2, merge hay phát hành.
