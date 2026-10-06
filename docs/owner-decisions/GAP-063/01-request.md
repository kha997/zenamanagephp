---
work_id: GAP-063
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-06-gap-063-treasury-runtime-readiness-audit.md
  plan: null
  branch: docs/GAP-063-treasury-runtime-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/336
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-06T18:34:17+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-06: 'APPROVE GAP-063 Gate 1'. Bound to reviewed Draft PR #336 head cf70fcfab3b3b6940b1cbce1c990d29e2653b1b0, canonical base f652ceebfa5f641207138243a4a8e3eba7a2909c, including the Owner's in-session business answers recorded in this packet (Admin/super_admin = X with self-approval, Finance = accountant, PM + SiteEngineer = Z; project wallets only in S1; web UI from S1). Authorizes preparing Gate 2 for slice S1 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-06T18:23:59+07:00"
  updated_at: "2026-10-06T18:34:17+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-063 Gate 1 in-session on 2026-10-06 against Draft PR #336
head `cf70fcfab3b3b6940b1cbce1c990d29e2653b1b0`. This authorizes preparation of Gate 2 for slice S1 only.

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

## Câu trả lời của Owner (trong phiên, 2026-10-06)

1. **Vai trò:** Admin (và super_admin) = chủ doanh nghiệp X, được tự duyệt chi;
   Finance = kế toán; PM và SiteEngineer = kỹ sư/chỉ huy Z (không tự duyệt). Các
   vai trò khác chỉ xem khi là thành viên dự án.
2. **Ví:** chỉ ví theo dự án trong S1; ví dùng chung công ty để lát sau.
3. **Giao diện:** có màn hình web ngay từ S1 (kèm API).

## Loại trừ rõ ràng

Không mở lại thiết kế đã duyệt (A–D, v17); không sửa
`ReportPageController::cashflow()`, `ContractPayment`, chi phí
Component/Project; không AI/OCR/ngân hàng tự động; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt Gate 1 (S1); Gate 2 sẽ thiết kế theo 3 câu trả lời trên.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế chi tiết S1, các lát S2–S6, merge hay phát hành.
