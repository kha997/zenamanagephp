---
work_id: GAP-086
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-10-20-gap-086-npm-audit-high-gate-evidence.md
  plan: null
  branch: docs/GAP-086-npm-audit-high-gate
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
  created_at: "2026-10-20T19:38:42+07:00"
  updated_at: "2026-10-20T19:38:42+07:00"
generated_by: agent
---

## OWNER GATE 1: AWAITING OWNER

## Owner Summary

Hiện CI chỉ đỏ khi `npm audit` có lỗ hổng **critical**. GAP-083 chọn mức đó vì
lúc ấy main còn 12 lỗ hổng high. Sau GAP-084 và GAP-085, npm đã **0 lỗ hổng**.
Vì vậy có thể nâng mức chặn lên **high** (high hoặc critical đều làm đỏ) mà không
PR nào đang có bị đỏ.

Lưu ý: npm không có cơ chế bỏ qua lỗ hổng như Composer hay Trivy. Nếu sau này có
lỗ hổng high chưa có bản vá, mọi PR sẽ đỏ cho đến khi ghim phiên bản
(`overrides`) hoặc thêm ngoại lệ. Gate 2 sẽ đề xuất cách xử lý.

## Vấn đề vận hành

Lỗ hổng npm mức high hiện chỉ được báo, không bị chặn.

## Người dùng bị ảnh hưởng

Người phát triển: PR thêm gói npm có lỗ hổng high sẽ đỏ.

## Phạm vi đề xuất

Gate 2 thiết kế:
- ngưỡng chặn (high, hay cả moderate);
- cách ghi ngoại lệ có lý do;
- cách chứng minh cổng chặn hoạt động (commit thử đỏ rồi hoàn tác).

## Loại trừ rõ ràng

Không đổi gói; không đổi cổng Composer hay Trivy; không deploy.

## Khả năng hoàn tác

Gate 1 chỉ là tài liệu.

## Đề xuất

Phê duyệt chuyển sang Gate 2.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Chưa duyệt thiết kế, sửa workflow, merge, phát hành hay deploy.
