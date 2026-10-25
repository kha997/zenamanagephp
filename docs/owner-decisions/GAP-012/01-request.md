---
work_id: GAP-012
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-25-gap-012-cr-apply-notification-evidence.md
  plan: null
  branch: docs/GAP-012-cr-apply-notification
  pr: https://github.com/kha997/zenamanagephp/pull/370
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-25T19:49:22+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-25, verbatim: 'APPROVE GAP-012 Gate 1'. Bound to reviewed Draft PR #370 head 22b5723532fac04619e5e3098f55273722399dd8, canonical base b810ed921b82edf2c83d222287f4d55826826950. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-25T19:48:01+07:00"
  updated_at: "2026-10-25T19:49:22+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-012 Gate 1 in-session on 2026-10-25 against Draft PR #370
head `22b5723532fac04619e5e3098f55273722399dd8`. This authorizes preparation of Gate 2 only.

## Owner Summary

Việc 3 trong thứ tự Owner đã duyệt là nhóm thông báo (GAP-012, 013, 014b). Mình làm từng GAP một, bắt đầu với GAP-012
vì nhỏ nhất.

- Khi **duyệt** change request (CR), hệ thống đã báo cho người yêu cầu và PM dự án.
- Khi **áp dụng** CR (`apply`: chuyển sang "đã thực hiện", tạo task bù và cập nhật baseline), **không ai được báo**.
  Người yêu cầu và PM chỉ biết nếu tự mở CR ra xem.
- Đây là việc cố ý hoãn từ trước: backlog ghi "Apply notification explicitly deferred", và có một test khẳng định apply
  **không** gửi thông báo nào.

## Vấn đề vận hành

Người yêu cầu và PM không biết CR đã được thực hiện, nên có thể không biết baseline hay việc cần làm đã thay đổi.

## Người dùng bị ảnh hưởng

Người yêu cầu CR, PM dự án, người được giao task bù.

## Phạm vi đề xuất

Gate 2 thiết kế: ai nhận thông báo khi apply, dùng lại hàm tạo thông báo sẵn có của CR, và thay test cũ (đang khẳng định
không có thông báo) bằng test mới.

## Loại trừ rõ ràng

Không sửa code trong Gate 1. Không đổi thông báo của submit/approve/reject. Không gửi email. Không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, danh sách người nhận, sửa code, merge, phát hành hay deploy.
