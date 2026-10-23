---
work_id: GAP-093
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-23-gap-093-nightly-matrix-evidence.md
  plan: null
  branch: docs/GAP-093-nightly-server
  pr: https://github.com/kha997/zenamanagephp/pull/366
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
  created_at: "2026-10-23T11:17:44+07:00"
  updated_at: "2026-10-23T11:17:44+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Owner chọn GAP-093 là "nightly-matrix bỏ artisan serve". Khi điều tra, mình thấy vấn đề lớn hơn, và Owner chọn
"Sửa cho nightly chạy được (Recommended)":

- **91/91 lần chạy** của Nightly Test Matrix (từ 20/02 đến 22/10) đều đỏ. **Chưa lần nào xanh.**
- Lần nào cũng đỏ trong khoảng 1 phút, ở bước `cp .env.testing .env`, vì repo không có file `.env.testing`.
- Vì vậy job chưa từng tới bước `artisan serve` hay chạy test nào. Các nhóm test slow, load, stress, redis chưa từng
  chạy trên CI.

## Vấn đề vận hành

Lần chạy nightly nào cũng đỏ, nên kết quả không còn ý nghĩa. Nếu các nhóm test chậm có lỗi thật, sẽ không ai biết.

## Người dùng bị ảnh hưởng

Người phát triển và Owner: mất lớp kiểm tra nightly.

## Phạm vi đề xuất

Gate 2 thiết kế:
- tạo file env ngay trong workflow, thay vì copy file không tồn tại;
- chạy app bằng nginx + php-fpm thay `artisan serve`, như GAP-090 và GAP-092;
- xử lý bước websocket (lệnh này không chạy được vì thiếu Ratchet, GAP-089);
- chạy thử workflow để xem các bước sau còn hỏng gì. Nếu lộ ra lỗi test thật, Gate 2 sẽ trình Owner cách xử lý.

## Loại trừ rõ ràng

Không sửa app trong Gate 1. Không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, sửa workflow hay test, merge, phát hành hay deploy.
