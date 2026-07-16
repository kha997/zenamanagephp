# Phase 3 — Hiển thị báo giá trong CRM Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** surface the zena-boq-core quote data synced by Phase 2 directly on the Opportunity page, with correct status/calibration badges, a staleness warning, and a deep link to the real quote.

**Architecture:** Extend Phase 2's read pipeline (no new tables/columns) — the synced-quote snapshot gains a `revision` field for Phase 4's later use, `Web\CrmPageController` assembles a small source-agnostic view-model from `external_quote_snapshot` (per the roadmap spec's forward-compat note), and the existing "Báo giá — zena-boq-core" card is redesigned around that view-model with two new/extended Blade components.

**Tech Stack:** Laravel 12, Blade components, existing `ZenaBoqIntegrationService` (Phase 2), existing `<x-ui.status-badge>`/`<x-ui.field-value>`/`<x-ui.card>` components.

## Global Constraints

- **No new migration.** `revision` lives inside the existing `external_quote_snapshot` JSON column (cast to `array` on `Opportunity`), alongside `subtotal`/`vat_amount`/`total`/`status`/`calibration`/`issued_at`. Do not add a dedicated column.
- **Stale threshold is exactly 14 days.** `external_quote_synced_at->diffInDays(now()) > 14` marks a synced quote as stale — decided with the user during this phase's brainstorm.
- **Relative-time rendering uses `->diffForHumans()`**, matching this codebase's only two existing precedents (`App\Models\CalendarIntegration::`, `App\Models\ProjectActivity::`). App locale is `en` — do not add Vietnamese-locale (`Carbon::setLocale('vi')`) infrastructure; that's out of scope for this phase.
- **Money formatting** uses the existing convention already in this file: `number_format((float) $value, 0, ',', '.') . '₫'`.
- **External link target** is `{rtrim(config('zena_boq.base_url'), '/')}/quotes/{external_quote_id}`, with `target="_blank" rel="noopener"` — confirmed real route (`web/src/app/quotes/[id]/page.tsx` in `zena-boq-core`). No embed/iframe.
- **`QuoteStatus` real values** (confirmed against `zena-boq-core`'s Prisma schema): `DRAFT | ISSUED | ACCEPTED | REJECTED | SUPERSEDED`. `CalibrationStatus` real values: `UNCALIBRATED | CALIBRATED`.
- **The calibration badge is a new, dedicated component** (`<x-ui.calibration-badge>`), never folded into the generic `<x-ui.status-badge>` — this is a data-quality/governance signal carried over from `zena-boq-core`'s own rules, not an ordinary workflow status, and the spec requires it to be visually impossible to confuse with anything else.
- **Both mutation actions in the BOQ card (the "Liên kết" link-form and the "Đồng bộ báo giá" sync-button) are gated in the view by a `canManageBoq` flag** computed from `auth()->user()->hasPermission('crm.manage')`. Phase 2 left these ungated at the view layer (relying only on server-side route middleware); this phase closes that gap for both actions, not just the empty-state one the spec text called out, for consistency — a `crm.view`-only user must see the card's data with no action buttons at all, never a button that silently 403s on click.
- **The Blade template consumes only the assembled view-model array** returned by `CrmPageController::buildBoqCardViewModel()`, never `external_quote_snapshot` fields directly — this is what lets the deferred internal-quotation fallback (Phase 2's "Non-Z.E.N.A tenants" note) reuse the same card later without a rewrite.
- `declare(strict_types=1)` at the top of every PHP file touched.
- Every test mocks `zena-boq-core` via `Http::fake([...])` — the real read API still does not exist.

---

### Task 1: Capture `revision` in the synced quote snapshot

**Files:**
- Modify: `app/Services/ZenaBoqIntegrationService.php:37-96` (`fetchLatestQuote()`)
- Modify: `app/Http/Controllers/Api/OpportunityController.php:498-517` (`syncExternalQuote()`)
- Modify: `tests/Unit/ZenaBoqIntegrationServiceTest.php:57-80` (existing success test)
- Modify: `tests/Feature/Api/CrmApiTest.php:336-369` (existing sync-success test)

**Interfaces:**
- Consumes: nothing new — extends the existing `fetchLatestQuote(string $projectCode): ?array` return shape and `syncExternalQuote()`'s existing `$quote` array handling (both already built in Phase 2).
- Produces: `fetchLatestQuote()`'s returned array gains `'revision' => int`; `external_quote_snapshot` gains a `'revision'` key. Task 3 does not consume `revision` (it's stored only, for Phase 4).

- [ ] **Step 1: Update the existing unit test to assert `revision` is captured**

In `tests/Unit/ZenaBoqIntegrationServiceTest.php`, find `test_fetch_latest_quote_returns_shaped_array_on_success` (around line 57):

```php
    public function test_fetch_latest_quote_returns_shaped_array_on_success(): void
    {
        config(['zena_boq.base_url' => 'https://zena-boq.example', 'zena_boq.read_api_secret' => 'test-secret']);

        Http::fake([
            'https://zena-boq.example/api/external/projects/*' => Http::response(['id' => 'proj_1', 'code' => 'PRJ-001'], 200),
            'https://zena-boq.example/api/external/quotes/latest*' => Http::response([
                'id' => 'quote_1',
                'subtotal' => 100000000,
                'vatAmount' => 8000000,
                'total' => 108000000,
                'status' => 'ISSUED',
                'calibration' => 'UNCALIBRATED',
                'issuedAt' => '2026-07-10T00:00:00Z',
            ], 200),
        ]);

        $result = (new ZenaBoqIntegrationService())->fetchLatestQuote('PRJ-001');

        $this->assertNotNull($result);
        $this->assertSame('quote_1', $result['id']);
        $this->assertSame(108000000.0, $result['total']);
        $this->assertSame('UNCALIBRATED', $result['calibration']);
    }
```

Replace with (adds `'revision' => 3` to the fake response and a new assertion):

```php
    public function test_fetch_latest_quote_returns_shaped_array_on_success(): void
    {
        config(['zena_boq.base_url' => 'https://zena-boq.example', 'zena_boq.read_api_secret' => 'test-secret']);

        Http::fake([
            'https://zena-boq.example/api/external/projects/*' => Http::response(['id' => 'proj_1', 'code' => 'PRJ-001'], 200),
            'https://zena-boq.example/api/external/quotes/latest*' => Http::response([
                'id' => 'quote_1',
                'revision' => 3,
                'subtotal' => 100000000,
                'vatAmount' => 8000000,
                'total' => 108000000,
                'status' => 'ISSUED',
                'calibration' => 'UNCALIBRATED',
                'issuedAt' => '2026-07-10T00:00:00Z',
            ], 200),
        ]);

        $result = (new ZenaBoqIntegrationService())->fetchLatestQuote('PRJ-001');

        $this->assertNotNull($result);
        $this->assertSame('quote_1', $result['id']);
        $this->assertSame(3, $result['revision']);
        $this->assertSame(108000000.0, $result['total']);
        $this->assertSame('UNCALIBRATED', $result['calibration']);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/ZenaBoqIntegrationServiceTest.php --filter test_fetch_latest_quote_returns_shaped_array_on_success`
Expected: FAIL — `$result['revision']` is undefined (the service doesn't return it yet).

- [ ] **Step 3: Add `revision` to `fetchLatestQuote()`'s return shape**

In `app/Services/ZenaBoqIntegrationService.php`, find:

```php
    /**
     * @return array{id: string, subtotal: float, vat_amount: float, total: float, status: string, calibration: string, issued_at: ?string}|null
     */
    public function fetchLatestQuote(string $projectCode): ?array
    {
```

Replace with:

```php
    /**
     * @return array{id: string, revision: int, subtotal: float, vat_amount: float, total: float, status: string, calibration: string, issued_at: ?string}|null
     */
    public function fetchLatestQuote(string $projectCode): ?array
    {
```

Then find:

```php
            return [
                'id' => (string) ($quote['id'] ?? ''),
                'subtotal' => (float) ($quote['subtotal'] ?? 0),
                'vat_amount' => (float) ($quote['vatAmount'] ?? 0),
                'total' => (float) ($quote['total'] ?? 0),
                'status' => (string) ($quote['status'] ?? ''),
                'calibration' => (string) ($quote['calibration'] ?? ''),
                'issued_at' => $quote['issuedAt'] ?? null,
            ];
```

Replace with:

```php
            return [
                'id' => (string) ($quote['id'] ?? ''),
                'revision' => (int) ($quote['revision'] ?? 0),
                'subtotal' => (float) ($quote['subtotal'] ?? 0),
                'vat_amount' => (float) ($quote['vatAmount'] ?? 0),
                'total' => (float) ($quote['total'] ?? 0),
                'status' => (string) ($quote['status'] ?? ''),
                'calibration' => (string) ($quote['calibration'] ?? ''),
                'issued_at' => $quote['issuedAt'] ?? null,
            ];
```

- [ ] **Step 4: Run the unit test to verify it passes**

Run: `php artisan test tests/Unit/ZenaBoqIntegrationServiceTest.php`
Expected: PASS (all existing tests in this file, including the updated one — the other tests that hit the malformed/null paths are unaffected since `revision` defaults to `0` via `?? 0`, never breaking their assertions).

- [ ] **Step 5: Update the feature test to assert `revision` reaches `external_quote_snapshot`**

In `tests/Feature/Api/CrmApiTest.php`, find `test_sync_populates_snapshot_on_success` (around line 336):

```php
        \Illuminate\Support\Facades\Http::fake([
            'https://zena-boq.example/api/external/projects/*' => \Illuminate\Support\Facades\Http::response(['id' => 'proj_1'], 200),
            'https://zena-boq.example/api/external/quotes/latest*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'quote_1',
                'subtotal' => 100000000,
                'vatAmount' => 8000000,
                'total' => 108000000,
                'status' => 'ISSUED',
                'calibration' => 'UNCALIBRATED',
                'issuedAt' => '2026-07-10T00:00:00Z',
            ], 200),
        ]);

        $opportunity = $this->createOpportunity(['external_boq_project_code' => 'PRJ-001']);

        $response = $this->postJson($this->route('opportunities.boq-sync', ['id' => $opportunity->id]), [], $this->headersFor($this->userA));

        $response->assertStatus(200)
            ->assertJsonPath('data.external_quote_snapshot.total', 108000000)
            ->assertJsonPath('data.external_quote_snapshot.calibration', 'UNCALIBRATED');

        $opportunity->refresh();
        $this->assertNotNull($opportunity->external_quote_synced_at);
        $this->assertSame('quote_1', $opportunity->external_quote_id);
    }
```

Replace with (adds `'revision' => 3` to the fake response and an assertion that it lands in the persisted snapshot):

```php
        \Illuminate\Support\Facades\Http::fake([
            'https://zena-boq.example/api/external/projects/*' => \Illuminate\Support\Facades\Http::response(['id' => 'proj_1'], 200),
            'https://zena-boq.example/api/external/quotes/latest*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'quote_1',
                'revision' => 3,
                'subtotal' => 100000000,
                'vatAmount' => 8000000,
                'total' => 108000000,
                'status' => 'ISSUED',
                'calibration' => 'UNCALIBRATED',
                'issuedAt' => '2026-07-10T00:00:00Z',
            ], 200),
        ]);

        $opportunity = $this->createOpportunity(['external_boq_project_code' => 'PRJ-001']);

        $response = $this->postJson($this->route('opportunities.boq-sync', ['id' => $opportunity->id]), [], $this->headersFor($this->userA));

        $response->assertStatus(200)
            ->assertJsonPath('data.external_quote_snapshot.total', 108000000)
            ->assertJsonPath('data.external_quote_snapshot.calibration', 'UNCALIBRATED');

        $opportunity->refresh();
        $this->assertNotNull($opportunity->external_quote_synced_at);
        $this->assertSame('quote_1', $opportunity->external_quote_id);
        $this->assertSame(3, $opportunity->external_quote_snapshot['revision']);
    }
```

- [ ] **Step 6: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Api/CrmApiTest.php --filter test_sync_populates_snapshot_on_success`
Expected: FAIL — `external_quote_snapshot['revision']` is undefined (the controller doesn't store it yet).

- [ ] **Step 7: Store `revision` in `external_quote_snapshot`**

In `app/Http/Controllers/Api/OpportunityController.php`, find:

```php
        if ($quote !== null) {
            $opportunity->external_quote_id = $quote['id'];
            $opportunity->external_quote_snapshot = [
                'subtotal' => $quote['subtotal'],
                'vat_amount' => $quote['vat_amount'],
                'total' => $quote['total'],
                'status' => $quote['status'],
                'calibration' => $quote['calibration'],
                'issued_at' => $quote['issued_at'],
            ];
```

Replace with:

```php
        if ($quote !== null) {
            $opportunity->external_quote_id = $quote['id'];
            $opportunity->external_quote_snapshot = [
                'revision' => $quote['revision'],
                'subtotal' => $quote['subtotal'],
                'vat_amount' => $quote['vat_amount'],
                'total' => $quote['total'],
                'status' => $quote['status'],
                'calibration' => $quote['calibration'],
                'issued_at' => $quote['issued_at'],
            ];
```

- [ ] **Step 8: Run both tests to verify they pass**

Run: `php artisan test tests/Unit/ZenaBoqIntegrationServiceTest.php tests/Feature/Api/CrmApiTest.php`
Expected: PASS (all tests in both files — the other `CrmApiTest` sync tests, e.g. the two graceful-degradation ones, don't reach the write branch or don't assert on `revision`, so they're unaffected).

- [ ] **Step 9: Commit**

```bash
git add app/Services/ZenaBoqIntegrationService.php app/Http/Controllers/Api/OpportunityController.php tests/Unit/ZenaBoqIntegrationServiceTest.php tests/Feature/Api/CrmApiTest.php
git commit -m "feat(zena-boq): capture quote revision in synced snapshot for Phase 4"
```

---

### Task 2: Calibration badge component + extend status badge for QuoteStatus

**Files:**
- Create: `resources/views/components/ui/calibration-badge.blade.php`
- Modify: `resources/views/components/ui/status-badge.blade.php`
- Create: `tests/Feature/Zena/BoqBadgeComponentsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `<x-ui.calibration-badge status="UNCALIBRATED|CALIBRATED" />` (new); `<x-ui.status-badge status="issued|accepted|superseded" />` (extended — `draft`/`rejected` already existed and cover 2 of the 5 real `QuoteStatus` values). Task 3 consumes both.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Zena/BoqBadgeComponentsTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class BoqBadgeComponentsTest extends TestCase
{
    public function test_calibration_badge_renders_distinct_markup_for_each_state(): void
    {
        $uncalibrated = Blade::render('<x-ui.calibration-badge status="UNCALIBRATED" />');
        $calibrated = Blade::render('<x-ui.calibration-badge status="CALIBRATED" />');

        $this->assertStringContainsString('bg-rose-600', $uncalibrated);
        $this->assertStringContainsString('Chưa hiệu chỉnh', $uncalibrated);
        $this->assertStringContainsString('bg-emerald-600', $calibrated);
        $this->assertStringContainsString('Đã hiệu chỉnh', $calibrated);
        $this->assertStringNotContainsString('bg-rose-600', $calibrated);
        $this->assertStringNotContainsString('bg-emerald-600', $uncalibrated);
    }

    public function test_status_badge_renders_new_quote_status_values(): void
    {
        $issued = Blade::render('<x-ui.status-badge status="issued" />');
        $accepted = Blade::render('<x-ui.status-badge status="accepted" />');
        $superseded = Blade::render('<x-ui.status-badge status="superseded" />');

        $this->assertStringContainsString('Đã phát hành', $issued);
        $this->assertStringContainsString('Đã chấp nhận', $accepted);
        $this->assertStringContainsString('Đã thay thế', $superseded);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Zena/BoqBadgeComponentsTest.php`
Expected: FAIL — `<x-ui.calibration-badge>` component doesn't exist yet; `status-badge` doesn't yet render the new labels for `issued`/`accepted`/`superseded` (they currently fall through to `default => (string) $status`, i.e. render the raw lowercase string, not the Vietnamese labels).

- [ ] **Step 3: Create the calibration badge component**

Create `resources/views/components/ui/calibration-badge.blade.php`:

```blade
@props(['status'])

@php
    $isCalibrated = strtoupper((string) $status) === 'CALIBRATED';
    $classes = $isCalibrated ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white';
    $label = $isCalibrated ? '✓ Đã hiệu chỉnh' : '⚠ Chưa hiệu chỉnh';
@endphp

<span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $classes }}">
    {{ $label }}
</span>
```

- [ ] **Step 4: Extend the status badge component**

In `resources/views/components/ui/status-badge.blade.php`, find:

```blade
    $classes = match ($value) {
        'draft' => 'bg-slate-100 text-slate-700',
        'submitted', 'open', 'pending_review' => 'bg-amber-100 text-amber-800',
        'approved', 'fulfilled', 'answered', 'applied' => 'bg-emerald-100 text-emerald-800',
        'rejected', 'escalated' => 'bg-rose-100 text-rose-800',
        'closed' => 'bg-slate-200 text-slate-600',
        default => 'bg-slate-100 text-slate-700',
    };
    $label = match ($value) {
        'draft' => 'Nháp',
        'submitted' => 'Đã gửi duyệt',
        'approved' => 'Đã phê duyệt',
        'fulfilled' => 'Hoàn tất',
        'rejected' => 'Từ chối',
        'open' => 'Đang mở',
        'answered' => 'Đã trả lời',
        'closed' => 'Đã đóng',
        'escalated' => 'Đã chuyển cấp',
        'pending_review' => 'Đang xét',
        'applied' => 'Đã áp dụng',
        default => (string) $status,
    };
```

Replace with:

```blade
    $classes = match ($value) {
        'draft' => 'bg-slate-100 text-slate-700',
        'submitted', 'open', 'pending_review', 'issued' => 'bg-amber-100 text-amber-800',
        'approved', 'fulfilled', 'answered', 'applied', 'accepted' => 'bg-emerald-100 text-emerald-800',
        'rejected', 'escalated' => 'bg-rose-100 text-rose-800',
        'closed', 'superseded' => 'bg-slate-200 text-slate-600',
        default => 'bg-slate-100 text-slate-700',
    };
    $label = match ($value) {
        'draft' => 'Nháp',
        'submitted' => 'Đã gửi duyệt',
        'approved' => 'Đã phê duyệt',
        'fulfilled' => 'Hoàn tất',
        'rejected' => 'Từ chối',
        'open' => 'Đang mở',
        'answered' => 'Đã trả lời',
        'closed' => 'Đã đóng',
        'escalated' => 'Đã chuyển cấp',
        'pending_review' => 'Đang xét',
        'applied' => 'Đã áp dụng',
        'issued' => 'Đã phát hành',
        'accepted' => 'Đã chấp nhận',
        'superseded' => 'Đã thay thế',
        default => (string) $status,
    };
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Zena/BoqBadgeComponentsTest.php`
Expected: PASS (2/2).

- [ ] **Step 6: Run the full CRM test files to confirm no regression**

Run: `php artisan test tests/Feature/Api/CrmApiTest.php tests/Feature/Zena/OperatorCrmUiTest.php`
Expected: PASS — the `status-badge` change is additive (new match arms only), so every existing caller of this shared component (design-item review statuses, deliverable statuses, etc.) is unaffected.

- [ ] **Step 7: Commit**

```bash
git add resources/views/components/ui/calibration-badge.blade.php resources/views/components/ui/status-badge.blade.php tests/Feature/Zena/BoqBadgeComponentsTest.php
git commit -m "feat(zena-boq): add calibration badge component, extend status badge for QuoteStatus"
```

---

### Task 3: Assemble the BOQ card view-model, gate mutation actions, redesign the card

**Files:**
- Modify: `app/Http/Controllers/Web/CrmPageController.php:173-200` (`showOpportunity()`)
- Modify: `resources/views/crm/opportunity-show.blade.php:109-135` (BOQ card)
- Modify: `tests/Feature/Zena/OperatorCrmUiTest.php` (extend/add tests)

**Interfaces:**
- Consumes: `<x-ui.calibration-badge>`/`<x-ui.status-badge>` (Task 2), `ZenaBoqIntegrationService::isTenantAuthorized()` (Phase 2, unchanged), `config('zena_boq.base_url')` (Phase 2, unchanged), `Opportunity::external_quote_snapshot`/`external_quote_synced_at`/`external_quote_id`/`external_boq_project_code` (Phase 2, unchanged), `User::hasPermission(string): bool` (existing).
- Produces: `CrmPageController::buildBoqCardViewModel(Opportunity $opportunity): ?array` — returns `null` only when `external_boq_project_code` is empty (→ empty state); otherwise `{project_code, subtotal, vat_amount, total, status, calibration, synced_at, is_stale, external_url}` (all quote fields nullable until the first successful sync). `showOpportunity()` view data gains `boqCard` (this array or `null`) and `canManageBoq` (bool).

- [ ] **Step 1: Write the failing tests**

In `tests/Feature/Zena/OperatorCrmUiTest.php`, find `test_boq_link_and_sync_ui_flow_for_authorized_tenant` (around line 201) and replace its `Http::fake` block:

```php
        \Illuminate\Support\Facades\Http::fake([
            'https://zena-boq.example/api/external/projects/*' => \Illuminate\Support\Facades\Http::response(['id' => 'proj_1'], 200),
            'https://zena-boq.example/api/external/quotes/latest*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'quote_1', 'subtotal' => 100000000, 'vatAmount' => 8000000, 'total' => 108000000,
                'status' => 'ISSUED', 'calibration' => 'UNCALIBRATED', 'issuedAt' => '2026-07-10T00:00:00Z',
            ], 200),
        ]);
```

with:

```php
        \Illuminate\Support\Facades\Http::fake([
            'https://zena-boq.example/api/external/projects/*' => \Illuminate\Support\Facades\Http::response(['id' => 'proj_1'], 200),
            'https://zena-boq.example/api/external/quotes/latest*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'quote_1', 'revision' => 3, 'subtotal' => 100000000, 'vatAmount' => 8000000, 'total' => 108000000,
                'status' => 'ISSUED', 'calibration' => 'UNCALIBRATED', 'issuedAt' => '2026-07-10T00:00:00Z',
            ], 200),
        ]);
```

Then find the test's final assertion block:

```php
        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertSee('PRJ-001');
    }
```

Replace with (adds badge/link/status assertions):

```php
        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertSee('PRJ-001')
            ->assertSee('Đã phát hành')
            ->assertSee('⚠ Chưa hiệu chỉnh')
            ->assertSee('https://zena-boq.example/quotes/quote_1', false);
    }
```

Now add three new test methods immediately after this test (before `test_boq_card_is_hidden_for_non_authorized_tenant`):

```php
    public function test_boq_card_hides_mutation_actions_for_view_only_user(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $viewOnlyUser = $this->createTenantUser($this->tenant, [], ['staff'], ['crm.view']);

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang xem thoi',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi chi xem',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($viewOnlyUser)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertSee('Chưa liên kết báo giá')
            ->assertDontSee('Liên kết')
            ->assertDontSee('Đồng bộ báo giá');
    }

    public function test_boq_card_flags_synced_quote_older_than_14_days_as_stale(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A', 'zena_boq.base_url' => 'https://zena-boq.example']);

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang bao gia cu',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi bao gia cu',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
            'external_boq_project_code' => 'PRJ-002',
            'external_quote_id' => 'quote_old',
            'external_quote_snapshot' => ['total' => 50000000, 'status' => 'ISSUED', 'calibration' => 'CALIBRATED'],
            'external_quote_synced_at' => now()->subDays(20),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertSee('text-amber-600', false);
    }

    public function test_boq_card_does_not_flag_recently_synced_quote_as_stale(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A', 'zena_boq.base_url' => 'https://zena-boq.example']);

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang bao gia moi',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi bao gia moi',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
            'external_boq_project_code' => 'PRJ-003',
            'external_quote_id' => 'quote_new',
            'external_quote_snapshot' => ['total' => 50000000, 'status' => 'ISSUED', 'calibration' => 'CALIBRATED'],
            'external_quote_synced_at' => now()->subDays(2),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertDontSee('text-amber-600', false);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Zena/OperatorCrmUiTest.php`
Expected: FAIL — `boqCard`/`canManageBoq` view vars don't exist yet; the card still renders the old field-value layout without badges, the external link, or any RBAC gating.

- [ ] **Step 3: Add `buildBoqCardViewModel()` and wire view data**

In `app/Http/Controllers/Web/CrmPageController.php`, find:

```php
    public function showOpportunity(string $id, ZenaBoqIntegrationService $boqService): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $opportunity = Opportunity::query()
            ->forTenant($tenantId)
            ->with('account:id,tenant_id,display_name,phone,email', 'salesOwner:id,name', 'technicalOwner:id,name', 'convertedProject:id,name,code')
            ->findOrFail($id);

        $this->authorize('view', $opportunity);

        return view('crm.opportunity-show', [
            'opportunity' => $opportunity,
            'boqIntegrationEnabled' => $boqService->isTenantAuthorized($tenantId),
            'users' => User::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'events' => \App\Models\EventRecord::query()
                ->where('tenant_id', $tenantId)
                ->where('aggregate_type', 'opportunity')
                ->where('aggregate_id', $id)
                ->with('actor:id,name')
                ->orderByDesc('occurred_at')
                ->limit(20)
                ->get(),
        ]);
    }
```

Replace with:

```php
    public function showOpportunity(string $id, ZenaBoqIntegrationService $boqService): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $opportunity = Opportunity::query()
            ->forTenant($tenantId)
            ->with('account:id,tenant_id,display_name,phone,email', 'salesOwner:id,name', 'technicalOwner:id,name', 'convertedProject:id,name,code')
            ->findOrFail($id);

        $this->authorize('view', $opportunity);

        return view('crm.opportunity-show', [
            'opportunity' => $opportunity,
            'boqIntegrationEnabled' => $boqService->isTenantAuthorized($tenantId),
            'boqCard' => $this->buildBoqCardViewModel($opportunity),
            'canManageBoq' => (bool) auth()->user()?->hasPermission('crm.manage'),
            'users' => User::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'events' => \App\Models\EventRecord::query()
                ->where('tenant_id', $tenantId)
                ->where('aggregate_type', 'opportunity')
                ->where('aggregate_id', $id)
                ->with('actor:id,name')
                ->orderByDesc('occurred_at')
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * @return array{project_code: string, subtotal: ?float, vat_amount: ?float, total: ?float, status: ?string, calibration: ?string, synced_at: ?\Illuminate\Support\Carbon, is_stale: bool, external_url: ?string}|null
     */
    private function buildBoqCardViewModel(Opportunity $opportunity): ?array
    {
        if (!$opportunity->external_boq_project_code) {
            return null;
        }

        $snapshot = $opportunity->external_quote_snapshot ?? [];
        $syncedAt = $opportunity->external_quote_synced_at;
        $baseUrl = rtrim((string) config('zena_boq.base_url'), '/');

        return [
            'project_code' => (string) $opportunity->external_boq_project_code,
            'subtotal' => isset($snapshot['subtotal']) ? (float) $snapshot['subtotal'] : null,
            'vat_amount' => isset($snapshot['vat_amount']) ? (float) $snapshot['vat_amount'] : null,
            'total' => isset($snapshot['total']) ? (float) $snapshot['total'] : null,
            'status' => $snapshot['status'] ?? null,
            'calibration' => $snapshot['calibration'] ?? null,
            'synced_at' => $syncedAt,
            'is_stale' => $syncedAt !== null && $syncedAt->diffInDays(now()) > 14,
            'external_url' => $opportunity->external_quote_id ? "{$baseUrl}/quotes/{$opportunity->external_quote_id}" : null,
        ];
    }
```

- [ ] **Step 4: Redesign the Blade card**

In `resources/views/crm/opportunity-show.blade.php`, find:

```blade
    @if ($boqIntegrationEnabled)
        <x-ui.card title="Báo giá — zena-boq-core">
            @if ($opportunity->external_boq_project_code)
                <div class="operator-form-grid">
                    <x-ui.field-value label="Mã dự án" :value="$opportunity->external_boq_project_code" />
                    <x-ui.field-value label="Trạng thái" :value="$opportunity->external_quote_snapshot['status'] ?? '—'" />
                    <x-ui.field-value label="Hiệu chỉnh giá" :value="$opportunity->external_quote_snapshot['calibration'] ?? '—'" />
                    <x-ui.field-value label="Tổng tiền" :value="isset($opportunity->external_quote_snapshot['total']) ? number_format((float) $opportunity->external_quote_snapshot['total'], 0, ',', '.') . '₫' : '—'" />
                    <x-ui.field-value label="Đồng bộ lần cuối" :value="optional($opportunity->external_quote_synced_at)->format('d/m/Y H:i') ?? 'Chưa đồng bộ'" />
                </div>

                <form method="POST" action="{{ route('operator.crm.opportunities.boq-sync', $opportunity->id) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="operator-button operator-button-primary">Đồng bộ báo giá</button>
                </form>
            @else
                <form method="POST" action="{{ route('operator.crm.opportunities.boq-link', $opportunity->id) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="operator-field flex-1 min-w-64">
                        <label for="external_boq_project_code">Mã dự án zena-boq-core</label>
                        <input id="external_boq_project_code" name="external_boq_project_code" type="text" class="operator-input" value="{{ old('external_boq_project_code') }}" required placeholder="vd: PRJ-001">
                    </div>
                    <button type="submit" class="operator-button operator-button-primary">Liên kết</button>
                </form>
            @endif
        </x-ui.card>
    @endif
```

Replace with:

```blade
    @if ($boqIntegrationEnabled)
        <x-ui.card title="Báo giá — zena-boq-core">
            @if ($boqCard === null)
                <p class="text-sm text-slate-500">Chưa liên kết báo giá.</p>
                @if ($canManageBoq)
                    <form method="POST" action="{{ route('operator.crm.opportunities.boq-link', $opportunity->id) }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        <div class="operator-field flex-1 min-w-64">
                            <label for="external_boq_project_code">Mã dự án zena-boq-core</label>
                            <input id="external_boq_project_code" name="external_boq_project_code" type="text" class="operator-input" value="{{ old('external_boq_project_code') }}" required placeholder="vd: PRJ-001">
                        </div>
                        <button type="submit" class="operator-button operator-button-primary">Liên kết</button>
                    </form>
                @endif
            @else
                <div class="operator-form-grid">
                    <x-ui.field-value label="Mã dự án" :value="$boqCard['project_code']" />
                    <x-ui.field-value label="Trạng thái">
                        @if ($boqCard['status'])
                            <x-ui.status-badge :status="$boqCard['status']" />
                        @else
                            —
                        @endif
                    </x-ui.field-value>
                    <x-ui.field-value label="Hiệu chỉnh giá">
                        @if ($boqCard['calibration'])
                            <x-ui.calibration-badge :status="$boqCard['calibration']" />
                        @else
                            —
                        @endif
                    </x-ui.field-value>
                    <x-ui.field-value label="Tổng tiền" :value="$boqCard['total'] !== null ? number_format($boqCard['total'], 0, ',', '.') . '₫' : '—'" />
                    <x-ui.field-value label="Đồng bộ lần cuối">
                        @if ($boqCard['synced_at'])
                            <span class="{{ $boqCard['is_stale'] ? 'text-amber-600 font-semibold' : '' }}">{{ $boqCard['synced_at']->diffForHumans() }}</span>
                        @else
                            Chưa đồng bộ
                        @endif
                    </x-ui.field-value>
                    @if ($boqCard['external_url'])
                        <x-ui.field-value label="Xem trên zena-boq-core">
                            <a href="{{ $boqCard['external_url'] }}" target="_blank" rel="noopener" class="operator-link">Mở báo giá ↗</a>
                        </x-ui.field-value>
                    @endif
                </div>

                @if ($canManageBoq)
                    <form method="POST" action="{{ route('operator.crm.opportunities.boq-sync', $opportunity->id) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="operator-button operator-button-primary">Đồng bộ báo giá</button>
                    </form>
                @endif
            @endif
        </x-ui.card>
    @endif
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Zena/OperatorCrmUiTest.php`
Expected: PASS (all tests in this file, including the 3 new ones and the extended flow test).

- [ ] **Step 6: Run the full CRM + BOQ test files to confirm no regression**

Run: `php artisan test tests/Feature/Api/CrmApiTest.php tests/Feature/Zena/OperatorCrmUiTest.php tests/Feature/Zena/BoqBadgeComponentsTest.php tests/Unit/ZenaBoqIntegrationServiceTest.php`
Expected: PASS across all four files.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Web/CrmPageController.php resources/views/crm/opportunity-show.blade.php tests/Feature/Zena/OperatorCrmUiTest.php
git commit -m "feat(zena-boq): assemble BOQ card view-model, gate mutation actions by crm.manage, redesign card with badges/stale-flag/external-link"
```

---

### Task 4: Full suite + Deptrac verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass, including this plan's new/modified tests (~2 in Task 2, ~4 new + 1 extended in Task 3 — roughly +6 over the pre-Phase-3 baseline of 1358 passed).

- [ ] **Step 2: Run Deptrac**

Run: `composer deptrac`
Expected: `Violations 0`. This phase touches only `Web\CrmPageController` (already an allowed `WebControllers → Services` edge for `ZenaBoqIntegrationService`, unchanged from Phase 2) and Blade view components (untracked by Deptrac) — no new cross-layer dependency is introduced.

- [ ] **Step 3: Commit (if any fixes were needed in prior steps)**

```bash
git add -A
git commit -m "test(zena-boq): confirm full suite and Deptrac are green for Phase 3"
```

(Skip this commit if step 1-2 required no changes.)

---

## Self-Review Notes

**Spec coverage check** (against `docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md`, Phase 3 section as revised through §8):
- Subtotal/VAT/total, quote-status badge, calibration badge (visually distinct), staleness-flagged sync-time label (14 days), external link (`target="_blank"`, real `/quotes/[id]` route) — all covered by Task 3.
- `revision` captured for Phase 4 — covered by Task 1.
- Empty state ("Chưa liên kết báo giá") with `crm.manage`-gated action — covered by Task 3, extended (per Global Constraints) to also gate the sync button for consistency.
- Source-agnostic view-model shape, assembled by the controller, consumed only via the view-model in the template — covered by Task 3's `buildBoqCardViewModel()`.

**Placeholder scan:** no "TBD"/"TODO"/"add appropriate X" phrases in any step above; every step has complete, real code.

**Type/signature consistency check:** `fetchLatestQuote(string): ?array` and its `revision` key (Task 1) are only ever stored (`OpportunityController::syncExternalQuote()`), never read back out in Task 3 — `buildBoqCardViewModel()` deliberately does not reference `revision`, matching the Global Constraints note that Phase 4 is its consumer. `<x-ui.calibration-badge status="...">` and `<x-ui.status-badge status="...">` (Task 2) are invoked with the same `status` prop name in both Task 3's Blade snippet and Task 2's own test. `boqCard`/`canManageBoq` view variable names are used identically in the controller (Task 3, Step 3) and the Blade template (Task 3, Step 4).
