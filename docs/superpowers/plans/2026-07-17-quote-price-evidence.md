# Quote Price Evidence & Reusable Reference Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a user record why a quote line item's price is what it is (a typed evidence source, not free text), and reuse that evidence automatically the next time the same work item is quoted.

**Architecture:** One new append-only table `price_reference_entries` keyed by `(tenant_id, work_item_code, unit)`, no foreign key to quotes — matched by string equality against `QuoteLineItem.code`/`unit`, which are already free-text fields. Two new read-only JSON endpoints (lookup latest reference, list history) plus a small addition to the existing `saveQuoteLines` save path that inserts a new reference row when a user supplies evidence. Everything lives in the existing `CrmPageController` and `quote-show.blade.php` — no new controller, no new page.

**Tech Stack:** Laravel 12, MySQL/SQLite (via existing `TenantScope` trait + ULIDs), vanilla JS (`fetch`) matching the existing `addLine()` pattern in `quote-show.blade.php` — no new frontend framework.

## Global Constraints

- Tenant isolation via the existing `App\Traits\TenantScope` trait on every new model — copied verbatim from `QuoteLineItem`.
- No new permission — reuse `crm.view` (read endpoints) and `crm.manage` (evidence-writing side effect of `saveQuoteLines`, already gated).
- `price_reference_entries` rows are never updated or deleted after creation (`UPDATED_AT = null` on the model, no delete/update endpoint anywhere in this plan).
- Spec: `docs/superpowers/specs/2026-07-17-quote-price-evidence-design.md` — read it first if anything here is ambiguous.

---

### Task 1: `PriceReferenceEntry` model, migration, and factory

**Files:**
- Create: `database/migrations/2026_07_17_150000_create_price_reference_entries_table.php`
- Create: `app/Models/PriceReferenceEntry.php`
- Create: `database/factories/PriceReferenceEntryFactory.php`
- Test: `tests/Unit/Models/PriceReferenceEntryTest.php`

**Interfaces:**
- Produces: `App\Models\PriceReferenceEntry` with constants `VALID_BENCHMARK_TYPES` (array of 4 strings), `BENCHMARK_TYPE_LABELS` (array, type => Vietnamese label), and static method `latestFor(string $tenantId, string $code, string $unit): ?self`.

- [ ] **Step 1: Write the failing unit test**

Create `tests/Unit/Models/PriceReferenceEntryTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PriceReferenceEntry;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceReferenceEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_for_returns_the_newest_entry_by_evidenced_at(): void
    {
        $tenant = Tenant::factory()->create();

        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
            'unit_price' => 1500000,
            'evidenced_at' => '2026-01-01',
        ]);

        $newest = PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
            'unit_price' => 1800000,
            'evidenced_at' => '2026-07-01',
        ]);

        $result = PriceReferenceEntry::latestFor((string) $tenant->id, 'BT-MONG', 'm3');

        $this->assertNotNull($result);
        $this->assertSame($newest->id, $result->id);
        $this->assertEqualsWithDelta(1800000, $result->unit_price, 0.01);
    }

    public function test_latest_for_returns_null_when_no_match(): void
    {
        $tenant = Tenant::factory()->create();

        $result = PriceReferenceEntry::latestFor((string) $tenant->id, 'NO-SUCH-CODE', 'm3');

        $this->assertNull($result);
    }

    public function test_latest_for_is_tenant_isolated(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenantA->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
        ]);

        $result = PriceReferenceEntry::latestFor((string) $tenantB->id, 'BT-MONG', 'm3');

        $this->assertNull($result);
    }

    public function test_unit_mismatch_does_not_match(): void
    {
        $tenant = Tenant::factory()->create();

        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
        ]);

        $result = PriceReferenceEntry::latestFor((string) $tenant->id, 'BT-MONG', 'm2');

        $this->assertNull($result);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Models/PriceReferenceEntryTest.php`
Expected: FAIL — `Class "App\Models\PriceReferenceEntry" not found` (and factory not found).

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_17_150000_create_price_reference_entries_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_reference_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('work_item_code', 50);
            $table->string('work_item_name', 255);
            $table->string('unit', 30);
            $table->decimal('unit_price', 15, 2);
            $table->string('benchmark_type', 30);
            $table->text('evidence_note')->nullable();
            $table->date('evidenced_at');
            $table->foreignUlid('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'work_item_code', 'unit', 'evidenced_at'], 'pre_tenant_code_unit_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_reference_entries');
    }
};
```

- [ ] **Step 4: Write the model**

Create `app/Models/PriceReferenceEntry.php`:

```php
<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $work_item_code
 * @property string $work_item_name
 * @property string $unit
 * @property float $unit_price
 * @property string $benchmark_type
 * @property string|null $evidence_note
 * @property \Illuminate\Support\Carbon $evidenced_at
 * @property string|null $created_by
 */
class PriceReferenceEntry extends Model
{
    use HasUlids;
    /** @use HasFactory<\Database\Factories\PriceReferenceEntryFactory> */
    use HasFactory;
    use TenantScope;

    public const UPDATED_AT = null;

    public const BENCHMARK_VENDOR_QUOTE = 'vendor_quote';
    public const BENCHMARK_PREVIOUS_PROJECT = 'previous_project';
    public const BENCHMARK_APPROVED_RATE = 'approved_rate';
    public const BENCHMARK_EXPERT_ESTIMATE = 'expert_estimate';

    /** @var list<string> */
    public const VALID_BENCHMARK_TYPES = [
        self::BENCHMARK_VENDOR_QUOTE,
        self::BENCHMARK_PREVIOUS_PROJECT,
        self::BENCHMARK_APPROVED_RATE,
        self::BENCHMARK_EXPERT_ESTIMATE,
    ];

    /** @var array<string, string> */
    public const BENCHMARK_TYPE_LABELS = [
        self::BENCHMARK_VENDOR_QUOTE => 'Báo giá nhà cung cấp',
        self::BENCHMARK_PREVIOUS_PROJECT => 'Giá dự án trước',
        self::BENCHMARK_APPROVED_RATE => 'Bảng giá nội bộ đã duyệt',
        self::BENCHMARK_EXPERT_ESTIMATE => 'Ước tính chuyên gia',
    ];

    protected $table = 'price_reference_entries';

    protected $fillable = [
        'tenant_id',
        'work_item_code',
        'work_item_name',
        'unit',
        'unit_price',
        'benchmark_type',
        'evidence_note',
        'evidenced_at',
        'created_by',
    ];

    /** @var array{unit_price: string, evidenced_at: string} */
    protected $casts = [
        'unit_price' => 'float',
        'evidenced_at' => 'date',
    ];

    public static function latestFor(string $tenantId, string $code, string $unit): ?self
    {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->where('work_item_code', $code)
            ->where('unit', $unit)
            ->orderByDesc('evidenced_at')
            ->orderByDesc('created_at')
            ->first();
    }
}
```

- [ ] **Step 5: Write the factory**

Create `database/factories/PriceReferenceEntryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\PriceReferenceEntry;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PriceReferenceEntry> */
class PriceReferenceEntryFactory extends Factory
{
    protected $model = PriceReferenceEntry::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_item_code' => 'CODE-' . $this->faker->unique()->numerify('###'),
            'work_item_name' => $this->faker->words(3, true),
            'unit' => $this->faker->randomElement(['m2', 'm3', 'kg', 'cai']),
            'unit_price' => $this->faker->randomFloat(2, 10000, 2000000),
            'benchmark_type' => $this->faker->randomElement(PriceReferenceEntry::VALID_BENCHMARK_TYPES),
            'evidence_note' => $this->faker->sentence(),
            'evidenced_at' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }
}
```

- [ ] **Step 6: Run migration and test to verify it passes**

Run: `php artisan migrate --env=testing` (or let `RefreshDatabase` handle it), then:
`./vendor/bin/phpunit tests/Unit/Models/PriceReferenceEntryTest.php`
Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_17_150000_create_price_reference_entries_table.php app/Models/PriceReferenceEntry.php database/factories/PriceReferenceEntryFactory.php tests/Unit/Models/PriceReferenceEntryTest.php
git commit -m "feat(crm): add PriceReferenceEntry model for quote price evidence"
```

---

### Task 2: Lookup + history JSON endpoints

**Files:**
- Modify: `app/Http/Controllers/Web/CrmPageController.php` (add 2 public methods near `saveQuoteLines`, around line 693)
- Modify: `routes/web.php` (add 2 routes near `crm.quotes.lines.save`, around line 1044)
- Test: `tests/Feature/Zena/QuotePriceReferenceTest.php` (new file)

**Interfaces:**
- Consumes: `App\Models\PriceReferenceEntry::latestFor()` and `BENCHMARK_TYPE_LABELS` from Task 1.
- Produces: routes `operator.crm.price-references.lookup` (GET, query params `code`, `unit`) and `operator.crm.price-references.history` (GET, same query params), both returning `{"data": ...}` JSON.

- [ ] **Step 1: Write the failing feature test**

Create `tests/Feature/Zena/QuotePriceReferenceTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Models\PriceReferenceEntry;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class QuotePriceReferenceTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_lookup_returns_latest_matching_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view']);

        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
            'unit_price' => 1750000,
            'benchmark_type' => PriceReferenceEntry::BENCHMARK_VENDOR_QUOTE,
            'evidenced_at' => '2026-07-01',
        ]);

        $response = $this->actingAs($user)->getJson(
            route('operator.crm.price-references.lookup', ['code' => 'BT-MONG', 'unit' => 'm3'])
        );

        $response->assertOk();
        $response->assertJsonPath('data.unit_price', 1750000);
        $response->assertJsonPath('data.benchmark_type', 'vendor_quote');
        $response->assertJsonPath('data.benchmark_type_label', 'Báo giá nhà cung cấp');
    }

    public function test_lookup_returns_null_data_when_no_match(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view']);

        $response = $this->actingAs($user)->getJson(
            route('operator.crm.price-references.lookup', ['code' => 'NOPE', 'unit' => 'm3'])
        );

        $response->assertOk();
        $response->assertJsonPath('data', null);
    }

    public function test_lookup_requires_crm_view_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], []);

        $response = $this->actingAs($user)->getJson(
            route('operator.crm.price-references.lookup', ['code' => 'BT-MONG', 'unit' => 'm3'])
        );

        $response->assertStatus(403);
    }

    public function test_history_returns_all_entries_newest_first(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view']);

        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
            'unit_price' => 1500000,
            'evidenced_at' => '2026-01-01',
        ]);
        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
            'unit_price' => 1750000,
            'evidenced_at' => '2026-07-01',
        ]);

        $response = $this->actingAs($user)->getJson(
            route('operator.crm.price-references.history', ['code' => 'BT-MONG', 'unit' => 'm3'])
        );

        $response->assertOk();
        $response->assertJsonPath('data.0.unit_price', 1750000);
        $response->assertJsonPath('data.1.unit_price', 1500000);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_history_is_tenant_isolated(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $userB = $this->createTenantUser($tenantB, [], ['admin'], ['crm.view']);

        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenantA->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
        ]);

        $response = $this->actingAs($userB)->getJson(
            route('operator.crm.price-references.history', ['code' => 'BT-MONG', 'unit' => 'm3'])
        );

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Zena/QuotePriceReferenceTest.php`
Expected: FAIL — route `operator.crm.price-references.lookup` not defined.

- [ ] **Step 3: Add the routes**

In `routes/web.php`, immediately after the line (around 1044):
```php
    Route::post('/crm/quotes/{id}/lines', [App\Http\Controllers\Web\CrmPageController::class, 'saveQuoteLines'])->middleware('rbac:crm.manage')->name('crm.quotes.lines.save');
```
add:
```php
    Route::get('/crm/price-references/lookup', [App\Http\Controllers\Web\CrmPageController::class, 'lookupPriceReference'])->middleware('rbac:crm.view')->name('crm.price-references.lookup');
    Route::get('/crm/price-references/history', [App\Http\Controllers\Web\CrmPageController::class, 'priceReferenceHistory'])->middleware('rbac:crm.view')->name('crm.price-references.history');
```

- [ ] **Step 4: Add the controller methods**

In `app/Http/Controllers/Web/CrmPageController.php`, add these two methods right after `saveQuoteLines()` (which ends around line 693, before `public function sendQuote`):

```php
    public function lookupPriceReference(Request $request): JsonResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;
        $code = (string) $request->query('code', '');
        $unit = (string) $request->query('unit', '');

        if ($code === '' || $unit === '') {
            return response()->json(['data' => null]);
        }

        $entry = \App\Models\PriceReferenceEntry::latestFor($tenantId, $code, $unit);

        if (!$entry) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->serializePriceReferenceEntry($entry)]);
    }

    public function priceReferenceHistory(Request $request): JsonResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;
        $code = (string) $request->query('code', '');
        $unit = (string) $request->query('unit', '');

        $entries = \App\Models\PriceReferenceEntry::query()
            ->where('tenant_id', $tenantId)
            ->where('work_item_code', $code)
            ->where('unit', $unit)
            ->orderByDesc('evidenced_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (\App\Models\PriceReferenceEntry $entry) => $this->serializePriceReferenceEntry($entry))
            ->values();

        return response()->json(['data' => $entries]);
    }

    /** @return array{unit_price: float, benchmark_type: string, benchmark_type_label: string, evidence_note: string|null, evidenced_at: string} */
    private function serializePriceReferenceEntry(\App\Models\PriceReferenceEntry $entry): array
    {
        return [
            'unit_price' => $entry->unit_price,
            'benchmark_type' => $entry->benchmark_type,
            'benchmark_type_label' => \App\Models\PriceReferenceEntry::BENCHMARK_TYPE_LABELS[$entry->benchmark_type] ?? $entry->benchmark_type,
            'evidence_note' => $entry->evidence_note,
            'evidenced_at' => $entry->evidenced_at->format('Y-m-d'),
        ];
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Zena/QuotePriceReferenceTest.php`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/CrmPageController.php routes/web.php tests/Feature/Zena/QuotePriceReferenceTest.php
git commit -m "feat(crm): add price reference lookup and history endpoints"
```

---

### Task 3: Record evidence on `saveQuoteLines`

**Files:**
- Modify: `app/Http/Controllers/Web/CrmPageController.php:635-693` (`saveQuoteLines` method)
- Test: `tests/Feature/Zena/QuotePriceReferenceTest.php` (append tests)

**Interfaces:**
- Consumes: `PriceReferenceEntry` model (Task 1).
- Produces: nothing new consumed by later tasks — this is the write side of the feature.

- [ ] **Step 1: Write the failing feature tests**

Append to `tests/Feature/Zena/QuotePriceReferenceTest.php` (inside the class, before the final closing brace):

```php
    public function test_saving_a_line_with_benchmark_type_creates_one_reference_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $quote = $this->makeDraftQuote($tenant, $user);

        $this->actingAs($user)->post(route('operator.crm.quotes.lines.save', $quote->id), [
            'lines' => [
                [
                    'name' => 'Bê tông móng',
                    'unit' => 'm3',
                    'quantity' => 20,
                    'unit_price' => 1750000,
                    'code' => 'BT-MONG',
                    'benchmark_type' => 'vendor_quote',
                    'evidence_note' => 'Báo giá Công ty ABC',
                    'evidence_date' => '2026-07-10',
                ],
            ],
        ]);

        $this->assertDatabaseCount('price_reference_entries', 1);
        $this->assertDatabaseHas('price_reference_entries', [
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
            'benchmark_type' => 'vendor_quote',
            'evidence_note' => 'Báo giá Công ty ABC',
        ]);
    }

    public function test_saving_a_line_without_benchmark_type_creates_no_reference_entry(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $quote = $this->makeDraftQuote($tenant, $user);

        $this->actingAs($user)->post(route('operator.crm.quotes.lines.save', $quote->id), [
            'lines' => [
                [
                    'name' => 'Bê tông móng',
                    'unit' => 'm3',
                    'quantity' => 20,
                    'unit_price' => 1750000,
                    'code' => 'BT-MONG',
                ],
            ],
        ]);

        $this->assertDatabaseCount('price_reference_entries', 0);
    }

    public function test_saving_twice_with_evidence_appends_two_entries_not_upsert(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $quote = $this->makeDraftQuote($tenant, $user);

        $payload = [
            'lines' => [
                [
                    'name' => 'Bê tông móng',
                    'unit' => 'm3',
                    'quantity' => 20,
                    'unit_price' => 1750000,
                    'code' => 'BT-MONG',
                    'benchmark_type' => 'vendor_quote',
                    'evidence_date' => '2026-07-10',
                ],
            ],
        ];

        $this->actingAs($user)->post(route('operator.crm.quotes.lines.save', $quote->id), $payload);
        $payload['lines'][0]['unit_price'] = 1800000;
        $payload['lines'][0]['evidence_date'] = '2026-07-15';
        $this->actingAs($user)->post(route('operator.crm.quotes.lines.save', $quote->id), $payload);

        $this->assertDatabaseCount('price_reference_entries', 2);
    }

    public function test_evidence_is_rejected_without_crm_manage_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);
        $quote = $this->makeDraftQuote($tenant, $admin);
        $viewer = $this->createTenantUser($tenant, [], ['member'], ['crm.view']);

        $this->actingAs($viewer)->post(route('operator.crm.quotes.lines.save', $quote->id), [
            'lines' => [
                [
                    'name' => 'Bê tông móng',
                    'unit' => 'm3',
                    'quantity' => 20,
                    'unit_price' => 1750000,
                    'code' => 'BT-MONG',
                    'benchmark_type' => 'vendor_quote',
                ],
            ],
        ])->assertStatus(403);

        $this->assertDatabaseCount('price_reference_entries', 0);
    }

    private function makeDraftQuote(Tenant $tenant, \App\Models\User $user): \App\Models\Quote
    {
        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Test Account',
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Test Opp',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        return \App\Models\Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opportunity->id,
            'quote_number' => \App\Models\Quote::nextNumber((string) $tenant->id),
            'revision_no' => \App\Models\Quote::nextRevision((string) $opportunity->id),
            'status' => \App\Models\Quote::STATUS_DRAFT,
            'created_by' => (string) $user->id,
        ]);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Zena/QuotePriceReferenceTest.php --filter benchmark_type`
Expected: FAIL — `price_reference_entries` count is 0 where 1 or 2 expected (validation strips unknown fields, nothing writes them).

- [ ] **Step 3: Modify `saveQuoteLines`**

In `app/Http/Controllers/Web/CrmPageController.php`, find the `$validated = $request->validate([...]);` block inside `saveQuoteLines` (around line 652) and change it from:

```php
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.unit' => ['required', 'string', 'max:30'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.price_note' => ['nullable', 'string', 'max:500'],
            'lines.*.code' => ['nullable', 'string', 'max:50'],
        ]);
```

to:

```php
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.unit' => ['required', 'string', 'max:30'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.price_note' => ['nullable', 'string', 'max:500'],
            'lines.*.code' => ['nullable', 'string', 'max:50'],
            'lines.*.benchmark_type' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Models\PriceReferenceEntry::VALID_BENCHMARK_TYPES)],
            'lines.*.evidence_note' => ['nullable', 'string', 'max:500'],
            'lines.*.evidence_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
```

Then find the `foreach ($validated['lines'] as $index => $line) { ... }` loop inside the `DB::transaction` (around line 670) and change it from:

```php
            foreach ($validated['lines'] as $index => $line) {
                $amount = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
                $subtotal += $amount;

                QuoteLineItem::query()->create([
                    'tenant_id' => $tenantId,
                    'quote_id' => (string) $quote->id,
                    'sort_order' => $index + 1,
                    'code' => $line['code'] ?? null,
                    'name' => $line['name'],
                    'unit' => $line['unit'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'amount' => $amount,
                    'price_note' => $line['price_note'] ?? null,
                ]);
            }
```

to:

```php
            foreach ($validated['lines'] as $index => $line) {
                $amount = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
                $subtotal += $amount;

                QuoteLineItem::query()->create([
                    'tenant_id' => $tenantId,
                    'quote_id' => (string) $quote->id,
                    'sort_order' => $index + 1,
                    'code' => $line['code'] ?? null,
                    'name' => $line['name'],
                    'unit' => $line['unit'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'amount' => $amount,
                    'price_note' => $line['price_note'] ?? null,
                ]);

                if (!empty($line['benchmark_type']) && !empty($line['code'])) {
                    \App\Models\PriceReferenceEntry::create([
                        'tenant_id' => $tenantId,
                        'work_item_code' => $line['code'],
                        'work_item_name' => $line['name'],
                        'unit' => $line['unit'],
                        'unit_price' => $line['unit_price'],
                        'benchmark_type' => $line['benchmark_type'],
                        'evidence_note' => $line['evidence_note'] ?? null,
                        'evidenced_at' => $line['evidence_date'] ?? now()->toDateString(),
                        'created_by' => (string) auth()->id(),
                    ]);
                }
            }
```

- [ ] **Step 4: Run all tests in the file to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Zena/QuotePriceReferenceTest.php`
Expected: PASS (9 tests total).

- [ ] **Step 5: Run the existing quote lifecycle suite to check for regressions**

Run: `./vendor/bin/phpunit tests/Feature/QuoteLifecycleTest.php`
Expected: PASS — the validation addition is all-`nullable`, so existing payloads without these fields are unaffected.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/CrmPageController.php tests/Feature/Zena/QuotePriceReferenceTest.php
git commit -m "feat(crm): record price reference evidence when saving quote lines"
```

---

### Task 4: UI — editable code field, evidence inputs, auto-fill, and history link

**Files:**
- Modify: `resources/views/crm/quote-show.blade.php`

**Interfaces:**
- Consumes: `operator.crm.price-references.lookup` and `operator.crm.price-references.history` routes (Task 2); `lines.*.benchmark_type`/`evidence_note`/`evidence_date` fields (Task 3).

This task is UI-only glue over already-tested backend endpoints — no new backend test is added here; the existing feature tests already cover every code path this UI calls into. Manual verification steps are given instead of a unit test, matching this codebase's pattern for Blade+vanilla-JS additions (see e.g. `addLine()`, which also has no dedicated JS test).

- [ ] **Step 1: Make `code` a visible, editable input**

In `resources/views/crm/quote-show.blade.php`, the existing `@foreach ($lines as $i => $line)` block (around line 139) currently renders `code` as a hidden input:

```php
                                <input type="hidden" name="lines[{{ $i }}][code]" value="{{ $line->code }}">
```

Replace it with a visible field placed right after the "Tên" field (so it reads: Mã, Tên, ĐVT, KL, Đơn giá, Ghi chú):

```php
                                <div class="operator-field w-24">
                                    <label>Mã công tác</label>
                                    <input type="text" name="lines[{{ $i }}][code]" value="{{ $line->code }}" class="operator-input line-code-input" data-index="{{ $i }}" oninput="lookupPriceReference({{ $i }})">
                                </div>
```

- [ ] **Step 2: Add evidence inputs and a reference-hint/history area per line**

Immediately after the "Ghi chú đơn giá" field block (around line 160, right before the closing `</div>` of `.line-row`), add:

```php
                                <div class="operator-field w-40">
                                    <label>Nguồn giá (tuỳ chọn)</label>
                                    <select name="lines[{{ $i }}][benchmark_type]" class="operator-input">
                                        <option value="">— Không lưu chứng cứ —</option>
                                        @foreach (\App\Models\PriceReferenceEntry::BENCHMARK_TYPE_LABELS as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="operator-field flex-1 min-w-32">
                                    <label>Ghi chú chứng cứ</label>
                                    <input type="text" name="lines[{{ $i }}][evidence_note]" class="operator-input">
                                </div>
                                <div class="operator-field w-32">
                                    <label>Ngày chứng cứ</label>
                                    <input type="date" name="lines[{{ $i }}][evidence_date]" class="operator-input" max="{{ now()->format('Y-m-d') }}">
                                </div>
                                <div class="w-full text-sm text-gray-500 price-reference-hint" id="price-reference-hint-{{ $i }}"></div>
                                <button type="button" class="operator-button text-xs" onclick="showPriceReferenceHistory({{ $i }})">Xem lịch sử giá</button>
```

- [ ] **Step 3: Update `addLine()`'s template string to match**

In the `<script>` block (around line 247), the `row.innerHTML` template currently ends with a hidden `code` input and has no evidence fields. Replace the whole `row.innerHTML` assignment with:

```javascript
        row.innerHTML = `
            <div class="operator-field flex-1 min-w-32">
                <label>Tên</label>
                <input type="text" name="lines[${index}][name]" class="operator-input" required>
            </div>
            <div class="operator-field w-24">
                <label>Mã công tác</label>
                <input type="text" name="lines[${index}][code]" class="operator-input line-code-input" data-index="${index}" oninput="lookupPriceReference(${index})">
            </div>
            <div class="operator-field w-20">
                <label>ĐVT</label>
                <input type="text" name="lines[${index}][unit]" class="operator-input" required>
            </div>
            <div class="operator-field w-24">
                <label>KL</label>
                <input type="number" name="lines[${index}][quantity]" class="operator-input" step="0.001" min="0.001" required>
            </div>
            <div class="operator-field w-32">
                <label>Đơn giá</label>
                <input type="number" name="lines[${index}][unit_price]" class="operator-input" step="0.01" min="0" required>
            </div>
            <div class="operator-field flex-1 min-w-32">
                <label>Ghi chú đơn giá</label>
                <input type="text" name="lines[${index}][price_note]" class="operator-input">
            </div>
            <div class="operator-field w-40">
                <label>Nguồn giá (tuỳ chọn)</label>
                <select name="lines[${index}][benchmark_type]" class="operator-input">
                    <option value="">— Không lưu chứng cứ —</option>
                    ${window.zenaBenchmarkTypeOptions}
                </select>
            </div>
            <div class="operator-field flex-1 min-w-32">
                <label>Ghi chú chứng cứ</label>
                <input type="text" name="lines[${index}][evidence_note]" class="operator-input">
            </div>
            <div class="operator-field w-32">
                <label>Ngày chứng cứ</label>
                <input type="date" name="lines[${index}][evidence_date]" class="operator-input">
            </div>
            <div class="w-full text-sm text-gray-500 price-reference-hint" id="price-reference-hint-${index}"></div>
            <button type="button" class="operator-button text-xs" onclick="showPriceReferenceHistory(${index})">Xem lịch sử giá</button>
        `;
```

- [ ] **Step 4: Add the lookup/autofill and history JS functions**

At the top of the `<script>` block (right after `@push('scripts')` and before `function addLine()`), add:

```javascript
    window.zenaBenchmarkTypeOptions = `@foreach (\App\Models\PriceReferenceEntry::BENCHMARK_TYPE_LABELS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach`;

    let lookupDebounceTimers = {};

    function lookupPriceReference(index) {
        clearTimeout(lookupDebounceTimers[index]);
        lookupDebounceTimers[index] = setTimeout(() => {
            const row = document.querySelector(`.line-row[data-index="${index}"]`) || document.querySelector(`.line-code-input[data-index="${index}"]`).closest('.line-row');
            const codeInput = row.querySelector(`[name="lines[${index}][code]"]`);
            const unitInput = row.querySelector(`[name="lines[${index}][unit]"]`);
            const priceInput = row.querySelector(`[name="lines[${index}][unit_price]"]`);
            const hint = document.getElementById(`price-reference-hint-${index}`);

            const code = codeInput.value.trim();
            const unit = unitInput.value.trim();
            if (!code || !unit) {
                hint.textContent = '';
                return;
            }

            fetch(`{{ route('operator.crm.price-references.lookup') }}?code=${encodeURIComponent(code)}&unit=${encodeURIComponent(unit)}`)
                .then(r => r.json())
                .then(({ data }) => {
                    if (!data) {
                        hint.textContent = '';
                        return;
                    }
                    if (!priceInput.value) {
                        priceInput.value = data.unit_price;
                    }
                    hint.textContent = `Tham chiếu: ${data.unit_price.toLocaleString('vi-VN')}đ — ${data.benchmark_type_label}, ${data.evidenced_at}`;
                });
        }, 400);
    }

    function showPriceReferenceHistory(index) {
        const row = document.querySelector(`.line-code-input[data-index="${index}"]`).closest('.line-row');
        const code = row.querySelector(`[name="lines[${index}][code]"]`).value.trim();
        const unit = row.querySelector(`[name="lines[${index}][unit]"]`).value.trim();
        if (!code || !unit) {
            alert('Cần nhập mã công tác và đơn vị tính trước.');
            return;
        }

        fetch(`{{ route('operator.crm.price-references.history') }}?code=${encodeURIComponent(code)}&unit=${encodeURIComponent(unit)}`)
            .then(r => r.json())
            .then(({ data }) => {
                if (!data.length) {
                    alert('Chưa có lịch sử giá cho công tác này.');
                    return;
                }
                const lines = data.map(e => `${e.evidenced_at} — ${e.unit_price.toLocaleString('vi-VN')}đ — ${e.benchmark_type_label}${e.evidence_note ? ' — ' + e.evidence_note : ''}`);
                alert('Lịch sử giá:\n\n' + lines.join('\n'));
            });
    }
```

(A plain `alert()`-based history view is intentionally minimal for this slice — the spec calls for a "small modal," and a JS `alert()` with newline-joined text is the simplest thing that satisfies "read-only list, newest first" without adding a new Blade partial/modal component. Upgrading to a styled modal is a trivial follow-up if the plain alert proves too crude in practice, and is not blocked by anything in this plan.)

- [ ] **Step 5: Manual verification**

Run the dev server (or use the existing `/run` skill/pattern for this repo) and:
1. Open a draft quote, add a line with code `BT-MONG`, unit `m3`, price `1750000`, choose "Báo giá nhà cung cấp" as source, note "Test NCC A", save.
2. Reload the page, add a second line with the same code/unit — confirm the price auto-fills to `1750000` and the hint text shows "Tham chiếu: 1.750.000đ — Báo giá nhà cung cấp, {today's date}".
3. Click "Xem lịch sử giá" on that line — confirm the alert lists exactly one entry.
4. Change the price to `1800000`, pick a source again, save — click "Xem lịch sử giá" again, confirm two entries now show, newest (1.800.000đ) first.

- [ ] **Step 6: Commit**

```bash
git add resources/views/crm/quote-show.blade.php
git commit -m "feat(crm): wire price reference auto-fill and history into quote line editing"
```

---

## Self-Review

**Spec coverage:**
- Data model (`price_reference_entries`, no FK, tenant-scoped, append-only) → Task 1. ✓
- Auto-lookup + auto-fill → Task 2 (endpoint) + Task 4 (JS wiring). ✓
- Optional evidence capture on save, append-only (never upsert) → Task 3. ✓
- Read-only price history view → Task 2 (endpoint) + Task 4 (`showPriceReferenceHistory`). ✓
- Permissions (`crm.view` read, `crm.manage` write side-effect) → Tasks 2 & 3 tests assert both. ✓
- Client never sees evidence → nothing in this plan touches `quotePdf`, `renderQuoteDocument`, or any portal view — confirmed by grep-checking those methods are untouched. ✓

**Placeholder scan:** no TBD/TODO; the one deliberately-simple choice (`alert()`-based history) is explained inline, not left vague.

**Type consistency:** `PriceReferenceEntry::latestFor(string $tenantId, string $code, string $unit): ?self` (Task 1) is called identically in Task 2's controller methods and Task 1's own tests. `BENCHMARK_TYPE_LABELS` keys/values used identically in the model (Task 1), controller serialization (Task 2), and Blade `@foreach` (Task 4).

**Scope check:** single cohesive slice, four tasks, each independently testable and committable. No decomposition needed.
