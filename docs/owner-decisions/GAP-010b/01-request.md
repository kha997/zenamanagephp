---
work_id: GAP-010b
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_more_info_or_decline_or_defer"
references:
  spec: null
  plan: null
  branch: docs/GAP-010b-legacy-csv-export-safety
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
  created_at: "2026-08-06T15:31:23+07:00"
  updated_at: "2026-08-06T15:31:23+07:00"
generated_by: agent
---

## OWNER GATE 1: REQUEST

Đề nghị owner phê duyệt để tiến hành thiết kế chi tiết (Gate 2) cho việc xử lý GAP-010b.

## Owner Summary
`OPERATIONAL_GAP_REGISTER.md` (vừa được owner duyệt phát hành ở OWN-2026-003) ghi nhận GAP-010b là **OPEN (verified 2026-08-06)** — một đường xuất CSV cũ vẫn đang hoạt động thật trên hệ thống, có 2 vấn đề đã xác minh lại bằng cách đọc trực tiếp mã nguồn hiện tại. Đây là yêu cầu Gate 1 để bắt đầu quy trình xử lý — chưa sửa gì, chỉ xin phép thiết kế.

## Vấn đề vận hành
- **Route đang sống thật:** `routes/api.php:1008-1009` đăng ký `POST /tasks/bulk/export` và `POST /projects/bulk/export`, cả hai đều gọi vào `ExportController` — bất kỳ ai có quyền truy cập tính năng xuất dữ liệu đều có thể kích hoạt đường này ngay bây giờ.
- **Chèn công thức bảng tính (formula injection) vẫn còn:** Hàm `generateCsv()` tại `app/Http/Controllers/Api/ExportController.php:137-174` chỉ escape dấu ngoặc kép (`str_replace('"', '""', $field)`), không kiểm tra hay escape ký tự đầu ô như `=`, `+`, `-`, `@`. Nếu một trường dữ liệu (ví dụ tên task, mô tả) do người dùng nhập bắt đầu bằng một trong các ký tự này, khi mở file CSV xuất ra bằng Excel/Google Sheets, nội dung đó có thể bị máy tính bảng tính hiểu nhầm thành công thức và thực thi — đây là lỗ hổng chèn công thức bảng tính đã được biết đến rộng rãi.
- **Dựng toàn bộ file trong bộ nhớ (nguy cơ hết bộ nhớ):** Cùng hàm này xây dựng toàn bộ nội dung CSV thành một biến chuỗi (`$csvContent`) trong bộ nhớ trước khi ghi một lần bằng `Storage::put()` — không dùng cách ghi theo luồng (streaming). Với danh sách task hoặc project lớn, việc này có thể làm tiến trình hết bộ nhớ hoặc time-out.

## Người dùng bị ảnh hưởng
Bất kỳ người dùng nào có quyền xuất task/project qua API (rủi ro chèn công thức: người mở file CSV xuất ra, có thể là chính người dùng đó hoặc người khác nhận file); đội vận hành hệ thống (rủi ro hết bộ nhớ khi xuất dữ liệu lớn, có thể ảnh hưởng tiến trình hoặc worker dùng chung).

## Bằng chứng
Xác minh Wave 1 (OWN-2026-002, đã được owner duyệt phát hành qua OWN-2026-003) đọc trực tiếp mã nguồn hiện tại trên `main` — trích dẫn cụ thể: `app/Http/Controllers/Api/ExportController.php:137-174` (hàm `generateCsv()`, không escape ký tự công thức, dựng chuỗi trong bộ nhớ), route sống tại `routes/api.php:1008-1009`. Việc yêu cầu Gate 1 này tái xác nhận cùng trích dẫn bằng cách đọc lại chính các dòng đó tại thời điểm hiện tại — nội dung khớp với ghi nhận trong sổ đăng ký.

## Tác động nếu không xử lý
Đường xuất CSV cũ tiếp tục là một lỗ hổng chèn công thức đang mở, có thể bị khai thác bất cứ lúc nào có người dùng nhập dữ liệu độc hại rồi xuất ra; đồng thời rủi ro hết bộ nhớ khi xuất dữ liệu lớn vẫn còn treo, có thể gây gián đoạn dịch vụ.

## Phạm vi đề xuất
Xin phép tiến hành **thiết kế chi tiết (Gate 2)** cho việc khắc phục GAP-010b, với mục tiêu nghiệp vụ: **xuất dữ liệu an toàn, đáng tin cậy, không làm hỏng dữ liệu hợp lệ.** Thiết kế Gate 2 sẽ xác định:
- Cách vô hiệu hoá nguy cơ chèn công thức bảng tính mà không phá hỏng dữ liệu hợp lệ (ví dụ dữ liệu hợp lệ bắt đầu bằng dấu `+` trong số điện thoại).
- Cách xử lý nguy cơ hết bộ nhớ (ví dụ chuyển sang ghi theo luồng) mà vẫn giữ nguyên hành vi API hiện tại đối với các client đang gọi.
- Ranh giới khả năng hoàn tác (rollback) — sẽ được thiết kế cụ thể ở Gate 2, chưa quyết định ở Gate 1 này.
- Việc tương thích ngược với API hiện tại (request/response format, tên endpoint, quyền truy cập) phải được đánh giá kỹ trước khi triển khai bất kỳ thay đổi nào.

## Loại trừ rõ ràng
Yêu cầu Gate 1 này **không** chọn sẵn class, thư viện, hay chi tiết kỹ thuật triển khai streaming — những quyết định đó thuộc về Gate 2. Việc phê duyệt Gate 1 **không** cho phép sửa bất kỳ mã nguồn nào. Phân quyền tenant, xác thực (authorization), và các quy tắc truy cập xuất dữ liệu hiện có **phải giữ nguyên không đổi** trong toàn bộ quá trình xử lý GAP-010b — đây là ràng buộc bắt buộc cho thiết kế Gate 2, không phải điều được quyết định lại.

## Khả năng hoàn tác
Việc phê duyệt Gate 1 này chỉ cho phép thiết kế, không có gì để hoàn tác ở bước này. Ranh giới hoàn tác cho chính việc triển khai sẽ được thiết kế cụ thể ở Gate 2.

## Đề xuất
Đội kỹ thuật đề xuất: phê duyệt để tiến hành thiết kế chi tiết (Gate 2) cho GAP-010b, với các ràng buộc bắt buộc nêu trên (giữ nguyên phân quyền/xác thực, đánh giá tương thích API trước khi triển khai).

## Decision Needed
Owner cần chọn một trong: **Phê duyệt để tiến hành thiết kế (Gate 2)** / **Yêu cầu thêm thông tin** / **Từ chối** / **Hoãn lại**.

## What the owner is NOT being asked to decide
Owner không được yêu cầu phê duyệt bất kỳ thay đổi mã nguồn, class, thư viện, hay chi tiết kỹ thuật triển khai nào — chỉ xác nhận việc xử lý GAP-010b là cần thiết và cho phép tiến hành thiết kế chi tiết. Owner cũng không được yêu cầu quyết định về GAP-010c, GAP-014b, GAP-014c, hay bất kỳ gap nào khác trong yêu cầu này.
