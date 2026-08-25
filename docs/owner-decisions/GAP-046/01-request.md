---
work_id: GAP-046
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_proceed"
references:
  spec: docs/audits/2026-08-25-gap-046-service-line-semantics-audit.md
  plan: null
  branch: docs/GAP-046-service-line-semantics-audit
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-25T05:34:18Z"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-25T05:34:18Z"
  updated_at: "2026-08-25T05:34:18Z"
generated_by: agent
---

## Owner Summary
Đội kỹ thuật đã rà soát toàn bộ hệ thống phân loại "Service Line" (Design/Construction/Inspection) từ Lead → Opportunity → Quote → Contract → Project. Hiện tại chỉ có một cột duy nhất (`Opportunity.service_category`), là giá trị đơn (không đa giá trị), và tự động mặc định thành "architecture" một cách âm thầm ở 3 chỗ trong code — vi phạm đúng quy tắc đã được Owner duyệt trong tài liệu SSOT ngày 2026-08-15. Đây là Phase A (chỉ điều tra, không sửa gì) của một Work ID lớn hơn đã được chính SSOT đó lên kế hoạch trước.

## Vấn đề vận hành
Khi tạo Opportunity (qua API, hoặc qua chuyển đổi từ Lead) mà người dùng không chọn rõ loại dịch vụ, hệ thống tự gán "architecture" — không có cách nào phân biệt "người dùng thực sự chọn Architecture" với "không ai chọn gì cả." Giá trị mặc định sai này sau đó không được sao chép sang Project/Contract khi chuyển đổi (Project không có trường phân loại nào), nhưng lại được đọc lại ở 2 nơi đang chạy thật: báo cáo hiệu suất theo loại dịch vụ (`BusinessKpiService`) và gợi ý AI cho hạng mục thiết kế (`DesignItemPageController`) — cả hai đều bị lệch về phía "Architecture" một cách âm thầm.

## Người dùng bị ảnh hưởng
Nhân viên sales tạo Opportunity/chuyển đổi Lead mà quên chọn loại dịch vụ (mặc định âm thầm thành Architecture); người xem báo cáo hiệu suất CRM theo loại dịch vụ (số liệu bị lệch); người dùng tính năng gợi ý AI cho hạng mục thiết kế trên các dự án không phải kiến trúc (gợi ý bị lệch ngữ cảnh).

## Bằng chứng
Đọc trực tiếp mã nguồn hiện tại trên `main` (không suy đoán): 3 nơi độc lập gán mặc định "architecture" (2 controller + 1 cột database); không có trường phân loại nào trên Project/Quote/Contract; test hiện tại gán cứng "architecture" ở hơn 10 file test thay vì kiểm thử đa dạng loại dịch vụ; không có factory/seeder nào từng thiết lập trường này một cách có chủ đích. Toàn bộ chi tiết, kèm số dòng file cụ thể, trong `docs/audits/2026-08-25-gap-046-service-line-semantics-audit.md`.

## Tác động nếu không xử lý
Không thể triển khai bất kỳ tính năng nào cần biết dự án thuộc loại dịch vụ nào một cách tin cậy (OPPM, Portfolio, Control Tower — đều đã được SSOT quy hoạch dựa trên giả định có phân loại đáng tin cậy). Báo cáo CRM và gợi ý AI tiếp tục sai lệch âm thầm. Mỗi slice tương lai phụ thuộc vào phân loại dịch vụ sẽ phải tự vá lỗi này một cách rời rạc thay vì có một nền tảng chung.

## Phạm vi đề xuất
Xác nhận phạm vi Phase A (điều tra) đã hoàn tất và đầy đủ bằng chứng; nếu được duyệt, Phase B (xây nền tảng: giá trị Service Line đa giá trị + cơ chế thành viên Opportunity/Project + trường nguồn gốc dữ liệu CONFIRMED/INFERRED/NEEDS_REVIEW/UNKNOWN) sẽ là một Gate 2 riêng, thiết kế schema cụ thể chưa được quyết ở đây.

## Loại trừ rõ ràng
Không đề xuất bất kỳ thay đổi migration/model/controller/service/route/UI nào ở bước này. Không gộp: CRM Classification UX & Gates, Quote Scope Snapshot, Contract Service-Line migration, Portfolio Membership Migration, Project OPPM (Issue #248), Operations Control Tower, Finance Control, Project Treasury, GAP-041/GAP-042/GAP-045 (đều là các work item CI/test-infrastructure không liên quan, đã xác nhận qua hồ sơ quyết định riêng của chúng). Không xác định lại lịch sử phân bố dữ liệu production thật — dữ liệu đó hiện KHÔNG có sẵn trong repo để kiểm tra và được báo cáo rõ là chưa biết, không suy đoán số liệu.

## Đề xuất
Đội kỹ thuật đề xuất: Owner phê duyệt Gate 1 để tiến sang Gate 2 (thiết kế Phase B) — không phát hiện xung đột kiến trúc hay rào cản nào khiến ranh giới phạm vi đề xuất trở nên bất khả thi; khoảng trống bằng chứng duy nhất (phân bố dữ liệu production thật) là câu hỏi cần xử lý ở thời điểm Phase B, không phải rào cản Gate 1/Gate 2.

## Decision Needed
Owner chọn một trong: Approve để tiến sang thiết kế (Gate 2) / Yêu cầu thêm thông tin / Từ chối / Hoãn lại.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt bất kỳ thay đổi code, schema, migration, hay cơ chế kỹ thuật cụ thể nào ở bước này — chỉ xác nhận vấn đề có thật, phạm vi điều tra đã đủ, và đáng để tiến hành thiết kế Gate 2. Owner cũng không được yêu cầu quyết định thiết kế schema chính xác (giá trị đa chọn được lưu thế nào, tên bảng join, v.v.) — đó là quyết định của Gate 2.
