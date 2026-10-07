---
work_id: GAP-066
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-07-gap-066-treasury-s3-expenses-readiness.md
  plan: null
  branch: docs/GAP-066-treasury-s3-expenses
  pr: https://github.com/kha997/zenamanagephp/pull/339
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-07T18:35:20+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-07: 'APPROVE GAP-066 Gate 1'. Bound to reviewed Draft PR #339 head 602c5a7fd35aac64ea5f1868fefdcecb9e79708a, canonical base 7e783c675b49314c5201d1781c8fdec478b4accd, including the Owner's in-session business answers recorded in this packet (new ContractExpense created atomically when no cost record exists; approval posts immediately and is balance-guarded; rejected is terminal with copy-to-new-draft; Z spends only from wallets Z holds). Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-07T17:54:27+07:00"
  updated_at: "2026-10-07T18:35:20+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-066 Gate 1 in-session on 2026-10-07 against Draft PR #339
head `602c5a7fd35aac64ea5f1868fefdcecb9e79708a`. This authorizes preparation of Gate 2 only.

## Owner Summary

Lát **S3 — Chi phí và duyệt chi** của Ngân quỹ (Issue #244): PM/kỹ sư tạo khoản
chi (nháp → gửi duyệt), chủ doanh nghiệp hoặc kế toán duyệt — **duyệt là ghi sổ
và trừ ví ngay** —, chủ doanh nghiệp được tự duyệt khoản mình tạo (ghi rõ và báo
cáo riêng), mỗi khoản chi **gắn với chi phí đã ghi nhận** (chi phí hợp đồng hoặc
dòng phiếu nhập vật tư, có thể chia nhiều phần, không vượt số chi phí), đảo khoản
chi đã ghi sổ thì gỡ luôn phần gắn chi phí. Đề nghị phê duyệt Gate 1.

## Vấn đề vận hành

Tiền đã chi ra cho chi phí dự án chưa được ghi nhận ở đâu (S2 chỉ có tiền vào,
chuyển ví, điều chỉnh).

## Người dùng bị ảnh hưởng

PM/kỹ sư giữ tiền, chủ doanh nghiệp, kế toán.

## Bằng chứng

`docs/audits/2026-10-07-gap-066-treasury-s3-expenses-readiness.md`.

## Câu trả lời của Owner (trong phiên, 2026-10-07)

1. **Chi chưa có chi phí ghi sẵn:** trong form chi chọn một hợp đồng của dự án +
   loại (nhân công/thầu phụ/thiết kế thuê ngoài/khác); hệ thống tạo dòng chi phí
   hợp đồng và gắn khoản chi vào đó **trong cùng một thao tác**. Dự án chưa có hợp
   đồng thì phải tạo hợp đồng trước.
2. **Duyệt là ghi sổ luôn**; không đủ tiền trong ví thì không duyệt được.
3. **Bị từ chối là trạng thái cuối** (đúng v17); có nút "Sao chép thành nháp mới".
4. **PM/kỹ sư chỉ chi từ ví mình giữ**; chủ doanh nghiệp chi từ mọi ví.

## Phạm vi đề xuất

Như Owner Summary; chi tiết kỹ thuật ở Gate 2.

## Loại trừ rõ ràng

Tạm ứng và quyết toán (S5), trung gian + đối soát (S4), báo cáo (S6); không sửa
`ContractPayment`, `ReportPageController::cashflow()`, chi phí
Component/Project; không mở lại thiết kế đã duyệt; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt Gate 1.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế chi tiết, merge hay phát hành.
