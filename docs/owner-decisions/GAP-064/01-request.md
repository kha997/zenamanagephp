---
work_id: GAP-064
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-07-gap-064-treasury-s2-ledger-readiness.md
  plan: null
  branch: docs/GAP-064-treasury-s2-ledger
  pr: https://github.com/kha997/zenamanagephp/pull/337
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-07T07:57:42+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-07: 'APPROVE GAP-064 Gate 1'. Bound to reviewed Draft PR #337 head b3571a34aba99ef14a4e01dc97dcec04627f3715, canonical base fbdc7b1c0fb1b6594e5216495121429659b60c35, including the Owner's in-session business answers recorded in this packet (transaction date + reference columns with duplicate warning; Z transfers only from wallets Z holds, X from any project wallet, accountant does not transfer; adjustments in S2, reason required, X and accountant only). Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-07T00:14:38+07:00"
  updated_at: "2026-10-07T07:57:42+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-064 Gate 1 in-session on 2026-10-07 against Draft PR #337
head `b3571a34aba99ef14a4e01dc97dcec04627f3715`. This authorizes preparation of Gate 2 only.

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
