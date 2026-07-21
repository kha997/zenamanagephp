# Site Diary Autofill — Design Spec

**Date:** 2026-07-21
**Status:** Approved for planning

## Context

Nghiên cứu tối ưu tốc độ nhập liệu (2026-07-20) xếp Site Diary autofill là ưu tiên #2 trong 4 hạng mục còn lại, vì đây là form nhập liệu tần suất cao nhất trong hệ thống (hàng ngày, mỗi dự án) và hiện chưa có bất kỳ tối ưu nào — toàn bộ field là text/textarea tự do.

File liên quan:
- `app/Models/SiteDiary.php`
- `app/Http/Controllers/Web/SiteDiaryPageController.php`
- `resources/views/site-diaries/create.blade.php`

## Goal

Khi người dùng tạo nhật ký công trường mới, các field mang tính "lặp lại theo dự án" (thời tiết, nhiệt độ, số nhân lực, thiết bị sử dụng) tự động điền sẵn từ nhật ký gần nhất của cùng dự án đó, giảm thao tác gõ lại mỗi ngày. Các field mang tính "sự kiện của riêng ngày đó" (công việc thực hiện, vật tư nhập về, ghi chú an toàn, khách/đoàn kiểm tra, chậm trễ/sự cố) giữ nguyên nhập tự do — không autofill.

## Scope

**Autofill (4 field):** `weather`, `temperature`, `manpower_count`, `equipment_used`
**Không autofill (giữ tự do):** `work_performed`, `materials_delivered`, `safety_notes`, `visitors`, `delays_issues`, `diary_date`, `project_id`

Không có route/API/permission/migration mới. Chỉ sửa `SiteDiaryPageController@create()` và `resources/views/site-diaries/create.blade.php`.

## Architecture & Data Flow

1. `SiteDiaryPageController@create()`: sau khi lấy danh sách `$projects` như hiện tại, query thêm bản ghi `SiteDiary` gần nhất cho từng `project_id` của tenant:
   - `orderByDesc('diary_date')->orderByDesc('created_at')`, lấy record đầu tiên mỗi `project_id`.
   - Không lọc theo `status` (draft/submitted/approved đều tính) — thông tin thời tiết/thiết bị vẫn đúng dù nhật ký chưa được duyệt.
   - Chỉ select 4 cột cần autofill (`weather`, `temperature`, `manpower_count`, `equipment_used`) + `project_id`.
2. Query cụ thể: lấy toàn bộ `SiteDiary` của tenant (`forTenant($tenantId)`), select `project_id, weather, temperature, manpower_count, equipment_used, diary_date, created_at`, order `orderByDesc('diary_date')->orderByDesc('created_at')`, sau đó dùng Collection `->groupBy('project_id')->map->first()` để lấy bản ghi mới nhất mỗi dự án (đúng giả định volume nhỏ/tenant, tránh SQL `GROUP BY` mơ hồ hoặc window function không cần thiết ở quy mô này).
3. Build mảng liên kết `[project_id => ['weather' => ..., 'temperature' => ..., 'manpower_count' => ..., 'equipment_used' => ...]]`. Dự án chưa có nhật ký nào thì không có key trong mảng.
4. Truyền cho view dưới dạng biến `$autofillByProject` (mảng PHP thường, không JSON-encode ở controller).

## UI Behavior (Vanilla JS, không dùng Alpine)

`layouts/operator.blade.php` hiện KHÔNG load Alpine.js (không có script CDN, `package.json` không có `alpinejs`) — chỉ nạp vài file JS riêng qua `@vite` (theo pattern `money-format.js`, `ai-lead-suggest.js`...). Vì vậy view này dùng **vanilla JS scoped trong `<script>` cuối trang**, không thêm Alpine mới cho operator surface:

- Blade nhúng dữ liệu an toàn bằng `@js($autofillByProject)` (Laravel helper escape đúng cho HTML/JS context) thay vì gọi `json_encode()` thủ công: `const autofillByProject = @js($autofillByProject);`.
- Script gắn `change` listener lên `#project_id`, và `input` listener lên từng field trong nhóm autofill (`#weather`, `#temperature`, `#manpower_count`, `#equipment_used`) để set cờ "touched" tương ứng (object JS đơn giản, không cần framework).
- Khi `#project_id` đổi: tra `autofillByProject[projectId]`; với mỗi field trong nhóm, chỉ gán `.value` nếu cờ touched tương ứng là `false`. Field không có trong `autofillByProject[projectId]` (dự án mới, chưa có diary trước) thì bỏ qua, giữ nguyên giá trị hiện tại.
- Listener `change` chỉ bắn khi người dùng chủ động thao tác dropdown — không bắn khi trang load lần đầu, kể cả khi `old('project_id')` đã có sẵn giá trị (trường hợp `back()->withInput()` sau lỗi validate). Nhờ vậy giá trị `old()` của các field autofill không bị autofill JS ghi đè khi hiển thị lại form sau lỗi.
- **Lưu ý phát hiện thêm (không thuộc phạm vi spec này):** `resources/views/projects/_apply-work-template.blade.php` (PR#210) dùng `x-data`/`x-init` Alpine nhưng `layouts.operator` không load Alpine ở đâu — khả năng cao tính năng "Áp dụng mẫu công việc" đang không chạy JS trên trình duyệt thật. Ghi nhận làm nợ kỹ thuật cần điều tra riêng, không sửa trong scope này.

## Edge Cases

| Tình huống | Hành vi |
|---|---|
| Dự án chưa có nhật ký nào trước đó | 4 field autofill giữ nguyên rỗng/mặc định như hiện tại |
| Người dùng gõ tay vào field autofill rồi đổi dự án | Field đã gõ tay giữ nguyên giá trị (không bị ghi đè); field chưa gõ vẫn autofill |
| Validate lỗi, form hiển thị lại với `old()` | Autofill JS không chạy (không có sự kiện `change`), giá trị `old()` giữ nguyên |
| `manpower_count` mặc định hiện tại là `0` | Coi `0`/rỗng là "chưa touched" — vẫn cho autofill ghi giá trị thật vào |
| Nhật ký gần nhất có field autofill = null (vd. chưa từng ghi nhiệt độ) | Field đó giữ rỗng, không lỗi |

## Testing

- Bổ sung test vào `tests/Feature/Zena/OperatorSiteOpsUiTest.php` (không phải `SiteDiaryApiTest` — requirement chính là view `create` nhận đúng `autofillByProject`, không phải hành vi API): assert `GET operator.site-diaries.create` trả về view với `autofillByProject` đúng giá trị 4 field từ bản ghi `SiteDiary` mới nhất (theo `diary_date` rồi `created_at`) của từng dự án; dự án không có diary nào thì không có key trong mảng; đảm bảo tenant isolation (không lộ dữ liệu tenant khác — seed 1 diary ở tenant khác, assert không xuất hiện trong `autofillByProject`).
- Không có hạ tầng test JS trong repo — hành vi vanilla JS (touched-guard, autofill on change) sẽ verify bằng browser thủ công sau khi implement, không viết test tự động cho phần này.

## Out of Scope

- Các hạng mục #3 (Vendor select cho RFI/Submittal), #4 (tự sinh mã), #5 (dọn `/templates`) trong bảng ưu tiên data-entry — làm ở phiên riêng.
- Không đổi schema `site_diaries`, không đổi API `SiteDiaryController`.
