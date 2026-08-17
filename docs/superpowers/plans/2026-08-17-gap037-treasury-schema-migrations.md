# GAP-037 Project Treasury — Schema Migrations + Structural Models Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the 14 Treasury tables, their Eloquent models, and row-level invariant enforcement exactly as specified in the Owner-approved Gate 2 schema proposal (`docs/owner-decisions/GAP-037/02-design-v17.md`, gate_status: approved, 2026-08-17), with zero deviation from its column lists, constraints, and indexes.

**Architecture:** Straight Laravel migrations (one file per table, in the exact dependency order §16 of the design doc specifies) plus minimal Eloquent models under `App\Models\Treasury`. A shared trait (`EnforcesRowInvariants`) enforces every single-row `CHECK`-equivalent (positive amounts, mutually-exclusive nullable pairs, exactly-one-of-N nullable groups, co-nullable pairs, allowed-value lists) uniformly on both MySQL and SQLite, since Laravel 12's Schema `Blueprint` has no fluent `check()` builder and SQLite cannot `ALTER TABLE ADD CONSTRAINT CHECK` after table creation.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL 8 (prod/dev) + SQLite (test suite), PHPUnit, ULID primary keys (`HasUlids`), existing `App\Traits\TenantScope`.

## Global Constraints

- **Source of truth:** `docs/owner-decisions/GAP-037/02-design-v17.md` (approved, frozen). Every column, `CHECK`, and index below is copied from it verbatim. Do not add, rename, or drop any column/index/constraint beyond what it specifies.
- **Primary keys:** every table uses `$table->ulid('id')->primary()`, matching the codebase's existing convention (`app/Models/Opportunity.php`, `database/migrations/2026_07_26_090000_create_rfi_escalations_table.php`).
- **Composite tenant-scoped uniqueness:** every one of the 14 tables gets `$table->unique(['tenant_id', 'id'], '<short_name>')` — required so other Treasury tables can target it with a composite `(tenant_id, xxx_id)` foreign key (design doc §13, "Composite-FK-target index requirement").
- **Composite FKs:** every Treasury-to-Treasury reference is `$table->foreign(['tenant_id', 'xxx_id'], '<short_name>')->references(['tenant_id', 'id'])->on('treasury_yyy')`. A composite FK with a `NULL` in either local column is not enforced by MySQL/SQLite (standard `MATCH SIMPLE` behavior) — this is exactly what nullable typed-FK columns (e.g. `source_wallet_id`) need.
- **No cascading deletes on Treasury-internal or cost-source FKs.** Only `tenant_id → tenants(id)` cascades on delete (matches existing repo convention in `rfi_escalations`: "hard-deleting ... must fail at the DB level, never silently cascade away the audit trail"). Every other FK (composite Treasury-to-Treasury, single-column to `contract_payments`/`contract_expenses`/`material_receipt_lines`/`users`/`accounts`) has no `onDelete` clause, which defaults to `RESTRICT` in MySQL/SQLite.
- **Money columns:** `$table->decimal('<col>', 15, 2)`, matching `contract_payments.amount` / `contract_expenses.amount` precedent.
- **Enum-like columns are `$table->string('<col>', <n>)`, not native `ENUM`** — validated by `EnforcesRowInvariants`'s `$allowedValues` map, for MySQL/SQLite portability and to allow future value additions without a migration.
- **Timestamps — two shapes, both from the design doc, never guess:**
  - Tables the doc lists as "..., timestamps." get `$table->timestamps()` (both `created_at` and `updated_at`).
  - Tables the doc lists as "..., `created_at`." (singular — every event-log/ledger table: `treasury_ledger_entries`, `treasury_advance_settlements`, `treasury_cost_settlement_allocations`, `treasury_expense_approvals`, `treasury_reconciliation_entries`) get **only** `$table->timestamp('created_at')->useCurrent()` — no `updated_at` column at all. This is not an oversight: it is the schema-level expression of design-doc §2.1a's "immutable once created" rule for these tables — there is no column to even update.
- **Models:** `App\Models\Treasury\<Name>`, `use HasUlids; use TenantScope;`. Every model gets a `@property` PHPDoc block for every column (repo convention — PHPStan runs without Larastan, so magic Eloquent properties must be declared via `@property` or Code Quality CI fails; see existing `app/Models/Opportunity.php`).
- **Explicit FK/unique constraint names required on every `foreign()`/`unique()` call.** MySQL's 64-character identifier limit is a real risk with `tenant_id`-prefixed composite names on already-long table names (`treasury_cost_settlement_allocations`) — every call in this plan passes an explicit short name, matching the existing `accounts_tenant_id_foreign` precedent in this codebase.
- **Out of scope for this plan (do not build):** no controller, service, route, UI, or business-logic enforcement of any multi-row/multi-table invariant — settlement conservation (§6), lock ordering (§11), the reversal state machine (§2.2/§2.2a-e), atomic multi-table writes (§7.5/§7.6), reconciliation lifecycle (§12). Models here are **structural only**: fillable, casts, relationships, and the single-row checks `EnforcesRowInvariants` covers. A later GAP-037 implementation slice adds the service layer that orchestrates those multi-row rules under the exact lock order in §11.
- **Migration file naming:** `database/migrations/2026_08_17_1{NN}000_create_treasury_<table>_table.php`, `NN` increasing per task in the exact order below, so `php artisan migrate` always runs them in the dependency-safe order design-doc §16 specifies.

---

### Task 1: `treasury_financial_parties` + `treasury_wallets`

**Files:**
- Create: `database/migrations/2026_08_17_110000_create_treasury_financial_parties_table.php`
- Create: `database/migrations/2026_08_17_110100_create_treasury_wallets_table.php`
- Create: `app/Models/Treasury/TreasuryFinancialParty.php`
- Create: `app/Models/Treasury/TreasuryWallet.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryFinancialPartiesSchemaTest.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryWalletsSchemaTest.php`

**Interfaces:**
- Produces: `TreasuryFinancialParty` (table `treasury_financial_parties`, columns `id,tenant_id,party_type,name,linked_account_id,linked_user_id,created_at,updated_at`), `TreasuryWallet` (table `treasury_wallets`, columns `id,tenant_id,project_id,wallet_type,name,custodian_party_id,created_at,updated_at`). Later tasks' composite FKs target `treasury_financial_parties(tenant_id,id)` and `treasury_wallets(tenant_id,id)`.

- [ ] **Step 1: Write the failing schema tests**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryFinancialPartiesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_financial_parties'));
        $this->assertTrue(Schema::hasColumns('treasury_financial_parties', [
            'id', 'tenant_id', 'party_type', 'name', 'linked_account_id',
            'linked_user_id', 'created_at', 'updated_at',
        ]));
    }
}
```

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryWalletsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_wallets'));
        $this->assertTrue(Schema::hasColumns('treasury_wallets', [
            'id', 'tenant_id', 'project_id', 'wallet_type', 'name',
            'custodian_party_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_custodian_party_composite_foreign_key_is_enforced(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $otherTenant = \App\Models\Tenant::factory()->create();

        $foreignParty = \App\Models\Treasury\TreasuryFinancialParty::create([
            'tenant_id' => $otherTenant->id,
            'party_type' => 'vendor',
            'name' => 'Cross-tenant party',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\Treasury\TreasuryWallet::create([
            'tenant_id' => $tenant->id,
            'wallet_type' => 'bank',
            'name' => 'Should fail',
            'custodian_party_id' => $foreignParty->id,
        ]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TreasuryFinancialPartiesSchemaTest`
Run: `php artisan test --filter=TreasuryWalletsSchemaTest`
Expected: both FAIL — table `treasury_financial_parties`/`treasury_wallets` does not exist, and the model classes do not exist yet.

- [ ] **Step 3: Write `treasury_financial_parties` migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_financial_parties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->string('party_type', 32);
            $table->string('name');
            $table->ulid('linked_account_id')->nullable();
            $table->ulid('linked_user_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tfp_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('linked_account_id', 'tfp_linked_account_fk')
                ->references('id')->on('accounts');
            $table->foreign('linked_user_id', 'tfp_linked_user_fk')
                ->references('id')->on('users');

            $table->unique(['tenant_id', 'id'], 'tfp_tenant_id_id_unique');
            $table->index(['tenant_id'], 'tfp_tenant_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_financial_parties');
    }
};
```

- [ ] **Step 4: Write `treasury_wallets` migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_wallets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id')->nullable();
            $table->string('wallet_type', 32);
            $table->string('name');
            $table->ulid('custodian_party_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tw_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'tw_project_id_fk')
                ->references('id')->on('projects');
            $table->foreign(
                ['tenant_id', 'custodian_party_id'],
                'tw_custodian_party_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_parties');

            $table->unique(['tenant_id', 'id'], 'tw_tenant_id_id_unique');
            $table->index(['tenant_id'], 'tw_tenant_id_idx');
            $table->index(['project_id'], 'tw_project_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_wallets');
    }
};
```

- [ ] **Step 5: Write `TreasuryFinancialParty` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $party_type
 * @property string $name
 * @property string|null $linked_account_id
 * @property string|null $linked_user_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryFinancialParty extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'treasury_financial_parties';

    protected $fillable = [
        'tenant_id', 'party_type', 'name', 'linked_account_id', 'linked_user_id',
    ];
}
```

- [ ] **Step 6: Write `TreasuryWallet` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $project_id
 * @property string $wallet_type
 * @property string $name
 * @property string|null $custodian_party_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryWallet extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'treasury_wallets';

    protected $fillable = [
        'tenant_id', 'project_id', 'wallet_type', 'name', 'custodian_party_id',
    ];

    /** @return BelongsTo<TreasuryFinancialParty, $this> */
    public function custodianParty(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialParty::class, 'custodian_party_id');
    }
}
```

- [ ] **Step 7: Run migrations and tests to verify they pass**

Run: `php artisan migrate:fresh --seed=false`
Run: `php artisan test --filter=TreasuryFinancialPartiesSchemaTest`
Run: `php artisan test --filter=TreasuryWalletsSchemaTest`
Expected: PASS. `test_custodian_party_composite_foreign_key_is_enforced` proves the composite `(tenant_id, custodian_party_id) → treasury_financial_parties(tenant_id, id)` FK actually rejects a cross-tenant reference (the party exists, but under a different `tenant_id`, so no `(tenant_id, id)` row matches).

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_17_110000_create_treasury_financial_parties_table.php \
        database/migrations/2026_08_17_110100_create_treasury_wallets_table.php \
        app/Models/Treasury/TreasuryFinancialParty.php \
        app/Models/Treasury/TreasuryWallet.php \
        tests/Unit/Migrations/Treasury/TreasuryFinancialPartiesSchemaTest.php \
        tests/Unit/Migrations/Treasury/TreasuryWalletsSchemaTest.php
git commit -m "feat(gap-037): add treasury_financial_parties and treasury_wallets tables"
```

---

### Task 2: `treasury_financial_documents` + `EnforcesRowInvariants` trait

**Files:**
- Create: `app/Models/Treasury/Concerns/EnforcesRowInvariants.php`
- Create: `database/migrations/2026_08_17_120000_create_treasury_financial_documents_table.php`
- Create: `app/Models/Treasury/TreasuryFinancialDocument.php`
- Test: `tests/Unit/Models/Treasury/EnforcesRowInvariantsTest.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryFinancialDocumentsSchemaTest.php`

**Interfaces:**
- Consumes: `TreasuryWallet`, `TreasuryFinancialParty` (Task 1).
- Produces: trait `App\Models\Treasury\Concerns\EnforcesRowInvariants` with protected static config arrays `$positiveAmountColumns`, `$mutuallyExclusivePairs`, `$exactlyOneOfGroups`, `$coNullablePairs`, `$allowedValues`, hooked into the model's `saving` event, throwing `\InvalidArgumentException` on violation. Every later task's model uses this trait. `TreasuryFinancialDocument` (table `treasury_financial_documents`).

- [ ] **Step 1: Write the failing trait test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Models\Treasury;

use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\Treasury\TreasuryWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnforcesRowInvariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_positive_amount_rejects_zero(): void
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('amount must be > 0');

        TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id,
            'document_type' => 'funding',
            'status' => 'draft',
            'amount' => 0,
            'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_positive_amount_rejects_negative(): void
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('amount must be > 0');

        TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id,
            'document_type' => 'funding',
            'status' => 'draft',
            'amount' => -10,
            'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_mutually_exclusive_source_pair_rejects_both_set(): void
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);
        $party = \App\Models\Treasury\TreasuryFinancialParty::create([
            'tenant_id' => $tenant->id, 'party_type' => 'vendor', 'name' => 'P',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('source_wallet_id and source_party_id are mutually exclusive');

        TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id,
            'document_type' => 'expense',
            'status' => 'draft',
            'amount' => 10,
            'source_wallet_id' => $wallet->id,
            'source_party_id' => $party->id,
            'destination_party_id' => $party->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_allowed_values_rejects_unknown_document_type(): void
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("document_type must be one of");

        TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id,
            'document_type' => 'not_a_real_type',
            'status' => 'draft',
            'amount' => 10,
            'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_valid_row_passes(): void
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);

        $doc = TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id,
            'document_type' => 'funding',
            'status' => 'draft',
            'amount' => 100,
            'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);

        $this->assertNotNull($doc->id);
        $this->assertSame('100.00', (string) $doc->amount);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EnforcesRowInvariantsTest`
Expected: FAIL — trait and `TreasuryFinancialDocument` do not exist yet.

- [ ] **Step 3: Write the `EnforcesRowInvariants` trait**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury\Concerns;

/**
 * Enforces single-row CHECK-equivalents identically on MySQL and SQLite.
 *
 * Laravel 12's Schema Blueprint has no fluent check() builder, and SQLite
 * cannot ALTER TABLE ADD CONSTRAINT CHECK after table creation — so these
 * design-doc-mandated row-level invariants are enforced here, in the model's
 * `saving` event, instead of as literal SQL CHECK constraints. Multi-row /
 * multi-table invariants (settlement conservation, lock ordering, the
 * reversal state machine) are explicitly NOT covered by this trait — see
 * docs/superpowers/plans/2026-08-17-gap037-treasury-schema-migrations.md's
 * Global Constraints section.
 */
trait EnforcesRowInvariants
{
    /** @var list<string> columns that must be > 0 when set */
    protected static array $positiveAmountColumns = [];

    /** @var list<array{0:string,1:string}> at most one of the pair may be non-null */
    protected static array $mutuallyExclusivePairs = [];

    /** @var list<list<string>> exactly one column in each group must be non-null */
    protected static array $exactlyOneOfGroups = [];

    /** @var list<array{0:string,1:string}> both null together, or both non-null together */
    protected static array $coNullablePairs = [];

    /** @var array<string,list<string>> column => allowed values */
    protected static array $allowedValues = [];

    protected static function bootEnforcesRowInvariants(): void
    {
        static::saving(function ($model): void {
            $model->runTreasuryRowChecks();
        });
    }

    protected function runTreasuryRowChecks(): void
    {
        foreach (static::$positiveAmountColumns as $column) {
            $value = $this->getAttribute($column);
            if ($value !== null && (float) $value <= 0) {
                throw new \InvalidArgumentException("{$column} must be > 0, got {$value}");
            }
        }

        foreach (static::$mutuallyExclusivePairs as [$a, $b]) {
            if ($this->getAttribute($a) !== null && $this->getAttribute($b) !== null) {
                throw new \InvalidArgumentException("{$a} and {$b} are mutually exclusive — at most one may be set");
            }
        }

        foreach (static::$exactlyOneOfGroups as $group) {
            $setCount = 0;
            foreach ($group as $column) {
                if ($this->getAttribute($column) !== null) {
                    $setCount++;
                }
            }
            if ($setCount !== 1) {
                $list = implode(', ', $group);
                throw new \InvalidArgumentException("exactly one of [{$list}] must be set, got {$setCount}");
            }
        }

        foreach (static::$coNullablePairs as [$a, $b]) {
            $aSet = $this->getAttribute($a) !== null;
            $bSet = $this->getAttribute($b) !== null;
            if ($aSet !== $bSet) {
                throw new \InvalidArgumentException("{$a} and {$b} must be both null or both set together");
            }
        }

        foreach (static::$allowedValues as $column => $values) {
            $value = $this->getAttribute($column);
            if ($value !== null && !in_array($value, $values, true)) {
                $list = implode(', ', $values);
                throw new \InvalidArgumentException("{$column} must be one of [{$list}], got {$value}");
            }
        }
    }
}
```

- [ ] **Step 4: Write `treasury_financial_documents` migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_financial_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->string('document_type', 32);
            $table->string('status', 32);
            $table->string('posting_path', 16)->nullable();
            $table->decimal('amount', 15, 2);
            $table->ulid('source_wallet_id')->nullable();
            $table->ulid('destination_wallet_id')->nullable();
            $table->ulid('source_party_id')->nullable();
            $table->ulid('destination_party_id')->nullable();
            $table->text('description')->nullable();
            $table->ulid('created_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->ulid('reversed_document_id')->nullable();
            $table->ulid('replacement_document_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tfd_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'tfd_project_id_fk')
                ->references('id')->on('projects');
            $table->foreign('created_by', 'tfd_created_by_fk')
                ->references('id')->on('users');
            $table->foreign('approved_by', 'tfd_approved_by_fk')
                ->references('id')->on('users');

            $table->foreign(['tenant_id', 'source_wallet_id'], 'tfd_src_wallet_fk')
                ->references(['tenant_id', 'id'])->on('treasury_wallets');
            $table->foreign(['tenant_id', 'destination_wallet_id'], 'tfd_dst_wallet_fk')
                ->references(['tenant_id', 'id'])->on('treasury_wallets');
            $table->foreign(['tenant_id', 'source_party_id'], 'tfd_src_party_fk')
                ->references(['tenant_id', 'id'])->on('treasury_financial_parties');
            $table->foreign(['tenant_id', 'destination_party_id'], 'tfd_dst_party_fk')
                ->references(['tenant_id', 'id'])->on('treasury_financial_parties');
            $table->foreign(['tenant_id', 'reversed_document_id'], 'tfd_reversed_doc_fk')
                ->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->foreign(['tenant_id', 'replacement_document_id'], 'tfd_replacement_doc_fk')
                ->references(['tenant_id', 'id'])->on('treasury_financial_documents');

            $table->unique(['tenant_id', 'id'], 'tfd_tenant_id_id_unique');
            $table->unique('reversed_document_id', 'tfd_reversed_document_id_unique');
            $table->index(['tenant_id'], 'tfd_tenant_id_idx');
            $table->index(['project_id'], 'tfd_project_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_financial_documents');
    }
};
```

- [ ] **Step 5: Write `TreasuryFinancialDocument` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Models\Treasury\Concerns\EnforcesRowInvariants;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $document_type
 * @property string $status
 * @property string|null $posting_path
 * @property string $amount
 * @property string|null $source_wallet_id
 * @property string|null $destination_wallet_id
 * @property string|null $source_party_id
 * @property string|null $destination_party_id
 * @property string|null $description
 * @property string $created_by
 * @property string|null $approved_by
 * @property \Illuminate\Support\Carbon|null $posted_at
 * @property string|null $reversed_document_id
 * @property string|null $replacement_document_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryFinancialDocument extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    protected $table = 'treasury_financial_documents';

    public const TYPE_FUNDING = 'funding';
    public const TYPE_INTERNAL_TRANSFER = 'internal_transfer';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_OWNER_CONTRIBUTION = 'owner_contribution';
    public const TYPE_ADVANCE = 'advance';
    public const TYPE_ADVANCE_RETURN = 'advance_return';
    public const TYPE_REVERSAL = 'reversal';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_POSTED_UNRECONCILED = 'posted_unreconciled';
    public const STATUS_POSTED_RECONCILED = 'posted_reconciled';
    public const STATUS_REVERSED = 'reversed';

    public const POSTING_PATH_DIRECT = 'direct';
    public const POSTING_PATH_VIA_ROUTE = 'via_route';

    protected $fillable = [
        'tenant_id', 'project_id', 'document_type', 'status', 'posting_path',
        'amount', 'source_wallet_id', 'destination_wallet_id',
        'source_party_id', 'destination_party_id', 'description',
        'created_by', 'approved_by', 'posted_at',
        'reversed_document_id', 'replacement_document_id',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    protected static array $positiveAmountColumns = ['amount'];

    protected static array $mutuallyExclusivePairs = [
        ['source_wallet_id', 'source_party_id'],
        ['destination_wallet_id', 'destination_party_id'],
    ];

    protected static array $allowedValues = [
        'document_type' => [
            self::TYPE_FUNDING, self::TYPE_INTERNAL_TRANSFER, self::TYPE_EXPENSE,
            self::TYPE_OWNER_CONTRIBUTION, self::TYPE_ADVANCE, self::TYPE_ADVANCE_RETURN,
            self::TYPE_REVERSAL, self::TYPE_ADJUSTMENT,
        ],
        'status' => [
            self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_APPROVED,
            self::STATUS_REJECTED, self::STATUS_POSTED_UNRECONCILED,
            self::STATUS_POSTED_RECONCILED, self::STATUS_REVERSED,
        ],
        'posting_path' => [self::POSTING_PATH_DIRECT, self::POSTING_PATH_VIA_ROUTE],
    ];

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'source_wallet_id');
    }

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'destination_wallet_id');
    }

    /** @return BelongsTo<TreasuryFinancialParty, $this> */
    public function sourceParty(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialParty::class, 'source_party_id');
    }

    /** @return BelongsTo<TreasuryFinancialParty, $this> */
    public function destinationParty(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialParty::class, 'destination_party_id');
    }

    /** @return BelongsTo<TreasuryFinancialDocument, $this> */
    public function reversedDocument(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialDocument::class, 'reversed_document_id');
    }

    /** @return BelongsTo<TreasuryFinancialDocument, $this> */
    public function replacementDocument(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialDocument::class, 'replacement_document_id');
    }
}
```

- [ ] **Step 6: Write the schema test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryFinancialDocumentsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_financial_documents'));
        $this->assertTrue(Schema::hasColumns('treasury_financial_documents', [
            'id', 'tenant_id', 'project_id', 'document_type', 'status',
            'posting_path', 'amount', 'source_wallet_id', 'destination_wallet_id',
            'source_party_id', 'destination_party_id', 'description',
            'created_by', 'approved_by', 'posted_at',
            'reversed_document_id', 'replacement_document_id',
            'created_at', 'updated_at',
        ]));
    }

    public function test_reversed_document_id_is_unique(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = \App\Models\Treasury\TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);

        $original = \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'document_type' => 'funding', 'status' => 'posted_unreconciled',
            'amount' => 100, 'destination_wallet_id' => $wallet->id, 'created_by' => $user->id,
        ]);

        \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'document_type' => 'reversal', 'status' => 'draft',
            'amount' => 100, 'source_wallet_id' => $wallet->id,
            'reversed_document_id' => $original->id, 'created_by' => $user->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'document_type' => 'reversal', 'status' => 'draft',
            'amount' => 100, 'source_wallet_id' => $wallet->id,
            'reversed_document_id' => $original->id, 'created_by' => $user->id,
        ]);
    }
}
```

- [ ] **Step 7: Run migrations and tests to verify they pass**

Run: `php artisan migrate:fresh --seed=false`
Run: `php artisan test --filter=EnforcesRowInvariantsTest`
Run: `php artisan test --filter=TreasuryFinancialDocumentsSchemaTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Models/Treasury/Concerns/EnforcesRowInvariants.php \
        database/migrations/2026_08_17_120000_create_treasury_financial_documents_table.php \
        app/Models/Treasury/TreasuryFinancialDocument.php \
        tests/Unit/Models/Treasury/EnforcesRowInvariantsTest.php \
        tests/Unit/Migrations/Treasury/TreasuryFinancialDocumentsSchemaTest.php
git commit -m "feat(gap-037): add treasury_financial_documents table and EnforcesRowInvariants trait"
```

---

### Task 3: `treasury_payment_routes` + `treasury_payment_route_legs`

**Files:**
- Create: `database/migrations/2026_08_17_130000_create_treasury_payment_routes_table.php`
- Create: `database/migrations/2026_08_17_130100_create_treasury_payment_route_legs_table.php`
- Create: `app/Models/Treasury/TreasuryPaymentRoute.php`
- Create: `app/Models/Treasury/TreasuryPaymentRouteLeg.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryPaymentRoutesSchemaTest.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryPaymentRouteLegsSchemaTest.php`

**Interfaces:**
- Consumes: `TreasuryFinancialDocument`, `TreasuryWallet` (Tasks 1-2), `EnforcesRowInvariants` (Task 2), existing `contract_payments` table.
- Produces: `TreasuryPaymentRoute` (table `treasury_payment_routes`), `TreasuryPaymentRouteLeg` (table `treasury_payment_route_legs`).

- [ ] **Step 1: Write the failing tests**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryPaymentRoutesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_payment_routes'));
        $this->assertTrue(Schema::hasColumns('treasury_payment_routes', [
            'id', 'tenant_id', 'project_id', 'total_allocated_amount', 'status',
            'linked_financial_document_id', 'linked_contract_payment_id',
            'expected_destination_wallet_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_linked_financial_document_id_is_unique(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = \App\Models\Treasury\TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);
        $doc = \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'funding', 'status' => 'draft',
            'amount' => 100, 'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);

        \App\Models\Treasury\TreasuryPaymentRoute::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 100, 'status' => 'planned',
            'linked_financial_document_id' => $doc->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\Treasury\TreasuryPaymentRoute::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'total_allocated_amount' => 50, 'status' => 'planned',
            'linked_financial_document_id' => $doc->id,
        ]);
    }
}
```

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryPaymentRouteLegsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_payment_route_legs'));
        $this->assertTrue(Schema::hasColumns('treasury_payment_route_legs', [
            'id', 'tenant_id', 'payment_route_id', 'sequence_no', 'from_wallet_id',
            'to_wallet_id', 'amount', 'status', 'occurred_at', 'created_at', 'updated_at',
        ]));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TreasuryPaymentRoutesSchemaTest`
Run: `php artisan test --filter=TreasuryPaymentRouteLegsSchemaTest`
Expected: both FAIL — tables/models don't exist yet.

- [ ] **Step 3: Write `treasury_payment_routes` migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_payment_routes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->decimal('total_allocated_amount', 15, 2);
            $table->string('status', 16);
            $table->ulid('linked_financial_document_id')->nullable();
            $table->ulid('linked_contract_payment_id')->nullable();
            $table->ulid('expected_destination_wallet_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tpr_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'tpr_project_id_fk')
                ->references('id')->on('projects');
            $table->foreign('linked_contract_payment_id', 'tpr_linked_cp_fk')
                ->references('id')->on('contract_payments');
            $table->foreign(
                ['tenant_id', 'linked_financial_document_id'],
                'tpr_linked_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->foreign(
                ['tenant_id', 'expected_destination_wallet_id'],
                'tpr_expected_dst_wallet_fk'
            )->references(['tenant_id', 'id'])->on('treasury_wallets');

            $table->unique(['tenant_id', 'id'], 'tpr_tenant_id_id_unique');
            $table->unique('linked_financial_document_id', 'tpr_linked_doc_id_unique');
            $table->index(['tenant_id'], 'tpr_tenant_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_payment_routes');
    }
};
```

- [ ] **Step 4: Write `treasury_payment_route_legs` migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_payment_route_legs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('payment_route_id');
            $table->unsignedInteger('sequence_no');
            $table->ulid('from_wallet_id')->nullable();
            $table->ulid('to_wallet_id');
            $table->decimal('amount', 15, 2);
            $table->string('status', 16);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tprl_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'payment_route_id'],
                'tprl_route_fk'
            )->references(['tenant_id', 'id'])->on('treasury_payment_routes');
            $table->foreign(
                ['tenant_id', 'from_wallet_id'],
                'tprl_from_wallet_fk'
            )->references(['tenant_id', 'id'])->on('treasury_wallets');
            $table->foreign(
                ['tenant_id', 'to_wallet_id'],
                'tprl_to_wallet_fk'
            )->references(['tenant_id', 'id'])->on('treasury_wallets');

            $table->unique(['tenant_id', 'id'], 'tprl_tenant_id_id_unique');
            $table->index(['payment_route_id'], 'tprl_route_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_payment_route_legs');
    }
};
```

- [ ] **Step 5: Write `TreasuryPaymentRoute` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Models\Treasury\Concerns\EnforcesRowInvariants;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $total_allocated_amount
 * @property string $status
 * @property string|null $linked_financial_document_id
 * @property string|null $linked_contract_payment_id
 * @property string|null $expected_destination_wallet_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryPaymentRoute extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    protected $table = 'treasury_payment_routes';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id', 'project_id', 'total_allocated_amount', 'status',
        'linked_financial_document_id', 'linked_contract_payment_id',
        'expected_destination_wallet_id',
    ];

    protected static array $positiveAmountColumns = ['total_allocated_amount'];

    protected static array $exactlyOneOfGroups = [
        ['linked_financial_document_id', 'linked_contract_payment_id'],
    ];

    protected static array $coNullablePairs = [
        ['linked_contract_payment_id', 'expected_destination_wallet_id'],
    ];

    protected static array $allowedValues = [
        'status' => [self::STATUS_PLANNED, self::STATUS_PARTIAL, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
    ];

    /** @return BelongsTo<TreasuryFinancialDocument, $this> */
    public function linkedFinancialDocument(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialDocument::class, 'linked_financial_document_id');
    }

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function expectedDestinationWallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'expected_destination_wallet_id');
    }

    /** @return HasMany<TreasuryPaymentRouteLeg, $this> */
    public function legs(): HasMany
    {
        return $this->hasMany(TreasuryPaymentRouteLeg::class, 'payment_route_id');
    }
}
```

- [ ] **Step 6: Write `TreasuryPaymentRouteLeg` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Models\Treasury\Concerns\EnforcesRowInvariants;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $payment_route_id
 * @property int $sequence_no
 * @property string|null $from_wallet_id
 * @property string $to_wallet_id
 * @property string $amount
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $occurred_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryPaymentRouteLeg extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    protected $table = 'treasury_payment_route_legs';

    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'tenant_id', 'payment_route_id', 'sequence_no', 'from_wallet_id',
        'to_wallet_id', 'amount', 'status', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    protected static array $positiveAmountColumns = ['amount'];

    protected static array $allowedValues = [
        'status' => [self::STATUS_IN_TRANSIT, self::STATUS_SETTLED, self::STATUS_REVERSED],
    ];

    /** @return BelongsTo<TreasuryPaymentRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(TreasuryPaymentRoute::class, 'payment_route_id');
    }

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function fromWallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'from_wallet_id');
    }

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'to_wallet_id');
    }
}
```

- [ ] **Step 7: Run migrations and tests to verify they pass**

Run: `php artisan migrate:fresh --seed=false`
Run: `php artisan test --filter=TreasuryPaymentRoutesSchemaTest`
Run: `php artisan test --filter=TreasuryPaymentRouteLegsSchemaTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_17_130000_create_treasury_payment_routes_table.php \
        database/migrations/2026_08_17_130100_create_treasury_payment_route_legs_table.php \
        app/Models/Treasury/TreasuryPaymentRoute.php \
        app/Models/Treasury/TreasuryPaymentRouteLeg.php \
        tests/Unit/Migrations/Treasury/TreasuryPaymentRoutesSchemaTest.php \
        tests/Unit/Migrations/Treasury/TreasuryPaymentRouteLegsSchemaTest.php
git commit -m "feat(gap-037): add treasury_payment_routes and treasury_payment_route_legs tables"
```

---

### Task 4: `treasury_ledger_entries`

**Files:**
- Create: `database/migrations/2026_08_17_140000_create_treasury_ledger_entries_table.php`
- Create: `app/Models/Treasury/TreasuryLedgerEntry.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryLedgerEntriesSchemaTest.php`

**Interfaces:**
- Consumes: `TreasuryFinancialDocument`, `TreasuryPaymentRouteLeg`, `TreasuryWallet` (Tasks 1-3), `EnforcesRowInvariants`.
- Produces: `TreasuryLedgerEntry` (table `treasury_ledger_entries`, **`created_at` only, no `updated_at`** — append-only per §2.1a).

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryLedgerEntriesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_ledger_entries'));
        $this->assertTrue(Schema::hasColumns('treasury_ledger_entries', [
            'id', 'tenant_id', 'source_financial_document_id', 'source_payment_route_leg_id',
            'wallet_id', 'direction', 'amount', 'entry_type', 'posted_at',
            'reversal_of_entry_id', 'original_posting_key', 'created_at',
        ]));
        $this->assertFalse(
            Schema::hasColumn('treasury_ledger_entries', 'updated_at'),
            'treasury_ledger_entries is append-only per design-doc Sec 2.1a — it must not have an updated_at column'
        );
    }

    public function test_original_posting_key_is_unique(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $wallet = \App\Models\Treasury\TreasuryWallet::create([
            'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
        ]);
        $doc = \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'document_type' => 'funding', 'status' => 'posted_unreconciled',
            'amount' => 100, 'destination_wallet_id' => $wallet->id,
            'created_by' => $user->id,
        ]);

        \App\Models\Treasury\TreasuryLedgerEntry::create([
            'tenant_id' => $tenant->id, 'source_financial_document_id' => $doc->id,
            'wallet_id' => $wallet->id, 'direction' => 'credit', 'amount' => 100,
            'entry_type' => 'funding_credit', 'posted_at' => now(),
            'original_posting_key' => "doc:{$doc->id}:credit",
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\Treasury\TreasuryLedgerEntry::create([
            'tenant_id' => $tenant->id, 'source_financial_document_id' => $doc->id,
            'wallet_id' => $wallet->id, 'direction' => 'credit', 'amount' => 100,
            'entry_type' => 'funding_credit', 'posted_at' => now(),
            'original_posting_key' => "doc:{$doc->id}:credit",
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TreasuryLedgerEntriesSchemaTest`
Expected: FAIL — table/model don't exist yet.

- [ ] **Step 3: Write migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_ledger_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('source_financial_document_id')->nullable();
            $table->ulid('source_payment_route_leg_id')->nullable();
            $table->ulid('wallet_id');
            $table->string('direction', 8);
            $table->decimal('amount', 15, 2);
            $table->string('entry_type', 64);
            $table->timestamp('posted_at');
            $table->ulid('reversal_of_entry_id')->nullable();
            $table->string('original_posting_key');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id', 'tle_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'source_financial_document_id'],
                'tle_src_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->foreign(
                ['tenant_id', 'source_payment_route_leg_id'],
                'tle_src_leg_fk'
            )->references(['tenant_id', 'id'])->on('treasury_payment_route_legs');
            $table->foreign(
                ['tenant_id', 'wallet_id'],
                'tle_wallet_fk'
            )->references(['tenant_id', 'id'])->on('treasury_wallets');
            $table->foreign(
                ['tenant_id', 'reversal_of_entry_id'],
                'tle_reversal_of_fk'
            )->references(['tenant_id', 'id'])->on('treasury_ledger_entries');

            $table->unique(['tenant_id', 'id'], 'tle_tenant_id_id_unique');
            $table->unique('reversal_of_entry_id', 'tle_reversal_of_entry_unique');
            $table->unique('original_posting_key', 'tle_original_posting_key_unique');
            $table->index(['source_financial_document_id'], 'tle_src_doc_idx');
            $table->index(['source_payment_route_leg_id'], 'tle_src_leg_idx');
            $table->index(['wallet_id', 'posted_at'], 'tle_wallet_posted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_ledger_entries');
    }
};
```

- [ ] **Step 4: Write `TreasuryLedgerEntry` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Models\Treasury\Concerns\EnforcesRowInvariants;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $source_financial_document_id
 * @property string|null $source_payment_route_leg_id
 * @property string $wallet_id
 * @property string $direction
 * @property string $amount
 * @property string $entry_type
 * @property \Illuminate\Support\Carbon $posted_at
 * @property string|null $reversal_of_entry_id
 * @property string $original_posting_key
 * @property \Illuminate\Support\Carbon $created_at
 */
class TreasuryLedgerEntry extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    public const UPDATED_AT = null;

    protected $table = 'treasury_ledger_entries';

    public const DIRECTION_DEBIT = 'debit';
    public const DIRECTION_CREDIT = 'credit';

    protected $fillable = [
        'tenant_id', 'source_financial_document_id', 'source_payment_route_leg_id',
        'wallet_id', 'direction', 'amount', 'entry_type', 'posted_at',
        'reversal_of_entry_id', 'original_posting_key',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    protected static array $positiveAmountColumns = ['amount'];

    protected static array $exactlyOneOfGroups = [
        ['source_financial_document_id', 'source_payment_route_leg_id'],
    ];

    protected static array $allowedValues = [
        'direction' => [self::DIRECTION_DEBIT, self::DIRECTION_CREDIT],
    ];

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'wallet_id');
    }
}
```

- [ ] **Step 5: Run migration and test to verify they pass**

Run: `php artisan migrate:fresh --seed=false`
Run: `php artisan test --filter=TreasuryLedgerEntriesSchemaTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_17_140000_create_treasury_ledger_entries_table.php \
        app/Models/Treasury/TreasuryLedgerEntry.php \
        tests/Unit/Migrations/Treasury/TreasuryLedgerEntriesSchemaTest.php
git commit -m "feat(gap-037): add treasury_ledger_entries table"
```

---

### Task 5: `treasury_fund_chains`

**Files:**
- Create: `database/migrations/2026_08_17_150000_create_treasury_fund_chains_table.php`
- Create: `app/Models/Treasury/TreasuryFundChain.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryFundChainsSchemaTest.php`

**Interfaces:**
- Produces: `TreasuryFundChain` (table `treasury_fund_chains`). Task 9's `treasury_fund_chain_members` targets it.

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryFundChainsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_fund_chains'));
        $this->assertTrue(Schema::hasColumns('treasury_fund_chains', [
            'id', 'tenant_id', 'project_id', 'chain_reference', 'description',
            'created_at', 'updated_at',
        ]));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TreasuryFundChainsSchemaTest`
Expected: FAIL.

- [ ] **Step 3: Write migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_fund_chains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->string('chain_reference');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tfc_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'tfc_project_id_fk')
                ->references('id')->on('projects');

            $table->unique(['tenant_id', 'id'], 'tfc_tenant_id_id_unique');
            $table->index(['tenant_id'], 'tfc_tenant_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_fund_chains');
    }
};
```

- [ ] **Step 4: Write `TreasuryFundChain` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $chain_reference
 * @property string|null $description
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryFundChain extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'treasury_fund_chains';

    protected $fillable = ['tenant_id', 'project_id', 'chain_reference', 'description'];
}
```

- [ ] **Step 5: Run migration and test to verify they pass**

Run: `php artisan migrate:fresh --seed=false`
Run: `php artisan test --filter=TreasuryFundChainsSchemaTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_17_150000_create_treasury_fund_chains_table.php \
        app/Models/Treasury/TreasuryFundChain.php \
        tests/Unit/Migrations/Treasury/TreasuryFundChainsSchemaTest.php
git commit -m "feat(gap-037): add treasury_fund_chains table"
```

---

### Task 6: `treasury_advances` + `treasury_advance_settlements`

**Files:**
- Create: `database/migrations/2026_08_17_160000_create_treasury_advances_table.php`
- Create: `database/migrations/2026_08_17_160100_create_treasury_advance_settlements_table.php`
- Create: `app/Models/Treasury/TreasuryAdvance.php`
- Create: `app/Models/Treasury/TreasuryAdvanceSettlement.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryAdvancesSchemaTest.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryAdvanceSettlementsSchemaTest.php`

**Interfaces:**
- Consumes: `TreasuryFinancialDocument`, `TreasuryFinancialParty` (Tasks 1-2), `EnforcesRowInvariants`.
- Produces: `TreasuryAdvance` (table `treasury_advances`, both timestamps), `TreasuryAdvanceSettlement` (table `treasury_advance_settlements`, **`created_at` only** — event-log per §2.1a).

- [ ] **Step 1: Write the failing tests**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryAdvancesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_advances'));
        $this->assertTrue(Schema::hasColumns('treasury_advances', [
            'id', 'tenant_id', 'project_id', 'financial_party_id',
            'originating_financial_document_id', 'amount', 'created_at', 'updated_at',
        ]));
    }

    public function test_originating_financial_document_id_is_unique(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);
        $project = \App\Models\Project::factory()->create(['tenant_id' => $tenant->id]);
        $party = \App\Models\Treasury\TreasuryFinancialParty::create([
            'tenant_id' => $tenant->id, 'party_type' => 'vendor', 'name' => 'P',
        ]);
        $doc = \App\Models\Treasury\TreasuryFinancialDocument::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'document_type' => 'advance', 'status' => 'posted_unreconciled',
            'amount' => 100, 'source_wallet_id' => \App\Models\Treasury\TreasuryWallet::create([
                'tenant_id' => $tenant->id, 'wallet_type' => 'bank', 'name' => 'W',
            ])->id,
            'destination_party_id' => $party->id,
            'created_by' => $user->id,
        ]);

        \App\Models\Treasury\TreasuryAdvance::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'financial_party_id' => $party->id,
            'originating_financial_document_id' => $doc->id, 'amount' => 100,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\Treasury\TreasuryAdvance::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'financial_party_id' => $party->id,
            'originating_financial_document_id' => $doc->id, 'amount' => 100,
        ]);
    }
}
```

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryAdvanceSettlementsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_advance_settlements'));
        $this->assertTrue(Schema::hasColumns('treasury_advance_settlements', [
            'id', 'tenant_id', 'advance_id', 'settlement_type', 'direction', 'amount',
            'financial_document_id', 'reverses_settlement_id', 'created_at',
        ]));
        $this->assertFalse(
            Schema::hasColumn('treasury_advance_settlements', 'updated_at'),
            'treasury_advance_settlements is an event-log table per Sec 2.1a — no updated_at'
        );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TreasuryAdvancesSchemaTest`
Run: `php artisan test --filter=TreasuryAdvanceSettlementsSchemaTest`
Expected: both FAIL.

- [ ] **Step 3: Write `treasury_advances` migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_advances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->ulid('financial_party_id');
            $table->ulid('originating_financial_document_id');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->foreign('tenant_id', 'ta_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'ta_project_id_fk')
                ->references('id')->on('projects');
            $table->foreign(
                ['tenant_id', 'financial_party_id'],
                'ta_financial_party_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_parties');
            $table->foreign(
                ['tenant_id', 'originating_financial_document_id'],
                'ta_originating_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');

            $table->unique(['tenant_id', 'id'], 'ta_tenant_id_id_unique');
            $table->unique('originating_financial_document_id', 'ta_originating_doc_unique');
            $table->index(['tenant_id'], 'ta_tenant_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_advances');
    }
};
```

- [ ] **Step 4: Write `treasury_advance_settlements` migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_advance_settlements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('advance_id');
            $table->string('settlement_type', 32);
            $table->string('direction', 8);
            $table->decimal('amount', 15, 2);
            $table->ulid('financial_document_id')->nullable();
            $table->ulid('reverses_settlement_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id', 'tas_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'advance_id'],
                'tas_advance_fk'
            )->references(['tenant_id', 'id'])->on('treasury_advances');
            $table->foreign(
                ['tenant_id', 'financial_document_id'],
                'tas_financial_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->foreign(
                ['tenant_id', 'reverses_settlement_id'],
                'tas_reverses_settlement_fk'
            )->references(['tenant_id', 'id'])->on('treasury_advance_settlements');

            $table->unique(['tenant_id', 'id'], 'tas_tenant_id_id_unique');
            $table->unique('reverses_settlement_id', 'tas_reverses_settlement_unique');
            $table->unique('financial_document_id', 'tas_financial_document_unique');
            $table->index(['advance_id'], 'tas_advance_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_advance_settlements');
    }
};
```

- [ ] **Step 5: Write `TreasuryAdvance` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Models\Treasury\Concerns\EnforcesRowInvariants;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $financial_party_id
 * @property string $originating_financial_document_id
 * @property string $amount
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryAdvance extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    protected $table = 'treasury_advances';

    protected $fillable = [
        'tenant_id', 'project_id', 'financial_party_id',
        'originating_financial_document_id', 'amount',
    ];

    protected static array $positiveAmountColumns = ['amount'];

    /** @return BelongsTo<TreasuryFinancialParty, $this> */
    public function financialParty(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialParty::class, 'financial_party_id');
    }

    /** @return BelongsTo<TreasuryFinancialDocument, $this> */
    public function originatingFinancialDocument(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialDocument::class, 'originating_financial_document_id');
    }

    /** @return HasMany<TreasuryAdvanceSettlement, $this> */
    public function settlements(): HasMany
    {
        return $this->hasMany(TreasuryAdvanceSettlement::class, 'advance_id');
    }
}
```

- [ ] **Step 6: Write `TreasuryAdvanceSettlement` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Models\Treasury\Concerns\EnforcesRowInvariants;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $advance_id
 * @property string $settlement_type
 * @property string $direction
 * @property string $amount
 * @property string|null $financial_document_id
 * @property string|null $reverses_settlement_id
 * @property \Illuminate\Support\Carbon $created_at
 */
class TreasuryAdvanceSettlement extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    public const UPDATED_AT = null;

    protected $table = 'treasury_advance_settlements';

    public const SETTLEMENT_TYPE_APPROVED_EXPENSE = 'approved_expense';
    public const SETTLEMENT_TYPE_CASH_RETURN = 'cash_return';

    public const DIRECTION_APPLY = 'apply';
    public const DIRECTION_REVERSE = 'reverse';

    protected $fillable = [
        'tenant_id', 'advance_id', 'settlement_type', 'direction', 'amount',
        'financial_document_id', 'reverses_settlement_id',
    ];

    protected static array $positiveAmountColumns = ['amount'];

    protected static array $allowedValues = [
        'settlement_type' => [self::SETTLEMENT_TYPE_APPROVED_EXPENSE, self::SETTLEMENT_TYPE_CASH_RETURN],
        'direction' => [self::DIRECTION_APPLY, self::DIRECTION_REVERSE],
    ];

    /** @return BelongsTo<TreasuryAdvance, $this> */
    public function advance(): BelongsTo
    {
        return $this->belongsTo(TreasuryAdvance::class, 'advance_id');
    }
}
```

- [ ] **Step 7: Run migrations and tests to verify they pass**

Run: `php artisan migrate:fresh --seed=false`
Run: `php artisan test --filter=TreasuryAdvancesSchemaTest`
Run: `php artisan test --filter=TreasuryAdvanceSettlementsSchemaTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_17_160000_create_treasury_advances_table.php \
        database/migrations/2026_08_17_160100_create_treasury_advance_settlements_table.php \
        app/Models/Treasury/TreasuryAdvance.php \
        app/Models/Treasury/TreasuryAdvanceSettlement.php \
        tests/Unit/Migrations/Treasury/TreasuryAdvancesSchemaTest.php \
        tests/Unit/Migrations/Treasury/TreasuryAdvanceSettlementsSchemaTest.php
git commit -m "feat(gap-037): add treasury_advances and treasury_advance_settlements tables"
```

---

### Task 7: `treasury_cost_settlement_allocations`

**Files:**
- Create: `database/migrations/2026_08_17_170000_create_treasury_cost_settlement_allocations_table.php`
- Create: `app/Models/Treasury/TreasuryCostSettlementAllocation.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryCostSettlementAllocationsSchemaTest.php`

**Interfaces:**
- Consumes: `TreasuryFinancialDocument` (Task 2), `TreasuryAdvanceSettlement` (Task 6), `EnforcesRowInvariants`, existing `contract_expenses`/`material_receipt_lines` tables.
- Produces: `TreasuryCostSettlementAllocation` (table `treasury_cost_settlement_allocations`, **`created_at` only**).

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryCostSettlementAllocationsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_cost_settlement_allocations'));
        $this->assertTrue(Schema::hasColumns('treasury_cost_settlement_allocations', [
            'id', 'tenant_id', 'financial_document_id', 'advance_settlement_id',
            'cost_source_contract_expense_id', 'cost_source_material_receipt_line_id',
            'direction', 'allocated_amount', 'reverses_allocation_id', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('treasury_cost_settlement_allocations', 'updated_at'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TreasuryCostSettlementAllocationsSchemaTest`
Expected: FAIL.

- [ ] **Step 3: Write migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_cost_settlement_allocations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('financial_document_id')->nullable();
            $table->ulid('advance_settlement_id')->nullable();
            $table->ulid('cost_source_contract_expense_id')->nullable();
            $table->ulid('cost_source_material_receipt_line_id')->nullable();
            $table->string('direction', 8);
            $table->decimal('allocated_amount', 15, 2);
            $table->ulid('reverses_allocation_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id', 'tcsa_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'financial_document_id'],
                'tcsa_financial_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->foreign(
                ['tenant_id', 'advance_settlement_id'],
                'tcsa_advance_settlement_fk'
            )->references(['tenant_id', 'id'])->on('treasury_advance_settlements');
            $table->foreign('cost_source_contract_expense_id', 'tcsa_cost_source_ce_fk')
                ->references('id')->on('contract_expenses');
            $table->foreign('cost_source_material_receipt_line_id', 'tcsa_cost_source_mrl_fk')
                ->references('id')->on('material_receipt_lines');
            $table->foreign(
                ['tenant_id', 'reverses_allocation_id'],
                'tcsa_reverses_allocation_fk'
            )->references(['tenant_id', 'id'])->on('treasury_cost_settlement_allocations');

            $table->unique(['tenant_id', 'id'], 'tcsa_tenant_id_id_unique');
            $table->unique('reverses_allocation_id', 'tcsa_reverses_allocation_unique');
            $table->index(['financial_document_id'], 'tcsa_financial_doc_idx');
            $table->index(['advance_settlement_id'], 'tcsa_advance_settlement_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_cost_settlement_allocations');
    }
};
```

- [ ] **Step 4: Write `TreasuryCostSettlementAllocation` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Models\Treasury\Concerns\EnforcesRowInvariants;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $financial_document_id
 * @property string|null $advance_settlement_id
 * @property string|null $cost_source_contract_expense_id
 * @property string|null $cost_source_material_receipt_line_id
 * @property string $direction
 * @property string $allocated_amount
 * @property string|null $reverses_allocation_id
 * @property \Illuminate\Support\Carbon $created_at
 */
class TreasuryCostSettlementAllocation extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    public const UPDATED_AT = null;

    protected $table = 'treasury_cost_settlement_allocations';

    public const DIRECTION_APPLY = 'apply';
    public const DIRECTION_REVERSE = 'reverse';

    protected $fillable = [
        'tenant_id', 'financial_document_id', 'advance_settlement_id',
        'cost_source_contract_expense_id', 'cost_source_material_receipt_line_id',
        'direction', 'allocated_amount', 'reverses_allocation_id',
    ];

    protected static array $positiveAmountColumns = ['allocated_amount'];

    protected static array $exactlyOneOfGroups = [
        ['financial_document_id', 'advance_settlement_id'],
        ['cost_source_contract_expense_id', 'cost_source_material_receipt_line_id'],
    ];

    protected static array $allowedValues = [
        'direction' => [self::DIRECTION_APPLY, self::DIRECTION_REVERSE],
    ];

    /** @return BelongsTo<TreasuryFinancialDocument, $this> */
    public function financialDocument(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialDocument::class, 'financial_document_id');
    }

    /** @return BelongsTo<TreasuryAdvanceSettlement, $this> */
    public function advanceSettlement(): BelongsTo
    {
        return $this->belongsTo(TreasuryAdvanceSettlement::class, 'advance_settlement_id');
    }
}
```

- [ ] **Step 5: Run migration and test to verify they pass**

Run: `php artisan migrate:fresh --seed=false`
Run: `php artisan test --filter=TreasuryCostSettlementAllocationsSchemaTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_17_170000_create_treasury_cost_settlement_allocations_table.php \
        app/Models/Treasury/TreasuryCostSettlementAllocation.php \
        tests/Unit/Migrations/Treasury/TreasuryCostSettlementAllocationsSchemaTest.php
git commit -m "feat(gap-037): add treasury_cost_settlement_allocations table"
```

---

### Task 8: `treasury_expense_approvals`

**Files:**
- Create: `database/migrations/2026_08_17_180000_create_treasury_expense_approvals_table.php`
- Create: `app/Models/Treasury/TreasuryExpenseApproval.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryExpenseApprovalsSchemaTest.php`

**Interfaces:**
- Consumes: `TreasuryFinancialDocument` (Task 2).
- Produces: `TreasuryExpenseApproval` (table `treasury_expense_approvals`, **`created_at` only**).

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryExpenseApprovalsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_expense_approvals'));
        $this->assertTrue(Schema::hasColumns('treasury_expense_approvals', [
            'id', 'tenant_id', 'financial_document_id', 'event', 'from_status',
            'to_status', 'actor_id', 'note', 'context', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('treasury_expense_approvals', 'updated_at'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TreasuryExpenseApprovalsSchemaTest`
Expected: FAIL.

- [ ] **Step 3: Write migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_expense_approvals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('financial_document_id');
            $table->string('event', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->ulid('actor_id');
            $table->text('note')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id', 'tea_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'financial_document_id'],
                'tea_financial_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->foreign('actor_id', 'tea_actor_id_fk')
                ->references('id')->on('users');

            $table->unique(['tenant_id', 'id'], 'tea_tenant_id_id_unique');
            $table->index(['financial_document_id'], 'tea_financial_doc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_expense_approvals');
    }
};
```

- [ ] **Step 4: Write `TreasuryExpenseApproval` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $financial_document_id
 * @property string $event
 * @property string|null $from_status
 * @property string $to_status
 * @property string $actor_id
 * @property string|null $note
 * @property array<string,mixed>|null $context
 * @property \Illuminate\Support\Carbon $created_at
 */
class TreasuryExpenseApproval extends Model
{
    use HasUlids;
    use TenantScope;

    public const UPDATED_AT = null;

    protected $table = 'treasury_expense_approvals';

    protected $fillable = [
        'tenant_id', 'financial_document_id', 'event', 'from_status',
        'to_status', 'actor_id', 'note', 'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    /** @return BelongsTo<TreasuryFinancialDocument, $this> */
    public function financialDocument(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialDocument::class, 'financial_document_id');
    }
}
```

- [ ] **Step 5: Run migration and test to verify they pass**

Run: `php artisan migrate:fresh --seed=false`
Run: `php artisan test --filter=TreasuryExpenseApprovalsSchemaTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_17_180000_create_treasury_expense_approvals_table.php \
        app/Models/Treasury/TreasuryExpenseApproval.php \
        tests/Unit/Migrations/Treasury/TreasuryExpenseApprovalsSchemaTest.php
git commit -m "feat(gap-037): add treasury_expense_approvals table"
```

---

### Task 9: `treasury_reconciliations` + `treasury_fund_chain_members` + `treasury_reconciliation_entries`

**Files:**
- Create: `database/migrations/2026_08_17_190000_create_treasury_reconciliations_table.php`
- Create: `database/migrations/2026_08_17_190100_create_treasury_fund_chain_members_table.php`
- Create: `database/migrations/2026_08_17_190200_create_treasury_reconciliation_entries_table.php`
- Create: `app/Models/Treasury/TreasuryReconciliation.php`
- Create: `app/Models/Treasury/TreasuryFundChainMember.php`
- Create: `app/Models/Treasury/TreasuryReconciliationEntry.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryReconciliationsSchemaTest.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryFundChainMembersSchemaTest.php`
- Test: `tests/Unit/Migrations/Treasury/TreasuryReconciliationEntriesSchemaTest.php`

**Interfaces:**
- Consumes: `TreasuryWallet` (Task 1), `TreasuryFinancialDocument` (Task 2), `TreasuryPaymentRoute` (Task 3), `TreasuryLedgerEntry` (Task 4), `TreasuryFundChain` (Task 5), `EnforcesRowInvariants`.
- Produces: `TreasuryReconciliation` (both timestamps), `TreasuryFundChainMember` (both timestamps), `TreasuryReconciliationEntry` (**`created_at` only**). This is the last task — every table from design-doc §16 exists after this task.

- [ ] **Step 1: Write the failing tests**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryReconciliationsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_reconciliations'));
        $this->assertTrue(Schema::hasColumns('treasury_reconciliations', [
            'id', 'tenant_id', 'wallet_id', 'reconciliation_type', 'external_reference',
            'reconciled_at', 'reconciled_by', 'created_at', 'updated_at',
        ]));
    }
}
```

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryFundChainMembersSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_fund_chain_members'));
        $this->assertTrue(Schema::hasColumns('treasury_fund_chain_members', [
            'id', 'tenant_id', 'fund_chain_id', 'member_financial_document_id',
            'member_payment_route_id', 'created_at', 'updated_at',
        ]));
    }
}
```

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TreasuryReconciliationEntriesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('treasury_reconciliation_entries'));
        $this->assertTrue(Schema::hasColumns('treasury_reconciliation_entries', [
            'id', 'tenant_id', 'reconciliation_id', 'ledger_entry_id', 'direction',
            'reverses_reconciliation_entry_id', 'actor_id', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('treasury_reconciliation_entries', 'updated_at'));
    }

    public function test_all_fourteen_treasury_tables_exist(): void
    {
        foreach ([
            'treasury_financial_parties', 'treasury_wallets', 'treasury_financial_documents',
            'treasury_payment_routes', 'treasury_payment_route_legs', 'treasury_ledger_entries',
            'treasury_fund_chains', 'treasury_advances', 'treasury_advance_settlements',
            'treasury_cost_settlement_allocations', 'treasury_expense_approvals',
            'treasury_reconciliations', 'treasury_fund_chain_members', 'treasury_reconciliation_entries',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} must exist per design-doc Sec 16's 14-table inventory");
        }
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TreasuryReconciliationsSchemaTest`
Run: `php artisan test --filter=TreasuryFundChainMembersSchemaTest`
Run: `php artisan test --filter=TreasuryReconciliationEntriesSchemaTest`
Expected: all FAIL.

- [ ] **Step 3: Write `treasury_reconciliations` migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_reconciliations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('wallet_id');
            $table->string('reconciliation_type', 32);
            $table->string('external_reference')->nullable();
            $table->timestamp('reconciled_at');
            $table->ulid('reconciled_by');
            $table->timestamps();

            $table->foreign('tenant_id', 'tr_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'wallet_id'],
                'tr_wallet_fk'
            )->references(['tenant_id', 'id'])->on('treasury_wallets');
            $table->foreign('reconciled_by', 'tr_reconciled_by_fk')
                ->references('id')->on('users');

            $table->unique(['tenant_id', 'id'], 'tr_tenant_id_id_unique');
            $table->index(['wallet_id'], 'tr_wallet_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_reconciliations');
    }
};
```

- [ ] **Step 4: Write `treasury_fund_chain_members` migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_fund_chain_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('fund_chain_id');
            $table->ulid('member_financial_document_id')->nullable();
            $table->ulid('member_payment_route_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id', 'tfcm_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'fund_chain_id'],
                'tfcm_fund_chain_fk'
            )->references(['tenant_id', 'id'])->on('treasury_fund_chains');
            $table->foreign(
                ['tenant_id', 'member_financial_document_id'],
                'tfcm_member_doc_fk'
            )->references(['tenant_id', 'id'])->on('treasury_financial_documents');
            $table->foreign(
                ['tenant_id', 'member_payment_route_id'],
                'tfcm_member_route_fk'
            )->references(['tenant_id', 'id'])->on('treasury_payment_routes');

            $table->unique(['tenant_id', 'id'], 'tfcm_tenant_id_id_unique');
            $table->unique('member_financial_document_id', 'tfcm_member_doc_unique');
            $table->unique('member_payment_route_id', 'tfcm_member_route_unique');
            $table->index(['fund_chain_id'], 'tfcm_fund_chain_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_fund_chain_members');
    }
};
```

- [ ] **Step 5: Write `treasury_reconciliation_entries` migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_reconciliation_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('reconciliation_id');
            $table->ulid('ledger_entry_id');
            $table->string('direction', 8);
            $table->ulid('reverses_reconciliation_entry_id')->nullable();
            $table->ulid('actor_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id', 'trce_tenant_id_fk')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign(
                ['tenant_id', 'reconciliation_id'],
                'trce_reconciliation_fk'
            )->references(['tenant_id', 'id'])->on('treasury_reconciliations');
            $table->foreign(
                ['tenant_id', 'ledger_entry_id'],
                'trce_ledger_entry_fk'
            )->references(['tenant_id', 'id'])->on('treasury_ledger_entries');
            $table->foreign(
                ['tenant_id', 'reverses_reconciliation_entry_id'],
                'trce_reverses_entry_fk'
            )->references(['tenant_id', 'id'])->on('treasury_reconciliation_entries');
            $table->foreign('actor_id', 'trce_actor_id_fk')
                ->references('id')->on('users');

            $table->unique(['tenant_id', 'id'], 'trce_tenant_id_id_unique');
            $table->unique('reverses_reconciliation_entry_id', 'trce_reverses_entry_unique');
            $table->index(['reconciliation_id'], 'trce_reconciliation_idx');
            $table->index(['ledger_entry_id'], 'trce_ledger_entry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_reconciliation_entries');
    }
};
```

- [ ] **Step 6: Write `TreasuryReconciliation` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $wallet_id
 * @property string $reconciliation_type
 * @property string|null $external_reference
 * @property \Illuminate\Support\Carbon $reconciled_at
 * @property string $reconciled_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryReconciliation extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'treasury_reconciliations';

    protected $fillable = [
        'tenant_id', 'wallet_id', 'reconciliation_type', 'external_reference',
        'reconciled_at', 'reconciled_by',
    ];

    protected $casts = [
        'reconciled_at' => 'datetime',
    ];

    /** @return BelongsTo<TreasuryWallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(TreasuryWallet::class, 'wallet_id');
    }
}
```

- [ ] **Step 7: Write `TreasuryFundChainMember` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Models\Treasury\Concerns\EnforcesRowInvariants;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $fund_chain_id
 * @property string|null $member_financial_document_id
 * @property string|null $member_payment_route_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryFundChainMember extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    protected $table = 'treasury_fund_chain_members';

    protected $fillable = [
        'tenant_id', 'fund_chain_id', 'member_financial_document_id', 'member_payment_route_id',
    ];

    protected static array $exactlyOneOfGroups = [
        ['member_financial_document_id', 'member_payment_route_id'],
    ];

    /** @return BelongsTo<TreasuryFundChain, $this> */
    public function fundChain(): BelongsTo
    {
        return $this->belongsTo(TreasuryFundChain::class, 'fund_chain_id');
    }
}
```

- [ ] **Step 8: Write `TreasuryReconciliationEntry` model**

```php
<?php declare(strict_types=1);

namespace App\Models\Treasury;

use App\Models\Treasury\Concerns\EnforcesRowInvariants;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $reconciliation_id
 * @property string $ledger_entry_id
 * @property string $direction
 * @property string|null $reverses_reconciliation_entry_id
 * @property string $actor_id
 * @property \Illuminate\Support\Carbon $created_at
 */
class TreasuryReconciliationEntry extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesRowInvariants;

    public const UPDATED_AT = null;

    protected $table = 'treasury_reconciliation_entries';

    public const DIRECTION_APPLY = 'apply';
    public const DIRECTION_REVERSE = 'reverse';

    protected $fillable = [
        'tenant_id', 'reconciliation_id', 'ledger_entry_id', 'direction',
        'reverses_reconciliation_entry_id', 'actor_id',
    ];

    protected static array $allowedValues = [
        'direction' => [self::DIRECTION_APPLY, self::DIRECTION_REVERSE],
    ];

    /** @return BelongsTo<TreasuryReconciliation, $this> */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(TreasuryReconciliation::class, 'reconciliation_id');
    }

    /** @return BelongsTo<TreasuryLedgerEntry, $this> */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(TreasuryLedgerEntry::class, 'ledger_entry_id');
    }
}
```

- [ ] **Step 9: Run migrations and tests to verify they pass**

Run: `php artisan migrate:fresh --seed=false`
Run: `php artisan test --filter=TreasuryReconciliationsSchemaTest`
Run: `php artisan test --filter=TreasuryFundChainMembersSchemaTest`
Run: `php artisan test --filter=TreasuryReconciliationEntriesSchemaTest`
Expected: all PASS, including `test_all_fourteen_treasury_tables_exist`.

- [ ] **Step 10: Run the full Treasury test group and the repo's owner-governance lint**

Run: `php artisan test --filter=Treasury`
Expected: every test from Tasks 1-9 passes together (no cross-task migration-order or naming collisions).

Run: `php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering`
Expected: PASS — this plan's file lives under `docs/superpowers/plans/`, not `docs/owner-decisions/`, so it does not affect gate-ordering lint, but running it confirms nothing in this slice accidentally touched a governance packet.

- [ ] **Step 11: Commit**

```bash
git add database/migrations/2026_08_17_190000_create_treasury_reconciliations_table.php \
        database/migrations/2026_08_17_190100_create_treasury_fund_chain_members_table.php \
        database/migrations/2026_08_17_190200_create_treasury_reconciliation_entries_table.php \
        app/Models/Treasury/TreasuryReconciliation.php \
        app/Models/Treasury/TreasuryFundChainMember.php \
        app/Models/Treasury/TreasuryReconciliationEntry.php \
        tests/Unit/Migrations/Treasury/TreasuryReconciliationsSchemaTest.php \
        tests/Unit/Migrations/Treasury/TreasuryFundChainMembersSchemaTest.php \
        tests/Unit/Migrations/Treasury/TreasuryReconciliationEntriesSchemaTest.php
git commit -m "feat(gap-037): add treasury_reconciliations, treasury_fund_chain_members, treasury_reconciliation_entries tables — all 14 GAP-037 tables complete"
```

---

## What this plan deliberately does NOT build

Per the design doc's own repeated scope boundary (every revision v2-v17 restates it) and the Owner's Gate 2 approval decision text, this plan produces **only** the 14 tables and structural models. It explicitly does not build:

- Any controller, service, route, or UI.
- The reversal state machine (§2.2/§2.2a-e) — no code sets `posting_path`, transitions `status`, or fires the dependent-state coupling (§2.2b).
- Settlement conservation enforcement (§6) or the advance/cash-return atomic-write rules (§7.5-§7.8).
- The lock-order orchestration (§11) — that only matters once multi-row writes exist, which this plan does not create.
- Reconciliation lifecycle transitions (§12.1/§12.2).
- Seed/backfill data.

These are the next GAP-037 implementation slice(s), to be scoped and planned separately once this migration/model foundation is reviewed and merged. Building them now, in the same plan, would violate the same "don't guess at scope on a financial schema" discipline this GAP has maintained across all 17 Gate 2 rounds.
