---
work_id: GAP-046
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-046/02-design.md
---

# GAP-046 Phase B — Canonical Service-Line Foundation: Persistence & Migration Design (Gate 2)

**Status:** Gate 2 preparing/awaiting Owner review. **This document is docs-only.** It authorizes no migration, model, controller, service, route, or UI change. Selecting a design here does not itself authorize building it — that authorization is Gate 2 Owner approval, and even after approval, only the exact bounded surface named in §7 below.

**Scope binding:** This design is constrained by the Owner's Gate-1-approval scope clarification (`docs/owner-decisions/GAP-046/01-request.md`, OWNER GATE 1: APPROVED section) and by the canonical SSOT (`docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md`, OWN-2026-009), which controls wherever this document is silent or in tension with PR #257/#245 (non-normative). Originally verified against `main` at `c3a1226059bcf5a573aad1eebf8f1333331d9ad2` when first authored (2026-08-25); design substance below is unchanged since.

**Provenance note (mechanical split, 2026-08-26):** this document was originally submitted in the same PR as Gate 1 (PR #287); Owner directed a split into two PRs to work around a confirmed lint-tooling gap (OWN-2026-005's gate-ordering exemption excludes `docs/audits/**` from its path-prefix allowlist, so a same-PR Gate1+Gate2 submission whose diff included the Gate-1 evidence file under `docs/audits/` failed `--enforce-gate-ordering` even though the actual content was 100% documentation). Gate 1 (`01-request.md`) landed to `main` at squash-merge SHA `f913f040063fc628ad8f425b5f01ff5da960d742`. This document is recovered byte-for-byte from the original combined-PR head `7fe8b8d73b8a63b70a1e142d08cf98a97cda2878` — no design content, option comparison, recommendation, or exclusion was altered during the split; only this provenance note was added and the opening scope-binding sentence's tense was adjusted to reflect that the original main-drift check happened at initial authoring, not at re-submission. Canonical main at the time of this split-driven re-submission is `f913f040063fc628ad8f425b5f01ff5da960d742` (re-verified live, zero unexpected drift beyond the just-merged Gate-1 commit itself).

---

## 1. What this design must accomplish (recap of Owner's Gate-1-approval boundary)

In scope: (a) a canonical Service-Line value set; (b) an Opportunity/Project membership *persistence mechanism* (schema + minimal read/write model surface — not runtime business-logic wiring, see §6); (c) a CONFIRMED/INFERRED/NEEDS_REVIEW/UNKNOWN provenance representation; (d) migration/backfill mechanics strictly necessary to establish the foundation, conservatively (never marking legacy rows CONFIRMED); (e) narrowly necessary compatibility mechanics, only if proven strictly required.

Out of scope (each belongs to a separate future Work ID per SSOT §14): fixing the live `Opportunity.service_category` default-to-`architecture` write path; runtime Opportunity→Project Service-Line propagation; CRM stage gates/classification UX; Quote Scope Snapshot; Contract Service-Line implementation; Portfolio Membership behavior; Project OPPM; Control Tower; Finance/Treasury.

## 2. Canonical Service-Line value set — design question and recommendation

**Question:** does "central value set" require a new database lookup table, or is an application-level constant enum sufficient?

**Evidence:** SSOT §2.1 fixes the value set normatively at exactly three values (`DESIGN`, `CONSTRUCTION`, `INSPECTION`) and gives no indication more will be added without its own future normative change. The existing, closest-analog field in this codebase, `Opportunity.service_category`, is implemented exactly this way today — `Opportunity::VALID_SERVICE_CATEGORIES` (`app/Models/Opportunity.php:79-82`), a `public const` array, validated at the controller layer via `Rule::in(...)` (`OpportunityController.php:110`, `LeadController.php:279`) — not a lookup table. No other classification-style field in this repo (`pipeline_stage`, `forecast_category`, `priority`, `account_type`, `status` fields throughout) uses a DB lookup table either; all are plain `string` columns validated by app-level constant arrays. This is an established, consistent repo convention, not an ad hoc choice.

**Recommendation:** a `public const` array `SERVICE_LINES = ['DESIGN', 'CONSTRUCTION', 'INSPECTION']` (exact host class TBD at implementation time — likely a new small `ServiceLine` value-object/enum class shared by both `Opportunity` and `Project`, avoiding duplicating the array on two models), not a database table. This matches repo convention, avoids an unnecessary join for a fixed, small, normatively-closed set, and is trivially portable across SQLite/MySQL (no schema difference at all — it's PHP-only). If the Owner intends genuine future extensibility beyond the three SSOT-fixed values, that would be a deliberate deviation from current repo convention and should be called out explicitly in Gate 2 review — this design does not assume it.

## 3. Membership + provenance persistence — four options compared

All four options were evaluated against: portable SQLite/MySQL DDL only (no driver-specific syntax — confirmed as this repo's universal existing convention across every migration read during Gate 1 and this design pass); tenant isolation matching the existing `TenantScope` pattern (every classification-bearing table in this repo carries its own `tenant_id` column and FK, not an inferred join — confirmed on `opportunities`/`accounts`/`leads`, `2026_07_09_100000_create_leads_table.php`); multi-value support (a Project/Opportunity can carry more than one Service Line simultaneously, SSOT §2.1); and per-line provenance (SSOT §2.5's four-state model applies per classification, not once per subject).

### Option A — Single polymorphic table
One table (`service_line_classifications`: `id, tenant_id, classifiable_type, classifiable_id, service_line, provenance, source, created_by, created_at, updated_at`), shared by `Opportunity` and `Project` via a polymorphic relation.
- **Pro:** one table, one model, DRY.
- **Con:** polymorphic FKs cannot carry a real DB foreign-key constraint to two different parent tables simultaneously — referential integrity becomes app-level only, weaker than this repo's existing convention (every other relation in the funnel uses a real `$table->foreign(...)` constraint, e.g. `opportunities_account_id_foreign`). Also over-generalizes for a case the Owner has explicitly bounded to exactly two named subject types (Opportunity, Project) — no third classifiable type is in scope or foreseeable within this Work ID.

### Option B — Two explicit non-polymorphic tables (RECOMMENDED)
`opportunity_service_lines` (`id, tenant_id, opportunity_id, service_line, provenance, source, created_by, created_at, updated_at`) and `project_service_lines` (same shape, `project_id`). Each has a real `$table->foreign('opportunity_id')->references('id')->on('opportunities')->cascadeOnDelete()` (and the `projects` equivalent), plus `$table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete()`, matching every existing migration in this funnel exactly. Unique constraint `(tenant_id, opportunity_id, service_line)` / `(tenant_id, project_id, service_line)` prevents duplicate rows.
- **Pro:** real FK integrity to both parents (matches 100% of existing convention in this codebase); trivial to query ("all Opportunities with DESIGN" is a plain indexed join, no `classifiable_type` filter needed); each table can evolve independently if Opportunity- and Project-side semantics diverge later (e.g. Project needs a "removal requires controlled reclassification" audit trail per SSOT §3.5 that Opportunity does not).
- **Con:** two tables/two small models instead of one — minor duplication, mitigated by sharing the provenance enum/constants and validation logic in one trait or base concern.

### Option C — JSON column on each parent
`opportunities.service_lines` / `projects.service_lines`, a JSON array of `{line, provenance, source, ...}` objects (Laravel `json` cast).
- **Pro:** no new tables, no joins.
- **Con:** no queryable index for "which Projects have CONSTRUCTION" without a MySQL/SQLite JSON-function query, which this repo's migrations do not currently use anywhere (grep for `whereJsonContains`/`json` schema usage across `app/Models` found only the pre-existing, unrelated `external_quote_snapshot` field on `Opportunity`, itself never filtered/queried by content, only stored/displayed) — a real, repo-verified capability gap. No per-row `created_at`/`created_by` audit trail without hand-rolling it inside the JSON structure. Rejected: portfolio membership (a later slice, but the entire reason this foundation exists per SSOT §2.7) needs indexed set-membership queries, and a JSON column defers that cost onto a slice that explicitly must not redesign this one's schema.

### Option D — Flat boolean + provenance columns per line on each parent
`opportunities.is_design`, `opportunities.design_provenance`, `opportunities.is_construction`, `opportunities.construction_provenance`, `opportunities.is_inspection`, `opportunities.inspection_provenance` (6 columns × 2 tables = 12 new columns), same shape on `projects`.
- **Pro:** simplest possible query (`WHERE is_design = true`), no join at all.
- **Con:** not extensible without a schema migration if the SSOT-fixed 3-value set is ever revisited (explicitly a future normative decision, not this document's to assume shut); triples the provenance-column count per parent instead of one row per actual classification; no natural place for a per-classification `source`/`created_by`/timestamp without further tripling columns. Rejected as the least normalized, least auditable option, though it remains the cheapest to query.

### Recommendation: **Option B**

Matches this repository's own established migration/FK/tenant-isolation conventions more closely than any alternative (verified against every migration read in this funnel during Gate 1 and this design pass), gives genuine indexed set-membership queries for the portfolio-membership slice this foundation exists to enable (SSOT §2.7), and keeps Opportunity-side and Project-side reclassification-audit semantics (SSOT §3.5's "removing a line is more dangerous than adding" applies to Project, not Opportunity) independently evolvable without cross-contamination.

## 4. Provenance representation

Per SSOT §2.5: `CONFIRMED | INFERRED | NEEDS_REVIEW | UNKNOWN`. Recommendation: a plain `string` column `provenance` on each new table (not a DB `ENUM` type — this repo's convention, confirmed zero uses of `$table->enum(...)` anywhere in `database/migrations/` for any classification-style field; MySQL `ENUM` and SQLite have divergent semantics and alteration costs, which is exactly the kind of driver-specific behavior every other classification field in this codebase already avoids), validated at the application layer via a `Rule::in(...)` constant array, mirroring `Opportunity::VALID_SERVICE_CATEGORIES`'s existing pattern exactly.

## 5. Tenant isolation, uniqueness, integrity

- Both new tables carry their own `tenant_id` ULID column + real FK to `tenants`, matching every table in the funnel (`opportunities`, `accounts`, `leads`) — not inferred solely through the parent relation, so the existing `TenantScope` trait pattern (already used by `Opportunity`/`Project`, confirmed §E of the Gate-1 audit) applies directly to the new tables' own Eloquent models without a cross-table scope workaround.
- Unique constraint `(tenant_id, opportunity_id, service_line)` and `(tenant_id, project_id, service_line)` — prevents a duplicate classification row for the same subject/line/tenant triple. (Tenant is redundant with the parent's own tenant in a correctly-functioning system, but included in the constraint for defense-in-depth consistent with how `opportunities_tenant_stage_index` etc. already compose tenant_id into indexes throughout this schema.)
- Real FK `cascadeOnDelete()` to the parent (`opportunities`/`projects`) — a hard delete of the parent removes its classification rows; `Project`'s existing `SoftDeletes` is unaffected (soft delete never touches FK-triggered cascade, only a real `DELETE` does), matching how every other child table in this schema already relates to `projects`/`opportunities`.

## 6. Membership mechanism vs. runtime propagation — the exact boundary line (flagged per Owner instruction #6, not resolved here)

Owner's Gate-1 scope clarification authorizes the "Opportunity/Project membership persistence mechanism" but explicitly excludes "runtime Opportunity→Project Service-Line propagation." This design reads that boundary as: Phase B may build the schema, the two new Eloquent models, and a plain read/write relation (`Opportunity::serviceLines(): HasMany`, `Project::serviceLines(): HasMany`) — but must **not** modify `OpportunityController::convert()` or `OpportunityController::createContract()` (the WON→Project conversion call sites identified in the Gate-1 audit, §C.5) to automatically copy an Opportunity's Service Lines onto the newly created Project. The practical consequence: immediately after Phase B ships, the `project_service_lines` table has **zero rows for every Project**, including ones converted after Phase B ships, until the separate future "Opportunity→Project Propagation & Project Classification UX" slice wires the copy step in. This is a real, visible tradeoff (a "foundation" with no live writer on the Project side yet) that follows directly from the Owner's own stated boundary, not a boundary this document is choosing to cross — flagged here explicitly per Owner instruction #6, not silently absorbed and not silently left unstated.

## 7. Legacy backfill semantics — scope and the one open boundary question

**In scope (Opportunity side):** a one-time, idempotent backfill migration or Artisan command that reads every existing `Opportunity.service_category` value and inserts the corresponding `opportunity_service_lines` row(s) per SSOT §2.5's mapping table (`architecture|interior|landscape|structure|mep → DESIGN (INFERRED)`; `construction → CONSTRUCTION (INFERRED)`; `inspection → NEEDS_REVIEW`; `consulting|combined_package → NEEDS_REVIEW`; `null/unrecognized → UNKNOWN`). Per Owner's explicit instruction, **no row is ever inserted with `provenance = CONFIRMED` by this backfill** — the existing column carries no signal distinguishing genuine user choice from the silent code-level default (Gate-1 audit §C.2/§G), so `INFERRED`/`NEEDS_REVIEW`/`UNKNOWN` per the mapping table is the ceiling of what can honestly be claimed. Idempotency: the backfill command must be safe to re-run (e.g. `firstOrCreate` keyed on the unique constraint, or an explicit "already backfilled" check) so it is not a one-shot irreversible script.

**Open boundary question the Owner should confirm, not this document's to decide:** should the same backfill also populate `project_service_lines` for Projects that were *already* converted from an Opportunity *before* Phase B ships, by reading their historical `Opportunity.converted_project_id` link? Mechanically this is a one-time batch read of exactly the same data a *runtime* propagation step would read continuously — the data source and mapping logic are identical, only the trigger (batch-once vs. on-every-conversion) differs. Given §6's boundary (runtime propagation explicitly excluded), this design's conservative default is to **also exclude this one-time Project-side backfill** and leave `project_service_lines` empty for all pre-existing Projects (they remain `UNKNOWN`-by-absence until a future slice classifies them, consistent with SSOT §2.4's "missing classification must remain UNKNOWN"). This is flagged, not decided, because a reasonable case exists either way and it sits exactly on the boundary line Owner drew — Gate 2 approval should explicitly confirm or override this default before implementation.

## 8. Backward compatibility

`Opportunity.service_category` (and its default, and `BusinessKpiService`/`DesignItemPageController`'s reads of it) are **not modified, removed, or overridden** by this design — no destructive migration (SSOT §10.1), and no code path currently reading `service_category` needs to change, because the new tables are purely additive and nothing yet writes through them at runtime (§6). This satisfies Owner's "compatibility treatment only if strictly required" instruction by concluding it is **not** required for Phase B: both discovered consumers keep functioning exactly as today, unchanged, for the duration of Phase B.

## 9. Rollback

Both new tables are created by ordinary Laravel migrations with a `down()` that `Schema::dropIfExists(...)` each table — no existing table or column is altered, so rollback is a clean, complete no-op on every other part of the schema. The backfill command (§7) is separate from the migration (not baked into `up()`), so it can be re-run or skipped independently without coupling to migration rollback semantics.

## 10. SQLite/MySQL parity

Every column type proposed (`ulid`, `string`, `foreignId`/`foreign()->references()->on()`, `timestamps`) is a portable Laravel `Blueprint` primitive already used identically across every migration in this funnel (`2026_07_09_100000_create_leads_table.php` and siblings) on both drivers in this repo's existing CI (SQLite for the default test tier, MySQL for the MySQL-parity tier per GAP-040/041/043/044's own evidence). No driver-specific DDL, no MySQL `ENUM`, no JSON functions — nothing in this design introduces a new parity risk class beyond what the existing `opportunities`/`accounts`/`leads` tables already carry successfully today.

## 11. Exact future implementation boundary (for whichever Work ID/Gate 3 eventually implements this)

**In:** 2 migrations (`opportunity_service_lines`, `project_service_lines`); 2 small Eloquent models (or 1 shared concern/trait + 2 thin models) with `TenantScope`, real FKs, the provenance constant/validation; a `serviceLines()` relation added to `Opportunity` and `Project`; the Opportunity-side backfill command (§7); a shared `ServiceLine`/provenance constants class (§2, §4). **Out:** any change to `OpportunityController`, `LeadController`, `CrmPageController`, `DesignItemPageController`, `BusinessKpiService`, or any existing migration/column; any UI; any RBAC/policy change; the Project-side backfill question (§7, pending Owner confirmation); everything listed in §1's out-of-scope list.

## 12. Acceptance tests (Gate 3 criteria, not built at Gate 2)

1. Tenant isolation: a `service_line` row created under tenant A is invisible to a query scoped to tenant B.
2. Uniqueness: inserting a duplicate `(tenant_id, opportunity_id, service_line)` (or Project equivalent) fails/is prevented.
3. Provenance validation: an out-of-enum `provenance` value is rejected at the application layer.
4. Migration round-trip: `up()`/`down()` succeed cleanly on both SQLite and MySQL, verified LIVE on MySQL per this repo's established real-MySQL evidence standard (not SQLite-only).
5. Backfill idempotency: running the Opportunity-side backfill command twice produces no duplicate rows and no error.
6. Backfill provenance ceiling: no row produced by the backfill ever has `provenance = CONFIRMED`.
7. Zero regression: `BusinessKpiServiceTest` and the `DesignItemPageController`/`AiDesignItemSuggestionTest` suites continue passing unmodified, proving the new tables are additive and non-breaking.
8. `Opportunity::serviceLines()`/`Project::serviceLines()` relations return the expected rows for a seeded fixture, with no other business-logic side effects.

## 13. Loại trừ rõ ràng (explicit exclusions, restated)

Everything in §1's out-of-scope list; the Project-side historical backfill (§7, pending Owner confirmation, currently excluded by default); any change to `service_category`'s default value or validation; any UI/CRM-form change; any stage-gate enforcement; any Quote/Contract schema change; any Portfolio/OPPM/Control-Tower/Finance/Treasury code.

## 14. Decision Needed

Owner chooses one: Approve Option B (§3) and the §7 conservative default (Project-side backfill excluded) to authorize implementation within the exact §11 boundary / Request changes to the design (e.g. a different persistence option, or an explicit override of the §7 default) / Decline.

## 15. What the owner is NOT being asked to decide

Not being asked to approve exact column names, exact model/class names, exact migration file naming, or exact test file organization — those are implementation-time details within the approved §11 boundary. Not being asked to decide anything about the excluded slices in §1/§13 — those remain separate future Work IDs with their own Gate 1→2→3 lifecycles.
