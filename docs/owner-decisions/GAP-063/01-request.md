---
work_id: GAP-063
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-06-gap-063-treasury-runtime-readiness-audit.md
  plan: null
  branch: docs/GAP-063-treasury-runtime-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/336
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
  created_at: "2026-10-06T18:23:59+07:00"
  updated_at: "2026-10-06T18:23:59+07:00"
generated_by: agent
---

## Owner Summary

Ngân quỹ dự án (Treasury, Issue #244) đã có **thiết kế được duyệt** (GAP-037,
schema v17) và **14 bảng dữ liệu trên main** (GAP-038), nhưng **chưa có bất kỳ
chức năng nào người dùng dùng được**: chưa có quyền, màn hình, hay nghiệp vụ ghi
sổ. Đề xuất chia phần triển khai thành 6 lát, mỗi lát một Work ID, duyệt và phát
hành riêng: (S1) nền tảng — quyền, đối tác, ví; (S2) ghi sổ — nhận tiền, chuyển
nội bộ, đảo bút toán, số dư; (S3) chi phí và duyệt chi; (S4) thanh toán qua
trung gian và đối soát; (S5) tạm ứng và quyết toán; (S6) dashboard và báo cáo.
Work ID này (GAP-063) là **lát S1**. Đề nghị phê duyệt Gate 1 và trả lời 3 câu
hỏi nghiệp vụ ở dưới để thiết kế S1.

## Vấn đề vận hành

Không ai ghi nhận được tiền vào/ra, ví, tạm ứng của dự án trong hệ thống; thiết
kế đã duyệt từ 2026-08 chưa được đưa vào sử dụng.

## Người dùng bị ảnh hưởng

Chủ doanh nghiệp, kế toán, chỉ huy công trình/kỹ sư giữ tiền dự án.

## Bằng chứng

`docs/audits/2026-10-06-gap-063-treasury-runtime-readiness-audit.md`.

## Phạm vi đề xuất (S1 — nền tảng)

15 mã quyền `treasury.*` + quyền mặc định theo vai trò; kiểm tra quyền theo
tenant và thành viên dự án; quản lý đối tác tài chính và ví; tab "Ngân quỹ"
trong dự án (khung trống cho các lát sau). Chưa có ghi sổ tiền.

## Câu hỏi nghiệp vụ cho Owner (cần trước Gate 2)

1. **Vai trò:** "Chủ doanh nghiệp X" (được tự duyệt chi) là vai trò nào; "kế
   toán" là vai trò nào; "kỹ sư/chỉ huy Z" là vai trò nào?
2. **Ví dùng chung công ty** (không thuộc dự án nào): dùng ngay từ S1 hay chỉ ví
   theo dự án trước?
3. **Giao diện:** có màn hình web ngay từ S1, hay làm API trước?

## Loại trừ rõ ràng

Không mở lại thiết kế đã duyệt (A–D, v17); không sửa
`ReportPageController::cashflow()`, `ContractPayment`, chi phí
Component/Project; không AI/OCR/ngân hàng tự động; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt Gate 1 (S1) và trả lời 3 câu hỏi.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế chi tiết S1, các lát S2–S6, merge hay phát hành.
