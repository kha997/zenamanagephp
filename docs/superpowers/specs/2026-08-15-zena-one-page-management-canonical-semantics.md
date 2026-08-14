---
work_id: OWN-2026-009
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/OWN-2026-009/02-design.md
---

# ZENA One-Page Management — Canonical Shared Semantics (SSOT)

**Status:** Gate 1 approved (2026-08-15). Gate 2 preparing/awaiting Owner review. Gate 3 not started. **This document is docs-only.** It authorizes no migration, model, controller, service, route, or UI change. Nothing in it may be cited as implementation authorization; each future implementation slice (§14) still requires its own Work ID and its own Gate 1→2→3 lifecycle.

**Authority and precedence:** This document is the canonical reference for the shared semantics it defines. Where it conflicts with PR #257 (`ded7cf9f558bd7960b5eff5836140b1e15255b9a`) or PR #245 (`cd8b79d861f4c1bae5278b6c57f29cd14e505594`), this document controls — those two PRs are non-normative source evidence only (§13). Where this document is silent, PR #257/#245 remain useful context but not authority; a future slice's own Gate 2 must resolve the gap explicitly, not assume the source PRs decide it.

**Change control:** Amending a normative rule in this document after Gate 2 approval requires a new Gate 2 revision (a new file, `supersedes` the prior one) per `docs/owner-governance/OWNER_DECISION_RULES.md`'s routing rule — not a silent edit.

---

## 1. Product hierarchy (normative)

1. **One CRM pipeline.** Design, Construction, and Inspection engagements share a single Opportunity pipeline. There is no per-lens CRM.
2. **One canonical Project.** A real-world engagement is one `Project` record regardless of how many Service Lines it carries. There is no per-lens Project system.
3. **Design / Construction / Inspection are operational lenses**, not project types and not separate systems. A Project may carry more than one lens concurrently (e.g. Design-Build).
4. **Resource Control** is a horizontal capability across all lenses — not owned by any single lens.
5. **Project OPPM** is a per-project drill-down/read surface, consuming the shared read models defined below. It is not a second project-management system.
6. **Operations Control Tower** is the company-wide exception layer, assembled from the same shared read models. It is not a replacement for portfolio pages, and it is the last surface to be built (§14).
7. **Today Workspace** (already shipped, `app/Http/Controllers/Web/TodayController.php`) is a separate personal/actor-scoped execution surface. It is out of scope for every slice in §14 and is not touched by this document.

## 2. Service Line & Scope taxonomy (normative)

1. **Service Line is multi-valued**: `DESIGN`, `CONSTRUCTION`, `INSPECTION`. A Project's Service Lines are a set, not a scalar.
2. **Service Scope / Discipline is a separate dimension** from Service Line (e.g. Architecture, Structure, MEP under DESIGN). A full scope catalog is not required before Service Line itself is implemented.
3. **No canonical `project_type` field.** A single scalar classification is explicitly rejected — it cannot represent multi-lens engagements.
4. **Never default to Design/Architecture.** Missing classification must remain `UNKNOWN`/unclassified. No code path may silently apply a default classification.
5. **Classification provenance has four states**: `CONFIRMED`, `INFERRED`, `NEEDS_REVIEW`, `UNKNOWN`. Legacy `Opportunity.service_category` values map as: `architecture|interior|landscape|structure|mep` → `DESIGN` (`INFERRED`); `construction` → `CONSTRUCTION` (`INFERRED`); `inspection` → `NEEDS_REVIEW` (ambiguous — current UI labels it "Giám sát", which is not the same concept as standalone Inspection); `consulting`, `combined_package` → `NEEDS_REVIEW`; null/unrecognized → `UNKNOWN`.
6. **Standalone Inspection ≠ Construction QA/QC inspection.** A construction project's QC/NCR inspection records never by themselves add the `INSPECTION` Service Line to a Project.
7. **Portfolio membership is a set-membership test** (`DESIGN ∈ Project.service_lines`, etc.), not heuristic inference from task names or incidental artifacts. A multi-line Project appears in multiple portfolios but counts once in company unique-project totals — portfolio counts must never be summed and presented as a total project count.
8. **Visibility policy for `INFERRED` rows in portfolio membership is explicitly deferred** to the implementation slice that builds portfolio membership (§14 slice "Portfolio Membership Migration") — not decided here.

## 3. Classification maturity through the funnel (normative)

1. **Lead**: classification is optional ("Service Intent"). May be zero, one, multiple, or explicitly unclassified.
2. **Opportunity**: "Qualified Service Lines." Transition into `scope_defined` (or any downstream active sales stage), formal Quote creation, and WON→Project conversion each require ≥1 confirmed Service Line. No path may silently default missing classification.
3. **Quote**: "Commercial Scope Snapshot." A sent/accepted Quote is a historical commercial artifact — its Service Line/Scope must be captured as of issuance and must not silently change if the Opportunity is edited afterward. Exact storage mechanism is an implementation-slice decision (§14).
4. **Contract**: "Committed Scope" — may be narrower than the Project's full scope (e.g. Project has [DESIGN, CONSTRUCTION], one Contract covers only DESIGN). Project Service Lines and Contract Service Lines are related but not interchangeable. Whether/how Contract gets multi-value Service Lines is explicitly **subordinate to a full Contract consumer audit** (not yet performed) and is out of scope for the first taxonomy implementation slice.
5. **Project**: "Operational Service Lines" — authoritative for portfolio membership. Inherited from confirmed Opportunity Service Lines on WON conversion, with provenance/audit trail. Adding a line later is allowed and audited. Removing a line is more dangerous than adding and must go through a controlled reclassification path when authoritative artifacts (active Design items, active Construction site artifacts, active standalone Inspection workflow) still prove operational scope exists.

## 4. Project Health shared read-model contract (normative)

1. A single, shared read model computes Project Health/attention/delay/progress-reliability, consumed identically by Project OPPM, portfolio pages, and Control Tower. No consuming surface may compute its own divergent formula for the same metric.
2. **Missing source data must never silently render as 0 / 0% / on-track / green.** This applies especially to progress, cost/budget, resource load, contract delay, Inspection Commercial Gate status, and construction QA/QC state. Use an explicit "no data" / "unknown" / "unavailable" state instead.
3. **Lifecycle ≠ attention**, generalized beyond Contract (§6.1): a Project/Contract can remain in an unchanged lifecycle status while simultaneously carrying derived attention flags (e.g. contract-late, forecast-late, blocked, waiting-client). Attention flags are derived from canonical data, not a second source of truth, and must never overload the lifecycle field.
4. Recorded workload/work-state is not the same claim as available capacity. A person with zero recorded open tasks is "no open work recorded," not "available" — real capacity planning (calendar/leave/assignment-period semantics) is explicitly deferred to a later slice (§14).

## 5. Project OPPM boundary (normative — binds Issue #248)

1. Issue #248's scope is **Project OPPM only**: a one-project drill-down/read surface. Confirmed by three Owner-authored amendments on Issue #248.
2. OPPM **consumes** the shared Project Health read model (§4) and canonical Service Lines (§2) to decide which domain blocks to render — it must not infer domain blocks from task names or incidental artifacts once canonical classification exists, and it must not invent its own progress/attention/delay formulas.
3. OPPM does **not** own, and must not independently implement: CRM Service-Line classification CRUD/gates, Opportunity stage gates, Quote scope snapshot mechanics, Contract classification migration, Portfolio Membership taxonomy, company-wide Contract Control, company-wide Finance Control, receivables aging engine, company cashflow calculation, or Project Treasury ledger/wallet.
4. A multi-Service-Line Project may render more than one OPPM domain block while remaining one Project record.
5. OPPM's Commercial & Finance block is a compact, one-project summary with drill-down links — not a replica of Contract Control's or Finance Control's full tables (§6).

## 6. Contract & Finance boundary (normative)

### 6.1 Lifecycle vs. attention
`Contract.status` (draft/active/closed/cancelled — already implemented, `app/Models/Contract.php:100-106`) is lifecycle only. "Contract attention" (waiting signature, commercial condition pending, payment overdue, expiring soon, advance/retention outstanding, payment-certificate waiting, acceptance/closeout pending) is a **separate, derived** concept and must never overload the lifecycle field.

### 6.2 Ownership matrix
- One-project Commercial & Finance summary → **Project OPPM** (Issue #248).
- Company-wide quotation pipeline, company-wide contract portfolio, contract attention/expiring/gate exceptions, payment-certificate portfolio, retention/advance-recovery portfolio → **Commercial & Contract Control** (its own future slice/Work ID).
- Company receivables/aging, company cashflow, cost-vs-cash view, cross-project financial-health comparison → **Finance Control** (its own future slice/Work ID).
- Detailed wallet/ledger/advance/reconciliation, transaction-level project cash mechanics → **Project Treasury** (§7).

### 6.3 Reuse mandate (binds against duplication)
- `app/Services/BusinessKpiService.php:59-104` (`outstandingDebt()`, aging buckets: not_due/1-30/31-60/61-90/90+) is the existing receivables-aging computation. Finance Control must extend/formalize this service, not build a second aging engine.
- `app/Http/Controllers/Web/ReportPageController.php:44-119` (route `operator.reports.cashflow`) is the existing, already cash-basis-correct company cashflow computation (labels its net figure `net`, never `profit`). Finance Control must reuse it, not recompute it.
- `app/Models/PaymentCertificate.php` (draft→submitted→approved, retention/advance-deduction/net-payable fields) and `Contract`'s `retention_percent`/`advance_amount`/`advance_recovery_percent` fields are the existing reuse targets for payment-certificate and retention/advance concepts — do not duplicate.

### 6.4 Inspection Commercial Gate
Classification (§2, which Service Lines apply) is separate from the Commercial Gate (whether execution is permitted). The Gate requires: (1) quotation accepted by client, (2) contract signed and effective, (3) any contract-required advance/precondition satisfied, (4) any other explicit contractual commencement condition satisfied. This is a business invariant — implementations must reject/prevent workflow transitions that bypass it. Contract Control may surface the Gate as a commercial-hold state; it does not redefine Gate logic.

## 7. Project Treasury boundary (normative)

1. Project Treasury owns **transaction-level project cash mechanics**: ledger, wallet/account representation, advances, internal transfers, settlement, reconciliation, immutable posting, reversal/replacement, funding traceability.
2. Project Treasury is **not**: statutory accounting, a general ledger / chart of accounts, revenue recognition, company P&L, a duplicate company cashflow calculation, a duplicate receivables engine, or a duplicate of the Contract payment lifecycle.
3. **Layering** (both directions of information flow are read-only until Treasury is canonical): `Finance Control → Project OPPM → Project Treasury → Ledger/evidence/audit`. Until Project Treasury becomes canonical and released, Finance Control and OPPM use only the currently-canonical sources (`ContractPayment`, `ContractExpense`, `ReportPageController::cashflow`) and mark Treasury-specific measures as **unavailable**, never zero.
4. **Required boundary with existing Contract financial models** (gap identified during reconciliation, §12): the relationship between Treasury's proposed `financial_documents`/`ledger_entries` (`document_type: expense`) and the existing `ContractExpense`/`ContractPayment` models must be made explicit in Project Treasury's own Gate 2 — whether they are inputs to, reconciled against, or replaced by the Treasury ledger. This document does not resolve that; it flags it as a required Gate-2-time decision for the Treasury work item.

## 8. Financial invariants (normative, apply to every surface: OPPM, Contract Control, Finance Control, Project Treasury, Control Tower)

1. **Cost ≠ Cash ≠ Revenue ≠ Profit.** Cost incurred, cash paid, cash received, receivable, contract value, and revenue/profit are distinct concepts. A material receipt recording incurred cost does not prove the supplier has been paid.
2. **Net Cash ≠ Profit.** `cash received − cash paid` is a cash/net-cash figure only, never labeled profit, margin, earned revenue, or accounting income, unless a future approved accounting model explicitly defines those terms.
3. **Missing financial data ≠ zero / green / paid / certain.** Every financial surface must render an explicit "unknown"/"unavailable" state for missing data rather than defaulting to a value that implies certainty.
4. **Contract lifecycle ≠ Contract attention** (restated from §6.1 — applies everywhere the lifecycle field is displayed).

## 9. Operations Control Tower boundary (normative)

1. Company-level exception aggregation only, across all lenses (segmentable by Service Line), assembled from the shared read models in §4/§6/§7 — it computes nothing itself that isn't already defined by an underlying shared read model.
2. Must display **Unique active projects** distinctly from **Projects by Service Line** (§2.7) — the latter must never be summed and presented as the former.
3. Explicitly not built until the underlying portfolio/OPPM/Contract Control/Finance Control read models are stable (§14 ordering).

## 10. Legacy data & migration principles (normative)

1. No destructive migration is authorized by this document or by the first taxonomy implementation slice.
2. Legacy classification ambiguity (§2.5) is resolved by marking rows `NEEDS_REVIEW`/`UNKNOWN`, never by silent reinterpretation.
3. `Opportunity.service_category` defaulting to `'architecture'` (`database/migrations/2026_07_09_100000_create_leads_table.php:47-48`, and the Lead→Opportunity conversion flow) is a **currently active violation** of Rule §2.4. It is recorded here as evidence; fixing it is explicitly out of scope for OWN-2026-009 (Owner directive) and is deferred to the CRM Classification UX & Gates implementation slice (§14).

## 11. Roles (normative, carried from source design, subject to the repository's actual permission names at implementation time)

Owner/Admin: company-wide visibility. PM: accountable-project + team scope. Team Lead: authorized team members. Staff: personal Today/My Work scope only. Any implementation slice must audit and reuse existing permission names before introducing new ones.

## 12. Known conflicts and open items surfaced during reconciliation

| # | Item | Status |
|---|---|---|
| 1 | `Opportunity.service_category` defaults to `architecture` | Active conflict with §2.4 — evidence recorded (§10.3), fix deferred to a future CRM slice, explicitly not this work item |
| 2 | Treasury ↔ `ContractExpense`/`ContractPayment` integration boundary | Not yet decided — flagged as required at Project Treasury's own Gate 2 (§7.4) |
| 3 | `INFERRED` row visibility policy in portfolio membership | Not yet decided — flagged as required at the Portfolio Membership Migration slice (§2.8) |
| 4 | Whether/how Contract gets multi-value Service Lines | Subordinate to a not-yet-performed Contract consumer audit (§3.4) |
| 5 | Exact Service Line persistence schema (join table naming, tenant-duplication approach) | Deferred to the Canonical Service-Line Foundation slice's own schema audit (§14) |
| 6 | `OPERATIONAL_GAP_REGISTER.md` Tier-5 cost/profit blind spot | Reported to Owner separately as GAP-036 candidate; not part of this document's scope |

None of the above block Gate 2 approval of this document — each is an implementation-slice-level decision the source designs themselves already deferred, not a defect in the shared semantics stated above.

## 13. Non-normative source material (evidence only — not authoritative on its own)

- PR #257, `ded7cf9f558bd7960b5eff5836140b1e15255b9a`: `docs/superpowers/specs/2026-08-12-zena-one-page-management-control-tower-design.md`, `docs/superpowers/specs/2026-08-12-zena-contract-finance-one-page-control-design.md`, `docs/superpowers/specs/2026-08-12-zena-service-line-taxonomy-design.md`.
- PR #245, `cd8b79d861f4c1bae5278b6c57f29cd14e505594`: `docs/superpowers/specs/2026-08-07-project-treasury-cashflow-design.md`.
- Issue #248 (original body + 3 Owner-authored amendment comments) and Issue #244 (original body, no comments).
- Current-`main` reconciliation evidence gathered 2026-08-14/15 against `d0d89e84a858e8038e99ffbbf48e536ee297d8e0` (file:line citations throughout §§2–7 above).

These four sources remain **KEEP_AS_ACTIVE_DESIGN_SOURCE** (per Owner Gate 1 decision) — useful for implementation-slice detail (KPI lists, exact field names, UX copy, threshold proposals) that this document deliberately does not restate. They are not release authority and do not, by themselves, authorize any implementation.

## 14. Recommended implementation-slice decomposition (non-normative roadmap — each entry requires its own Work ID and Gate 1→2→3 lifecycle; order may be revised if repository dependencies justify it)

1. Service-Line Taxonomy & Semantics Audit
2. Shared Project Health Read Model + Shared Commercial/Financial Read Semantics (formalizes existing `ProjectAnalyticsController`/`BusinessKpiService`/`ReportPageController::cashflow` logic)
3. CRM Classification UX & Gates (includes fixing the `architecture`-default conflict, §12 item 1)
4. Opportunity→Project Propagation & Project Classification UX
5. Quote Scope Snapshot
6. Portfolio Membership Migration
7. Commercial & Contract Control + Finance Control (company-wide)
8. Resource Control
9. Project OPPM (Issue #248)
10. Operations Control Tower
11. Project Treasury (independent/parallel-capable; its own Gate 2 must resolve §12 item 2)
12. Legacy taxonomy retirement (only after all consumers migrated)

## 15. Implementation-vs-design matrix (evidence appendix)

| Area | Status | Evidence |
|---|---|---|
| CRM Lead service intent | DESIGN_ONLY_NOT_IMPLEMENTED | No field on `Lead` model |
| Opportunity classification | PARTIALLY_IMPLEMENTED + active conflict | `Opportunity.php:87-90`, default `architecture` |
| Opportunity stage gates (service-line-aware) | DESIGN_ONLY_NOT_IMPLEMENTED | 14-stage pipeline exists; classification gating does not |
| Quote revisioning mechanism | SUPPORTED_BY_CURRENT_IMPLEMENTATION | `Quote.php` revision_no + TRANSITIONS |
| Quote scope/service snapshot | DESIGN_ONLY_NOT_IMPLEMENTED | No such columns on `quotes`/`quote_line_items` |
| WON→Project conversion path | SUPPORTED (mechanism) / DESIGN_ONLY (classification propagation) | `OpportunityController.php:339-416,422-576` |
| Project classification schema | DESIGN_ONLY_NOT_IMPLEMENTED | `Project.php:44-59`, no classification column |
| Portfolio membership | DESIGN_ONLY_NOT_IMPLEMENTED | Zero "portfolio" hits repo-wide |
| Contract lifecycle field | SUPPORTED_BY_CURRENT_IMPLEMENTATION | `Contract.php:100-106`, not overloaded |
| Contract attention | DESIGN_ONLY_NOT_IMPLEMENTED | Only a Project-level ad hoc analog exists |
| PaymentCertificate/retention/advance | SUPPORTED_BY_CURRENT_IMPLEMENTATION | `PaymentCertificate.php`, `Contract.php:46-64` |
| Receivables aging | PARTIALLY_IMPLEMENTED | `BusinessKpiService.php:59-104` |
| Company cashflow | SUPPORTED_BY_CURRENT_IMPLEMENTATION | `ReportPageController.php:44-119` |
| Project financial health | DESIGN_ONLY_NOT_IMPLEMENTED | No such service exists |
| OPPM / Control Tower / Portfolios | DESIGN_ONLY_NOT_IMPLEMENTED (100%) | Zero code hits repo-wide |
| Today Workspace overlap | NO CONFLICT | Confirmed clean separation |
| Project Treasury (all of #245) | DESIGN_ONLY_NOT_IMPLEMENTED (100%) | Zero `Treasury`/`Wallet`/`Ledger` hits repo-wide |
