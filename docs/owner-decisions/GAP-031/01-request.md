---
work_id: GAP-031
gate: 1
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
  recorded_at: "2026-08-04T09:00:00+07:00"
  owner_response_reference: "conversation: GAP-031 owner-control-layer retrofit, 2026-08-04"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-04T09:00:00+07:00"
  updated_at: "2026-08-04T09:00:00+07:00"
generated_by: agent
---

## Vấn đề vận hành

Trên trang web, nút Duyệt/Từ chối một tài liệu không hoạt động đúng — chưa từng được nối vào đường dẫn nào người dùng thật bấm tới được, và một người chỉ có quyền sửa tài liệu vẫn có thể tự ghi trạng thái "đã duyệt"/"bị từ chối" mà không cần ai thực sự bấm nút duyệt.

## Người dùng bị ảnh hưởng

Bất kỳ ai có quyền duyệt tài liệu trong dự án (thường là quản lý dự án), và bất kỳ ai nộp tài liệu chờ duyệt.

## Bằng chứng

Đội kỹ thuật xác nhận: màn hình duyệt trên web chưa từng được nối route thật; đồng thời phát hiện một người chỉ có quyền sửa có thể tự đặt trạng thái duyệt mà không qua bước duyệt thật.

## Tác động nếu không xử lý

Hồ sơ tài liệu tiếp tục bị mắc kẹt hoặc bị lách quyền duyệt; không ai biết chắc ai đã thực sự duyệt cái gì.

## Phạm vi đề xuất

Nối màn hình web vào đúng quy trình duyệt đã có ở API, và khoá đường lách quyền duyệt.

## Loại trừ rõ ràng

Không đổi cấu trúc dữ liệu. Không có "người duyệt được chỉ định riêng cho từng hồ sơ" (đó là việc khác).

## Đề xuất

Xử lý ngay — đây là lỗ hổng phân quyền, không phải cải tiến trải nghiệm.

## Quyết định

☑ Đồng ý tiến hành thiết kế (Gate 2)
