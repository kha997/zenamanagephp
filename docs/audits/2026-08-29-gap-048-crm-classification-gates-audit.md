# GAP-048 — CRM Classification UX & Gates: Gate-1 Problem/Evidence Audit

**Round 1 correction notice:** Owner reviewed the initial submission (PR
#293, head `47bd1ba5`) and returned **MORE INFO / TARGETED CORRECTION
REQUESTED** — not a rejection; the core problem was substantively
validated. Two corrections were directed and are applied throughout this
re-presentation: (1) a runtime-evidence narrative contradiction, corrected
in §2 below and in the wording of every subsequent reference to the
GAP-046 regression test; (2) an incomplete Quote-lifecycle audit — the
original §9 inferred a single "formal Quote creation" enforcement point
from `CrmPageController::storeQuote()` alone; this has been replaced with
a complete native+portal+external Quote lifecycle audit (new §9), H8 has
been reframed (§15), and the Gate-2 boundary wording (§19) no longer
pre-selects an enforcement point. The full verbatim Owner directive and
permanent Round-1 history are recorded in
`docs/owner-decisions/GAP-048/01-request.md`. All 13 other Round-0
findings were explicitly reaffirmed by Owner and are unchanged below.

**Status:** Gate 1, evidence-only. This document authorizes no migration,
model, controller, service, route, or UI change. It proves the current-state
problem and blast radius so an Owner can decide whether to authorize Gate 2
design. No solution is selected here.

**Canonical baseline SHA:** `87bb7d36128f878d8b6291705fed2c4262b11819` — the
released GAP-046 (Canonical Service-Line Foundation) squash-merge commit,
verified as both the worktree's starting commit and `origin/main` at the
start of this session (`git fetch origin --prune && git rev-parse origin/main`
returned this exact SHA; no drift).

**Work ID uniqueness:** `GAP-048` was verified unallocated before this audit
began — `grep -ril "GAP-048"` across the repo, `gh search issues`/`gh search
prs`/`gh pr list --search "GAP-048"` against `kha997/zenamanagephp`, and a
listing of `docs/owner-decisions/*` all returned zero prior hits. This is a
new Work ID.

**This audit corresponds to** SSOT slice §14 item 3, "CRM Classification UX
& Gates," `docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md`,
and to the exclusions GAP-046 itself recorded as future work
(`docs/owner-decisions/GAP-046/01-request.md`, Owner's Gate-1 approval
scope clarification).

---

## 1. Design Dependency Preflight

Read and reconciled on the exact canonical baseline (`87bb7d36`), no drift:

1. **`docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md`
   (OWN-2026-009, canonical SSOT).** §2 fixes Service Line as multi-valued
   (`DESIGN`/`CONSTRUCTION`/`INSPECTION`), §2.4 "never default to
   Design/Architecture," §2.5 the four-state provenance model and the exact
   legacy mapping (architecture-family/interior/landscape/structure/mep →
   DESIGN/INFERRED; construction → CONSTRUCTION/INFERRED; inspection →
   NEEDS_REVIEW, explicitly noting "current UI labels it 'Giám sát', which is
   not the same concept as standalone Inspection"; consulting/combined_package
   → NEEDS_REVIEW; null/unrecognized → UNKNOWN), §3.2 the Opportunity funnel
   requirement ("Transition into `scope_defined` ..., formal Quote creation,
   and WON→Project conversion each require ≥1 confirmed Service Line"),
   §10.3/§12 item 1 record `Opportunity.service_category` defaulting to
   `'architecture'` as "a currently active violation of Rule §2.4," with the
   fix explicitly "deferred to the CRM Classification UX & Gates
   implementation slice." §14 item 3 names this exact slice.
2. **`docs/superpowers/specs/2026-08-25-gap-046-service-line-foundation-design.md`
   and `docs/owner-decisions/GAP-046/02-design.md`** — GAP-046's approved
   Gate 2 design. Binding, not reopened here: Option B (two explicit
   non-polymorphic join tables), exactly 3 canonical Service-Line values (no
   UNKNOWN/NEEDS_REVIEW as membership values), the legacy backfill mapping,
   zero Project-side backfill, and the tenant-parent-derivation invariant.
3. **`docs/owner-decisions/GAP-046/03-release.md`** — GAP-046's Gate 3
   release record (squash `87bb7d36`, PR #292). Confirms explicitly, in its
   "Explicit confirmation" section: no CRM classification UX, stage gates,
   Quote Scope Snapshot, or WON→Project propagation was built; `service_category`'s
   default and validation are unmodified; `OpportunityController`,
   `LeadController`, `CrmPageController`, `DesignItemPageController`,
   `BusinessKpiService` were not touched. This audit treats those exclusions
   as evidence of GAP-046's boundary, not as defects in GAP-046.
4. **Source design docs** (`2026-08-12-zena-service-line-taxonomy-design.md`,
   `...control-tower-design.md`, `...contract-finance-one-page-control-design.md`)
   — read as context only; the canonical SSOT controls any conflict
   (SSOT §13 marks them `KEEP_AS_ACTIVE_DESIGN_SOURCE`, non-authoritative on
   their own).
5. **Governance schema** — `docs/owner-governance/packet-schema.yml`,
   `docs/owner-governance/OWNER_DECISION_RULES.md`,
   `docs/owner-governance/templates/gate-1-business-request.md`. All read
   live from the current repo (not from memory) to build this Gate-1
   packet's frontmatter and template shape (§22 below).

**Preflight conclusion:** no conflicting or superseding design was found.
GAP-048 is the SSOT-planned next slice, its scope boundary is externally
fixed by the SSOT (§14 ordering) and by GAP-046's own recorded exclusions,
and no dependency reconciliation issue blocks proceeding to Gate-1 evidence
gathering.

---

## 2. Method note

Evidence below is a combination of direct `Read`/`grep` verification
performed in this session and a delegated read-only research pass (general-
purpose subagent, same worktree, same commit, no file writes — confirmed via
`git status` before and after) whose findings were spot-checked independently
(§3.1, §6 boot excerpt, §11 constant files) and matched exactly. All claims
below are FACT (grep/read evidence, file:line cited) unless explicitly
labeled INFERENCE, ASSUMPTION, or UNKNOWN.

**Runtime reproduction was not performed** in this session: this worktree
has no installed `vendor/` (composer dependencies), and `php artisan test`
fails at `require vendor/autoload.php`. Per this task's own directive §18,
material claims may be backed by "file:line evidence, route/call graph
evidence, OR reproducible runtime evidence" — the file:line evidence below
is exhaustive and internally cross-validated (e.g. the `service_category`
default is independently confirmed at 2 controller call sites, the DB
column default, and the model's `VALID_SERVICE_CATEGORIES` constant all
agreeing). Where a claim rests only on static reading and could benefit
from runtime confirmation, this is marked explicitly. No repository file was
modified to reach this conclusion (`git status` clean throughout, confirmed
below in §24).

**Corrected wording — GAP-046's WON-conversion regression test (Owner
Round 1 correction #1):** a prior version of this document's companion
Gate-1 packet stated the team "reran" GAP-046's existing regression test
in this session, which contradicted the paragraph above. That was
inaccurate and is corrected here explicitly, distinguishing three separate
facts:

- **(A)** `tests/Feature/Crm/OpportunityConversionUnchangedTest.php` was
  read in full and its control flow traced against `OpportunityController::convert()`
  and `OpportunityStageTransitionService::transition()` (§8/§10 below) — a
  static-reading activity, not execution.
- **(B)** GAP-046's own previously-recorded Gate-3 release evidence
  (`docs/owner-decisions/GAP-046/03-release.md`, acceptance-matrix
  criterion K and its live-CI verification sections) independently
  establishes that this exact test passed on the released baseline via
  real GitHub Actions CI runs — that is committed, already-existing
  evidence this audit cites, not evidence this audit generated.
- **(C)** this GAP-048 Gate-1 session itself did **not** rerun
  PHPUnit or perform any runtime reproduction — no `vendor/` is installed
  in this worktree, and no fresh test execution occurred. The claim "WON
  conversion today performs zero Service-Line reads or writes" throughout
  this document rests on (A) and (B) together — static deterministic
  control-flow proof plus a prior, independently-verified, already-recorded
  passing-test fact — never on a fabricated or implied fresh run.

---

## 3. Active Opportunity write-path inventory

### 3.1 Routes (all under `rbac:` middleware — tenant/RBAC detail in §14)

| Route | Method | Controller/action | Permission |
|---|---|---|---|
| `routes/api_zena.php:373` | GET `/opportunities` | `Api\OpportunityController@index` | `rbac:crm.view` |
| `routes/api_zena.php:374` | POST `/opportunities` | `Api\OpportunityController@store` | `rbac:crm.manage` |
| `routes/api_zena.php:375` | GET `/opportunities/{id}` | `Api\OpportunityController@show` | `rbac:crm.view` |
| `routes/api_zena.php:376` | PUT `/opportunities/{id}` | `Api\OpportunityController@update` | `rbac:crm.manage` |
| `routes/api_zena.php:377` | POST `/opportunities/{id}/stage` | `Api\OpportunityController@updateStage` | `rbac:crm.manage` |
| `routes/api_zena.php:378` | POST `/opportunities/{id}/convert` | `Api\OpportunityController@convert` | `rbac:crm.convert` |
| `routes/api_zena.php:381` | POST `/opportunities/{id}/create-contract` | `Api\OpportunityController@createContract` | `rbac:crm.manage` |
| `routes/web.php:819` | GET `/crm/opportunities/{id}` | `Web\CrmPageController@showOpportunity` | `rbac:crm.view` |
| `routes/web.php:821` | POST `/crm/opportunities/{id}/stage` | `Web\CrmPageController@updateStage` | `rbac:crm.manage` |
| `routes/web.php:822` | POST `/crm/opportunities/{id}/convert` | `Web\CrmPageController@convertOpportunity` | `rbac:crm.convert` |
| `routes/web.php:825` | POST `/crm/opportunities/{id}/create-contract` | `Web\CrmPageController@createContract` | `rbac:crm.manage` |
| `routes/web.php:828` | POST `/crm/opportunities/{id}/quotes` | `Web\CrmPageController@storeQuote` | `rbac:crm.manage` (corrected — full Quote lifecycle route table in §9.2/§9.4) |

The web routes are thin wrappers that call the same underlying API
controller server-side (Web `convertOpportunity`/`updateStage`/`createContract`
delegate into the `Api\OpportunityController`/`LeadController` logic). **FACT:
there is no direct Blade form or route for "create/edit Opportunity"
independent of Lead conversion** — the only human-facing entry point that
sets `service_category` is Lead→Opportunity conversion (§3.2). The API
`store`/`update` endpoints exist and are reachable by any authorized API
caller but have no corresponding operator-UI form.

### 3.2 Direct Opportunity create — `Api\OpportunityController::store()`

`app/Http/Controllers/Api/OpportunityController.php:187-232`

- Validation (line 110): `'service_category' => ['nullable', Rule::in(Opportunity::VALID_SERVICE_CATEGORIES)]` — nullable, closed 9-value enum.
- **Default-fallback (line 217):** `'service_category' => (string) $request->input('service_category', 'architecture'),` — an explicit PHP-level application default, not merely a passive DB default.
- Authorization: `$this->authorize('create', Opportunity::class)` (line 200) → `app/Policies/OpportunityPolicy.php:19-22` → `crm.manage`.
- Tenant source: derived from the authenticated tenant context (standard controller pattern; `tenant_id` is not user-suppliable in the request body per the `RESPONSE_FIELDS`/rules allowlist, line 43).
- `opportunity_service_lines` handling: none — zero reference to `OpportunityServiceLine`/`ServiceLine` anywhere in this controller (confirmed by full-file grep, §9).
- Tests: no dedicated Feature test exercises `OpportunityController::store()` with an omitted `service_category` and asserts the resulting persisted value — **NOT FOUND** (§15/H2 evidence gap).

### 3.3 Direct Opportunity update — `Api\OpportunityController::update()`

Lines 234-277. `fill($request->only([...]))` (line 279) includes
`service_category` but **not** `pipeline_stage` — an update call cannot
change stage (stage changes only through `updateStage()`, §5). No PHP
default is applied on update: if `service_category` is absent from the
request body, `only()` omits the key and the existing DB value is left
untouched. Blocked once the Opportunity is terminal (`isTerminal()` guard,
lines 260-264). Authorization: `$this->authorize('update', $opportunity)`
(tenant match + `crm.manage`).

### 3.4 Lead → Opportunity conversion — the primary human-facing write path

Two layers:

- **`Api\LeadController::convert()`**, `app/Http/Controllers/Api/LeadController.php:243-341`.
  Validation (line 279): identical `Rule::in(VALID_SERVICE_CATEGORIES)`.
  **Default-fallback (line 304, inside `DB::transaction`):**
  `'service_category' => (string) $request->input('service_category', 'architecture'),`
  — the same explicit `'architecture'` fallback pattern as `store()`.
  Authorization: `$this->authorize('convert', $lead)` (line 262). Guard:
  lead must be `status === 'new'` (line 266).
- **`Web\CrmPageController::convertLead()`**, `app/Http/Controllers/Web/CrmPageController.php:197-218`.
  Validates `service_category` as `['nullable','string']` (looser than the
  API layer) then forwards to the real `LeadController::convert()` (line
  209), which re-validates against the closed enum — so the effective
  constraint is unchanged. **Line 209:**
  `array_filter($validated, fn ($value) => $value !== null && $value !== '')`
  — an empty-string `service_category` selection is stripped from the
  forwarded payload before it reaches `LeadController::convert()`, so a
  human submitting the Blade form with the blank `""` option selected
  reliably falls through to the `'architecture'` PHP default at
  `LeadController.php:304`. **This is the exact mechanism, traced end to
  end, by which "no selection made" becomes Architecture in production.**

### 3.5 Other write categories checked

- **API/AJAX/JSON:** the routes in §3.1 are already JSON API routes; no
  separate AJAX-only Opportunity-create endpoint was found.
- **Factories/seeders:** `Opportunity` factory/seeders were not found to set
  `service_category` deliberately in a way that represents production
  behavior beyond test fixtures — out of scope for a "live write path" per
  the directive's own framing (test-only classification, §12).
- **Commands/jobs/listeners/events:** the only console command touching
  Opportunity-side Service-Line data is `service-lines:backfill-opportunities`
  (`app/Console/Commands/BackfillOpportunityServiceLines.php`), which never
  writes `service_category` itself — it only reads the existing value to
  decide the canonical backfill mapping, and only ever writes `INFERRED`
  rows to `opportunity_service_lines` (§9).
- **`createContract`:** `OpportunityController::createContract()`
  (lines ~470-576) creates a `Contract` from a WON Opportunity; no
  reference to `service_category`/`ServiceLine`/`opportunity_service_lines`
  found in this method (grep-confirmed, zero hits) — it does not read or
  gate on classification of any kind.

---

## 4. `service_category` DB default — schema evidence

Single migration co-locating `accounts`/`opportunities`/`leads`:
`database/migrations/2026_07_09_100000_create_leads_table.php:38-78`.

```php
42:  $table->string('opportunity_name');
43:  $table->string('service_category')->default('architecture');
44:  // architecture|interior|landscape|structure|mep|construction|inspection|consulting|combined_package
47:  $table->string('pipeline_stage')->default('new_lead');
```

No separate `create_opportunities_table` migration exists; no later
migration alters this default. `Opportunity::VALID_SERVICE_CATEGORIES`
(`app/Models/Opportunity.php:83-86`) matches the comment's 9-value list
exactly.

**On real MySQL/`SHOW CREATE TABLE opportunities`:** not executed this
session (no local MySQL instance available, no vendor/ installed — see §2).
This is recorded as **UNKNOWN (not independently reproduced on live MySQL
in this session)**, not silently assumed identical to the migration source.
Given Laravel's schema builder emits a standards-conformant
`DEFAULT 'architecture'` for a `string()` column on both SQLite and MySQL,
and GAP-046's own Gate-3 evidence (`03-release.md`) independently verified
this same migration file's *sibling* tables round-trip identically on real
MySQL 8.0, the risk of an undocumented driver-level divergence is assessed
low (INFERENCE), but is not itself proof and should be spot-checked on real
MySQL before Gate 2 design finalizes any migration-touching remediation.

---

## 5. Root-cause distinction: DB default vs. application default vs. UI default

Three separate, independently confirmed mechanisms exist and must not be
collapsed into one finding:

1. **DB-level default** (§4): `opportunities.service_category DEFAULT 'architecture'`. This fires only on a raw INSERT that omits the column entirely — e.g. via `DB::table('opportunities')->insert()` bypassing Eloquent's explicit `create()` array, or a raw SQL insert. **No such call site was found** in `app/`/`src/` (confirmed by grep for `DB::table('opportunities')` — the only writes go through the `Opportunity` Eloquent model with explicit `service_category` keys in the `create()` array, per §3.2/§3.4). This default is currently a dormant safety net, not the active mechanism.
2. **Application/controller-level default** (§3.2, §3.4): `$request->input('service_category', 'architecture')` at `OpportunityController.php:217` and `LeadController.php:304`. **This is the active mechanism** — it fires on every create/conversion call where the client omits or blanks the field, independent of the DB default.
3. **UI-level default**: the Blade `<select>` (§7 below) does **not** HTML-preselect Architecture — the blank option is unmarked. But because the web wrapper strips empty-string submissions before forwarding (§3.4), the functional outcome is identical to a UI default even though no `selected` attribute exists in the markup.
4. **Test/factory default**: not separately investigated as a "live write path" (§12) — the Owner directive frames factories as evidence only where they represent production behavior; no evidence surfaced that any factory-level default independently causes the production behavior found in mechanisms 1–3.
5. **Presentation-only label**: `resources/views/crm/opportunity-show.blade.php:54` displays the already-persisted `service_category` read-only; this is a downstream display of mechanism 2's result, not itself a default-creating mechanism.

**Conclusion: H2 (application/controller code separately defaults
architecture) is CONFIRMED as the primary, active mechanism. H1 (DB schema
default) is CONFIRMED to exist but is NOT the active mechanism for any
currently-reachable write path — it is a secondary, currently-dormant
safety net.**

---

## 6. Lead→Opportunity conversion reproduction (static trace)

Traced end to end (no live DB run performed, per §2):

- **Request:** `POST /crm/leads/{id}/convert` (web) with `service_category`
  omitted or submitted as `""` from the Blade form's blank default option.
- **Validation outcome:** passes — both the web layer (`nullable|string`)
  and, after `array_filter` strips the empty value, the API layer's
  `nullable|Rule::in(...)` (an absent key satisfies `nullable`).
- **Persisted `service_category`:** `'architecture'` (via
  `LeadController.php:304`'s fallback).
- **`opportunity_service_lines` rows:** zero — `LeadController::convert()`
  contains no reference to `OpportunityServiceLine`/`ServiceLine` (grep-confirmed).
- **Provenance:** not applicable — no row is created, so no provenance state
  exists for the new Opportunity until/unless the separate backfill command
  is later run against it.

This static trace is corroborated by the independently-read source (§3.4)
at the exact call sites; it is not a runtime-executed reproduction in this
session (recorded honestly per §2 — file:line evidence, not fabricated
runtime output).

---

## 7. Current CRM UX evidence

**There is no dedicated create/edit Opportunity form.** The only UI element
that sets `service_category` is the Lead-conversion form,
`resources/views/crm/leads.blade.php:88-98`:

```blade
90: <select name="service_category" class="operator-select" data-ai-field="service_category">
91:     <option value="">Loại dịch vụ</option>
92:     @foreach (['architecture' => 'Kiến trúc', 'interior' => 'Nội thất',
                   'landscape' => 'Cảnh quan', 'structure' => 'Kết cấu',
                   'mep' => 'Cơ điện (MEP)', 'construction' => 'Thi công',
                   'inspection' => 'Giám sát', 'consulting' => 'Tư vấn',
                   'combined_package' => 'Trọn gói'] as $value => $label)
93:         <option value="{{ $value }}">{{ $label }}</option>
94:     @endforeach
95: </select>
```

Point-by-point against the directive's §7 checklist:

1. **Shown at create time?** Yes, at Lead-conversion time (the only creation
   entry point with a human UI).
2. **Required?** No — `nullable` at both validation layers.
3. **Scalar or multi-select?** Scalar `<select>`, single value.
4. **Legacy values offered:** all 9 (`architecture, interior, landscape,
   structure, mep, construction, inspection, consulting, combined_package`).
5. **Does UI preselect Architecture?** No `selected` attribute in the
   markup — the blank option is first/default in the DOM.
6. **Does omitted input become Architecture anyway?** Yes (§3.4/§5) — via
   the server-side fallback, regardless of the UI not visually preselecting it.
7. **Can the user see canonical GAP-046 Service Lines?** No — grep for
   `ServiceLine`/`serviceLines`/`CONFIRMED`/`INFERRED` across all of
   `resources/views/` returns zero hits related to this feature (the only
   `CONFIRMED` hit anywhere in views is an unrelated `CalendarEvent::STATUS_CONFIRMED`).
8. **Can the user see provenance state?** No — same grep, zero hits.
9. **Can a user confirm an INFERRED classification?** No — no UI exists to
   view or act on any `opportunity_service_lines` row at all.
10. **Can the user explicitly leave an Opportunity unclassified?** Only by
    the DB-permissive sense that the field is nullable-validated — in
    practice, no, because the application default always fills it in on
    conversion (§3.4/§5). There is no UI affordance for "explicitly mark
    unclassified" distinct from "leave blank" (which silently becomes
    Architecture).
11. **"Inspection" labeling:** legacy value `inspection` is labeled "Giám
    sát" (`leads.blade.php:92`). Case-insensitive search across
    `resources/views/` for "giám sát"/"inspection" also surfaces a wholly
    separate, unrelated feature: a QC/NCR field-inspection module
    (`resources/views/inspections/*.blade.php`, routes `operator.inspections.*`)
    that has no connection to `Opportunity::VALID_SERVICE_CATEGORIES` or to
    GAP-046's `ServiceLine::INSPECTION` constant. **This is recorded as
    ambiguity evidence per the directive's §7 instruction, not resolved
    here:** the legacy CRM label "Giám sát"/`inspection` and the canonical
    `ServiceLine::INSPECTION` value are two different concepts that share
    an English word, and the SSOT itself (§2.5) already flags this exact
    ambiguity ("current UI labels it 'Giám sát', which is not the same
    concept as standalone Inspection").

---

## 8. Pipeline stage-transition evidence

14 stages (`app/Models/Opportunity.php:29-42`): `new_lead, qualified,
contacted, brief_discovery, survey_or_inputs_received, scope_defined,
proposal_draft, proposal_sent, negotiation, contracting, won, lost,
nurture, no_bid`. `TERMINAL_STAGES` = `won, lost, no_bid`.

**Centralized transition service:** `app/Services/Crm/OpportunityStageTransitionService.php:20-58`,
`transition()` method, called from both `OpportunityController::updateStage()`
(line 320) and `CrmPageController::updateStage()` (line 517) — no other
call site found. It validates stage membership, blocks re-transition once
terminal, requires `lost_reason` for `lost`, sets `forecast_category`, and
writes an `EventRecord`. **Full-file read: zero reference to `service_line`,
`OpportunityServiceLine`, `ServiceLine`, or `ServiceLineProvenance` anywhere
in this file.**

**Bypass check:** `OpportunityController::update()`'s mass-assignable
`only()` list (line 279) does **not** include `pipeline_stage` — direct
`update()` cannot change stage; only `updateStage()` can. The transition
mechanism is centralized for every controller path inspected.

**Proof that zero canonical Service Lines does not block `scope_defined`
or any downstream stage, including WON:** `OpportunityStageTransitionService::transition()`
performs no Service-Line check of any kind before permitting any stage
value in `VALID_STAGES`, including `scope_defined` and `won`. This is a
direct code-reading proof (the method's full body contains no such
conditional), not an inference from absence of a keyword alone — the
method's control flow was read in full and traced.

**Existing regression proof for the WON step specifically** (also directly
relevant to §9/§10): `tests/Feature/Crm/OpportunityConversionUnchangedTest.php`
contains `test_won_to_project_conversion_creates_zero_service_line_rows`
and the stronger `test_won_to_project_conversion_does_not_propagate_existing_canonical_membership`
(seeds a real `INFERRED`/`DESIGN` row *before* conversion, converts via the
real unmodified `OpportunityController::convert()` endpoint, and asserts
the row survives unchanged and zero `ProjectServiceLine` rows are created).
Both are discriminating tests (would fail if any Service-Line
read/write/gate were added to `convert()`), and both are part of the
currently-green GAP-046 test suite per `docs/owner-decisions/GAP-046/03-release.md`'s
acceptance matrix (criterion K). This is the strongest available evidence —
a passing, currently-committed, purpose-built negative-assertion test —
that WON conversion today performs zero Service-Line reads or writes,
regardless of an Opportunity's canonical membership state.

---

## 9. Complete Quote lifecycle gate evidence

**Round 1 correction:** the original version of this section inferred a
single "formal Quote creation" enforcement point from `storeQuote()`
alone. Owner directed a complete lifecycle audit. Below is every native,
portal, and external-integration Quote state transition found in the
repository, each independently read in full.

### 9.1 Quote state model

`app/Models/Quote.php:41-61`:

```php
41: public const STATUS_DRAFT = 'draft';
42: public const STATUS_SENT = 'sent';
43: public const STATUS_ACCEPTED = 'accepted';
44: public const STATUS_REJECTED = 'rejected';
45: public const STATUS_SUPERSEDED = 'superseded';
...
57: self::STATUS_DRAFT => [self::STATUS_SENT, self::STATUS_SUPERSEDED],
58: self::STATUS_SENT => [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_SUPERSEDED],
59: self::STATUS_REJECTED => [self::STATUS_SUPERSEDED],
60: self::STATUS_ACCEPTED => [],
61: self::STATUS_SUPERSEDED => [],
```

`canTransition()` (`Quote.php:127`) is a pure state-membership check
against this table — no classification input of any kind.

### 9.2 Native lifecycle matrix

| Path | Route | Actor | Authorization | Source state | Destination state | Reads canonical Opportunity Service Lines? | Checks CONFIRMED provenance? | Checks legacy `service_category`? | Reachable with unclassified Opportunity? |
|---|---|---|---|---|---|---|---|---|---|
| **A. DRAFT creation** — `CrmPageController::storeQuote()`, `app/Http/Controllers/Web/CrmPageController.php:645-668` | `POST /crm/opportunities/{id}/quotes` (`routes/web.php:828`) | Operator | `rbac:crm.manage` | (new Quote) | `DRAFT` | No — zero reference to `ServiceLine`/`OpportunityServiceLine`/`service_category` in the full method body | No | No | **Yes** — no gate of any kind on the source Opportunity's classification |
| **B. Revision (new Draft) creation** — `CrmPageController::reviseQuote()`, `app/Http/Controllers/Web/CrmPageController.php:1088-1140+` | `POST /crm/quotes/{id}/revise` (`routes/web.php:835`) | Operator | `rbac:crm.manage` | any existing Quote | new `DRAFT` (copies `opportunity_id`, line items, discount/vat/terms from the original — full method read, lines 1101-1124 shown) | No | No | No | **Yes** — copies the same `opportunity_id` with no re-check of any kind |
| **C. Formal send (DRAFT→SENT)** — `CrmPageController::sendQuote()`, `app/Http/Controllers/Web/CrmPageController.php:979-1028` | `POST /crm/quotes/{id}/send` (`routes/web.php:832`) | Operator | `rbac:crm.manage` | `DRAFT` (`Quote::canTransition()` gate) | `SENT` | No | No | No | **Yes** — the only preconditions checked are `canTransition()` (state-machine membership) and `$hasLines` (at least one `QuoteLineItem` exists, line 996-1003); zero reference to `service_category`/`ServiceLine` anywhere in the method |
| **D. Operator acceptance** — `CrmPageController::acceptQuote()`, `app/Http/Controllers/Web/CrmPageController.php:1030-1057` | `POST /crm/quotes/{id}/accept` (`routes/web.php:833`) | Operator | `rbac:crm.manage` | `SENT` (`canTransition()` gate) | `ACCEPTED` (delegates to `QuoteLifecycleService::accept()`, §9.3) | No | No | No | **Yes** |
| **E. Operator rejection** — `CrmPageController::rejectQuote()`, `app/Http/Controllers/Web/CrmPageController.php:1059-1086` | `POST /crm/quotes/{id}/reject` (`routes/web.php:834`) | Operator | `rbac:crm.manage` | `SENT` (`canTransition()` gate) | `REJECTED` (delegates to `QuoteLifecycleService::reject()`, §9.3) | No | No | No | **Yes** |

### 9.3 Shared lifecycle service — `app/Services/QuoteLifecycleService.php` (full file read)

Both `accept(Quote $quote, array $context)` (lines 16-57) and
`reject(Quote $quote, array $context)` (lines 63-96) — the single shared
implementation called by both the operator paths (§9.2 D/E) and the
client-portal paths (§9.4) — perform, in full: a `canTransition()` state
check, the `Quote` status/`decided_at` update, on acceptance a supersession
sweep of sibling Quotes for the same `opportunity_id` (lines 30-35), and an
`EventRecord` write. **Zero reference to `service_category`, `ServiceLine`,
`OpportunityServiceLine`, or any provenance concept anywhere in this file**
(full-file grep, zero hits) — this is the one place in the entire Quote
lifecycle where operator and portal acceptance/rejection logic converge,
and it is unconditioned on classification of any kind.

### 9.4 Client-portal lifecycle — `app/Http/Controllers/Web/Portal/PortalQuoteController.php` (full relevant methods read)

| Path | Route | Actor | Authorization | Source state | Destination state | Classification checks |
|---|---|---|---|---|---|---|
| **F. Portal acceptance** — `accept()`, lines 52-77 | `POST /portal/{tenantSlug}/quotes/{id}/accept` (`routes/web.php:860`) | Client-portal account (`Auth::guard('client')`) | `portal.auth` middleware (route group, `routes/web.php:851`) + `throttle:portal-actions`; ownership enforced by `findOwnedQuote()` (lines 22-33, join on `opportunities.account_id = $account->id`, and excludes `STATUS_DRAFT` — a portal user can never see/act on a Draft) | `SENT` (checked at line 61, not via `canTransition()` directly but an equivalent explicit status check) | `ACCEPTED` (delegates to the same `QuoteLifecycleService::accept()`, §9.3) | None — same shared service, same zero-classification-check conclusion |
| **G. Portal rejection** — `reject()`, lines 79-107 | `POST /portal/{tenantSlug}/quotes/{id}/reject` | Client-portal account | Same as F | `SENT` | `REJECTED` (delegates to `QuoteLifecycleService::reject()`) | None |

**Authorization note:** the portal path uses a distinct authentication
guard (`client`, not the operator `rbac:crm.*` permission model) and a
distinct authorization mechanism (query-scoped ownership via
`findOwnedQuote()`, not `OpportunityPolicy`/`QuotePolicy`). This is a
structurally different actor/authorization class from every other path
audited in this document (§13) — flagged here as evidence, not as a
defect; it is consistent with a legitimate client-facing surface.

### 9.5 External Quote integration (zena-boq-core) — reconciliation required per Owner directive §4

Three methods in `app/Http/Controllers/Api/OpportunityController.php` form
a separate, parallel "external Quote" concept that does not use the native
`Quote` model at all:

- **`linkExternalBoqProject()`**, lines 593-631. Route not in the table
  above (API-only, `rbac:crm.manage`-gated via `$this->authorize('update', $opportunity)`
  at line 614, plus a separate `ZenaBoqIntegrationService::isTenantAuthorized()`
  tenant-integration gate at line 604). Sets `opportunity->external_boq_project_code`.
  **Zero reference to `service_category`/`ServiceLine`/`OpportunityServiceLine`**
  in the full method body.
- **`syncExternalQuote()`**, lines 633-691. Same authorization pattern
  (`$this->authorize('update', $opportunity)` line 654 + tenant-integration
  gate line 644). Pulls the latest external quote via
  `ZenaBoqIntegrationService::fetchLatestQuote()` (line 662) and persists,
  verbatim from the external system, onto the `Opportunity` row itself
  (not a `Quote` row): `external_quote_id`, and
  `external_quote_snapshot = ['revision', 'subtotal', 'vat_amount', 'total',
  'status', 'calibration', 'issued_at']` (lines 665-676) — `status` here is
  the **external** system's own quote status string (e.g. `'ACCEPTED'`),
  not derived from or checked against the native `Quote::STATUS_*` enum or
  any canonical/legacy classification. **Zero reference to
  `service_category`/`ServiceLine`/`OpportunityServiceLine`** in the full
  method body.
- **`createContract()`**, lines 422-579 (already partially cited in §3.5;
  now read in full for this correction). Lines 462-476 are the exact
  commercial-prerequisite check:

  ```php
  462: $snapshot = $opportunity->external_quote_snapshot ?? [];
  463: $nativeQuote = Quote::query()
  464:     ->where('opportunity_id', (string) $opportunity->id)
  465:     ->where('tenant_id', $tenantId)
  466:     ->where('status', Quote::STATUS_ACCEPTED)
  467:     ->first();
  469: $hasExternalAccepted = ($snapshot['status'] ?? null) === 'ACCEPTED';
  470: $hasNativeAccepted = $nativeQuote instanceof Quote;
  472: if (! $hasNativeAccepted && ! $hasExternalAccepted) {
  473:     return $this->validationError([
  474:         'quote' => ['Either a native accepted quote or an accepted external quote is required to generate a contract.'],
  475:     ]);
  476: }
  ```

  `createContract()` treats a native `Quote` at `STATUS_ACCEPTED` and an
  external `external_quote_snapshot['status'] === 'ACCEPTED'` as
  **equally valid, either being sufficient** to pass the commercial-
  prerequisite gate and proceed to create a `Project`/`Contract`. **Full-method
  grep confirms zero reference to `service_category`, `ServiceLine`, or
  `OpportunityServiceLine` anywhere in `createContract()`** — the same
  conclusion as every other path in this section.

**Determination (Owner directive §4, answered directly):**

- **Does external Quote linking/syncing have any canonical Service-Line
  gate?** No — confirmed by full-method reading of both
  `linkExternalBoqProject()` and `syncExternalQuote()`, zero references
  found.
- **Can an externally ACCEPTED Quote exist while Opportunity canonical
  Service-Line membership is zero/unconfirmed?** **Yes** — `syncExternalQuote()`
  persists `external_quote_snapshot['status']` directly from the external
  system's own response with no cross-check against
  `opportunity_service_lines` (or against `service_category`) at all;
  nothing in the sync path reads or requires canonical membership.
- **Does this create a potential bypass Gate 2 must account for?** **Yes,
  structurally, stated as evidence not as a design decision:** because
  `createContract()` accepts a native-OR-external accepted Quote as
  equally sufficient (§9.5 above), any future gate placed only on the
  *native* Quote lifecycle (§9.2/§9.3/§9.4) would leave the external path
  fully unconstrained — an Opportunity could reach `createContract()`
  purely through `syncExternalQuote()` pulling an `'ACCEPTED'` snapshot
  from zena-boq-core, with zero native `Quote` rows and zero canonical
  Service-Line rows involved anywhere. This is recorded as a **Gate-2
  design dependency to be accounted for**, not solved here. Per the Owner
  directive, this does **not** pull zena-boq-core itself into GAP-048's
  implementation scope merely because the integration exists — if Gate 2
  design work determines an actual change to the external system is
  necessary, that must be flagged to Owner as its own dependency rather
  than silently absorbed into this Work ID's scope.

### 9.6 Summary conclusion for §9

Across all three lifecycle families found — native operator (DRAFT
creation, revision, send, accept, reject), client-portal (accept, reject),
and external zena-boq-core synchronization feeding `createContract()`'s
commercial-prerequisite check — **zero transition point anywhere checks
`service_category`, canonical Service-Line membership, or CONFIRMED
provenance.** No single method is "the" formal-Quote gate; the lifecycle
has at least three architecturally distinct transition families (native
DRAFT→SENT→ACCEPTED/REJECTED via a shared `QuoteLifecycleService`, a
client-portal variant of the same shared service, and a wholly separate
external-system-driven `Opportunity`-level snapshot consumed directly by
`createContract()`), none of which is currently a candidate "the" gate
belongs on by default — that placement is explicitly a Gate-2 decision
(§19).

---

## 10. WON→Project conversion gate evidence

Already established in §8: `OpportunityController::convert()` (the only
WON→Project conversion path; no other convert/`Project::create` call site
tied to Opportunity conversion was found) performs zero Service-Line reads
or writes, proven by the currently-passing `OpportunityConversionUnchangedTest.php`
regression tests (criterion K of GAP-046's own accepted acceptance matrix).

Applying the directive's five sub-scenarios:

- **A. Zero canonical rows:** conversion proceeds (no gate exists to check
  row count).
- **B. Only INFERRED membership:** conversion proceeds identically — the
  stronger existing test (`test_won_to_project_conversion_does_not_propagate_existing_canonical_membership`)
  seeds exactly this state and proves conversion is unaffected.
- **C. A CONFIRMED membership:** **UNKNOWN/not independently reproduced** —
  no test seeds a CONFIRMED row before conversion (consistent with §11's
  finding that no write path can currently create a CONFIRMED row at all
  outside a hypothetical direct DB write). By the same code-reading proof
  as A/B (the transition/convert methods contain no provenance-conditional
  branch of any kind), the outcome would be identical — conversion
  proceeds — but this specific sub-case has no dedicated passing test as
  direct evidence, so it is recorded as INFERENCE from the unconditional
  code path, not as separately reproduced FACT.
- **D. Legacy `architecture` scalar, zero canonical rows:** conversion
  proceeds — this is in fact the default/common case today (§5), and no
  code path anywhere in `convert()` reads `service_category` as a gate
  (confirmed by the same full-method grep as §8; `service_category` is
  copied onto no `Project` field because `Project` has no classification
  column at all, per the SSOT's own implementation-vs-design matrix, §15).
- **E. Ambiguous legacy value (`inspection`/`consulting`/`combined_package`),
  zero canonical rows:** conversion proceeds identically, by the same
  unconditional-code-path proof.

**No propagation exists in either direction** — confirmed both by GAP-046's
own release record (`03-release.md`, "Explicit confirmation" section: "WON→Project
propagation remains absent (proven, strengthened, by
test_won_to_project_conversion_does_not_propagate_existing_canonical_membership)")
and independently re-confirmed here by direct reading of the same test file
and the `convert()`/`OpportunityStageTransitionService` source.

---

## 11. Canonical Service-Line / CONFIRMED-provenance write-path evidence

`app/Support/ServiceLine.php` — `VALUES = [DESIGN, CONSTRUCTION, INSPECTION]`.
`app/Support/ServiceLineProvenance.php` — `VALUES = [CONFIRMED, INFERRED,
NEEDS_REVIEW, UNKNOWN]`, with its own docblock stating the four-state enum
is declared "as general future-proofing for a later, separately-scoped
manual classification/reclassification workflow" and that GAP-046 "never
writes CONFIRMED."

**Production write paths to `OpportunityServiceLine`:** grep for
`OpportunityServiceLine::` across `app/`/`src/` returns exactly one call
site: `app/Console/Commands/BackfillOpportunityServiceLines.php:96`
(`OpportunityServiceLine::query()->firstOrCreate(...)`, always with
`'provenance' => ServiceLineProvenance::INFERRED`, line 100). Grep for any
reference to `OpportunityServiceLine`/`ProjectServiceLine` under
`app/Http/Controllers/**` returns **zero hits**.

**CONFIRMED provenance:** grep for `CONFIRMED` across `app/`/`src/` (excluding
the constant's own declaration and docblock comments) returns zero
construction sites. **FACT: no route, controller, command, or job in this
repository can currently create a `provenance = CONFIRMED` row.**

**Who can currently write anything to these tables:** only whoever can
invoke `php artisan service-lines:backfill-opportunities` (a CLI-only,
non-HTTP surface, gated by shell/deploy access, not RBAC/`crm.*`
permissions) — this is an operational/CLI actor, not an end user through
any UI or API.

**Whether provenance can be spoofed from request input:** not applicable
today — there is no request-driven write path to spoof, because none
exists.

**Tenant-parent integrity (`EnforcesServiceLineIntegrity` trait,
`app/Models/Concerns/EnforcesServiceLineIntegrity.php`, full file read):**
enforces canonical `service_line`/`provenance` membership, resolvable
parent, and tenant congruence (both explicit child `tenant_id` mismatch
and acting/current-tenant-context mismatch, mirroring `App\Traits\TenantScope`'s
precedence without modifying it) on every `saving` event — i.e. on both
create and update, for both models. This is model-level enforcement, not a
controller/route gate; it currently only ever guards the backfill
command's writes (§8 of `03-release.md`'s acceptance matrix — item I —
already independently proves this via passing tests). Since no controller
writes through the model, "bypass" is not currently a live attack surface
— this is a forward-looking safety net (INFERENCE, consistent with the
trait's own docblock framing it as guarding "a later, separately-scoped"
workflow).

**Can existing rows change from INFERRED→CONFIRMED, or be removed?** No UI
or API exists to update or delete an `OpportunityServiceLine`/`ProjectServiceLine`
row at all — grep for any `Http/Controllers` reference to either model
returns zero hits, so update/delete is currently only theoretically
possible via direct Eloquent/tinker/CLI access, not through any supported
production surface.

**Conclusion:** there is currently **NO supported user-facing confirmation
path**. This is stated as the directive requires — explicitly, not
softened.

---

## 12. `service_category` active-consumer matrix

| File:line | Class | Note |
|---|---|---|
| `app/Http/Controllers/Web/DesignItemPageController.php:94-99` | **A. Authoritative business logic** | Pulls the legacy scalar via `->value('service_category')` and feeds it into `AiAssistService::suggestDesignItemDescription($itemType, $serviceCategory)` for AI-generated design-item content on the converted Project — a real, load-bearing runtime consumer. |
| `app/Http/Controllers/Api/OpportunityController.php:217` | **A** | The `'architecture'` default-fallback itself (§3.2). |
| `app/Http/Controllers/Api/LeadController.php:304` | **A** | The `'architecture'` default-fallback itself (§3.4). |
| `app/Services/BusinessKpiService.php:140-160` (`serviceCategoryPerformance()`) | **C. Filtering/reporting** | Groups WON/LOST terminal Opportunities by `service_category` for win-rate/avg-fee KPIs — single-value grouping, cannot represent multi-Service-Line Opportunities even if they existed. |
| `app/Http/Controllers/Api/OpportunityController.php:110,279` | **B. Validation/gating** | `Rule::in(VALID_SERVICE_CATEGORIES)` on create/update — gates the *value*, not classification *presence/confidence*. |
| `app/Http/Controllers/Api/LeadController.php:279` | **B** | Same, on Lead-conversion. |
| `resources/views/crm/leads.blade.php:90-95` | **D. Display/data-entry UI** | The only UI element setting this field (§7). |
| `resources/views/crm/opportunity-show.blade.php:54` | **D** | Read-only display on the Opportunity detail page. |
| `app/Http/Controllers/Api/OpportunityController.php:233` (`serialize()`) | **D** | API response field. |
| `app/Http/Controllers/Web/CrmPageController.php:304` | **D** | Included in `buildOpportunitySummaryContext()`, fed to an AI opportunity-summary feature — presentation/context input, not a gate. |
| `app/Services/AiAssistService.php` (multiple lines) | **D/compat** | `service_category` is both an input shape and part of the AI tool-use structured output for Lead-conversion suggestions — feeds back into the same form field, not a gate. |
| `database/migrations/2026_07_09_100000_create_leads_table.php:43-44` | **G. Compatibility-only (schema)** | Column definition. |
| `app/Models/Opportunity.php:96` | **G** | `$fillable` entry. |

**Impact if the scalar becomes nullable/unclassified:** `BusinessKpiService::serviceCategoryPerformance()`
and `DesignItemPageController`'s AI-suggestion call would both need an
explicit "unclassified" branch — neither currently guards against a
null/empty value (INFERENCE — not independently reproduced at runtime this
session, since it would require modifying data, which is out of Gate-1
scope). **Impact if canonical membership becomes authoritative later:**
both of these single-value consumers would need to decide how to represent
a Project/Opportunity carrying more than one canonical Service Line — this
is exactly the "Contract consumer audit" style question the SSOT (§3.4,
§12 item 4) already flags as a future Gate-2-time decision for a different
consumer (Contract); the same class of question applies here for
`BusinessKpiService`/`DesignItemPageController` and is recorded as a
Gate-2 boundary candidate (§20), not decided now.

No test-only or dead references to `service_category` were found in
`app/`/`database/migrations`/`resources/views` (every hit above is a
production reference).

---

## 13. Tenant + RBAC findings

- **Permissions in play:** `crm.view` (read), `crm.manage` (create/update/
  stage/create-contract), `crm.convert` (Lead/Opportunity conversion) — 3
  distinct permissions, applied via `rbac:` route middleware
  (`routes/api_zena.php:373-381`, `routes/web.php:816-828`) and re-enforced
  at the policy layer (`app/Policies/OpportunityPolicy.php`: `viewAny`→
  `crm.view`; `view`/`update`→tenant match + `crm.manage`; `create`→
  `crm.manage`; `convert`→tenant match + `crm.convert`).
- **Tenant scope:** standard tenant-derivation pattern for
  Opportunity/Lead controllers (not user-suppliable independently of the
  authenticated context); `OpportunityPolicy::update`/`convert` explicitly
  check tenant match before `crm.manage`/`crm.convert`.
- **Classification-changing paths specifically:** the only production
  classification-changing paths found are `store`/`update`/`convert` — all
  already gated by the RBAC/policy pairs above. The only path that writes
  canonical `OpportunityServiceLine` rows (the backfill command) is a CLI
  surface with no RBAC/HTTP gate at all — this is consistent with it being
  an operational, not end-user, surface, and was not found to be reachable
  through any web/API route.
- **Bypass check for Quote/WON-gate endpoints:** since no Service-Line gate
  currently exists on Quote creation or WON conversion (§9/§10), there is
  nothing to bypass yet — this finding is prospective (a future gate
  implementation would need its own RBAC audit at Gate 2 time), not a
  current defect.
- **No separate tenant-isolation or authorization vulnerability was
  discovered** during this audit beyond what is already covered by the
  existing `rbac:`/policy pattern common to the rest of the CRM module.
  Per the directive's §13 instruction, if such a finding had surfaced it
  would have been flagged separately and NOT folded into GAP-048 scope —
  none was found, so no such flag is raised.

---

## 14. Test-coverage audit

| Test file | Covers | Discriminating for GAP-048's future gates? |
|---|---|---|
| `tests/Feature/Crm/OpportunityConversionUnchangedTest.php` | WON→convert non-propagation | **Yes, strongly** — would fail if any Service-Line read/write/gate were added to `convert()` |
| `tests/Feature/Console/BackfillOpportunityServiceLinesTest.php` | Backfill mapping/idempotency | Yes for the backfill command; not applicable to controller/UI/gate paths |
| `tests/Unit/Support/ServiceLineTest.php` | `ServiceLine`/`ServiceLineProvenance` value objects | Narrow — pure constant-set test |
| `tests/Feature/Models/ServiceLineFoundationTest.php` | `EnforcesServiceLineIntegrity` trait (tenant derivation, cross-tenant rejection, enum validation, create+update) | Yes, strongly, for the trait itself |
| `tests/Unit/Services/Crm/OpportunityStageTransitionServiceTest.php` | Stage-transition service (valid/invalid stage, terminal guard, `lost_reason` requirement) | Does **not** assert anything about `service_category`/Service-Line absence — would not catch a future Service-Line gate being added unless a new test is written |
| `tests/Feature/Zena/CrmLeadEditTest.php` | Lead edit form fields | **Non-discriminating for classification** — no `service_category` assertions found |

**Explicit coverage gaps (NOT FOUND):**

1. **No test asserts the `'architecture'` default-fallback behavior itself**
   (`OpportunityController.php:217`, `LeadController.php:304`) — no test
   POSTs a create/convert request with `service_category` omitted and
   asserts the persisted value equals `'architecture'`. A regression here
   (e.g. an accidental removal of the fallback, or the opposite — an
   accidental strengthening of it) would go undetected today.
2. **No test exists for any Quote lifecycle transition (creation,
   revision, send, accept/reject — native or portal — or the external
   zena-boq-core sync/`createContract()` acceptance check) proving any of
   them is unconditioned on classification** — unlike the explicit,
   purpose-built `OpportunityConversionUnchangedTest` for `convert()`,
   there is no equivalent negative-assertion guard anywhere in the Quote
   lifecycle (§9).
3. **No test exercises cross-tenant isolation specifically on the
   Opportunity create/update/convert routes** beyond the structural
   `OpportunityPolicy` tenant-match check and `OpportunityConversionUnchangedTest`'s
   single-tenant setup.
4. **No test seeds a CONFIRMED-provenance row before WON conversion**
   (consistent with §11 — no path can create one today), leaving
   scenario C of §10 as inference rather than independently reproduced fact.

---

## 15. Root-cause matrix (H1–H11)

| # | Hypothesis | Verdict | Evidence |
|---|---|---|---|
| H1 | DB schema default silently creates architecture | **CONFIRMED to exist, but NOT the active mechanism** | `database/migrations/2026_07_09_100000_create_leads_table.php:43`; §4/§5 — no live INSERT path bypasses Eloquent's explicit `create()` arrays, so this default is currently dormant, not the operative cause |
| H2 | Application/controller code separately defaults architecture | **CONFIRMED, primary active mechanism** | `OpportunityController.php:217`, `LeadController.php:304` — explicit `$request->input('service_category', 'architecture')` at both create-Opportunity call sites |
| H3 | Lead conversion separately defaults architecture | **CONFIRMED** | Same mechanism as H2, specifically at `LeadController.php:304`, reached via the empty-string-stripping forward from `CrmPageController::convertLead()` (§3.4) |
| H4 | CRM UI preselects or requires a legacy scalar classification | **PARTIALLY CONFIRMED** | Not `required`-validated and no `selected` HTML attribute (NOT preselected in markup); but functionally equivalent because blank submissions are silently defaulted server-side (§3.4/§7) — the UI does not visually mislead the user into thinking Architecture is chosen, but the outcome is the same as if it did |
| H5 | Canonical Opportunity Service-Line rows are not used by CRM UX | **CONFIRMED** | Zero `ServiceLine`/provenance references anywhere in `resources/views/` (§7) |
| H6 | No supported user confirmation path exists for CONFIRMED provenance | **CONFIRMED** | §11 — zero construction sites for `provenance = CONFIRMED` anywhere in `app/`/`src/`; only CLI backfill (INFERRED-only) writes the tables at all |
| H7 | Pipeline transitions do not enforce confirmed Service Lines | **CONFIRMED** | `OpportunityStageTransitionService::transition()` full-file read, zero Service-Line references (§8) |
| H8 | Formal Quote creation does not enforce confirmed Service Lines | **CONFIRMED, reframed across the complete lifecycle (Owner Round 1 correction #2)** | Not evidenced by `storeQuote()` alone. Confirmed independently across every native, portal, and external transition point found: (a) DRAFT creation — `storeQuote()`; (b) DRAFT revision — `reviseQuote()`; (c) DRAFT→SENT formalization — `sendQuote()`; (d) operator ACCEPTED/REJECTED — `acceptQuote()`/`rejectQuote()` via the shared `QuoteLifecycleService::accept()`/`reject()`; (e) client-portal ACCEPTED/REJECTED — `PortalQuoteController::accept()`/`reject()`, delegating to the same shared service; (f) external zena-boq-core synchronization feeding `createContract()`'s native-OR-external-accepted commercial-prerequisite check — `linkExternalBoqProject()`/`syncExternalQuote()`/`createContract()`. Full-method reads of all of the above (§9) found zero reference to `service_category`, `ServiceLine`, `OpportunityServiceLine`, or provenance at any of these 6+ transition points. |
| H9 | WON→Project conversion does not enforce confirmed Service Lines | **CONFIRMED** | `OpportunityController::convert()` / `OpportunityConversionUnchangedTest.php` (§8/§10), including the discriminating pre-seeded-INFERRED-row test |
| H10 | Legacy `service_category` remains authoritative for downstream consumers | **CONFIRMED** | `BusinessKpiService::serviceCategoryPerformance()`, `DesignItemPageController` (§12) — both are real, single-value, currently-authoritative consumers with no canonical-membership awareness |
| H11 | Legacy "inspection"/"Giám sát" semantics are ambiguous relative to standalone INSPECTION | **CONFIRMED** | `leads.blade.php:92` labels legacy `inspection` as "Giám sát"; a wholly separate QC/NCR "inspections" module exists under the same English word with no code-level relationship to either the legacy enum or `ServiceLine::INSPECTION` (§7); the SSOT itself (§2.5, §2.6) already documents this exact risk normatively |

---

## 16. Confirmed problem statement

Today, an Opportunity's classification is a single legacy scalar
(`service_category`) that is explicitly, silently defaulted to
`'architecture'` in application code at both places an Opportunity can be
created (`OpportunityController::store()`, `LeadController::convert()`),
with no user-visible distinction between "the salesperson chose
Architecture" and "no one chose anything." That scalar — never a canonical,
multi-valued, provenance-tracked Service Line — is read by two live
consumers (a CRM performance-KPI report and an AI design-item-suggestion
feature) as if it were trustworthy classification. GAP-046's canonical
`opportunity_service_lines`/`project_service_lines` foundation exists at
the schema/model layer only: it has zero write paths reachable from any
controller or UI, zero read paths from any UI, and no CRM workflow —
pipeline-stage transition, formal Quote creation, or WON→Project conversion
— currently checks, requires, or even reads canonical Service-Line
membership or provenance in any way, confirmed both by direct source
reading and by GAP-046's own currently-passing regression tests. There is
no supported path today for any user to explicitly confirm a classification
into the canonical `CONFIRMED` provenance state at all.

## 17. Blast radius

- **Direct:** every Opportunity created through the only human-facing entry
  point (Lead conversion) that omits an explicit classification choice
  silently becomes `architecture` — the visible symptom the SSOT already
  flagged as an active §2.4 violation.
- **Downstream, currently live:** `BusinessKpiService`'s service-category
  performance KPIs and `DesignItemPageController`'s AI design-item
  suggestions both silently skew toward Architecture for any Opportunity
  whose real service intent was never explicitly captured.
- **Downstream, currently absent but SSOT-planned:** every future slice
  that depends on trustworthy classification (Opportunity→Project
  Propagation, Quote Scope Snapshot, Portfolio Membership, Project OPPM,
  Operations Control Tower — SSOT §14 items 4–10) inherits this problem
  transitively unless GAP-048 resolves it first, consistent with the SSOT's
  own ordering.
- **Gate-2 design-dependency risk (Owner Round 1 addition, §9.5):** the
  zena-boq-core external Quote integration means any future Quote gate
  confined to the native `Quote` lifecycle alone would leave
  `createContract()`'s external-accepted-snapshot path fully unconstrained
  — this is not a defect today (no gate exists on either path yet), but it
  is a structural fact Gate 2 design must explicitly account for rather
  than discover after choosing a native-only gate placement.
- **Not currently a security/tenant-isolation blast radius** — §13 found
  no separate vulnerability; the existing RBAC/tenant pattern already
  governs every classification-changing path found. The client-portal
  Quote-acceptance path (§9.4) uses a structurally different
  authentication/authorization mechanism (`client` guard + ownership
  query, not `rbac:crm.*`) than every other path in this audit — noted as
  a structural difference, not a vulnerability finding.

## 18. Explicit unknowns

- **Real production data distribution** of `service_category` values (how
  many Opportunities are truly Architecture vs. defaulted-Architecture) —
  **UNKNOWN**, no production database access available or authorized this
  session; not inferred from seed/test data per the directive's §16
  instruction.
- **Real MySQL `SHOW CREATE TABLE opportunities` output** — **UNKNOWN**,
  not independently executed this session (§4); assessed low-risk by
  analogy to GAP-046's own MySQL-parity evidence on sibling tables, but not
  itself proof.
- **Scenario C of §10** (a CONFIRMED-provenance row present before WON
  conversion) — **INFERRED, not independently reproduced**, since no
  write path can currently create such a row and no test seeds one; the
  unconditional-code-path proof used for scenarios A/B/D/E is the same
  proof applied here, but without a dedicated passing test as direct
  confirmation.
- **`LeadPolicy`'s exact permission-check body** — confirmed to exist and
  to gate `convert()` (`LeadController.php:262`), but its internal
  permission-name detail was not fully re-read line-by-line in this
  session (the pattern matches `OpportunityPolicy`'s `crm.manage`/`crm.convert`
  convention by strong analogy, not independently dumped).
- **Whether any factory/seeder sets `service_category` in a way that could
  represent an undiscovered alternate production write path** — treated as
  out of "live write path" scope per the directive's own framing (§12),
  not exhaustively enumerated file-by-file.

---

## 19. Proposed Gate-2 boundary candidates (non-binding, for Owner review)

Per the directive §20, assessed against actual repo dependencies found in
this audit:

**IN-SCOPE candidates, each verified as dependent only on files/paths this
audit already inventoried (no undiscovered dependency found that would
require silently expanding scope):**
- Truthful Opportunity classification UX (the only current UI is
  `leads.blade.php`'s Lead-conversion `<select>`, §7 — a Gate-2 design
  would extend/replace exactly this entry point plus the currently-absent
  Opportunity detail-page edit affordance).
- 0..N canonical Opportunity Service Lines exposed in that UX.
- Explicit user confirmation → CONFIRMED (currently zero write paths exist
  for this at all, §11 — a Gate-2 design has a clean slate here, not a
  migration of existing behavior).
- Visibility of provenance/trust state (currently zero UI surface, §7).
- Removing the silent Architecture default at its two confirmed call sites
  (`OpportunityController.php:217`, `LeadController.php:304`) while
  preserving genuinely-unclassified Opportunities.
- **Pipeline gate:** verified in this audit to have a single, centralized
  enforcement point today (`OpportunityStageTransitionService::transition()`,
  §8) — a narrow surface, favorable evidence for scope feasibility.
- **WON-conversion gate:** verified to have a single enforcement point
  today (`OpportunityController::convert()`, §10).
- **Formal-Quote gate:** **correction (Owner Round 1) — this is NOT a
  single centralized enforcement point.** §9 found at least three
  architecturally distinct transition families: native operator lifecycle
  (DRAFT creation/revision/send/accept/reject, converging on the shared
  `QuoteLifecycleService`), a client-portal variant of the same shared
  service under a different auth guard, and a wholly separate
  zena-boq-core external-sync path feeding `createContract()`'s
  native-OR-external-accepted check. **Gate 2 must decide the canonical
  Quote-classification enforcement point** after considering this actual
  lifecycle — for example (not a recommendation, illustrative only, listed
  to show the decision space, not to pre-select it): gating at native
  DRAFT creation, at DRAFT→SENT formalization, at every ACCEPTED
  transition (operator and portal), at `createContract()`'s
  commercial-prerequisite check (which would need to address the external
  path too, §9.5), or some combination. This audit intentionally does not
  select among these.
- Narrow legacy `service_category` compatibility for the two confirmed
  active consumers (`BusinessKpiService::serviceCategoryPerformance()`,
  `DesignItemPageController`) — their exact compatibility treatment is a
  Gate-2 decision, not decided here.

**OUT-OF-SCOPE candidates, verified as NOT structurally required for
GAP-048 to be coherent** (no evidence found in this audit that any of these
must be touched to implement the in-scope list above):
- Opportunity→Project Service-Line propagation (SSOT §14 item 4, its own
  slice; §10 confirms zero propagation exists today and GAP-048's gates
  can be implemented without adding propagation).
- Project classification UX / historical Project backfill (Project has no
  classification column at all today, per the SSOT's own implementation
  matrix — out of this slice by construction, not by choice).
- Quote Scope Snapshot persistence (§9 confirms Quote creation has no
  classification dependency to snapshot yet — a gate on *creation* does
  not require snapshot *persistence*).
- Contract multi-Service-Line implementation, Portfolio, Project Health,
  Commercial/Finance Control, Resource Control, OPPM, Control Tower,
  Project Treasury — none of these were found to be a structural
  dependency of any in-scope candidate above.
- Legacy taxonomy final retirement (`service_category` remains read by
  live consumers per §12; retiring it is explicitly SSOT §14 item 12, a
  separate, later slice).

**No ostensibly out-of-scope component was found to be genuinely required**
for GAP-048's in-scope list to be coherent — this audit did not discover a
hidden dependency that would force silent scope expansion.

## 20. No-fix confirmation

**Zero implementation, migration, controller, route, UI, or test changes
were made to reach any finding in this document.** Every finding above is
either a direct file:line citation of already-committed code at the
canonical baseline `87bb7d36`, or an explicit currently-passing test's
observed pass/fail semantics as already recorded in
`docs/owner-decisions/GAP-046/03-release.md`. This audit branch,
`docs/GAP-048-gate1-crm-classification-audit`, contains exactly two new
files (this document and `docs/owner-decisions/GAP-048/01-request.md`) and
no other diff against `87bb7d36`.
