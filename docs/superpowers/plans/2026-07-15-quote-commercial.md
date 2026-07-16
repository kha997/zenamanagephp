# Quote Commercial Fields Implementation Plan (Goal #2 Slice 3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Báo giá đủ chuẩn thương mại: chiết khấu % + VAT % + tổng sau thuế + hiệu lực + điều khoản thanh toán, hiển thị đủ trên operator/portal/PDF, và `total` thành `total_value` của Contract khi accept.

**Architecture:** Branch mới `feat/quote-commercial` từ `feat/quote-client-portal`; 1 migration cộng cột (có backfill `total = subtotal`); công thức MỘT nơi `Quote::computeTotals`; 1 endpoint mới `POST /crm/quotes/{id}/commercial`; các view/PDF đổi từ `subtotal` sang breakdown + `total`. Spec: `docs/superpowers/specs/2026-07-15-quote-commercial-design.md`.

## Global Constraints (giữ nguyên toàn bộ nếp slice 1+2)

- `Model::query()`; pattern auth như file đang sửa; **CẤM sửa tests/TestCase.php hay hạ tầng test dùng chung** — CSRF test = `$this->get('/login');` trong setUp(); file mới không có entry baseline (diff kiểm trước báo cáo); magic property = `@property` docblock.
- EventRecord LUÔN `event_key`/`actor_user_id`/`payload`/`occurred_at` — KHÔNG BAO GIỜ `event_type`/`actor_id` (bug slice 1 đã sửa, đừng tái phạm).
- Test mutation PHẢI assert response (redirect/success), không chỉ DB state (bài học bug 500 slice 1).
- Migration mới: kiểm độ dài mọi tên index/FK tự sinh ≤ 64 ký tự (slice này chỉ cộng cột, không index mới — nếu phát sinh, đặt tên tường minh).
- Tiền tệ decimal(15,2), percent decimal(5,2); công thức KHÔNG lặp — mọi recompute qua `Quote::computeTotals`.
- Claim "pre-existing failure" phải kèm bằng chứng chạy trên base commit.
- Checklist sau MỖI task: Architecture 29 / `--testsuite=Feature` toàn bộ xanh / phpstan exit 0. Push cuối: guardrails CI success.
- PR mới (Task 6): **base phải là `feat/quote-client-portal`** (stacked trên PR #168). Sau khi tạo dán `gh pr view <n> --json baseRefName,commits` vào báo cáo. KHÔNG merge.
- **KHÔNG đụng** các file WorkTemplate* untracked đang nằm trong working tree (của phiên khác) — không add, không xóa.

---

### Task 1: Migration + model + computeTotals

**Files:** Create migration `add_commercial_fields_to_quotes` (6 cột theo spec Data + backfill `DB::table('quotes')->update(['total' => DB::raw('subtotal')]);` trong `up()` sau khi thêm cột); Modify `app/Models/Quote.php` (fillable + casts float cho 5 cột số, string cho payment_terms + `@property` docblock đủ 6); Test: Create `tests/Feature/Models/QuoteTotalsTest.php`.

**Interfaces (T2-T5 dùng đúng):**

```php
/** @return array{discount_amount: float, vat_amount: float, total: float} */
public static function computeTotals(float $subtotal, float $discountPercent, float $vatPercent): array
{
    $discountAmount = round($subtotal * $discountPercent / 100, 2);
    $taxable = $subtotal - $discountAmount;
    $vatAmount = round($taxable * $vatPercent / 100, 2);

    return [
        'discount_amount' => $discountAmount,
        'vat_amount' => $vatAmount,
        'total' => round($taxable + $vatAmount, 2),
    ];
}
```

Truth table test: (1.000.000, 0, 0) → 0/0/1.000.000; (27.500.000, 10, 0) → 2.750.000/0/24.750.000; (27.500.000, 0, 8) → 0/2.200.000/29.700.000; (27.500.000, 10, 8) → 2.750.000/1.980.000/26.730.000; rounding (100, 33.33, 10) → 33.33/6.67/73.34. Thêm case backfill: quote tạo trước migration có `total` = `subtotal` (tạo quote set subtotal, assert total sau migrate — RefreshDatabase đã migrate nên assert default-row: tạo quote chỉ set subtotal qua create → total default 0 là ĐÚNG hành vi mới; case backfill kiểm bằng cách đọc migration code — ghi chú trong test docblock, không cần test runtime).

- [ ] Steps: failing test (computeTotals chưa tồn tại) → migration + model → PASS → checklist → commit `feat(quotes): commercial fields with single-source totals computation`.

---

### Task 2: Commercial endpoint + recompute wiring

**Files:** Modify `routes/web.php` (cạnh các route quotes hiện có ~1030): `Route::post('/crm/quotes/{id}/commercial', [..., 'saveQuoteCommercial'])->middleware('rbac:crm.manage')->name('crm.quotes.commercial');`; Modify `app/Http/Controllers/Web/CrmPageController.php`: method mới + sửa `saveQuoteLines`, `sendQuote`, `reviseQuote`; Test: thêm vào `tests/Feature/QuoteLifecycleTest.php`.

- `saveQuoteCommercial` (mirror scoped-fetch + draft-guard của `saveQuoteLines`): validate `discount_percent` (`required`, numeric, min:0, max:100), `vat_percent` (như trên), `valid_until` (`nullable`, date), `payment_terms` (`nullable`, string, max:2000); update 4 field + recompute: `$quote->update([...4 field..., ...Quote::computeTotals((float) $quote->subtotal, (float) $validated['discount_percent'], (float) $validated['vat_percent'])]);` → back success 'Đã lưu thông tin thương mại.'.
- `saveQuoteLines`: sau `$quote->update(['subtotal' => $subtotal])` đổi thành update subtotal + `Quote::computeTotals($subtotal, (float) $quote->discount_percent, (float) $quote->vat_percent)`.
- `sendQuote`: tương tự chỗ recompute subtotal.
- `reviseQuote`: create bản mới copy thêm `discount_percent`, `vat_percent`, `payment_terms`, `valid_until`, `discount_amount`, `vat_amount`, `total` (lines giống hệt).

Tests (nhớ assert redirect + session, không chỉ DB): commercial happy đúng từng đồng (27.500.000 / 10% / 8% → total 26.730.000); percent 101 → sessionHasErrors; khi sent → back error; saveQuoteLines sau khi đã set percent → totals nhất quán với lines mới; revise copy đủ 7 field mới.

- [ ] Steps: failing tests → route + methods → PASS → checklist → commit `feat(quotes): commercial terms endpoint with totals recompute`.

---

### Task 3: Operator UI + PDF breakdown

**Files:** Modify `resources/views/crm/quote-show.blade.php` (form "Thông tin thương mại" chỉ khi draft — 4 field, POST `operator.crm.quotes.commercial`; khối tổng: Tạm tính / `@if($quote->discount_amount > 0)` Chiết khấu ({{percent}}%) −amount `@endif` / `@if($quote->vat_amount > 0)` VAT +amount `@endif` / Tổng cộng `total`; Bằng chữ dòng 14 đổi sang `(float) $quote->total`; khối "Điều khoản thanh toán" khi có); Modify `resources/views/crm/quote-pdf.blade.php` (footer bảng + Bằng chữ như trên, thêm khối điều khoản trước khối ký); Test: thêm render assertions vào QuoteLifecycleTest + QuotePdfTest.

Tests: quote draft thấy form + label "Điều khoản thanh toán"; quote có discount 10% + VAT 8% render thấy "Chiết khấu", "VAT", total "26.730.000"; quote 0/0 KHÔNG thấy chữ "Chiết khấu"; PDF render tương tự + Bằng chữ theo total (dùng ví dụ số có sẵn của QuotePdfTest, tính tay giá trị kỳ vọng).

- [ ] Steps: failing render tests → views → PASS → checklist → commit `feat(quotes): commercial breakdown on quote UI and PDF`.

---

### Task 4: Portal view + dashboard + card opportunity

**Files:** Modify `resources/views/portal/quote.blade.php` (breakdown + total + Bằng chữ total + khối điều khoản — mirror T3); Modify `resources/views/portal/dashboard.blade.php` + `resources/views/crm/opportunity-show.blade.php` (cột tổng: `$q->total` thay `$q->subtotal`); Modify `app/Http/Controllers/Web/Portal/PortalQuoteController.php` (`amountInWords` cả show lẫn pdf đổi sang `(float) $quote->total`); Test: thêm case vào `tests/Feature/Portal/PortalQuoteTest.php` + `tests/Feature/Portal/PortalDashboardTest.php` (seed quote phải set `total` — hoặc tốt hơn: seed qua computeTotals cho khỏi lệch).

Tests: portal show thấy breakdown + total đúng; dashboard hiện total; portal PDF render Bằng chữ theo total và vẫn KHÔNG chứa price_note (regression flag `hidePriceNote`).

- [ ] Steps: failing tests → views + controller → PASS → checklist → commit `feat(portal): commercial totals on client quote views`.

---

### Task 5: createContract dùng total (điểm nối code cũ — cẩn trọng)

**Files:** Modify `app/Http/Controllers/Api/OpportunityController.php` — trong `DB::transaction` native path, MỘT dòng đổi: `'total_value' => $hasNativeAccepted ? (float) ($nativeQuote->total ?: $nativeQuote->subtotal) : (float) ($snapshot['total'] ?? 0),` — KHÔNG đụng gì khác, nhánh external nguyên vẹn; Modify `tests/Feature/QuoteToContractTest.php`: các test seed quote tay phải set thêm `total` (dùng `Quote::computeTotals`), assertion `total_value` cập nhật; thêm case mới: quote có discount 10% + VAT 8% → contract `total_value` = 26.730.000 (không phải subtotal).

- [ ] Steps: failing test mới → sửa 1 dòng → PASS + regression nguyên bộ QuoteToContractTest + CrmApiTest → checklist → commit `feat(quotes): contract total_value from quote grand total`.

---

### Task 6: Final verification + PR

- [ ] 3 con số (Architecture 29 / Feature suite full / phpstan 0) + baseline diff 0 path mới + regression QuoteLifecycleTest + PortalQuoteTest + QuotePdfTest + QuoteToContractTest + CrmApiTest + Portal suites + guardrails CI success.
- [ ] `gh pr create` head `feat/quote-commercial` **base `feat/quote-client-portal`**; dán `gh pr view <n> --json baseRefName,commits` vào báo cáo. KHÔNG merge.

## Self-review notes

- Spec coverage: Data+công thức→T1, endpoint+wiring→T2, operator UI+PDF→T3, portal→T4, contract→T5.
- `computeTotals` khai báo T1, gọi ở T2 (3 chỗ), T4 (seed test), T5 (seed test) — một chữ ký duy nhất.
- Số kiểm chứng dùng xuyên suốt: 27.500.000 / 10% / 8% → 26.730.000 (T2, T3, T5).
- Bài học slice trước nhúng thành constraint: EventRecord schema, assert response, WorkTemplate* không đụng, base PR stacked.
