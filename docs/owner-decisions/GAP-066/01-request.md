---
work_id: GAP-066
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-07-gap-066-treasury-s3-expenses-readiness.md
  plan: null
  branch: docs/GAP-066-treasury-s3-expenses
  pr: https://github.com/kha997/zenamanagephp/pull/339
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
  created_at: "2026-10-07T17:54:27+07:00"
  updated_at: "2026-10-07T17:54:27+07:00"
generated_by: agent
---

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
