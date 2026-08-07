---
work_id: GAP-010b
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: docs/superpowers/specs/2026-08-06-gap-010b-legacy-csv-export-safety-design.md
  plan: null
  branch: docs/GAP-010b-legacy-csv-export-safety
  pr: https://github.com/kha997/zenamanagephp/pull/243
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
  created_at: "2026-08-07T07:12:43+07:00"
  updated_at: "2026-08-07T13:14:39+07:00"
generated_by: agent
---

## OWNER GATE 2: DESIGN PRESENTED

Đội kỹ thuật trình bày thiết kế Gate 2 cho GAP-010b để owner xem xét. Chưa có mã nguồn nào được sửa, chưa có implementation plan nào được tạo. Bản thiết kế đầy đủ nằm tại `docs/superpowers/specs/2026-08-06-gap-010b-legacy-csv-export-safety-design.md`.

## Trước / Sau

**Trước:** `POST /tasks/bulk/export` và `POST /projects/bulk/export` xuất CSV không tách bạch giữa escaping cấu trúc CSV và vô hiệu hoá công thức bảng tính, dựng toàn bộ dữ liệu trong bộ nhớ tại nhiều tầng khác nhau (không chỉ bước ghi file cuối). Đồng thời route hiện **không gọi được** vì controller thiếu dòng `use Illuminate\Http\Request;`, và câu truy vấn xuất dữ liệu **không lọc theo tenant** (đã tách thành GAP-034, work item riêng).

**Sau (đề xuất, chờ owner chọn ở Gate 3):** công thức bảng tính bị vô hiệu hoá an toàn bằng lớp riêng biệt với escaping cấu trúc CSV chuẩn (không phá dữ liệu hợp lệ như số điện thoại bắt đầu bằng `+`); bộ nhớ dùng có giới hạn xuyên suốt toàn bộ chuỗi xử lý (câu truy vấn → hydrate model → transform → encode CSV → ghi file), không chỉ bước ghi cuối; route gọi được đúng như mô tả API hiện tại; hai route xuất dữ liệu **không được khôi phục hoạt động** cho tới khi GAP-034 (lọc theo tenant) cũng được triển khai và xác minh xong.

## Phát hiện quan trọng cần owner biết trước khi quyết định

1. **Route hiện không hoạt động được (đã xác minh bằng cách chạy thử thật, không phải suy đoán, và vẫn đúng với `main` hiện tại):** `ExportController.php` không có dòng `use Illuminate\Http\Request;`, và hai hàm `exportTasks()`/`exportProjects()` khai kiểu tham số là `Request` — vì không có `use`, PHP hiểu đây là `App\Http\Controllers\Api\Request`, một class không tồn tại. Khi Laravel cố tạo tham số này để gọi hàm, nó báo lỗi `Target class [App\Http\Controllers\Api\Request] does not exist.` — **lỗi xảy ra trước khi vào được thân hàm**, nên khối `try/catch` trong controller không bắt được. Kết quả: gọi 2 route này ngay bây giờ luôn thất bại, không xuất được gì cả — nghĩa là rủi ro chèn công thức và hết bộ nhớ **hiện tại không khai thác được**. Gate 2 này **ghi nhận** phát hiện này lại (vẫn đúng) nhưng **không sửa** — không "sửa nhanh import để route chạy lại" ở bước Gate 2.
2. **Tenant filtering là HARD BLOCKER riêng (GAP-034, PR #246, Gate 1 đang chờ owner):** Model `Task`/`Project` không có cơ chế tự động lọc theo tenant, `ExportController` cũng không tự lọc. GAP-034 là work item quản trị hoàn toàn tách biệt — có Gate 1/2/3, acceptance criteria, test, bằng chứng, và quyết định owner riêng. **Hai route xuất dữ liệu của GAP-010b không được khôi phục hoạt động cho tới khi GAP-034 cũng được triển khai và xác minh xong** — kể cả khi GAP-010b tự nó đã sẵn sàng. Thiết kế GAP-010b **không** thêm bất kỳ bộ lọc tenant nào "tiện tay" — đó là phạm vi của GAP-034.
3. **Phân quyền hiện tại rất chung chung, không riêng cho tính năng xuất dữ liệu:** route chỉ dùng middleware `rbac` không kèm quyền cụ thể — không thuộc phạm vi GAP-010b, giữ nguyên như hiện tại.

## Owner cần quyết định (không phải kỹ thuật)

- **Có đưa việc sửa lỗi thiếu `use Request` vào cùng phạm vi implementation của GAP-010b không?** Đội kỹ thuật đề xuất: có, vì không thể xác minh bất kỳ phần nào của thiết kế xuất dữ liệu an toàn bằng kiểm thử thực tế nếu route còn không gọi được — nhưng đây vẫn là quyết định của owner tại Gate 3, không tự ý gộp ở Gate 2 này.
- **Có chấp nhận thiết kế bảo đảm bộ nhớ giới hạn xuyên suốt toàn bộ chuỗi xử lý (không chỉ bước ghi file) như trình bày trong bản thiết kế đính kèm không?**
- **Có chấp nhận việc GAP-034 là điều kiện bắt buộc (hard blocker) trước khi khôi phục route, thay vì gộp lọc tenant vào GAP-010b không?** Đội kỹ thuật đề xuất: có — đã tách theo đúng chỉ đạo owner trước đó.

Xem đầy đủ phân tích kỹ thuật, quyết định thiết kế, tiêu chí chấp nhận, và kế hoạch kiểm thử tại bản thiết kế đính kèm.

## Trạng thái và bước tiếp theo

Gate 1 đã duyệt → **Gate 2 đang chờ owner (bước này)** → Gate 3 (chưa bắt đầu, chưa được phép). PR #243 vẫn là Draft, chưa merge, chưa có mã nguồn nào thay đổi, chưa có implementation plan nào được tạo.

## Ngoại lệ

GAP-010c, GAP-034 (work item riêng, hard blocker — xem trên), và các gap khác không thuộc phạm vi quyết định này.
