# GAP-105 — Treasury: BOQ link, fund chains, investor receivable — discovery evidence

Date: 2026-11-03. Base: `main` at `9e758cb89f8a398fc14868601ac9c5bc23fc2a29`.

Sources read:
- The canonical Treasury business spec `docs/superpowers/specs/2026-08-07-project-treasury-cashflow-design.md`, on branch
  `docs/project-treasury-spec`; read on that branch, not merged to `main`.
- The approved schema design (`docs/owner-decisions/GAP-037/02-design-v17.md`).
- The S6 readiness audit (`docs/audits/2026-10-27-gap-097-treasury-s6-dashboard-reports-readiness.md`) and GAP-097
  exclusions.
- The code on `main`.

Method: static reading; no production data available.

## 1. Why these three were left out

GAP-097 (S6 dashboard and reports) excluded the following, because each needs schema or rules not yet decided:
- "BOQ and budget variance";
- "fund chains";
- "investor receivable against contract value".

The Owner chose this group on 2026-11-03.

## 2. BOQ link

Canonical spec requirements:
- Q4 "who spent the money, for which cost item, and for which BOQ line";
- Q10 "overspending against budget or BOQ";
- a document field `boq_id` / `boq_line_item_id`;
- rule §12.5 "BOQ item must belong to the same project";
- register filter "BOQ item";
- alert "BOQ/budget overrun";
- report 7 "Expense by BOQ and BOQ line".

What exists:
- `boqs`: `tenant_id`, `project_id`, `code`, `name`.
- `boq_line_items`: `tenant_id`, `boq_id`, `component_id` (nullable), `code`, `name`, `quantity`, `unit`, `unit_price`
  (nullable, added 2026-07-14).
- Web pages `/app/boqs` with `boq.view` and `boq.create`.
- A line's budget is `quantity × unit_price` only when `unit_price` is set.
- **Treasury has no BOQ column.** `treasury_financial_documents` has no `boq_id` / `boq_line_item_id`; the GAP-037
  schema did not include them. `treasury_cost_settlement_allocations` points to `ContractExpense` or
  `MaterialReceiptLine`, not to BOQ.
- Linking a Treasury expense to a BOQ line therefore needs a **migration** (nullable FK column(s), same-project rule)
  and a field on the expense form.

Open points for Gate 2:
1. Link at document level (one expense → one BOQ line) or allow a split across lines.
2. Overrun basis: `unit_price` is often empty, so it must be no_data when budget is unknown (SSOT §8.3).
3. Whether advance settlements and cost allocations carry the BOQ line too.

## 3. Fund chains

The canonical spec defines a Fund Chain as "a traceability grouping showing how funding was subsequently transferred and
spent". It also lists a register filter "fund chain" and report 13 "Fund chain trace report".

What exists:
- `treasury_fund_chains`: `tenant_id`, `project_id`, `chain_reference`, `description`.
- `treasury_fund_chain_members`: exactly one of `member_financial_document_id` / `member_payment_route_id`, with unique
  member indexes, so a document or route belongs to at most one chain. The same-project rule is in the GAP-037
  invariants table.
- Models `TreasuryFundChain` and `TreasuryFundChainMember` exist.
- No service, route, UI or test creates a chain ("table exists, unused", GAP-097 audit §3). **No migration is needed**
  for the basic feature.

Open points for Gate 2:
1. Who creates a chain (manual grouping by an accountant, or automatic from routes / transfers).
2. Whether posted or reversed documents can be added or removed later. This touches the "posted cannot be edited" rule,
   because chain membership is metadata, not the ledger.
3. Which permission to use. The seeded `treasury.*` permissions include `view`, `manage_wallets`, `adjust` and others;
   none is named for chains.

## 4. Investor receivable

The canonical spec asks:
- Q1 "How much has the investor paid?";
- dashboard card "Contract/budget value where available";
- report 2 "Investor payments and outstanding receivable summary".

What exists, as **two separate sources of "money received"**:

1. **Contract lifecycle:** `contracts.total_value`, with types design / construction / other (contracts with the
   client). `ContractPayment` has statuses, and paid instalments are money received.
   - `BusinessKpiService::outstandingDebt()` already ages unpaid `ContractPayment` rows company-wide.
   - GAP-103 shows contract value and cash received per project.
2. **Treasury:**
   - `investor_funding` is posted `funding` documents.
   - `contract_payments_arrived` is completed payment routes linked to a `ContractPayment`
     (`TreasuryBalanceService::projectSummary`).
   - Funding documents are not tied to a contract.

SSOT OWN-2026-009 §7 / GAP-037 decision: Treasury is **not** a duplicate of the Contract payment lifecycle, and
`ContractPayment` stays the receivable owner.

Adding "contract value − investor funding" naively would mix the two sources. A funding that is also recorded as a paid
`ContractPayment` would be counted twice, and a funding unrelated to a contract would reduce the wrong receivable.

Open points for Gate 2 — choose one definition:
- Receivable from `ContractPayment` only, shown inside Treasury as a read-out.
- Treasury funding matched to contracts, which needs a contract link: a schema change.
- A side-by-side reconciliation view.

SSOT §8 applies to every option: no netting into profit, and missing contract value is no_data.

## 5. Shared constraints

- Tenant and project isolation; Treasury policy (`treasury.view-project`, project membership or `treasury.all_projects`).
- Posted Treasury documents are immutable; any new link on a posted document must not change ledger amounts.
- Reports export as CSV through the existing S6 pattern.
- MySQL CHECK / composite-FK conventions from GAP-037/038 for any new column.

## 6. Out of scope

- Evidence attachments.
- Period lock and bank statement import.
- Configurable thresholds and per-advance deadlines.
- Changing `ContractPayment` lifecycle or receivables aging.
- BOQ authoring changes.
- Accounting profit.
- Deployment.
