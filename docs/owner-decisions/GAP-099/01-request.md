---
work_id: GAP-099
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-28-gap-099-broken-v1-project-and-change-request-evidence.md
  plan: null
  branch: docs/GAP-099-broken-v1-project-and-change-request
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
  created_at: "2026-10-28T21:57:35+07:00"
  updated_at: "2026-10-28T21:57:35+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Hai nhóm API cũ hỏng với **mọi người dùng, kể cả super admin** (lỗi 500):

- **Sửa dự án** `PUT/PATCH /api/v1/projects/{id}`: đọc một hằng số không tồn tại.
- **Change request API cũ**: **9 / 11 route** lỗi 500 — controller thiếu method, sai số tham số, kiểu id không khớp; route
  chỉ nhận id dạng số trong khi dữ liệu dùng ULID, nên bản ghi thật không bao giờ khớp. Chỉ tạo mới (201) và nộp (422 kiểm
  tra dữ liệu) còn chạy.

API chuẩn `/api/zena/...` cho change request và dự án **đang chạy tốt**. Không trang web nào gọi các route hỏng.

## Vấn đề vận hành

API trả lỗi 500 thay vì làm việc hoặc báo rõ "không hỗ trợ"; dễ gây hiểu nhầm và nhiễu log lỗi.

## Người dùng bị ảnh hưởng

Ai gọi API cũ cho dự án / change request. Không trang web nào dùng; client ngoài chưa rõ — nhưng không ai đang dùng được các
route hỏng.

## Bằng chứng

`docs/audits/2026-10-28-gap-099-broken-v1-project-and-change-request-evidence.md` (bảng 14 lần gọi thử).

## Phạm vi đề xuất

Gate 2 so sánh: sửa cho chạy được (project update + từng route change request), hay gỡ / thay bằng phản hồi "đã chuyển sang
`/api/zena`" cho nhóm change request v1; kèm test đỏ trước / xanh sau.

## Loại trừ rõ ràng

Không đổi API chuẩn `/api/zena`; không đổi dữ liệu; không động tới các nhóm v1 khác; không deploy.

## Khả năng hoàn tác

Revert commit.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-099 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn sửa hay gỡ, chưa duyệt Gate 2, merge hay phát hành.
