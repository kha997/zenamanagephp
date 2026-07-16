# Native Quotes Implementation Plan (Goal #2 Slice 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Báo giá native: dòng + đơn giá + revision + lifecycle draft→sent→accepted/rejected/superseded, PDF báo giá, và accept → tự sinh Contract + bảng khối lượng HĐ từ đúng dòng đã chốt.

**Architecture:** 2 model mới (`Quote`, `QuoteLineItem`) theo mọi pattern đã thành nếp (ULID+TenantScope+TRANSITIONS map kiểu DesignItem); UI trong CRM (quyền `crm.*` sẵn có); điểm nối duy nhất vào code cũ = mở rộng guard + copy-BOQ trong `Api\OpportunityController::createContract`. Spec: `docs/superpowers/specs/2026-07-15-native-quotes-design.md`.

## Global Constraints (toàn bộ nếp cũ + nhấn mạnh)

- `Model::query()`; CẤM helper `auth()` (Auth facade / `$request->user()`); **CẤM sửa tests/TestCase.php hay hạ tầng test dùng chung** — CSRF trong test = `$this->get('/login');` trong setUp(); **file mới không có entry baseline** (diff kiểm trước báo cáo), thêm `HasFactory` vào model = kèm `/** @use HasFactory<...> */`; lỗi magic property = `@property` docblock.
- **Tên index/FK trong migration ≤ 64 ký tự** — `quote_line_items` + các cột dài dễ vượt: đặt tên tường minh (vd `qli_quote_sort_index`). Bài học MySQL 1059 — SQLite không bắt được.
- Claim "pre-existing failure" phải kèm bằng chứng chạy trên base commit.
- Checklist sau MỖI task: Architecture 29 / `--testsuite=Feature` ≥941 / phpstan exit 0. Push cuối: guardrails CI success. PR mới: sau khi tạo phải dán output `gh pr view <n> --json baseRefName,commits` vào báo cáo — **base phải là `feat/s4.3-material-submittal-package`** (bài học PR #166 mở nhầm base main).
- Test setup: pattern CRM sẵn có (`tests/Feature/Api/CrmApiTest.php` — tenant/account/opportunity qua `TenantUserFactoryTrait`, quyền `crm.view/crm.manage`).

---

### Task 1: Models + migrations + guard

**Files:** migration `create_quotes_tables` (2 bảng, 1 migration, theo spec Data — unique (`tenant_id`,`quote_number`) tên `quotes_tenant_number_unique`, unique (`opportunity_id`,`revision_no`) tên `quotes_opportunity_revision_unique`); `app/Models/Quote.php`, `app/Models/QuoteLineItem.php`; guard list `TenantScopedCrmModelsTest`; test `tests/Feature/Models/QuoteModelTest.php`.

**Interfaces (T2-T5 dùng đúng):**

```php
class Quote extends Model
{
    // STATUS_DRAFT/SENT/ACCEPTED/REJECTED/SUPERSEDED + VALID_STATUSES
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SENT, self::STATUS_SUPERSEDED],
        self::STATUS_SENT => [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_SUPERSEDED],
        self::STATUS_REJECTED => [self::STATUS_SUPERSEDED],
        self::STATUS_ACCEPTED => [],
        self::STATUS_SUPERSEDED => [],
    ];
    public static function canTransition(string $from, string $to): bool;
    public function opportunity(): BelongsTo;
    public function lines(): HasMany;          // QuoteLineItem, orderBy sort_order
    public static function nextNumber(string $tenantId): string;   // BG-{YYYY}-{%04d theo tenant+năm}
    public static function nextRevision(string $opportunityId): int;
}
```

`QuoteLineItem`: fillable theo spec, casts float/int, `quote(): BelongsTo`. Cả hai: ULID + TenantScope + `/** @use HasFactory<...> */` nếu thêm factory.

- [ ] Steps: failing test (trait guard 2 model; tạo quote+2 dòng, `lines` đúng thứ tự sort_order; `nextNumber` tuần tự và không nhảy theo tenant khác; `nextRevision`; bảng chân lý `canTransition` đủ các cặp) → migration (kiểm độ dài MỌI tên index tự sinh, >64 thì đặt tên) → models → PASS → checklist → commit `feat(quotes): Quote and QuoteLineItem models with lifecycle map`.

---

### Task 2: Lifecycle endpoints + revision copy

**Files:** routes nhóm operator cạnh CRM (`crm.view` cho GET, `crm.manage` cho POST):

```php
    Route::get('/crm/quotes/{id}', [..., 'showQuote'])->middleware('rbac:crm.view')->name('crm.quotes.show');
    Route::post('/crm/opportunities/{id}/quotes', [..., 'storeQuote'])->middleware('rbac:crm.manage')->name('crm.opportunities.quotes.store');
    Route::post('/crm/quotes/{id}/lines', [..., 'saveQuoteLines'])->middleware('rbac:crm.manage')->name('crm.quotes.lines.save');
    Route::post('/crm/quotes/{id}/send', [..., 'sendQuote'])->middleware('rbac:crm.manage')->name('crm.quotes.send');
    Route::post('/crm/quotes/{id}/accept', [..., 'acceptQuote'])->middleware('rbac:crm.manage')->name('crm.quotes.accept');
    Route::post('/crm/quotes/{id}/reject', [..., 'rejectQuote'])->middleware('rbac:crm.manage')->name('crm.quotes.reject');
    Route::post('/crm/quotes/{id}/revise', [..., 'reviseQuote'])->middleware('rbac:crm.manage')->name('crm.quotes.revise');
```

Controller: thêm vào `CrmPageController` (pattern scoped-fetch của chính nó; quote fetch qua opportunity tenant-scoped → 404 đồng nhất).

- `storeQuote`: tạo draft (number/revision từ helper T1); redirect quote show.
- `saveQuoteLines`: chỉ draft; input mảng `lines[]` (name/unit/quantity/unit_price/price_note/code, xóa = không gửi lại — thay toàn bộ: xóa dòng cũ, insert lại theo thứ tự, trong transaction); recompute `subtotal`; validate qty>0, price≥0, name required.
- `sendQuote`: guard `canTransition` + ≥1 dòng; set sent_at; EventRecord `quote.sent`.
- `acceptQuote`: transaction — transition accepted + decided_at; mọi quote khác của opportunity ở draft/sent/rejected → superseded; EventRecord `quote.accepted`.
- `rejectQuote`: transition + decided_at + EventRecord.
- `reviseQuote`: quote mới draft revision_no = nextRevision, copy lines + notes; redirect quote mới.

Test `tests/Feature/Zena/QuoteLifecycleTest.php`: happy path đủ vòng; accept supersede hàng loạt; sửa dòng khi sent → error; send khi 0 dòng → error; revise copy đúng dòng + price_note; subtotal đúng từng đồng (2 dòng: 100×200.000 + 5×1.500.000 = 27.500.000); cross-tenant 404; team_member thiếu quyền → chặn. (Nhớ `$this->get('/login');`.)

- [ ] Steps: failing test → routes → methods → PASS → checklist → commit `feat(quotes): lifecycle endpoints with revision copy and bulk supersede`.

---

### Task 3: UI

**Files:** card "Báo giá (native)" trong view opportunity show (tìm view mà `CrmPageController::showOpportunity` render — đọc method để lấy tên chính xác); view mới `resources/views/crm/quote-show.blade.php` (khung operator như certificate-show: bảng dòng, form thêm/sửa dòng khi draft — một form save-all giống saveCertificateLines UI, các nút hành động theo status với confirm 2 lần cho accept, subtotal + Bằng chữ qua `VietnameseMoneyWords`); render assertions bổ sung vào QuoteLifecycleTest (thấy nút Gửi khi draft, nút Chấp nhận khi sent, badge Superseded, "Bằng chữ").

- [ ] Steps: failing render assertions → views + controller show data → PASS → checklist → commit `feat(quotes): opportunity quote card and quote detail UI`.

---

### Task 4: PDF báo giá

**Files:** route `GET /crm/quotes/{id}/pdf` (`rbac:crm.view`, name `crm.quotes.pdf`); method `quotePdf` (mirror `certificatePdf`: view render → `DeliverablePdfExportService` → download `bao-gia-{quote_number}.pdf`, bắt Unavailable); blade `resources/views/crm/quote-pdf.blade.php` (khung DejaVu Sans: BẢNG BÁO GIÁ + số/rev, account + opportunity, bảng dòng có price_note cột Ghi chú, tổng + Bằng chữ, hiệu lực valid_until, khối ký); nút trên quote-show (mọi status trừ draft? — CHO CẢ draft để sale xem nháp, ghi watermark chữ "BẢN NHÁP" khi status=draft); test view-render + endpoint theo pattern PDF các slice trước.

- [ ] Steps: failing tests → implement → PASS → checklist → commit `feat(quotes): quotation PDF with draft watermark`.

---

### Task 5: Accept → Contract + contract BOQ (điểm nối code cũ — cẩn trọng nhất)

**Files:** Modify `app/Http/Controllers/Api/OpportunityController.php` (`createContract`); test `tests/Feature/Zena/QuoteToContractTest.php` + chạy regression external.

- Đọc NGUYÊN method trước. Thay guard external-accepted bằng: `$nativeQuote = Quote::query()->where('opportunity_id',...)->where('status', Quote::STATUS_ACCEPTED)->first();` — pass nếu `$nativeQuote !== null` HOẶC điều kiện external cũ; message lỗi mới nêu cả hai đường.
- Khi `$nativeQuote`: trong transaction hiện có — contract nhận `source_quote_id = $nativeQuote->id`, `source_quote_revision = $nativeQuote->revision_no`, `total_value = $nativeQuote->subtotal`; sau khi contract tạo: `Boq::query()->create([...contract_id...])` + copy từng line (code/name/unit/quantity/unit_price; theo sort_order). KHÔNG đụng nhánh external (giữ nguyên từng dòng).
- Test: accept quote → gọi createContract (qua route web `crm.opportunities.create-contract` như CrmApiTest làm) → contract đúng total/source/revision + BOQ + lines khớp từng dòng và đơn giá; idempotent gọi lần 2; **nhánh external-only chạy y cũ** (mirror test hiện có của createContract — tìm và chạy lại nguyên bộ); cả hai accepted → ưu tiên native.

- [ ] Steps: failing test → sửa method → PASS + regression CrmApiTest + toàn bộ test createContract/external hiện có → checklist → commit `feat(quotes): accepted native quote generates contract with BOQ lines`.

---

### Task 6: Final verification + PR

- [ ] 3 con số + baseline diff 0 path mới + guardrails CI success.
- [ ] `gh pr create` base `feat/s4.3-material-submittal-package`; dán `gh pr view --json baseRefName,commits` vào báo cáo. KHÔNG merge.

## Self-review notes

- Spec coverage: Data→T1, Lifecycle→T2, UI→T3, PDF→T4, Accept-wiring→T5; mọi error-rule của spec có test chỉ định; external regression là gate riêng trong T5.
- Interface T1 khai báo một lần; route names T2 dùng xuyên suốt T3/T4.
- Bài học tích lũy được nhúng thành constraint cứng (index 64 ký tự, TestCase, baseline, PR base).
