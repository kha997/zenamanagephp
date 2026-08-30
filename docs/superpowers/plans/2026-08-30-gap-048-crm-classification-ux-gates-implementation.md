# GAP-048 — CRM Classification UX & Gates — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the Owner-approved GAP-048 Gate-2 design (`docs/owner-decisions/GAP-048/02-design.md`,
`docs/superpowers/specs/2026-08-30-gap-048-crm-classification-ux-gates-design.md`): a canonical
multi-valued Service-Line classification UX with explicit CONFIRMED confirmation, a shared
legacy→canonical mapper reused by all three write paths, pipeline/Quote/WON gates backed by one
shared CONFIRMED predicate, row-level Opportunity locking with a canonical lock order to close the
Owner-identified concurrency race, and narrow compatibility bridges for the two legacy read-side
consumers.

**Architecture:** Extend the existing GAP-046 foundation (`opportunity_service_lines`,
`EnforcesServiceLineIntegrity`, `ServiceLine`/`ServiceLineProvenance`) — no new tables. Add one
shared mapper class, one shared predicate method on `Opportunity`, one new atomic reconciliation
service, and insert `lockForUpdate()` + gate checks at exactly the six operations named in design
§19. No Opportunity→Project propagation, no zena-boq-core change.

**Tech Stack:** Laravel 12, PHP 8.2+, MySQL 8.0 (parity-tested)/SQLite (default test DB), PHPUnit 11.

## Global Constraints

- Nullable `service_category` migration: remove DB default AND both PHP-level `'architecture'`
  fallbacks (`OpportunityController.php:217`, `LeadController.php:304`). No historical data touched.
- Exactly one shared mapping source (`App\Support\LegacyServiceCategoryMapper`) reused by
  `BackfillOpportunityServiceLines`, `store()`, `LeadController::convert()`, `update()`.
- `CONFIRMED` rows are NEVER overwritten/demoted/deleted by the legacy mapper, ever.
- One shared CONFIRMED predicate: `Opportunity::hasConfirmedServiceLine(): bool`. All five/six gate
  sites call this — no independently-written `count(...)` queries.
- Canonical lock order: Opportunity row locked FIRST via `lockForUpdate()`, inside a `DB::transaction`,
  with state re-read AFTER the lock, for every one of: (A) classification reconciliation, (B) pipeline
  transition, (C) `sendQuote()`, (D) `convert()`, (E) `createContract()`, (F) legacy `update()`.
- No grace/grandfather/time-based exception anywhere. `INFERRED` never satisfies a gate.
- No new permission — reuse `crm.manage`. No Opportunity→Project propagation. No zena-boq-core change.
- Failure UX: `$this->validationError([...])` (API) / `back()->with('error', ...)` (web), same key
  conventions already used (`'service_line' => [...]`).

---

## File Structure

- Create: `app/Support/LegacyServiceCategoryMapper.php` — single shared mapping source.
- Create: `app/Services/Crm/OpportunityServiceLineClassificationService.php` — atomic desired-set
  reconciliation (§5) with lifecycle invariant + explicit tenant/parent check.
- Create: `database/migrations/2026_08_30_100000_make_opportunities_service_category_nullable.php`
- Modify: `app/Models/Opportunity.php` — add `hasConfirmedServiceLine()`.
- Modify: `app/Http/Controllers/Api/OpportunityController.php` — `store()`, `update()`, `convert()`,
  `createContract()`, add `updateServiceLines()` action.
- Modify: `app/Http/Controllers/Api/LeadController.php` — `convert()`.
- Modify: `app/Services/Crm/OpportunityStageTransitionService.php` — lock + gate.
- Modify: `app/Http/Controllers/Web/CrmPageController.php` — `sendQuote()` gate; add
  `confirmServiceLines()` web action delegating to the same service.
- Modify: `app/Console/Commands/BackfillOpportunityServiceLines.php` — consume shared mapper.
- Modify: `app/Services/BusinessKpiService.php` — explicit Unclassified bucket.
- Modify: `app/Http/Controllers/Web/DesignItemPageController.php` — complete CONFIRMED-set context.
- Modify: `resources/views/crm/opportunity-show.blade.php` — classification panel + Confirm action.
- Modify: `routes/api_zena.php`, `routes/web.php` — new classification routes.
- Create: `tests/Feature/Crm/ServiceLineClassificationWriterSyncTest.php` (A–E, I)
- Create: `tests/Feature/Crm/ServiceLineClassificationReconciliationTest.php` (F, G, H, security)
- Create: `tests/Feature/Crm/ServiceLineGateTest.php` (K, L, M, N, O, §12 gate)
- Create: `tests/Feature/Crm/DesignItemAiContextServiceLineTest.php` (J)
- Create: `tests/Feature/Crm/BusinessKpiUnclassifiedBucketTest.php`
- Create: `tests/Feature/Concurrency/OpportunityServiceLineConcurrencyTest.php` (CONCURRENCY-1/2/3,
  `@group stress`, mirrors `RfiEscalationConcurrencyTest.php`'s subprocess-race pattern)
- Create: `app/Console/Commands/ConcurrencyTest/OpportunityConcurrencyTestTransition.php`,
  `...ReconcileToEmpty.php`, `...SendQuote.php`, `...UpdateServiceCategory.php` — artisan-invokable
  single-operation commands the concurrency test races as separate OS subprocesses (same technique
  as `rfi:concurrency-test-escalate`).

---

### Task 1: Shared legacy→canonical mapper

**Files:**
- Create: `app/Support/LegacyServiceCategoryMapper.php`
- Modify: `app/Console/Commands/BackfillOpportunityServiceLines.php`
- Test: `tests/Unit/Support/LegacyServiceCategoryMapperTest.php`

**Interfaces:**
- Produces: `LegacyServiceCategoryMapper::mapToServiceLine(?string $legacyCategory): ?string` — returns
  one of `ServiceLine::VALUES` or `null` (no mapping).

- [ ] **Step 1: Write failing unit test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LegacyServiceCategoryMapper;
use App\Support\ServiceLine;
use PHPUnit\Framework\TestCase;

class LegacyServiceCategoryMapperTest extends TestCase
{
    public static function designFamilyProvider(): array
    {
        return [
            ['architecture'], ['interior'], ['landscape'], ['structure'], ['mep'],
        ];
    }

    /** @dataProvider designFamilyProvider */
    public function test_design_family_maps_to_design(string $legacy): void
    {
        $this->assertSame(ServiceLine::DESIGN, LegacyServiceCategoryMapper::mapToServiceLine($legacy));
    }

    public function test_construction_maps_to_construction(): void
    {
        $this->assertSame(ServiceLine::CONSTRUCTION, LegacyServiceCategoryMapper::mapToServiceLine('construction'));
    }

    public static function ambiguousProvider(): array
    {
        return [['inspection'], ['consulting'], ['combined_package'], [null], ['not_a_real_value']];
    }

    /** @dataProvider ambiguousProvider */
    public function test_ambiguous_or_unrecognized_maps_to_null(?string $legacy): void
    {
        $this->assertNull(LegacyServiceCategoryMapper::mapToServiceLine($legacy));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Support/LegacyServiceCategoryMapperTest.php`
Expected: FAIL — class `LegacyServiceCategoryMapper` not found.

- [ ] **Step 3: Implement**

```php
<?php declare(strict_types=1);

namespace App\Support;

/**
 * GAP-048 §4 — the single shared legacy→canonical mapping source. Every
 * writer (BackfillOpportunityServiceLines, OpportunityController::store(),
 * LeadController::convert(), OpportunityController::update()) MUST consume
 * this class rather than re-declaring the mapping table.
 */
final class LegacyServiceCategoryMapper
{
    /** @var array<string, string> */
    private const MAP = [
        'architecture' => ServiceLine::DESIGN,
        'interior' => ServiceLine::DESIGN,
        'landscape' => ServiceLine::DESIGN,
        'structure' => ServiceLine::DESIGN,
        'mep' => ServiceLine::DESIGN,
        'construction' => ServiceLine::CONSTRUCTION,
        // inspection, consulting, combined_package, null, and any
        // unrecognized value are deliberately absent — no membership row.
    ];

    public static function mapToServiceLine(?string $legacyCategory): ?string
    {
        if ($legacyCategory === null) {
            return null;
        }

        return self::MAP[$legacyCategory] ?? null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Support/LegacyServiceCategoryMapperTest.php`
Expected: PASS (9 tests incl. dataProviders).

- [ ] **Step 5: Point the backfill command at the shared mapper (keep it green)**

Replace `BackfillOpportunityServiceLines::MAP` usage:
```php
$line = \App\Support\LegacyServiceCategoryMapper::mapToServiceLine($opportunity->service_category);
```
Remove the now-redundant private `MAP` constant on the command.

Run: `./vendor/bin/phpunit tests/Feature/Console/BackfillOpportunityServiceLinesTest.php`
Expected: PASS (unchanged behavior, same mapping, now delegated).

- [ ] **Step 6: Commit**

```bash
git add app/Support/LegacyServiceCategoryMapper.php app/Console/Commands/BackfillOpportunityServiceLines.php tests/Unit/Support/LegacyServiceCategoryMapperTest.php
git commit -m "feat(GAP-048): extract shared legacy service_category to Service-Line mapper"
```

---

### Task 2: Nullable `service_category` migration + remove app-level defaults

**Files:**
- Create: `database/migrations/2026_08_30_100000_make_opportunities_service_category_nullable.php`
- Modify: `app/Http/Controllers/Api/OpportunityController.php:217`
- Modify: `app/Http/Controllers/Api/LeadController.php:304`
- Test: `tests/Feature/Crm/OpportunityServiceCategoryNullableTest.php`

**Interfaces:**
- Consumes: `LegacyServiceCategoryMapper::mapToServiceLine()` (Task 1) — used in the SAME create call,
  see Task 3.

- [ ] **Step 1: Write failing test (persisted-NULL behavior, SQLite)**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityServiceCategoryNullableTest extends TestCase
{
    use RefreshDatabase;

    public function test_omitted_service_category_persists_as_null_not_architecture(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $account = Account::factory()->create(['tenant_id' => $tenant->id]);
        $this->grantCrmManage($user);

        $response = $this->actingAs($user)->postJson('/api/opportunities', [
            'account_id' => $account->id,
            'opportunity_name' => 'No category supplied',
        ]);

        $response->assertCreated();
        $opportunity = Opportunity::query()->findOrFail($response->json('data.id'));
        $this->assertNull($opportunity->service_category);
    }
}
```

(`grantCrmManage()` — reuse the existing test helper trait already used by sibling CRM tests; grep
`tests/Feature/Crm/*.php` for the exact helper name/trait in use before writing this line — e.g.
`OpportunityConversionUnchangedTest.php`'s own setup — and mirror it exactly rather than inventing a
new one.)

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Crm/OpportunityServiceCategoryNullableTest.php`
Expected: FAIL — asserts null, gets `'architecture'`.

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
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->string('service_category')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->string('service_category')->default('architecture')->change();
        });
    }
};
```

Note: `->change()` requires `doctrine/dbal` — confirm it is already a project dependency (GAP-046's
own migrations used `.../opportunity_service_lines` additive-only DDL; check `composer.json` for
`doctrine/dbal` before relying on `.change()`. If absent, use raw `DB::statement('ALTER TABLE
opportunities MODIFY service_category VARCHAR(255) NULL DEFAULT NULL')` guarded by
`if (DB::getDriverName() === 'sqlite') { Schema::table(...) } else { DB::statement(...) }` — SQLite's
schema builder doesn't support `MODIFY`; Laravel's `->change()` normally abstracts this, so prefer
`.change()` and only fall back to raw SQL if a real run proves it fails.)

- [ ] **Step 4: Remove both PHP-level defaults**

`OpportunityController.php:217`:
```php
'service_category' => $request->input('service_category'),
```
(was `(string) $request->input('service_category', 'architecture')`)

`LeadController.php:304`: same change.

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/Crm/OpportunityServiceCategoryNullableTest.php --group=` (no
mysql-parity group needed for this SQLite assertion)
Expected: PASS.

- [ ] **Step 6: Run full existing CRM regression suite (preserve existing green)**

Run: `./vendor/bin/phpunit tests/Feature/Crm tests/Feature/Console/BackfillOpportunityServiceLinesTest.php tests/Feature/Models/ServiceLineFoundationTest.php`
Expected: PASS, including `OpportunityConversionUnchangedTest.php` unchanged.

- [ ] **Step 7: MySQL parity — nullable migration**

Add/extend a `@group mysql-parity` test asserting `SHOW COLUMNS FROM opportunities LIKE
'service_category'` reports `Null = YES` and `Default = NULL` after migration, following the exact
pattern of `tests/Unit/Migrations/Treasury/TreasuryNativeCheckConstraintsMysqlTest.php` (skip-if-
unreachable guard). Run under `ZENA_INVARIANTS_DB=mysql DB_CONNECTION=mysql php artisan migrate:fresh
--force && ./vendor/bin/phpunit --group mysql-parity` if a real MySQL instance is reachable in this
environment; if not, this step must be reported as NOT independently verified on real MySQL (honesty
requirement), not silently assumed passing.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_30_100000_make_opportunities_service_category_nullable.php app/Http/Controllers/Api/OpportunityController.php app/Http/Controllers/Api/LeadController.php tests/Feature/Crm/OpportunityServiceCategoryNullableTest.php
git commit -m "feat(GAP-048): nullable service_category, remove silent architecture defaults"
```

---

### Task 3: Shared CONFIRMED predicate on `Opportunity`

**Files:**
- Modify: `app/Models/Opportunity.php`
- Test: `tests/Unit/Models/OpportunityConfirmedPredicateTest.php` (or fold into Task 4/7 feature tests —
  a light Feature test is acceptable since the predicate needs a real DB row).

**Interfaces:**
- Produces: `Opportunity::hasConfirmedServiceLine(): bool` — `true` iff `serviceLines()` has ≥1 row
  with `provenance === ServiceLineProvenance::CONFIRMED`. Callers needing the lock-safe authoritative
  answer MUST call this on a model instance obtained AFTER `lockForUpdate()` inside the transaction
  (design §19 step 3) — this method itself does not lock; it is a pure read.

- [ ] **Step 1: Write failing test**

```php
public function test_hasConfirmedServiceLine_true_only_with_confirmed_row(): void
{
    $opportunity = Opportunity::factory()->create(['tenant_id' => $tenant->id]);
    $this->assertFalse($opportunity->hasConfirmedServiceLine());

    $opportunity->serviceLines()->create([
        'service_line' => ServiceLine::DESIGN,
        'provenance' => ServiceLineProvenance::INFERRED,
    ]);
    $this->assertFalse($opportunity->fresh()->hasConfirmedServiceLine());

    $opportunity->serviceLines()->create([
        'service_line' => ServiceLine::CONSTRUCTION,
        'provenance' => ServiceLineProvenance::CONFIRMED,
    ]);
    $this->assertTrue($opportunity->fresh()->hasConfirmedServiceLine());
}
```

- [ ] **Step 2: Run to verify it fails** — method not found.

- [ ] **Step 3: Implement**

```php
public function hasConfirmedServiceLine(): bool
{
    return $this->serviceLines()
        ->where('provenance', \App\Support\ServiceLineProvenance::CONFIRMED)
        ->exists();
}
```

- [ ] **Step 4: Run to verify it passes.**
- [ ] **Step 5: Commit**

```bash
git add app/Models/Opportunity.php tests/
git commit -m "feat(GAP-048): shared hasConfirmedServiceLine predicate on Opportunity"
```

---

### Task 4: `store()` / `LeadController::convert()` — atomic create + mapper-derived INFERRED

**Files:**
- Modify: `app/Http/Controllers/Api/OpportunityController.php` (`store()`)
- Modify: `app/Http/Controllers/Api/LeadController.php` (`convert()`)
- Test: `tests/Feature/Crm/ServiceLineClassificationWriterSyncTest.php` (cases A, B, C, I from §18)

**Interfaces:**
- Consumes: `LegacyServiceCategoryMapper::mapToServiceLine()` (Task 1), `OpportunityServiceLine` model
  (existing, GAP-046).

- [ ] **Step 1: Write failing tests (A, B, C)**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Models\Account;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityServiceLine;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceLineClassificationWriterSyncTest extends TestCase
{
    use RefreshDatabase;

    // Case A
    public function test_store_omitted_service_category_persists_null_and_zero_rows(): void
    {
        [$user, $account] = $this->tenantFixtures();

        $response = $this->actingAs($user)->postJson('/api/opportunities', [
            'account_id' => $account->id,
            'opportunity_name' => 'A',
        ]);

        $response->assertCreated();
        $opportunity = Opportunity::query()->findOrFail($response->json('data.id'));
        $this->assertNull($opportunity->service_category);
        $this->assertSame(0, OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)->count());
    }

    // Case B
    public function test_store_construction_maps_to_construction_inferred(): void
    {
        [$user, $account] = $this->tenantFixtures();

        $response = $this->actingAs($user)->postJson('/api/opportunities', [
            'account_id' => $account->id,
            'opportunity_name' => 'B',
            'service_category' => 'construction',
        ]);

        $response->assertCreated();
        $opportunity = Opportunity::query()->findOrFail($response->json('data.id'));
        $row = OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)->sole();
        $this->assertSame(ServiceLine::CONSTRUCTION, $row->service_line);
        $this->assertSame(ServiceLineProvenance::INFERRED, $row->provenance);
    }

    // Case C — identical outcome via Lead conversion
    public function test_lead_convert_construction_matches_store_outcome(): void
    {
        [$user, $account] = $this->tenantFixtures();
        $lead = Lead::factory()->create(['tenant_id' => $user->tenant_id, 'status' => Lead::STATUS_NEW]);

        $response = $this->actingAs($user)->postJson("/api/leads/{$lead->id}/convert", [
            'account_id' => $account->id,
            'opportunity_name' => 'C',
            'service_category' => 'construction',
        ]);

        $response->assertCreated();
        $opportunityId = $response->json('data.opportunity.id');
        $row = OpportunityServiceLine::query()->where('opportunity_id', $opportunityId)->sole();
        $this->assertSame(ServiceLine::CONSTRUCTION, $row->service_line);
        $this->assertSame(ServiceLineProvenance::INFERRED, $row->provenance);
    }

    private function tenantFixtures(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->grantCrmManage($user); // mirror existing sibling-test helper — see Task 2 note
        $account = Account::factory()->create(['tenant_id' => $tenant->id]);

        return [$user, $account];
    }
}
```

- [ ] **Step 2: Run — verify FAIL** (B/C: zero rows created today, no writer touches
  `OpportunityServiceLine`).

- [ ] **Step 3: Implement — `store()`**

Wrap the existing `Opportunity::query()->create([...])` call plus the new mapper-derived row creation
in one `DB::transaction()` (creation atomicity, design §19 legacy-writer-atomicity rule):

```php
$opportunity = DB::transaction(function () use ($request, $tenantId, $user): Opportunity {
    $legacyCategory = $request->input('service_category');

    $opportunity = Opportunity::query()->create([
        'tenant_id' => $tenantId,
        'account_id' => (string) $request->input('account_id'),
        'opportunity_name' => (string) $request->input('opportunity_name'),
        'service_category' => $legacyCategory,
        'service_scope_summary' => $request->input('service_scope_summary'),
        'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
        'forecast_category' => (string) $request->input('forecast_category', 'pipeline'),
        'estimated_fee' => $request->input('estimated_fee'),
        'estimated_project_value' => $request->input('estimated_project_value'),
        'probability' => $request->input('probability'),
        'expected_close_date' => $request->input('expected_close_date'),
        'sales_owner_id' => $request->input('sales_owner_id', (string) $user->id),
        'technical_owner_id' => $request->input('technical_owner_id'),
        'priority' => (string) $request->input('priority', 'medium'),
        'created_by' => (string) $user->id,
    ]);

    $mappedLine = \App\Support\LegacyServiceCategoryMapper::mapToServiceLine($legacyCategory);
    if ($mappedLine !== null) {
        $opportunity->serviceLines()->create([
            'service_line' => $mappedLine,
            'provenance' => \App\Support\ServiceLineProvenance::INFERRED,
            'source' => 'writer:store',
        ]);
    }

    return $opportunity;
});
```

- [ ] **Step 4: Implement — `LeadController::convert()`**

Inside the SAME existing `DB::transaction()` closure (it already wraps Account+Opportunity+Lead
writes), after the `Opportunity::query()->create([...])` call (with `'service_category' =>
$request->input('service_category')`, default removed per Task 2), add:

```php
$mappedLine = \App\Support\LegacyServiceCategoryMapper::mapToServiceLine($request->input('service_category'));
if ($mappedLine !== null) {
    $opportunity->serviceLines()->create([
        'service_line' => $mappedLine,
        'provenance' => \App\Support\ServiceLineProvenance::INFERRED,
        'source' => 'writer:lead_convert',
    ]);
}
```

- [ ] **Step 5: Run — verify PASS.**
- [ ] **Step 6: Regression — `OpportunityConversionUnchangedTest.php` must stay green** (proves this
  change didn't leak classification into WON conversion). Run:
  `./vendor/bin/phpunit tests/Feature/Crm/OpportunityConversionUnchangedTest.php`

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/OpportunityController.php app/Http/Controllers/Api/LeadController.php tests/Feature/Crm/ServiceLineClassificationWriterSyncTest.php
git commit -m "feat(GAP-048): store()/Lead convert() synchronize canonical INFERRED via shared mapper"
```

---

### Task 5: Atomic classification reconciliation service (§5) + routes/controller actions

**Files:**
- Create: `app/Services/Crm/OpportunityServiceLineClassificationService.php`
- Modify: `app/Http/Controllers/Api/OpportunityController.php` (add `updateServiceLines()`)
- Modify: `app/Http/Controllers/Web/CrmPageController.php` (add `confirmServiceLines()`)
- Modify: `routes/api_zena.php`, `routes/web.php`
- Test: `tests/Feature/Crm/ServiceLineClassificationReconciliationTest.php` (F, G, H, security, D, E)

**Interfaces:**
- Produces:
  `OpportunityServiceLineClassificationService::reconcile(User $actor, Opportunity $opportunity, array $desiredServiceLines): Opportunity`
  — `$desiredServiceLines` is a list of 0..3 values from `ServiceLine::VALUES` (deduplicated by
  caller). Throws `\Illuminate\Validation\ValidationException` (key `service_line`) if the invariant
  would be violated. Returns the fresh `Opportunity` (relationship not eager-loaded; caller re-queries
  `serviceLines` if needed).
- Consumes: `Opportunity::hasConfirmedServiceLine()` (Task 3), `EnforcesServiceLineIntegrity`
  (existing), `OpportunityServiceLine` (existing).

- [ ] **Step 1: Write failing tests**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Models\Opportunity;
use App\Models\OpportunityServiceLine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\OpportunityServiceLineClassificationService;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceLineClassificationReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function service(): OpportunityServiceLineClassificationService
    {
        return app(OpportunityServiceLineClassificationService::class);
    }

    // Case D
    public function test_update_reconciles_mapper_owned_inferred_row_to_new_scalar(): void
    {
        [$user, $opportunity] = $this->fixture(['service_category' => 'architecture']);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::INFERRED, 'source' => 'writer:store']);

        $this->actingAs($user)->putJson("/api/opportunities/{$opportunity->id}", ['service_category' => 'construction'])->assertOk();

        $rows = OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(ServiceLine::CONSTRUCTION, $rows->first()->service_line);
        $this->assertSame(ServiceLineProvenance::INFERRED, $rows->first()->provenance);
    }

    // Case E
    public function test_update_never_overwrites_confirmed_row(): void
    {
        [$user, $opportunity] = $this->fixture(['service_category' => 'architecture', 'pipeline_stage' => Opportunity::STAGE_NEW_LEAD]);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED, 'source' => 'confirm']);

        $this->actingAs($user)->putJson("/api/opportunities/{$opportunity->id}", ['service_category' => 'construction'])->assertOk();

        $rows = OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)->get();
        $this->assertTrue($rows->contains(fn ($r) => $r->service_line === ServiceLine::DESIGN && $r->provenance === ServiceLineProvenance::CONFIRMED));
    }

    // Case F
    public function test_reconcile_rejects_removing_last_confirmed_line_on_active_stage(): void
    {
        [$user, $opportunity] = $this->fixture(['pipeline_stage' => Opportunity::STAGE_SCOPE_DEFINED]);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED]);

        $this->expectException(ValidationException::class);
        $this->service()->reconcile($user, $opportunity->fresh(), []);
    }

    // Case G
    public function test_reconcile_atomically_replaces_confirmed_line(): void
    {
        [$user, $opportunity] = $this->fixture(['pipeline_stage' => Opportunity::STAGE_SCOPE_DEFINED]);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED]);

        $result = $this->service()->reconcile($user, $opportunity->fresh(), [ServiceLine::CONSTRUCTION]);

        $this->assertTrue($result->fresh()->hasConfirmedServiceLine());
        $rows = OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame(ServiceLine::CONSTRUCTION, $rows->first()->service_line);
    }

    // Case H
    public function test_reconcile_allows_prescope_return_to_zero(): void
    {
        [$user, $opportunity] = $this->fixture(['pipeline_stage' => Opportunity::STAGE_QUALIFIED]);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED]);

        $result = $this->service()->reconcile($user, $opportunity->fresh(), []);

        $this->assertFalse($result->fresh()->hasConfirmedServiceLine());
    }

    // Case I
    public function test_multiple_confirmed_lines_survive_as_a_set(): void
    {
        [$user, $opportunity] = $this->fixture(['pipeline_stage' => Opportunity::STAGE_QUALIFIED]);

        $result = $this->service()->reconcile($user, $opportunity->fresh(), [ServiceLine::DESIGN, ServiceLine::CONSTRUCTION]);

        $lines = OpportunityServiceLine::query()->where('opportunity_id', $opportunity->id)
            ->where('provenance', ServiceLineProvenance::CONFIRMED)->pluck('service_line')->sort()->values()->all();
        $this->assertSame([ServiceLine::CONSTRUCTION, ServiceLine::DESIGN], $lines);
    }

    // Security — cross-tenant
    public function test_reconcile_rejects_cross_tenant_opportunity(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $actorB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $opportunity = Opportunity::factory()->create(['tenant_id' => $tenantA->id, 'pipeline_stage' => Opportunity::STAGE_QUALIFIED]);

        $this->expectException(\Throwable::class); // authorization/tenant-mismatch exception
        $this->service()->reconcile($actorB, $opportunity, [ServiceLine::DESIGN]);
    }

    private function fixture(array $opportunityAttrs = []): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $opportunity = Opportunity::factory()->create(array_merge(['tenant_id' => $tenant->id], $opportunityAttrs));

        return [$user, $opportunity];
    }
}
```

- [ ] **Step 2: Run — verify FAIL** (class doesn't exist).

- [ ] **Step 3: Implement the service**

```php
<?php declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\EventRecord;
use App\Models\Opportunity;
use App\Models\OpportunityServiceLine;
use App\Models\Quote;
use App\Models\User;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * GAP-048 §5/§19 — atomic desired-set canonical Service-Line reconciliation.
 *
 * NOT an EnforcesServiceLineIntegrity-protected naked delete: this service
 * performs its OWN explicit tenant/parent authorization (the trait's
 * `saving` hook never sees a DELETE) and its own lifecycle-invariant check,
 * under an exclusive Opportunity row lock (canonical lock order: Opportunity
 * row first, child rows after), inside one DB transaction with the audit
 * EventRecord(s).
 */
class OpportunityServiceLineClassificationService
{
    /**
     * @param list<string> $desiredServiceLines subset of ServiceLine::VALUES
     */
    public function reconcile(User $actor, Opportunity $opportunity, array $desiredServiceLines): Opportunity
    {
        foreach ($desiredServiceLines as $line) {
            if (! in_array($line, ServiceLine::VALUES, true)) {
                throw ValidationException::withMessages(['service_line' => ["Invalid Service Line [{$line}]."]]);
            }
        }
        $desiredServiceLines = array_values(array_unique($desiredServiceLines));

        return DB::transaction(function () use ($actor, $opportunity, $desiredServiceLines): Opportunity {
            // Canonical lock order: Opportunity row FIRST.
            $locked = Opportunity::query()
                ->withoutGlobalScope('tenant')
                ->whereKey($opportunity->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Explicit tenant/parent authorization — NOT delegated to
            // EnforcesServiceLineIntegrity, which cannot see the delete half.
            if ((string) $locked->tenant_id !== (string) $actor->tenant_id) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'Cross-tenant Service-Line reconciliation rejected.'
                );
            }

            $existingRows = OpportunityServiceLine::query()
                ->where('opportunity_id', $locked->id)
                ->lockForUpdate()
                ->get();

            $existingConfirmed = $existingRows->where('provenance', ServiceLineProvenance::CONFIRMED)
                ->pluck('service_line')->all();
            $existingInferred = $existingRows->where('provenance', ServiceLineProvenance::INFERRED)
                ->pluck('service_line')->all();

            // Resulting CONFIRMED set after this transaction = every desired
            // line becomes/remains CONFIRMED. A previously-CONFIRMED line
            // that is NOT in the desired set is removed (this is the
            // explicit removal path — never a raw delete endpoint).
            $resultingConfirmed = $desiredServiceLines;

            if ($this->requiresConfirmedInvariant($locked) && count($resultingConfirmed) === 0) {
                throw ValidationException::withMessages([
                    'service_line' => ['At least one confirmed Service Line is required at this stage.'],
                ]);
            }

            // Remove rows no longer desired (CONFIRMED lines dropped from the
            // set, and any mapper-owned INFERRED row not superseded below).
            foreach ($existingRows as $row) {
                if (! in_array($row->service_line, $desiredServiceLines, true)) {
                    $row->delete();
                    $this->recordEvent($locked, $actor, 'crm.opportunity.service_line_removed', [
                        'service_line' => $row->service_line,
                        'prior_provenance' => $row->provenance,
                        'new_provenance' => null,
                    ]);
                }
            }

            // Create/confirm every desired line.
            foreach ($desiredServiceLines as $line) {
                $row = OpportunityServiceLine::query()
                    ->where('opportunity_id', $locked->id)
                    ->where('service_line', $line)
                    ->first();

                $priorProvenance = $row?->provenance;

                if ($row === null) {
                    $row = new OpportunityServiceLine([
                        'opportunity_id' => $locked->id,
                        'service_line' => $line,
                    ]);
                }

                $row->provenance = ServiceLineProvenance::CONFIRMED;
                $row->source = $row->source ?? 'confirm:ui';
                $row->save();

                $this->recordEvent($locked, $actor, 'crm.opportunity.service_line_confirmed', [
                    'service_line' => $line,
                    'prior_provenance' => $priorProvenance,
                    'new_provenance' => ServiceLineProvenance::CONFIRMED,
                ]);
            }

            return $locked->fresh() ?? $locked;
        });
    }

    /**
     * §5 binding invariant surfaces: active/gated pipeline stage, native
     * Quote SENT/ACCEPTED, external accepted snapshot, or already-won.
     */
    private function requiresConfirmedInvariant(Opportunity $opportunity): bool
    {
        $gatedStages = [
            Opportunity::STAGE_SCOPE_DEFINED,
            Opportunity::STAGE_PROPOSAL_DRAFT,
            Opportunity::STAGE_PROPOSAL_SENT,
            Opportunity::STAGE_NEGOTIATION,
            Opportunity::STAGE_CONTRACTING,
            Opportunity::STAGE_WON,
        ];

        if (in_array((string) $opportunity->pipeline_stage, $gatedStages, true)) {
            return true;
        }

        $hasSentOrAcceptedNativeQuote = Quote::query()
            ->where('opportunity_id', (string) $opportunity->id)
            ->where('tenant_id', (string) $opportunity->tenant_id)
            ->whereIn('status', [Quote::STATUS_SENT, Quote::STATUS_ACCEPTED])
            ->exists();

        if ($hasSentOrAcceptedNativeQuote) {
            return true;
        }

        $snapshot = $opportunity->external_quote_snapshot ?? [];

        return ($snapshot['status'] ?? null) === 'ACCEPTED';
    }

    private function recordEvent(Opportunity $opportunity, User $actor, string $eventKey, array $payload): void
    {
        EventRecord::query()->create([
            'tenant_id' => (string) $opportunity->tenant_id,
            'project_id' => $opportunity->converted_project_id,
            'aggregate_type' => 'opportunity',
            'aggregate_id' => (string) $opportunity->id,
            'event_key' => $eventKey,
            'actor_user_id' => (string) $actor->id,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
```

- [ ] **Step 4: Run — verify PASS.**

- [ ] **Step 5: Wire the controller actions**

`Api\OpportunityController::updateServiceLines(Request $request, string $id)`:
```php
public function updateServiceLines(Request $request, string $id, \App\Services\Crm\OpportunityServiceLineClassificationService $service): JsonResponse
{
    $user = Auth::user();
    if (! $user) {
        return $this->unauthorized('Authentication required');
    }

    $tenantId = $this->tenantId($request);
    if ($tenantId === '') {
        return $this->errorResponse('Tenant context missing', 400);
    }

    $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();
    if (! $opportunity instanceof Opportunity) {
        return $this->notFound('Opportunity not found');
    }

    $this->authorize('update', $opportunity);

    $validator = Validator::make($request->all(), [
        'service_lines' => ['present', 'array'],
        'service_lines.*' => [Rule::in(\App\Support\ServiceLine::VALUES)],
    ]);
    if ($validator->fails()) {
        return $this->validationError($validator->errors());
    }

    try {
        $opportunity = $service->reconcile($user, $opportunity, $request->input('service_lines', []));
    } catch (ValidationException $exception) {
        return $this->validationError($exception->errors());
    }

    return $this->zenaSuccessResponse($this->serialize($opportunity), 'Service-Line classification updated successfully');
}
```

Route (`routes/api_zena.php`, alongside the existing Opportunity group):
```php
Route::post('/opportunities/{id}/service-lines', [OpportunityController::class, 'updateServiceLines'])->middleware('rbac:crm.manage');
```

Web equivalent in `CrmPageController::confirmServiceLines()` mirroring `updateStage()`'s
try/catch-and-redirect shape, route `POST /crm/opportunities/{id}/service-lines` (`rbac:crm.manage`).

- [ ] **Step 6: Route-level feature test** (POST the new endpoint end-to-end, reuse Step 1's
  fixtures) — add to `ServiceLineClassificationReconciliationTest.php`.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Crm/OpportunityServiceLineClassificationService.php app/Http/Controllers/Api/OpportunityController.php app/Http/Controllers/Web/CrmPageController.php routes/api_zena.php routes/web.php tests/Feature/Crm/ServiceLineClassificationReconciliationTest.php
git commit -m "feat(GAP-048): atomic Service-Line classification reconciliation service + routes"
```

---

### Task 6: `OpportunityController::update()` — legacy scalar + mapper reconciliation, CONFIRMED-safe, atomic

**Files:**
- Modify: `app/Http/Controllers/Api/OpportunityController.php` (`update()`)
- Test: extends `ServiceLineClassificationWriterSyncTest.php` (D, E already covered via Task 5's
  `putJson` flow — this task is the controller-side wiring those tests actually exercise)

**Interfaces:**
- Consumes: `LegacyServiceCategoryMapper::mapToServiceLine()`.

- [ ] **Step 1: Confirm Task 5's D/E tests currently fail against `update()`** (they were written
  against the route, so if Task 5 already wired reconciliation into `update()`, skip to Step 3 —
  otherwise this is where the legacy-scalar-triggered reconciliation actually gets implemented).

- [ ] **Step 2: Implement inside `update()`, replacing the plain `fill()+save()`**

```php
$opportunity = DB::transaction(function () use ($opportunity, $request): Opportunity {
    $locked = Opportunity::query()
        ->whereKey($opportunity->id)
        ->lockForUpdate()
        ->firstOrFail();

    $incomingCategory = $request->has('service_category') ? $request->input('service_category') : null;
    $categoryChanging = $request->has('service_category');

    $locked->fill($request->only([
        'account_id', 'opportunity_name', 'service_category', 'service_scope_summary',
        'forecast_category', 'estimated_fee', 'estimated_project_value', 'probability',
        'expected_close_date', 'sales_owner_id', 'technical_owner_id', 'priority',
    ]));
    $locked->save();

    if ($categoryChanging) {
        $mappedLine = \App\Support\LegacyServiceCategoryMapper::mapToServiceLine($incomingCategory);

        $mapperOwnedRows = \App\Models\OpportunityServiceLine::query()
            ->where('opportunity_id', $locked->id)
            ->where('provenance', \App\Support\ServiceLineProvenance::INFERRED)
            ->get();

        foreach ($mapperOwnedRows as $row) {
            if ($row->service_line !== $mappedLine) {
                $row->delete();
            }
        }

        if ($mappedLine !== null) {
            $exists = \App\Models\OpportunityServiceLine::query()
                ->where('opportunity_id', $locked->id)
                ->where('service_line', $mappedLine)
                ->exists();

            if (! $exists) {
                $locked->serviceLines()->create([
                    'service_line' => $mappedLine,
                    'provenance' => \App\Support\ServiceLineProvenance::INFERRED,
                    'source' => 'writer:update',
                ]);
            }
            // If a row already exists for $mappedLine (regardless of its
            // provenance, including CONFIRMED), it is left untouched —
            // rule §4.2: a CONFIRMED row is never overwritten/demoted.
        }
    }

    return $locked;
});
```

Note: this loop only ever deletes/creates `INFERRED` rows (`->where('provenance', INFERRED)` on the
query that selects candidates for deletion) — a `CONFIRMED` row is structurally never touched by this
block, satisfying rule §4.2 by construction, not by a runtime branch that could be forgotten.

- [ ] **Step 3: Run `ServiceLineClassificationWriterSyncTest.php` + the D/E tests from Task 5 (move
  them here if authored in Task 5's file per the plan's file list) — verify PASS.**

- [ ] **Step 4: Regression — full existing `update()` test coverage stays green.**

Run: `./vendor/bin/phpunit tests/Feature/Crm`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/OpportunityController.php
git commit -m "feat(GAP-048): update() reconciles mapper-owned INFERRED, never touches CONFIRMED, atomic+locked"
```

---

### Task 7: Pipeline gate — `OpportunityStageTransitionService::transition()`

**Files:**
- Modify: `app/Services/Crm/OpportunityStageTransitionService.php`
- Test: `tests/Feature/Crm/ServiceLineGateTest.php` (pipeline-gate cases + N + O's pipeline half)

**Interfaces:**
- Consumes: `Opportunity::hasConfirmedServiceLine()` (Task 3).

- [ ] **Step 1: Write failing tests**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\OpportunityStageTransitionService;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceLineGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_transition_into_scope_defined_blocked_without_confirmed(): void
    {
        [$actor, $opportunity] = $this->fixture(Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED);

        $this->expectException(ValidationException::class);
        app(OpportunityStageTransitionService::class)->transition($actor, $opportunity, Opportunity::STAGE_SCOPE_DEFINED, null);
    }

    public function test_transition_into_scope_defined_allowed_with_confirmed(): void
    {
        [$actor, $opportunity] = $this->fixture(Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED]);

        $result = app(OpportunityStageTransitionService::class)->transition($actor, $opportunity->fresh(), Opportunity::STAGE_SCOPE_DEFINED, null);

        $this->assertSame(Opportunity::STAGE_SCOPE_DEFINED, $result->pipeline_stage);
    }

    public function test_transition_into_scope_defined_blocked_with_inferred_only(): void
    {
        [$actor, $opportunity] = $this->fixture(Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED);
        $opportunity->serviceLines()->create(['service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::INFERRED]);

        $this->expectException(ValidationException::class);
        app(OpportunityStageTransitionService::class)->transition($actor, $opportunity->fresh(), Opportunity::STAGE_SCOPE_DEFINED, null);
    }

    // Case O — always-allowed exits, zero classification
    public function test_lost_no_bid_nurture_transitions_never_gated(): void
    {
        foreach ([Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID, Opportunity::STAGE_NURTURE] as $stage) {
            [$actor, $opportunity] = $this->fixture(Opportunity::STAGE_QUALIFIED);
            $lostReason = $stage === Opportunity::STAGE_LOST ? 'price' : null;

            $result = app(OpportunityStageTransitionService::class)->transition($actor, $opportunity, $stage, $lostReason);

            $this->assertSame($stage, $result->pipeline_stage);
        }
    }

    // Case M/N — no grandfather: an Opportunity already WON before classification requirements existed still blocked
    public function test_already_won_opportunity_still_blocked_from_next_gated_transition_regardless_of_age(): void
    {
        // A won Opportunity cannot transition further (terminal) — the no-grandfather
        // proof for WON specifically belongs to convert()/createContract() (Task 8),
        // this test proves the immediately-prior negotiation->contracting/contracting->won step.
        [$actor, $opportunity] = $this->fixture(Opportunity::STAGE_CONTRACTING);

        $this->expectException(ValidationException::class);
        app(OpportunityStageTransitionService::class)->transition($actor, $opportunity, Opportunity::STAGE_WON, null);
    }

    private function fixture(string $stage): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);
        $opportunity = Opportunity::factory()->create(['tenant_id' => $tenant->id, 'pipeline_stage' => $stage]);

        return [$actor, $opportunity];
    }
}
```

- [ ] **Step 2: Run — verify FAIL** (no gate exists today; the "blocked" tests currently succeed
  instead of throwing).

- [ ] **Step 3: Implement — wrap `transition()` in a lock + gate**

```php
public function transition(User $actor, Opportunity $opportunity, string $toStage, ?string $lostReason): Opportunity
{
    Gate::forUser($actor)->authorize('update', $opportunity);

    if (! in_array($toStage, Opportunity::VALID_STAGES, true)) {
        throw ValidationException::withMessages(['pipeline_stage' => ['Giai đoạn không hợp lệ.']]);
    }

    $gatedStages = [
        Opportunity::STAGE_SCOPE_DEFINED, Opportunity::STAGE_PROPOSAL_DRAFT,
        Opportunity::STAGE_PROPOSAL_SENT, Opportunity::STAGE_NEGOTIATION,
        Opportunity::STAGE_CONTRACTING, Opportunity::STAGE_WON,
    ];

    return DB::transaction(function () use ($actor, $opportunity, $toStage, $lostReason, $gatedStages): Opportunity {
        // Canonical lock order: Opportunity row first. Re-read state AFTER lock (§19).
        $locked = Opportunity::query()
            ->whereKey($opportunity->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($locked->isTerminal()) {
            throw ValidationException::withMessages([
                'pipeline_stage' => ['Won/lost/no-bid opportunities can no longer change stage.'],
            ]);
        }

        if ($toStage === Opportunity::STAGE_LOST && trim((string) $lostReason) === '') {
            throw ValidationException::withMessages(['lost_reason' => ['Vui lòng nhập lý do khi chuyển sang Thua.']]);
        }

        if (in_array($toStage, $gatedStages, true) && ! $locked->hasConfirmedServiceLine()) {
            throw ValidationException::withMessages([
                'service_line' => ['At least one confirmed Service Line is required before entering this stage.'],
            ]);
        }

        $from = (string) $locked->pipeline_stage;
        $locked->pipeline_stage = $toStage;
        $locked->lost_reason = $toStage === Opportunity::STAGE_LOST ? (string) $lostReason : null;

        if ($toStage === Opportunity::STAGE_WON) {
            $locked->forecast_category = 'closed_won';
        } elseif (in_array($toStage, [Opportunity::STAGE_LOST, Opportunity::STAGE_NO_BID], true)) {
            $locked->forecast_category = 'closed_lost';
        }

        $locked->save();

        EventRecord::query()->create([
            'tenant_id' => (string) $locked->tenant_id,
            'project_id' => $locked->converted_project_id,
            'aggregate_type' => 'opportunity',
            'aggregate_id' => (string) $locked->id,
            'event_key' => 'crm.opportunity.stage_changed',
            'actor_user_id' => (string) $actor->id,
            'payload' => ['from' => $from, 'to' => $toStage],
            'occurred_at' => now(),
        ]);

        return $locked->fresh() ?? $locked;
    });
}
```

Add `use Illuminate\Support\Facades\DB;` import.

- [ ] **Step 4: Run — verify PASS.**

- [ ] **Step 5: Regression** — `tests/Unit/Services/Crm/OpportunityStageTransitionServiceTest.php` and
  `OpportunityConversionUnchangedTest.php` must stay green (the latter never reaches a gated stage
  without seeding CONFIRMED, or if it does, it now needs a CONFIRMED seed — read the test first; if it
  breaks, that is an expected, correct consequence of the new gate and the test fixture must be updated
  to seed CONFIRMED classification before the gated transition, NOT the gate loosened).

Run: `./vendor/bin/phpunit tests/Unit/Services/Crm/OpportunityStageTransitionServiceTest.php tests/Feature/Crm/OpportunityConversionUnchangedTest.php`

- [ ] **Step 6: Commit**

```bash
git add app/Services/Crm/OpportunityStageTransitionService.php tests/Feature/Crm/ServiceLineGateTest.php
git commit -m "feat(GAP-048): pipeline classification gate with Opportunity row lock"
```

---

### Task 8: `sendQuote()` gate + `convert()`/`createContract()` defense-in-depth gate

**Files:**
- Modify: `app/Http/Controllers/Web/CrmPageController.php` (`sendQuote()`)
- Modify: `app/Http/Controllers/Api/OpportunityController.php` (`convert()`, `createContract()`)
- Test: extends `ServiceLineGateTest.php` (K, L, M)

**Interfaces:**
- Consumes: `Opportunity::hasConfirmedServiceLine()`.

- [ ] **Step 1: Write failing tests (K, L, M)**

```php
// K — sendQuote blocked without CONFIRMED
public function test_send_quote_blocked_without_confirmed(): void
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->grantCrmManage($user);
    $opportunity = Opportunity::factory()->create(['tenant_id' => $tenant->id]);
    $quote = \App\Models\Quote::factory()->create(['tenant_id' => $tenant->id, 'opportunity_id' => $opportunity->id, 'status' => \App\Models\Quote::STATUS_DRAFT]);
    \App\Models\QuoteLineItem::factory()->create(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]);

    $response = $this->actingAs($user)->post("/crm/quotes/{$quote->id}/send");

    $response->assertRedirect();
    $this->assertSame(\App\Models\Quote::STATUS_DRAFT, $quote->fresh()->status);
}

// L — external sync unblocked, but createContract() blocked
public function test_create_contract_blocked_without_confirmed_even_with_external_accepted_snapshot(): void
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->grantCrmManage($user);
    $opportunity = Opportunity::factory()->create([
        'tenant_id' => $tenant->id,
        'pipeline_stage' => Opportunity::STAGE_WON,
        'external_quote_snapshot' => ['status' => 'ACCEPTED', 'total' => 1000],
    ]);
    // NOTE: reaching STAGE_WON via factory bypasses the pipeline gate on purpose — this
    // test proves createContract()'s OWN independent gate, not the pipeline gate.

    $response = $this->actingAs($user)->postJson("/api/opportunities/{$opportunity->id}/create-contract");

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['service_line']);
}

// M — already-WON legacy Opportunity still blocked (no grandfather)
public function test_already_won_opportunity_convert_blocked_until_confirmed(): void
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $this->grantCrmManage($user);
    $opportunity = Opportunity::factory()->create(['tenant_id' => $tenant->id, 'pipeline_stage' => Opportunity::STAGE_WON]);

    $response = $this->actingAs($user)->postJson("/api/opportunities/{$opportunity->id}/convert");

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['service_line']);
}
```

- [ ] **Step 2: Run — verify FAIL.**

- [ ] **Step 3: Implement `sendQuote()` gate** (`CrmPageController.php`, inside `sendQuote()`, after
  the existing `canTransition`/`$hasLines` checks, before `$quote->update(...)`):

```php
$opportunity = Opportunity::query()
    ->whereKey($quote->opportunity_id)
    ->lockForUpdate()
    ->first();

if ($opportunity instanceof Opportunity && ! $opportunity->hasConfirmedServiceLine()) {
    return back()->with('error', 'Cần ít nhất một Service Line đã xác nhận trước khi gửi báo giá chính thức.');
}
```

Wrap the whole method body (from the `Quote` lookup through the `EventRecord` write) in
`DB::transaction(function () use (...) { ... });` so the lock is held for the duration of the send —
canonical lock order: Opportunity locked first (it is looked up before the `$quote->update()` call).

- [ ] **Step 4: Implement `convert()` gate** (`OpportunityController.php`, after the existing WON-stage
  check, before the `Project::create` transaction):

```php
$locked = Opportunity::query()->whereKey($opportunity->id)->lockForUpdate()->firstOrFail();
if (! $locked->hasConfirmedServiceLine()) {
    return $this->validationError([
        'service_line' => ['At least one confirmed Service Line is required before converting to a project.'],
    ]);
}
```
Place this check as the first statement inside the existing `DB::transaction(function () use (...) {`
closure of `convert()` (so the lock is acquired inside the same transaction the mutation happens in),
using `$locked` in place of `$opportunity` for the mutation that follows.

- [ ] **Step 5: Implement `createContract()` gate** — same pattern, placed after the existing
  native-or-external-accepted-Quote check (so both the pre-existing `'quote'` failure and the new
  `'service_line'` failure are independently testable) and before the inline Project-creation branch:

```php
$locked = Opportunity::query()->whereKey($opportunity->id)->lockForUpdate()->firstOrFail();
if (! $locked->hasConfirmedServiceLine()) {
    return $this->validationError([
        'service_line' => ['At least one confirmed Service Line is required before generating a contract.'],
    ]);
}
```
placed right before `$projectId = $opportunity->converted_project_id;`. This check applies whether or
not `createContract()` needs to create a new Project (both the "already has converted_project_id" and
"needs a fresh convert" branches proceed to the Contract-creation transaction below it, so the gate
must sit before both).

- [ ] **Step 6: Run — verify PASS.**

- [ ] **Step 7: Regression** — `OpportunityConversionUnchangedTest.php` again (its own fixtures now
  need a CONFIRMED seed if they exercise `convert()`/`createContract()` end-to-end at a WON stage —
  update the fixture setup, never the new gate).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Web/CrmPageController.php app/Http/Controllers/Api/OpportunityController.php tests/Feature/Crm/ServiceLineGateTest.php
git commit -m "feat(GAP-048): sendQuote()/convert()/createContract() classification gates, Opportunity-locked"
```

---

### Task 9: `BusinessKpiService` — explicit Unclassified bucket

**Files:**
- Modify: `app/Services/BusinessKpiService.php`
- Test: `tests/Feature/Crm/BusinessKpiUnclassifiedBucketTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Models\Opportunity;
use App\Models\Tenant;
use App\Services\BusinessKpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessKpiUnclassifiedBucketTest extends TestCase
{
    use RefreshDatabase;

    public function test_null_service_category_appears_under_explicit_unclassified_bucket(): void
    {
        $tenant = Tenant::factory()->create();
        Opportunity::factory()->create([
            'tenant_id' => $tenant->id,
            'service_category' => null,
            'pipeline_stage' => Opportunity::STAGE_WON,
        ]);

        $result = app(BusinessKpiService::class)->serviceCategoryPerformance((string) $tenant->id);

        $this->assertArrayHasKey('unclassified', $result);
        $this->assertSame(1, $result['unclassified']['won']);
    }
}
```

- [ ] **Step 2: Run — verify FAIL** (`whereNotNull` currently drops the row entirely; no
  `'unclassified'` key).

- [ ] **Step 3: Implement**

```php
public function serviceCategoryPerformance(string $tenantId): array
{
    return Cache::remember("business_kpi_service_category_performance_{$tenantId}", 60, function () use ($tenantId): array {
        $rows = Opportunity::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('pipeline_stage', self::TERMINAL_LOST_STAGES)
            ->get(['service_category', 'pipeline_stage', 'estimated_fee', 'external_quote_snapshot']);

        $result = [];

        foreach ($rows->groupBy(fn (Opportunity $o) => $o->service_category ?? 'unclassified') as $category => $group) {
            $total = $group->count();
            $wonOpportunities = $group->where('pipeline_stage', Opportunity::STAGE_WON);
            $won = $wonOpportunities->count();
            $avgFee = $won > 0
                ? $wonOpportunities->sum(fn (Opportunity $opportunity) => $this->revenueFor($opportunity)) / $won
                : 0.0;

            $result[(string) $category] = [
                'won' => $won,
                'total' => $total,
                'rate' => $total > 0 ? (float) $won / $total : 0.0,
                'avg_fee' => $avgFee,
            ];
        }

        return $result;
    });
}
```

(Removed `->whereNotNull('service_category')`; `groupBy` closure maps `null` to the literal string
`'unclassified'` — GAP-048 §14 explicit decision, NOT multi-Service-Line aware in this Work ID.)

- [ ] **Step 4: Run — verify PASS.**
- [ ] **Step 5: Regression** — any existing `BusinessKpiService` test stays green (grep
  `tests/**/*BusinessKpi*` first).
- [ ] **Step 6: Commit**

```bash
git add app/Services/BusinessKpiService.php tests/Feature/Crm/BusinessKpiUnclassifiedBucketTest.php
git commit -m "feat(GAP-048): BusinessKpiService explicit Unclassified bucket for NULL service_category"
```

---

### Task 10: `DesignItemPageController`/`AiAssistService` — complete CONFIRMED-set context

**Files:**
- Modify: `app/Http/Controllers/Web/DesignItemPageController.php`
- Test: `tests/Feature/Crm/DesignItemAiContextServiceLineTest.php` (case J)

**Interfaces:**
- Consumes: `Opportunity::serviceLines()` (existing relation), `ServiceLine::VALUES` (canonical order).

- [ ] **Step 1: Write failing test** (mock/fake `AiAssistService` or assert on the argument passed —
  read the existing `AiAssistService` binding pattern used by sibling AI tests, e.g.
  `tests/Feature/**/*AiAssist*`, before choosing the double style, to stay consistent with the
  codebase's existing convention rather than inventing a new one)

```php
public function test_confirmed_set_passed_as_complete_stable_order_string_not_one_line(): void
{
    // Arrange: Opportunity behind the Project has CONFIRMED {DESIGN, CONSTRUCTION}
    // and a conflicting legacy service_category = 'interior'.
    // Act: POST /design-items/suggest-description with that project_id.
    // Assert: the AiAssistService call received "DESIGN, CONSTRUCTION" as context,
    // not "interior" and not a single line.
}
```

(Write this using this repo's actual AI-service test-double convention — spy/fake binding via the
service container, asserting on the captured `$serviceCategory` argument — fill in the concrete
assertion once the sibling test file's pattern is read; do not leave this as a stub in the final
commit.)

- [ ] **Step 2: Run — verify FAIL.**

- [ ] **Step 3: Implement**

```php
$opportunity = Opportunity::query()
    ->where('tenant_id', $tenantId)
    ->where('converted_project_id', $projectId)
    ->first();

$confirmedLines = $opportunity
    ? $opportunity->serviceLines()->where('provenance', \App\Support\ServiceLineProvenance::CONFIRMED)->pluck('service_line')->all()
    : [];

$stableOrder = \App\Support\ServiceLine::VALUES; // [DESIGN, CONSTRUCTION, INSPECTION]
$orderedConfirmed = array_values(array_intersect($stableOrder, $confirmedLines));

$serviceCategory = count($orderedConfirmed) > 0
    ? implode(', ', $orderedConfirmed)
    : $opportunity?->service_category;

$suggestion = $aiAssistService->suggestDesignItemDescription($itemType, $serviceCategory);
```

(§14 rule 6: `INFERRED`-only Opportunities fall through to `$opportunity?->service_category` exactly
like zero-CONFIRMED, because `$orderedConfirmed` is empty in both cases — no special-casing needed.)

- [ ] **Step 4: Run — verify PASS.**
- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Web/DesignItemPageController.php tests/Feature/Crm/DesignItemAiContextServiceLineTest.php
git commit -m "feat(GAP-048): DesignItemPageController passes complete stable CONFIRMED set as AI context"
```

---

### Task 11: UI — classification panel + explicit Confirm action

**Files:**
- Modify: `resources/views/crm/opportunity-show.blade.php`

**Interfaces:**
- Consumes: `POST /crm/opportunities/{id}/service-lines` (Task 5).

- [ ] **Step 1: Add a read model to `CrmPageController::showOpportunity()`** — pass
  `serviceLines` (grouped by provenance) and `ServiceLine::VALUES` to the view.

- [ ] **Step 2: Add the panel markup** — checkboxes for `DESIGN`/`CONSTRUCTION`/`INSPECTION` inside a
  `<form>` posting to the Task-5 web route, pre-checked for existing CONFIRMED/INFERRED lines,
  badge showing provenance per line, one submit button labeled "Xác nhận phân loại". No JS auto-save —
  the form is a plain POST, matching this repo's existing non-SPA Blade convention (see `updateStage`'s
  own form in the same file for the exact idiom to mirror).

- [ ] **Step 3: Manual verification via `superpowers:verification-before-completion`** — since Dusk/
  browser tests are a known-flaky surface in this repo (see project memory:
  `project_browser_tests_real_root_cause_date_input.md`), this task's acceptance is the feature test
  from Task 5 hitting the real route (already covers the write contract) plus a direct `Read` of the
  rendered Blade output; do not add a new Dusk test unless explicitly requested — note this decision in
  the final report rather than silently skipping it.

- [ ] **Step 4: Commit**

```bash
git add resources/views/crm/opportunity-show.blade.php app/Http/Controllers/Web/CrmPageController.php
git commit -m "feat(GAP-048): classification panel + explicit Confirm action on Opportunity detail page"
```

---

### Task 12: CONCURRENCY-1/2/3 — real MySQL subprocess race tests

**Files:**
- Create: `app/Console/Commands/ConcurrencyTest/OpportunityConcurrencyTransitionCommand.php`
- Create: `app/Console/Commands/ConcurrencyTest/OpportunityConcurrencyReconcileCommand.php`
- Create: `app/Console/Commands/ConcurrencyTest/OpportunityConcurrencySendQuoteCommand.php`
- Create: `app/Console/Commands/ConcurrencyTest/OpportunityConcurrencyUpdateCategoryCommand.php`
- Create: `tests/Feature/Concurrency/OpportunityServiceLineConcurrencyTest.php`

**Interfaces:**
- Consumes: `OpportunityStageTransitionService::transition()` (Task 7),
  `OpportunityServiceLineClassificationService::reconcile()` (Task 5), `CrmPageController::sendQuote()`
  logic factored or re-invoked (Task 8), `OpportunityController::update()` logic (Task 6).

Mirror `RfiEscalationConcurrencyTest.php`'s exact technique: each artisan command performs exactly ONE
of the two racing operations against a shared row, prints `OK`/`CONFLICT`/an exception message, and
exits 0/1 accordingly; the test starts two subprocesses with `DB_CONNECTION=mysql` at the same time
and asserts on the joint outcome, never on timing.

- [ ] **Step 1: Write the 4 artisan commands** (signature pattern:
  `opportunity:concurrency-test-transition {opportunityId} {toStage}`,
  `opportunity:concurrency-test-reconcile {opportunityId} {actorId} {--lines=*}`,
  `opportunity:concurrency-test-send-quote {quoteId}`,
  `opportunity:concurrency-test-update-category {opportunityId} {category} {--fail-mapper-write}` — the
  last flag exists purely to let CONCURRENCY-3 simulate a mapper-reconciliation failure by throwing
  after the scalar write but before commit, inside the SAME transaction, to prove rollback). Each
  command calls the real production service/controller logic (not a re-implementation) and prints
  `OK`/`CONFLICT <message>` then returns 0/1.

- [ ] **Step 2: Write failing tests** (will FAIL/ERROR against the current unlocked implementation —
  if Tasks 5-8 are already merged with locking, these should already show correct behavior; if MySQL is
  unreachable in this environment, `skipUnlessMysqlAvailable()` causes a SKIP, which must be reported
  honestly, not silently treated as a pass)

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Opportunity;
use App\Models\OpportunityServiceLine;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * @group stress
 */
class OpportunityServiceLineConcurrencyTest extends TestCase
{
    private function skipUnlessMysqlAvailable(): void
    {
        try {
            DB::connection('mysql')->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('real MySQL required for row-lock concurrency proof: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        try {
            if (DB::connection('mysql')->getPdo()) {
                DB::connection('mysql')->table('opportunity_service_lines')->delete();
                DB::connection('mysql')->table('quotes')->delete();
                DB::connection('mysql')->table('opportunities')->delete();
                DB::connection('mysql')->table('tenants')->delete();
                DB::connection('mysql')->table('users')->delete();
            }
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    public function test_concurrency_1_reconcile_to_empty_races_transition_to_scope_defined(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $actor = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));
        $opportunity = Opportunity::on('mysql')->create(Opportunity::factory()->raw([
            'tenant_id' => $tenant->id,
            'pipeline_stage' => Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED,
        ]));
        OpportunityServiceLine::on('mysql')->create([
            'tenant_id' => $tenant->id, 'opportunity_id' => $opportunity->id,
            'service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED,
        ]);

        $php = (new PhpExecutableFinder())->find();
        $procA = new Process([$php, 'artisan', 'opportunity:concurrency-test-transition', $opportunity->id, Opportunity::STAGE_SCOPE_DEFINED], base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process([$php, 'artisan', 'opportunity:concurrency-test-reconcile', $opportunity->id, $actor->id], base_path(), ['DB_CONNECTION' => 'mysql']);

        $procA->start();
        $procB->start();
        $procA->wait();
        $procB->wait();

        $fresh = Opportunity::on('mysql')->find($opportunity->id);
        $confirmedCount = OpportunityServiceLine::on('mysql')
            ->where('opportunity_id', $opportunity->id)
            ->where('provenance', ServiceLineProvenance::CONFIRMED)
            ->count();

        $this->assertFalse(
            $fresh->pipeline_stage === Opportunity::STAGE_SCOPE_DEFINED && $confirmedCount === 0,
            "Illegal state reached: scope_defined with zero CONFIRMED. A: {$procA->getOutput()} B: {$procB->getOutput()}"
        );
    }

    public function test_concurrency_2_reconcile_to_empty_races_send_quote(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $actor = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));
        $opportunity = Opportunity::on('mysql')->create(Opportunity::factory()->raw(['tenant_id' => $tenant->id]));
        OpportunityServiceLine::on('mysql')->create([
            'tenant_id' => $tenant->id, 'opportunity_id' => $opportunity->id,
            'service_line' => ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::CONFIRMED,
        ]);
        $quote = Quote::on('mysql')->create(Quote::factory()->raw(['tenant_id' => $tenant->id, 'opportunity_id' => $opportunity->id, 'status' => Quote::STATUS_DRAFT]));
        \App\Models\QuoteLineItem::on('mysql')->create(\App\Models\QuoteLineItem::factory()->raw(['tenant_id' => $tenant->id, 'quote_id' => $quote->id]));

        $php = (new PhpExecutableFinder())->find();
        $procA = new Process([$php, 'artisan', 'opportunity:concurrency-test-send-quote', $quote->id], base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process([$php, 'artisan', 'opportunity:concurrency-test-reconcile', $opportunity->id, $actor->id], base_path(), ['DB_CONNECTION' => 'mysql']);

        $procA->start();
        $procB->start();
        $procA->wait();
        $procB->wait();

        $freshQuote = Quote::on('mysql')->find($quote->id);
        $confirmedCount = OpportunityServiceLine::on('mysql')
            ->where('opportunity_id', $opportunity->id)
            ->where('provenance', ServiceLineProvenance::CONFIRMED)
            ->count();

        $this->assertFalse(
            $freshQuote->status === Quote::STATUS_SENT && $confirmedCount === 0,
            "Illegal state reached: Quote SENT with zero CONFIRMED. A: {$procA->getOutput()} B: {$procB->getOutput()}"
        );
    }

    public function test_concurrency_3_legacy_update_rolls_back_scalar_when_mapper_reconciliation_fails(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $opportunity = Opportunity::on('mysql')->create(Opportunity::factory()->raw(['tenant_id' => $tenant->id, 'service_category' => 'architecture']));

        $php = (new PhpExecutableFinder())->find();
        $proc = new Process([$php, 'artisan', 'opportunity:concurrency-test-update-category', $opportunity->id, 'construction', '--fail-mapper-write'], base_path(), ['DB_CONNECTION' => 'mysql']);
        $proc->run();

        $this->assertSame(1, $proc->getExitCode());

        $fresh = Opportunity::on('mysql')->find($opportunity->id);
        $this->assertSame('architecture', $fresh->service_category, 'Scalar must roll back together with the failed canonical reconciliation, no partial state.');
        $this->assertSame(0, OpportunityServiceLine::on('mysql')->where('opportunity_id', $opportunity->id)->where('service_line', ServiceLine::CONSTRUCTION)->count());
    }
}
```

- [ ] **Step 3: Run against MySQL if reachable in this environment** (`ZENA_INVARIANTS_DB=mysql
  DB_CONNECTION=mysql php artisan migrate:fresh --force && ./vendor/bin/phpunit --group stress
  tests/Feature/Concurrency/OpportunityServiceLineConcurrencyTest.php`); if MySQL is not reachable,
  report the SKIP honestly per this plan's Global Constraints — do not claim CONCURRENCY-1/2/3 passed
  without this exact command's real output.

- [ ] **Step 4: Sabotage-and-confirm** (per the existing repo convention documented inline in
  `RfiEscalationConcurrencyTest.php`) — temporarily remove one `lockForUpdate()` call, rerun, confirm
  the test now fails/flakes-toward-the-illegal-state, then restore the lock. Only claim the test is
  discriminating once this has actually been observed, not assumed.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ConcurrencyTest tests/Feature/Concurrency/OpportunityServiceLineConcurrencyTest.php
git commit -m "test(GAP-048): CONCURRENCY-1/2/3 real-MySQL subprocess race evidence"
```

---

### Task 13: Full regression pass + Gate-3 packet

- [ ] **Step 1:** `./vendor/bin/phpunit` (full suite, SQLite) — zero new failures, especially
  `tests/Feature/Crm/**`, `tests/Feature/Console/BackfillOpportunityServiceLinesTest.php`,
  `tests/Feature/Models/ServiceLineFoundationTest.php`, `tests/Feature/RouteRegistrationTest.php` (or
  repo-equivalent route/RBAC guardrail test), any tenant-isolation suite.
- [ ] **Step 2:** repository lint/static analysis commands (PHPStan, `scripts/ssot/lint_tests.sh`, or
  whatever `composer.json`'s `scripts` section names — read it before assuming a command name).
- [ ] **Step 3:** `git diff --stat dd7ed7c9..HEAD` — confirm every changed file is inside this plan's
  File Structure section; no drive-by scope creep.
- [ ] **Step 4:** Only after Steps 1-3 are green/clean with fresh command output pasted into the PR
  description, follow `docs/owner-governance/packet-schema.yml` (read live) to author
  `docs/owner-decisions/GAP-048/03-release.md` as `awaiting_owner`, compute the real
  `subject_sha`/`implementation_tree_digest`/`verified_pr_head_sha` per the repo's documented algorithm
  (grep for how GAP-046/GAP-047's `03-release.md` computed theirs and reuse the same mechanism/script,
  do not invent a new one). Open the implementation PR as Draft. Do not merge.
