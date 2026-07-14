# Contract-Centric Management (R-CTR) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Contracts become the organizing spine: typed (design/construction/other), each with a progress block matching its type, and a thu–chi finance block (payments rollup + manual expenses + auto material cost); plus a per-project contracts rollup card.

**Architecture:** Additive. One `contract_type` column; one new `ContractExpense` model/table (manual chi, 4 categories — materials excluded to avoid double-counting the existing receipt-based auto material cost from `Api\ContractController::costSummary()`); web endpoints written directly in `Web\ContractPageController` (tenant-scoped + rbac, same as its siblings); Blade blocks on `contracts.show` and `projects.show`. Spec: `docs/superpowers/specs/2026-07-13-contract-centric-management-design.md`.

**Tech Stack:** Laravel 12, PHPUnit, `TenantUserFactoryTrait`, operator Blade components (`x-ui.card`, `x-ui.status-badge`, `x-ui.field-value`, `operator-input`/`operator-select` classes).

## Global Constraints

- **PREREQUISITE: the R-DPM plan (`docs/superpowers/plans/2026-07-13-design-pm-completion.md`) must be fully implemented first.** Task 5 here extracts the R-DPM project design section into a shared partial; blocker fields and `revision_count` must exist.
- Never touch `src/*` or `/api/v1/*` mounted compatibility surfaces beyond the two App-owned controllers named here (`Api\ContractController` is App-owned per SSOT — extending its validation is in-policy; do not re-home its routes).
- New tenant-owned tables/models get `HasUlids` + `App\Traits\TenantScope` + an entry in the `TenantScopedCrmModelsTest` guard list.
- Web mutations that are review/contract business logic delegate to Api controllers (existing pattern); expense create/delete are operational metadata written directly in the Web controller with tenant-scoped `findOrFail` + route-level `rbac:*`.
- Money display: `number_format((float) $value)` + contract `currency`, matching `resources/views/contracts/show.blade.php:38`.
- Migration style: anonymous class, `declare(strict_types=1)`, real `down()`.
- Tests: `declare(strict_types=1)`, `RefreshDatabase`, alias `rbac` middleware in `setUp()` like `tests/Feature/Api/CrmApiTest.php`. Route names: verify prefixes with `php artisan route:list | grep contracts` and reuse whatever prefix the sibling `contracts.show` uses.
- Run `php artisan test tests/Feature/Architecture/` before the final commit.

---

### Task 1: `contract_type` column + form + badges

**Files:**
- Create: `database/migrations/2026_07_13_110000_add_contract_type_to_contracts_table.php`
- Modify: `app/Models/Contract.php` (constants + fillable)
- Modify: `app/Http/Controllers/Web/ContractPageController.php:63-73` (store validation)
- Modify: `app/Http/Controllers/Api/ContractController.php:120-134` (store validation)
- Modify: `resources/views/contracts/create.blade.php`, `resources/views/contracts/index.blade.php`, `resources/views/contracts/show.blade.php`
- Test: `tests/Feature/Zena/ContractTypeTest.php`

**Interfaces:**
- Produces: `contracts.contract_type` string default `'other'`; `Contract::TYPE_DESIGN = 'design'`, `TYPE_CONSTRUCTION = 'construction'`, `TYPE_OTHER = 'other'`, `VALID_TYPES` array; Blade label helper `Contract::typeLabel(): string`. Tasks 4-6 rely on these names.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Zena/ContractTypeTest.php` — setup: tenant + user with `['contract.view', 'contract.create', 'project.view']`, one project (copy the setup of the existing passing contract page test — find it via `grep -rln "contracts.store\|ContractPage" tests/Feature | head`). Tests:

```php
    public function test_contract_created_with_design_type(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->actingAs($this->user)->get(route('operator.contracts.create'), $headers)->assertOk();

        $this->actingAs($this->user)->post(route('operator.contracts.store'), [
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-TK-01',
            'title' => 'HĐ thiết kế nhà phố',
            'contract_type' => 'design',
            'total_value' => 500000000,
        ], $headers)->assertRedirect();

        $this->assertDatabaseHas('contracts', ['code' => 'CTR-TK-01', 'contract_type' => 'design']);
    }

    public function test_contract_type_defaults_to_other_and_rejects_invalid(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->actingAs($this->user)->get(route('operator.contracts.create'), $headers)->assertOk();

        $this->actingAs($this->user)->post(route('operator.contracts.store'), [
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-XX-01',
            'title' => 'HĐ chưa phân loại',
        ], $headers)->assertRedirect();
        $this->assertDatabaseHas('contracts', ['code' => 'CTR-XX-01', 'contract_type' => 'other']);

        $this->actingAs($this->user)->post(route('operator.contracts.store'), [
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-XX-02',
            'title' => 'Loại sai',
            'contract_type' => 'bogus',
        ], $headers)->assertSessionHasErrors('contract_type');
    }
```

- [ ] **Step 2: Run to verify failure** — `php artisan test tests/Feature/Zena/ContractTypeTest.php` → FAIL (unknown column / no validation).

- [ ] **Step 3: Migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('contract_type')->default('other')->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('contract_type');
        });
    }
};
```

- [ ] **Step 4: Model constants + fillable**

In `app/Models/Contract.php`, next to the STATUS constants (line ~91):

```php
    public const TYPE_DESIGN = 'design';
    public const TYPE_CONSTRUCTION = 'construction';
    public const TYPE_OTHER = 'other';

    public const VALID_TYPES = [
        self::TYPE_DESIGN,
        self::TYPE_CONSTRUCTION,
        self::TYPE_OTHER,
    ];

    public function typeLabel(): string
    {
        return match ($this->contract_type) {
            self::TYPE_DESIGN => 'Thiết kế',
            self::TYPE_CONSTRUCTION => 'Thi công',
            default => 'Khác',
        };
    }
```

Add `'contract_type',` to `$fillable` after `'title',`.

- [ ] **Step 5: Validation both layers**

`Web\ContractPageController::store()` rules array — add after the `'title'` line:

```php
            'contract_type' => ['nullable', 'in:design,construction,other'],
```

`Api\ContractController::store()` validator — add after its `'title'` line:

```php
            'contract_type' => ['nullable', Rule::in(Contract::VALID_TYPES)],
```

- [ ] **Step 6: Form select + badges**

`resources/views/contracts/create.blade.php` — after the `title` input's field block (line ~82):

```blade
                <div>
                    <label for="contract_type">Loại hợp đồng</label>
                    <select id="contract_type" name="contract_type" class="operator-select">
                        <option value="design" @selected(old('contract_type') === 'design')>Thiết kế</option>
                        <option value="construction" @selected(old('contract_type') === 'construction')>Thi công</option>
                        <option value="other" @selected(old('contract_type', 'other') === 'other')>Khác</option>
                    </select>
                </div>
```

`contracts/show.blade.php` — inside the "Thông tin hợp đồng" card add:

```blade
                <x-ui.field-value label="Loại hợp đồng" :value="$contract->typeLabel()" />
```

`contracts/index.blade.php` — next to each row's status badge (locate the `<x-ui.status-badge` usage), add:

```blade
                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ $contract->typeLabel() }}</span>
```

- [ ] **Step 7: Run tests + regression + commit**

`php artisan test tests/Feature/Zena/ContractTypeTest.php && php artisan test --filter=Contract` → PASS.

```bash
git add database/migrations/2026_07_13_110000_add_contract_type_to_contracts_table.php app/Models/Contract.php \
        app/Http/Controllers/Web/ContractPageController.php app/Http/Controllers/Api/ContractController.php \
        resources/views/contracts/ tests/Feature/Zena/ContractTypeTest.php
git commit -m "feat(contracts): add contract_type (design/construction/other) with form and badges"
```

---

### Task 2: ContractExpense data layer + permissions

**Files:**
- Create: `database/migrations/2026_07_13_110100_create_contract_expenses_table.php`
- Create: `app/Models/ContractExpense.php`
- Modify: `app/Models/Contract.php` (`expenses()` relation)
- Modify: `database/seeders/ZenaPermissionsSeeder.php`, `database/seeders/TestDatabaseSeeder.php`
- Test: extend `tests/Feature/Models/TenantScopedCrmModelsTest.php` guard list; create `tests/Feature/Models/ContractExpenseTest.php`

**Interfaces:**
- Produces: `App\Models\ContractExpense` (`contract_id`, `expense_date` date, `amount` decimal, `category` in `['labor','subcontractor','design_outsource','misc']`, `description`, `recorded_by`); `ContractExpense::VALID_CATEGORIES`; `ContractExpense::categoryLabel(): string`; `Contract::expenses(): HasMany`; permissions `contract.expense.view/create/delete`.

- [ ] **Step 1: Failing test** — `tests/Feature/Models/ContractExpenseTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\Project;
use App\Models\Tenant;
use App\Traits\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ContractExpenseTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_expense_uses_tenant_scope_and_belongs_to_contract(): void
    {
        $this->assertContains(TenantScope::class, class_uses_recursive(ContractExpense::class));

        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], []);
        $project = Project::factory()->create(['tenant_id' => (string) $tenant->id]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-EXP-01',
            'title' => 'HĐ test chi',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'created_by' => (string) $user->id,
        ]);

        $expense = ContractExpense::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'expense_date' => '2026-07-13',
            'amount' => 15000000,
            'category' => 'labor',
            'description' => 'Nhân công tháo dỡ',
            'recorded_by' => (string) $user->id,
        ]);

        $this->assertSame('CTR-EXP-01', $expense->contract->code);
        $this->assertSame(1, $contract->expenses()->count());
        $this->assertSame('Nhân công', $expense->categoryLabel());
    }
}
```

(If `Contract::create` needs more NOT NULL columns, copy the creation attributes from an existing passing Contract test.)

- [ ] **Step 2: Run to verify failure** — class not found.

- [ ] **Step 3: Migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->index();
            $table->string('contract_id')->index();
            $table->date('expense_date');
            $table->decimal('amount', 15, 2);
            $table->string('category');
            $table->string('description', 1000);
            $table->string('recorded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_expenses');
    }
};
```

- [ ] **Step 4: Model + relation**

`app/Models/ContractExpense.php`:

```php
<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manual expense line on a contract ("chi"). Material cost is NOT entered
 * here — it is computed automatically from MaterialReceiptLine via
 * Api\ContractController::costSummary(); a manual materials category
 * would double-count it.
 */
class ContractExpense extends Model
{
    use HasUlids;
    use TenantScope;

    public const CATEGORY_LABOR = 'labor';
    public const CATEGORY_SUBCONTRACTOR = 'subcontractor';
    public const CATEGORY_DESIGN_OUTSOURCE = 'design_outsource';
    public const CATEGORY_MISC = 'misc';

    public const VALID_CATEGORIES = [
        self::CATEGORY_LABOR,
        self::CATEGORY_SUBCONTRACTOR,
        self::CATEGORY_DESIGN_OUTSOURCE,
        self::CATEGORY_MISC,
    ];

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'expense_date',
        'amount',
        'category',
        'description',
        'recorded_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'float',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            self::CATEGORY_LABOR => 'Nhân công',
            self::CATEGORY_SUBCONTRACTOR => 'Thầu phụ',
            self::CATEGORY_DESIGN_OUTSOURCE => 'Thuê ngoài thiết kế',
            default => 'Khác',
        };
    }
}
```

In `app/Models/Contract.php` next to `payments()` (line ~119), plus the `HasMany` import if missing:

```php
    public function expenses(): HasMany
    {
        return $this->hasMany(ContractExpense::class);
    }
```

- [ ] **Step 5: Permissions**

`database/seeders/ZenaPermissionsSeeder.php` — after the `contract.payment.*` lines (~line 105):

```php
        ['code' => 'contract.expense.view', 'module' => 'contract', 'action' => 'expense.view', 'description' => 'View contract expenses'],
        ['code' => 'contract.expense.create', 'module' => 'contract', 'action' => 'expense.create', 'description' => 'Create contract expenses'],
        ['code' => 'contract.expense.delete', 'module' => 'contract', 'action' => 'expense.delete', 'description' => 'Delete contract expenses'],
```

`database/seeders/TestDatabaseSeeder.php` line ~59 — extend the list with `'contract.expense.view', 'contract.expense.create', 'contract.expense.delete',`. Then run `grep -rn "contract.payment.view" database/seeders/` — anywhere payments are granted to a role, grant the three expense codes identically.

- [ ] **Step 6: Guard + run + commit**

Add `ContractExpense::class` to the guard list in `TenantScopedCrmModelsTest` (+ import).
`php artisan test tests/Feature/Models/ContractExpenseTest.php tests/Feature/Models/TenantScopedCrmModelsTest.php` → PASS.

```bash
git add database/migrations/2026_07_13_110100_create_contract_expenses_table.php app/Models/ContractExpense.php \
        app/Models/Contract.php database/seeders/ZenaPermissionsSeeder.php database/seeders/TestDatabaseSeeder.php \
        tests/Feature/Models/ContractExpenseTest.php tests/Feature/Models/TenantScopedCrmModelsTest.php
git commit -m "feat(contracts): add ContractExpense model, table and contract.expense.* permissions"
```

---

### Task 3: Expense create/delete endpoints

**Files:**
- Modify: `routes/web.php` (2 routes next to `contracts.show`, line ~906)
- Modify: `app/Http/Controllers/Web/ContractPageController.php`
- Test: `tests/Feature/Zena/ContractExpenseEndpointsTest.php`

**Interfaces:**
- Consumes: `ContractExpense` (Task 2), rbac codes `contract.expense.create` / `contract.expense.delete`.
- Produces: route names `<prefix>.contracts.expenses.store` and `<prefix>.contracts.expenses.delete` (POST-with-verb pattern), used by Task 4's forms.

- [ ] **Step 1: Failing test** — `tests/Feature/Zena/ContractExpenseEndpointsTest.php`: setup as Task 2's test plus `createTenantUser` with `['contract.view', 'contract.expense.view', 'contract.expense.create', 'contract.expense.delete']`. Tests: (a) store happy path asserts `assertDatabaseHas('contract_expenses', [...])`; (b) store with `amount => -5` or missing `category` → `assertSessionHasErrors`; (c) store by a user WITHOUT `contract.expense.create` → not created (assert redirect/403 per the app's rbac web behavior — copy the assertion style of an existing rbac-denied web test, e.g. in `OperatorCrmUiTest`); (d) cross-tenant store → 404; (e) delete clears the row; (f) delete with an expense id belonging to a different contract → 404 and the row remains.

- [ ] **Step 2: Run to verify failure** — routes missing.

- [ ] **Step 3: Routes** (next to `contracts.show`, same group so the name prefix matches):

```php
    Route::post('/contracts/{id}/expenses', [App\Http\Controllers\Web\ContractPageController::class, 'storeExpense'])->middleware('rbac:contract.expense.create')->name('contracts.expenses.store');
    Route::post('/contracts/{id}/expenses/{expense}/delete', [App\Http\Controllers\Web\ContractPageController::class, 'deleteExpense'])->middleware('rbac:contract.expense.delete')->name('contracts.expenses.delete');
```

- [ ] **Step 4: Controller methods** (add `use App\Models\ContractExpense;`):

```php
    public function storeExpense(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'category' => ['required', \Illuminate\Validation\Rule::in(ContractExpense::VALID_CATEGORIES)],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $tenantId = (string) auth()->user()?->tenant_id;
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        ContractExpense::query()->create([
            'tenant_id' => $tenantId,
            'contract_id' => (string) $contract->id,
            'expense_date' => $validated['expense_date'],
            'amount' => (float) $validated['amount'],
            'category' => $validated['category'],
            'description' => $validated['description'],
            'recorded_by' => (string) auth()->id(),
        ]);

        return back()->with('success', 'Đã ghi khoản chi.');
    }

    public function deleteExpense(string $id, string $expense): RedirectResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;
        $contract = Contract::query()->where('tenant_id', $tenantId)->findOrFail($id);

        ContractExpense::query()
            ->where('tenant_id', $tenantId)
            ->where('contract_id', (string) $contract->id)
            ->findOrFail($expense)
            ->delete();

        return back()->with('success', 'Đã xóa khoản chi.');
    }
```

- [ ] **Step 5: Run + commit**

`php artisan test tests/Feature/Zena/ContractExpenseEndpointsTest.php` → PASS.

```bash
git add routes/web.php app/Http/Controllers/Web/ContractPageController.php tests/Feature/Zena/ContractExpenseEndpointsTest.php
git commit -m "feat(contracts): add expense create/delete endpoints with rbac"
```

---

### Task 4: Finance block on contracts.show

**Files:**
- Modify: `Web\ContractPageController::show()` (line ~100)
- Modify: `resources/views/contracts/show.blade.php` (replace the "Tổng hợp chi phí" card, line ~33-44)
- Test: `tests/Feature/Zena/ContractFinanceViewTest.php`

**Interfaces:**
- Consumes: `Contract::payments()`, `Contract::expenses()`, existing `$summary` (auto material cost, key `priced_line_cost_total`), route names from Task 3.
- Produces: view variables `$payments`, `$expenses`, `$finance` array: `['total_value', 'paid_total', 'remaining', 'overdue_count', 'manual_expense_total', 'material_cost_total' (nullable), 'expense_total', 'balance']`.

- [ ] **Step 1: Failing test** — `tests/Feature/Zena/ContractFinanceViewTest.php`: seed a contract (`total_value` 1,000,000,000) with payments: paid 300,000,000 (`status` paid, `paid_at` set), planned-not-due 200,000,000 (`due_date` next month), planned-overdue 100,000,000 (`due_date` last week); expenses: labor 50,000,000 + misc 10,000,000. GET `contracts.show` asserts: `assertSee('700.000.000', false)` (còn phải thu = 1tỷ − 300tr)… use exact `number_format` outputs; assert 'Quá hạn' with count 1; assert 'Nhân công' and the manual total '60.000.000'; assert the balance line ('Đã thu − đã chi').

- [ ] **Step 2: Run to verify failure.**

- [ ] **Step 3: Controller** — in `show()`, after the `$contract` fetch add:

```php
        $payments = $contract->payments()->orderBy('due_date')->get();
        $expenses = $contract->expenses()->orderBy('expense_date')->get();

        $paidTotal = (float) $payments->where('status', \App\Models\ContractPayment::STATUS_PAID)->sum('amount');
        $manualExpenseTotal = (float) $expenses->sum('amount');
        $materialCostTotal = $summary !== null ? (float) data_get($summary, 'priced_line_cost_total', 0) : null;

        $finance = [
            'total_value' => (float) $contract->total_value,
            'paid_total' => $paidTotal,
            'remaining' => (float) $contract->total_value - $paidTotal,
            'overdue_count' => $payments
                ->where('status', '!=', \App\Models\ContractPayment::STATUS_PAID)
                ->filter(fn ($p) => $p->due_date !== null && $p->due_date->isPast())
                ->count(),
            'manual_expense_total' => $manualExpenseTotal,
            'material_cost_total' => $materialCostTotal,
            'expense_total' => $manualExpenseTotal + ($materialCostTotal ?? 0.0),
            'balance' => $paidTotal - ($manualExpenseTotal + ($materialCostTotal ?? 0.0)),
        ];
```

(Note: `$summary` is computed ABOVE this insertion point in the existing method — insert after the try/catch that sets it.) Pass `'payments' => $payments, 'expenses' => $expenses, 'finance' => $finance,` into the view data.

- [ ] **Step 4: Blade** — replace the existing "Tổng hợp chi phí" card with a "Tài chính hợp đồng" card containing: (a) rollup `x-ui.field-value` rows — Tổng giá trị HĐ / Đã thu / Còn phải thu / Quá hạn (`{{ $finance['overdue_count'] }} đợt`); (b) a payments table (Tên đợt, Số tiền, Hạn thu, Trạng thái via `x-ui.status-badge`, Ngày thu); (c) an expenses table (Ngày, Nhóm via `categoryLabel()`, Diễn giải, Số tiền, nút Xóa = POST form to `contracts.expenses.delete`, shown `@if(auth()->user()?->hasPermission('contract.expense.delete'))`) + an inline add form (date, select 4 categories, description, amount → POST `contracts.expenses.store`, shown behind `contract.expense.create`); (d) the auto line: `@if($finance['material_cost_total'] !== null)` "Chi vật tư theo phiếu nhận (tự động): {{ number_format($finance['material_cost_total']) }}" `@else` "Chi vật tư tự động: không tải được — chưa tính vào tổng chi" `@endif`; (e) totals: Tổng chi / **Đã thu − đã chi: {{ number_format($finance['balance']) }}**. All money `number_format((float) ...)` + `{{ $contract->currency }}`.

- [ ] **Step 5: Run + regression + commit**

`php artisan test tests/Feature/Zena/ContractFinanceViewTest.php && php artisan test --filter=Contract` → PASS.

```bash
git add app/Http/Controllers/Web/ContractPageController.php resources/views/contracts/show.blade.php tests/Feature/Zena/ContractFinanceViewTest.php
git commit -m "feat(contracts): finance block — payment rollups, manual expenses, auto material cost, balance"
```

---

### Task 5: Progress block per contract type

**Files:**
- Create: `resources/views/projects/_design-progress.blade.php` (extracted from the R-DPM section)
- Modify: `resources/views/projects/show.blade.php` (replace inline section with `@include`)
- Modify: `Web\ContractPageController::show()` + `resources/views/contracts/show.blade.php`
- Test: `tests/Feature/Zena/ContractProgressViewTest.php`

**Interfaces:**
- Consumes: R-DPM's `$designItems`/`$blockedItems` shapes and its Blade markup (extract verbatim); `QcInspection`, `Ncr`, `MaterialReceipt` models; `$project->tasks` with assignee.
- Produces: partial `projects/_design-progress.blade.php` accepting `$designItems`, `$blockedItems`, `$tasks` (nullable — the tasks sub-block renders only when passed).

- [ ] **Step 1: Failing test** — `tests/Feature/Zena/ContractProgressViewTest.php`: three tests — design-type contract page shows a seeded DesignItem name + 'Sửa lần' badge context; construction-type contract page shows a seeded Task title and the counts row ('Nghiệm thu', 'NCR', 'Phiếu nhận vật tư'); other-type page shows 'Hợp đồng chưa phân loại'.

- [ ] **Step 2: Run to verify failure.**

- [ ] **Step 3: Extract the partial** — move the R-DPM "Thiết kế & tiến độ" card markup from `projects/show.blade.php` into `projects/_design-progress.blade.php` unchanged, with the tasks sub-block wrapped in `@if(($tasks ?? null) !== null)`. In `projects/show.blade.php` replace it with:

```blade
    @include('projects._design-progress', ['designItems' => $designItems, 'blockedItems' => $blockedItems, 'tasks' => $project->tasks])
```

Run the R-DPM page test (`tests/Feature/Zena/ProjectDesignSectionTest.php`) immediately — it must still PASS before continuing.

- [ ] **Step 4: Contract controller branch** — in `ContractPageController::show()` add (after the finance block):

```php
        $progress = ['type' => (string) $contract->contract_type];

        if ($contract->contract_type === Contract::TYPE_DESIGN) {
            $designItems = \App\Models\DesignItem::query()
                ->where('project_id', (string) $contract->project_id)
                ->with('assignee:id,name')
                ->orderBy('created_at')
                ->get();

            $progress['designItems'] = $designItems;
            $progress['blockedItems'] = $designItems->whereNotNull('blocked_at')->map(fn ($i) => [
                'type' => 'Hạng mục thiết kế', 'name' => $i->name, 'note' => $i->blocker_note, 'blocked_at' => $i->blocked_at,
            ])->values();
        }

        if ($contract->contract_type === Contract::TYPE_CONSTRUCTION) {
            $progress['tasks'] = \App\Models\Task::query()
                ->where('project_id', (string) $contract->project_id)
                ->with('assignee:id,name')
                ->get();
            $progress['inspectionCount'] = \App\Models\QcInspection::query()
                ->where('tenant_id', $tenantId)->where('project_id', (string) $contract->project_id)->count();
            $progress['openNcrCount'] = \App\Models\Ncr::query()
                ->where('tenant_id', $tenantId)->where('project_id', (string) $contract->project_id)
                ->where('status', '!=', 'closed')->count();
            $progress['receiptCount'] = \App\Models\MaterialReceipt::query()
                ->where('tenant_id', $tenantId)->where('contract_id', (string) $contract->id)->count();
        }
```

(Verify the `Ncr` status column/value with `grep -n "const\|closed" app/Models/Ncr.php` — if its closed-state constant differs, use the model's constant.) Pass `'progress' => $progress` to the view.

- [ ] **Step 5: Blade branch** in `contracts/show.blade.php` after the finance card:

```blade
    @if ($progress['type'] === 'design')
        @include('projects._design-progress', ['designItems' => $progress['designItems'], 'blockedItems' => $progress['blockedItems']])
    @elseif ($progress['type'] === 'construction')
        <x-ui.card title="Tiến độ thi công">
            <div class="mb-3 flex gap-4 text-sm text-slate-600">
                <span>Nghiệm thu: {{ $progress['inspectionCount'] }}</span>
                <span>NCR đang mở: {{ $progress['openNcrCount'] }}</span>
                <span>Phiếu nhận vật tư: {{ $progress['receiptCount'] }}</span>
            </div>
            @forelse ($progress['tasks'] as $task)
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 py-2 text-sm">
                    <span class="font-medium">{{ $task->title ?? $task->name }}</span>
                    <x-ui.status-badge :status="$task->status" />
                    @if ($task->blocked_at)<span class="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Vướng</span>@endif
                    <span class="text-slate-500">{{ $task->assignee?->name ?? 'Chưa giao' }}</span>
                    <span class="text-slate-400">{{ (int) $task->progress_percent }}%</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">Chưa có công việc.</p>
            @endforelse
        </x-ui.card>
    @else
        <x-ui.card title="Tiến độ">
            <p class="text-sm text-slate-500">Hợp đồng chưa phân loại — chọn loại hợp đồng để xem tiến độ tương ứng.</p>
        </x-ui.card>
    @endif
```

- [ ] **Step 6: Run + commit**

`php artisan test tests/Feature/Zena/ContractProgressViewTest.php tests/Feature/Zena/ProjectDesignSectionTest.php` → PASS.

```bash
git add resources/views/projects/_design-progress.blade.php resources/views/projects/show.blade.php \
        resources/views/contracts/show.blade.php app/Http/Controllers/Web/ContractPageController.php \
        tests/Feature/Zena/ContractProgressViewTest.php
git commit -m "feat(contracts): type-specific progress block (design partial reuse / construction counts)"
```

---

### Task 6: Project rollup card + final verification

**Files:**
- Modify: `Web\ProjectController::show()` and `resources/views/projects/show.blade.php`
- Test: `tests/Feature/Zena/ProjectContractsRollupTest.php`

- [ ] **Step 1: Failing test** — seed a project with a design contract (500tr, paid 200tr, chi 50tr) and a construction contract (2tỷ, paid 0); assert the page shows 'Hợp đồng & tài chính', both codes, both type labels, '200.000.000' (đã thu), and the totals row '2.500.000.000'.

- [ ] **Step 2: Run to verify failure.**

- [ ] **Step 3: Controller** — in `Web\ProjectController::show()` add:

```php
            $contracts = \App\Models\Contract::query()
                ->where('project_id', (string) $project->id)
                ->withSum(['payments as paid_total' => fn ($q) => $q->where('status', \App\Models\ContractPayment::STATUS_PAID)], 'amount')
                ->withSum('expenses as expense_total', 'amount')
                ->orderBy('created_at')
                ->get();
```

Pass `'contracts' => $contracts` to the view.

- [ ] **Step 4: Blade** — after the R-DPM include on `projects/show.blade.php`:

```blade
    <x-ui.card title="Hợp đồng & tài chính">
        @forelse ($contracts as $contract)
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 py-2 text-sm">
                <a href="{{ route('operator.contracts.show', $contract->id) }}" class="font-medium">{{ $contract->code }}</a>
                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ $contract->typeLabel() }}</span>
                <x-ui.status-badge :status="$contract->status" />
                <span>Giá trị: {{ number_format((float) $contract->total_value) }}</span>
                <span class="text-emerald-700">Đã thu: {{ number_format((float) ($contract->paid_total ?? 0)) }}</span>
                <span class="text-red-700">Đã chi: {{ number_format((float) ($contract->expense_total ?? 0)) }}</span>
                <span class="text-slate-500">Còn phải thu: {{ number_format((float) $contract->total_value - (float) ($contract->paid_total ?? 0)) }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-500">Chưa có hợp đồng.</p>
        @endforelse
        @if ($contracts->isNotEmpty())
            <div class="mt-2 pt-2 border-t border-slate-200 text-sm font-medium">
                Tổng: {{ number_format((float) $contracts->sum('total_value')) }}
                · Đã thu {{ number_format((float) $contracts->sum('paid_total')) }}
                · Đã chi {{ number_format((float) $contracts->sum('expense_total')) }}
            </div>
        @endif
    </x-ui.card>
```

(Adjust the `operator.` route-name prefix to the actual `contracts.show` name.)

- [ ] **Step 5: Full verification**

```bash
php artisan test tests/Feature/Zena/ProjectContractsRollupTest.php
php artisan test tests/Feature/Architecture/
php artisan test --testsuite=Feature
```

Expected: all PASS (timing-threshold flakes: re-run once). Commit:

```bash
git add app/Http/Controllers/Web/ProjectController.php resources/views/projects/show.blade.php tests/Feature/Zena/ProjectContractsRollupTest.php
git commit -m "feat(projects): contracts & finance rollup card on project page"
```

Report results; do not push or merge without the user's go-ahead.

---

## Self-review notes

- Spec coverage: Component 1 → Task 1; Component 2 → Tasks 2-3; Component 3 → Task 4; Component 4 → Task 5; Component 5 → Task 6; degraded costSummary path handled in Task 4 Step 4(d).
- Dependency on R-DPM is stated as a hard prerequisite (partial extraction + blocker/revision fields).
- Type consistency: `contract_type` values, `VALID_CATEGORIES`, `finance` array keys, and route names are used identically across tasks; money always `number_format((float) ...)`.
- Known verify-at-execution points are explicit commands (route-name prefixes, `Ncr` closed constant, Contract NOT NULL columns) — not placeholders.
