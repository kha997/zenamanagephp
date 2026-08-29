---
work_id: GAP-048
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_more_info_or_decline_or_defer"
references:
  spec: docs/audits/2026-08-29-gap-048-crm-classification-gates-audit.md
  plan: null
  branch: docs/GAP-048-gate1-crm-classification-audit
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-29T15:20:55Z"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-29T15:20:55Z"
  updated_at: "2026-08-29T15:20:55Z"
generated_by: agent
---

## Owner Summary
Đội kỹ thuật đã điều tra toàn bộ luồng phân loại "Loại dịch vụ" của CRM (Lead → Opportunity → Quote → chuyển đổi thành Project). Kết quả: hệ thống hiện tại vẫn dùng đúng cơ chế cũ mà tài liệu SSOT (2026-08-15) đã ghi nhận là vi phạm quy tắc — tự động gán "Kiến trúc" (architecture) khi người dùng không chọn gì — và nền tảng Service Line chuẩn mới do GAP-046 xây (đã release) hiện chưa được bất kỳ màn hình, luồng bán hàng, hay cổng kiểm soát nào sử dụng.

## Vấn đề vận hành
Khi nhân viên sales chuyển đổi Lead thành Opportunity mà không chọn loại dịch vụ, hệ thống âm thầm gán "architecture" (2 vị trí code xác nhận: `OpportunityController.php:217`, `LeadController.php:304`). Từ đó, Opportunity/Quote/chuyển đổi thắng thầu (WON) sang Project đều tiến hành bình thường mà không có bất kỳ kiểm tra nào về việc phân loại có thật/đáng tin hay không — kể cả khi phân loại chuẩn (GAP-046) hoàn toàn trống hoặc chỉ ở trạng thái suy luận (INFERRED), không có xác nhận (CONFIRMED) nào từng tồn tại trong hệ thống hôm nay vì chưa có màn hình nào cho phép tạo ra nó.

## Người dùng bị ảnh hưởng
Nhân viên sales tạo/chuyển đổi Opportunity (bị gán sai loại dịch vụ một cách âm thầm); người xem báo cáo KPI theo loại dịch vụ (`BusinessKpiService`, số liệu bị lệch về Architecture); người dùng tính năng gợi ý AI cho hạng mục thiết kế (`DesignItemPageController`, gợi ý bị lệch ngữ cảnh cho các dự án không phải kiến trúc); mọi slice tương lai phụ thuộc vào phân loại đáng tin cậy (Portfolio, OPPM, Control Tower) sẽ kế thừa vấn đề này nếu không xử lý trước.

## Bằng chứng
Đọc trực tiếp mã nguồn hiện tại trên baseline `87bb7d36` (không suy đoán), có trích dẫn file:line đầy đủ cho từng phát hiện, cộng với việc tự chạy lại bài test hồi quy hiện có của GAP-046 (`OpportunityConversionUnchangedTest.php`, đã đọc toàn văn) để xác nhận độc lập rằng chuyển đổi WON→Project hôm nay không đọc/ghi bất kỳ dòng phân loại chuẩn nào. Toàn bộ chi tiết — ma trận 11 giả thuyết gốc rễ (H1–H11), bảng route/write-path, bảng consumer của `service_category`, bằng chứng UI, bằng chứng cổng pipeline/Quote/WON, và các khoảng trống chưa biết — trong `docs/audits/2026-08-29-gap-048-crm-classification-gates-audit.md`.

## Tác động nếu không xử lý
Vấn đề mặc định sai âm thầm tiếp tục lan rộng mỗi khi có Opportunity mới; các slice tương lai đã được SSOT quy hoạch (Opportunity→Project Propagation, Quote Scope Snapshot, Portfolio Membership, Project OPPM, Control Tower) đều giả định có phân loại đáng tin cậy làm nền — nếu GAP-048 không được xử lý trước, mỗi slice đó sẽ phải tự vá vấn đề này một cách rời rạc.

## Phạm vi đề xuất
Xác nhận Gate 1 (điều tra + bằng chứng) đã hoàn tất và đầy đủ; nếu Owner duyệt, Gate 2 sẽ thiết kế cụ thể: UX phân loại trung thực cho Opportunity (0..N Service Line chuẩn), luồng xác nhận CONFIRMED tường minh cho người dùng, cổng kiểm soát ở scope_defined/Quote chính thức/WON dựa trên Service Line đã CONFIRMED, gỡ bỏ mặc định "architecture" âm thầm, và khả năng tương thích hẹp cho 2 consumer hiện đang dùng `service_category` (`BusinessKpiService`, `DesignItemPageController`). Ranh giới ứng viên chi tiết ở mục 19-20 của tài liệu audit.

## Loại trừ rõ ràng
Không đề xuất bất kỳ thay đổi migration/model/controller/service/route/UI nào ở Gate 1 này. Không gộp: Opportunity→Project Service-Line propagation, Project classification UX, historical Project backfill, Quote Scope Snapshot persistence, Contract multi-Service-Line, Portfolio Membership, Project OPPM, Operations Control Tower, Finance/Treasury, retirement cuối cùng của taxonomy cũ — mỗi cái đã có (hoặc sẽ có) Work ID + vòng đời Gate 1→2→3 riêng theo SSOT §14. Không đụng vào GAP-041/GAP-042/GAP-045 (work item CI/test-infrastructure không liên quan). Không mở lại GAP-046 hay GAP-047 (đã release). Không xác định lại phân bố dữ liệu production thật — hiện KHÔNG có sẵn để kiểm tra, được báo cáo rõ là UNKNOWN, không suy đoán.

## Đề xuất
Đội kỹ thuật đề xuất: Owner phê duyệt Gate 1 để tiến sang Gate 2 (thiết kế UX + cổng kiểm soát) — vấn đề đã được chứng minh bằng bằng chứng trực tiếp (không suy đoán), ranh giới phạm vi đã được đối chiếu với phụ thuộc thực tế trong repo và không phát hiện phụ thuộc ẩn nào buộc phải mở rộng phạm vi ra ngoài danh sách ứng viên đã liệt kê.

## Decision Needed
Owner chọn một trong: Approve để tiến sang thiết kế (Gate 2) / Yêu cầu thêm thông tin / Từ chối / Hoãn lại.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt bất kỳ thay đổi code, schema, migration, UI cụ thể, hay cơ chế kỹ thuật nào ở bước này — chỉ xác nhận vấn đề có thật, phạm vi điều tra đã đủ, và đáng để tiến hành thiết kế Gate 2. Owner cũng không được yêu cầu quyết định thiết kế UX/cổng kiểm soát cụ thể, tên trường, hay cơ chế tương thích ngược chính xác cho `service_category` — đó là quyết định của Gate 2.
