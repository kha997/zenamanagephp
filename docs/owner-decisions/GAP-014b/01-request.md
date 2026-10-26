---
work_id: GAP-014b
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-26-gap-014b-ncr-notifications-evidence.md
  plan: null
  branch: docs/GAP-014b-ncr-notifications
  pr: https://github.com/kha997/zenamanagephp/pull/372
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-26T10:49:45+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-26, verbatim: 'APPROVE GAP-014b Gate 1'. Bound to reviewed Draft PR #372 head 641744a292aa4e312854527f400bac0a227006ef, canonical base 681043875ecda4c0cd19a19908c2dfbb41031194. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-26T10:19:28+07:00"
  updated_at: "2026-10-26T10:49:45+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-014b Gate 1 in-session on 2026-10-26 against Draft PR #372
head `641744a292aa4e312854527f400bac0a227006ef`. This authorizes preparation of Gate 2 only.

## Owner Summary

Việc cuối của nhóm thông báo, sau GAP-012 và GAP-013. Kiểm tra lại code:

- Tạo NCR và đổi trạng thái NCR (đang xử lý → đã xử lý → đóng) **không gửi thông báo nào**. Trang web và API dùng chung
  một chỗ xử lý.
- Có sẵn một file listener viết thông báo cho NCR, nhưng **không bao giờ chạy được**:
  - 4 lớp sự kiện nó dùng **không tồn tại** trong code;
  - không nơi nào phát sự kiện, cũng không đăng ký listener;
  - kể cả nếu chạy, nó ghi vào các cột không có (`message`, `channels`) và báo cho **mọi thành viên mọi nhóm** dự án.
- Hậu quả: người được giao NCR không biết, người tạo không biết khi NCR đã xử lý xong.

## Vấn đề vận hành

NCR (lỗi chất lượng) dễ bị bỏ sót, xử lý chậm.

## Người dùng bị ảnh hưởng

Người được giao NCR, người tạo NCR, PM dự án.

## Phạm vi đề xuất

Gate 2 thiết kế: ai nhận thông báo ở từng bước, gửi trực tiếp như GAP-012/013 hay dựng lại các sự kiện, và xử lý file
listener chết (sửa hoặc xoá).

## Loại trừ rõ ràng

Không sửa code trong Gate 1. Không làm GAP-014c (liên kết NCR – CAPA). Không gửi email. Không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, danh sách người nhận, sửa code, merge, phát hành hay deploy.
