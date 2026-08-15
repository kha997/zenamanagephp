---
work_id: GAP-037
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-037/02-design.md
---

# GAP-037 — Project Treasury: Architecture Decisions A–D (Gate 2 Investigation)

**Status:** Gate 1 approved (2026-08-16, 2 rounds). Gate 2 investigation — options and recommendations only. **No option in this document is authorized until the Owner picks one.** No migration, model, controller, service, route, or UI exists or is created by this document.

**Objective:** answer, with options and a recommendation for each, the 4 architecture decisions Gate 1 bound Gate 2 to resolve, so that whichever option the Owner picks can be turned into a concrete schema proposal in a later Gate 2 revision — never proposing that schema here.

**Authority:** subordinate to `docs/superpowers/specs/2026-08-15-zena-one-page-management-canonical-semantics.md` §§7–8 (canonical, Owner-approved, merged) and to Owner Gate 1 for GAP-037. PR #245 (`cd8b79d861f4c1bae5278b6c57f29cd14e505594`) is read throughout as non-normative design evidence only — its own decisions (§17 of that design) are cited as context, never as authority.

---

## 0. Runtime facts this analysis is built on (no new audit — carried from Gate 1)

| Source | What it records | Payment/cash state? | Feeds into |
|---|---|---|---|
| `ContractPayment` (`app/Models/ContractPayment.php`) | Client → company payments against a Contract | Yes — real `status` (planned/paid/overdue) + `paid_at` | `ReportPageController::cashflow()` `thu` (correctly cash-basis) |
| `ContractExpense` (`app/Models/ContractExpense.php:33-40`) | Manual non-material cost (labor/subcontractor/design_outsource/misc) | No — no `status`/`paid_at` field at all | `ReportPageController::cashflow()` `chi` (incorrectly treated as cash-basis) |
| `MaterialReceiptLine` (via `Api\ContractController::costSummary()`, `ContractController.php:268-306`) | Material cost at receipt (`unit_cost` × `quantity_received`) | No — no payment state | Nothing else; not combined with `ContractExpense` anywhere |
| `Component.actual_cost` → `Project.actual_cost`/`budget_actual` (`app/Models/Component.php:39-138`, `src/CoreProject/Listeners/ProjectCalculationListener.php:58-162`, `app/Models/Project.php:419-436`) | A component-level cost figure, auto-rolled up to Project on change via `ComponentCostUpdated`/`EventBus`, or on demand via `recalculateActualCost()` | No — no payment state, and no stated business meaning (progress-management cost vs. financial incurred cost is undetermined) | Nothing financial currently reads it |

No table in current `main` records "cash actually paid out" for any project expense. This is the actual gap Treasury exists to close — not a missing UI, a missing fact.

---

## A. Cost authority

**Question Gate 1 bound Gate 2 to answer:** what is the canonical source (or composition rule) for "cost incurred," across `ContractExpense`, `MaterialReceiptLine`-derived cost, `Component.actual_cost`, `Project.actual_cost`/`budget_actual`, and a future Treasury ledger?

### Option A1 — Treasury becomes the sole cost authority
Retire `ContractExpense` as a system of record; every cost entry (manual and material) is entered directly as a Treasury `financial_documents`/`ledger_entries` row. `Component`/`Project` cost rollup is redefined as purely progress-tracking, decoupled from any financial claim.
- **Pro:** one source of truth going forward.
- **Con:** requires migrating existing `ContractExpense` data and every UI/report that reads it; large blast radius for a first release; conflicts with the "no destructive migration in the first slice" principle already established for the Service-Line taxonomy work and implicit in SSOT §10.

### Option A2 — Treasury as a pure read-aggregator
Treasury does not own cost data at all. It reads and displays `ContractExpense` + material cost as-is; its own ledger only ever records cash movements, never cost entries. A `financial_documents` "expense" row is not a cost record — there is no such row type in this option.
- **Pro:** zero migration risk; keeps existing systems of record exactly as they are.
- **Con:** contradicts PR #245's own design (design evidence, not binding, but a real signal of business intent) which explicitly wants a `document_type: expense` ledger row with an approval workflow (draft→submitted→approved→posted) — that workflow has no home in this option.

### Option A3 — Treasury owns the cash side of a cost event; cost data stays where it is (recommended)
A Treasury `ledger_entries` row of type "expense" (cash-out) is only ever created **when money actually moves**, and it **must reference** the originating `ContractExpense` or `MaterialReceiptLine` record it is paying down (a `linked_source_type`/`linked_source_id` pair, or explicit `no_linked_source: true` for a genuinely new manual cash-out not yet tied to a recorded cost, e.g. petty cash). `ContractExpense` and `MaterialReceiptLine` remain the systems of record for **cost incurred**; Treasury becomes the system of record for **cash paid against that cost**. `Component`/`Project` cost rollup is excluded entirely (see §A.4 below) — not classified, not migrated, not read.
- **Pro:** no data migration; respects PR #245's approval-workflow intent (the workflow now gates "is this expense approved to be paid," not "is this expense real"); the link field is exactly the no-double-posting mechanism (§C).
- **Con:** requires Gate 2 (whichever revision proposes schema) to define the link field precisely enough that it can't silently be left null and become a second untracked cost entry.

### A.4 — What to do about `Component`/`Project` cost rollup
This is the piece Gate 1 Round 1 found missing. Two sub-options:

- **A4-a (recommended): explicit non-goal for the first Treasury release.** GAP-037's eventual schema proposal states, as a binding exclusion, that Treasury never reads from or writes to `Component.actual_cost`, `Project.actual_cost`, or `Project.budget_actual` in its first release. The semantic question — is this figure planning/progress cost, financial incurred cost, or a manual rollup with no defined meaning — is real, but resolving it is not on Treasury's critical path, and forcing an answer under Treasury's own schema deadline risks a wrong answer driven by expediency rather than evidence. It becomes its own follow-up investigation (could be scoped inside a later Treasury revision, or as its own small Work ID) once someone actually needs Component/Project cost data used for a financial decision.
- **A4-b: resolve it now as part of GAP-037.** Audit every consumer of `Component.actual_cost`/`Project.actual_cost` (UI, reports, exports) to determine actual usage, then classify it. Higher upfront cost, but removes the open question permanently. Risk: this audit could itself be nontrivial (WBS/progress features are used across the Project surface, not just finance), and there's no evidence yet that anyone reads these fields for a financial purpose — spending Gate 2 effort here before schema work starts may not be time well spent relative to A4-a.

**Recommendation:** A3 for cost/cash split, A4-a for Component/Project rollup (defer, don't resolve, explicitly exclude).

---

## B. Cash authority

**Question:** does the Treasury ledger become canonical for *all* project cash movement, or only a subset — and what is its relationship to `ContractPayment`, payments against `ContractExpense`, supplier/material payments, owner contribution, internal transfers, and advances/settlements?

### Option B1 — Treasury canonical for all cash, including client payments
`ContractPayment` is migrated into or superseded by Treasury `ledger_entries` (funding type).
- **Con:** `ContractPayment` already backs live functionality — `BusinessKpiService::outstandingDebt()` (receivables aging) and `ReportPageController::cashflow()`'s `thu` side both depend on it, and both are correct today. Migrating this in Treasury's first release is high blast-radius for no immediate business benefit, and directly contradicts canonical SSOT §7.2: Treasury "is not... a duplicate of the Contract payment lifecycle."

### Option B2 — Treasury canonical for everything ContractPayment doesn't already cover (recommended)
`ContractPayment` stays canonical for client → company payments (money in via contract milestones) exactly as today — Treasury does not write to it, does not re-post it, and does not duplicate its lifecycle. Treasury becomes canonical for every cash movement `ContractPayment` was never designed to track:

| Cash movement | Canonical source under B2 |
|---|---|
| Client payment against a Contract milestone | `ContractPayment` (unchanged) |
| Payment of a recorded `ContractExpense` to a supplier/subcontractor/labor | **Treasury** (new — `ContractExpense` has no payment state today) |
| Payment for received material (against `MaterialReceiptLine`) | **Treasury** (new — no payment state today) |
| Owner contribution / project financing | **Treasury** (new — no existing model at all) |
| Internal transfer between wallets/accounts | **Treasury** (new) |
| Advance issuance and settlement | **Treasury** (new) |

This matches what PR #245's own 12-table design already implies by omission — it never mentions `ContractPayment` anywhere in its ~628 lines, meaning it was designed for exactly this complementary space, not as a replacement.
- **Pro:** zero migration; matches SSOT §7.2 exactly; matches PR #245's actual (if implicit) scope.
- **Con:** two cash systems coexist long-term (`ContractPayment` for client-in, Treasury for everything else) — a future company-wide Finance Control slice has to read both. This is explicitly acceptable: SSOT §6.2 already assigns that integration job to Finance Control, not to Treasury.

**Recommendation:** B2.

---

## C. Economic-event / no-double-posting rule

**Question:** for each existing source, is it an input, a projection, a reconciliation target, referenced evidence, or a source Treasury replaces — and how is a single economic event prevented from being posted twice?

| Source | Role under the A3/B2 recommendation | Mechanism preventing double-posting |
|---|---|---|
| `ContractPayment` | **Referenced evidence / input** — Treasury never writes here | N/A — Treasury has no write path to this table |
| `ContractExpense` | **Referenced evidence** for the cost side of a cash-out posting | Every Treasury cash-out `ledger_entries` row of type "expense" carries a mandatory link to the `ContractExpense` row it settles (or an explicit "no linked source" flag for a genuinely new manual entry) |
| `MaterialReceiptLine` | **Referenced evidence** for the cost side of a cash-out posting | Same link mechanism as above |
| `Component.actual_cost`/`Project.actual_cost`/`budget_actual` | **Out of scope entirely** (§A.4, option A4-a) | Treasury code introduces no call path to `Project::recalculateActualCost()` or `ProjectCalculationListener::recalculateProjectCost()`, and no Treasury write touches these three fields, full stop — this is a binding exclusion carried into any future schema proposal, not merely a recommendation |

**Binding rule (regardless of which cost/cash option the Owner ultimately picks):** Treasury must never auto-synchronize `Project.actual_cost` until a *separate, explicit* decision determines whether doing so would duplicate or overwrite the existing Component/Project rollup — this is Gate 1's own binding instruction, restated here as a hard constraint on every option above, not something any option is free to relax.

---

## D. Company cashflow integration

**Question:** how does Treasury relate to `ReportPageController::cashflow()`, without building a second calculator and without inheriting that controller's existing `ContractExpense`-as-cash-paid mislabeling?

**Recommendation:** Treasury does not touch `ReportPageController::cashflow()` at all — not to fix it, not to extend it, not to read from it. That controller keeps functioning exactly as it does today throughout GAP-037's entire lifecycle. Canonical SSOT §6.3 already assigns the job of deciding what to do about the `chi` side's accrual-vs-cash problem to a future **Finance Control** slice's own Gate 2 — GAP-037 explicitly declines to pre-empt that decision, consistent with the Gate 1 exclusion list (no edits to `ReportPageController::cashflow()`).

What GAP-037 *does* produce, once Treasury is canonical (per SSOT §7.3's layering), is a new, correct source of "cash actually paid out" data that a *future* Finance Control slice can choose to consume instead of `ContractExpense`. Until then, per SSOT §7.3, any surface (including a future Project OPPM Commercial & Finance block) must mark Treasury-derived cash figures as **unavailable**, never zero, if Treasury hasn't shipped yet — and must not silently blend Treasury figures with `ContractExpense`-derived ones once it has, without an explicit reconciliation decision.

---

## Summary recommendation (for Owner decision — not self-approved here)

| Decision | Recommended option |
|---|---|
| A. Cost authority | A3 — Treasury owns cash-out postings referencing existing cost records; cost data stays in `ContractExpense`/`MaterialReceiptLine` |
| A.4 Component/Project rollup | A4-a — explicit non-goal for Treasury v1; deferred, not resolved |
| B. Cash authority | B2 — Treasury canonical for everything `ContractPayment` doesn't already cover; `ContractPayment` untouched |
| C. No-double-posting | Mandatory link field on every Treasury cash-out entry; hard exclusion on any Component/Project write |
| D. Cashflow integration | No changes to `ReportPageController::cashflow()`; that decision belongs to a future Finance Control slice |

**Migration implications of the recommended set:** none. Every recommended option is additive — new Treasury tables plus a link field pattern, zero changes to existing schema, zero data migration, zero UI/report changes. This is a direct consequence of choosing A3/B2 over A1/B1.

**No-double-count guarantee under the recommended set:** every economic event has exactly one canonical source. Cost-incurred events stay in `ContractExpense`/`MaterialReceiptLine`. Cash-in events from clients stay in `ContractPayment`. Every other cash event becomes canonical in Treasury. The only new relationship introduced is a reference link from a Treasury cash-out row back to the cost record it settles — a link, not a second copy.

If the Owner prefers a different option for any of A/B — for example A1 (full migration) or B1 (Treasury absorbs `ContractPayment`) — that changes the migration-risk profile substantially and would need to be stated explicitly before any schema proposal is drafted.
