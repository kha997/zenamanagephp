---
work_id: GAP-064
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-07-gap-064-treasury-s2-ledger-readiness.md
  plan: null
  branch: docs/GAP-064-treasury-s2-ledger
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
  created_at: "2026-10-07T00:14:38+07:00"
  updated_at: "2026-10-07T00:14:38+07:00"
generated_by: agent
---

## Owner Summary

Lát **S2 — Ghi sổ** của Ngân quỹ (Issue #244): khai báo tiền nhận và góp vốn
chủ (ghi sổ ngay, trạng thái "chưa đối soát"), chuyển tiền giữa hai ví (ghi
sổ ngay, không duyệt), điều chỉnh số dư (bắt buộc lý do), đảo bút toán khi nhập
sai (kèm liên kết chứng từ thay thế), xem số dư từng ví và toàn dự án, sổ giao
dịch trên trang Ngân quỹ, nhật ký thao tác. Tuân thủ toàn bộ quy tắc của thiết
kế đã duyệt (v17). Đề nghị phê duyệt Gate 1.

## Vấn đề vận hành

Đã có ví nhưng chưa ghi nhận được đồng tiền nào; không có số dư.

## Người dùng bị ảnh hưởng

Chủ doanh nghiệp, kế toán, PM/kỹ sư giữ tiền dự án.

## Bằng chứng

`docs/audits/2026-10-07-gap-064-treasury-s2-ledger-readiness.md`.

## Câu trả lời của Owner (trong phiên, 2026-10-07)

1. **Thêm "ngày giao dịch" (bắt buộc) và "số tham chiếu" (tuỳ chọn)** cho chứng
   từ — một migration chỉ thêm cột; có cảnh báo khai trùng (cùng dự án, số tiền,
   ngày, nguồn, đích, tham chiếu).
2. **Chuyển ví:** PM/kỹ sư (Z) chỉ chuyển từ ví mà mình là người giữ (đối tác
   người giữ liên kết tới tài khoản của Z); chủ doanh nghiệp (X) chuyển từ mọi
   ví của dự án; kế toán không chuyển tiền.
3. **Điều chỉnh có trong S2**: tăng/giảm số dư ví (nhập số dư đầu kỳ, chênh lệch
   kiểm quỹ), bắt buộc lý do, chỉ X và kế toán.

## Phạm vi đề xuất

Như Owner Summary; chi tiết kỹ thuật ở Gate 2.

## Loại trừ rõ ràng

Chi phí và duyệt chi (S3), thanh toán qua trung gian và đối soát (S4), tạm ứng
(S5), dashboard/báo cáo (S6), ví chung công ty; không mở lại thiết kế đã duyệt;
không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt Gate 1.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế chi tiết, merge hay phát hành.
