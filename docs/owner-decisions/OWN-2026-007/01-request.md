---
work_id: OWN-2026-007
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_proceed
references:
  spec: docs/owner-decisions/OWN-2026-007/00-gap-010c-reproduction-evidence.md
  plan: null
  branch: docs/OWN-2026-007-post-p1-gap-register-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/255
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: true
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-10T08:33:51+07:00"
  updated_at: "2026-08-10T08:45:39+07:00"
generated_by: agent
---

## Owner Summary
`OPERATIONAL_GAP_REGISTER.md` hiện ghi 4 hàng đã lỗi thời so với trạng thái đã xác minh trên `main`: GAP-010, GAP-010b, GAP-010c và GAP-034. Cần cập nhật lại cho đúng — chỉ tài liệu, không đổi mã nguồn.

## Vấn đề vận hành
`OPERATIONAL_GAP_REGISTER.md` (SSOT) hiện ghi:
- GAP-010: `PARTIALLY RESOLVED (verified)`
- GAP-010b: `OPEN (verified 2026-08-06)`
- GAP-010c: `REOPENED FOR REPRODUCTION (2026-08-06)`
- GAP-034: `GATE 1 APPROVED — GATE 2 PENDING (2026-08-07)`

Trên `main` hiện tại, GAP-010b và GAP-034 đã qua Gate 3, có triển khai merge trong PR #253 (commit `1325c0e6`). GAP-010c đã hoàn thành bước tái hiện với kết quả **NOT REPRODUCED** — không cần triển khai sửa lỗi. Việc để register sai khiến agent và owner có thể ưu tiên công việc đã đóng hoặc lặp lại verification.

## Người dùng bị ảnh hưởng
- owner;
- engineering agents;
- reviewers/planners relying on the operational register.

## Bằng chứng
- **GAP-010b Gate 3 approved:** `docs/owner-decisions/GAP-010b/03-release.md` ghi `gate_status: approved`, `owner_decision.value: approved` (2026-08-09). Triển khai merge trong PR #253.
- **GAP-034 Gate 3 approved:** `docs/owner-decisions/GAP-034/03-release.md` ghi `gate_status: approved`, `owner_decision.value: approved` (2026-08-09). Cùng PR #253 nguyên tử.
- **PR #253 merged:** `origin/main` hiện tại tại commit `1325c0e6 Merge PR #253: combined export release`.
- **GAP-010c reproduction:** Bằng chứng kỹ thuật chi tiết được lưu trong `docs/owner-decisions/OWN-2026-007/00-gap-010c-reproduction-evidence.md`. Tóm tắt: baseline `1325c0e6`, 8 ca kiểm tra, `SHIFTED=0`, không có chuyển đổi múi giờ phía client trên `/schedule`. Commit `63afc21f` ghi nhận remediation date-only Gantt normalization lịch sử. Hợp đồng schema `tasks.start_date/end_date` là `DATE` theo migration. Runtime kiểm tra disposable đã xem xét; không claim gì về production DB schema thực tế nếu chưa query trực tiếp.
- **GAP-010 parent:** GAP-010a và GAP-010b đã RESOLVED. GAP-010c có kết quả khác (không phải RESOLVED). Gate 2 phải xác định trạng thái cha phù hợp dựa trên trạng thái cuối của 3 dòng con.

## Tác động nếu không xử lý
Agent/owner có thể chọn sai hàng đợi, lặp lại công việc kỹ thuật đã hoàn thành, và mất niềm tin vào SSOT vận hành.

## Phạm vi đề xuất
Cập nhật `OPERATIONAL_GAP_REGISTER.md` để phản ánh đúng trạng thái đã xác minh của 4 hàng:
- GAP-010: trạng thái cuối do Gate 2 xác định dựa trên 3 dòng con (GAP-010a RESOLVED, GAP-010b RESOLVED, GAP-010c NOT REPRODUCED/closed).
- GAP-010b: `RESOLVED (verified)`.
- GAP-010c: đóng lại với trạng thái mô tả chính xác kết quả NOT REPRODUCED — không phải `RESOLVED (verified)` vì không có lỗi đã xác nhận và sửa chữa. Gate 2 phải định nghĩa taxonomy chính xác (ví dụ: `CLOSED — NOT REPRODUCED (verified)` hoặc tương đương).
- GAP-034: `RESOLVED (verified)`.

Toàn bộ chỉ là thao tác tài liệu — không thay đổi hành vi ứng dụng, không triển khai gap nào.

## Loại trừ rõ ràng
- mã nguồn production;
- kiểm thử;
- migration;
- thay đổi route;
- GAP-032;
- GAP-033;
- cleanup Task model casts;
- strict date-only validation;
- dual Task-model cast cleanup.

## Đề xuất
Phê duyệt để tiến hành thiết kế chi tiết (Gate 2) cho việc cập nhật đúng trạng thái trong `OPERATIONAL_GAP_REGISTER.md`, với taxonomy mới cho GAP-010c và trạng thái cha GAP-010 phải phản ánh đúng 3 trạng thái con khác nhau.

## Decision Needed
Owner cần chọn một trong: **Phê duyệt để tiến hành thiết kế (Gate 2)** / **Yêu cầu thêm thông tin** / **Từ chối** / **Hoãn lại**.

## What the owner is NOT being asked to decide
Owner không được yêu cầu phê duyệt bất kỳ thay đổi mã nguồn, migration, route, validation, hay triển khai gap nào — chỉ xác nhận việc cập nhật đúng trạng thái trong `OPERATIONAL_GAP_REGISTER.md` là cần thiết và cho phép tiến hành thiết kế chi tiết (Gate 2).
