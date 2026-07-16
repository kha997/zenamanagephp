# Quote Document Context Implementation Plan (Goal #4 Slice)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thêm `quote` làm context thứ 4 vào thư viện biểu mẫu (`document-templates`) đã có, cho phép sale tự thiết kế mẫu HTML báo giá và xuất PDF từ quote thật.

**Architecture:** `QuoteContextProvider implements DocumentContextProvider` (interface có sẵn) đăng ký vào `DocumentContextRegistry` (đã có 3 provider: Contract/Certificate/Project — theo ĐÚNG pattern, không tự sáng tạo cấu trúc mới); wiring vào `DocumentTemplatePageController` (mở khóa context) và `CrmPageController` (route render PDF thật, mirror `ContractPageController::renderContractDocument`). Spec: `docs/superpowers/specs/2026-07-16-quote-document-context-design.md`.

## Global Constraints

- `Model::query()`; CẤM helper `auth()` (dùng `Auth` facade/`$request->user()` theo file đang sửa — `CrmPageController` hiện dùng `auth()->user()?->tenant_id` nhiều chỗ, GIỮ NGUYÊN pattern của file, không đổi phong cách file khác); CẤM sửa `tests/TestCase.php`; file mới không có entry baseline (diff kiểm trước báo cáo); `HasFactory` mới = kèm `/** @use HasFactory<...> */`.
- Test mutation/render PHẢI assert response thực tế, không chỉ side-effect.
- `sample()` của provider mới CHỈ được trả scalar (string/int/float/bool) — có test chung `DocumentContextProvidersTest::test_sample_returns_literal_array_without_db` sẽ tự động phủ provider mới nếu bạn thêm nó vào mảng `$providers` trong test đó (Task 1 làm việc này).
- `price_note` (ghi chú đơn giá nội bộ) KHÔNG được xuất hiện trong `lines_table_html` — đúng nguyên tắc đã áp dụng cho portal/PDF quote ở các slice trước.
- Checklist sau MỖI task: Architecture 29 / `--testsuite=Feature` toàn bộ xanh / phpstan exit 0. Push cuối: guardrails CI success.
- PR: base `main` (không còn PR nào khác đang stack). Sau khi tạo dán `gh pr view <n> --json baseRefName,commits,mergeable` vào báo cáo. KHÔNG merge.
- Claim "pre-existing failure" phải kèm bằng chứng chạy trên base commit hiện tại của `main`.

---

### Task 1: QuoteContextProvider + đăng ký registry

**Files:** Create `app/Services/DocumentContext/QuoteContextProvider.php`; Modify `app/Providers/AppServiceProvider.php` (thêm vào mảng providers của `DocumentContextRegistry` singleton, dòng ~27-33); Modify `tests/Unit/Services/DocumentContextProvidersTest.php` (thêm `QuoteContextProvider` vào constructor registry, `assertCount(3, ...)` → `assertCount(4, ...)`, thêm vào mảng `$providers` của `test_sample_returns_literal_array_without_db`); Test: Create `tests/Unit/Services/QuoteContextProviderTest.php`.

**Interfaces (Task 2-3 dùng đúng):**

```php
namespace App\Services\DocumentContext;

use App\Models\Quote;
use App\Support\VietnameseMoneyWords;
use Illuminate\Database\Eloquent\Model;

class QuoteContextProvider implements DocumentContextProvider
{
    public function slug(): string { return 'quote'; }
    public function label(): string { return 'Báo giá'; }

    /** @return list<array{key: string, type: string, label: string}> */
    public function keys(): array
    {
        return [
            ['key' => 'quote_number', 'type' => 'string', 'label' => 'Số báo giá'],
            ['key' => 'revision_no', 'type' => 'string', 'label' => 'Bản chào số'],
            ['key' => 'status_label', 'type' => 'string', 'label' => 'Trạng thái'],
            ['key' => 'account_name', 'type' => 'string', 'label' => 'Tên khách hàng'],
            ['key' => 'opportunity_name', 'type' => 'string', 'label' => 'Tên cơ hội'],
            ['key' => 'valid_until', 'type' => 'date', 'label' => 'Hiệu lực đến'],
            ['key' => 'subtotal', 'type' => 'number', 'label' => 'Tạm tính'],
            ['key' => 'discount_percent', 'type' => 'number', 'label' => 'Chiết khấu (%)'],
            ['key' => 'discount_amount', 'type' => 'number', 'label' => 'Số tiền chiết khấu'],
            ['key' => 'vat_percent', 'type' => 'number', 'label' => 'VAT (%)'],
            ['key' => 'vat_amount', 'type' => 'number', 'label' => 'Số tiền VAT'],
            ['key' => 'total', 'type' => 'number', 'label' => 'Tổng cộng'],
            ['key' => 'total_words', 'type' => 'string', 'label' => 'Tổng bằng chữ'],
            ['key' => 'payment_terms', 'type' => 'string', 'label' => 'Điều khoản thanh toán'],
            ['key' => 'today', 'type' => 'date', 'label' => 'Hôm nay'],
            ['key' => 'lines_table_html', 'type' => 'html', 'label' => 'Bảng dòng báo giá'],
        ];
    }

    /** @return array<string, mixed> */
    public function build(Model $subject): array
    {
        /** @var Quote $quote */
        $quote = $subject;
        $quote->loadMissing('lines', 'opportunity.account');

        return [
            'quote_number' => (string) $quote->quote_number,
            'revision_no' => (string) $quote->revision_no,
            'status_label' => $this->statusLabel($quote->status),
            'account_name' => (string) ($quote->opportunity?->account?->display_name ?? ''),
            'opportunity_name' => (string) ($quote->opportunity?->opportunity_name ?? ''),
            'valid_until' => $quote->valid_until?->format('d/m/Y') ?? '',
            'subtotal' => number_format((float) $quote->subtotal, 2, '.', ','),
            'discount_percent' => number_format((float) $quote->discount_percent, 2, '.', ','),
            'discount_amount' => number_format((float) $quote->discount_amount, 2, '.', ','),
            'vat_percent' => number_format((float) $quote->vat_percent, 2, '.', ','),
            'vat_amount' => number_format((float) $quote->vat_amount, 2, '.', ','),
            'total' => number_format((float) $quote->total, 2, '.', ','),
            'total_words' => VietnameseMoneyWords::toWords((float) $quote->total),
            'payment_terms' => (string) ($quote->payment_terms ?? ''),
            'today' => now()->format('d/m/Y'),
            'lines_table_html' => $this->renderLinesTable($quote->lines),
        ];
    }

    /** @return array<string, mixed> */
    public function sample(): array
    {
        return [
            'quote_number' => 'BG-2026-0001',
            'revision_no' => '1',
            'status_label' => 'Đã gửi',
            'account_name' => 'Công ty TNHH Golden',
            'opportunity_name' => 'Cải tạo văn phòng Golden',
            'valid_until' => '31/12/2026',
            'subtotal' => '27,500,000.00',
            'discount_percent' => '10.00',
            'discount_amount' => '2,750,000.00',
            'vat_percent' => '8.00',
            'vat_amount' => '1,980,000.00',
            'total' => '26,730,000.00',
            'total_words' => 'Hai mươi sáu triệu bảy trăm ba mươi nghìn đồng',
            'payment_terms' => '50% tạm ứng, 50% khi bàn giao',
            'today' => now()->format('d/m/Y'),
            'lines_table_html' => $this->sampleLinesTable(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Quote::STATUS_DRAFT => 'Nháp',
            Quote::STATUS_SENT => 'Đã gửi',
            Quote::STATUS_ACCEPTED => 'Đã chấp nhận',
            Quote::STATUS_REJECTED => 'Đã từ chối',
            Quote::STATUS_SUPERSEDED => 'Đã thay thế',
            default => $status,
        };
    }

    // renderLinesTable($lines) / sampleLinesTable(): mirror renderBoqTable/sampleBoqTable
    // của ContractContextProvider (đọc file đó trước khi viết) — cột STT/Tên/ĐVT/KL/Đơn giá/Thành tiền,
    // KHÔNG có cột price_note.
}
```

Test `QuoteContextProviderTest` (mirror style của `DocumentContextProvidersTest`): `slug()==='quote'`, `label()==='Báo giá'`; mọi key trong `keys()` có mặt trong `sample()`; build từ quote thật (2 dòng: 100×200.000 + 5×1.500.000, discount 10%, vat 8% — dùng `Quote::computeTotals`) → đúng từng field: `subtotal` 27.500.000,00, `discount_amount` 2.750.000,00, `vat_amount` 1.980.000,00, `total` 26.730.000,00, `total_words` không rỗng, `lines_table_html` chứa tên dòng và KHÔNG chứa `price_note` đã seed; `sample()` toàn scalar.

- [ ] Steps: failing test (provider chưa tồn tại) → viết provider + đăng ký AppServiceProvider + sửa `DocumentContextProvidersTest` (assertCount 3→4, thêm vào mảng sample test) → PASS toàn bộ (kể cả `DocumentContextProvidersTest` cũ) → checklist → commit `feat(documents): quote context provider for template library`.

---

### Task 2: Mở khóa context `quote` trong thư viện biểu mẫu

**Files:** Modify `app/Http/Controllers/Web/DocumentTemplatePageController.php` (`VALID_CONTEXTS` dòng ~19 thêm `'quote'`; validation `store()` dòng ~59 rule `in:contract,certificate,project,quote`); Test: thêm case vào test hiện có của controller này (tìm file test tương ứng — grep `DocumentTemplatePageController` trong `tests/`) hoặc tạo case mới nếu chưa có: tạo template `context=quote` thành công, `edit()` trả đúng `placeholders` từ `QuoteContextProvider::keys()`.

- [ ] Steps: failing test → 2 dòng sửa → PASS → checklist → commit `feat(documents): unlock quote context in template library UI`.

---

### Task 3: Route render PDF thật + UI trên trang quote

**Files:** Modify `routes/web.php` (route mới cạnh các route quotes hiện có ~dòng 1030-1037); Modify `app/Http/Controllers/Web/CrmPageController.php` (method `renderQuoteDocument` mirror `ContractPageController::renderContractDocument` — ĐỌC NGUYÊN method đó trước khi viết; và trong `showQuote()` thêm truy vấn `quoteTemplates` giống `ContractPageController::show()` dòng ~275-281 nhưng `where('context', 'quote')`); Modify `resources/views/crm/quote-show.blade.php` (khối "Xuất theo biểu mẫu": `@foreach($quoteTemplates as $tpl)` link tới route render, ẩn khối nếu rỗng — đọc cách `contracts/show.blade.php` xử lý danh sách rỗng trước khi viết để đồng bộ UI); Test: thêm case vào `tests/Feature/QuoteLifecycleTest.php` hoặc file riêng.

```php
Route::get('/crm/quotes/{id}/render/{template}', [App\Http\Controllers\Web\CrmPageController::class, 'renderQuoteDocument'])->middleware('rbac:crm.view')->name('crm.quotes.render-document');
```

```php
public function renderQuoteDocument(string $id, string $template, DeliverableTemplateVersionService $versionService, DocumentContextRegistry $contextRegistry, DeliverablePdfExportService $pdfService): SymfonyResponse
{
    $tenantId = (string) auth()->user()?->tenant_id;

    $quote = Quote::query()
        ->join('opportunities', 'opportunities.id', '=', 'quotes.opportunity_id')
        ->where('quotes.id', $id)
        ->where('quotes.tenant_id', $tenantId)
        ->select('quotes.*')
        ->firstOrFail();

    $tpl = \App\Models\DeliverableTemplate::query()->where('tenant_id', $tenantId)->where('context', 'quote')->findOrFail($template);

    $version = $tpl->latestPublishedVersion()->first();
    if ($version === null) {
        abort(404);
    }

    $html = (string) \Illuminate\Support\Facades\Storage::disk('local')->get($version->storage_path);
    $context = $contextRegistry->get('quote')->build($quote);
    $rendered = $versionService->renderHtml($html, $context);

    try {
        $pdfBytes = $pdfService->render($rendered);
    } catch (\App\Exceptions\DeliverablePdfExportUnavailableException) {
        return back()->with('error', 'Không thể tạo PDF vào lúc này.');
    }

    return response($pdfBytes, 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="bao-gia-' . \Illuminate\Support\Str::slug($tpl->name) . '-' . $quote->quote_number . '.pdf"',
    ]);
}
```

Test: publish 1 template context=quote, gọi route → 200 PDF (hoặc pattern skip-if-unavailable của QuotePdfTest — đọc file đó trước, theo đúng cách xử lý); template context khác (vd contract) không hiện trong danh sách UI của trang quote; quote/template khác tenant → 404; chưa publish → link không hiện (render assertion `assertDontSee`).

- [ ] Steps: failing test → route + method + view → PASS → checklist → commit `feat(documents): render published quote templates to PDF`.

---

### Task 4: Final verification + PR

- [ ] 3 con số (Architecture 29 / Feature suite full xanh / phpstan 0) + baseline diff 0 path mới + `DocumentContextProvidersTest` cũ vẫn xanh (4 provider) + guardrails CI success.
- [ ] `gh pr create` head nhánh mới (tạo từ `main` hiện tại, sau khi đã có toàn bộ quote work) **base `main`**; dán `gh pr view <n> --json baseRefName,commits,mergeable` vào báo cáo. KHÔNG merge.

## Self-review notes

- Spec coverage: Provider→T1, unlock UI→T2, render thật+UI trang quote→T3.
- `QuoteContextProvider` khai báo 1 lần ở T1, T2/T3 chỉ tiêu thụ qua registry — không viết logic build context ở nơi khác.
- price_note không lộ: chốt ở T1 (test) — kế thừa đúng nguyên tắc slice 2.
- Bài học cũ nhúng: mọi test mutation/render assert response thật; sample() chỉ scalar (assert chung + assert riêng); PR base main (không phải branch trung gian — bài học từ vụ #167/168/169 bị lạc merge).
