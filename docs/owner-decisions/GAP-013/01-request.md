---
work_id: GAP-013
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-25-gap-013-submittal-notifications-evidence.md
  plan: null
  branch: docs/GAP-013-submittal-notifications
  pr: null
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
  created_at: "2026-10-25T21:57:47+07:00"
  updated_at: "2026-10-25T21:57:47+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Tiếp nhóm thông báo, sau GAP-012. Mình kiểm tra lại thì register đã cũ một phần:

- **Đã có:** khi submittal được **nộp lại** sau khi bị từ chối, người đã từ chối lần trước nhận thông báo.
- **Chưa có:**
  - Khi **nộp lần đầu**, không ai được báo là có submittal chờ duyệt.
  - Khi **duyệt** hoặc **từ chối**, người nộp không được báo.
- Submittal **không có trường "người duyệt được chỉ định"**. Ai có quyền `submittal.approve` trong tenant đều duyệt được.
  Vì vậy, chọn ai nhận thông báo khi nộp lần đầu là việc Owner quyết ở Gate 2.

## Vấn đề vận hành

Tốc độ duyệt vật tư phụ thuộc vào việc mọi người tự vào danh sách kiểm tra. Người nộp không biết kết quả.

## Người dùng bị ảnh hưởng

Người nộp submittal, người duyệt, PM dự án.

## Phạm vi đề xuất

Gate 2 thiết kế:
- người nộp nhận thông báo khi submittal được duyệt hoặc bị từ chối;
- chọn người nhận khi nộp lần đầu: PM dự án, mọi người có quyền duyệt, hoặc chưa báo;
- dùng lại cách gửi sẵn có (gửi sau khi commit, lỗi gửi chỉ ghi log).

## Loại trừ rõ ràng

Không sửa code trong Gate 1. Không thêm trường "người duyệt". Không gửi email. Không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, danh sách người nhận, sửa code, merge, phát hành hay deploy.
