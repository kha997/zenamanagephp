# Hardening Slice + Completion Roadmap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the 4 verified, code-fixable hardening gaps left open by the 2026-07-12 system audit (AI-endpoint rate limiting, missing tenant global scopes, undocumented `ANTHROPIC_API_KEY`, stray unmounted route files), and record the prioritized backlog of follow-up plans needed to complete the ZENA 10-goal vision.

**Architecture:** Pure additive hardening on existing Laravel 12 patterns — a new named `RateLimiter` in `RouteServiceProvider` (same style as the existing `invitation-accept` limiter), the existing `App\Traits\TenantScope` trait applied to 4 CRM/design models that currently rely on manual `where('tenant_id')`, and file deletions of route files proven unmounted. No new services, no schema changes, no behavior change for correctly-scoped requests.

**Tech Stack:** Laravel 12, PHPUnit (`php artisan test`), existing test helpers `Tests\Traits\TenantUserFactoryTrait`, `Tenant::factory()`, `Http::fake()`.

## Global Constraints

- Never touch anything under `src/CoreProject/{Controllers,Services,Listeners}` or the `/api/v1/*` compatibility runtime — it is a live, intentionally-frozen surface guarded by `tests/Feature/Architecture/ModuleOwnership*InvariantTest.php` and `docs/architecture/module-ownership-ssot.md`.
- Reachability rule for this codebase: a file is NOT dead just because `Route::` greps miss it — `routes/api.php` mounts 7 module route files via raw `require base_path(...)` (see `docs/architecture/project-model-reference-inventory.md`, methodology note). Every "unused file" claim in this plan was verified against `require`/`base_path` mounts too; re-verify before deleting anything not listed here.
- Do not add `rbac:*` middleware to routes that already do Policy-based authorization in the controller (audit lesson: it breaks the friendly-error UX and existing tests).
- All new tests follow existing conventions: `declare(strict_types=1)`, `RefreshDatabase`, `TenantUserFactoryTrait`, alias the `rbac` middleware in `setUp()` exactly as `tests/Feature/Api/CrmApiTest.php` does.
- Run targeted test files during TDD loops; run the two `ModuleOwnership*InvariantTest` files plus `ProjectModelReferenceAllowlistTest` before the final commit of the branch.
- Commit messages follow the repo's conventional-commit style (`feat:`, `fix:`, `test:`, `chore:`, `docs:` with module scope).

## Context: verified current state (2026-07-12)

All four findings below were re-verified against the working tree today — they are real, current, and independent of each other:

1. `routes/web.php:975` (`design-items.suggest-description`) and `routes/web.php:983` (`crm.leads.suggest-conversion`) call the paid Anthropic API with **no throttle middleware**, while comparable sensitive endpoints (`api-tokens.store`, portal login, invitation-accept) are all throttled.
2. `App\Models\{DesignItem, Lead, Opportunity, Account, Invitation}` do **not** use the `App\Traits\TenantScope` global-scope trait — tenant isolation relies purely on each query site remembering `where('tenant_id', ...)`. The trait exists, is used by `Document`, `Team`, `Material`, etc., and no-ops safely when no tenant is bound (verified `app/Traits/TenantScope.php:18-34`).
3. `ANTHROPIC_API_KEY` (read by `config/ai.php`) is missing from both `.env.example` and `env.example`; `.env.example` ships `QUEUE_CONNECTION=sync` with no production warning.
4. `routes/` contains 8 files mounted **nowhere** (verified against `app/Providers/RouteServiceProvider.php`, `bootstrap/app.php`, and every `require` in mounted route files): `api_consolidated.php`, `api_dashboard.php`, `api_dashboard.php.backup`, `api_zena.php.backup`, `web.php.backup.20250924_212141`, `web_backup_20250921_201745.php`, `web_backup_20250922_123959.php`, `web_clean.php`. (`health.php` also appears unmounted but is intentionally left out of scope here — verify separately if desired.) Mounted files, for reference, are: `api.php`, `api-simple.php`, `api_zena.php` (via `require` at `routes/api.php:1016`), `web.php`, `debug.php`, `debug_api.php`, `channels.php`, `console.php`, plus `routes/legacy/*` and the 7 `src/*/routes/api.php` module mounts.

**Deliberately out of scope for this plan** (each needs its own discovery/plan — see the roadmap section at the bottom): `Invitation` model tenant-scoping (its accept flow may legitimately be queried cross-tenant — must be traced first), `LegacyDocumentAdapter` unification, PHPStan enforcement, the 40 not-yet-traced `Src\CoreProject\Models\Project` references, and all feature-building work on vision goals #2/#4/#6/#7.

---

### Task 1: Rate-limit the two AI suggestion endpoints

**Files:**
- Modify: `app/Providers/RouteServiceProvider.php` (inside `configureRateLimiting()`, after the `invitation-accept` limiter ending near line 90)
- Modify: `routes/web.php:975` and `routes/web.php:983`
- Modify: `.env.example` and `env.example` (document `ANTHROPIC_API_KEY`; warn on `QUEUE_CONNECTION=sync`)
- Test: `tests/Feature/Zena/AiSuggestRateLimitTest.php` (new)

**Interfaces:**
- Consumes: existing `RateLimiter::for(...)` pattern in `RouteServiceProvider::configureRateLimiting()`; existing route definitions; `Tests\Traits\TenantUserFactoryTrait::createTenantUser()`.
- Produces: a named limiter `ai-suggest` (10 requests/min keyed by user id + IP) that any future AI endpoint must also attach via `throttle:ai-suggest`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Zena/AiSuggestRateLimitTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class AiSuggestRateLimitTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private Tenant $tenant;
    private User $user;
    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        config(['ai.anthropic_api_key' => 'test-key']);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['crm.view', 'crm.manage', 'ai.suggest', 'design-item.view', 'design-item.manage']
        );

        $this->lead = Lead::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contact_hint' => 'Chi Lan - 090xxxxxxx',
            'project_description' => 'Can ho 2 phong ngu can thiet ke noi that',
            'source' => 'zalo',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->user->id,
        ]);
    }

    public function test_both_ai_routes_declare_the_ai_suggest_throttle(): void
    {
        foreach (['operator.crm.leads.suggest-conversion', 'operator.design-items.suggest-description'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} not found");
            $this->assertContains(
                'throttle:ai-suggest',
                $route->gatherMiddleware(),
                "Route {$name} is missing throttle:ai-suggest"
            );
        }
    }

    public function test_eleventh_request_within_a_minute_is_throttled(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_lead_conversion',
                    'input' => ['service_category' => 'interior', 'scope_summary' => 'Tóm tắt.'],
                ]],
            ], 200),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)->get(route('operator.crm.leads'), $headers)->assertOk();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->user)
                ->post(route('operator.crm.leads.suggest-conversion', $this->lead->id), [], $headers)
                ->assertOk();
        }

        $this->actingAs($this->user)
            ->post(route('operator.crm.leads.suggest-conversion', $this->lead->id), [], $headers)
            ->assertStatus(429);
    }
}
```

Note on route names: the two routes live inside the operator route group; the existing `AiLeadSuggestionTest` resolves them as `operator.crm.leads.suggest-conversion`. If `getByName` returns null in Step 2 for a reason other than the missing throttle, run `php artisan route:list | grep suggest` and use the exact names it prints — do not guess.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Zena/AiSuggestRateLimitTest.php`
Expected: FAIL — `test_both_ai_routes_declare_the_ai_suggest_throttle` fails asserting `throttle:ai-suggest` is missing, and `test_eleventh_request_within_a_minute_is_throttled` fails because the 11th request returns 200.

- [ ] **Step 3: Add the named limiter**

In `app/Providers/RouteServiceProvider.php`, inside `configureRateLimiting()`, append after the `invitation-accept` limiter's closing `});`:

```php
        RateLimiter::for('ai-suggest', function (Request $request) {
            $user = $request->user();
            $userId = $user ? (string) $user->id : 'guest';

            return Limit::perMinute(10)->by($userId . '|' . $request->ip());
        });
```

(`RateLimiter`, `Limit`, and `Request` are already imported by the existing limiters in this file.)

- [ ] **Step 4: Attach the throttle to both routes**

In `routes/web.php`:

Line 975 — change:

```php
    Route::post('/design-items/suggest-description', [App\Http\Controllers\Web\DesignItemPageController::class, 'suggestDescription'])->middleware(['rbac:design-item.manage', 'rbac:ai.suggest'])->name('design-items.suggest-description');
```

to:

```php
    Route::post('/design-items/suggest-description', [App\Http\Controllers\Web\DesignItemPageController::class, 'suggestDescription'])->middleware(['rbac:design-item.manage', 'rbac:ai.suggest', 'throttle:ai-suggest'])->name('design-items.suggest-description');
```

Line 983 — change:

```php
    Route::post('/crm/leads/{id}/suggest-conversion', [App\Http\Controllers\Web\CrmPageController::class, 'suggestLeadConversion'])->middleware(['rbac:crm.manage', 'rbac:ai.suggest'])->name('crm.leads.suggest-conversion');
```

to:

```php
    Route::post('/crm/leads/{id}/suggest-conversion', [App\Http\Controllers\Web\CrmPageController::class, 'suggestLeadConversion'])->middleware(['rbac:crm.manage', 'rbac:ai.suggest', 'throttle:ai-suggest'])->name('crm.leads.suggest-conversion');
```

- [ ] **Step 5: Run the new test to verify it passes**

Run: `php artisan test tests/Feature/Zena/AiSuggestRateLimitTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the existing AI suggestion tests to verify no regression**

Run: `php artisan test tests/Feature/Zena/AiLeadSuggestionTest.php tests/Feature/Zena/AiDesignItemSuggestionTest.php`
Expected: PASS. (If `AiDesignItemSuggestionTest.php` does not exist under that exact name, run `ls tests/Feature/Zena | grep -i ai` and run whatever AI test files exist.) If an existing test now hits 429 because it makes >10 suggest calls in one test run, raise the limiter to `perMinute(20)` rather than weakening the test isolation — but this is unlikely; each test method gets a fresh app.

- [ ] **Step 7: Document the env vars**

In `.env.example`, append at the end:

```
# Anthropic API key for AI suggestions (Lead conversion, DesignItem description).
# Leave unset to disable AI features — AiAssistService fails closed (returns null).
ANTHROPIC_API_KEY=
AI_MODEL=claude-haiku-4-5-20251001
```

and change the line `QUEUE_CONNECTION=sync` to:

```
# sync is for local dev only — use redis (or database) in production,
# otherwise queued jobs (notifications, exports) run inline in the request.
QUEUE_CONNECTION=sync
```

In `env.example`, append the same `ANTHROPIC_API_KEY`/`AI_MODEL` block at the end (its `QUEUE_CONNECTION` is already `redis`; leave it alone).

- [ ] **Step 8: Commit**

```bash
git add app/Providers/RouteServiceProvider.php routes/web.php tests/Feature/Zena/AiSuggestRateLimitTest.php .env.example env.example
git commit -m "feat(security): rate-limit AI suggestion endpoints and document ANTHROPIC_API_KEY"
```

---

### Task 2: Apply the TenantScope global scope to 4 CRM/design models

**Files:**
- Modify: `app/Models/Lead.php`, `app/Models/Account.php`, `app/Models/Opportunity.php`, `app/Models/DesignItem.php`
- Test: `tests/Feature/Models/TenantScopedCrmModelsTest.php` (new)

**Interfaces:**
- Consumes: `App\Traits\TenantScope` (existing trait at `app/Traits/TenantScope.php` — boots a global scope filtering by `app('tenant')->id`, `app('current_tenant_id')`, or the request `tenant_id` attribute, and no-ops when none is bound).
- Produces: automatic tenant filtering on every Eloquent query against these 4 models whenever a tenant is bound; existing manual `where('tenant_id', ...)`/`scopeForTenant` calls become redundant-but-harmless duplicates (same value, same column).

**Explicitly excluded:** `App\Models\Invitation`. Its accept flow looks up invitations by token for users who may not yet belong to the target tenant; applying a tenant global scope could hide the invitation mid-acceptance. Do NOT add the trait to Invitation in this task — it is queued as roadmap item R1 (trace first, then decide).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Models/TenantScopedCrmModelsTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Account;
use App\Models\DesignItem;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Traits\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopedCrmModelsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Static guard: these tenant-owned models must keep the TenantScope
     * trait so isolation never again depends on per-query where() calls.
     */
    public function test_tenant_owned_crm_models_use_the_tenant_scope_trait(): void
    {
        foreach ([Lead::class, Account::class, Opportunity::class, DesignItem::class] as $model) {
            $this->assertContains(
                TenantScope::class,
                class_uses_recursive($model),
                "{$model} must use App\\Traits\\TenantScope"
            );
        }
    }

    public function test_lead_queries_are_scoped_to_the_bound_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $makeLead = fn (Tenant $t, string $hint) => Lead::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => (string) $t->id,
            'contact_hint' => $hint,
            'project_description' => 'desc',
            'source' => 'zalo',
            'status' => Lead::STATUS_NEW,
        ]);

        $makeLead($tenantA, 'lead-a');
        $makeLead($tenantB, 'lead-b');

        app()->instance('tenant', $tenantA);
        try {
            $hints = Lead::query()->pluck('contact_hint')->all();
            $this->assertSame(['lead-a'], $hints);
        } finally {
            app()->forgetInstance('tenant');
        }
    }

    public function test_lead_queries_are_unscoped_when_no_tenant_is_bound(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        foreach ([[$tenantA, 'lead-a'], [$tenantB, 'lead-b']] as [$tenant, $hint]) {
            Lead::query()->create([
                'tenant_id' => (string) $tenant->id,
                'contact_hint' => $hint,
                'project_description' => 'desc',
                'source' => 'zalo',
                'status' => Lead::STATUS_NEW,
            ]);
        }

        $this->assertSame(2, Lead::query()->count());
    }
}
```

Implementation notes for this step, read before running:
- If `Lead::STATUS_NEW` requires `captured_by`, add `'captured_by' => null` or create a user — copy whatever `tests/Feature/Zena/AiLeadSuggestionTest.php:40-47` does.
- The `withoutGlobalScope('tenant')` in `$makeLead` matters: once the trait is applied, creating tenant B's row while tenant A could be bound must not be silently filtered on the follow-up read. `create()` itself is unaffected by global scopes, but keeping the escape hatch explicit documents intent.
- The trait registers its scope under the string name `'tenant'` (`static::addGlobalScope('tenant', ...)` at `app/Traits/TenantScope.php:20`).

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Models/TenantScopedCrmModelsTest.php`
Expected: FAIL — `test_tenant_owned_crm_models_use_the_tenant_scope_trait` fails for all 4 classes; `test_lead_queries_are_scoped_to_the_bound_tenant` fails returning both hints.

- [ ] **Step 3: Apply the trait to the 4 models**

In each of `app/Models/Lead.php`, `app/Models/Account.php`, `app/Models/Opportunity.php`, `app/Models/DesignItem.php`:

Add the import below the existing `use` statements at the top of the file:

```php
use App\Traits\TenantScope;
```

Add the trait inside the class body next to any existing traits (e.g. `use HasFactory;` / `use HasUlids;` — match each file's existing style):

```php
    use TenantScope;
```

Do not remove any existing `scopeForTenant()` methods or manual `where('tenant_id', ...)` calls in controllers — they are now redundant but harmless, and removing them is riskier than leaving them.

- [ ] **Step 4: Run the new test to verify it passes**

Run: `php artisan test tests/Feature/Models/TenantScopedCrmModelsTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Run the CRM and design-item feature suites to catch scope regressions**

Run: `php artisan test tests/Feature/Api/CrmApiTest.php tests/Feature/Zena/OperatorCrmUiTest.php tests/Feature/Zena/AiLeadSuggestionTest.php`
Then: `php artisan test --filter=DesignItem`
Expected: PASS everywhere.

If a test fails with rows unexpectedly missing, the failing query is running with a *different* tenant bound (not no tenant). That is the global scope surfacing a real pre-existing cross-tenant read — investigate the query site before weakening anything; fix the caller's tenant binding, not the trait. If it is an admin/cross-tenant listing that is *supposed* to see all tenants, add `->withoutGlobalScope('tenant')` at that call site with a one-line comment stating why cross-tenant read is intended.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Lead.php app/Models/Account.php app/Models/Opportunity.php app/Models/DesignItem.php tests/Feature/Models/TenantScopedCrmModelsTest.php
git commit -m "feat(security): apply TenantScope global scope to Lead, Account, Opportunity, DesignItem"
```

---

### Task 3: Delete the 8 unmounted stray route files

**Files:**
- Delete: `routes/api_consolidated.php`, `routes/api_dashboard.php`, `routes/api_dashboard.php.backup`, `routes/api_zena.php.backup`, `routes/web.php.backup.20250924_212141`, `routes/web_backup_20250921_201745.php`, `routes/web_backup_20250922_123959.php`, `routes/web_clean.php`

**Interfaces:**
- Consumes: nothing — these files are loaded by nothing (verified below, and re-verified as Step 1).
- Produces: a `routes/` directory where every `.php` file is actually mounted, so future maintainers/audits stop tracing dead surfaces.

- [ ] **Step 1: Re-verify each file is unmounted (do not skip)**

Run each of these; ALL must produce no output before deleting:

```bash
grep -rn "api_consolidated\|api_dashboard\|web_clean\|web_backup_20250921\|web_backup_20250922\|web.php.backup" \
  app/ bootstrap/ config/ routes/api.php routes/web.php routes/api_zena.php routes/api-simple.php \
  routes/debug.php routes/debug_api.php routes/channels.php routes/console.php routes/health.php \
  src/*/routes/ tests/ composer.json phpunit.xml 2>/dev/null | grep -v "^Binary"
```

Expected: no output. If ANY line prints, remove that file from the deletion list, keep it, and note it in the commit message — do not force the deletion.

- [ ] **Step 2: Delete via git**

```bash
git rm routes/api_consolidated.php routes/api_dashboard.php routes/api_dashboard.php.backup \
       routes/api_zena.php.backup routes/web.php.backup.20250924_212141 \
       routes/web_backup_20250921_201745.php routes/web_backup_20250922_123959.php routes/web_clean.php
```

If `git rm` is blocked by a permission boundary in the executor environment (this has happened in past sessions), fall back to renaming each file with a `.unmounted-backup` suffix via `git mv`, which achieves the "no live .php confusion" goal without deletion.

- [ ] **Step 3: Prove the app still boots and routes still resolve**

```bash
php artisan route:list > /dev/null && echo ROUTES_OK
php artisan test tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php tests/Feature/Architecture/ModuleOwnershipSourceInvariantTest.php
```

Expected: `ROUTES_OK` printed; both invariant test files PASS.

- [ ] **Step 4: Commit**

```bash
git commit -m "chore(routes): delete 8 unmounted backup/stray route files"
```

---

### Task 4: Final verification of the whole slice

**Files:** none new.

- [ ] **Step 1: Run the full targeted regression set**

```bash
php artisan test tests/Feature/Zena/AiSuggestRateLimitTest.php \
  tests/Feature/Models/TenantScopedCrmModelsTest.php \
  tests/Feature/Api/CrmApiTest.php \
  tests/Feature/Zena/AiLeadSuggestionTest.php \
  tests/Feature/Architecture/
```

Expected: PASS everywhere.

- [ ] **Step 2: Run the broader feature suite once**

```bash
php artisan test --testsuite=Feature
```

Expected: PASS, or only failures that also fail on the base commit (verify any failure against `git stash`-free base by checking out the pre-slice commit in a separate worktree — never `git stash` in this repo; the stash stack is shared across sessions). Known pre-existing flake: wall-clock timing assertions (e.g. `PerformanceTest`) may fail on a slow machine — re-run once before investigating.

- [ ] **Step 3: Commit any final fixes and stop**

Do not merge, do not open a PR unless the user asked — report results and wait.

---

## Completion roadmap (follow-up plans — each needs its own brainstorm + plan before implementation)

These are deliberately NOT tasks in this plan. They are ordered by (business value × risk-reduction) ÷ effort, per the code-verified 10-goal coverage table in the 2026-07-12 audit. Each entry lists the trigger question a future planner must answer first.

**User direction (2026-07-13):** "project management" in the ZENA vision explicitly includes BOTH design-project management (per design project: which tasks exist, who is assigned, what is done/not done, what difficulties came up, is the design option approved yet, which revision number we are on) AND construction management. This elevates R-DPM below above the feature items R5-R8.

**R-DPM — Design-project management completion (high priority per user direction).** Code-verified current state:
- ALREADY EXISTS: task breakdown per project (`App\Models\Task` with `assignee_id`, `status`, `progress_percent`, `estimated/actual_hours`, `dependencies_json`, `phase_id`, `client_approved`); templated design workflows (`WorkTemplate` → `WorkInstance` → `WorkInstanceStep` with `assignee_id`, `status`, `sla_hours`, `deadline_at`); client review cycle (`DesignItem.review_status`: draft → internal_review → sent_to_client → revision_requested → approved → final, plus `assigned_to`, `client_feedback_notes`, `approval_evidence`, `due_to_client_at`).
- MISSING 1 — revision counter/history: `STATUS_REVISION_REQUESTED` exists but nothing records *which* revision a DesignItem is on ("chỉnh sửa lần thứ mấy") or its revision timeline. Design: either an integer `revision_number` incremented on each transition INTO revision_requested, or (better) a `design_item_revisions` table capturing (revision_no, requested_at, feedback snapshot, resolved_at) — decide in brainstorm; check whether `AuditLog`/`ProjectActivity` already capture the transitions and can seed history.
- MISSING 2 — difficulties/blockers: no blocker concept anywhere in `app/Models` (verified by grep). Design: `blocked_at`/`blocker_note` on Task + DesignItem, or a first-class `Blocker` model linked polymorphically; surfaced on the project view and dashboards.
- MISSING 3 — a per-design-project answer view: one screen per project answering the 6 operator questions above (task list with assignee/status, blockers, DesignItem review states with revision numbers). Most data already exists — this is mostly a projection/UI slice once 1-2 land.
Blocked question for brainstorm: does the operator count revisions per DesignItem, per design phase, or per project?

**R1 — Invitation tenant-scoping decision (small).** Trace the invitation accept flow (`routes/web.php:475` area, `Invitation` model queries). Decide: trait + `withoutGlobalScope` at the accept lookup, or documented exclusion. Blocked question: is the accept lookup ever executed with a *different* tenant bound?

**R2 — Document upload path unification (medium).** 2 of 4 Document upload paths still construct `Src\DocumentManagement\Models\LegacyDocumentAdapter`; `document_type` is validated 3 inconsistent ways (strict 6-value enum vs free text) against the same column. Discovery first: enumerate the 4 paths, pick the canonical validation (per SSOT, `App\Models\Document` + `SimpleDocumentController` own Documents), converge. Guard with an architecture test like `ProjectModelReferenceAllowlistTest`.

**R3 — PHPStan enforcement decision (small config, big policy).** Both CI jobs run PHPStan with `continue-on-error: true` (`.github/workflows/ci-cd-code-quality-debug.yml:36-55`); the baseline holds ~42.5k lines of suppressions and a fresh run still finds unbaselined errors. Recommendation to bring to the user: regenerate the baseline once, flip `continue-on-error` to `false` so *new* errors block, and ratchet the baseline down opportunistically. This is a user decision — present options, don't pick unilaterally.

**R4 — `Src\CoreProject\Models\Project` consolidation continuation (large, gated).** 40 files in `docs/architecture/project-model-reference-inventory.md` are "Not yet traced". Follow that doc's methodology note verbatim (`require base_path` mounts first). The forward guard (`ProjectModelReferenceAllowlistTest`) already prevents growth; tracing can proceed in small batches.

**R5 — Vision goal #2: real quotation numbers (large, highest business value).** Native `boq_*` tables have no price columns at all; all real pricing lives in external zena-boq-core. Decide with the user: (a) deepen the integration (pull line-item prices into `external_quote_snapshot` and render a commercial quote document), or (b) add native price columns. Option (a) matches the existing Phase 2-4 integration direction.

**R6 — Vision goal #4: document/form generation (large).** Only one generated document type exists (contract PDF, fixed Blade). Goal: template library (hợp đồng, biên bản nghiệm thu, biên bản bàn giao, phụ lục) rendered from project/opportunity data. Reuse the existing contract-PDF pipeline as the pattern.

**R7 — Vision goal #6: client portal actions (medium).** Portal (`routes/web.php:995`, magic-link auth) is read-only. Highest-value first actions: client approves/comments on a `DesignItem` awaiting feedback, and confirms receipt of a document. Reuse `portal.auth` guard; every write needs the anti-enumeration lessons from Phase 6.

**R8 — Vision goal #7: knowledge base (medium, greenfield).** No SOP/checklist/lessons-learned model exists (verified: nothing in `app/Models`). Start minimal: `KnowledgeArticle` (tenant-scoped, categorized, markdown) + list/show/edit UI + link from project close-out.

**R9 — Test-suite timing-flake remediation (medium, quality).** ~90+ wall-clock timing assertions across the suite are latent flakes. Inventory them (`grep -rn "assertLessThan.*(micro)?time\|->assertResponseTime" tests/`), convert to functional assertions or quarantine into a non-blocking perf suite.

---

## Self-review notes

- Spec coverage: all 4 verified findings map to Tasks 1-3; Task 4 is the regression gate; everything not code-fixable today is in R1-R9 with its blocking question stated.
- Route names in Task 1's test are sourced from the existing passing `AiLeadSuggestionTest` (`operator.` prefix) with a fallback instruction (`route:list`) if the prefix differs for design-items.
- Type consistency: limiter name `ai-suggest` is identical in RouteServiceProvider, both route strings, and both test assertions; scope name `'tenant'` matches `app/Traits/TenantScope.php:20`.
- No placeholders: every code step contains the full code; the only conditional instructions are verification fallbacks with exact commands.
