---
work_id: GAP-088
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-21-gap-088-pecl-retry-evidence.md
  plan: null
  branch: docs/GAP-088-pecl-retry
  pr: https://github.com/kha997/zenamanagephp/pull/361
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-21T16:32:43+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-21, verbatim: 'APPROVE GAP-088 Gate 1'. Bound to reviewed Draft PR #361 head 3317247cb21292a6ea93132410438ff26e87e197, canonical base e14bdaa0b0b9439032ee64e81fb23094234cc093. Authorizes preparing Gate 2 only."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-21T16:29:51+07:00"
  updated_at: "2026-10-21T16:32:43+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

Owner approved GAP-088 Gate 1 in-session on 2026-10-21 against Draft PR #361
head `3317247cb21292a6ea93132410438ff26e87e197`. This authorizes preparation of Gate 2 only.

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
