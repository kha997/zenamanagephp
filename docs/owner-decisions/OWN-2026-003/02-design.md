---
work_id: OWN-2026-003
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: null
  plan: null
  branch: docs/OWN-2026-003-wave1-register-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/241
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
  created_at: "2026-08-06T13:05:06+07:00"
  updated_at: "2026-08-06T13:05:06+07:00"
generated_by: agent
---

## Owner Summary
Đây là thiết kế chi tiết (không phải triển khai) cho việc cập nhật lại sổ đăng ký gap vận hành (`OPERATIONAL_GAP_REGISTER.md`) cho đúng với kết quả xác minh Wave 1. **Sổ đăng ký chưa hề bị đụng vào** — tài liệu này chỉ trình bày chính xác nội dung sẽ được sửa, để owner xem qua và quyết định trước khi việc sửa thật sự diễn ra.

## Bảng đối chiếu chi tiết

| ID chính thức | Nội dung/trạng thái hiện tại trong sổ đăng ký | Kết quả xác minh Wave 1 | Nội dung/trạng thái đề xuất | Nguồn bằng chứng | Cách giữ lịch sử |
|---|---|---|---|---|---|
| GAP-001 | `UNVERIFIED (audit nói đã vá 2026-07-09)` — "Cross-tenant IDOR trong Web TaskController/DocumentController" | Đã xác minh: mỗi công ty (tenant) chỉ thấy đúng dữ liệu của mình trên các đường dẫn thật đang hoạt động; có 2 đoạn mã debug không lọc theo công ty nhưng không ai truy cập được (mã còn sót lại, không dùng tới) | `RESOLVED (verified 2026-08-06)` | `app/Http/Controllers/Web/TaskController.php:145,168`, `DocumentController.php:64,140,181` | Giữ nguyên dòng gốc + thêm dòng "Xác minh Wave 1 (2026-08-06)" nối tiếp, không xoá câu chữ cũ |
| GAP-003 | `UNVERIFIED` — "Provenance bảng zena_submittals vs submittals chưa rõ ràng" | Đã xác minh: `zena_submittals` đã được khai báo deprecated rõ ràng trong migration; bảng chính thức `submittals` và model `Submittal` duy nhất; không còn tham chiếu sống nào tới bảng cũ | `RESOLVED (verified 2026-08-06)` | `database/migrations/2025_09_16_083456_deprecate_zena_design_construction_tables.php:75-86`; `database/migrations/2026_07_07_000001_create_submittals_table.php` | Giữ nguyên dòng gốc + thêm ghi chú xác minh, không xoá |
| GAP-004 | `UNVERIFIED (audit nói đã vá 2026-07-09)` — "Viewer role có thể tạo task qua POST /app/tasks do thiếu rbac:task.create" | Đã xác minh: middleware `rbac:task.create` có mặt trên route thật, có test hồi quy đang chạy | `RESOLVED (verified 2026-08-06)` | `routes/web.php:396`; `tests/Feature/RBACRolesPermissionsTest.php` | Giữ nguyên dòng gốc + thêm ghi chú xác minh |
| GAP-005 | `UNVERIFIED (audit nói đã vá)` — "SSRF qua webhook URL" | Đã xác minh: hai lớp chặn độc lập ngăn webhook gọi tới địa chỉ nội bộ/nhạy cảm — chặn lúc tạo webhook, và chặn lại lần nữa ngay lúc gửi thật (để tránh trường hợp địa chỉ web đổi hướng sau khi đã qua lớp chặn đầu) | `RESOLVED (verified 2026-08-06)` | `app/Http/Controllers/Web/WebhookPageController.php:38-42`; `app/Jobs/DeliverWebhook.php:97-114` | Giữ nguyên dòng gốc + thêm ghi chú xác minh |
| GAP-006 | `UNVERIFIED (audit nói đã vá)` — "Race condition tạo trùng daily log" | Đã xác minh: ràng buộc unique ở cơ sở dữ liệu + bắt lỗi trả về thông báo hợp lệ thay vì lỗi hệ thống | `RESOLVED (verified 2026-08-06)` | `database/migrations/2026_07_08_100000_create_site_diaries_table.php:41`; `app/Http/Controllers/Api/SiteDiaryController.php:198-207` | Giữ nguyên dòng gốc + thêm ghi chú xác minh |
| GAP-007 | `UNVERIFIED (audit nói đã vá)` — "Webhook retry đếm trùng lần thất bại" | Đã xác minh: điều kiện chặn đúng cả hai đường thử lại | `RESOLVED (verified 2026-08-06)` | `app/Jobs/DeliverWebhook.php:73-75,85-87` | Giữ nguyên dòng gốc + thêm ghi chú xác minh |
| GAP-008 | `UNVERIFIED (audit nói đã vá)` — "LIKE-filter injection trong tìm kiếm activity feed" | Đã xác minh: ký tự đặc biệt được thoát trước khi đưa vào câu truy vấn | `RESOLVED (verified 2026-08-06)` | `app/Http/Controllers/Web/ActivityFeedPageController.php:26-27` | Giữ nguyên dòng gốc + thêm ghi chú xác minh |
| GAP-009 | `UNVERIFIED (audit nói đã vá)` — "Tạo API token không có rate limit" | Đã xác minh: giới hạn tần suất có mặt trên route thật | `RESOLVED (verified 2026-08-06)` | `routes/web.php:952` | Giữ nguyên dòng gốc + thêm ghi chú xác minh |
| GAP-010 (mục cha) | `UNVERIFIED (audit nói đã vá toàn bộ)` — "Cụm lỗi nhỏ: CSV formula injection, lộ secret qua flash message, OOM khi export, lệch timezone Gantt" | Xác minh riêng từng phần cho kết quả KHÁC NHAU — không thể coi cả cụm là một trạng thái duy nhất | `PARTIALLY RESOLVED (verified 2026-08-06)` — xem 3 dòng con GAP-010a/b/c ngay bên dưới | (xem từng dòng con) | Giữ nguyên dòng gốc làm mục cha, đổi Status thành "PARTIALLY RESOLVED", thêm ghi chú trỏ tới 3 dòng con mới |
| GAP-010a (dòng con mới) | *(chưa có, sẽ thêm mới)* | Đã xác minh: đường xuất báo cáo chính thức (đường mà người dùng thật sự dùng) đã thoát công thức CSV, dùng cách xuất tiết kiệm bộ nhớ, và lộ bí mật đã sửa bằng khoá phiên riêng | `RESOLVED (verified 2026-08-06)` | `app/Http/Controllers/Web/ReportPageController.php:122-158` | Dòng mới, ghi rõ "tách từ GAP-010, phần đã đóng" |
| GAP-010b (dòng con mới) | *(chưa có, sẽ thêm mới)* | Đã xác minh: một đường xuất CSV cũ hơn (`ExportController::generateCsv()`) **vẫn đang hoạt động thật**, không thoát công thức, và dựng toàn bộ file trong bộ nhớ — cùng loại lỗi mà audit cũ tưởng đã vá hết | `OPEN (verified 2026-08-06)` — **lỗi thật, đang mở, cần ưu tiên xử lý** | `app/Http/Controllers/Api/ExportController.php:137-174`; route sống tại `routes/api.php:1008-1009` | Dòng mới, ghi rõ "tách từ GAP-010, phần còn mở — audit gốc không phát hiện vì đường xuất mới được thêm song song, không thay thế đường cũ" |
| GAP-010c (dòng con mới) | *(chưa có, sẽ thêm mới)* | Tìm thấy một trang thật đang hoạt động (`/schedule`) có cách hiển thị ngày tháng (cắt chuỗi ký tự thô thay vì định dạng theo múi giờ) khớp với kiểu lỗi lệch múi giờ Gantt mà audit cũ mô tả — **nhưng CHƯA thực hiện bước tái hiện lỗi thật nào** | `REOPENED FOR REPRODUCTION (2026-08-06) — chưa xác nhận là lỗi thật` | `routes/web.php:934` (`SchedulePageController`); `resources/views/schedule/index.blade.php:112,116` | Dòng mới, ghi rõ nguyên văn: "mở lại để xác nhận tái hiện, KHÔNG PHẢI lỗi đã xác nhận — cần Gate 1 riêng cho bước tái hiện trước khi coi là gap thật" |
| GAP-014 (mục cha) | `UNVERIFIED (một phần có thể đã đóng)` — "NCR/CAPA: dashboard và ngữ nghĩa thông báo còn deferred; liên kết NCR↔task lưu trữ lâu dài còn UNKNOWN" | Xác minh riêng từng phần cho kết quả KHÁC NHAU | `PARTIALLY RESOLVED (verified 2026-08-06)` — xem 3 dòng con GAP-014a/b/c ngay bên dưới | (xem từng dòng con) | Giữ nguyên dòng gốc làm mục cha, đổi Status thành "PARTIALLY RESOLVED", thêm ghi chú trỏ tới 3 dòng con mới |
| GAP-014a (dòng con mới) | *(chưa có, sẽ thêm mới)* | Đã xác minh: bảng điều khiển NCR/CAPA tính số liệu thật, đúng tenant, từ dữ liệu sống | `RESOLVED (verified 2026-08-06)` | `app/Http/Controllers/Api/SiteEngineerDashboardController.php:80,101-160` | Dòng mới, ghi rõ "tách từ GAP-014, phần đã đóng" |
| GAP-014b (dòng con mới) | *(chưa có, sẽ thêm mới)* | Đã xác minh: mã gửi thông báo NCR đã viết đầy đủ nhưng **chưa từng được kích hoạt** — giống như đã lắp chuông báo nhưng chưa nối dây điện, nên không có NCR nào thật sự kích hoạt việc gửi thông báo | `OPEN (verified 2026-08-06)` — thông báo NCR không hoạt động thật | `app/Listeners/NcrEventListener.php`; không có nơi nào gọi `NcrCreated`/`NcrAssigned`/`NcrResolved`; không đăng ký trong `app/Providers/EventServiceProvider.php` | Dòng mới, ghi rõ "tách từ GAP-014, phần còn mở" |
| GAP-014c (dòng con mới) | *(chưa có, sẽ thêm mới)* | Đã xác minh: không có cột hay quan hệ nào lưu liên kết lâu dài giữa NCR và công việc khắc phục — chỉ liên kết tạm qua tag, không bền | `OPEN (verified 2026-08-06)` — chưa có lưu trữ liên kết bền | `database/migrations/2025_09_20_142033_create_ncrs_table.php` (không có cột `task_id`/`capa_task_id`); `app/Models/Ncr.php` (không có quan hệ task) | Dòng mới, ghi rõ "tách từ GAP-014, phần còn mở" |
| Mục "Chưa làm trong lần hợp nhất này" (phần văn xuôi cuối file, không phải bảng) | "Chưa verify lại 9 mục đánh dấu `UNVERIFIED` liên quan bảo mật (GAP-001, GAP-004 đến GAP-010) — audit gốc tự báo đã vá, nhưng chưa có re-check trực tiếp trong phiên này." | Câu này đã LỖI THỜI sau khi 8/9 mục trong danh sách này (GAP-001, 004-009) được xác minh ở Wave 1, và GAP-010 được tách thành 3 dòng con — nếu không sửa, câu này sẽ mâu thuẫn trực tiếp với chính các dòng bảng bên trên nó | Xoá câu này khỏi danh sách "Chưa làm", vì việc "chưa verify" đã không còn đúng — thay bằng một dòng mới: "Đã verify lại 9 mục UNVERIFIED liên quan bảo mật trong Wave 1 (2026-08-06) — xem OWN-2026-002/OWN-2026-003; GAP-010 tách thành 3 dòng con." | (tự tham chiếu — đây là chính sổ đăng ký, không phải mã nguồn) | Không phải xoá lịch sử kỹ thuật, chỉ cập nhật một câu tóm tắt "việc chưa làm" cho đúng với thực tế mới sau khi việc đó ĐÃ được làm — không đụng tới nội dung audit gốc trong các dòng bảng |

## Quy tắc ngôn ngữ trạng thái
Đề xuất dùng đúng từ vựng trạng thái sổ đăng ký đã có sẵn (`RESOLVED (verified)`, `OPEN (verified)`, `UNVERIFIED`, `BLOCKED (external)`) — không phát minh từ mới. Hai trường hợp ngoại lệ cần thêm chữ rõ ràng vì sổ đăng ký hiện chưa có sẵn:
- **`PARTIALLY RESOLVED (verified)`** cho GAP-010 và GAP-014 (mục cha) — vì các mục này có phần đã đóng, phần chưa đóng, không thể gọi chung một trạng thái đơn.
- **`REOPENED FOR REPRODUCTION`** riêng cho GAP-010c — để không thể bị đọc nhầm là "OPEN (verified)" (tức đã xác nhận chắc chắn là lỗi thật). GAP-010c chỉ là "tìm thấy nơi khả nghi", không phải "xác nhận có lỗi".

## Cách trình bày dòng con trong sổ đăng ký (ví dụ minh hoạ)
Sổ đăng ký hiện là bảng phẳng (mỗi dòng một ID), không có cấu trúc lồng nhau. Đề xuất thêm dòng con ngay sau dòng cha, đúng theo khuôn mẫu đã có sẵn trong sổ đăng ký (ví dụ GAP-031 → GAP-032/GAP-033 đã là các dòng riêng nối tiếp nhau theo đúng cách này). Không tạo cấu trúc bảng mới.

## Việc bảo toàn lịch sử
- Câu chữ audit gốc, ngày audit gốc, nguồn trích dẫn gốc: **giữ nguyên, không xoá** trên mọi dòng.
- Tuyên bố sửa lỗi trước đây (ví dụ "vá bằng throttle:6,1"): **giữ nguyên**.
- Thêm — không thay thế — một đoạn "Xác minh Wave 1 (2026-08-06)" cho mỗi dòng, ghi rõ kết quả xác minh mới và nguồn bằng chứng mới.
- 6 dòng con mới (GAP-010a/b/c, GAP-014a/b/c) là dòng hoàn toàn mới, không thay thế dòng cha — dòng cha vẫn còn, chỉ đổi Status và trỏ sang dòng con.

## Tính nhất quán tổng thể sổ đăng ký
Đã đọc toàn bộ 110 dòng của `OPERATIONAL_GAP_REGISTER.md`: **không có bảng tổng số, chỉ số đếm, hay bảng tóm tắt dạng bảng nào** trong toàn bộ file — chỉ có 8 mục "Tier" được nhóm theo tiêu đề, không có số đếm mục trong mỗi tier. Tuy nhiên, một vòng review độc lập đã phát hiện: mục văn xuôi **"Chưa làm trong lần hợp nhất này"** ở cuối file có một câu liệt kê chính xác 9/10 mục Wave 1 là "chưa verify" — câu này sẽ trở nên sai và tự mâu thuẫn với các dòng bảng mới nếu không sửa cùng lúc. Đã bổ sung câu này vào phạm vi cập nhật (xem dòng cuối bảng đối chiếu ở trên). Ngoài câu này, không còn tổng số, chỉ số, hay câu văn xuôi nào khác cần đồng bộ lại.

## Tiêu chí chấp nhận nghiệp vụ
1. Mọi dòng bị ảnh hưởng giữ nguyên toàn bộ lịch sử audit gốc.
2. 8 mục đã xác nhận sửa đúng (GAP-001,003,004,005,006,007,008,009) được đánh dấu rõ ràng là đã xác minh.
3. GAP-010 và GAP-014 (mục cha) được thể hiện rõ ràng là "PARTIALLY RESOLVED".
4. GAP-010b vẫn ở trạng thái mở và được ưu tiên.
5. GAP-010c chỉ được ghi nhận là "chờ xác nhận tái hiện" — không phải lỗi đã xác nhận.
6. GAP-014b và GAP-014c vẫn ở trạng thái mở.
7. Sổ đăng ký không có bảng tổng số/chỉ số nào cần đồng bộ lại; câu văn xuôi duy nhất cần cập nhật cho khỏi mâu thuẫn (mục "Chưa làm trong lần hợp nhất này") đã được đưa vào phạm vi cập nhật ở trên.
8. Không có thay đổi nào đối với mã nguồn sản phẩm, test, route, migration, workflow, hay quyền hạn.
9. Toàn bộ việc cập nhật có thể hoàn tác bằng một lần revert duy nhất, chỉ ảnh hưởng tài liệu.
10. Việc cập nhật này KHÔNG cấp phép triển khai bất kỳ gap nào.

## Rollback
Hoàn tác bằng cách revert đúng một commit sửa tài liệu trong tương lai (commit cập nhật `OPERATIONAL_GAP_REGISTER.md`) — không có tác dụng phụ nào khác cần khôi phục.

## Loại trừ phạm vi
Gate 2 này KHÔNG bao gồm: sửa GAP-010b; tái hiện hay sửa GAP-010c; triển khai GAP-014b/GAP-014c; thiết kế hay triển khai GAP-032/GAP-033/GAP-030; hay bất kỳ gap nào khác. Phạm vi duy nhất là nội dung cập nhật `OPERATIONAL_GAP_REGISTER.md` như bảng đối chiếu ở trên.

## Decision Needed
Owner chọn một trong ba: **Phê duyệt để tiến hành cập nhật tài liệu (sửa `OPERATIONAL_GAP_REGISTER.md` đúng như bảng trên — không phải mã nguồn)** / **Yêu cầu chỉnh sửa** / **Từ chối**.

## What the owner is NOT being asked to decide
Owner không được yêu cầu quyết định về cách trình bày kỹ thuật của bảng markdown, cấu trúc file, hay công cụ kiểm tra. Owner cũng không được yêu cầu phê duyệt việc sửa GAP-010b, xác nhận GAP-010c, hay bất kỳ gap nào khác — chỉ quyết định nội dung cập nhật sổ đăng ký ở trên có đúng và đủ hay không.
