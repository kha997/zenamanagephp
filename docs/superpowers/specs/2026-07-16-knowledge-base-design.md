# Tri thức nội bộ / Knowledge Base (Goal #7) — Design Spec

Date: 2026-07-16
Status: chosen by orchestrator — goal #7 chưa có gì (~5% coverage, audit 12/07 xác nhận zero model liên quan), gap lớn còn lại duy nhất chưa động tới trong 10 mục tiêu.

## Purpose

Kho tri thức nội bộ dùng chung toàn công ty: SOP (quy trình chuẩn), Checklist (danh mục kiểm tra chuẩn hóa), Bài học công trình (lessons learned, có thể gắn 1 dự án cụ thể). Thay thế tài liệu rải rác trên Zalo/Drive — mọi nhân viên tra cứu được, quản lý biên soạn/xuất bản.

## Verified integration facts

- Pattern chuẩn ULID+TenantScope: `App\Traits\TenantScope` tự áp `where('tenant_id', ...)` qua global scope, model cần `use HasUlids, HasFactory, TenantScope;`.
- Permission: `database/seeders/ZenaPermissionsSeeder::CANONICAL_PERMISSIONS` — thêm entry là đủ để admin/System Admin tự động có quyền (qua `ZenaAdminRolePermissionSeeder` sync toàn bộ permission cho role admin-tier). Role khác (member/sales) KHÔNG tự có, phải cấp riêng qua RBAC UI sau — không nằm trong scope slice này.
- Route web operator theo `rbac:{module}.{action}` middleware, dùng `Route::prefix('operator')` nhóm sẵn (xem cách `webhooks`/`document-templates` mount trong `routes/web.php`).
- Layout `resources/views/layouts/operator.blade.php` — sidebar có section, mỗi mục 1 `<a>` không điều kiện `@can` (RBAC gate ở route, không ẩn nav — đúng "operator-first" design đã chốt).
- CSRF test: `$this->get('/login');` trong `setUp()`.

## Data

`knowledge_articles`: ULID, `tenant_id` (+TenantScope), `type` (`sop`|`checklist`|`lesson_learned`), `title` string(255), `category` string(100) nullable (nhóm ngành: pháp lý/phá dỡ/ép cọc/thô/hoàn thiện/nội thất/PCCC/BIM — free text, không enum cứng để không khóa cứng danh sách), `body` text (nội dung SOP/bài học, markdown-as-plaintext hiển thị `nl2br`), `checklist_items` json nullable (mảng `{text: string, done: bool}` — danh mục kiểm tra CHUẨN do người biên soạn định nghĩa, KHÔNG phải tiến độ theo từng lần dùng thực tế — slice sau nếu cần tracking theo dự án), `tags` json nullable (mảng string), `project_id` nullable FK `projects` (chỉ dùng cho `lesson_learned` gắn 1 công trình cụ thể), `status` (`draft`|`published`), `published_at` nullable, `created_by`/`updated_by` FK `users`, timestamps.

## Lifecycle

```
draft → published
published → draft (unpublish, để sửa lại)
```

Chỉ `draft` được sửa nội dung (title/body/checklist_items/tags/category) và xóa. `published` chỉ đọc — muốn sửa phải unpublish trước (tránh sửa "âm thầm" tài liệu đang được team dùng).

## Behavior

- `index`: danh sách filter theo `type` + `category` + tìm `title` LIKE; mặc định hiện `published` (draft hiện thêm nếu người xem có quyền `manage` VÀ query `?status=draft`); phân trang 20.
- `store`: tạo draft (không publish ngay).
- `update`: chỉ khi `draft`.
- `publish`: guard `status===draft` + `body` không rỗng (SOP/lesson) hoặc `checklist_items` không rỗng (checklist) → set `published_at`.
- `unpublish`: guard `status===published` → về draft, giữ `published_at` cũ (lịch sử) hay null lại — chọn: giữ nguyên `published_at` cũ, chỉ đổi `status` (để biết "từng publish lúc nào" ngay cả khi đang sửa lại).
- `destroy`: chỉ `draft`.

## UI

- Sidebar: section mới "Tri thức" (đặt sau "Chất lượng", trước "Tài liệu" — theo mạch tài liệu/kiến thức gần nhau).
- `knowledge.index`: bảng loại/tiêu đề/nhóm ngành/trạng thái/ngày cập nhật, filter dropdown loại+nhóm ngành, ô tìm kiếm tiêu đề.
- `knowledge.create`/`knowledge.edit`: form chọn `type` (select, khóa sau khi tạo — không đổi loại nội dung giữa chừng), title, category, body (textarea), checklist_items (chỉ hiện khi type=checklist — input động thêm/bớt dòng text), tags (input phân tách bằng dấu phẩy), project (select, chỉ hiện khi type=lesson_learned).
- `knowledge.show`: hiện đầy đủ, checklist render dạng danh sách có icon check tĩnh (không tương tác), nút Sửa/Xuất bản/Gỡ xuất bản/Xóa theo trạng thái + quyền.

## Error handling

Sửa/xóa khi không phải draft → back error; publish thiếu nội dung → back error theo loại; cross-tenant → 404; thiếu quyền `manage` khi thao tác ghi → 403 qua middleware `rbac:knowledge.manage`.

## Testing

CRUD đầy đủ (draft→published→unpublish→edit lại→published lại); publish guard theo loại (checklist rỗng chặn, SOP rỗng chặn); destroy chỉ draft; filter type/category/search hoạt động đúng; cross-tenant 404; RBAC (thiếu quyền view → 403, thiếu quyền manage → không thấy nút, POST trực tiếp → 403); TenantScope guard test (thêm vào guard test tập trung nếu có, hoặc test riêng theo pattern `TenantScopedCrmModelsTest`); baseline 0 path mới.

## Out of scope

Tìm kiếm full-text nâng cao (chỉ LIKE cơ bản), versioning/lịch sử chỉnh sửa nội dung, tracking tiến độ checklist theo từng lần dùng thực tế trên công trình, đính kèm file/hình ảnh, bình luận/thảo luận, AI gợi ý nội dung liên quan.
