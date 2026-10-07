---
work_id: GAP-067
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-08-gap-067-treasury-s4a-reconciliation-readiness.md
  plan: null
  branch: docs/GAP-067-treasury-s4a-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/340
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
  created_at: "2026-10-08T04:40:00+07:00"
  updated_at: "2026-10-08T04:40:00+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Lát **S4a — Đối soát** của Ngân quỹ (Issue #244): kế toán hoặc chủ doanh
nghiệp chọn một ví, tick các giao dịch khớp với sao kê ngân hàng / kiểm quỹ /
chứng từ, ghi số tham chiếu → các chứng từ đủ khớp chuyển **"đã đối soát"**;
gỡ đối soát phải ghi lý do và chứng từ quay về "chưa đối soát" (trừ chứng từ đã
đảo). Mỗi ví hiện số dư đã/chưa đối soát. Đề nghị phê duyệt Gate 1.

## Vấn đề vận hành

Mọi giao dịch từ S2/S3 đang ở "chưa đối soát" và không có cách xác nhận chúng
khớp với tiền thật trong tài khoản/quỹ.

## Người dùng bị ảnh hưởng

Kế toán, chủ doanh nghiệp (thao tác); PM/kỹ sư (chỉ xem trạng thái).

## Bằng chứng

`docs/audits/2026-10-08-gap-067-treasury-s4a-reconciliation-readiness.md`.

## Câu trả lời của Owner (trong phiên, 2026-10-08)

1. **Tách S4** thành S4a Đối soát (làm trước, Work ID này) và S4b Trung gian
   + truy vết thanh toán hợp đồng (Work ID sau).
2. Cho S4b (ghi nhận trước, chưa được duyệt ở đây): trung gian là **ví trung
   chuyển trong dự án**; chủ DN + kế toán ghi mọi chặng, PM/kỹ sư chỉ chặng ra
   hoặc vào ví mình giữ; truy vết thanh toán hợp đồng **tùy chọn**, có danh
   sách nhắc, khoản đã truy vết không được khai lại thành "tiền nhận".

## Phạm vi đề xuất

Như Owner Summary; quyền dùng mã `treasury.reconcile` đã duyệt ở S1 (chủ DN,
kế toán). Chi tiết kỹ thuật ở Gate 2.

## Loại trừ rõ ràng

Trung gian/tuyến tiền và truy vết thanh toán hợp đồng (S4b), tạm ứng (S5), báo
cáo (S6), nhập file sao kê, khoá kỳ; không sửa `ContractPayment`; không mở lại
thiết kế đã duyệt; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt Gate 1.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế chi tiết, merge hay phát hành; chưa duyệt S4b.
