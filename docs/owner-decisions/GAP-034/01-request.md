---
work_id: GAP-034
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_more_info_or_decline_or_defer"
references:
  spec: null
  plan: null
  branch: docs/GAP-034-export-tenant-isolation
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-07T07:49:49+07:00"
  owner_response_reference: "ChatGPT project conversation — owner directive 2026-08-06/07 splitting the tenant-filtering finding out of GAP-010b into its own work item"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-07T07:49:49+07:00"
  updated_at: "2026-08-07T07:49:49+07:00"
generated_by: agent
---

## Owner Summary
Khi chuẩn bị Gate 2 cho GAP-010b (đường xuất CSV cũ), phát hiện thêm một lỗ hổng cách ly dữ liệu nghiêm trọng hơn: câu truy vấn xuất dữ liệu không lọc theo tenant. Owner đã chỉ đạo tách phát hiện này thành work item riêng, liên kết với GAP-010b như một điều kiện bắt buộc (hard blocker) trước khi 2 route xuất dữ liệu được khôi phục hoạt động.

## Vấn đề vận hành
`ExportController::exportTasks()`/`exportProjects()` (`app/Http/Controllers/Api/ExportController.php:16-132`) xây dựng câu truy vấn `Task::with(['project','assignments'])->get()` và `Project::with(['tasks'])->get()` **không có bất kỳ điều kiện lọc `tenant_id` nào**. `Src\CoreProject\Models\Task` không có global scope theo tenant; `Src\CoreProject\Models\Project` có một local scope `scopeForTenant()` (`Project.php:242`) nhưng đây là scope tự chọn (opt-in) — phải gọi tường minh (`Project::forTenant($id)`), và `ExportController` không bao giờ gọi nó. Middleware `tenant.isolation` (`TenantIsolationMiddleware`) chỉ xác thực header `X-Tenant-ID` khớp với tenant của người dùng — nó **không** tự động lọc câu truy vấn của bất kỳ model nào.

## Người dùng bị ảnh hưởng
Bất kỳ người dùng nào đã đăng nhập, có một trong 13 vai trò rộng mà middleware `rbac` (không kèm quyền cụ thể) cho qua (bao gồm cả `viewer`, `client`) — khi 2 route xuất dữ liệu này hoạt động được (xem GAP-010b), người dùng thuộc tenant A có thể xuất được dữ liệu task/project của **tenant B** nếu biết hoặc đoán được ID.

## Bằng chứng
Xác minh bằng cách đọc trực tiếp mã nguồn hiện tại: không có `->where('tenant_id', ...)` hay tương đương trong `ExportController.php`; không có `booted()`/`addGlobalScope()` trong `src/CoreProject/Models/Task.php` hay `src/CoreProject/Models/Project.php`; `app/Traits/TenantScope.php` tồn tại trong repo nhưng không được `use` bởi 2 model này. Phát hiện trong quá trình chuẩn bị Gate 2 của GAP-010b (2026-08-06/07), khi đọc lại toàn bộ `ExportController.php` và middleware chain để xác minh khả năng thực thi thật của route.

## Tác động nếu không xử lý
Nếu GAP-010b chỉ sửa lỗi thiếu import (khiến route gọi được trở lại — xem GAP-010b) mà không xử lý gap này, việc khôi phục route sẽ **mở ra** lỗ hổng rò rỉ dữ liệu giữa các tenant — nghiêm trọng hơn cả 2 vấn đề gốc của GAP-010b (chèn công thức bảng tính, hết bộ nhớ), vì đây là vi phạm trực tiếp ranh giới cách ly dữ liệu multi-tenant, không chỉ là lỗi định dạng file xuất ra.

## Phạm vi đề xuất
Thiết kế và triển khai lọc tenant bắt buộc, không tuỳ chọn, ở đúng tầng câu truy vấn cho cả hai đường xuất (task và project) trong `ExportController`, đảm bảo người dùng chỉ xuất được dữ liệu thuộc tenant của chính họ — kể cả khi ID được truyền trực tiếp trong `task_ids`/`project_ids`.

## Loại trừ rõ ràng
Không thiết kế lại mô hình RBAC (13 vai trò rộng của middleware `rbac` giữ nguyên, không mở rộng không thu hẹp — đó là phạm vi khác, không thuộc gap này). Không sửa lỗi thiếu import `Illuminate\Http\Request` (thuộc GAP-010b). Không sửa 2 rủi ro chèn công thức bảng tính/hết bộ nhớ (thuộc GAP-010b). GAP-034 chỉ xử lý đúng một việc: đảm bảo câu truy vấn xuất dữ liệu bị giới hạn đúng theo tenant.

## Đề xuất
Đội kỹ thuật đề xuất: phê duyệt để tiến hành thiết kế chi tiết (Gate 2) cho GAP-034, và đánh dấu GAP-034 là điều kiện bắt buộc (hard blocker) — 2 route xuất dữ liệu của GAP-010b không được khôi phục hoạt động cho đến khi GAP-034 cũng được triển khai và xác minh xong (có thể cùng một đợt/PR triển khai với GAP-010b, nhưng phạm vi, tiêu chí chấp nhận, kiểm thử, bằng chứng và quyết định owner của từng work item vẫn phải tách biệt).

## Decision Needed
Owner chọn một trong: **Phê duyệt để tiến hành thiết kế (Gate 2)** / **Yêu cầu thêm thông tin** / **Từ chối** / **Hoãn lại**.

## What the owner is NOT being asked to decide
Owner không được yêu cầu phê duyệt bất kỳ thay đổi mã nguồn, class, thư viện, hay chi tiết kỹ thuật triển khai nào ở bước này — chỉ xác nhận vấn đề là có thật và cho phép tiến hành thiết kế chi tiết. Owner cũng không được yêu cầu quyết định về GAP-010b, GAP-010c, hay bất kỳ gap nào khác trong yêu cầu này.
