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
2. Build mảng liên kết `[project_id => ['weather' => ..., 'temperature' => ..., 'manpower_count' => ..., 'equipment_used' => ...]]`. Dự án chưa có nhật ký nào thì không có key trong mảng.
3. Truyền cho view dưới dạng biến `$autofillByProject`; view `json_encode()` trực tiếp vào một `x-data` Alpine.js (không cần endpoint AJAX riêng — số dự án mỗi tenant trong ứng dụng này không lớn).

## UI Behavior (Alpine.js)

- Form thêm `x-data` chứa: `autofillByProject` (từ JSON truyền vào), và cờ "touched" cho từng field trong nhóm autofill (`weatherTouched`, `temperatureTouched`, `manpowerTouched`, `equipmentTouched`), mặc định `false`.
- Mỗi input trong 4 field autofill gắn `@input` set cờ touched tương ứng thành `true` (đánh dấu người dùng đã tự gõ).
- Dropdown `project_id` gắn `@change`: tra `autofillByProject[project_id]`; với mỗi field trong nhóm, chỉ gán giá trị nếu cờ touched tương ứng là `false`. Field không có trong `autofillByProject[project_id]` (dự án mới, chưa có diary trước) thì bỏ qua, giữ nguyên giá trị hiện tại.
- Sự kiện `@change` chỉ bắn khi người dùng chủ động thao tác dropdown — không bắn khi trang load lần đầu, kể cả khi `old('project_id')` đã có sẵn giá trị (trường hợp `back()->withInput()` sau lỗi validate). Nhờ vậy giá trị `old()` của các field autofill không bị autofill JS ghi đè khi hiển thị lại form sau lỗi.

## Edge Cases

| Tình huống | Hành vi |
|---|---|
| Dự án chưa có nhật ký nào trước đó | 4 field autofill giữ nguyên rỗng/mặc định như hiện tại |
| Người dùng gõ tay vào field autofill rồi đổi dự án | Field đã gõ tay giữ nguyên giá trị (không bị ghi đè); field chưa gõ vẫn autofill |
| Validate lỗi, form hiển thị lại với `old()` | Autofill JS không chạy (không có sự kiện `change`), giá trị `old()` giữ nguyên |
| `manpower_count` mặc định hiện tại là `0` | Coi `0`/rỗng là "chưa touched" — vẫn cho autofill ghi giá trị thật vào |
| Nhật ký gần nhất có field autofill = null (vd. chưa từng ghi nhiệt độ) | Field đó giữ rỗng, không lỗi |

## Testing

- Feature test mới (hoặc bổ sung vào `SiteDiaryApiTest`/test tương ứng của Web controller): assert view `site-diaries.create` nhận `autofillByProject` đúng giá trị 4 field từ bản ghi `SiteDiary` mới nhất (theo `diary_date` rồi `created_at`) của từng dự án; dự án không có diary nào thì không có key trong mảng; đảm bảo tenant isolation (không lộ dữ liệu tenant khác).
- Không có hạ tầng test JS trong repo — hành vi Alpine.js (touched-guard, autofill on change) sẽ verify bằng browser thủ công sau khi implement, không viết test tự động cho phần này.

## Out of Scope

- Các hạng mục #3 (Vendor select cho RFI/Submittal), #4 (tự sinh mã), #5 (dọn `/templates`) trong bảng ưu tiên data-entry — làm ở phiên riêng.
- Không đổi schema `site_diaries`, không đổi API `SiteDiaryController`.
