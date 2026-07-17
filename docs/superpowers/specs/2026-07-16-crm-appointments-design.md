# Lịch tư vấn / khảo sát (Goal #1 Slice) — Design Spec

Date: 2026-07-16
Status: chosen by orchestrator — audit 12/07 xác nhận: pipeline Opportunity đã có stage `brief_discovery`/`survey_or_inputs_received` (tư vấn/khảo sát) nhưng KHÔNG có cơ chế đặt lịch hẹn thực tế nào gắn với các stage này — sale chỉ đổi nhãn stage, không có ngày giờ/người phụ trách/kết quả buổi hẹn.

## Purpose

Cho phép sale đặt lịch hẹn tư vấn/khảo sát gắn với 1 Opportunity, theo dõi trạng thái (đã đặt/hoàn thành/hủy/dời lịch), ghi kết quả sau buổi hẹn — khép một phần khoảng trống "CRM sale" (mục tiêu #1), đóng góp cụ thể cho luồng Lead → Tư vấn → Khảo sát → Báo giá.

## Verified integration facts

- `App\Models\Opportunity` đã có `pipeline_stage` với các hằng số liên quan: `STAGE_BRIEF_DISCOVERY` ('brief_discovery'), `STAGE_SURVEY_OR_INPUTS_RECEIVED` ('survey_or_inputs_received') — hai stage này là "tư vấn" và "khảo sát" theo đúng luồng vision, nhưng model Opportunity không có field ngày giờ hẹn — chỉ có nhãn stage.
- Trang `crm/opportunity-show.blade.php` đã có nhiều `<x-ui.card title="...">` (dòng ~36-239): "Thông tin cơ hội", "Chuyển giai đoạn", "Báo giá — zena-boq-core", "Báo giá (native)", "Hợp đồng", "Lịch sử" — thêm card mới "Lịch hẹn" theo ĐÚNG pattern này (không viết trang riêng).
- `CrmPageController::showOpportunity()` (dòng ~204-234): truyền `$opportunity`, và các view-model phụ (`boqCard`, `contractCard`, `users`, `events`) — thêm `appointments` vào mảng trả về theo đúng pattern (query trong controller, KHÔNG N+1 trong view).
- Pattern ULID+TenantScope: xem `App\Models\Quote` làm mẫu chuẩn nhất (TRANSITIONS map, `Model::query()`, EventRecord đúng schema `event_key`/`actor_user_id`/`occurred_at` — bài học slice trước: KHÔNG BAO GIỜ dùng `event_type`/`actor_id`).
- Permission tái dùng `crm.view`/`crm.manage` — KHÔNG tạo permission mới (đã đủ, đúng nguyên tắc scoped-narrow của CRM module).
- Route group: `Route::prefix('operator')->name('operator.')->middleware(['auth', 'tenant.isolation'])` — route mới đặt cạnh các route `crm.opportunities.*` hiện có trong `routes/web.php` (tìm bằng grep `operator.crm.opportunities`).
- CSRF test: `$this->get('/login');` trong `setUp()`. Test mutation PHẢI assert response (redirect/session), không chỉ DB state — bài học bug 500 EventRecord slice trước.

## Data

`opportunity_appointments`: ULID, `tenant_id` (+TenantScope), `opportunity_id` (indexed, FK `opportunities`), `type` (`consultation`|`survey` — khớp 2 stage `brief_discovery`/`survey_or_inputs_received`), `scheduled_at` datetime, `location` string(255) nullable (địa chỉ khảo sát/hình thức tư vấn), `assigned_to` FK `users` nullable (người phụ trách buổi hẹn), `status` (`scheduled`|`completed`|`cancelled`|`rescheduled`), `outcome_notes` text nullable (ghi kết quả sau buổi hẹn), `created_by` FK `users`, timestamps.

## Lifecycle

```
scheduled → completed | cancelled | rescheduled
completed/cancelled/rescheduled → (không đi đâu — chốt, giữ làm lịch sử)
```

- Dời lịch (`rescheduled`): tạo bản ghi MỚI `scheduled` với `scheduled_at` mới, bản gốc chuyển `rescheduled` (giữ lịch sử, KHÔNG sửa đè `scheduled_at` cũ — mirror cách `Quote::reviseQuote` giữ bản gốc).
- `completed`: bắt buộc có `outcome_notes` (kết quả buổi hẹn — dùng để quyết định chuyển stage tiếp theo).
- EventRecord cho mọi transition (aggregate `opportunity_appointment`).

## Behavior

- `storeAppointment`: tạo `scheduled`, validate `scheduled_at` không ở quá khứ (so với `now()`), `type` bắt buộc.
- `completeAppointment`: guard `status===scheduled`, bắt buộc `outcome_notes` không rỗng.
- `cancelAppointment`: guard `status===scheduled`, `outcome_notes` tùy chọn (lý do hủy).
- `rescheduleAppointment`: guard `status===scheduled`, nhận `scheduled_at` mới, tạo bản ghi mới + đổi bản gốc thành `rescheduled` — cả hai trong 1 transaction.

## UI

- Card "Lịch hẹn" trên `opportunity-show.blade.php`: bảng lịch hẹn (loại/ngày giờ/người phụ trách/trạng thái), form "Đặt lịch mới" (type select, datetime-local, location, assigned_to select từ `$users` đã có sẵn trong view); mỗi dòng `scheduled` có 3 nút Hoàn thành (mở form nhỏ nhập outcome_notes)/Hủy/Dời lịch (mở form nhập ngày mới).
- Badge màu theo status (mirror cách quote-show.blade.php làm badge trạng thái).

## Error handling

Transition sai → back error qua canTransition map; hoàn thành thiếu outcome_notes → back error; cross-tenant → 404 đồng nhất; thiếu quyền `crm.manage` cho hành động ghi → 403 qua middleware.

## Testing

Đặt lịch thành công (cả 2 loại); hoàn thành yêu cầu outcome_notes (thiếu → error, đủ → thành công + EventRecord đúng schema); hủy; dời lịch tạo bản ghi mới + bản gốc chuyển rescheduled đúng; cross-tenant 404; thiếu quyền `crm.manage` → 403; card hiện đúng trên trang opportunity (render assertion thấy tiêu đề "Lịch hẹn" + dữ liệu); baseline 0 path mới; regression: `CrmApiTest` + `QuoteLifecycleTest` nguyên bộ vẫn xanh (không đụng gì liên quan).

## Out of scope

Tích hợp với `/operator/schedule` (lịch task chung — có thể nối sau), nhắc lịch qua email/SMS, đồng bộ Google Calendar, tự động chuyển `pipeline_stage` của Opportunity khi hoàn thành lịch hẹn (sale tự đổi stage thủ công như hiện tại, tránh side-effect ẩn).
