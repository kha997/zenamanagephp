# Dead Milestone/ProjectTemplate API Removal — Design Spec

**Date:** 2026-07-22
**Status:** Approved for planning

## Context

Yêu cầu ban đầu: sửa mã quyền `project.write` (không tồn tại trong seeder) ở `ProjectMilestoneController` + `ProjectTemplateController` — cùng bug đã vá ở `ProjectController` (PR#213). Điều tra sâu hơn trước khi sửa lộ ra cả hai controller **hỏng ở tầng sâu hơn permission**, độc lập với vụ mã quyền, và **zero consumer thật** (không blade/JS nào gọi, không test nào exercise, chỉ đăng ký route).

Bằng chứng đã xác minh trực tiếp trên code (không suy đoán):

- **`ProjectMilestoneController`**: route `apiResource('milestones', ...)` đăng ký trong `routes/api.php` (dưới `Route::prefix('projects')`) nhưng KHÔNG có segment `{project}` trong URL (`api/projects/milestones`, không phải `api/projects/{project}/milestones`), trong khi mọi method (`store`, `show`, `update`, `destroy`, `complete`, `cancel`) đều bắt buộc tham số `string $projectId`. Gọi endpoint này sẽ vỡ ở tầng framework trước khi chạm tới check quyền.
- **`ProjectTemplateController` (namespace `Api`)**: gọi `App\Models\ProjectTemplate::forTenant(...)` ở 8/9 method, nhưng model KHÔNG `use TenantScope` và không tự định nghĩa `forTenant()` → `BadMethodCallException` ngay lập tức. `store()` còn gọi `ProjectTemplate::create(['tenant_id'=>..., 'template_data'=>..., 'milestones'=>..., 'is_public'=>...])` nhưng các field này **không có trong `$fillable`** của model — bị Eloquent âm thầm bỏ qua, và cột `tenant_id` NOT NULL trên schema thật sẽ ném lỗi DB.
- Nguyên nhân gốc: 2 migration khác nhau cùng tạo bảng `project_templates` — bản `2024_02_15` chạy trước (tạo schema có `phases`/`default_settings`, KHÔNG có `tenant_id`), bản `2025_09_16` có guard `if (!Schema::hasTable(...))` nên `up()` **không bao giờ thực thi** trong bất kỳ môi trường nào. `Api\ProjectTemplateController` được viết cho schema của bản 2025-09 — schema đó **chưa từng tồn tại thật trên DB**. Controller này chưa từng hoạt động đúng từ ngày được thêm vào.
- Model `ProjectMilestone` vẫn **sống thật**: được đọc qua Eloquent trực tiếp (không qua `ProjectMilestoneController`) ở `app/Models/Project.php` (relation), `ProjectAnalyticsController`, `PmDashboardController`, event `ProjectMilestoneCompleted`, `RealTimeNotificationService`.
- Model `App\Models\ProjectTemplate` vẫn sống qua `App\Http\Controllers\ProjectTemplateController` (controller khác, namespace gốc, khớp đúng schema thật) và `TemplateTask` — controller này có bug riêng (route bị `Src\WorkTemplate\Controllers\TemplateController` che khuất, thiếu `use Illuminate\Http\Request;`) nhưng **cố tình để lại cho một phiên audit riêng**, không thuộc phạm vi slice này.

## Decision (đã chốt với user qua 2 câu hỏi)

1. **Milestone**: xoá controller + toàn bộ route ghi/sửa/xoá milestone. Giữ nguyên model `ProjectMilestone` — không đụng bất kỳ chỗ nào đọc nó qua Eloquent.
2. **Template**: chỉ xoá phần **chắc chắn chết** — `Api\ProjectTemplateController` + route `project-templates` + migration `2025_09_16_081512_create_project_templates_table.php` (no-op vĩnh viễn, an toàn xoá vì không có tác dụng thực thi nào để mất). **Không đụng** `App\Http\Controllers\ProjectTemplateController`, `Src\WorkTemplate\Controllers\TemplateController`, `App\Http\Controllers\TemplateController`, `App\Http\Controllers\Api\App\TemplateController`, migration `2024_02_15` — để audit riêng.

## Scope

**Xoá:**
- `app/Http/Controllers/Api/ProjectMilestoneController.php` (toàn bộ file)
- `app/Http/Controllers/Api/ProjectTemplateController.php` (toàn bộ file)
- `database/migrations/2025_09_16_081512_create_project_templates_table.php` (toàn bộ file)
- Trong `routes/api.php`: khối `Route::apiResource('milestones', ...)` + `Route::prefix('milestones')->group(...)` (dòng ~274-280), và khối `Route::apiResource('project-templates', ...)` + `Route::prefix('project-templates')->group(...)` (dòng ~283-289)

**Giữ nguyên, không đụng:**
- `app/Models/ProjectMilestone.php`, `app/Models/ProjectTemplate.php`, `app/Models/TemplateTask.php`
- `app/Http/Controllers/ProjectTemplateController.php` (namespace gốc) + route `/api/templates` của nó
- `Src\WorkTemplate\Controllers\TemplateController` + route `/api/templates/{id}`
- `app/Http/Controllers/TemplateController.php`, `app/Http/Controllers/Api/App/TemplateController.php`
- Migration `2024_02_15_000001_create_project_templates_table.php`
- Mọi nơi đọc `ProjectMilestone`/`ProjectTemplate` qua Eloquent trực tiếp (Project relation, ProjectAnalyticsController, PmDashboardController, event, notification service)

## Testing

Guard test mới xác nhận:
1. Route đã gỡ: `milestones.index/store/show/update/destroy`, `milestones.reorder` (tên route thật, verify qua `route:list` trước khi viết test), `project-templates.index/store/show/update/destroy`, `project-templates.create-project/duplicate/categories` — không còn tồn tại.
2. Model vẫn instantiate/query được bình thường (`ProjectMilestone::query()`, `ProjectTemplate::query()`) — chứng minh việc xoá controller không đụng model.
3. Các consumer thật của `ProjectMilestone` vẫn hoạt động — chạy lại test suite hiện có của `ProjectAnalyticsController`/`PmDashboardController` nếu có, hoặc smoke test đọc relation `Project->milestones` (tuỳ theo test có sẵn tìm được lúc viết plan).
4. `App\Http\Controllers\ProjectTemplateController` (controller giữ lại) vẫn đăng ký route bình thường — không bị ảnh hưởng bởi việc xoá route `project-templates` (path khác hẳn `/api/templates` vs `/api/project-templates`).

## Out of Scope

- Sửa `App\Http\Controllers\ProjectTemplateController` (route bị che, thiếu import) — audit riêng.
- Điều tra `Src\WorkTemplate\Controllers\TemplateController` có phải code chết không — audit riêng.
- Điều tra `App\Http\Controllers\TemplateController` + `Api\App\TemplateController` — chưa xác minh mount, audit riêng.
- Xoá migration `2024_02_15` (đang là schema thật, đang dùng).
