---
work_id: GAP-017
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-25-gap-017-invitation-dead-ends-evidence.md
  plan: null
  branch: docs/GAP-017-invitation-decline
  pr: https://github.com/kha997/zenamanagephp/pull/367
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
  created_at: "2026-10-25T08:59:47+07:00"
  updated_at: "2026-10-25T08:59:47+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Owner chọn làm GAP-017 trước. Khi kiểm tra lại code, mình thấy mô tả trong register đã cũ:

- **Trang "lời mời hết hạn" bị thiếu không còn ai chạm tới.** Hàm gọi view đó không còn route nào trỏ vào; trang nhận lời
  mời hiện dùng controller khác.
- **Lỗi thật đang có: link từ chối lời mời luôn trả lỗi 500.** `GET /invitations/decline/{token}` trỏ tới hàm `decline`
  không tồn tại (và chưa từng tồn tại). Link cũ `/invite/decline/{token}` chuyển hướng về đúng link này. Không cần đăng
  nhập, ai mở link cũng gặp 500.
- Trong repo không có email hay trang nào dẫn tới link từ chối.
- Lời mời đã hết hạn vẫn hiện trang nhận lời mời bình thường, có ghi ngày hết hạn; bấm nhận sẽ báo lỗi, không bị 500.

## Vấn đề vận hành

Một URL công khai luôn trả 500, làm bẩn log lỗi và tạo trải nghiệm hỏng nếu link này từng được gửi cho ai.

## Người dùng bị ảnh hưởng

Người được mời, nếu có link từ chối; đội vận hành đọc log lỗi.

## Phạm vi đề xuất

Gate 2 thiết kế một trong hai hướng: làm chức năng từ chối lời mời thật, hoặc xoá route từ chối và link chuyển hướng cũ
vì không còn gì dẫn tới. Kèm test chặn hồi quy, và xử lý hai hàm không còn route (`accept`, `processAcceptance`).

## Loại trừ rõ ràng

Không sửa code trong Gate 1. Không đổi luồng nhận lời mời. Không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, sửa code, merge, phát hành hay deploy.
