# Project Treasury & Cashflow Management — Design Specification

**Document ID:** ZENA-PTCM-001  
**Status:** Approved for implementation planning  
**Repository:** `kha997/zenamanagephp`  
**Scope:** Project treasury, cashflow, wallets, approvals, advances, reconciliation, audit, BOQ linkage, dashboard and reports  
**Explicitly out of scope for this phase:** AI, financial chat, automated bank feeds, statutory accounting, VAT accounting, VAS chart of accounts, tax declarations

---

## 1. Purpose

Build a project-centred treasury and cashflow system for ZenaManage that can trace money across complex real-world construction flows without confusing internal transfers with income or expense.

The system must answer, at any time:

1. How much has the investor paid for a project?
2. Through which route did the money arrive?
3. Which wallet currently holds the money?
4. Who spent the money, for which cost item, and for which BOQ line?
5. Which expenses are pending approval, approved, posted, reconciled, or missing evidence?
6. How much money has the owner personally injected into the project?
7. How much remains held by each engineer or cashier?
8. How much remains payable to labour, suppliers, subcontractors, or other parties?
9. Whether a payment route such as `A -> C -> Y` has been counted once or incorrectly duplicated.
10. Whether the project is overspending against budget or BOQ.

This module is not a statutory accounting package. It is the operational single source of truth for project cashflow.

---

## 2. Existing repository context

The implementation must fit the current repository rather than create a parallel application.

Current relevant characteristics observed in the repository:

- Laravel 12 on PHP 8.2.
- MySQL persistence.
- ULID primary keys are widely used.
- Multi-tenant isolation exists through `tenant_id` and `TenantScope`.
- `Project` already stores summary fields such as budget and actual cost.
- BOQ models already exist.
- Approval, audit log, policy, service and workflow foundations already exist.
- The repository contains `app`, `src`, models, policies, repositories, services, routes, tests and CI guardrails.

Claude Code must inspect and follow the canonical domain ownership and routing conventions before creating files. Do not assume the older developer documentation is fully current; the code and `composer.json` are authoritative.

---

## 3. Business actors and terminology

The following letters describe the original business scenario only. Production data must use actual names and IDs.

| Symbol | Meaning |
|---|---|
| A | Current investor/client funding the project |
| B | Contractor/project delivery organisation; operationally represented by the project and tenant |
| C | Previous investor, intermediary company, billing route or payment intermediary |
| D | Labour team, workers or labour subcontractor |
| E | Supplier, service provider or subcontractor |
| X | Business owner/director personally receiving or paying project money |
| Y | Company bank account, company cash fund or legal entity wallet |
| Z | Engineer, site manager, cashier or employee temporarily holding project money |

### 3.1 Core concepts

- **Project:** The financial control centre. Every treasury transaction belongs to exactly one project in this phase.
- **Financial Party:** An external or internal counterparty such as investor, intermediary, supplier, labour team, employee or owner.
- **Wallet:** A location or custodian that can hold project money. Examples: owner personal account, company bank account, company cash box, engineer cash wallet.
- **Financial Document:** The business transaction record entered by a user.
- **Ledger Entry:** The immutable posted movement affecting a project wallet or project financial total.
- **Funding:** Money entering the project from an external source or owner contribution.
- **Internal Transfer:** Money moving between project wallets. It does not change project income, expense or total project-held funds.
- **Expense:** Money finally consumed by labour, supplier, subcontractor, tax/fee or another external recipient.
- **Advance:** Money entrusted to a person or wallet for later spending and settlement.
- **Settlement:** Evidence-backed application of an advance to approved expenses, plus any return of unused money.
- **Reconciliation:** Confirmation that a posted transaction matches bank, cash, receipt or other independent evidence.
- **Payment Route:** A linked multi-leg movement such as `A -> C -> Y` representing one economic funding event.
- **Fund Chain:** A traceability grouping showing how funding was subsequently transferred and spent.

---

## 4. Scope

### 4.1 In scope

1. Project wallets.
2. Financial parties.
3. Funding receipts.
4. Multi-leg payment routes.
5. Internal wallet transfers.
6. Expense requests and approvals.
7. Owner self-approval.
8. Advances and settlements.
9. Evidence attachments.
10. Reconciliation.
11. Immutable posting, reversal and adjustment.
12. BOQ and budget linkage.
13. Project treasury dashboard.
14. Project financial timeline.
15. Reports and exports.
16. Permissions and audit.
17. Project actual cost synchronisation from posted expenses.
18. Alerts for overdue advances, unreconciled funding, missing evidence and budget overruns.

### 4.2 Out of scope

1. AI Financial Trace.
2. Conversational financial assistant.
3. OCR or automatic extraction from receipts.
4. Direct bank API integrations.
5. Statutory double-entry accounting.
6. General ledger chart of accounts.
7. VAT declarations and tax reports.
8. Payroll calculation.
9. Inventory costing.
10. Foreign exchange revaluation.
11. Multi-project allocation of a single transaction.
12. Automated invoice issuance.

These can be future phases. Do not add hidden schemas or speculative abstractions for them now.

---

## 5. Non-negotiable domain rules

### 5.1 General rules

1. Every record must include `tenant_id` and be tenant-scoped.
2. Every operational treasury transaction must belong to one `project_id`.
3. All primary keys must follow repository conventions, preferably ULID where canonical.
4. Money must use fixed precision decimal columns, never floating point.
5. Currency for this phase is VND by default. Store `currency_code` for future compatibility but do not implement FX logic.
6. Posted balances are derived from immutable ledger entries. Users must never type or directly edit wallet balances.
7. Draft and rejected documents may be edited. Posted documents may not be edited or deleted.
8. A posted correction requires reversal and replacement or adjustment.
9. Every state transition must be auditable.
10. Internal transfers must never affect project revenue, project expense or profit.

### 5.2 Funding rules

1. Funding entered by X or Z is posted immediately when declared.
2. Immediate posting status is **posted_unreconciled**.
3. It immediately increases:
   - investor paid amount when the source is an investor payment;
   - destination wallet balance;
   - total project-held funds.
4. Reconciliation later changes status to **posted_reconciled** without posting the amount again.
5. Suspected errors are corrected by reversal and replacement, never direct overwrite after posting.
6. Duplicate detection must warn on same project, amount, date, source, destination and reference/evidence combination.
7. Owner contribution is funding but not investor payment and not project revenue. It must be separately reportable as owner-funded/project financing.

### 5.3 Expense rules

1. An expense declared by Z or another employee is not a posted project expense until approved.
2. Expense workflow:
   - draft
   - submitted
   - approved
   - posted
   - reconciled, where applicable
3. Rejection workflow:
   - submitted
   - rejected
   - revised
   - resubmitted
4. Approval and posting may occur in one atomic application service operation.
5. Only posted expenses reduce a wallet balance and increase project actual cost.
6. X is allowed to approve an expense created by X.
7. Self-approval must be explicitly recorded with `approval_mode = self_approval`.
8. Dashboard and reports must separately show self-approved expenses.
9. Other users may not self-approve unless granted an explicit future permission; default is prohibited.
10. Posted expense records cannot be deleted.

### 5.4 Internal transfer rules

1. A wallet transfer is one atomic transaction with two balanced ledger legs.
2. Source wallet decreases and destination wallet increases by the same amount.
3. The project total held funds remains unchanged.
4. Users must not create separate unrelated receive and pay documents for the same transfer.
5. A transfer may optionally create or update an advance relationship when the destination wallet belongs to an employee or engineer.

### 5.5 Payment route rules

For `A -> C -> Y`:

1. This is one economic funding event with multiple route legs.
2. The investor paid total must increase once only.
3. The first leg records payment by A through C.
4. The second leg records actual receipt into Y.
5. A route may be partially completed.
6. The dashboard must show:
   - amount investor paid;
   - amount still held by intermediary C;
   - amount received by project wallets;
   - route variance, if any.
7. Route legs must be linked by one `payment_route_id`.

### 5.6 Advance and settlement rules

1. Money entrusted to Z is an internal transfer and may create an advance balance.
2. The advance balance is not an expense.
3. Approved expenses paid by Z reduce the outstanding advance.
4. Money returned by Z to X or Y also reduces the outstanding advance.
5. The system must show:
   - amount advanced;
   - approved settled expenses;
   - returned cash;
   - outstanding amount;
   - overdue days;
   - missing evidence count.
6. One expense may settle one advance in this phase. Do not implement complex many-to-many allocation unless required by existing code patterns.

### 5.7 Reconciliation rules

1. Posted funding may remain unreconciled.
2. Posted expenses may be reconciled against bank or cash evidence.
3. Reconciliation does not change the economic amount.
4. Reconciliation records who reconciled, when, evidence reference and notes.
5. A reconciled transaction cannot be marked unreconciled without an auditable reversal of reconciliation state.

### 5.8 Audit and correction rules

1. Posted documents and ledger entries are immutable.
2. No hard delete for posted financial records.
3. Reversal creates equal and opposite ledger entries linked to the original.
4. Replacement creates a new document linked to the reversed document.
5. Adjustment must clearly state reason and approver.
6. Every create, submit, approve, reject, post, reconcile, reverse and adjust action must be logged.

---

## 6. Recommended architecture

Create a bounded project finance domain named **ProjectTreasury** or follow the repository’s canonical domain ownership structure after inspection.

Recommended logical components:

1. `FinancialParty`
2. `ProjectWallet`
3. `FinancialDocument`
4. `LedgerEntry`
5. `PaymentRoute`
6. `PaymentRouteLeg`
7. `FundChain`
8. `Advance`
9. `AdvanceSettlement`
10. `ExpenseApproval`
11. `Reconciliation`
12. `FinancialAttachment`
13. `TreasuryAuditEvent` or integration with the existing canonical audit log
14. `ProjectTreasuryService`
15. `LedgerPostingService`
16. `ExpenseApprovalService`
17. `AdvanceSettlementService`
18. `ReconciliationService`
19. `TreasuryDashboardQuery`
20. `FinancialTimelineQuery`

Controllers must remain thin. Business invariants belong in domain/application services and database transactions.

### 6.1 Posting atomicity

All posting operations must run inside a database transaction.

Examples:

- Posting funding creates document state transition plus ledger entry atomically.
- Posting internal transfer creates both source and destination legs atomically.
- Approving expense records approval, posts expense, updates advance settlement and synchronises project cost atomically.
- Reversal creates reversal document and all opposite ledger entries atomically.

### 6.2 Summary fields

The existing `Project` summary fields may be maintained as cached projections for performance, but the ledger is authoritative.

At minimum:

- `Project.actual_cost` or canonical equivalent must equal total posted non-reversed project expenses.
- A command or service must be able to rebuild project financial summaries from ledger entries.
- Tests must verify that cached totals can be reconstructed.

---

## 7. Data model proposal

Claude Code must confirm actual naming conventions and migration ordering before implementation. The following model is normative at the conceptual level.

### 7.1 `financial_parties`

Purpose: counterparties used by treasury without overloading CRM `accounts`.

Required fields:

- `id`
- `tenant_id`
- `party_code`
- `display_name`
- `legal_name` nullable
- `party_type`: investor, intermediary, owner, employee, labour, supplier, subcontractor, authority, other
- `linked_user_id` nullable
- `linked_account_id` nullable
- `tax_code` nullable
- `phone` nullable
- `email` nullable
- `status`
- timestamps
- soft delete if repository convention permits

A party may link to CRM Account or User but remains a separate treasury concept.

### 7.2 `project_wallets`

Required fields:

- `id`
- `tenant_id`
- `project_id`
- `wallet_code`
- `name`
- `wallet_type`: company_bank, company_cash, owner_personal, employee_cash, employee_bank, intermediary_control, other
- `custodian_party_id` nullable
- `custodian_user_id` nullable
- `currency_code` default VND
- `status`
- `allow_negative` default false
- timestamps

Do not persist editable balance.

### 7.3 `financial_documents`

Required fields:

- `id`
- `tenant_id`
- `project_id`
- `document_no`
- `document_type`: funding, internal_transfer, expense, owner_contribution, advance, advance_return, reversal, adjustment
- `status`: draft, submitted, rejected, approved, posted_unreconciled, posted_reconciled, reversed
- `transaction_date`
- `amount` decimal
- `currency_code`
- `source_party_id` nullable
- `destination_party_id` nullable
- `source_wallet_id` nullable
- `destination_wallet_id` nullable
- `payment_route_id` nullable
- `fund_chain_id` nullable
- `advance_id` nullable
- `boq_id` nullable
- `boq_line_item_id` nullable
- `cost_category` nullable
- `description`
- `reference_no` nullable
- `created_by`
- `submitted_by` nullable
- `submitted_at` nullable
- `approved_by` nullable
- `approved_at` nullable
- `approval_mode` nullable
- `posted_by` nullable
- `posted_at` nullable
- `reversed_document_id` nullable
- `replacement_document_id` nullable
- timestamps

### 7.4 `ledger_entries`

Required fields:

- `id`
- `tenant_id`
- `project_id`
- `financial_document_id`
- `wallet_id` nullable
- `entry_type`: wallet_in, wallet_out, project_funding, project_expense, owner_financing, route_in_transit, advance_open, advance_settlement
- `direction`: debit or credit only if useful internally, otherwise signed amount pattern; choose one consistent implementation
- `amount` decimal positive
- `signed_amount` optional generated/service-derived value
- `currency_code`
- `posted_at`
- `reversal_of_entry_id` nullable
- timestamps

There must be no update path for posted ledger entry amounts.

### 7.5 `payment_routes`

Required fields:

- `id`
- `tenant_id`
- `project_id`
- `route_no`
- `economic_source_party_id`
- `expected_destination_wallet_id`
- `expected_amount`
- `status`: planned, partial, completed, cancelled
- `description`
- timestamps

### 7.6 `payment_route_legs`

Required fields:

- `id`
- `tenant_id`
- `payment_route_id`
- `sequence_no`
- `from_party_id` nullable
- `to_party_id` nullable
- `from_wallet_id` nullable
- `to_wallet_id` nullable
- `amount`
- `leg_date`
- `financial_document_id` nullable
- `status`
- timestamps

### 7.7 `fund_chains`

Required fields:

- `id`
- `tenant_id`
- `project_id`
- `chain_no`
- `root_financial_document_id`
- `root_amount`
- `status`: active, fully_allocated, closed, cancelled
- `description`
- timestamps

A document may optionally reference one chain. This phase only requires trace grouping, not exact FIFO allocation of every currency unit.

### 7.8 `advances`

Required fields:

- `id`
- `tenant_id`
- `project_id`
- `advance_no`
- `holder_party_id`
- `holder_wallet_id`
- `source_wallet_id`
- `original_amount`
- `advanced_at`
- `due_date` nullable
- `status`: open, partially_settled, settled, overdue, cancelled
- `created_document_id`
- timestamps

### 7.9 `advance_settlements`

Required fields:

- `id`
- `tenant_id`
- `advance_id`
- `financial_document_id`
- `settlement_type`: approved_expense, cash_return, adjustment
- `amount`
- `settled_at`
- timestamps

### 7.10 `expense_approvals`

Use the existing workflow/approval system if it supports the required lifecycle cleanly. Otherwise create a treasury-specific approval record.

Required information:

- financial document
- requested by/at
- decision
- approver
- decision time
- comment
- approval mode: normal or self_approval

### 7.11 `reconciliations`

Required fields:

- `id`
- `tenant_id`
- `financial_document_id`
- `reconciliation_type`: bank, cash, receipt, intermediary_confirmation, other
- `reference_no`
- `reconciled_amount`
- `reconciled_by`
- `reconciled_at`
- `notes`
- timestamps

### 7.12 Attachments

Prefer reuse of the repository’s canonical document/attachment system. Do not create a duplicate file storage subsystem.

The treasury record must support evidence types:

- bank transfer image
- bank statement
- invoice
- receipt
- payment voucher
- cash acknowledgement
- labour confirmation
- contract or purchase order
- other

---

## 8. State machines

### 8.1 Funding

`declared -> posted_unreconciled -> posted_reconciled`

Alternative correction:

`posted_unreconciled|posted_reconciled -> reversed`

Funding must post immediately upon valid declaration.

### 8.2 Expense

`draft -> submitted -> approved -> posted_unreconciled -> posted_reconciled`

Implementation may combine approved and posted in one atomic transition while retaining both timestamps.

Rejection:

`submitted -> rejected -> draft -> submitted`

Correction:

`posted_* -> reversed`, then create replacement.

### 8.3 Internal transfer

`draft -> posted_unreconciled -> posted_reconciled`

If business policy requires approval later, add it only through a future change request. It is not required now.

### 8.4 Advance

`open -> partially_settled -> settled`

Automatic display state:

`open|partially_settled + due date passed -> overdue`

---

## 9. Permissions

Create granular permissions following repository conventions.

Minimum permission set:

- `treasury.view`
- `treasury.manage_parties`
- `treasury.manage_wallets`
- `treasury.declare_funding`
- `treasury.create_transfer`
- `treasury.create_expense`
- `treasury.submit_expense`
- `treasury.approve_expense`
- `treasury.self_approve_expense`
- `treasury.reconcile`
- `treasury.reverse`
- `treasury.adjust`
- `treasury.view_audit`
- `treasury.export`
- `treasury.manage_period_lock`

Default policy:

- Owner/admin X may use all permissions including self-approval.
- Accountant may approve, reconcile, reverse and report according to assigned role.
- Engineer Z may declare funding received, receive advances, create transfers where authorised, create expenses and submit settlements.
- Engineer Z cannot approve their own expense by default.
- Project viewers may see dashboard and transactions only if assigned to the project.

Every authorization check must include tenant and project membership/access.

---

## 10. User interface requirements

Use the repository’s existing Blade/Alpine/Tailwind and universal frame conventions unless the canonical frontend has changed.

### 10.1 Project Treasury dashboard

Required cards:

- Contract/budget value where available.
- Investor paid total.
- Funding received through intermediaries.
- Owner contribution.
- Posted project expenses.
- Total project-held funds.
- Unreconciled funding.
- Pending expense approvals.
- Outstanding advances.
- Missing evidence count.
- Budget variance.

Required wallet table:

- wallet
- custodian
- current derived balance
- unreconciled amount
- outstanding advance amount
- last activity

Required alerts:

- unreconciled funding older than configured threshold
- expense awaiting approval
- overdue advance
- missing evidence
- negative wallet where not permitted
- duplicate suspicion
- BOQ/budget overrun
- intermediary route not fully received

### 10.2 Transaction register

Filters:

- project
- date range
- document type
- status
- source/destination party
- source/destination wallet
- creator
- approver
- reconciled/unreconciled
- self-approved
- BOQ item
- fund chain
- payment route

Columns:

- document number
- date
- type
- source
- destination
- amount
- wallet impact
- status
- creator
- approver
- reconciliation
- evidence count

### 10.3 Declare funding form

Must support:

- investor payment
- intermediary route
- owner contribution
- source party
- receiving wallet
- amount/date/reference
- payment route or create route
- fund chain creation
- evidence

On save, valid funding is posted immediately as unreconciled.

### 10.4 Expense form

Must support:

- payer wallet
- recipient party
- amount/date
- category
- BOQ/BOQ line
- advance being settled
- description
- evidence
- draft or submit

No wallet balance or project expense changes before approval/posting.

### 10.5 Approval queue

Must show:

- requester
- payer wallet
- recipient
- amount
- BOQ/budget comparison
- evidence
- duplicate warning
- advance impact
- approve
- reject with mandatory reason

When X approves X’s own expense, show a clear self-approval badge and save the audit mode.

### 10.6 Advances page

Show by holder:

- original amount
- settled expense amount
- returned amount
- outstanding amount
- due date
- overdue days
- missing evidence
- linked documents

### 10.7 Financial timeline

Project page must provide chronological events such as:

- A paid 100,000,000 VND to X
- X transferred 50,000,000 VND to Y
- Y advanced 20,000,000 VND to Z
- Z paid 15,000,000 VND to labour D after approval
- Z paid 5,000,000 VND to supplier E after approval

Each event links to its document, route, chain, wallet and evidence. Timeline is a normal deterministic UI; no AI is required.

---

## 11. Reports

Required reports:

1. Project cashflow summary.
2. Investor payments and outstanding receivable summary.
3. Wallet balance report.
4. Custodian/employee money-held report.
5. Advance ageing report.
6. Expense by category.
7. Expense by BOQ and BOQ line.
8. Expense by supplier/labour/subcontractor.
9. Self-approved expense report.
10. Unreconciled transaction report.
11. Missing evidence report.
12. Payment route/in-transit report.
13. Fund chain trace report.
14. Reversal and adjustment report.

Exports should use existing export infrastructure where practical.

---

## 12. Validation and safeguards

1. Amount must be greater than zero.
2. Source and destination cannot be identical for transfers.
3. Wallets must belong to the same tenant and project.
4. Parties must belong to the same tenant.
5. BOQ item must belong to the same project.
6. Expense cannot post without an approver.
7. Expense cannot post without evidence when tenant policy marks evidence mandatory.
8. Funding must have source party and destination wallet.
9. Transfer must have source and destination wallet.
10. Posted transaction cannot be edited or hard deleted.
11. Reversal amount must exactly reverse original posted amount.
12. Negative balance must be blocked unless wallet explicitly allows it.
13. Duplicate warning must not silently block legitimate repeated payments; user may proceed with reason where authorised.
14. Every command must be idempotent against accidental double submission.
15. Concurrency must be controlled during posting to avoid double approval/posting.

---

## 13. Testing requirements

Claude Code must use TDD and existing PHPUnit conventions.

Minimum automated coverage:

### Domain/service tests

- funding posts immediately as unreconciled
- funding increases destination wallet derived balance
- investor payment increases investor-paid total once
- owner contribution does not increase investor-paid total
- multi-leg route does not double-count funding
- internal transfer preserves total project-held funds
- internal transfer creates two balanced wallet legs
- submitted expense does not change wallet balance
- approved expense posts and reduces payer wallet
- approved expense increases project actual cost
- X can self-approve X-created expense
- self-approval audit flag is stored
- Z cannot self-approve by default
- reversal restores financial effect without deleting original
- advance reduces after approved expense settlement
- advance reduces after cash return
- reconciliation does not repost amount
- tenant isolation prevents cross-tenant access
- project access prevents unauthorised viewing/posting
- duplicate submission is idempotent
- concurrent approval cannot post twice
- BOQ link must belong to project
- summary rebuild matches ledger totals

### Feature tests

- dashboard renders correct totals
- funding form posts immediately
- expense form creates draft/submitted state
- approval queue approves/rejects correctly
- self-approval badge appears
- filters return correct records
- timeline orders events correctly
- exports respect tenant/project permissions

### Migration/database tests

- money precision is preserved
- required foreign keys and indexes exist
- posted records cannot be hard-deleted through application paths

---

## 14. Delivery phases

### Phase 1 — Foundation

- Repository audit and domain placement decision.
- Financial parties.
- Project wallets.
- Permissions and policies.
- Basic navigation.

### Phase 2 — Ledger engine

- Financial documents.
- Ledger entries.
- Posting service.
- Derived wallet/project balances.
- Reversal framework.

### Phase 3 — Funding and routes

- Immediate funding posting.
- Owner contribution.
- Payment routes and route legs.
- Reconciliation.
- Duplicate warning.

### Phase 4 — Expenses and approvals

- Draft/submission.
- Approval/rejection.
- X self-approval.
- Posting after approval.
- Evidence and audit.

### Phase 5 — Advances and settlements

- Advance creation from authorised wallet transfer.
- Expense settlement.
- Cash return.
- Ageing and overdue alerts.

### Phase 6 — BOQ, dashboard and reports

- BOQ linkage.
- Project actual cost projection.
- Dashboard.
- Timeline.
- Reports and exports.

No AI work is permitted in these phases.

---

## 15. Acceptance criteria

The feature is acceptable only when all scenarios below work end-to-end.

### Scenario A — Direct investor payment to owner

1. A transfers 100,000,000 VND to X.
2. X declares the receipt.
3. The system immediately posts it as unreconciled.
4. Investor paid increases by 100,000,000.
5. X wallet increases by 100,000,000.
6. Reconciliation later changes only reconciliation status.

### Scenario B — Investor payment through intermediary

1. A pays 100,000,000 VND through C.
2. C later sends 80,000,000 VND to Y.
3. Investor paid displays 100,000,000 once.
4. Y wallet shows 80,000,000 received.
5. Route shows 20,000,000 still in transit/held by C.
6. No duplicated funding is reported.

### Scenario C — Internal transfer

1. X transfers 50,000,000 VND to Y.
2. X wallet decreases by 50,000,000.
3. Y wallet increases by 50,000,000.
4. Total project-held funds do not change.
5. Project expense does not change.

### Scenario D — Engineer advance and expense

1. Y transfers 20,000,000 VND to Z as an advance.
2. Z submits a 15,000,000 VND labour expense.
3. Before approval, project expense and Z wallet posted balance do not change from the expense.
4. X or accountant approves.
5. Z wallet decreases by 15,000,000.
6. Project actual cost increases by 15,000,000.
7. Advance outstanding becomes 5,000,000.

### Scenario E — Owner self-approval

1. X creates an expense.
2. X submits and approves it.
3. The expense posts successfully.
4. Audit shows creator and approver are X.
5. Approval mode is self_approval.
6. Self-approved report includes the expense.

### Scenario F — Correction

1. X mistakenly declares 1,000,000,000 instead of 100,000,000.
2. Posted record cannot be edited or deleted.
3. Authorised user reverses the incorrect record.
4. A replacement record for 100,000,000 is posted.
5. Original, reversal and replacement remain traceable.
6. Final balances are correct.

---

## 16. Claude Code implementation instructions

Before changing code, Claude Code must:

1. Read repository-level AI and project rules.
2. Inspect domain ownership and canonical model locations.
3. Inspect existing `Project`, BOQ, Approval, AuditLog, Document/Attachment, policies, routes and test conventions.
4. Identify duplicate or legacy classes before choosing a namespace.
5. Write an implementation plan with exact files and test cases.
6. Implement one phase at a time using TDD.
7. Run focused tests after every task and repository fast tests before each PR.
8. Preserve tenant isolation, project authorization and immutable posting rules.
9. Avoid unrelated refactors.
10. Do not implement AI or speculative future features.

The ledger and domain invariants take priority over UI speed. A visually complete screen backed by mutable or incorrect financial logic is not acceptable.

---

## 17. Final design decisions approved by the product owner

1. Use Project Ledger + Multi-wallet architecture.
2. Build a Project Treasury & Cashflow Management module, not a simple income/expense page.
3. Funding declared by recipient posts immediately as unreconciled.
4. Expenses require approval before posting.
5. X or accountant may approve expenses.
6. X may approve an expense created by X.
7. Self-approval must be clearly audited and separately reportable.
8. Internal transfers are not expenses.
9. Payment through C must not double-count investor payment.
10. Posted records are immutable and corrected by reversal/adjustment.
11. BOQ and budget linkage are required.
12. Financial timeline is required.
13. AI is explicitly deferred.
