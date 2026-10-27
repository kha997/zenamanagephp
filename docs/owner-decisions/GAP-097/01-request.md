---
work_id: GAP-097
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-27-gap-097-treasury-s6-dashboard-reports-readiness.md
  plan: null
  branch: docs/GAP-097-treasury-s6-dashboard-reports
  pr: https://github.com/kha997/zenamanagephp/pull/376
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
  created_at: "2026-10-27T18:13:36+07:00"
  updated_at: "2026-10-27T18:13:36+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Lát **S6 — Dashboard và báo cáo** của Ngân quỹ (Issue #244), lát cuối của chuỗi S1–S6.

Dữ liệu tiền đã đủ (nhận tiền, chuyển ví, chi phí, tuyến trung gian, đối soát, tạm ứng), nhưng chủ DN và kế toán
chưa có chỗ xem tổng quan, chưa lọc được sổ giao dịch và chưa xuất được báo cáo. S6 làm:

- **Dashboard từng dự án:** thẻ tổng (nhà đầu tư đã trả, tiền về qua trung gian, góp vốn chủ, chi phí, tiền đang giữ,
  tiền nhận chưa đối soát, chi phí chờ duyệt, tạm ứng còn nợ), bảng ví (người giữ, số dư, chưa đối soát, tạm ứng còn
  nợ, lần phát sinh cuối) và **cảnh báo**.
- **Bảng tổng các dự án** ở trang Ngân quỹ chung.
- **Sổ giao dịch có bộ lọc** (ngày, loại, trạng thái, ví, đối tác, người lập, người duyệt, tự duyệt, đối soát).
- **Dòng thời gian tài chính** của dự án ("A trả 100.000.000 ₫ cho X", "X chuyển 50.000.000 ₫ cho Y"…).
- **Báo cáo + xuất CSV** (chỉ những báo cáo đã có dữ liệu).

## Vấn đề vận hành

Muốn biết dự án đang giữ bao nhiêu, ai giữ, khoản nào chưa đối soát hay tạm ứng nào quá lâu, phải đọc từng thẻ và
sổ giao dịch (chỉ 100 dòng mới nhất); không xuất được số liệu.

## Người dùng bị ảnh hưởng

Chủ DN và kế toán (xem, xuất báo cáo); PM / kỹ sư (xem dashboard dự án mình).

## Bằng chứng

`docs/audits/2026-10-27-gap-097-treasury-s6-dashboard-reports-readiness.md`: dữ liệu S1–S5 đã có; quyền
`treasury.export` đã có nhưng chưa dùng; cách xuất CSV có sẵn ở trang Báo cáo.

## Câu trả lời của Owner (trong phiên, 2026-10-27)

1. **Báo cáo:** chỉ báo cáo đã có dữ liệu — dòng tiền dự án, số dư ví, tiền theo người giữ, tuổi nợ tạm ứng, chi phí
   theo loại, chi phí theo đối tác, chi phí tự duyệt, giao dịch chưa đối soát, tiền đang qua trung gian, đảo / điều
   chỉnh. Bằng chứng, BOQ, chuỗi quỹ để lát sau.
2. **Xuất file:** CSV như báo cáo hiện có.
3. **Ngưỡng cảnh báo:** cố định — tiền nhận chưa đối soát quá 30 ngày; tạm ứng quá hạn = còn nợ trên 90 ngày.
4. **Dashboard:** từng dự án + bảng tổng các dự án.

## Phạm vi đề xuất

Như Owner Summary, theo spec §10.1, §10.2, §10.7, §11 (phần có dữ liệu). Chỉ đọc: không đổi cách ghi sổ, không đổi
lược đồ. Chi tiết (quyền, API, trang) ở Gate 2.

## Loại trừ rõ ràng

Chứng từ đính kèm / thiếu chứng từ; BOQ và chênh lệch ngân sách; chuỗi quỹ; công nợ nhà đầu tư theo giá trị hợp đồng;
ngưỡng cấu hình được; file Excel; biểu đồ; không đổi lược đồ; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt Gate 1.

## Decision Needed

Owner chọn một: `APPROVE GAP-097 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế chi tiết, merge hay phát hành.
