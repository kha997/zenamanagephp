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
A Treasury `ledger_entries` row of type "expense" (cash-out) is only ever created **when money actually moves**, and it **must reference** the originating `ContractExpense` or `MaterialReceiptLine` record(s) it is paying down (see §A.5 for the cardinality this reference must support). `Component`/`Project` cost rollup is excluded entirely (see §A.4 below) — not classified, not migrated, not read.

**Corrected per Owner Gate 2 Round 1 — no generic unlinked-expense escape hatch.** An earlier draft of this option allowed an unlinked cash-out ("no linked source") for any "genuinely new manual entry," using petty cash as the example. That framing was too broad: it would let Treasury silently become a cost authority any time a link was simply omitted, defeating the entire point of A3. The corrected rule:
- **Unlinked cash-out entries are valid ONLY for movement types that are not, by definition, a cost/expense settlement** — internal transfer, advance issuance, owner contribution/financing, and equivalent non-cost movements (per PR #245 §5.1.10/§17: these explicitly don't touch project expense).
- **If a cash-out is genuinely an expense/cost settlement and no cost record exists yet**, Treasury must not post it as an unlinked cash-out. The canonical cost record (`ContractExpense` or a `MaterialReceiptLine`-backed cost, per the existing cost-entry paths) must be created or identified as part of the **same atomic business operation** as the cash-out posting — e.g. "record and immediately settle a new expense" is one transaction that creates the `ContractExpense` row and the linked Treasury cash-out row together, never a cash-out alone with cost implied. This is a domain rule, binding on any future schema proposal; exact transaction/service boundaries are schema-proposal-time detail, not decided here.
- **Petty cash**, if it needs to exist as a concept at all, is therefore either (a) modeled as its own non-cost-movement type (like an advance) until a real cost record settles it, or (b) requires the atomic cost-record-creation path above — it is never an example of a valid unlinked "expense."

- **Pro:** no data migration; respects PR #245's approval-workflow intent (the workflow now gates "is this expense approved to be paid," not "is this expense real"); the link mechanism (now precisely scoped) is exactly the no-double-posting mechanism (§C), with no silent-omission gap.
- **Con:** requires Gate 2 (whichever revision proposes schema) to define the link/allocation mechanism precisely enough to support the cardinality in §A.5, not just a single foreign key.

### A.5 — Settlement cardinality (domain requirement, not schema — added per Owner Gate 2 Round 1)
The relationship between a Treasury cash-out and the cost record(s) it settles must, at the domain level, support all of the following — this constrains any future schema proposal; it does not itself pick tables/columns:
- **One cost → multiple partial payments.** A single `ContractExpense`/`MaterialReceiptLine`-derived cost may be settled across several Treasury cash-outs over time (e.g. a large subcontractor invoice paid in installments).
- **One payment → multiple cost records.** A single Treasury cash-out may settle several `ContractExpense`/`MaterialReceiptLine` rows at once (e.g. one bank transfer covering several material receipts from the same supplier).
- **Partial allocation.** A cash-out amount may only partially cover a given cost record's remaining balance, and the remainder must stay trackable as still-outstanding.
- **Reversal/replacement with allocation auditability.** If a cash-out is reversed or replaced (per PR #245's existing immutable-posting/reversal design, §5.8/§8), every allocation it made to a cost record must be reversible/traceable back to the specific allocation, not just to the cash-out as a whole.

**Binding constraint on schema:** a single `linked_source_type`/`linked_source_id` foreign key pair on the cash-out row — a strict 1:1 relationship — cannot represent any of the four cases above and must not be the architecture locked in at schema-proposal time. The eventual schema needs an explicit allocation/settlement concept between cash-outs and cost records (many-to-many, with a per-allocation amount and its own auditable state), not a single reference field. This document does not name that concept's tables/columns — only that a 1:1 link is insufficient and must not be assumed.

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

### B2-T — ContractPayment ↔ Treasury funding traceability extension of B2 (added per Owner Gate 2 Round 1; renamed from "§B.1" and invariant corrected per Owner Gate 2 Round 2 — **this is an extension of recommended Option B2, not Option B1; Option B1 remains not recommended and is not part of this extension in any way**)

B2's clean partition ("Treasury owns what `ContractPayment` doesn't") is not the whole story once a client/investor payment needs **route, custody, or wallet traceability after it's received** — e.g. a client pays into a company account (A), which routes through an intermediary (C) before landing in a project-specific wallet (Y). This is a real case PR #245's own `payment_routes`/`payment_route_legs` design already anticipates (§5 of that design), and it is a `ContractPayment`-sourced event, not a Treasury-originated one — B2 as originally stated didn't say what Treasury is allowed to record about it.

**Resolved relationship (binding, not deferred to a schema revision):**
- `ContractPayment` remains the sole authority for **whether and how much was commercially paid** — its `amount`/`status`/`paid_at` are the only place that fact is asserted. Treasury never re-asserts "the client paid $X"; it only ever asserts facts about *where that already-paid money currently sits or has moved through*.

**Corrected conservation invariant per Owner Gate 2 Round 2.** An earlier draft used `sum(route legs) <= ContractPayment.amount`. That is wrong: a single economic payment can move through several **serial** legs (A→C, then C→Y) — each leg carries the *same* $100 moving one hop further, not a fresh $100 each time. Summing leg amounts for a $100 payment routed A→C→Y would wrongly total $200 and either falsely flag a violation or (worse, if used as a cap) let $200 worth of allocation slip through a check meant to bound $100. Route legs are **movement history**, not additive money. The corrected model distinguishes three separate things that must never be conflated:

1. **Commercial fact (asserted once):** `ContractPayment.amount`/`status`/`paid_at`. Never re-derived or re-summed from Treasury data. The investor/client-paid metric is read from here, and only here — never assembled by summing Treasury route or leg records.
2. **Economic allocation (the conservation boundary):** a `ContractPayment` may have all or part of its amount allocated to one or more Treasury routes (mirroring the cost-side allocation pattern in §A.5 — this is the same kind of relationship, not a new one). The constraint is: **`sum(economic allocation across all Treasury routes linked to a given ContractPayment) <= ContractPayment.amount`.** This is checked at the *allocation* level — how much of the commercial payment has been assigned to route(s) for tracking — never at the leg level.
3. **Movement history (not conserved, not summed):** within a route, each serial leg (A→C, C→Y, …) records the same allocated amount moving one custody hop further. Legs are a log of *where the allocated amount has been*, not a ledger of *additional* amounts — they are never summed across hops to compute a total, and never used to enforce any upper bound. A route with legs A→C=100 and C→Y=100 represents one $100 allocation that has moved twice, not $200 moved once.

**Additional requirements carried from Round 1, restated against the corrected model:**
- **Route in-transit tracking:** Treasury may show a `ContractPayment` marked `paid` as still "in transit" through custody (e.g. received at company account A, not yet at project wallet Y) — current custody/location state is derived from the *latest* leg per route/allocation, not from a sum of legs. This does not contradict `ContractPayment.status = paid`, which only asserts "the company has been paid," not "the money has reached its final custody destination."
- **Current custody must reconcile to allocated amount, accounting for partial routing and reversal/refund.** At any point, the sum of *current* custody-state amounts across all of a `ContractPayment`'s routes/allocations (not the sum of historical legs) must equal the total amount actually allocated to Treasury for that `ContractPayment`, net of any reversed/refunded portion. A leg reversal reduces the in-transit/settled amount at its point in the chain; it does not touch `ContractPayment.amount` itself (a commercial-level correction to the payment fact, if it ever happens, is `ContractPayment`'s own concern, not Treasury's).
- **Project-wallet receipt/balance:** computed from Treasury's own route-leg/wallet ledger (current custody state per allocation, §3 above), which is authoritative for *location*, while `ContractPayment` stays authoritative for *commercial fact*. These are two different questions about the same economic event, not two competing sources of the same fact.
- **No second funding economic event:** a `ContractPayment`-sourced Treasury route/allocation record is never itself a `financial_documents`/`funding` document independent of the `ContractPayment` it traces — it is a custody projection of that one commercial event, linked, not duplicated.

**Recommendation:** B2, extended with B2-T's binding funding-traceability relationship above — not deferred to a later schema revision, per Owner instruction. **Option B1 (Treasury absorbs `ContractPayment`) is not selected and is unrelated to this extension.**

---

## C. Economic-event / no-double-posting rule

**Question:** for each existing source, is it an input, a projection, a reconciliation target, referenced evidence, or a source Treasury replaces — and how is a single economic event prevented from being posted twice?

| Source | Role under the A3/B2 recommendation | Mechanism preventing double-posting |
|---|---|---|
| `ContractPayment` (the payment fact itself: amount/status/paid_at) | **Referenced evidence / input — never re-written or re-asserted.** Treasury has no write path to this table and never posts a second "amount paid" fact for the same event. Treasury *may* record custody/route/wallet facts *about* a `ContractPayment`-sourced event (§B2-T) — that is a different kind of fact (location, not commercial payment) | `sum(economic allocation across all Treasury routes linked to a ContractPayment) <= ContractPayment.amount` — an allocation-level check, never a sum of historical route-leg amounts (§B2-T) |
| `ContractExpense` | **Referenced evidence** for the cost side of a cash-out posting | Every Treasury expense-settlement cash-out carries a mandatory allocation to one or more `ContractExpense` rows (§A.5) — an unlinked cash-out is valid only for a non-cost movement type (transfer/advance/owner financing), never for an expense settlement (§A.3 correction) |
| `MaterialReceiptLine` | **Referenced evidence** for the cost side of a cash-out posting | Same allocation mechanism as above |
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
| A. Cost authority | A3 — Treasury owns cash-out postings allocated to existing cost records; cost data stays in `ContractExpense`/`MaterialReceiptLine` |
| A.4 Component/Project rollup | A4-a — explicit non-goal for Treasury v1; deferred, not resolved |
| A.5 Settlement cardinality | Domain requirement for many-to-many allocation with partial/reversal auditability — no 1:1 link |
| B. Cash authority | B2 — Treasury canonical for everything `ContractPayment` doesn't already cover; `ContractPayment` untouched. **Option B1 (Treasury absorbs `ContractPayment`) is NOT selected.** |
| B2-T. Funding traceability extension of B2 | Treasury may record route/custody/wallet facts about a `ContractPayment`-sourced event, always allocation-linked (conservation checked at the allocation level, never by summing route legs), never re-asserting the payment fact itself |
| C. No-double-posting | Mandatory allocation on every expense-settlement cash-out; unlinked postings restricted to genuinely non-cost movements only; hard exclusion on any Component/Project write |
| D. Cashflow integration | No changes to `ReportPageController::cashflow()`; that decision belongs to a future Finance Control slice |

**Migration implications of the recommended set — corrected per Owner Gate 2 Round 1.** No migration of *existing* data or tables is required to adopt this recommended set — `ContractPayment`, `ContractExpense`, `MaterialReceiptLine`, `Component`, and `Project` are all read-only from Treasury's perspective (or, for Component/Project, not touched at all) and none of their current rows change. This does **not** mean Treasury requires no migrations at all: implementing Treasury still requires additive schema migrations for its own new tables (the ledger/wallet/allocation/route model from PR #245, revised per §A.5/§B2-T above) — those migrations are simply not designed, named, or proposed in this Gate 2 revision. A concrete schema proposal is a distinct, later step.

**No-double-count guarantee under the recommended set:** every economic event has exactly one canonical source. Cost-incurred events stay in `ContractExpense`/`MaterialReceiptLine`. Commercial payment facts from clients stay in `ContractPayment`. Every other cash event becomes canonical in Treasury. The only new relationships introduced are (a) an allocation between a Treasury cash-out and the cost record(s) it settles, supporting the cardinality in §A.5, and (b) an allocation between a Treasury route/custody record and the `ContractPayment` it traces, conserved at the allocation level per §B2-T — both are references to an existing fact, never a second copy of that fact.

**Recommended set, stated unambiguously (per Owner Gate 2 Round 2):** A3 + A4-a + A.5 (cost side) / B2 + B2-T funding-traceability extension (cash side) / C / D. **Option B1 (Treasury absorbs `ContractPayment`) is explicitly NOT selected** and is unrelated to the B2-T extension despite the superficially similar name that an earlier draft used ("§B.1") — that name has been retired specifically to remove this ambiguity.

If the Owner prefers Option A1 (full cost migration) or Option B1 (Treasury absorbs `ContractPayment`) instead of the recommended set, that changes the migration-risk profile substantially and would need to be stated explicitly before any schema proposal is drafted.
