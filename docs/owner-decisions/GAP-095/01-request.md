---
work_id: GAP-095
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-27-gap-095-treasury-s4b2-contract-payment-tracing-readiness.md
  plan: null
  branch: docs/GAP-095-treasury-s4b2-contract-payment-tracing
  pr: https://github.com/kha997/zenamanagephp/pull/374
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
  created_at: "2026-10-27T09:27:52+07:00"
  updated_at: "2026-10-27T09:27:52+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Lát **S4b-2 — Truy vết tiền thanh toán hợp đồng** của Ngân quỹ (Issue #244), phần còn lại của S4b sau GAP-094.

Khi chủ đầu tư trả một đợt thanh toán hợp đồng, đợt đó được đánh dấu "đã thanh toán" ở trang hợp đồng. Hiện chưa
có cách cho biết **tiền đó đang nằm ở đâu**: vào TK công ty, qua người mang, rồi về quỹ công trường… S4b-2 cho phép
(tùy chọn) gắn đợt thanh toán đó vào một **tuyến tiền** như S4b-1:

- Chọn một đợt thanh toán hợp đồng **đã thanh toán** của dự án, chọn ví đích; ghi các chặng như S4b-1. Một đợt có thể
  chia nhiều tuyến, tổng không vượt số tiền đợt.
- **Danh sách nhắc** các đợt đã thanh toán mà chưa truy vết đủ.
- **Không chặn gì phía hợp đồng**; trang hợp đồng, báo cáo dòng tiền giữ nguyên. Ngân quỹ không bao giờ sửa đợt thanh
  toán.
- Tiền đã truy vết **không được khai lại thành "tiền nhận"** (tránh cộng hai lần).

## Vấn đề vận hành

Tiền chủ đầu tư trả theo hợp đồng làm tăng tiền dự án thực tế, nhưng ngân quỹ không thấy: số dư các ví thiếu khoản
này, hoặc phải khai thêm "tiền nhận" (dễ trùng với số liệu hợp đồng).

## Người dùng bị ảnh hưởng

Chủ doanh nghiệp, kế toán (gắn tuyến, ghi chặng); PM/kỹ sư (ghi chặng ra hoặc vào ví mình giữ, như S4b-1).

## Bằng chứng

`docs/audits/2026-10-27-gap-095-treasury-s4b2-contract-payment-tracing-readiness.md`: quy tắc B2-T và v17 §4.1 Case A
đã duyệt; bảng tuyến đã có cột gắn đợt thanh toán; chưa có chỗ nào dùng.

## Câu trả lời của Owner đã có (2026-10-08, ghi ở GAP-067)

1. Trung gian là ví trung chuyển trong dự án.
2. Chủ DN và kế toán ghi mọi chặng; PM/kỹ sư chỉ chặng ra hoặc vào ví mình giữ.
3. Truy vết thanh toán hợp đồng **tùy chọn**, có danh sách nhắc, không chặn phía hợp đồng; tiền đã truy vết không khai
   lại thành "tiền nhận".

## Phạm vi đề xuất

Như Owner Summary, theo B2-T và GAP-037 v17 §4.1 Case A, §4.2a, §4.3. Không đổi lược đồ. Chi tiết (cách chặn khai
trùng, đối soát, quyền, trang) ở Gate 2.

## Loại trừ rõ ràng

Tạm ứng (S5), báo cáo (S6); không sửa `ContractPayment`, trang hợp đồng, báo cáo dòng tiền/KPI; không nhập sao kê,
khoá kỳ; không đổi lược đồ; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt Gate 1.

## Decision Needed

Owner chọn một: `APPROVE GAP-095 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế chi tiết, merge hay phát hành; chưa duyệt S5, S6.
