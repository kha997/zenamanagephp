# Interim Payment Certificates (IPC) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Contract-scoped BOQ with prices + per-period payment certificates (khối lượng kỳ này × đơn giá snapshot, lũy kế derive từ các kỳ đã duyệt), workflow draft→submitted→approved, approve tự sinh `ContractPayment`.

**Architecture:** Extend native `Boq`/`BoqLineItem` (both already `HasUlids`+`TenantScope`) with `contract_id`/`unit_price`; two new models `PaymentCertificate`+`PaymentCertificateLine` following the `SiteDiary` workflow pattern and the `DesignItem` TRANSITIONS pattern; web endpoints in `ContractPageController` per the expense-endpoint pattern; UI cards on `contracts.show`. Spec: `docs/superpowers/specs/2026-07-14-payment-certificates-design.md`.

**Tech Stack:** Laravel 12, PHPUnit, patterns already in-repo (referenced by exact file:line below).

## Global Constraints

- All constraints from `docs/superpowers/plans/2026-07-13-opencode-handoff.md` apply (frozen `src/*`, no bare stash, TDD, conventional commits).
- **PHPStan gate:** `vendor/bin/phpstan analyse --memory-limit=1G` must exit 0 after EVERY task (lesson from handoff-4 Task L). Use `Model::query()->...` everywhere — never magic statics.
- New models: `HasUlids` + `App\Traits\TenantScope` + add to the guard list in `tests/Feature/Models/TenantScopedCrmModelsTest.php`.
- Money `decimal(15,2)`, quantities `decimal(14,3)` (matches `boq_line_items.quantity`).
- Route names: verify prefix with `php artisan route:list | grep contracts` (expected `operator.contracts.*`).
- Web POST tests need `$this->get('/login');` in `setUp()` (CSRF session).
- Cumulative quantities are DERIVED from approved certificates — never stored per line.

---

### Task 1: Contract BOQ data layer

**Files:**
- Create: `database/migrations/2026_07_14_100000_add_contract_id_to_boqs_table.php`
- Create: `database/migrations/2026_07_14_100100_add_unit_price_to_boq_line_items_table.php`
- Modify: `app/Models/Boq.php` (fillable `contract_id`, `contract(): BelongsTo`), `app/Models/BoqLineItem.php` (fillable `unit_price`, cast `'unit_price' => 'float'`), `app/Models/Contract.php` (`boq(): HasOne`)
- Test: `tests/Feature/Models/ContractBoqTest.php`

**Interfaces:**
- Produces: `Contract::boq()` (HasOne `Boq` where contract_id), `Boq::contract()`, `BoqLineItem.unit_price`. Tasks 2-5 rely on these names.

- [ ] **Step 1: Failing test** — `ContractBoqTest`: create tenant/project/contract (copy setup from `tests/Feature/Zena/ContractExpenseEndpointsTest.php`), create `Boq::query()->create([... 'contract_id' => $contract->id])` + one `BoqLineItem` with `unit_price => 1500000`, assert `$contract->boq->lineItems->first()->unit_price == 1500000.0` and that a project-scoped Boq (`contract_id` null) still creates fine. Run → FAIL (unknown column).
- [ ] **Step 2: Migrations** — two standard anonymous-class migrations: `$table->string('contract_id')->nullable()->index()` after `project_id` on `boqs`; `$table->decimal('unit_price', 15, 2)->nullable()` after `unit` on `boq_line_items`. Real `down()` dropping each column.
- [ ] **Step 3: Model edits** — add fillables/casts; `Boq::contract()` = `belongsTo(Contract::class)`; `Contract::boq()` = `hasOne(Boq::class, 'contract_id')`. (`Boq::lineItems()` already exists — verify name with grep; if it is `items()` use that consistently everywhere in this plan.)
- [ ] **Step 4: Run test → PASS; run `php artisan test --filter=Boq` → PASS; phpstan exit 0.**
- [ ] **Step 5: Commit** `feat(boq): contract-scoped BOQ with unit_price (goal-#2 first step)`.

---

### Task 2: PaymentCertificate + line models, permissions, guard

**Files:**
- Create: `database/migrations/2026_07_14_100200_create_payment_certificates_tables.php` (both tables, one migration)
- Create: `app/Models/PaymentCertificate.php`, `app/Models/PaymentCertificateLine.php`
- Modify: `database/seeders/ZenaPermissionsSeeder.php`, `database/seeders/TestDatabaseSeeder.php` (permissions `payment_certificate.view/create/approve` — mirror the `contract.expense.*` lines added 2026-07-13)
- Modify: `tests/Feature/Models/TenantScopedCrmModelsTest.php` (guard both models)
- Test: `tests/Feature/Models/PaymentCertificateTest.php`

**Interfaces (exact, later tasks depend on these):**

```php
class PaymentCertificate extends Model
{
    use HasUlids; use TenantScope;
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SUBMITTED],
        self::STATUS_SUBMITTED => [self::STATUS_APPROVED, self::STATUS_DRAFT],
        self::STATUS_APPROVED => [],
    ];
    // fillable: tenant_id, contract_id, period_no, period_from, period_to,
    //           status, total_this_period, submitted_by, submitted_at, approved_by, approved_at
    // casts: period_from/to date; submitted_at/approved_at datetime; total_this_period float; period_no integer
    public function contract(): BelongsTo;
    public function lines(): HasMany;   // PaymentCertificateLine, orderBy created_at
    public static function canTransition(string $from, string $to): bool;
}
```

`PaymentCertificateLine` fillable: `tenant_id, payment_certificate_id, boq_line_item_id, qty_this_period, unit_price_snapshot, amount_this_period`; casts qty float, prices float; `certificate(): BelongsTo`, `boqLineItem(): BelongsTo`.

Schema: per spec Component 2 — `payment_certificates` unique (`contract_id`,`period_no`); `payment_certificate_lines` unique (`payment_certificate_id`,`boq_line_item_id`); all id/FK columns `string`/`ulid` indexed, tenant_id indexed.

- [ ] Steps: failing model test (trait guard for both classes + create certificate with 1 line + `canTransition` truth table: draft→submitted ok, draft→approved NOT, submitted→draft ok, approved→anything NOT) → migration → models → seeders (grep where `site_diary.approve` is granted, grant the 3 new codes identically) → guard list update → tests PASS → phpstan 0 → commit `feat(certificates): PaymentCertificate models, tables, permissions`.

---

### Task 3: Web endpoints — BOQ lines CRUD + certificate lifecycle

**Files:**
- Modify: `routes/web.php` (inside the operator contracts group, after the expenses routes), `app/Http/Controllers/Web/ContractPageController.php`
- Test: `tests/Feature/Zena/PaymentCertificateFlowTest.php`

**Routes (names exact):**

```php
    Route::post('/contracts/{id}/boq-lines', [...,'storeBoqLine'])->middleware('rbac:contract.update')->name('contracts.boq-lines.store');
    Route::post('/contracts/{id}/boq-lines/{line}/update', [...,'updateBoqLine'])->middleware('rbac:contract.update')->name('contracts.boq-lines.update');
    Route::post('/contracts/{id}/boq-lines/{line}/delete', [...,'deleteBoqLine'])->middleware('rbac:contract.update')->name('contracts.boq-lines.delete');
    Route::post('/contracts/{id}/certificates', [...,'storeCertificate'])->middleware('rbac:payment_certificate.create')->name('contracts.certificates.store');
    Route::get('/contracts/{id}/certificates/{certificate}', [...,'showCertificate'])->middleware('rbac:payment_certificate.view')->name('contracts.certificates.show');
    Route::post('/contracts/{id}/certificates/{certificate}/lines', [...,'saveCertificateLines'])->middleware('rbac:payment_certificate.create')->name('contracts.certificates.lines.save');
    Route::post('/contracts/{id}/certificates/{certificate}/submit', [...,'submitCertificate'])->middleware('rbac:payment_certificate.create')->name('contracts.certificates.submit');
    Route::post('/contracts/{id}/certificates/{certificate}/approve', [...,'approveCertificate'])->middleware('rbac:payment_certificate.approve')->name('contracts.certificates.approve');
```

**Controller behavior (all methods: tenant-scoped `Contract::query()->where('tenant_id',...)->findOrFail($id)` first; certificate fetched `->where('contract_id', $contract->id)` — cross-contract = 404, per the deleteExpense pattern at `ContractPageController::deleteExpense`):**

- `storeBoqLine`: validate code/name required, unit required, quantity numeric gt:0, unit_price numeric gte:0 nullable. First line auto-creates the contract Boq (`Boq::query()->firstOrCreate(['tenant_id'=>..., 'contract_id'=>...], ['project_id'=>$contract->project_id, 'code'=>'BOQ-'.$contract->code, 'name'=>'Bảng khối lượng '.$contract->code])`). **Lock rule** (shared private method `assertBoqUnlocked($contract)`): if any certificate of this contract has status approved → back with error "Bảng khối lượng đã khóa (đã có chứng chỉ được duyệt)." Applies to store/update/delete.
- `deleteBoqLine`: additionally 422/back-with-error when `PaymentCertificateLine::query()->where('boq_line_item_id',$line)->exists()`.
- `storeCertificate`: validate period_from/to dates (`to` after_or_equal `from`); `period_no` = (max period_no for contract) + 1; create draft; redirect to certificate show.
- `saveCertificateLines`: only when status draft (else back error). Input: array `lines[<boq_line_item_id>] = qty` (nullable/0 = remove line). For each qty > 0: upsert the certificate line with `unit_price_snapshot` = current BOQ line unit_price (fallback 0), `amount_this_period` = qty × snapshot. Recompute and save `total_this_period`. Wrap the whole thing in `DB::transaction`.
- `submitCertificate`: transition check via `canTransition` (else back error); set status, submitted_by/at.
- `approveCertificate`: transition check; inside `DB::transaction`: recompute total from lines, set approved fields, create `ContractPayment::query()->create(['tenant_id'=>..., 'contract_id'=>..., 'name'=>'Nghiệm thu KL kỳ '.$cert->period_no, 'amount'=>$cert->total_this_period, 'status'=>ContractPayment::STATUS_PLANNED, 'due_date'=>now()->addDays(14)])`, write `EventRecord` (copy the create-block shape from `Api\DesignItemController::updateStatus`, `aggregate_type` `payment_certificate`, `event_key` `payment_certificate.approved`, payload `['period_no'=>..., 'total'=>...]`).

**Test (`PaymentCertificateFlowTest`, setup = ContractExpenseEndpointsTest + construction contract + 2 BOQ lines qty 100/50 price 200000/1000000):**
1. Happy path: create cert kỳ 1 → save lines (30, 10) → total = 30×200000 + 10×1000000 = 16.000.000 → submit → approve → assert `contract_payments` has "Nghiệm thu KL kỳ 1" amount 16000000 planned; assert cert approved.
2. Cumulative: approve kỳ 1 (30), create kỳ 2, save line qty 80 → page shows lũy kế 30, kỳ này 80, cảnh báo vượt (30+80 > 100) — assert warning text present, and save SUCCEEDS (warning not block).
3. Snapshot: after kỳ-1 approve, try updateBoqLine → error (locked); change attempt leaves kỳ-1 line amount unchanged.
4. period_no unique: creating two certs concurrently-ish → second gets period_no 2 (not a 500).
5. Transitions: approve a draft directly → error; edit lines of submitted → error.
6. Cross-tenant cert show → 404; user without `payment_certificate.approve` cannot approve.

- [ ] Steps: failing test → routes → controller methods → PASS → `php artisan test --filter=Contract` PASS → phpstan 0 → commit `feat(certificates): certificate lifecycle endpoints + contract BOQ CRUD with lock rule`.

---

### Task 4: Cumulative computation helper

**Files:**
- Create: `app/Services/PaymentCertificateSummaryService.php`
- Test: `tests/Unit/Services/PaymentCertificateSummaryServiceTest.php` (Feature-style with RefreshDatabase is fine if Unit dir lacks DB setup — place where sibling service tests live; check `tests/` first)

**Interface (Task 5 consumes this exactly):**

```php
final class PaymentCertificateSummaryService
{
    /**
     * @return array<string, array{contract_qty: float, unit_price: float|null,
     *   prev_qty: float, this_qty: float, remaining_qty: float, percent_done: float,
     *   over_quantity: bool, amount_this_period: float}>  keyed by boq_line_item_id
     */
    public function lineSummaries(PaymentCertificate $certificate): array;
}
```

`prev_qty` = sum of `qty_this_period` over lines of APPROVED certificates of the same contract with `period_no < $certificate->period_no`. `this_qty` from the certificate's own lines (0 when absent). `percent_done` = (prev+this)/contract_qty×100 (0 when contract_qty 0). `over_quantity` = prev+this > contract_qty. One query per collection — no N+1 (fetch all approved lines for the contract in one go, group in PHP).

- [ ] Steps: failing test (2 approved periods + 1 draft → assert exact numbers incl. over_quantity flag and zero-qty edge) → implement → PASS → phpstan 0 → commit `feat(certificates): cumulative summary service`.

---

### Task 5: UI on contracts.show + certificate detail page

**Files:**
- Modify: `ContractPageController::show()` (load `boq.lineItems` + certificates list when construction), `showCertificate()` (pass `lineSummaries`)
- Create: `resources/views/contracts/certificate-show.blade.php`
- Modify: `resources/views/contracts/show.blade.php` (2 cards, construction-type only, after the progress block)
- Test: extend `PaymentCertificateFlowTest` with page-render assertions

**Cards on contracts.show (construction only):** "Bảng khối lượng HĐ" — line table (mã, tên, ĐVT, KL HĐ, đơn giá, thành tiền = qty×price) + inline add form + per-line update/delete forms (hidden khi locked, hiện chú thích khóa); "Nghiệm thu khối lượng" — certificate rows (Kỳ N, from→to, `number_format(total)`, status badge, link detail) + create form (2 date inputs).

**certificate-show.blade.php:** header (contract code, Kỳ N, status badge, period); line table from `lineSummaries` — KL HĐ / lũy kế trước / **kỳ này** (input khi draft + user has `payment_certificate.create`, plain text otherwise) / còn lại / % / đơn giá / thành tiền, row class `bg-amber-50` + badge "Vượt KL" when `over_quantity`; footer tổng; action buttons: save lines (draft), submit (draft), approve (submitted + permission). All forms `@csrf`, POST to the Task-3 route names. Follow `x-ui.card`/`operator-input` idioms as in `resources/views/contracts/show.blade.php`.

- [ ] Steps: failing render assertions (page shows "Bảng khối lượng HĐ", "Nghiệm thu khối lượng", "Vượt KL" warning in the cumulative test, "16,000,000") → controller data → blades → PASS → phpstan 0 → commit `feat(certificates): contract BOQ and certificate UI`.

---

### Task 6: Final verification

- [ ] `php artisan test tests/Feature/Architecture/` → PASS
- [ ] `php artisan test --testsuite=Feature` → PASS (record the new baseline count)
- [ ] `vendor/bin/phpstan analyse --memory-limit=1G` → exit 0
- [ ] Report the three numbers. Do not merge; push only.

## Self-review notes

- Spec coverage: C1→T1, C2→T2, C3→T3(+approve side-effects), cumulative→T4, C4→T5; every error-handling rule in the spec has an explicit test in T3.
- Type consistency: status constants/TRANSITIONS mirror DesignItem verbatim; service return shape declared once in T4 and consumed in T5; route names declared once in T3 and used in T5 blades.
- Deferred items (retention, PDF, import, variations) appear nowhere in tasks — YAGNI held.
