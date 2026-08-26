# GAP-046 — Canonical Service-Line Foundation, Phase A (Semantics Audit) — Evidence

**Status:** Gate 1 evidence, docs-only. Authorizes no migration, model, controller, service, route, seeder, or UI change. Verified against `main` at `c3a1226059bcf5a573aad1eebf8f1333331d9ad2` (2026-08-25).

**Label legend:** VERIFIED LIVE (repo content on `main`, read directly by this agent) / VERIFIED STATIC (derived by inspection, not executed) / LOCAL OBSERVATION / HISTORICAL / ASSUMPTION-UNVERIFIED.

**Authority:** Where this evidence conflicts with `docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md` (OWN-2026-009, the merged canonical SSOT), the SSOT controls. PR #257 is non-normative source evidence only. This document does not restate the SSOT's normative rules (§1–§9 there); it verifies current `main` against them and adds file:line evidence.

---

## A. Scope reconciliation with the SSOT roadmap (§14 item 1)

The SSOT (`2026-08-15-zena-one-page-management-canonical-semantics.md`, Gate 2 approved under OWN-2026-009) already names this exact slice: "**Canonical Service-Line Foundation** — one slice, one Work ID, two internal phases... **Phase A, Semantics Audit** (investigation only)... **Phase B, Foundation Build**... Phase B does not start until Phase A's findings are reviewed." (§14.1). This document is that Phase A. VERIFIED LIVE.

Issue #248's boundary (SSOT §5) is Project OPPM only, and OPPM is item 9 in the §14 sequence — several slices after this one. This audit does not touch OPPM implementation; it only confirms (§F below) that OPPM/Portfolio/Control Tower have zero code presence today, consistent with SSOT §15's evidence-appendix claim that they are 100% design-only. VERIFIED LIVE.

## B. Product hierarchy — current state vs. SSOT invariants

- **One CRM pipeline, one canonical Project** — VERIFIED LIVE. `app/Models/Opportunity.php` has a single 14-stage `pipeline_stage` (`STAGE_NEW_LEAD` … `STAGE_NO_BID`, lines 28–41); no per-lens duplicate pipeline exists. `app/Models/Project.php` is the only project table (`projects`); no per-lens project table exists.
- **No canonical `project_type` field** — VERIFIED LIVE. `app/Models/Project.php:44-59` fillable list: `tenant_id, code, name, description, pm_id, created_by, manager_id, start_date, end_date, status, priority, progress, actual_cost, budget_total, budget_actual, budget, spent_amount`. No classification/type/service-line column exists on `projects` in any migration (grep across `database/migrations/` for `project` + `service`/`classif`/`type` returns nothing relevant beyond `status`/`priority`).
- **Service Line is not yet multi-valued anywhere** — VERIFIED LIVE. The only classification field in the entire funnel is `Opportunity.service_category`, a single scalar `string` column (`database/migrations/2026_07_09_100000_create_leads_table.php:43`), not a set/array/pivot. This is the gap Phase B exists to close.

## C. Field-by-field inventory, Lead → Opportunity → Quote → Contract → Project

### C.1 Lead
- `leads` table (`2026_07_09_100000_create_leads_table.php:80-101`): `contact_hint, project_description, source, status, converted_opportunity_id, notes, captured_by`. **No classification field of any kind.** VERIFIED LIVE.
- Matches SSOT §3.1 ("Lead: classification is optional... may be zero, one, multiple, or explicitly unclassified") only in the degenerate sense that it's *always* unclassified today — there is no field to hold even an optional "Service Intent." SSOT §15 independently confirms this as `DESIGN_ONLY_NOT_IMPLEMENTED`. VERIFIED LIVE, consistent with SSOT.

### C.2 Opportunity
- `opportunities` table (`2026_07_09_100000_create_leads_table.php:38-77`): `service_category` — `string`, **`default('architecture')`** (line 43), plus a same-line code comment enumerating the 9 legacy values. `service_scope_summary` (`text`, nullable) is a free-text companion field, not a structured scope.
- `app/Models/Opportunity.php:79-82`: `VALID_SERVICE_CATEGORIES = ['architecture','interior','landscape','structure','mep','construction','inspection','consulting','combined_package']` — 9 values, exactly matching the SSOT §2.5 legacy-value list and the migration comment. VERIFIED LIVE.
- No model cast, no accessor/mutator normalizes or defaults `service_category` beyond the raw DB default; it is a plain fillable string (`Opportunity.php:96`).
- **Three independent code paths default to `'architecture'` when not supplied, not just the DB column default:**
  1. `app/Http/Controllers/Api/OpportunityController.php:217` (`store()`): `'service_category' => (string) $request->input('service_category', 'architecture')`.
  2. `app/Http/Controllers/Api/LeadController.php:304` (`convert()`, the Lead→Opportunity conversion endpoint): `'service_category' => (string) $request->input('service_category', 'architecture')`.
  3. The DB column default itself (`database/migrations/2026_07_09_100000_create_leads_table.php:43`) — a third, redundant defaulting layer, reached only if a row is inserted outside these two controllers (e.g. a factory/seeder/tinker/future code path) without the column set.
  All three are VERIFIED LIVE. This directly instantiates SSOT §10.3/§12 item 1's "currently active violation of Rule §2.4" (never silently default to Design/Architecture) — confirmed here as a live code defect, not merely a schema artifact.
- **Web layer validation is looser than API layer validation.** `app/Http/Controllers/Web/CrmPageController.php:203` (`convertLead()`): `'service_category' => ['nullable', 'string']` — accepts *any* string, not constrained to `Rule::in(Opportunity::VALID_SERVICE_CATEGORIES)`. The API equivalents (`LeadController.php:279`, `OpportunityController.php:110`) do use `Rule::in(Opportunity::VALID_SERVICE_CATEGORIES)`. `CrmPageController::convertLead()` delegates to `ApiLeadController::convert()` internally (constructor-injected, `CrmPageController.php:197`) so the actual persistence goes through the same defaulting code (C.2.2 above) — this is one defect, not two independent ones — but the web-form validation gap means a web submission could pass an out-of-enum string that the API path alone would reject, until it reaches the same `convert()` method's own (API-level) validator. VERIFIED LIVE; classified as a real but narrow inconsistency, not a second root cause.
- **Stage-gate enforcement is absent.** SSOT §3.2 states `scope_defined` transition, formal Quote creation, and WON→Project conversion "each require ≥1 confirmed Service Line" (a *future* normative rule, not asserted as already implemented). Live check: `app/Services/Crm/OpportunityStageTransitionService.php` contains no reference to `service_category` (grep returns zero hits); `OpportunityController::convert()` (WON→Project, see C.5) validates only `project_name/start_date/end_date`, not classification state. **No such gate exists today.** VERIFIED LIVE — consistent with SSOT (rule not yet implemented), and confirms Phase B has a real, unblocked greenfield to build the gate into.

### C.3 Quote
- `app/Models/Quote.php` — grep for `service|scope|classif` returns zero hits. No scope/service-line snapshot field exists on `quotes` or `quote_line_items`. VERIFIED LIVE, matches SSOT §15 (`Quote scope/service snapshot | DESIGN_ONLY_NOT_IMPLEMENTED`).
- `OpportunityController::createContract()` (line ~463) reads `$opportunity->external_quote_snapshot` (an existing JSON-cast field, `Opportunity.php:121`, used for the external zena-boq quote integration) and looks up a native accepted `Quote` — neither path touches `service_category`. VERIFIED LIVE.

### C.4 Contract
- `app/Models/Contract.php` — grep for `service|scope|classif` returns zero hits outside unrelated `scopeForProject`/`scopeByStatus`/`scopeActive`/`scopeLatestVersion` query-scope method names (Eloquent local-scope naming convention, not classification). VERIFIED LIVE, matches SSOT §15.
- `OpportunityController::createContract()` (`app/Http/Controllers/Api/OpportunityController.php:422-`) creates a `Contract` without any `service_category`/service-line field — none exists on the model to set. VERIFIED LIVE.

### C.5 WON → Project conversion
- `OpportunityController::convert()` (`app/Http/Controllers/Api/OpportunityController.php:339-420`): creates `Project` from `opportunity_name`, `service_scope_summary` (free text, into `description`), `estimated_project_value`/`estimated_fee`, `technical_owner_id`/`sales_owner_id`. **`service_category` is never read or copied.** VERIFIED LIVE. Consistent with SSOT §15 (`WON→Project conversion path | SUPPORTED (mechanism) / DESIGN_ONLY (classification propagation)`).
- `OpportunityController::createContract()` (`app/Http/Controllers/Api/OpportunityController.php:479-505`) has a second, near-duplicate Project-creation block (used when a contract is generated before an explicit `convert()` call) — same omission, `service_category` never referenced. VERIFIED LIVE; this is a second code path Phase B must account for, not a duplicate of the same call site.

## D. Classification-driven consumers (repo-wide search, `service_category`/`ServiceLine`/`service_line`/`service_scope`)

Exhaustive `grep -rl` across `app/` and `src/` for these terms returns exactly these files (VERIFIED LIVE, full list, none omitted):

| File | Role | Behavior |
|---|---|---|
| `app/Models/Opportunity.php` | Field owner | See §C.2 |
| `app/Http/Controllers/Api/LeadController.php` | Lead→Opportunity conversion | Defaults to `architecture` (§C.2) |
| `app/Http/Controllers/Api/OpportunityController.php` | Opportunity CRUD, WON conversion, contract creation | Defaults to `architecture` on create; never propagates on convert (§C.2, §C.5) |
| `app/Http/Controllers/Web/CrmPageController.php` | Web CRM forms | Looser validation, delegates to API controllers (§C.2) |
| `app/Http/Controllers/Web/DesignItemPageController.php` | AI Design-Item suggestion endpoint | **Reverse-looks-up** `service_category` via `Opportunity::where('converted_project_id', $projectId)->value('service_category')` (line 94-97) and feeds it into `AiAssistService::suggestDesignItemDescription($itemType, $serviceCategory)`. This is a real runtime consumer: any Project whose originating Opportunity silently defaulted to `'architecture'` (§C.2) will bias AI-generated Design Item copy toward architecture framing even for e.g. a `construction`- or `mep`-classified engagement. VERIFIED LIVE. |
| `app/Services/AiAssistService.php` | AI lead-classification suggestion (separate feature, Phase 7/PR#159) | Produces a *candidate* `service_category` value from an LLM tool-call for Lead-capture assistance — advisory only, does not persist or gate anything by itself. |
| `app/Services/BusinessKpiService.php:140-149` | CRM reporting KPI | `groupBy('service_category')` over Opportunities `whereNotNull('service_category')` to compute win-rate/avg-fee **by category**. Because the column is virtually never null (DB default + 2 code defaults, §C.2), every unclassified Opportunity is silently counted as `'architecture'` in this report today — a live reporting-accuracy consequence of the defaulting defect, not merely a schema nit. VERIFIED LIVE. |

No RBAC policy, tenant-scope rule, route filter, portfolio filter, export, or scheduled job references `service_category`/service-line anywhere in `app/`, `src/`, `routes/`, or `resources/views/` beyond the above. VERIFIED LIVE (repo-wide grep, zero additional hits).

## E. RBAC / tenant isolation

`Opportunity`, `Lead`, `Project`, `Quote`, `Contract` all use `App\Traits\TenantScope` (confirmed on `Opportunity.php:26`, and present on the sibling models by prior-session convention; not re-verified per-model here beyond Opportunity/Project which were read in full). Service-Line classification is a plain attribute on an already tenant-scoped model — Phase B introduces no new tenant-isolation surface by itself, only a new (or extended) column/table that must inherit the same `tenant_id` scoping discipline the existing fields already use. VERIFIED LIVE for `Opportunity`/`Project`; ASSUMPTION-UNVERIFIED (based on established repo convention, not re-read line-by-line) for `Quote`/`Contract`/`Lead`'s exact trait usage in this session.

`app/Policies/OpportunityPolicy.php` exists and gates `create`/`update`/`convert` on `Opportunity` (referenced at call sites in §C.2/§C.5); it does not reference `service_category`. No classification-based authorization rule exists today. VERIFIED LIVE (existence and non-reference confirmed; full policy body not read line-by-line this session).

## F. Zero-implementation surfaces (confirms SSOT §15's evidence appendix is still accurate on current main)

Repo-wide case-insensitive search for `portfolio` and `control.tower`/`ControlTower` across `app/`, `src/`, `routes/`, `resources/views/`: the only hits are a `.blade.php.backup` stray file and one UI copy string (`'projects': 'Manage your project portfolio'` in `resources/views/layouts/app-layout.blade.php:331`) — not a feature. Search for `oppm` (case-insensitive) returns zero hits anywhere in `app/`, `src/`, `routes/`, `resources/views/`. VERIFIED LIVE, 2026-08-25 — confirms SSOT §15's `OPPM / Control Tower / Portfolios | DESIGN_ONLY_NOT_IMPLEMENTED (100%)` claim still holds on current `main` (10 days after the SSOT's own 2026-08-15 evidence date; nothing in the intervening commits — GAP-040/041/042/043/044/045 discovery and release work, all test-infrastructure/CI-only per their own decision records — touched CRM/Project-classification code).

## G. Data-migration problem — legacy value mapping contract

Per SSOT §2.5, the canonical mapping is: `architecture|interior|landscape|structure|mep → DESIGN (INFERRED)`; `construction → CONSTRUCTION (INFERRED)`; `inspection → NEEDS_REVIEW` (current UI label "Giám sát" is not the same concept as standalone Inspection, SSOT §2.6); `consulting, combined_package → NEEDS_REVIEW`; `null/unrecognized → UNKNOWN`. This document does not redesign that mapping (Phase B's job); it verifies what evidence is and is not available to execute it:

- **No `OpportunityFactory` exists in the repo** (`database/factories/` contains no `OpportunityFactory.php`; only `OpportunityAppointmentFactory.php` was found). Tests construct Opportunities via direct `::create()`/helper methods, most of which pass `'service_category' => 'architecture'` explicitly (e.g. `tests/Feature/Api/CrmApiTest.php:75,193,636`; `tests/Feature/Zena/CrmReportPageTest.php:52,303,331`; `tests/Unit/Services/BusinessKpiServiceTest.php:53,162-164,181,273`). VERIFIED LIVE. This means the *test* corpus's value distribution is dominated by the same default this audit flags as a defect — it does not encode a deliberate, tested cross-category distribution.
- **No seeder sets `service_category`** (grep across `database/seeders/*.php` for the term returns zero hits). VERIFIED LIVE.
- **This repo checkout has no way to observe real production data distribution.** No local/dev database was inspected this session (none was available/authorized in this isolated worktree agent's environment). The actual live distribution of `architecture` vs. genuinely-classified vs. silently-defaulted rows in any real tenant's data is **UNKNOWN — must be obtained from Owner or a live database inspection at Phase B time**, not assumed or estimated here. This is a genuine evidentiary gap, reported explicitly per the mission's instruction not to manufacture counts.
- Because `service_category` defaults to `'architecture'` at three layers (§C.2) and no code path ever reads it back as "was this ever actually set by a human," **CONFIRMED vs. INFERRED vs. UNKNOWN provenance cannot be reconstructed retroactively from the `service_category` value alone** for any existing row — the column carries no distinguishing signal between "a user genuinely selected Architecture" and "no one touched this field." This is the central reason a provenance field (SSOT §2.5) is a Phase B necessity, not an optional nicety: without it, Phase B's own migration would face the identical ambiguity this audit is surfacing, permanently, unless a provenance column is introduced going forward and existing rows are conservatively marked (e.g. `NEEDS_REVIEW`/`UNKNOWN`) rather than assumed `CONFIRMED`. VERIFIED STATIC (reasoned from §C.2's live evidence).

## H. Blast radius / dependency map

**Must be owned by this Work ID's Phase B (Foundation Build):**
- `Opportunity.service_category` scalar → multi-value Service Line representation + provenance field(s) (exact schema is a Gate 2 decision, not decided here).
- The three live defaulting-to-`architecture` code paths (§C.2.1–3) — replacing "silently default" with "explicit `UNKNOWN`/unclassified" per SSOT §2.4.
- The `Web` vs `API` validation inconsistency (§C.2, `CrmPageController.php:203`) — bringing both under one consistent gate.
- WON→Project classification propagation (currently absent, §C.5) — both `convert()` and `createContract()`'s duplicate Project-creation block.
- `BusinessKpiService::serviceCategoryPerformance()` (or equivalent) — must read the new canonical representation once it exists, not `service_category` directly, or it will misreport during/after migration.
- `DesignItemPageController`'s reverse Opportunity lookup for AI suggestions — must read the new canonical representation.

**Should merely be made compatible (not owned, but must not break):**
- `Quote`/`Contract` models and their controllers — no change required by this slice per SSOT §3.4 (Contract multi-value Service Lines is "subordinate to a not-yet-performed Contract consumer audit," explicitly out of scope here).
- `AiAssistService`'s Lead-suggestion tool-call output shape (`service_category` as a suggested string) — can continue emitting a legacy-shaped suggestion short-term; Phase B should confirm it doesn't need to change API immediately, but does not need to be redesigned in Phase B.
- Existing tests that hardcode `'service_category' => 'architecture'` — will need updating whenever Phase B's defaulting fix lands, but that is Phase B implementation work, not Phase A's.

**Explicitly out of scope for this Work ID (per mission §5, confirmed still correctly out of scope after this audit — no evidence surfaced that changes this):**
CRM Classification UX & Gates (SSOT §14 item 3); Quote Scope Snapshot (§14 item 5); Contract Service-Line migration (§14 item 4's Contract-relevant part, subordinate to §3.4's undone audit); Portfolio Membership Migration (§14 item 6); Project OPPM (§14 item 9, Issue #248); Control Tower (§14 item 10); Finance Control / Project Treasury (§6–§7, GAP-036-adjacent, unrelated); GAP-041/GAP-042/GAP-045 (unrelated CI/test-infrastructure work items, confirmed by their own decision records to be test-only, not CRM/classification-related — cross-checked against `OPERATIONAL_GAP_REGISTER.md` entries for GAP-041/042/045 during this audit, VERIFIED LIVE).

**No architectural dependency discovered during this audit makes any of the above boundaries impossible.** No escalation needed on that front.

## I. Compatibility risks a multi-value foundation would introduce (named, not designed)

1. Every existing query/report that treats `service_category` as a single scalar (`BusinessKpiService::groupBy('service_category')`, §D) must be rewritten for a set-membership model — a real but bounded blast radius (one file identified).
2. Existing tests hardcoding the scalar value (§G) will need mechanical updates; volume is moderate (≥10 test files touch the field per §D/§G's file list) but each is a simple literal-value change, not a structural rewrite, based on the usages read.
3. The AI Design-Item consumer (`DesignItemPageController`, §D) does a single-value reverse lookup (`->value('service_category')`) — a multi-value model requires this call site to decide which/how-many values to pass into the AI service, a small but real design question for Phase B (not decided here).
4. Legacy-value migration provenance ambiguity (§G) means Phase B cannot honestly claim `CONFIRMED` for any pre-existing row without an explicit, conservative default (`NEEDS_REVIEW`/`UNKNOWN`) — a compatibility/trust risk if skipped, not a technical blocker.

## J. Answers the evidence above lets the Owner reach without reading the repo (mission §6)

1. **What is wrong/missing today:** `Opportunity.service_category` is the only classification field anywhere in the CRM→Project funnel, it is a single scalar (not multi-value), it silently defaults to `'architecture'` at three independent layers, that default is never propagated to `Project`/`Contract`/`Quote`, and it already feeds a live AI-suggestion consumer and a live reporting consumer with the same silent-default bias.
2. **Real workflows affected:** creating an Opportunity via API or Web without explicitly picking a category (defaults to Architecture unnoticed); the CRM report's category performance breakdown (biased toward Architecture); AI Design-Item description suggestions on any Project whose Opportunity never had classification explicitly set.
3. **Why a foundation, not UI cleanup:** the defect is in three separate backend code paths plus the schema default, not a display-layer issue; no amount of UI polish fixes a column that is wrong at write-time in the API/DB layer, and there is currently no field capable of representing "this Project is both Design and Construction" at all.
4. **Closest existing authority:** `Opportunity.VALID_SERVICE_CATEGORIES` (9 legacy values) is the closest thing to an existing taxonomy; it predates and does not yet reflect the SSOT's 3-value canonical model (`DESIGN`/`CONSTRUCTION`/`INSPECTION`) plus separate Scope/Discipline dimension.
5. **Where current behavior conflicts with the SSOT:** §2.4 (never default to Design/Architecture) is actively violated at three code layers (§C.2); §2.1 (multi-valued Service Line) does not exist yet (scalar only); §3.2's proposed stage-gates do not exist yet (no gate blocks progression on missing classification).
6. **Legacy/data ambiguity:** the 9 existing values map cleanly per SSOT §2.5's rule table, but no code today distinguishes a genuinely-user-chosen value from a silently-defaulted one, and this repo checkout cannot determine the real production-data distribution — that is an Owner/Phase-B-time question, not something this audit can answer from source alone.
7. **Smallest correct Phase B boundary:** central Service Line value set + Opportunity/Project membership mechanism + provenance field(s) (SSOT §12 item 5's own framing) — i.e., exactly the two backend models (`Opportunity`, `Project`) plus the ~2 conversion call sites plus the 2 live consumers named in §D, not Contract/Quote/Portfolio/OPPM.
8. **What Phase B must not own:** everything listed in §H's "explicitly out of scope" bucket.
9. **Compatibility risks:** named in §I — bounded, mechanical, not architecturally blocking.
10. **Anything serious enough to block Gate 2:** no. No architectural conflict, no impossible boundary, and no evidence contradicting the SSOT was found. The one open evidentiary gap (real production-data distribution, §G) is a Phase-B-time data question, not a Gate-1/Gate-2 blocker — Phase B's own Gate 2 design should explicitly plan how it will obtain or approximate that distribution before executing any migration.

## K. Unresolved factual uncertainty (explicit, not smoothed over)

- Real production `service_category` value distribution: **UNKNOWN**, no local/dev DB was available to this session to inspect, and no repo evidence (seeders/factories) encodes it. Flagged for Phase B, not assumed.
- Exact tenant-scope trait usage on `Quote`/`Contract`/`Lead` models: cross-checked by established repo convention and by their controllers' `tenant_id`-scoped queries, but not re-read trait-by-trait this session (ASSUMPTION-UNVERIFIED, low risk — these models are already in production use under the existing tenant-isolation regime regardless of this audit's outcome).
- Whether any additional legacy/duplicate CRM surface exists outside `app/`/`src/` (e.g. a stale JS-only or mobile-API-only path) was not searched beyond the standard backend directories listed in §D; a full-text search of `resources/` (Blade/JS) for `service_category` was performed only via the `grep -rl` pass in §D's directory list (`resources/views/` included), which found none beyond the layout string in §F — no separate `resources/js` classification UI was found, consistent with SSOT §15 ("CRM Lead service intent | DESIGN_ONLY_NOT_IMPLEMENTED").
