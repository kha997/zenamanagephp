---
work_id: GAP-088
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-21-gap-088-pecl-retry-evidence.md
  plan: null
  branch: docs/GAP-088-pecl-retry
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
  created_at: "2026-10-21T16:29:51+07:00"
  updated_at: "2026-10-21T16:29:51+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Docker Security Scan đỏ **2 lần** trên PR #360 (GAP-087) vì cùng một lỗi:
`pecl install redis` trong `Dockerfile.prod` không tải được từ pecl.php.net.
Lần nào chạy lại cũng xanh, và cùng cây code đó đã xanh ở các commit khác.
Nghĩa là lỗi đến từ máy chủ PECL lúc chập chờn, không phải từ code.

Dockerfile tải redis từ PECL **một lần, không thử lại**. Chỉ cần PECL trả lỗi
một lần là cả bản build hỏng, và PR (kể cả main) bị đỏ cho tới khi có người
chạy lại.

Thêm nữa, cả 3 Dockerfile (`Dockerfile.prod`, `Dockerfile.websocket`, `Dockerfile`) đều
cài redis **không ghi phiên bản**. Vì vậy mỗi lần build có thể ra một phiên bản phpredis khác.

## Vấn đề vận hành

Build image phụ thuộc vào một lần gọi pecl.php.net. CI và việc release/deploy
có thể hỏng ngẫu nhiên.

## Người dùng bị ảnh hưởng

- Người phát triển: Docker Security Scan đỏ ngẫu nhiên.
- Người phát hành: build image khi release hoặc deploy.

## Phạm vi đề xuất

Gate 2 so sánh các cách:
- thử lại khi tải lỗi;
- ghim phiên bản phpredis;
- build từ mã nguồn đã ghim thay cho PECL.

Gate 2 cũng chọn sửa những Dockerfile nào, và cách kiểm chứng.

## Loại trừ rõ ràng

Không đổi extension PHP nào khác. Không đổi base image. Không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, sửa Dockerfile, merge, phát hành hay deploy.
