# Quote trên Client Portal (Goal #2 Slice 2) — Design Spec

Date: 2026-07-15
Status: chosen by orchestrator (don't-ask mode) — khép kín vòng báo giá: sale gửi → khách tự chốt trên portal → contract
Depends on: native quotes slice 1 (PR #167, branch `feat/native-quotes`), client portal Phase 6 (magic-link, guard `client`, middleware `portal.auth`, throttle `portal-actions`).

## Purpose

Khách hàng xem báo giá và bấm **Chấp nhận / Từ chối** ngay trên portal qua magic link — không cần sale thao tác hộ. Accept từ portal có đúng semantics như operator accept (supersede hàng loạt, EventRecord, sẵn sàng createContract). Đồng thời sửa bug slice 1: EventRecord của quote được insert với key sai làm send/accept/reject trả 500.

## Bug slice 1 phải sửa trước (đã xác minh bằng probe test — 500 thật)

`CrmPageController::sendQuote/acceptQuote/rejectQuote` tạo EventRecord với `event_type`/`actor_id` — không nằm trong `$fillable` của `EventRecord` (đúng là `event_key`/`actor_user_id`), và thiếu `occurred_at` (NOT NULL). Fillable strip 2 key → insert thiếu `event_key` + `occurred_at` → SQL NOT NULL violation → response 500 (quote vẫn đổi status vì update chạy trước). Test slice 1 pass vì không assert response status/EventRecord. Fix + regression test là Task 1, commit lên `feat/native-quotes` (PR #167).

## Verified integration facts

- Portal routes mount tại `routes/web.php:1041` — `Route::prefix('portal/{tenantSlug}')->as('portal.')`, nhóm authenticated dùng middleware `portal.auth`, action POST thêm `throttle:portal-actions` (định nghĩa `RouteServiceProvider.php:99`).
- Actor phía khách: `Auth::guard('client')->user()` trả `Account` (xem `PortalDesignItemController`). Ownership pattern portal: mọi nhánh từ chối → **404 đồng nhất** qua `findOrFail`.
- Quote ownership KHÔNG đi qua `converted_project_id` (quote tồn tại trước khi convert): đi thẳng `opportunities.account_id`.
- `EventRecord` đúng schema: `event_key`, `actor_user_id` nullable, `payload` json, `occurred_at` NOT NULL (migration `2026_04_04_130000`). Actor phía portal là Account → `actor_user_id = null`, `payload.actor_account_id` (tiền lệ `DesignItemStatusService`).
- Notification cho người phụ trách: pattern `PortalDesignItemController::notifyAssignee` — `Notification::query()->create` trong try/catch nuốt lỗi, `type = 'portal_client_action'`.
- Bằng chữ: `\App\Support\VietnameseMoneyWords::toWords(float)`.
- PDF: `CrmPageController::quotePdf` (dòng ~685) render `crm.quote-pdf` + `DeliverablePdfExportService` — portal tái dùng nguyên blade.
- TenantScope trên Quote hoạt động trong portal context (tiền lệ DesignItem) — vẫn where `tenant_id` tường minh theo pattern portal.

## Design

### QuoteLifecycleService (chống trùng logic operator/portal)

`app/Services/QuoteLifecycleService.php`: chuyển accept/reject (transaction + supersede + EventRecord) từ `CrmPageController` vào service; cả operator lẫn portal gọi chung. Context: `actor_user_id` (operator) / `actor_account_id` + `source` (portal), `note` tùy chọn khi reject. Transition sai → `ValidationException`. `sendQuote` vẫn ở operator (khách không gửi).

### Portal routes (trong nhóm `portal.auth`)

- `GET  /quotes/{id}` → `portal.quotes.show` — chỉ quote **không phải draft** của opportunity thuộc account; khác → 404.
- `GET  /quotes/{id}/pdf` → `portal.quotes.pdf` — như show, tải PDF (blade `crm.quote-pdf` — không draft nên không cần watermark).
- `POST /quotes/{id}/accept` / `POST /quotes/{id}/reject` → throttle `portal-actions`; chỉ khi status `sent`; reject nhận `note` tùy chọn (max 1000) lưu vào payload EventRecord; sau hành động: Notification cho `quote.created_by`; accept supersede các quote khác của opportunity (qua service).

### UI

- `resources/views/portal/quote.blade.php` (khung như `portal/design-item.blade.php`): số + revision + status badge, hiệu lực `valid_until`, bảng dòng (name/unit/qty/unit_price/amount — KHÔNG hiện `price_note`, đó là ghi chú nội bộ đơn giá), subtotal + Bằng chữ, nút Tải PDF; khi `sent`: form Chấp nhận (confirm) + form Từ chối kèm ô lý do.
- Dashboard portal: section "Báo giá" — mọi quote không-draft của các opportunity thuộc account (kể cả chưa convert), cột số/rev/tổng/status/ngày gửi, link sang trang quote.

### Quyết định scope

- `price_note` là chứng cứ đơn giá nội bộ → không lộ ra portal (cả view lẫn PDF? — PDF hiện có cột Ghi chú: GIỮ cho operator, portal dùng chung blade nên chấp nhận lộ price_note trong PDF? KHÔNG — truyền flag `hidePriceNote` vào blade, portal set true, operator giữ nguyên hành vi cũ).
- Không gửi email (vẫn out of scope) — sale gửi link portal cho khách như flow design-item.
- Không cho khách xem draft, không cho khách revise.

## Error handling

Hành động khi không còn `sent` → back error "Báo giá không còn ở trạng thái chờ phản hồi." (pattern design-item); transition race → ValidationException từ service → back error; cross-account/cross-tenant/draft → 404 đồng nhất; PDF engine unavailable → back error như slice trước; Notification lỗi không được phá hành động của khách.

## Testing

Task 1: send/accept/reject operator giờ PHẢI assert redirect (không 5xx) + EventRecord tồn tại với đúng `event_key`. Portal: happy accept (status + decided_at + supersede các quote khác + EventRecord `quote.accepted` payload có `actor_account_id`, `source=portal` + Notification cho creator); reject kèm note trong payload; quote draft → 404; quote của account khác → 404; tenant slug khác → 404; chưa đăng nhập → redirect login; action khi đã accepted → back error; dashboard hiện quote sent kể cả opportunity chưa convert; PDF portal 200 + không chứa price_note; operator PDF vẫn chứa price_note (regression). Regression: QuoteLifecycleTest + QuoteToContractTest + CrmApiTest + Portal suites xanh nguyên bộ.

## Out of scope

Email/notify khách khi sale gửi quote, VAT/discount/điều khoản, thư viện đơn giá, template-context `quote`, chữ ký điện tử, đồng bộ external.
