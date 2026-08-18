---
work_id: GAP-037
gate: 2
gate_status: changes_requested
owner_decision:
  value: changes_requested
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-16-gap037-project-treasury-architecture-decisions.md
  plan: null
  branch: docs/GAP-037-project-treasury-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/263
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-16T16:59:15+07:00"
  owner_response_reference: "Owner Gate 2 Schema Proposal Revision 2 decision — REQUEST CHANGES, recorded in-session on 2026-08-16 against reviewed PR #263 head 14d62d713653f49ce368d94a313ea6c0694c0750: 'GAP-037 — Gate 2 Schema Proposal Revision 2 — Owner Decision: REQUEST CHANGES. Tôi, Owner, yêu cầu chỉnh sửa schema proposal của GAP-037 tại PR #263, reviewed head 14d62d713653f49ce368d94a313ea6c0694c0750. Gate 2 architecture set đã approved trước đó: A3 + A4-a + A.5 / B2 + B2-T / C / D vẫn giữ nguyên và không được mở lại. Tôi yêu cầu schema revision tiếp theo xử lý các vấn đề sau trước khi có thể tiến tới Gate 3: 1. Ledger-source bridge cho B2-T — Schema phải cho phép custody/route movements của một ContractPayment-linked route tác động tới Treasury wallet ledger mà không tạo second Treasury funding/commercial-payment document. Phải xác định concrete relationship giữa treasury_payment_route_legs và immutable ledger entries. 2. Canonical signed ledger semantics — treasury_ledger_entries phải có một deterministic debit/credit hoặc signed-amount convention và allocation/reversal semantics đủ để rebuild wallet balance. Bổ sung explicit reversal_of_entry_id hoặc cơ chế tương đương. Không được nói sum ledger entries khi schema không định nghĩa dấu của từng movement. 3. Source/destination của transaction document — internal_transfer, advance và các two-sided movements phải lưu được source và destination ngay từ draft state, trước khi ledger được post. Một singular wallet_id không đủ. Sửa bằng source/destination wallet fields hoặc một concrete equivalent. 4. Partial-route custody — Không được derive current custody bằng latest leg = toàn bộ route amount. Schema phải biểu diễn được ví dụ A→C 100, sau đó C→Y 60, với current custody C=40/Y=60. Current custody phải bảo toàn allocated economic amount bằng net movements, có xét partial routing, reversal/refund. 5. Approval audit phải được resolve trong schema Gate 2 — Không defer treasury_expense_approvals reuse-vs-new sang implementation planning/Gate 3. Existing DocumentApprovalEvent hiện hard-coupled với documents/document_versions, nên không được tuyên bố reuse trực tiếp nếu điều đó yêu cầu thay đổi existing schema/model. Chọn concrete additive approach hoặc chứng minh một existing generic mechanism thực sự reusable. Sau quyết định này phải cập nhật lại exact physical table count và migration order. 6. Same-tenant + same-project referential invariants — Với mọi polymorphic/non-FK link tới ContractExpense, MaterialReceiptLine, ContractPayment, wallet hoặc party, schema proposal phải ghi binding validation rule bảo đảm cùng tenant và đúng Project ownership. TenantScope query filtering một mình không đủ. 7. Prevent cost over-settlement — Ngoài rule allocations của một payment phải bằng payment amount, phải bảo đảm cumulative active allocations against each canonical cost source không vượt canonical incurred-cost amount, net of reversals, trừ trường hợp overpayment/prepayment có semantic riêng được Owner duyệt. Material prepayment trước material receipt không được giả lập thành incurred material cost. 8. Reconciliation model — Quyết định rõ whole-entry hay partial reconciliation và thiết kế quan hệ ledger↔reconciliation có referential integrity, auditability và reversal semantics. Phải định nghĩa deterministic rule cho posted_unreconciled → posted_reconciled. JSON list of ledger IDs không được để lại như một implementation ambiguity nếu nó không đáp ứng các invariant trên. Giữ nguyên: A3 Cost!=Cash boundary; A4-a absolute no-read/write/sync Component/Project cost fields; A.5 many-to-many/partial cost settlement; B2 giữ ContractPayment canonical commercial lifecycle; B2-T economic-allocation conservation, route legs non-additive; C no-double-posting; D không sửa ReportPageController::cashflow(); zero changes to existing tables/data; PR #245/#257 unchanged; không runtime/migration/model/controller/service/route/UI/tests; không Gate 3. Ghi nhận REQUEST CHANGES này trước bằng governance-record-only commit vào 02-design-v2.md. Sau đó freeze 02-design-v2.md và tạo 02-design-v3.md superseding nó, đúng versioned-Gate-2 pattern hiện tại. Chạy lại required CI và đưa 02-design-v3.md về awaiting_owner tại exact new head. Không được suy luận schema approval hoặc Gate 3 authorization.'"
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design.md
superseded_by: "docs/owner-decisions/GAP-037/02-design-v3.md"
timestamps:
  created_at: "2026-08-16T16:16:07+07:00"
  updated_at: "2026-08-16T16:59:15+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 2 — Schema Proposal

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** 2026-08-16 (`docs/owner-decisions/GAP-037/02-design.md`, now frozen — `superseded_by` there points here). This revision proposes a **concrete schema** implementing exactly that approved architecture — still Gate 2, still a proposal. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet. Every table/column/constraint below is a design artifact for Owner review, not a `Schema::create()` call.

**Authority:** every decision below is a direct, literal application of the already-approved architecture set — `docs/owner-decisions/GAP-037/02-design.md` (frozen) and `docs/superpowers/specs/2026-08-16-gap037-project-treasury-architecture-decisions.md` (background reference, still `references.spec` above). This document does not reopen or re-derive A3/A4-a/A.5/B2/B2-T/C/D; if implementing them faithfully surfaced a genuine conflict, that conflict would go back to the Owner as a new Gate 2 revision, never resolved by silently changing the approved architecture — none was found while drafting this.

PR #245 (`cd8b79d861f4c1bae5278b6c57f29cd14e505594`) is cross-referenced throughout as non-normative design evidence — its 12-table sketch is the starting point, revised wherever the approved architecture requires a change from it (chiefly: the cost-settlement allocation model in place of a single 1:1 link, and the funding-route allocation-conservation model in place of leg-summing).

---

## 1. Owner Summary
The approved architecture set (**A3 + A4-a + A.5 / B2 + B2-T / C / D**) is now translated into 12 concrete tables, their columns, constraints, state machines, indexes, tenant-isolation approach, and a dependency-safe migration order — with every design choice traced back to the specific architecture decision it implements.

## 2. Table inventory

| # | Table | Purpose | Relative to PR #245 |
|---|---|---|---|
| 1 | `treasury_financial_parties` | Counterparties (investor, intermediary, owner, employee, labour, supplier, subcontractor, authority, other) | As PR #245 §7.1, unchanged |
| 2 | `treasury_wallets` | Custodian/location abstraction, no persisted balance | As PR #245 §7.2, unchanged |
| 3 | `treasury_financial_documents` | Approval-workflow-gated transaction header (draft→submitted→approved→posted) | As PR #245 §7.3, scope narrowed per A3/B2 (see §4) |
| 4 | `treasury_ledger_entries` | Immutable posted movement | As PR #245 §7.4, unchanged in shape |
| 5 | **`treasury_cost_settlement_allocations`** | Many-to-many allocation between a cash-out and the cost record(s) it settles | **New — replaces PR #245's implied 1:1 link, required by A.5** |
| 6 | `treasury_payment_routes` | Multi-leg route header | As PR #245 §7.5, extended for B2-T's `ContractPayment` linkage (see §5) |
| 7 | `treasury_payment_route_legs` | Individual serial hop | As PR #245 §7.5, **conservation semantics corrected per B2-T** (see §5) |
| 8 | `treasury_fund_chains` (+ `treasury_fund_chain_members`) | Trace grouping only | As PR #245 §7.6, unchanged |
| 9 | `treasury_advances` | Advance lifecycle | As PR #245 §7.7, unchanged |
| 10 | **`treasury_advance_settlements`** | Advance↔settlement allocation | As PR #245 §7.7, **same many-to-many allocation pattern as #5** (partial settlement support) |
| 11 | `treasury_expense_approvals` | Approval decision audit | Proposal: **reuse the existing `DocumentApprovalEvent` shape** (`app/Models/DocumentApprovalEvent.php`, established by GAP-031/032/033) rather than invent a new audit-log pattern — same "who decided what and when" need, already-proven convention. Pending a small reuse-vs-new audit before Gate 3 (§6) |
| 12 | `treasury_reconciliations` | Bank/cash/receipt/intermediary reconciliation | As PR #245 §7.8, unchanged |
| — | Attachments | Evidence documents | **No new table** — reuse the existing document/attachment system, per PR #245's own instruction (§7.9) |

**Zero tables added, altered, or referenced outside this list.** No migration in this proposal touches `contracts`, `contract_expenses`, `contract_payments`, `material_receipts`, `material_receipt_lines`, `components`, or `projects` in any way — no new column, no new index, no new foreign key pointing *into* them from a required-not-null direction that would force a data backfill. Every relationship from a Treasury table to an existing table is a **nullable, non-enforced-at-the-database-level polymorphic reference** (§4–§5), because the existing tables must never be required to know Treasury exists.

---

## 3. `treasury_financial_parties` and `treasury_wallets`

Unchanged from PR #245's own design (§7.1–§7.2), carried here for completeness:

**`treasury_financial_parties`**: `id` (ulid), `tenant_id`, `party_type` (investor|intermediary|owner|employee|labour|supplier|subcontractor|authority|other), `name`, `linked_account_id` (nullable, CRM `accounts`), `linked_user_id` (nullable, `users`), timestamps. Index: `(tenant_id, party_type)`.

**`treasury_wallets`**: `id` (ulid), `tenant_id`, `project_id` (nullable — some wallets are company-level, not project-scoped), `wallet_type` (company_bank|company_cash|owner_personal|employee_cash|employee_bank|intermediary_control|project_wallet|other), `name`, `custodian_party_id` (nullable FK to `treasury_financial_parties`), timestamps. **No `balance` column** — balance is always derived by summing `treasury_ledger_entries` for that wallet at read time (or a cached/materialized projection built later, out of scope here). Index: `(tenant_id, project_id, wallet_type)`.

---

## 4. `treasury_financial_documents`, `treasury_ledger_entries`, and the A3 cost/cash boundary

**`treasury_financial_documents`**: `id` (ulid), `tenant_id`, `project_id`, `document_type` (funding|internal_transfer|expense|owner_contribution|advance|advance_return|reversal|adjustment), `status` (draft|submitted|approved|rejected|posted_unreconciled|posted_reconciled|reversed), `amount`, `wallet_id` (FK, the wallet this document moves money into/out of), `counterparty_id` (nullable FK to `treasury_financial_parties`), `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable), `reversed_document_id` (nullable self-ref), `replacement_document_id` (nullable self-ref), timestamps. Index: `(tenant_id, project_id, document_type, status)`.

**Binding scope narrowing per A3 (this is the load-bearing correction vs. a naive read of PR #245):** a `document_type: expense` row represents **only the cash-out side of settling an already-recorded cost** — it is never itself a cost record. Its `amount` is "cash paid," not "cost incurred." The cost itself continues to live exclusively in `ContractExpense` or `MaterialReceiptLine`, per A3. This document type's only job is to trigger the creation of one or more `treasury_cost_settlement_allocations` rows (§4.1) at posting time.

**`treasury_ledger_entries`**: `id` (ulid), `tenant_id`, `financial_document_id` (FK, immutable once created), `entry_type` (wallet_in|wallet_out|project_funding|project_expense_settlement|owner_financing|route_in_transit|advance_open|advance_settlement), `wallet_id`, `amount`, `posted_at`, timestamps (no `updated_at` — rows are append-only, never updated after insert; correction is a new row via reversal, per C). Index: `(tenant_id, financial_document_id)`, `(wallet_id, posted_at)` (for wallet-balance derivation).

### 4.1 `treasury_cost_settlement_allocations` — the A.5 mechanism (new relative to PR #245)

This table is what makes A3's "must reference canonical cost evidence" and A.5's cardinality requirements true simultaneously — a single FK pair on the financial document cannot do both.

| Column | Type | Notes |
|---|---|---|
| `id` | ulid | |
| `tenant_id` | string | |
| `financial_document_id` | FK → `treasury_financial_documents.id` | the cash-out being allocated |
| `cost_source_type` | enum: `contract_expense` \| `material_receipt_line` | which existing table the cost record lives in |
| `cost_source_id` | string | the `ContractExpense.id` or `MaterialReceiptLine.id` — **not a foreign key at the database level** (the two source tables have no common identity space, and Laravel's morph pattern doesn't require an FK constraint here), validated at the application layer against `cost_source_type` |
| `allocated_amount` | decimal | the portion of `financial_document.amount` applied to this specific cost record — supports partial allocation directly |
| `status` | enum: `active` \| `reversed` | |
| `reversed_allocation_id` | nullable self-ref | points to the allocation this row reverses, for per-allocation auditability (not just per-document) |
| `created_at`, `updated_at` | timestamps | |

**How this satisfies A.5's four cardinality requirements:**
- *One cost → many partial payments*: many allocation rows may share the same `(cost_source_type, cost_source_id)`, each from a different `financial_document_id`, each with its own `allocated_amount`.
- *One payment → many cost records*: many allocation rows may share the same `financial_document_id`, each pointing at a different cost record.
- *Partial allocation*: `allocated_amount` on each row is independent of both the financial document's total `amount` and the cost record's total value — the application layer sums allocations per cost record to compute "how much of this cost has been settled."
- *Reversal/replacement with allocation auditability*: `reversed_allocation_id` traces a correction to the exact allocation row it corrects, not merely to the parent financial document — satisfying the "allocation-level auditability" requirement distinctly from document-level reversal (`treasury_financial_documents.reversed_document_id`).

**How this satisfies A3's no-generic-escape-hatch correction:** a `document_type: expense` financial document is only valid (application-level invariant, not yet a DB constraint decision) if it has **at least one** `treasury_cost_settlement_allocations` row at posting time whose `allocated_amount` sum equals the document's `amount` — with a single documented exception class: `document_type` values that are *not* `expense` (`internal_transfer`, `advance`, `owner_contribution`) never require an allocation, because they are not, by definition, cost settlements. If a real expense has no existing cost record, the proposal requires the cost record (`ContractExpense` row) to be created in the **same database transaction** as the financial document + allocation — this is a service-layer atomicity requirement to state at implementation time, not a new table.

Index: `(financial_document_id)`, `(cost_source_type, cost_source_id)` — the second index is what makes "how much of `ContractExpense` X has been paid so far" a fast query.

---

## 5. `treasury_payment_routes` / `treasury_payment_route_legs` — the B2-T conservation model

**`treasury_payment_routes`**: `id` (ulid), `tenant_id`, `project_id`, `linked_source_type` (enum: `financial_document` \| `contract_payment`), `linked_source_id` (polymorphic, same non-DB-FK pattern as §4.1 for the `contract_payment` case; a real FK to `treasury_financial_documents` for the `financial_document` case), `total_allocated_amount` (decimal — **the economically allocated amount this route is responsible for tracking; this is the field checked for conservation, never a sum of legs**), `status` (planned|partial|completed|cancelled), timestamps.

**Corrected conservation invariant per Owner Gate 2 Round 2:** when `linked_source_type = contract_payment`, the following must hold at all times — enforced at the application/service layer (a cross-table sum check is not cleanly expressible as a MySQL `CHECK` constraint, so this is a `TreasuryRouteAllocationService`-level invariant plus a scheduled consistency job, stated here as a requirement, not yet as code):

```
SUM(treasury_payment_routes.total_allocated_amount
    WHERE linked_source_type = 'contract_payment'
    AND linked_source_id = <the ContractPayment.id>)
  <= ContractPayment.amount
```

This is an **allocation-level** check across routes, computed from each route's own `total_allocated_amount` field — **never** by summing `treasury_payment_route_legs.amount`. Legs are movement history of one already-allocated amount moving through custody hops; they are non-additive by definition (§5.1).

**`treasury_payment_route_legs`**: `id` (ulid), `tenant_id`, `payment_route_id` (FK), `sequence_no` (integer, ordering within the route), `from_wallet_id` (nullable — null for the first leg's external origin), `to_wallet_id` (FK), `amount` (decimal — equal to the route's `total_allocated_amount`, or a documented sub-amount if the route itself splits into parallel legs, a case this proposal does not need to resolve further since none of the current use cases require parallel splitting), `status` (in_transit|settled|reversed), `occurred_at`, timestamps.

### 5.1 Explicit non-additivity rule (documentation requirement, not a column)
Every implementation of route-leg logic must carry this rule as an inline comment or equivalent documentation at the point where legs are read: **`SUM(payment_route_legs.amount)` across a route's legs is never a valid computation for anything** — not for conservation, not for "total moved," not for the investor/client-paid metric. A route with legs A→C=100 then C→Y=100 represents one $100 allocation that has moved twice, not $200. Current custody location for a route = the wallet referenced by that route's latest non-reversed leg (by `sequence_no`), and current custody amount = that route's `total_allocated_amount` (not a leg sum) minus any amount returned via a `reversed` leg chain.

### 5.2 Investor/client-paid metric — single source, restated as a schema rule
No Treasury table, view, or computed column may be the source of an "amount paid by client/investor" figure. That figure is `ContractPayment.amount` (for `status = paid` rows), read directly — full stop. This proposal introduces no aggregate, materialized view, or cached column that could be mistaken for a second source of this fact.

---

## 6. `treasury_fund_chains`, `treasury_advances`, `treasury_advance_settlements`, `treasury_expense_approvals`, `treasury_reconciliations`

**`treasury_fund_chains`**: `id` (ulid), `tenant_id`, `project_id`, `chain_reference` (free-form grouping key), `description`, timestamps. A join table `treasury_fund_chain_members` (`fund_chain_id`, `member_type`: financial_document|payment_route, `member_id`) links documents/routes into a trace group. Explicitly **not** exact FIFO allocation — grouping only, per PR #245 §7.6's own stated scope.

**`treasury_advances`**: `id` (ulid), `tenant_id`, `project_id`, `financial_party_id` (FK, who holds the advance), `originating_financial_document_id` (FK, the transfer that created it), `amount`, `status` (open|partially_settled|settled|overdue|cancelled), timestamps.

**`treasury_advance_settlements`**: `id` (ulid), `tenant_id`, `advance_id` (FK), `settlement_type` (approved_expense|cash_return|adjustment), `linked_financial_document_id` (nullable FK — the cash-out or cash-return document), `linked_allocation_id` (nullable FK → `treasury_cost_settlement_allocations`, when `settlement_type = approved_expense`), `settled_amount`, `status` (active|reversed), timestamps. Same partial/many-to-many pattern as §4.1: multiple rows per advance support partial settlement over time.

**`treasury_expense_approvals`**: proposal is to **audit first** (recommendation, not yet done): inspect whether `app/Models/DocumentApprovalEvent.php`'s shape (actor, decision, decided_at, target polymorphic reference) can be reused directly for `treasury_financial_documents` approval events, since GAP-031/032/033 already established this exact "who decided what and when" pattern for Document workflow. If reusable, no new table — `DocumentApprovalEvent`-equivalent rows target `treasury_financial_documents` instead of `documents`. If the existing shape doesn't fit cleanly, a `treasury_expense_approvals` table mirrors the same shape independently. Deferred to implementation-planning time, since it depends on reading `DocumentApprovalEvent`'s actual coupling — implementation-detail-level investigation, not an architecture decision A–D covers.

**`treasury_reconciliations`**: `id` (ulid), `tenant_id`, `wallet_id` (FK), `reconciliation_type` (bank|cash|receipt|intermediary_confirmation|other), `reconciled_ledger_entry_ids` (json array of `treasury_ledger_entries.id`), `external_reference`, `reconciled_at`, `reconciled_by`, timestamps. Per PR #245 §7.8/§5.7.3: reconciliation never changes the economic amount, only marks ledger entries as externally confirmed.

---

## 7. State machines

**`treasury_financial_documents.status`:** `draft → submitted → approved → posted_unreconciled → posted_reconciled`. Alternate branch: `submitted → rejected` (terminal). From either posted state: `→ reversed`, via creating a new document with `document_type: reversal`, `reversed_document_id` pointing back, and (if applicable) a `replacement_document_id` on the reversal pointing to a newly created corrected document. No transition ever returns a `posted_*` document to `draft`/`submitted`/`approved` — correction is always forward-only (a new document), per C's immutable-posting requirement.

**`treasury_ledger_entries`:** no status field — entries are created once, at posting time, and never transition. Correction is a new entry (of a reversal-flavored `entry_type` or via the reversal document's own new entries), never an update to an existing row.

**`treasury_cost_settlement_allocations.status`:** `active → reversed` (one-way; a reversal doesn't "un-reverse").

**`treasury_payment_routes.status`:** `planned → partial → completed`; from `planned` or `partial` only: `→ cancelled`. From `completed`: no forward transition — a completed route is corrected via reversing its legs, not by changing the route's own status back.

**`treasury_advances.status`:** `open → partially_settled → settled`; independently, time-based `→ overdue` (does not block other transitions); any non-terminal state `→ cancelled`.

**`treasury_advance_settlements.status`:** `active → reversed` (same pattern as cost-settlement allocations).

---

## 8. Indexes (proposal-level; final index list is implementation detail, not locked here)

| Table | Suggested indexes |
|---|---|
| All tables | `(tenant_id)` at minimum; `(tenant_id, project_id)` where `project_id` exists |
| `treasury_financial_documents` | `(tenant_id, project_id, document_type, status)` |
| `treasury_ledger_entries` | `(tenant_id, financial_document_id)`, `(wallet_id, posted_at)` |
| `treasury_cost_settlement_allocations` | `(financial_document_id)`, `(cost_source_type, cost_source_id)` |
| `treasury_payment_routes` | `(linked_source_type, linked_source_id)`, `(tenant_id, project_id, status)` |
| `treasury_payment_route_legs` | `(payment_route_id, sequence_no)` |
| `treasury_advances` | `(financial_party_id, status)` |
| `treasury_advance_settlements` | `(advance_id)` |
| `treasury_reconciliations` | `(wallet_id, reconciled_at)` |

---

## 9. Tenant / project isolation

Every table carries `tenant_id`, matching the repo's existing convention confirmed present on `ContractExpense`, `ContractPayment`, `MaterialReceipt`, and `MaterialReceiptLine` (all `use TenantScope`, per Gate 1's own runtime audit). Proposal: every new Treasury Eloquent model applies the same `App\Traits\TenantScope` trait — an existing, audited convention, not a new isolation mechanism. `project_id` is present on every table where the record is inherently project-scoped (`treasury_financial_documents`, `treasury_wallets` when `wallet_type = project_wallet`, `treasury_payment_routes`, `treasury_advances`); company-level wallets (`company_bank`, `company_cash`) carry a nullable `project_id`.

---

## 10. Migration strategy

**Every migration in this proposal is a pure additive `Schema::create()` statement.** Zero `Schema::table()` alterations to any existing table — this is the concrete, checkable form of the "no existing-data migration" claim already approved in Gate 2 Round 1/2. Confirmed zero touches to: `contracts`, `contract_expenses`, `contract_payments`, `material_receipts`, `material_receipt_lines`, `components`, `projects`.

**Proposed creation order** (dependency-safe — each table only references tables created before it):
1. `treasury_financial_parties`
2. `treasury_wallets` (references `treasury_financial_parties` via nullable `custodian_party_id`)
3. `treasury_financial_documents` (references `treasury_wallets`, `treasury_financial_parties`)
4. `treasury_ledger_entries` (references `treasury_financial_documents`, `treasury_wallets`)
5. `treasury_cost_settlement_allocations` (references `treasury_financial_documents`)
6. `treasury_payment_routes` (references `treasury_financial_documents`; polymorphic, non-FK reference to `contract_payments`)
7. `treasury_payment_route_legs` (references `treasury_payment_routes`, `treasury_wallets`)
8. `treasury_fund_chains` + `treasury_fund_chain_members` (references `treasury_financial_documents`, `treasury_payment_routes`)
9. `treasury_advances` (references `treasury_financial_parties`, `treasury_financial_documents`)
10. `treasury_advance_settlements` (references `treasury_advances`, `treasury_financial_documents`, `treasury_cost_settlement_allocations`)
11. `treasury_expense_approvals` (only if not reusing `DocumentApprovalEvent` — pending the audit in §6) (references `treasury_financial_documents`)
12. `treasury_reconciliations` (references `treasury_wallets`)

No table in this order is ever created before a table it references. **No migration file exists yet — this is the proposed order for when Gate 3 authorizes writing them.**

---

## 11. Verification against every C (no-double-posting) and D (cashflow) requirement

- Every economic fact has exactly one canonical source: cost in `ContractExpense`/`MaterialReceiptLine` (untouched); commercial payment in `ContractPayment` (untouched); everything else in the 12 Treasury tables above.
- Every Treasury reference to an existing table is either (a) a nullable, non-enforced polymorphic pointer (§4.1, §5) or (b) absent entirely (Component/Project, per A4-a — confirmed zero tables above reference `components` or `projects`).
- Immutable posting: `treasury_ledger_entries` has no `updated_at`-driven mutation path; correction is always a new row/document (§7).
- `ReportPageController::cashflow()`: zero references anywhere in this schema. No Treasury table is read by it, writes to it, or is designed to be read by it in this proposal — that integration is explicitly Finance Control's future job (D), and this schema only needs to expose *queryable* cash facts (via `treasury_ledger_entries`/wallets) for Finance Control to consume later, not implement that consumption itself.

---

## 12. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu (Gate 1/2/2-v2 packets + 1 spec doc), **không phải authorization cho migration/model/code thật**; việc viết migration/model/controller/service/route/UI thật vẫn cần Gate 3 approval rồi mới bắt đầu, và có thể còn cần thêm một Gate 2 implementation-plan revision tuỳ theo yêu cầu Owner lúc đó.
- Nếu Owner Request changes: nêu rõ bảng/cột/constraint nào cần sửa; sẽ tạo `02-design-v3.md` (supersedes bản này), không sửa `02-design-v2.md` sau khi có quyết định.
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này, giữ nguyên phần architecture đã approved làm lịch sử.

## 13. Loại trừ phạm vi
Kế thừa nguyên vẹn từ Gate 1 và Gate 2 round 1-3: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment.

## Decision Needed
**Owner đã chọn: Request changes**, tại PR #263 head `14d62d713653f49ce368d94a313ea6c0694c0750` (2026-08-16) — 8 required corrections (ledger-source bridge for B2-T, signed ledger semantics, source/destination on two-sided documents, partial-route custody, resolve approval-audit reuse-vs-new now, cross-tenant/project referential invariants, prevent cost over-settlement, proper reconciliation model). Architecture set A3+A4-a+A.5/B2+B2-T/C/D confirmed unchanged, not reopened. Chi tiết nguyên văn tại `decision_provenance.owner_response_reference`. **This packet (`02-design-v2.md`) is now frozen — no further content edits.** `docs/owner-decisions/GAP-037/02-design-v3.md`, addressing these 8 points, follows in the next commit.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật, tên class PHP cụ thể, hay chi tiết implementation (transaction boundaries, service method names) — đó là công việc thực hiện sau Gate 3, theo đúng schema đã duyệt ở đây. Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved ở round 3, packet đó đã đóng băng. Owner cũng không được yêu cầu quyết định `treasury_expense_approvals` reuse-vs-new — đó là một audit nhỏ còn treo, sẽ báo cáo riêng khi có kết quả trước khi Gate 3.
