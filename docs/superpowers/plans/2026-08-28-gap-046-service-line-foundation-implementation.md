---
work_id: GAP-046
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-046/02-design.md
plan_status: ready
derived_from:
  gate2_design: docs/superpowers/specs/2026-08-25-gap-046-service-line-foundation-design.md
  gate2_record: docs/owner-decisions/GAP-046/02-design.md
baseline_sha: 9944e1b50de515accb68bd5fd67347747620c6d3
---

# GAP-046 Implementation Plan — Canonical Service-Line Foundation

Strictly bounded by Gate-2 §11: 2 migrations, 2 thin models, shared
Service-Line/provenance constants, 2 relations (`Opportunity::serviceLines()`,
`Project::serviceLines()`), 1 Opportunity-side backfill command. No
controller/UI/route/RBAC change. No modification to any existing migration,
`Opportunity.service_category`, `OpportunityController`, `LeadController`,
`BusinessKpiService`, `DesignItemPageController`.

## 0. Baseline

- Implementation branch `feat/GAP-046-service-line-foundation` cut from
  `9944e1b50de515accb68bd5fd67347747620c6d3` (verified == origin/main).
- Baseline test run before any change: `php artisan test --testsuite=Unit,Feature,Integration`
  (SQLite, default `phpunit.xml` groups already exclude `performance` and
  `mysql-parity`). Must be green (or pre-existing failures documented) before
  writing any GAP-046 code.

## 1. Shared value definitions (new files, no dependency on anything else)

### 1.1 `app/Support/ServiceLine.php`
```php
<?php declare(strict_types=1);

namespace App\Support;

final class ServiceLine
{
    public const DESIGN = 'DESIGN';
    public const CONSTRUCTION = 'CONSTRUCTION';
    public const INSPECTION = 'INSPECTION';

    public const VALUES = [self::DESIGN, self::CONSTRUCTION, self::INSPECTION];
}
```

### 1.2 `app/Support/ServiceLineProvenance.php`
```php
<?php declare(strict_types=1);

namespace App\Support;

final class ServiceLineProvenance
{
    public const CONFIRMED = 'CONFIRMED';
    public const INFERRED = 'INFERRED';
    public const NEEDS_REVIEW = 'NEEDS_REVIEW';
    public const UNKNOWN = 'UNKNOWN';

    public const VALUES = [self::CONFIRMED, self::INFERRED, self::NEEDS_REVIEW, self::UNKNOWN];
}
```

Test (RED first): `tests/Unit/Support/ServiceLineTest.php` —
asserts `ServiceLine::VALUES === ['DESIGN','CONSTRUCTION','INSPECTION']` and
`ServiceLineProvenance::VALUES === ['CONFIRMED','INFERRED','NEEDS_REVIEW','UNKNOWN']`.
Command: `php artisan test --filter=ServiceLineTest`.

## 2. Migrations (additive only, new files, no edits to existing migrations)

### 2.1 `database/migrations/2026_08_28_120000_create_opportunity_service_lines_table.php`
Mirrors the `leads` table convention in
`2026_07_09_100000_create_leads_table.php` exactly (ULID PK, explicit ULID FK
columns with named constraints, cascadeOnDelete to parent, tenant FK to
`tenants`).

```php
Schema::create('opportunity_service_lines', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->ulid('tenant_id');
    $table->ulid('opportunity_id');
    $table->string('service_line');
    $table->string('provenance');
    $table->string('source')->nullable();
    $table->ulid('created_by')->nullable();
    $table->timestamps();

    $table->foreign('tenant_id', 'opp_service_lines_tenant_id_foreign')
        ->references('id')->on('tenants')->cascadeOnDelete();
    $table->foreign('opportunity_id', 'opp_service_lines_opportunity_id_foreign')
        ->references('id')->on('opportunities')->cascadeOnDelete();
    $table->foreign('created_by', 'opp_service_lines_created_by_foreign')
        ->references('id')->on('users')->nullOnDelete();

    $table->unique(['tenant_id', 'opportunity_id', 'service_line'], 'opp_service_lines_unique');
    $table->index(['tenant_id', 'opportunity_id'], 'opp_service_lines_tenant_opp_index');
});
```
`down()`: `Schema::dropIfExists('opportunity_service_lines');`

### 2.2 `database/migrations/2026_08_28_120001_create_project_service_lines_table.php`
Same shape, `project_id` FK to `projects.id` (ulid PK, unaffected by the
legacy `projects.tenant_id` string-typing — this migration does not touch
`projects` at all, only adds a new child table referencing `projects.id`).

```php
Schema::create('project_service_lines', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->ulid('tenant_id');
    $table->ulid('project_id');
    $table->string('service_line');
    $table->string('provenance');
    $table->string('source')->nullable();
    $table->ulid('created_by')->nullable();
    $table->timestamps();

    $table->foreign('tenant_id', 'proj_service_lines_tenant_id_foreign')
        ->references('id')->on('tenants')->cascadeOnDelete();
    $table->foreign('project_id', 'proj_service_lines_project_id_foreign')
        ->references('id')->on('projects')->cascadeOnDelete();
    $table->foreign('created_by', 'proj_service_lines_created_by_foreign')
        ->references('id')->on('users')->nullOnDelete();

    $table->unique(['tenant_id', 'project_id', 'service_line'], 'proj_service_lines_unique');
    $table->index(['tenant_id', 'project_id'], 'proj_service_lines_tenant_proj_index');
});
```
`down()`: `Schema::dropIfExists('project_service_lines');`

Verification (RED before writing migration is N/A — schema has no failing
test to write first for the DDL itself; verification is round-trip execution,
not TDD): after both files exist, run
`php artisan migrate:fresh --env=testing` (SQLite) and confirm exit 0, then
`php artisan migrate:rollback --step=2 --env=testing` and confirm both new
tables are dropped and no pre-existing table/column changed (`php artisan
migrate:status`). Real-MySQL round-trip is done in §8 (disposable harness).

## 3. Concern: tenant-parent integrity + value validation (shared trait)

### 3.1 `app/Models/Concerns/EnforcesServiceLineIntegrity.php`
```php
<?php declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use InvalidArgumentException;
use RuntimeException;

trait EnforcesServiceLineIntegrity
{
    protected static function bootEnforcesServiceLineIntegrity(): void
    {
        static::creating(function ($model): void {
            if (!in_array($model->service_line, ServiceLine::VALUES, true)) {
                throw new InvalidArgumentException(
                    "Invalid service_line [{$model->service_line}]; must be one of: " . implode(', ', ServiceLine::VALUES)
                );
            }

            if (!in_array($model->provenance, ServiceLineProvenance::VALUES, true)) {
                throw new InvalidArgumentException(
                    "Invalid provenance [{$model->provenance}]; must be one of: " . implode(', ', ServiceLineProvenance::VALUES)
                );
            }

            $parentTenantId = $model->resolveParentTenantId();

            if ($parentTenantId === null) {
                throw new RuntimeException('Cannot create a service-line row without a resolvable parent.');
            }

            if ($model->tenant_id !== null && (string) $model->tenant_id !== (string) $parentTenantId) {
                throw new RuntimeException(
                    'Cross-tenant service-line write rejected: child tenant_id does not match parent tenant_id.'
                );
            }

            $model->tenant_id = $parentTenantId;
        });
    }

    /**
     * Resolve the true tenant_id of this row's parent, bypassing any
     * tenant global scope so the check is truthful regardless of the
     * acting/current tenant context.
     */
    abstract protected function resolveParentTenantId(): ?string;
}
```

- `service_line`/`provenance` invalid values throw `InvalidArgumentException`
  (covers acceptance B and the provenance-invalid criterion).
- tenant derivation/rejection covers acceptance I.
- `resolveParentTenantId()` implemented per-model in §4, using
  `withoutGlobalScope('tenant')` (or `withoutGlobalScopes()`) plus a plain
  `find()` on the immutable parent id, so the true parent tenant is read
  regardless of the caller's current tenant context — this is exactly what
  makes the "fail closed" check truthful instead of self-scoping-blind.

Test (RED first): `tests/Unit/Models/Concerns/EnforcesServiceLineIntegrityTest.php`
using a minimal in-memory double is impractical (trait needs a real model) —
instead this trait is tested directly through the two concrete models in §4's
tests (acceptance A/B/I are proven there). No standalone trait test file.

## 4. Models

### 4.1 `app/Models/OpportunityServiceLine.php`
```php
<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\EnforcesServiceLineIntegrity;
use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $opportunity_id
 * @property string $service_line
 * @property string $provenance
 * @property string|null $source
 * @property string|null $created_by
 */
class OpportunityServiceLine extends Model
{
    use HasUlids;
    use TenantScope;
    use EnforcesServiceLineIntegrity;

    protected $table = 'opportunity_service_lines';

    protected $fillable = [
        'opportunity_id',
        'service_line',
        'provenance',
        'source',
        'created_by',
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'opportunity_id');
    }

    protected function resolveParentTenantId(): ?string
    {
        $opportunity = Opportunity::query()
            ->withoutGlobalScope('tenant')
            ->find($this->opportunity_id);

        return $opportunity?->tenant_id;
    }
}
```
Note: `tenant_id` is deliberately absent from `$fillable` — it can only be
set by the trait's `creating` hook (defense in depth beyond mass-assignment
guarding: the hook also runs against a directly-set `tenant_id` property,
since Eloquent's magic `__set` bypasses `$fillable` entirely).

### 4.2 `app/Models/ProjectServiceLine.php`
Same shape, `project_id` FK, `resolveParentTenantId()` looks up
`Project::query()->withoutGlobalScope('tenant')->find($this->project_id)?->tenant_id`.

### 4.3 Relations added to existing models (ONLY these two additions, nothing else touched in these files)

`app/Models/Opportunity.php` — add:
```php
public function serviceLines(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(OpportunityServiceLine::class, 'opportunity_id');
}
```

`app/Models/Project.php` — add:
```php
public function serviceLines(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(ProjectServiceLine::class, 'project_id');
}
```

### Tests (RED → GREEN), `tests/Feature/Models/ServiceLineFoundationTest.php`

Helpers: build Tenant A/B via `Tenant::factory()->create()`, an Account +
Opportunity per tenant (pattern copied from
`tests/Feature/Models/OpportunityAppointmentModelTest.php::makeOpportunity`),
and a Project per tenant via `Project::factory()->create(['tenant_id' => ...])`.

1. `test_service_line_accepts_exactly_the_three_canonical_values` — create one
   row per canonical value via `$opportunity->serviceLines()->create([...])`,
   assert all 3 succeed and persist. **(A)**
2. `test_invalid_service_line_is_rejected` — attempt `service_line =>
   'UNKNOWN'` (and a garbage string) via the relation `create()`, assert
   `InvalidArgumentException` thrown, assert 0 rows persisted. **(B)**
3. `test_invalid_provenance_is_rejected` — attempt `provenance => 'BOGUS'`
   with a valid `service_line`, assert `InvalidArgumentException`, 0 rows.
4. `test_opportunity_service_lines_relation_returns_seeded_rows` — seed 2 rows
   for one Opportunity, assert `$opportunity->serviceLines()->pluck(...)`
   matches, and a second Opportunity's relation is empty.
5. `test_project_service_lines_relation_returns_seeded_rows` — same for
   `Project::serviceLines()`.
6. `test_tenant_scoped_visibility` — create rows under tenant A and tenant B,
   bind `app()->instance('tenant', $tenantA)`, assert
   `OpportunityServiceLine::query()->count()` only sees tenant A's rows;
   `forgetInstance('tenant')` in `finally`.
7. `test_duplicate_membership_is_rejected_by_unique_constraint` — insert
   `(tenant, opportunity, DESIGN)` twice, assert the second raises a DB
   unique-constraint exception (`QueryException`).
8. `test_tenant_id_is_derived_from_opportunity_parent_not_caller_input` —
   create via `$opportunity->serviceLines()->create(['service_line' =>
   ServiceLine::DESIGN, 'provenance' => ServiceLineProvenance::INFERRED])`
   with NO `tenant_id` passed; assert the persisted row's `tenant_id` equals
   the opportunity's `tenant_id`.
9. `test_cross_tenant_write_is_rejected_for_opportunity` — build an
   `OpportunityServiceLine` model directly (`new OpportunityServiceLine()`),
   set `tenant_id = $tenantA->id` and `opportunity_id =
   $opportunityOfTenantB->id` via direct property assignment (bypasses
   `$fillable`), call `save()`, assert `RuntimeException`, assert 0 rows
   persisted. **(I, Opportunity side)**
10. `test_cross_tenant_write_is_rejected_for_project` — same for
    `ProjectServiceLine`/`Project`. **(I, Project side)**
11. `test_migration_up_and_down_round_trip_leaves_no_trace` — assert both
    tables exist after migrate, assert `Schema::hasTable(...)` false after
    targeted rollback of just these 2 migrations, assert no other table
    changed (spot-check `opportunities`/`projects` column list unchanged).

Command for this file: `php artisan test --filter=ServiceLineFoundationTest`.
Each test written and observed RED (class/relation not yet existing →
class-not-found or method-not-found failure) before the corresponding
production code lands; commits are grouped per coherent slice (constants →
migrations → trait+models → relations), each slice's tests green before the
next slice starts.

## 5. Opportunity-side backfill command

### 5.1 `app/Console/Commands/BackfillOpportunityServiceLines.php`
Signature: `service-lines:backfill-opportunities {--dry-run} {--chunk=500}`.
Style mirrors `app/Console/Commands/BackfillInvitationTokenHash.php`
(chunked, dry-run flag, idempotent, logs summary).

Mapping (exactly per Gate-2 §7, case-sensitive on the legacy
`service_category` string values):

```php
private const MAP = [
    'architecture' => ServiceLine::DESIGN,
    'interior' => ServiceLine::DESIGN,
    'landscape' => ServiceLine::DESIGN,
    'structure' => ServiceLine::DESIGN,
    'mep' => ServiceLine::DESIGN,
    'construction' => ServiceLine::CONSTRUCTION,
    // inspection, consulting, combined_package, null, unrecognized -> no row (absent from map)
];
```

Logic per opportunity (chunked by id, `Opportunity::query()->chunkById(...)`,
no explicit tenant scoping needed — console context has no bound tenant so
the `TenantScope` global scope is a no-op and every tenant's Opportunities
are visited):

```php
$line = self::MAP[$opportunity->service_category] ?? null;
if ($line === null) {
    continue; // no membership row — §7 cases E/F
}

$created = OpportunityServiceLine::query()->firstOrCreate(
    [
        'tenant_id' => $opportunity->tenant_id,
        'opportunity_id' => $opportunity->id,
        'service_line' => $line,
    ],
    [
        'provenance' => ServiceLineProvenance::INFERRED,
        'source' => 'backfill:legacy_service_category',
    ]
);
```
`firstOrCreate`'s lookup array keys exactly the unique constraint, making
re-runs idempotent (H) without a separate "already backfilled" flag table.
Because `service_line`/`provenance` are always valid literals from `MAP`
and `ServiceLineProvenance::INFERRED`, the trait's validation never rejects
a backfill-originated row. `tenant_id` in the lookup array is dropped by
mass-assignment guarding on `create()`'s branch (not in `$fillable`) and
re-derived by the trait from the parent — same value, so no behavior change,
just confirms the invariant holds even through `firstOrCreate`.

Dry-run mode: counts and prints the per-mapped-line breakdown without
writing. `--chunk` default 500.

### Tests (RED → GREEN), `tests/Feature/Console/BackfillOpportunityServiceLinesTest.php`

Seed one Opportunity per legacy `service_category` value (9 values) plus one
with `service_category = null` (requires `Opportunity::query()->withoutGlobalScope('tenant')->forceFill([...])->saveQuietly()`-style raw creation, or simply create then null the column via `DB::table('opportunities')->update(['service_category' => null])` since `service_category` has a DB default and is not nullable-validated at DB level — confirm column nullability first; if the column is NOT NULL, use the closest proxy for "unrecognized" instead: seed a genuinely unrecognized string, e.g. `'totally_unknown_value'`, written directly via `DB::table('opportunities')->where('id', $id)->update(['service_category' => 'totally_unknown_value'])` to bypass the app-level `Rule::in` validation that only exists at controller layer, not model layer — Eloquent itself does not enforce `VALID_SERVICE_CATEGORIES`).

1. `test_architecture_family_creates_only_design_inferred_rows` — for each of
   architecture/interior/landscape/structure/mep, assert exactly 1 row,
   `service_line=DESIGN`, `provenance=INFERRED`. **(C)**
2. `test_construction_creates_only_construction_inferred_row` — **(D)**
3. `test_inspection_consulting_combined_package_create_zero_rows` — **(E)**
4. `test_unrecognized_or_null_creates_zero_rows` — **(F)**
5. `test_backfill_never_creates_confirmed_provenance` — assert
   `OpportunityServiceLine::query()->where('provenance', 'CONFIRMED')->count()
   === 0` after a full run across all seeded opportunities. **(G)**
6. `test_backfill_is_idempotent` — run the command twice, assert row count
   unchanged between runs, no exception, no provenance mutated. **(H)**
7. `test_dry_run_writes_nothing` — `--dry-run`, assert 0 rows created.
8. `test_service_category_column_is_never_modified_by_backfill` — snapshot
   `service_category` values before/after, assert byte-identical.

Command: `php artisan test --filter=BackfillOpportunityServiceLinesTest`.

## 6. Project-side backfill — explicitly NOT built (proves J)

No command/job/migration data-fill touches `project_service_lines` anywhere
in this plan. Test `tests/Feature/Models/ServiceLineFoundationTest.php::
test_project_service_lines_table_has_zero_rows_after_full_backfill_run` —
after running `service-lines:backfill-opportunities` (including against
Opportunities that have `converted_project_id` set to a real, pre-existing
Project), assert `ProjectServiceLine::query()->count() === 0`. **(J)**

## 7. No runtime propagation — proves K

Test `tests/Feature/Crm/OpportunityConversionUnchangedTest.php` (new file,
narrowly scoped): drive the existing WON→Project conversion path exactly as
it exists today (call whatever `OpportunityController::convert()`/
`createContract()` currently does, unmodified) and assert
`ProjectServiceLine::query()->where('project_id', $newProject->id)->count()
=== 0` immediately after conversion. This test exercises **existing,
unmodified** conversion code — it does not add any wiring; it is a negative
assertion proving GAP-046 added no propagation. If the existing conversion
flow requires non-trivial fixture setup, reuse the closest existing
conversion test's fixture setup (locate via `grep -rl
"OpportunityController::convert\|->convert(" tests/Feature` before writing,
and copy its arrange-phase exactly) rather than inventing new setup.
**Constraint: this test file must not modify `OpportunityController`,
`LeadController`, or any conversion code path — read-only exercise + assert.**

## 8. Regression: BusinessKpiService / DesignItemPageController — proves item 7 of §12

No changes made to either file. Run their existing test suites unmodified:
```
php artisan test --filter=BusinessKpiServiceTest
php artisan test --filter=DesignItemPageController
php artisan test --filter=AiDesignItemSuggestion
```
(exact test class names to be confirmed via `grep -rl "BusinessKpiService\|DesignItemPageController\|AiDesignItemSuggestion" tests/` at execution time — run whatever exists). Must be green, byte-identical pass/fail status to the pre-GAP-046 baseline run in §0.

## 9. SQLite full-suite regression

```
php artisan test --testsuite=Unit,Feature,Integration
```
Must be green (excluding any pre-existing red documented in §0's baseline).

## 10. Real-MySQL parity evidence (disposable harness pattern per GAP-043/044)

Follow the exact mechanism recorded in `docs/owner-decisions/GAP-043/03-release.md`:
1. Tag the two new migrations' test classes (`ServiceLineFoundationTest`,
   `BackfillOpportunityServiceLinesTest`) with `#[Group('mysql-parity')]` (or
   add them to a `mysql-parity`-tagged subset) so they run under the
   existing `.github/workflows/routes-guardrails.yml` MySQL service
   container without inventing new CI infrastructure.
2. Push the implementation branch, open the Draft PR (§11 below) — this
   alone triggers `routes-guardrails.yml` against a real `mysql:8.0` service
   container, which runs `php artisan migrate:fresh --force` (proving the
   new migrations' `up()` against real MySQL as part of the normal schema
   build) and `--group=mysql-parity` tests.
3. If the two new test files are not picked up by the existing
   `mysql-parity` group filter, use the GAP-043-style disposable harness: a
   throwaway branch overlaying a one-line CI-selector addition onto
   `.github/workflows/automated-testing.yml` or `routes-guardrails.yml` to
   explicitly run `php artisan test --filter=ServiceLineFoundationTest
   --filter=BackfillOpportunityServiceLinesTest` against the MySQL service
   container, capture the live run/job ID, then delete the throwaway branch
   without merging it. Record both the disposable-harness commit SHA and
   its parent (the real implementation commit) distinctly in the Gate-3
   packet, exactly as GAP-043 did.
4. Explicitly capture and distinguish in the Gate-3 packet: SQLite migration
   round-trip result vs. real-MySQL migration round-trip result vs.
   application-layer tenant-congruence enforcement (§3, proven by tests, not
   a DB constraint) vs. actual DB FK/unique constraints (proven by the
   unique-constraint-violation test in §4 and by inspecting real MySQL
   `SHOW CREATE TABLE opportunity_service_lines` / `project_service_lines`
   output from the live run).

## 11. Draft PR

Branch `feat/GAP-046-service-line-foundation`. PR body first line exactly:
`Work ID: GAP-046`. Body cross-references Gate 1 PR #287, Gate 2 PR #288,
canonical Gate-2 merge `9944e1b50de515accb68bd5fd67347747620c6d3`. Created as
Draft, kept Draft — not marked Ready, not merged, not deployed by this plan.

## 12. Scope audit before opening the PR

`git diff --stat 9944e1b50de515accb68bd5fd67347747620c6d3..HEAD` must show
exactly: 2 new migration files, `app/Support/ServiceLine.php`,
`app/Support/ServiceLineProvenance.php`,
`app/Models/Concerns/EnforcesServiceLineIntegrity.php`,
`app/Models/OpportunityServiceLine.php`, `app/Models/ProjectServiceLine.php`,
`app/Models/Opportunity.php` (relation-only diff),
`app/Models/Project.php` (relation-only diff),
`app/Console/Commands/BackfillOpportunityServiceLines.php`, the new test
files from §4/§5/§7, this plan file, and (after §13) the Gate-3 packet. Any
other changed file is out of scope and must be explained or reverted before
opening the PR. Explicitly grep the diff for `OpportunityController`,
`LeadController`, `CrmPageController`, `DesignItemPageController`,
`BusinessKpiService`, and any existing migration filename — none may appear
except in the read-only regression test of §7/§8 (which must show 0 lines
changed in those production files).

## 13. Gate-3 packet

After all of the above is green and pushed, prepare
`docs/owner-decisions/GAP-046/03-release.md` per the dispatching session's
governance format (gate_status: awaiting_owner, technical_readiness.value:
ready, owner_decision.value: none/human_owner, decision_requested:
"approve_or_correction_or_defer"), computing the implementation-tree digest
via `scripts/ssot/owner_governance_lint.php`'s
`owner_governance_compute_implementation_tree_digest` mechanism bound to the
exact implementation head SHA, and run
`php scripts/ssot/owner_governance_lint.php`,
`php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering`, and
`php scripts/ci/lint-mysql-claim-truthfulness.php` locally before presenting
the packet.

## Explicit scope exclusions (restated, binding)

No CRM classification UX, no stage gates, no Quote Scope Snapshot, no
Contract Service-Line classification, no Portfolio membership behavior, no
Project OPPM, no Operations Control Tower, no Finance/Treasury, no
historical Project backfill, no `projects.tenant_id` schema cleanup, no
runtime review/remediation UI, no unrelated refactors, no modification to
`Opportunity.service_category`'s default or validation, no modification to
any existing migration file.
