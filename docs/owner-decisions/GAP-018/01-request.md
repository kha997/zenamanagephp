---
work_id: GAP-018
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-28-gap-018-smart-tools-evidence.md
  plan: null
  branch: docs/GAP-018-smart-tools-api-prefix
  pr: https://github.com/kha997/zenamanagephp/pull/381
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-29T00:05:23+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-28, verbatim: 'APPROVE GAP-018 Gate 1'. Bound to reviewed Draft PR #381 head e3e0215c891eb7d97dc088796711397d6f069019, canonical base 0207062843857e7e974a77137261da2e29e6a549. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T00:03:27+07:00"
  updated_at: "2026-10-29T00:05:23+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-018 Gate 1 in-session on 2026-10-28 against Draft PR #381 head
`e3e0215c891eb7d97dc088796711397d6f069019`. This authorizes preparation of Gate 2 only.

## Owner Summary

Bốn component "smart tools" (tìm kiếm, bộ lọc, ngăn phân tích, xuất dữ liệu) và một trang demo vẫn nằm trong code, gọi tới
API `/api/universal-frame/*` **không tồn tại**. **Không trang nào dùng chúng.** Đã có chú thích "lưu trữ, không dùng" nhưng
vẫn dễ gây nhầm cho người sửa sau (1.374 dòng code chết).

Một điểm dính: danh sách kiểm tra "sẵn sàng ra mắt" cũ (`LaunchChecklistService`) đánh dấu mục "smart tools" bằng cách kiểm
tra file `smart-search` có tồn tại hay không.

## Vấn đề vận hành

Không ảnh hưởng người dùng; là rác kiến trúc gây hiểu nhầm.

## Người dùng bị ảnh hưởng

Không ai (không trang nào render). Ảnh hưởng người bảo trì code.

## Bằng chứng

`docs/audits/2026-10-28-gap-018-smart-tools-evidence.md`.

## Phạm vi đề xuất

Gate 2: xoá 5 file (hay giữ lưu trữ), cách xử lý mục "smart tools" trong danh sách kiểm tra, và test chặn chúng quay lại.

## Loại trừ rõ ràng

Không đụng `layouts/universal-frame.blade.php` và API `/api/v1/universal-frame/*` (GAP-019); không deploy.

## Khả năng hoàn tác

Revert commit.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: `APPROVE GAP-018 Gate 1` / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa chọn cách xử lý, chưa duyệt Gate 2, merge hay phát hành.
