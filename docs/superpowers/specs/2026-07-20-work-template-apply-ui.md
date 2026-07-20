# Work Template — Apply UI (chọn & áp dụng, không soạn thảo)

## Bối cảnh

Backend `WorkTemplate` v2 (`App\Models\WorkTemplate` + `App\Http\Controllers\Api\WorkTemplateController` + `WorkTemplateCrudService`) đã hoàn thiện: CRUD, versioning, publish workflow, RBAC, tenant isolation, apply-to-project (dry-run, duplicate detection qua `apply_fingerprint`, tạo `WorkInstance` + `App\Models\Task` thật). 16/16 test hiện có (`WorkTemplateV2ApiTest`, `WorkTemplateV2RbacTest`, `WorkTemplateV2TenantIsolationTest`, `WorkTemplateV2ValidationTest`) đều xanh. Không có route `web.php` hay Blade view nào gọi tới các endpoint này — PM phải tạo Task tay từng cái dù template đã tồn tại.

**Lưu ý nợ kỹ thuật phát hiện, KHÔNG xử lý trong slice này:** `Src\CoreProject\Models\WorkTemplate` là model khác trỏ **cùng bảng** `work_templates`, không đổi gì từ commit khởi tạo repo — code chết, cần dọn ở slice riêng.

## Phạm vi

**Chỉ "chọn & áp dụng"** một `WorkTemplate` đã publish vào một Project có sẵn. **Không** bao gồm màn hình soạn thảo/tạo/sửa/publish template (tách slice sau). **Không** bao gồm apply theo Component (chỉ scope Project).

## Quyền (RBAC)

- **KHÔNG** sửa `PermissionSeeder.php` cho việc này — `DatabaseSeeder` chạy `PermissionSeeder` (thứ tự #29) **trước** `ZenaPermissionsSeeder` (#30), nơi thực sự tạo permission `template.view`/`template.apply` (kèm `name = code` đúng, cột mà `User::hasPermission()` — method thật, override trait — check). Nếu gán role trong `PermissionSeeder.php`, trên DB seed-từ-đầu (`migrate:fresh --seed`, CI, deploy mới) 2 permission này CHƯA TỒN TẠI ở thời điểm đó → `sync()` bỏ qua im lặng, không gán được gì (dev DB hiện tại "chạy đúng" chỉ vì đã seed cũ từ trước, không phản ánh đúng hành vi seed mới).
  **Sửa đúng chỗ:** thêm bước gán `template.view` + `template.apply` cho role **Project Manager** ngay trong `ZenaPermissionsSeeder::run()`, sau khi vòng lặp `updateOrCreate` các permission đã chạy xong (cùng file, chạy sau khi permission vừa được tạo với `name=code` chuẩn, không có hazard thứ tự). Không cấp `template.edit_draft`/`template.publish`/`template.delete` cho PM.
  **Nợ kỹ thuật phát hiện kèm theo (KHÔNG sửa trong slice này):** `PermissionSeeder.php` hiện có cùng hazard thứ tự này với các mã đã thêm ở PR#209 hôm nay (`task.view`, `crm.view`, `material.view`...) — trên DB seed-từ-đầu, Project Member có thể KHÔNG có các quyền đó dù PR#209 đã merge. Cần audit riêng, ghi vào memory để theo dõi.
- Route Web dùng **`middleware('rbac:template.apply')`** (và `rbac:template.view` cho endpoint danh sách) — **không** dùng `$this->authorize()`, vì `TemplatePolicy` hiện gắn với `App\Models\Template` (model legacy khác), không phải `App\Models\WorkTemplate`. Thêm `WorkTemplatePolicy` mới nằm ngoài phạm vi slice này.
- Khối UI trên `projects/show.blade.php` chỉ hiện khi `auth()->user()?->hasPermission('template.apply')`.

## Kiến trúc

**Web controller mới** `App\Http\Controllers\Web\WorkTemplateApplyController` — façade mỏng, session-auth, KHÔNG qua `auth:sanctum`:

- `GET /app/projects/{project}/work-templates` (`middleware(['rbac:template.view', 'tenant.isolation'])`)
  Query: `WorkTemplate::where('tenant_id', $tenantId)->whereHas('publishedVersions')->with('publishedVersions')->get()`, sau đó resolve bản mới nhất bằng PHP: `$template->publishedVersions->sortByDesc('published_at')->first()`. **Không** dùng `limit(1)` trong eager-load closure — `publishedVersions()` kế thừa `orderByDesc('created_at')` từ `versions()`, không phải sắp theo `published_at`, và model chưa có quan hệ `latestOfMany()`; sort bằng PHP sau khi eager-load tránh phụ thuộc thứ tự cột sai và tránh khác biệt hành vi subquery giữa sqlite (test) và MySQL (CI parity).
  Trả về mỗi template kèm `latest_published_version_id` (id của bản publish mới nhất vừa resolve) — **không** lọc theo cột `status` của `WorkTemplate` (có thể lệch với version thật), lọc theo sự tồn tại của `WorkTemplateVersion.published_at != null` qua relation `publishedVersions()` đã có sẵn trên model.

- `POST /app/projects/{project}/work-templates/preview` (`middleware(['rbac:template.apply', 'tenant.isolation'])`)
  Nhận `work_template_id` (hoặc `work_template_version_id`), **delegate** sang `app(\App\Http\Controllers\Api\WorkTemplateController::class)->applyToProject($request, $projectId)` sau khi ép `$request->merge(['dry_run' => true])`. Không gọi endpoint `preview()` riêng của API controller (endpoint đó theo một nhánh code khác, không đi qua đúng logic duplicate/summary/would_create của apply thật — dùng `applyToProject(dry_run=true)` để đảm bảo parity 100% giữa preview và apply thật).

- `POST /app/projects/{project}/work-templates/apply` (`middleware(['rbac:template.apply', 'tenant.isolation'])`)
  Giống hệt trên nhưng ép `dry_run=false` — tạo thật.

**Bắt buộc gắn `tenant.isolation` (`TenantIsolationMiddleware`) vào cả 3 route Web này** — không chỉ `rbac:*`. Lý do: `Api\WorkTemplateController::tenantId()` (dòng 949) đọc `request()->attributes->get('tenant_id')`, thuộc tính này **chỉ được set bởi `TenantIsolationMiddleware`** (đang chạy trong route group `auth:sanctum` của API, không tự động có ở route Web). Nếu thiếu middleware này, gọi `applyToProject()` từ Web controller sẽ ném thẳng `RuntimeException('Tenant context missing')`. Đã xác minh `TenantIsolationMiddleware::handle()` dùng `Auth::user()` (dòng 28) — hoạt động bình thường với session/web guard, không phụ thuộc Sanctum, nên gắn an toàn vào route Web.

Không tách logic `buildTemplateApplicationPlan` ra service riêng trong slice này (đó là "Cleaner later" — xem mục Alternatives).

## Blade / Alpine.js

Partial mới `resources/views/projects/_apply-work-template.blade.php`, nhúng vào `projects/show.blade.php` trong khối `<x-ui.card title="Áp dụng mẫu công việc">`, theo đúng pattern Alpine.js đã dùng ở `invitations/create.blade.php` (fetch tới Web route, không gọi `/api/zena/...` bằng Sanctum).

State Alpine: `templates[]`, `selectedTemplateId`, `preview` (null cho tới khi fetch), `loading`, `error`.

Luồng:
1. `x-init` fetch danh sách template đã publish. Rỗng → hiện "Chưa có mẫu công việc nào được publish. Liên hệ quản trị viên để tạo mẫu."
2. Chọn template (`<select class="operator-select">`) → bấm "Xem trước" → POST `preview` → hiện tóm tắt `summary` (`phases`, `tasks`, `checklists`, `docs`) và `would_create`.
3. **Nếu `preview.duplicate === true`**: vô hiệu hoá nút "Áp dụng" ngay tại bước này, hiện dòng "Mẫu này đã được áp dụng cho dự án trước đó."
4. Nếu không duplicate, bấm "Áp dụng" → POST `apply` (vẫn kiểm tra lại `duplicate` trong response phòng trường hợp user bypass / double-click — idempotent theo `apply_fingerprint` có sẵn ở backend) → thành công → `window.location.reload()` để card "Công việc" hiện task mới.

## Sửa kèm bắt buộc: card "Công việc" đang đọc sai model

`resources/views/projects/show.blade.php` dòng 86-107 hiện dùng `$project->tasks` — quan hệ `Project::tasks()` trỏ `Src\CoreProject\Models\Task` (**model legacy khác**, comment sẵn trong `ProjectController::show()` xác nhận: *"$project->tasks() trỏ về Src\CoreProject\Models\Task (legacy)... không dùng cho khối này"*). Việc áp dụng template tạo `App\Models\Task` thật (model canonical, đã có sẵn biến `$sectionTasks` trong controller dùng đúng model này cho khối "Hạng mục bị chặn"/thiết kế) — nếu không sửa, **task mới tạo từ template sẽ KHÔNG hiện ra** trong card "Công việc" sau khi áp dụng, coi như tính năng vô dụng trên UI dù backend chạy đúng.

**Sửa:** đổi dòng 86-105 từ `$project->tasks` sang `$sectionTasks` (biến đã tồn tại sẵn trong `ProjectController::show()`, cùng field `name`/`status`/`progress_percent`/`end_date` — đã xác minh `App\Models\Task` có đủ các cột này, tương thích 100% với markup hiện tại, không cần đổi gì trong `<x-ui.data-table>`).

## Error handling

- Không có template đã publish → empty state, không lỗi.
- `preview.duplicate === true` → vô hiệu Áp dụng ngay từ bước preview (xem trên).
- Lỗi mạng/permission/validation → banner lỗi Alpine, cho phép thử lại, không crash trang.
- Backend vẫn tự bảo vệ chống double-apply qua `apply_fingerprint` kể cả nếu UI có bug — apply lần 2 luôn trả `duplicate: true`, không tạo trùng Task/WorkInstance.

## Testing

`tests/Feature/Web/ProjectWorkTemplateApplyTest.php`:
1. Danh sách chỉ trả template có `publishedVersions` (draft-only template không xuất hiện), đúng tenant hiện tại.
2. Preview trả đúng `summary`/`would_create` khớp số phase/task/checklist/docs của version (dùng factory dựng template+version+phases+tasks có sẵn).
3. Apply tạo thật `App\Models\Task` + `WorkInstance`, response không phải `duplicate`.
4. Apply lần 2 cùng project+template → `duplicate: true`, không tạo thêm Task/WorkInstance nào (đếm lại số bản ghi trước/sau).
5. User không có `template.apply` → 403 ở cả 2 route preview/apply.
6. User không có `template.view` → 403 ở route danh sách.
7. Sau khi apply, load lại `projects/show` (`GET /app/projects/{project}`) → card "Công việc" hiển thị đúng task mới tạo (assert `assertSee` tên task) — xác nhận sửa `$sectionTasks` hoạt động.
8. Test riêng cho seeder (`tests/Unit/Seeders/ZenaPermissionsSeederTest.php` hoặc tương đương): chạy `$this->seed()` (toàn bộ `DatabaseSeeder`, mô phỏng đúng seed-từ-đầu) rồi assert role "Project Manager" có cả `template.view` và `template.apply` — test này phải seed-từ-đầu thật (không dựa vào DB đã seed sẵn) để bắt đúng hazard thứ tự đã tìm ra.

## Alternatives (không chọn cho slice này)

- **Cleaner later**: tách `buildTemplateApplicationPlan` + phần tạo `WorkInstance`/`Task` từ `Api\WorkTemplateController` ra `WorkTemplateApplyService` riêng, cả API và Web cùng gọi service này thay vì Web gọi thẳng controller khác. Kiến trúc sạch hơn nhưng đụng vào code đang chạy tốt (16/16 test xanh) — để dành khi có nhu cầu tái sử dụng thứ 3 (ví dụ mobile app, hoặc apply-to-component UI).
- **Không chọn**: Alpine gọi thẳng `/api/zena/...` bằng Sanctum token — kéo theo cơ chế auth khác vào trang Blade session-based, không khớp quyết định kiến trúc đã chốt (mọi trang web trong app này đều gọi Web route, không gọi thẳng API Sanctum).
