# Quote Client Portal Implementation Plan (Goal #2 Slice 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sửa bug 500 EventRecord của quote lifecycle (slice 1), rồi đưa báo giá lên client portal: khách xem + Chấp nhận/Từ chối qua magic link, accept giữ nguyên semantics supersede + sẵn sàng createContract.

**Architecture:** Task 1 fix bug trên `feat/native-quotes` (cập nhật PR #167). Từ Task 2: branch mới `feat/quote-client-portal` từ `feat/native-quotes`; extract `QuoteLifecycleService` dùng chung operator/portal; `PortalQuoteController` theo đúng pattern `PortalDesignItemController` (guard `client`, ownership qua `opportunities.account_id`, 404 đồng nhất). Spec: `docs/superpowers/specs/2026-07-15-quote-client-portal-design.md`.

## Global Constraints (giữ nguyên toàn bộ nếp slice 1)

- `Model::query()`; pattern auth như file đang sửa (operator: `auth()`; portal: `Auth::guard('client')->user()`); **CẤM sửa tests/TestCase.php hay hạ tầng test dùng chung** — CSRF trong test = `$this->get('/login');` trong setUp(); file mới không có entry baseline (diff kiểm trước báo cáo); thêm `HasFactory` = kèm `/** @use HasFactory<...> */`; magic property = `@property` docblock.
- Tên index/FK migration ≤ 64 ký tự (slice này KHÔNG có migration mới — nếu phát sinh, dừng lại báo cáo).
- Claim "pre-existing failure" phải kèm bằng chứng chạy trên base commit.
- Checklist sau MỖI task: Architecture 29 / `--testsuite=Feature` toàn bộ xanh / phpstan exit 0. Push cuối: guardrails CI success.
- PR mới (Task 6): **base phải là `feat/native-quotes`** (stacked trên PR #167). Sau khi tạo phải dán output `gh pr view <n> --json baseRefName,commits` vào báo cáo. KHÔNG merge.
- EventRecord LUÔN theo schema thật: `event_key` / `actor_user_id` / `payload` / `occurred_at` (xem `Api\OpportunityController::recordEvent` dòng ~85 làm mẫu). KHÔNG BAO GIỜ dùng `event_type`/`actor_id`.
- Test portal: pattern `tests/Feature/Portal/PortalDesignItemActionsTest.php` — `$this->actingAs($account, 'client');`, route có `tenantSlug`.

---

### Task 1: Fix bug EventRecord 500 (trên `feat/native-quotes` — cập nhật PR #167)

**Files:** Modify `app/Http/Controllers/Web/CrmPageController.php` (3 chỗ tạo EventRecord trong `sendQuote` ~519, `acceptQuote` ~562, `rejectQuote` ~596); Modify `tests/Feature/QuoteLifecycleTest.php`; Delete `tests/Feature/TmpQuoteEventProbeTest.php` (probe của orchestrator, nội dung chuyển vào QuoteLifecycleTest).

**Bug đã xác minh:** 3 chỗ dùng `'event_type' => ...` và `'actor_id' => ...` — cả hai KHÔNG có trong `$fillable` của `EventRecord` nên bị strip, và thiếu `occurred_at`; cột `event_key`/`occurred_at` NOT NULL → insert nổ → response 500 (quote đã đổi status trước đó). Probe test hiện có tại `tests/Feature/TmpQuoteEventProbeTest.php` đang FAIL đúng chỗ này.

**Fix mẫu (áp cho cả 3, đổi event_key tương ứng `quote.sent`/`quote.accepted`/`quote.rejected`):**

```php
EventRecord::query()->create([
    'tenant_id' => $tenantId,
    'aggregate_type' => 'quote',
    'aggregate_id' => (string) $quote->id,
    'event_key' => 'quote.sent',
    'actor_user_id' => auth()->id() ? (string) auth()->id() : null,
    'payload' => ['quote_number' => $quote->quote_number],
    'occurred_at' => now(),
]);
```

- [ ] Steps: chạy probe test xác nhận FAIL (bằng chứng) → bổ sung vào QuoteLifecycleTest cho CẢ send/accept/reject: `->assertRedirect()` + `assertSessionHas('success')` + `EventRecord::query()->where('aggregate_id', ...)->where('event_key', 'quote.sent')->count() === 1` (xác nhận FAIL trước fix) → sửa 3 chỗ → PASS → xóa `TmpQuoteEventProbeTest.php` → checklist → commit trên `feat/native-quotes`: `fix(quotes): record lifecycle events with correct event_record columns` → push (PR #167 tự cập nhật).

---

### Task 2: QuoteLifecycleService (branch mới `feat/quote-client-portal`)

**Files:** Create `app/Services/QuoteLifecycleService.php`; Modify `app/Http/Controllers/Web/CrmPageController.php` (`acceptQuote`/`rejectQuote` delegate sang service, giữ nguyên response/message); Test: QuoteLifecycleTest phải xanh nguyên bộ (behavior không đổi) + bổ sung assert payload.

**Interfaces (T3 dùng đúng):**

```php
namespace App\Services;

class QuoteLifecycleService
{
    /**
     * @param array{actor_user_id?: string|null, actor_account_id?: string|null, source: string, note?: string|null} $context
     * @throws \Illuminate\Validation\ValidationException khi transition không hợp lệ
     */
    public function accept(Quote $quote, array $context): Quote;
    // transaction: status=accepted + decided_at=now; các quote khác cùng opportunity ở draft/sent/rejected → superseded;
    // EventRecord event_key=quote.accepted, actor_user_id từ context, payload = [quote_number, source, actor_account_id?, ...]

    public function reject(Quote $quote, array $context): Quote;
    // status=rejected + decided_at; EventRecord quote.rejected, payload thêm 'note' nếu có
}
```

- Service tự re-check `Quote::canTransition` → sai thì `ValidationException::withMessages(['action' => 'Không thể chuyển trạng thái.'])`.
- Operator gọi: `accept($quote, ['actor_user_id' => (string) auth()->id(), 'source' => 'operator'])`; controller giữ pre-check + `back()->with('error', ...)` y như cũ (message không đổi để test cũ xanh).

- [ ] Steps: thêm assert payload `source=operator` vào test accept hiện có (FAIL) → viết service → refactor 2 method → toàn bộ QuoteLifecycleTest PASS → checklist → commit `refactor(quotes): extract QuoteLifecycleService for shared accept/reject`.

---

### Task 3: Portal quote controller + routes + view

**Files:** Create `app/Http/Controllers/Web/Portal/PortalQuoteController.php`, `resources/views/portal/quote.blade.php`; Modify `routes/web.php` (trong nhóm `portal.auth`, sau design-items ~dòng 1052); Test: Create `tests/Feature/Portal/PortalQuoteTest.php`.

**Routes:**

```php
Route::get('/quotes/{id}', [App\Http\Controllers\Web\Portal\PortalQuoteController::class, 'show'])->name('quotes.show');
Route::get('/quotes/{id}/pdf', [App\Http\Controllers\Web\Portal\PortalQuoteController::class, 'pdf'])->name('quotes.pdf');
Route::post('/quotes/{id}/accept', [App\Http\Controllers\Web\Portal\PortalQuoteController::class, 'accept'])->middleware('throttle:portal-actions')->name('quotes.accept');
Route::post('/quotes/{id}/reject', [App\Http\Controllers\Web\Portal\PortalQuoteController::class, 'reject'])->middleware('throttle:portal-actions')->name('quotes.reject');
```

**Controller** (mirror `PortalDesignItemController` từng chi tiết: resolve tenant qua slug `firstOrFail`, account từ `Auth::guard('client')->user()`):

```php
private function findOwnedQuote(string $tenantId, string $accountId, string $id): Quote
{
    /** @var Builder<Quote> $query */
    $query = Quote::query()
        ->join('opportunities', 'opportunities.id', '=', 'quotes.opportunity_id')
        ->where('quotes.tenant_id', $tenantId)
        ->where('opportunities.account_id', $accountId)
        ->where('quotes.status', '!=', Quote::STATUS_DRAFT)
        ->select('quotes.*');

    return $query->findOrFail($id); // mọi nhánh từ chối đều 404 đồng nhất
}
```

- `show`: view `portal.quote` với `tenant`, `quote` (kèm `lines` orderBy sort_order — dùng relation), `amountInWords = \App\Support\VietnameseMoneyWords::toWords((float) $quote->subtotal)`.
- `accept`: nếu status !== sent → `back()->withErrors(['action' => 'Báo giá không còn ở trạng thái chờ phản hồi.'])`; gọi `QuoteLifecycleService::accept($quote, ['actor_account_id' => (string) $account->id, 'source' => 'portal'])` trong try/catch ValidationException → back()->withErrors; sau đó notify (dưới) + `back()->with('success', 'Bạn đã chấp nhận báo giá. Cảm ơn bạn!')`.
- `reject`: validate `['note' => ['nullable', 'string', 'max:1000']]`; tương tự với `note` trong context; success message 'Đã ghi nhận phản hồi của bạn.'.
- `notifyCreator(Quote $quote, string $actionLabel, ?string $body = null)`: copy pattern `notifyAssignee` (try/catch nuốt Throwable), gửi tới `$quote->created_by`, `type = 'portal_client_action'`, `link_url = route('operator.crm.quotes.show', $quote->id)`.

**View `portal/quote.blade.php`** (khung + header/footer y `portal/design-item.blade.php`): số quote + "Bản chào #{revision_no}", status badge (sent="Chờ phản hồi", accepted="Đã chấp nhận", rejected="Đã từ chối", superseded="Đã thay thế"), `valid_until` nếu có, bảng dòng (STT/Tên/ĐVT/KL/Đơn giá/Thành tiền — KHÔNG có price_note), tổng + "Bằng chữ: {amountInWords}", link "Tải PDF" → `portal.quotes.pdf`; khi sent: form POST accept (nút "Chấp nhận báo giá", `onclick="return confirm('Xác nhận chấp nhận báo giá này?')"`) + form POST reject với textarea `note` (nút "Từ chối / Yêu cầu điều chỉnh").

**Test `PortalQuoteTest`** (setUp như PortalDesignItemActionsTest nhưng KHÔNG cần project/converted — opportunity chỉ cần `account_id`; tạo quote sent với 2 dòng: 100×200000 + 5×1500000, subtotal 27500000):

1. show 200: thấy quote_number, "27.500.000" hoặc số raw theo view, "Bằng chữ", nút "Chấp nhận báo giá"; KHÔNG thấy nội dung price_note đã seed.
2. accept happy: redirect + success; quote accepted + decided_at; quote draft khác của opportunity → superseded; EventRecord `quote.accepted` với payload `source=portal` + `actor_account_id`; Notification cho created_by.
3. reject kèm note: quote rejected; payload EventRecord có `note`.
4. accept khi đã accepted → sessionHasErrors + status không đổi (không 500).
5. quote draft → show 404. 6. quote của account khác (cùng tenant) → 404. 7. tenantSlug của tenant khác → 404. 8. chưa login (không actingAs) → redirect về `portal.login`.

- [ ] Steps: failing test → routes → controller + view → PASS → checklist → commit `feat(portal): client quote view with accept and reject actions`.

---

### Task 4: Dashboard portal — section Báo giá

**Files:** Modify `app/Http/Controllers/Web/Portal/PortalDashboardController.php` + `resources/views/portal/dashboard.blade.php`; Modify test `tests/Feature/Portal/PortalDashboardTest.php` (thêm case, không sửa case cũ).

- Controller thêm (LƯU Ý: qua account_id trực tiếp, KHÔNG qua `$projectIds` — quote tồn tại trước khi convert):

```php
$quotes = Quote::query()
    ->join('opportunities', 'opportunities.id', '=', 'quotes.opportunity_id')
    ->where('quotes.tenant_id', $tenant->id)
    ->where('opportunities.account_id', $account->id)
    ->where('quotes.status', '!=', Quote::STATUS_DRAFT)
    ->orderByDesc('quotes.sent_at')
    ->select('quotes.*')
    ->get();
```

- View: section "Báo giá" (trên section dự án): bảng số/bản chào/tổng/status badge/ngày gửi, mỗi dòng link `portal.quotes.show`; rỗng thì ẩn section.
- Test: quote sent của opportunity CHƯA convert hiện trên dashboard kèm link; quote draft không hiện; quote của account khác không hiện.

- [ ] Steps: failing test → controller + view → PASS → checklist → commit `feat(portal): dashboard quote list for client accounts`.

---

### Task 5: PDF trên portal (ẩn price_note)

**Files:** Modify `resources/views/crm/quote-pdf.blade.php` (nhận flag `$hidePriceNote` default false — bọc cột Ghi chú trong `@unless($hidePriceNote ?? false)`); Modify `app/Http/Controllers/Web/Portal/PortalQuoteController.php` (method `pdf`); Test: thêm case vào `PortalQuoteTest` + regression case vào `tests/Feature/QuotePdfTest.php` (operator PDF VẪN chứa price_note).

- `pdf`: `findOwnedQuote` → mirror `CrmPageController::quotePdf` (đọc method ~dòng 685 trước): render `crm.quote-pdf` với `hidePriceNote => true` (không cần watermark — draft không bao giờ tới đây) → `DeliverablePdfExportService` → download `bao-gia-{quote_number}.pdf`; catch Unavailable → back error như quotePdf.
- Test portal: endpoint 200 + content-type PDF (hoặc pattern skip-if-unavailable mà QuotePdfTest đang dùng — đọc file đó trước và theo đúng pattern); view-render `crm.quote-pdf` với `hidePriceNote=true` KHÔNG chứa text price_note; regression operator render (không truyền flag) CÓ chứa.

- [ ] Steps: failing tests → blade flag + method → PASS → checklist → commit `feat(portal): quote pdf download without internal price notes`.

---

### Task 6: Final verification + PR

- [ ] 3 con số (Architecture 29 / Feature suite full xanh / phpstan 0) + baseline diff 0 path mới + guardrails CI success trên cả 2 branch đã push.
- [ ] `gh pr create` head `feat/quote-client-portal` **base `feat/native-quotes`**; dán `gh pr view <n> --json baseRefName,commits` vào báo cáo. KHÔNG merge.

## Self-review notes

- Spec coverage: Bug fix→T1, Service→T2, Portal view+actions→T3, Dashboard→T4, PDF→T5.
- Interface service khai báo ở T2, T3 gọi đúng chữ ký; route names T3 dùng ở T4 (link) và T5 (pdf).
- Ownership quote KHÔNG dùng converted_project_id — chốt ở cả T3 (helper) lẫn T4 (dashboard query) để khỏi lặp lại nhầm lẫn của dashboard hiện tại.
- price_note không lộ ra portal: chốt ở T3 (view + test) và T5 (PDF flag + regression operator).
