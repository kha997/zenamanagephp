# Thêm loại lịch hẹn "Tham quan" và "Họp" cho CRM Opportunity

Date: 2026-07-30

## Bối cảnh

Trang chi tiết cơ hội CRM (`resources/views/crm/opportunity-show.blade.php`) có form "Đặt lịch mới" cho phép tạo lịch hẹn (`OpportunityAppointment`) gắn với một opportunity. Hiện tại `Loại` chỉ có 2 giá trị: `consultation` (Tư vấn) và `survey` (Khảo sát), định nghĩa trong `OpportunityAppointment::VALID_TYPES`.

Thực tế khách hàng thường có thêm 2 kiểu lịch: tham quan (công trình đã làm hoặc trụ sở công ty) và họp. Cần bổ sung 2 loại lịch hẹn mới, gộp "tham quan công trình" và "tham quan trụ sở" thành một loại `site_visit` (Tham quan) — chi tiết địa điểm cụ thể dùng field `location` free-text đã có sẵn trên form.

## Mục tiêu

- Thêm 2 giá trị loại lịch hẹn mới: `site_visit` (Tham quan), `meeting` (Họp).
- Người dùng chọn được các loại này khi đặt lịch mới, và badge/nhãn hiển thị đúng trong bảng lịch hẹn hiện có của opportunity.
- Không thay đổi schema DB, không thay đổi logic validation (đã dynamic theo constant).

## Ngoài phạm vi

- Không thêm trường mới vào form (dùng field `location` có sẵn để ghi chi tiết địa điểm tham quan).
- Không đổi luồng hoàn thành/huỷ lịch hẹn (`outcome_notes`, status transitions) — không liên quan đến loại lịch.
- Không đổi báo cáo/dashboard khác — hiện tại type chỉ hiển thị trong view opportunity-show.

## Thiết kế

### 1. Model — `app/Models/OpportunityAppointment.php`

Thêm 2 constant mới cạnh `TYPE_CONSULTATION`, `TYPE_SURVEY` (dòng ~32-38):

```php
const TYPE_SITE_VISIT = 'site_visit';
const TYPE_MEETING = 'meeting';
```

Thêm 2 giá trị này vào mảng `VALID_TYPES`. Validation trong `CrmPageController::storeAppointment` (dòng 585) dùng `'in:' . implode(',', OpportunityAppointment::VALID_TYPES)` nên tự động chấp nhận giá trị mới, không cần sửa controller.

### 2. View — `resources/views/crm/opportunity-show.blade.php`

**Label map (dòng 14-17)** — thêm:
```php
'site_visit' => 'Tham quan',
'meeting' => 'Họp',
```
Map này dùng để hiển thị badge trong bảng lịch hẹn đã có (dòng 143), nên chỉ cần thêm entry, không đổi logic render.

**Dropdown "Loại" (dòng 211-217)** — thêm 2 `<option>`:
```html
<option value="site_visit">Tham quan</option>
<option value="meeting">Họp</option>
```

### Dữ liệu ví dụ sau khi làm

Dropdown "Loại" sẽ có: Chọn loại (placeholder) / Tư vấn / Khảo sát / Tham quan / Họp.

## Test

- `tests/Feature/OpportunityAppointmentLifecycleTest.php`: thêm case tạo lịch hẹn với `type = site_visit` và `type = meeting`, xác nhận tạo thành công (201/redirect) và record lưu đúng giá trị.
- `tests/Feature/Models/OpportunityAppointmentModelTest.php`: xác nhận `VALID_TYPES` chứa `site_visit` và `meeting`.
- Test cũ với `consultation`/`survey` phải vẫn pass (không phá vỡ tương thích ngược).

## Rủi ro / lưu ý

- Cột `type` là `string(20)` — `site_visit` (9 ký tự) và `meeting` (7 ký tự) đều nằm trong giới hạn, không cần đổi migration.
- Không có nơi nào khác trong codebase filter/hardcode theo giá trị `consultation`/`survey` list (đã xác nhận qua khảo sát code), nên thêm giá trị mới không phá vỡ chỗ khác.
