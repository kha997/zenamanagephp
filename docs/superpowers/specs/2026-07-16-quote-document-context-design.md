# Quote Document Context (Goal #4 Slice) — Design Spec

Date: 2026-07-16
Status: chosen by orchestrator — hoàn thiện thư viện biểu mẫu cho báo giá, tận dụng hạ tầng `DocumentContextRegistry` có sẵn (Contract/Certificate/Project)

## Purpose

Cho phép sale tạo biểu mẫu HTML tùy biến cho báo giá (số/rev/dòng/tổng/điều khoản) qua thư viện biểu mẫu đã có (`document-templates`), rồi xuất PDF từ quote đã chốt — thay vì chỉ có 1 khung PDF cố định (`crm/quote-pdf.blade.php`) như hiện tại.

## Verified integration facts

- `App\Services\DocumentContext\DocumentContextProvider` interface: `slug()`, `label()`, `keys(): list<array{key,type,label}>`, `build(Model $subject): array<string,mixed>`, `sample(): array<string,mixed>`.
- 3 provider hiện có (`ContractContextProvider`, `CertificateContextProvider`, `ProjectContextProvider`) đăng ký singleton trong `App\Providers\AppServiceProvider::register()` qua mảng truyền vào `DocumentContextRegistry`.
- `App\Http\Controllers\Web\DocumentTemplatePageController`: hằng số `VALID_CONTEXTS = ['contract', 'certificate', 'project']` (dòng ~19) và validation rule `'context' => ['required', 'in:contract,certificate,project']` trong `store()` (dòng ~59) — CẢ HAI phải thêm `quote`.
- Render thật (không phải preview) theo mẫu `ContractPageController::renderContractDocument`/`renderCertificateDocument` (dòng ~864-919): load template published theo `context`, lấy `latestPublishedVersion`, `$contextRegistry->get($slug)->build($subject)`, `$versionService->renderHtml($html, $context)`, `$pdfService->render($rendered)` → response PDF.
- `sample()` trả mảng CHỈ giá trị scalar (string/int/float/bool) — không có Model — test `DocumentContextProvidersTest::test_sample_returns_literal_array_without_db` xác nhận điều này cho mọi provider, provider mới cũng phải tuân theo.
- `VietnameseMoneyWords::toWords(float)` dùng cho *_words key (tiền lệ `total_value_words`, `net_payable_words`).
- Quote model có sẵn: `subtotal`, `discount_percent`, `discount_amount`, `vat_percent`, `vat_amount`, `total`, `payment_terms`, `valid_until`, `quote_number`, `revision_no`, `status`, quan hệ `lines()` (QuoteLineItem orderBy sort_order), `opportunity()` (BelongsTo, có `account`).

## Design

### QuoteContextProvider

`app/Services/DocumentContext/QuoteContextProvider.php` implements `DocumentContextProvider`:

- `slug()`: `'quote'`
- `label()`: `'Báo giá'`
- `keys()`: `quote_number`, `revision_no` (string), `status_label`, `account_name`, `opportunity_name`, `valid_until` (date), `subtotal`, `discount_percent`, `discount_amount`, `vat_percent`, `vat_amount`, `total`, `total_words`, `payment_terms`, `today`, `lines_table_html`.
- `build(Model $subject)`: `$subject` là `Quote`; `loadMissing('lines', 'opportunity.account')`; bảng dòng HTML render theo đúng style/markup của `ContractContextProvider::renderBoqTable` (cột STT/Mã/Tên/ĐVT/KL/Đơn giá/Thành tiền) — tái dùng cấu trúc, KHÔNG copy-paste style khác biệt; ẩn cột ghi chú giá (price_note nội bộ, đúng nguyên tắc đã áp cho portal/PDF quote).
- `status_label`: map `Quote::STATUS_*` sang tiếng Việt (Nháp/Đã gửi/Đã chấp nhận/Đã từ chối/Đã thay thế) — helper `statusLabel()` riêng, không phụ thuộc view helper khác.
- `sample()`: dữ liệu literal cứng (không query DB), đủ mọi key của `keys()`.

### Wiring

- `AppServiceProvider::register()`: thêm `$app->make(QuoteContextProvider::class)` vào mảng providers của `DocumentContextRegistry` singleton.
- `DocumentTemplatePageController`: `VALID_CONTEXTS` thêm `'quote'`; validation `store()` thêm `'quote'` vào rule `in:`.
- `CrmPageController::renderQuoteDocument(string $id, string $template, ...)`: mirror `ContractPageController::renderContractDocument` — scoped-fetch quote theo tenant, tìm `DeliverableTemplate` theo `context='quote'`, lấy bản đã publish, build context từ `QuoteContextProvider`, render PDF, filename `bao-gia-{template-slug}-{quote_number}.pdf`.
- Route: `GET /crm/quotes/{id}/render/{template}` → `rbac:crm.view`, tên `crm.quotes.render-document`.
- UI: `quote-show.blade.php` thêm khối "Xuất theo biểu mẫu" — liệt kê `DeliverableTemplate` đã publish với `context='quote'` (query trong controller `showQuote`, truyền `quoteTemplates` vào view), mỗi cái là link tới route render.

## Error handling

Chưa publish template nào cho context `quote` → khối UI ẩn (không hiện lỗi, tương tự cách contract page xử lý khi rỗng — kiểm tra pattern trước khi viết). Template không tồn tại/khác tenant/khác context → 404. PDF engine unavailable → back error như các chỗ khác.

## Testing

Unit `QuoteContextProviderTest` (mirror `DocumentContextProvidersTest` style): registry đăng ký đúng slug, `keys()`/`sample()` phủ đủ nhau, build từ quote thật (2 dòng, discount 10%, VAT 8%) → đúng từng field kể cả `total_words` và `lines_table_html` chứa tên dòng; `sample()` chỉ scalar. Feature: route render trả PDF (hoặc pattern skip-if-unavailable của các PDF test trước), cross-tenant/context sai → 404, chưa publish → UI không hiện link (render assertion), regression: `DocumentContextProvidersTest` cũ vẫn xanh (giờ registry có 4 provider, sửa assertion `assertCount(3, ...)` thành `assertCount(4, ...)`).

## Out of scope

Không đổi `crm/quote-pdf.blade.php` (khung PDF mặc định hiện có giữ nguyên, đây là kênh THỨ HAI tùy biến qua thư viện mẫu), không thêm placeholder cho portal quote, không đổi hành vi ẩn `price_note`.
