---
work_id: GAP-068
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-08-gap-068-reconciliation-mutation-response-evidence.md
  plan: null
  branch: docs/GAP-068-reconciliation-mutation-response
  pr: null
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
  created_at: "2026-10-08T09:18:18+07:00"
  updated_at: "2026-10-08T09:18:18+07:00"
generated_by: agent
---

## Owner Summary

API đối soát (GAP-067) có thể trả **thành công nhưng không kèm dữ liệu**
(`data: null`) sau khi đối soát hoặc gỡ đối soát. Lỗi xảy ra khi dự án đã có
hơn 100 lần đối soát mới hơn lần đang thao tác, ví dụ đối soát lùi ngày hoặc gỡ
một lần cũ. Dữ liệu vẫn ghi đúng; chỉ phần trả về bị rỗng. Trang web không bị
ảnh hưởng. Đã tái hiện: lần đối soát thứ 101 lùi 5 ngày trả `201` và
`data: null`. Phát hiện bởi Codex review trên PR #340 sau khi merge. Đề xuất:
phê duyệt Gate 1 để thiết kế bản sửa.

## Vấn đề vận hành

Ứng dụng gọi API không biết lần đối soát vừa tạo hoặc vừa gỡ là gì nếu không
gọi thêm một lần nữa.

## Người dùng bị ảnh hưởng

Ứng dụng/tích hợp gọi API ngân quỹ. Người dùng trang web không bị ảnh hưởng
bởi lỗi chính. Liên quan (Finding 3): trang đối soát chỉ hiện 100 lần gần nhất,
nên lần cũ hơn không gỡ được từ trang.

## Bằng chứng

`docs/audits/2026-10-08-gap-068-reconciliation-mutation-response-evidence.md`:
nguyên nhân, lệnh tái hiện và kết quả, phạm vi ảnh hưởng.

## Phạm vi đề xuất

Gate 2 thiết kế: trả về lần đối soát bị tác động bằng cách tra theo id (không
giới hạn 100), có test cho trường hợp trên 100 lần; Owner chọn có xử lý luôn
Finding 3 (phân trang lịch sử trên trang web) hay không.

## Loại trừ rõ ràng

Không đổi cơ sở dữ liệu, không đổi quy tắc đối soát hay khoá, không đổi
quyền. Không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, thay đổi code, merge hay phát hành.
