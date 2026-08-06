---
work_id: OWN-2026-004
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: null
  plan: null
  branch: fix/OWN-2026-004-gap-subidentifier-governance
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-06T15:46:36+07:00"
  owner_response_reference: "ChatGPT project conversation — explicit owner approval of the GAP sub-identifier governance compatibility correction on 2026-08-06"
  reconciliation_required: true
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-06T15:46:36+07:00"
  updated_at: "2026-08-06T15:46:36+07:00"
generated_by: agent
---

## OWNER GATE 1: APPROVED

The owner approves preparation of the detailed design for a narrowly scoped Owner Control Layer governance-tooling compatibility correction.

This approval does not authorize implementing GAP-010b, and does not approve GAP-010b's business request.

## Owner Summary
Khi chuẩn bị hồ sơ Gate 1 cho GAP-010b, công cụ kiểm tra cấu trúc chung của kho mã (Owner Control Layer) đã từ chối `work_id: GAP-010b` vì mẫu định danh hiện tại chỉ chấp nhận đúng 3 chữ số sau "GAP-", không chấp nhận mã con dạng chữ cái như "b". Đây là lỗi tương thích trong công cụ quản trị, không phải lỗi nghiệp vụ. Yêu cầu này xin phép sửa đúng công cụ quản trị để chấp nhận các mã con chính thức đã tồn tại (như GAP-010b, GAP-014c) mà không đổi tên chúng và không làm yếu mô hình định danh chung.

## Vấn đề vận hành
Mô hình định danh gap của dự án từ trước đến nay đã dùng các mã con dạng chữ cái thường (ví dụ GAP-010a/b/c, GAP-014a/b/c) để tách các phần khác nhau của một cụm lỗi cha có nhiều trạng thái khác nhau — đây là quy ước đã được owner chấp nhận và dùng trong sổ đăng ký gap vận hành (`OPERATIONAL_GAP_REGISTER.md`) đã phát hành qua OWN-2026-003. Tuy nhiên công cụ kiểm tra hồ sơ quản trị (Owner Control Layer) lại chưa từng hỗ trợ các mã con này làm `work_id` của một hồ sơ quản trị (packet) — mẫu kiểm tra hiện tại (`docs/owner-governance/packet-schema.yml`) chỉ chấp nhận đúng `GAP-[0-9]{3}` (3 chữ số, không hậu tố). Điều này chặn hoàn toàn việc mở hồ sơ Gate 1 chính thức cho GAP-010b.

## Người dùng bị ảnh hưởng
Đội kỹ thuật (không thể mở hồ sơ Gate 1 cho bất kỳ mã con gap nào cho tới khi sửa); owner (không thể nhận và xem xét yêu cầu Gate 1 cho GAP-010b, GAP-014b, GAP-014c hay bất kỳ mã con nào khác trong tương lai).

## Bằng chứng
Chạy thử `php scripts/ssot/owner_governance_lint.php docs/owner-decisions/GAP-010b/01-request.md` với `work_id: GAP-010b` cho kết quả lỗi `[work-id-pattern]: work_id 'GAP-010b' does not match the canonical pattern.` Mẫu định nghĩa tại `docs/owner-governance/packet-schema.yml:6`: `work_id_pattern: '^(GAP-[0-9]{3}|ZMC-[0-9]{3,}|WP-[0-9]{3,}|OWN-[0-9]{4}-[0-9]{3})$'` — không có phần cho hậu tố chữ cái.

## Tác động nếu không xử lý
GAP-010b (một lỗi thật, đang mở, đã được owner xác nhận qua sổ đăng ký) không thể được đưa vào quy trình xử lý chính thức của Owner Control Layer. Tương tự cho GAP-014b, GAP-014c, và bất kỳ mã con nào khác cần một Gate 1 riêng trong tương lai.

## Phạm vi đề xuất
Sửa đúng công cụ quản trị (schema + 2 nơi trích xuất Work-ID trong CI) để chấp nhận đúng dạng mã con chính thức đã tồn tại: 3 chữ số, có thể theo sau bởi đúng 1 chữ cái thường (a-z). Không đổi tên GAP-010b. Không nới lỏng cho bất kỳ dạng chuỗi tuỳ ý nào khác.

## Loại trừ rõ ràng
Yêu cầu này **không** phê duyệt nội dung nghiệp vụ hay việc triển khai sửa lỗi GAP-010b. Đây thuần tuý là sửa công cụ quản trị (tooling), **không đổi hành vi sản phẩm**. Không đụng tới `OPERATIONAL_GAP_REGISTER.md`, không đụng tới hồ sơ `docs/owner-decisions/GAP-010b/01-request.md` đã soạn sẵn (vẫn giữ nguyên, chưa commit/mở PR), không đụng tới mã nguồn ứng dụng (`app/`, `resources/`, `routes/`, `database/`).

## Khả năng hoàn tác
Hoàn tác bằng cách revert đúng PR sửa công cụ quản trị này — không có dữ liệu, route, hay quyền hạn nào bị ảnh hưởng để phải khôi phục thêm.

## Đề xuất
Đội kỹ thuật đề xuất: owner phê duyệt Gate 1 và Gate 2 cùng lúc cho việc sửa công cụ quản trị này (phạm vi hẹp, rủi ro thấp, không đổi hành vi sản phẩm) để có thể tiếp tục xử lý GAP-010b theo đúng quy trình chính thức.

## Decision Needed
Owner đã chọn: **Phê duyệt Gate 1 và Gate 2** cho việc sửa công cụ quản trị này.

## What the owner is NOT being asked to decide
Owner không được yêu cầu phê duyệt bất kỳ thay đổi mã nguồn ứng dụng, sổ đăng ký gap, hay việc triển khai sửa GAP-010b — chỉ xác nhận việc mở rộng mẫu định danh trong công cụ quản trị để công cụ có thể tiếp nhận đúng các mã con gap đã tồn tại.
