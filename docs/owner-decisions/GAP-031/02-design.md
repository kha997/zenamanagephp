---
work_id: GAP-031
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md
  plan: docs/superpowers/plans/2026-08-04-gap031-document-approval-workflow.md
  branch: feature/gap031-document-approval-workflow
  pr: https://github.com/kha997/zenamanagephp/pull/238
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: "owner (session 2026-08-04, GAP-031 retrofit)"
  recorded_at: "2026-08-04T09:30:00+07:00"
  owner_response_reference: "conversation: GAP-031 owner-control-layer retrofit, 2026-08-04"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-04T09:30:00+07:00"
  updated_at: "2026-08-04T09:30:00+07:00"
generated_by: agent
---

## Trước / Sau

**Trước:** nút Duyệt/Từ chối trên web không hoạt động; ai có quyền sửa có thể tự đặt trạng thái duyệt.
**Sau:** người có quyền duyệt mở danh sách "Chờ duyệt", bấm Duyệt hoặc Từ chối; hệ thống ghi ai quyết định, khi nào, ghi chú gì. Chỉ một cách hợp lệ để chuyển trạng thái.

## Vai trò bị ảnh hưởng

Quản lý dự án (người duyệt), người nộp tài liệu.

## Được phép / Không được phép

Người có quyền duyệt: Duyệt, Từ chối. Người chỉ có quyền sửa: không còn tự đặt được trạng thái duyệt.

## Trạng thái và bước tiếp theo

Nháp → Đã nộp → (Đã duyệt hoặc Bị từ chối). Không có trạng thái nào khác.

## Ngoại lệ

Nếu người duyệt nghỉ việc giữa chừng, tài liệu vẫn ở "Đã nộp" chờ người có quyền duyệt khác xử lý (không tự động chuyển).

## Hành vi người dùng nhìn thấy

Danh sách "Chờ duyệt" hiển thị đúng tài liệu; nút Duyệt/Từ chối hoạt động thật.

## Kịch bản chấp nhận

Khi người có quyền duyệt bấm Duyệt, trạng thái đổi thành Đã duyệt và người nộp thấy được ai duyệt, khi nào.

## Loại trừ phạm vi

Không có người duyệt được chỉ định riêng cho từng hồ sơ (GAP-033, việc khác).

## Quyết định

☑ Đồng ý tiến hành triển khai
