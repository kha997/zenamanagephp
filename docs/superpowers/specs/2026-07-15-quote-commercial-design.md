# Quote thương mại: VAT + chiết khấu + điều khoản (Goal #2 Slice 3) — Design Spec

Date: 2026-07-15
Status: chosen by orchestrator (don't-ask mode) — báo giá hiện chỉ có subtotal, chưa đủ chuẩn phát hành thương mại VN
Depends on: slice 1 (PR #167), slice 2 (PR #168, branch `feat/quote-client-portal`).

## Purpose

Báo giá có **chiết khấu %, VAT %, tổng sau thuế, hiệu lực, điều khoản thanh toán** — đủ để phát hành cho khách thật. Tổng sau thuế (`total`) trở thành con số pháp lý: hiện trên operator/portal/PDF (kèm Bằng chữ), và là `total_value` của Contract khi accept.

## Verified integration facts

- `quotes` hiện có `subtotal` (denormalized, recompute ở `saveQuoteLines` + `sendQuote`), `valid_until` + `notes` đã fillable/cast **nhưng chưa có UI nhập** (storeQuote không nhận, quote-show không có form).
- `reviseQuote` (CrmPageController ~604) copy `notes` sang bản mới nhưng KHÔNG copy `valid_until`.
- Mọi chỗ hiển thị tiền đang dùng `subtotal`: `crm/quote-show.blade.php` (dòng 14 Bằng chữ, 35 Tổng cộng, 92 footer bảng), `crm/quote-pdf.blade.php`, `portal/quote.blade.php`, dashboard portal, card opportunity; `Api\OpportunityController::createContract` native path: `total_value = subtotal`.
- `QuoteToContractTest` assert `total_value` = 27.500.000 (subtotal); nhiều test tạo Quote trực tiếp với `subtotal` set tay — các test này phải set thêm field mới khi đổi createContract.

## Data (migration mới — không index mới, không rủi ro 64 ký tự)

Thêm vào `quotes`: `discount_percent` decimal(5,2) default 0; `vat_percent` decimal(5,2) default 0 (không tự áp thuế — sale chọn 8/10 tường minh); `discount_amount` decimal(15,2) default 0; `vat_amount` decimal(15,2) default 0; `total` decimal(15,2) default 0; `payment_terms` text nullable. **Backfill trong migration:** `UPDATE quotes SET total = subtotal` (dữ liệu cũ chưa có discount/vat).

## Công thức (một nơi duy nhất — static thuần trên model)

```
discount_amount = round(subtotal × discount_percent / 100, 2)
taxable        = subtotal − discount_amount
vat_amount     = round(taxable × vat_percent / 100, 2)
total          = taxable + vat_amount
```

`Quote::computeTotals(float $subtotal, float $discountPercent, float $vatPercent): array{discount_amount: float, vat_amount: float, total: float}` — dùng ở mọi chỗ recompute, KHÔNG lặp công thức.

## Behavior

- Endpoint mới (draft only): `POST /crm/quotes/{id}/commercial` — nhận `discount_percent` (0–100), `vat_percent` (0–100), `valid_until` (nullable date), `payment_terms` (nullable, max 2000); lưu + recompute totals qua `computeTotals`.
- `saveQuoteLines` và `sendQuote`: sau khi tính `subtotal` phải recompute cả bộ totals (dùng percent đang lưu trên quote).
- `reviseQuote`: copy thêm `discount_percent`, `vat_percent`, `payment_terms`, `valid_until` và bộ amount (lines giống hệt nên amount giữ nguyên) — sửa luôn thiếu sót valid_until của slice 1.
- `createContract` native path: `total_value = (float) ($nativeQuote->total ?: $nativeQuote->subtotal)` (fallback cho row cũ chưa qua backfill/test tạo tay).
- Lifecycle/accept/reject/supersede/portal action: KHÔNG đổi semantics.

## UI

- `crm/quote-show.blade.php`: form "Thông tin thương mại" (chỉ draft, cùng khối với form dòng): 4 field trên; khối tổng thay 1 dòng Tổng cộng bằng: Tạm tính / Chiết khấu (x%) −amount / VAT (x%) +amount / **Tổng cộng** total; Bằng chữ đổi sang `total`; hiện "Điều khoản thanh toán" khi có.
- `crm/quote-pdf.blade.php`: footer bảng thêm 3 dòng Tạm tính/Chiết khấu/VAT trước Tổng cộng (ẩn dòng chiết khấu/VAT khi = 0 để quote đơn giản không rườm), Tổng cộng + Bằng chữ dùng `total`; thêm khối "Điều khoản thanh toán" trước khối ký khi có.
- `portal/quote.blade.php`: như PDF (breakdown + total + Bằng chữ total + điều khoản).
- Dashboard portal + card "Báo giá (native)" trên opportunity show: cột tổng đổi sang `total` (fallback `?: subtotal` không cần — view đọc row đã backfill; test seed phải set total).

## Error handling

Validate percent 0–100 (numeric), commercial form khi không draft → back error như saveQuoteLines; các nhánh khác giữ nguyên.

## Testing

Truth table `computeTotals` (0/0, chỉ discount, chỉ VAT, cả hai, rounding lẻ .005); commercial endpoint: lưu + recompute đúng từng đồng (subtotal 27.500.000, discount 10% → 2.750.000, VAT 8% trên 24.750.000 → 1.980.000, total 26.730.000), draft-only, validation biên (percent 101 → lỗi); saveQuoteLines/sendQuote giữ totals nhất quán sau khi đổi dòng; revise copy đủ 4 field + amounts; createContract native: `total_value` = total (update QuoteToContractTest — test seed tay phải set `total`); PDF/portal/operator render: thấy breakdown khi có discount/VAT, KHÔNG thấy dòng chiết khấu khi 0, Bằng chữ theo total; regression: QuoteLifecycleTest + PortalQuoteTest + QuotePdfTest + CrmApiTest nguyên bộ.

## Out of scope

VAT per-line/nhiều thuế suất trong 1 quote, discount per-line, số tiền chiết khấu tuyệt đối (chỉ %), thư viện đơn giá, template-context `quote`, email.
