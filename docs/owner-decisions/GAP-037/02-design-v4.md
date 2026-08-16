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
  recorded_at: "2026-08-16T17:31:49+07:00"
  owner_response_reference: "Owner Gate 2 Schema Proposal Revision 4 decision — REQUEST CHANGES, recorded in-session on 2026-08-16 against reviewed PR #263 head bd75f5f063605d80ac6289e92cdf0e856b5c0f98: 'GAP-037 — Gate 2 Schema Proposal Revision 4 — Owner Decision: REQUEST CHANGES. Tôi, Owner, yêu cầu chỉnh sửa schema proposal của GAP-037 tại PR #263, reviewed head bd75f5f063605d80ac6289e92cdf0e856b5c0f98. Tôi xác nhận Revision 4 đã xử lý đúng các correction trước và architecture set A3 + A4-a + A.5 / B2 + B2-T / C / D vẫn approved, frozen và không được mở lại. Revision 5 chỉ cần xử lý các schema/invariant gaps sau: 1. Remove impossible conditional FK — treasury_payment_routes.linked_source_id không được đồng thời là polymorphic financial_document|contract_payment nhưng lại có real FK chỉ khi linked_source_type=financial_document. Chọn concrete implementable model: preferably split typed nullable source columns with an exactly-one constraint; hoặc keep the polymorphic pair wholly application-validated. Không được tuyên bố một conditional MySQL FK không thể tồn tại như mô tả hiện nay. 2. Freeze posting-path choice before posting — Binding invariant: một financial document dùng direct-post path hoặc route-leg-post path, không bao giờ cả hai; route association phải được xác lập trước posting; sau khi document hoặc route đã sinh ledger entry, không được attach/detach/relink route theo cách thay đổi posting source. at most one route một mình chưa đủ để bảo đảm exclusivity. 3. Exact reversal invariant — Với ledger entries, cost-settlement allocations, advance settlements và reconciliation entries: reverse row chỉ được trỏ vào một apply/original row hợp lệ; phải cùng economic subject; reverse amount phải bằng đúng original amount; một original/apply chỉ được reverse tối đa một lần; reverse của reverse không được dùng như arbitrary compensation; nếu cần undo một reversal phải đi theo explicit forward correction rule được schema cho phép. Net formulas hiện tại được giữ nguyên nhưng chỉ chạy trên các valid compensating reversals. 4. Concurrency-safe aggregate invariants — Mọi service-layer aggregate check phải đi cùng binding transactional serialization rule. Ít nhất phải bảo vệ: ContractPayment route-allocation conservation; cost over-settlement cap; advance outstanding settlement cap; active reconciliation uniqueness; financial-document posting-source selection. Có thể dùng row lock / equivalent serialization mechanism; không cần chốt tên class/method ở Gate 2, nhưng simple unlocked check-then-insert là không đủ. 5. Reconciliation reversal actor audit — Schema phải lưu hoặc deterministically derive được actor thực hiện reverse reconciliation. Không dùng cụm implicit actor trail nếu schema không chứa relationship chứng minh actor đó. Prefer explicit actor on the immutable reconciliation event row, hoặc một equivalent action-header design. 6. Composite-FK target indexes — Vì schema dùng composite (tenant_id,id) FKs, Revision 5 phải ghi binding index requirement cho mọi Treasury table đóng vai trò referenced target: UNIQUE/eligible index (tenant_id, id) theo DB compatibility được repo support. Không để migration implementation tự suy luận constraint prerequisite này. Giữ nguyên toàn bộ các phần đã đạt: exact table count 12 primary + 2 joins = 14 CREATE TABLEs; source/destination wallets; debit/credit ledger semantics; route->ledger bridge; B2-T ContractPayment route không tạo second commercial-payment document; partial-route custody bằng net signed movements; advance->cost settlement tạo zero second cash-out; outstanding-advance conservation equation; material prepayment không được giả thành incurred cost; cumulative cost over-settlement cap; reconciliation áp dụng cho cả financial-document và route-leg ledger entries; reconciliation wallet-match rule; whole-entry reconciliation; separate additive treasury_expense_approvals; Tier-B same-tenant/same-project validation; zero changes to existing tables/data; A4-a absolute no-read/write/sync Component/Project cost fields; D không sửa ReportPageController::cashflow(); PR #245/#257 untouched; không migration/model/controller/service/route/UI/tests thật; không Gate 3. Ghi nhận REQUEST CHANGES này trước bằng governance-record-only commit vào 02-design-v4.md; sau đó freeze v4 và tạo self-contained 02-design-v5.md superseding nó. Chạy lại required CI và đưa v5 về awaiting_owner tại exact new head. Không được suy luận schema approval hoặc Gate 3 authorization.'"
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design-v3.md
superseded_by: null
timestamps:
  created_at: "2026-08-16T17:20:14+07:00"
  updated_at: "2026-08-16T17:31:49+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 4 — Self-Contained Corrected Schema

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** (`docs/owner-decisions/GAP-037/02-design.md`, frozen) — **A3 + A4-a + A.5 / B2 + B2-T / C / D**, not reopened by this revision. Revisions 2 and 3 (both frozen, superseded) proposed schemas the Owner found gaps in. This revision is **fully self-contained** — it does not require reading v2 or v3 to understand the complete proposed schema, per the Owner's explicit instruction. Still Gate 2 — a proposal, not implementation. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet.

---

## 0. What changed vs. v3, at a glance

| # | Owner's finding | Fix |
|---|---|---|
| 1 | No rule preventing a movement from posting via both `financial_document` and `payment_route_leg`; no idempotency | A `treasury_financial_document` may have **at most one** associated route (new uniqueness constraint, §3.4); when one exists, the route's first leg posts the ledger entries and the document does not post its own. A generated-column unique index on `treasury_ledger_entries` gives exactly-once posting per `(source_type, source_id, direction)` (§4.2) |
| 2 | Advance settlement against a real cost would create a second cash-out | `treasury_cost_settlement_allocations` gains a nullable `advance_settlement_id` (mutually exclusive with `financial_document_id` via a `CHECK` constraint); an `approved_expense`-type advance settlement creates **no** new ledger entry — the cash already moved when the advance was disbursed (§6.3) |
| 3 | Route-leg entries excluded from reconciliation; no `reconciliation.wallet_id = ledger_entry.wallet_id` rule; Postgres-only partial-unique syntax; reversal not clearly auditable | Reconciliation now covers every ledger entry regardless of `source_type` (§7); wallet-match rule stated explicitly; the MySQL-compatible generated-column trick (same one used for §4.2) replaces the Postgres `WHERE` syntax; reversal uses the uniform apply/reverse pattern from fix #5 |
| 4 | Tenant/project invariants incomplete | §8 restated comprehensively, now including `treasury_ledger_entries.source_type/source_id`, approval records, fund-chain members, and reconciliation/wallet pairs — plus composite `(tenant_id, id)` foreign keys wherever a real single-table FK exists, elevating several checks from application-layer to DB-enforced |
| 5 | Ambiguous "status: active\|reversed" + separate reversal-pointer on an append-only row | Replaced everywhere (allocations, advance settlements, reconciliation entries) with a uniform **`direction: apply \| reverse`** pattern, mirroring the ledger's own debit/credit design — exact net-formula given for each (§9) |
| 6 | "15 CREATE TABLE" claimed, only 14 listed | Recounted precisely: **12 primary tables + 2 join tables = 14 `CREATE TABLE` statements.** The "15" in Revision 3 was an arithmetic error, not a hidden 15th table — corrected here, and this document is self-contained so the count can be verified directly against §10 |

---

## 1. `treasury_financial_parties` and `treasury_wallets`

**`treasury_financial_parties`**: `id` (ulid, PK), `tenant_id`, `party_type` (investor|intermediary|owner|employee|labour|supplier|subcontractor|authority|other), `name`, `linked_account_id` (nullable), `linked_user_id` (nullable), timestamps. Index: `(tenant_id, party_type)`.

**`treasury_wallets`**: `id` (ulid, PK), `tenant_id`, `project_id` (nullable — company-level wallets have no project), `wallet_type` (company_bank|company_cash|owner_personal|employee_cash|employee_bank|intermediary_control|project_wallet|other), `name`, `custodian_party_id` (nullable FK → `treasury_financial_parties(tenant_id, id)`, composite), timestamps. No `balance` column — always derived (§4.3). Index: `(tenant_id, project_id, wallet_type)`.

---

## 2. `treasury_financial_documents`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `tenant_id` | string | |
| `project_id` | string | |
| `document_type` | enum: funding\|internal_transfer\|expense\|owner_contribution\|advance\|advance_return\|reversal\|adjustment | |
| `status` | enum: draft\|submitted\|approved\|rejected\|posted_unreconciled\|posted_reconciled\|reversed | |
| `amount` | decimal, positive | |
| `source_wallet_id` | nullable FK → `treasury_wallets(tenant_id, id)`, composite | set for outflow types: expense, internal_transfer, advance, adjustment (decrease) |
| `destination_wallet_id` | nullable FK → `treasury_wallets(tenant_id, id)`, composite | set for inflow types: funding, internal_transfer, owner_contribution, advance_return, adjustment (increase) |
| `counterparty_id` | nullable FK → `treasury_financial_parties(tenant_id, id)`, composite | |
| `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable) | | |
| `reversed_document_id` | nullable self-ref (`tenant_id, id`) | |
| `replacement_document_id` | nullable self-ref (`tenant_id, id`) | |
| timestamps | | |

**Population rule (unchanged from v3, restated):** `internal_transfer` requires both wallet fields (must differ); `expense`/`advance` require only `source_wallet_id`; `funding`/`owner_contribution`/`advance_return` require only `destination_wallet_id`; `reversal` mirrors the reversed document with roles swapped; `adjustment` requires exactly one.

### 2.1 Fix #1 (part A) — at most one route per document
A `treasury_financial_document` may have **at most one** associated `treasury_payment_route` where `linked_source_type = 'financial_document'` — enforced via a generated-column unique index on `treasury_payment_routes` (§3.4). This is the structural half of the exclusive-posting rule (§4.2 is the other half).

Index: `(tenant_id, project_id, document_type, status)`, `(source_wallet_id)`, `(destination_wallet_id)`.

---

## 3. `treasury_payment_routes` and `treasury_payment_route_legs`

**`treasury_payment_routes`**: `id` (ulid, PK), `tenant_id`, `project_id`, `linked_source_type` (enum: `financial_document` \| `contract_payment`), `linked_source_id` (polymorphic — real composite FK to `treasury_financial_documents(tenant_id, id)` when `linked_source_type = financial_document`; non-DB-FK, application-validated against `ContractPayment` when `linked_source_type = contract_payment`, same pattern as `cost_source_id` elsewhere), `total_allocated_amount` (decimal — the conserved figure, unchanged from B2-T), `status` (planned|partial|completed|cancelled), timestamps.

### 3.4 Fix #1 (part B) — uniqueness constraint (MySQL-compatible)
Add a generated column: `financial_document_route_key = CASE WHEN linked_source_type = 'financial_document' THEN CONCAT(tenant_id, ':', linked_source_id) ELSE NULL END`, with a **`UNIQUE`** index on it. MySQL unique indexes treat `NULL` as distinct from every other `NULL` (multiple `NULL`s are permitted), so this enforces "at most one route per `(tenant, financial_document)`" while imposing no constraint at all on `contract_payment`-linked routes, which B2-T explicitly requires to allow multiple (partial routing across several routes, conserved by §6.1's `SUM(...) <= ContractPayment.amount`, unchanged from Round 2). This same generated-column technique is reused at every other point in this document where a Postgres-style partial-unique index was previously implied — it is the one, consistent MySQL-compatible mechanism for "unique among a subset of rows" throughout this schema.

**`treasury_payment_route_legs`**: `id` (ulid, PK), `tenant_id`, `payment_route_id` (FK → `treasury_payment_routes(tenant_id, id)`, composite), `sequence_no`, `from_wallet_id` (nullable FK, composite), `to_wallet_id` (FK, composite), `amount` (decimal — need not equal the route's `total_allocated_amount`; must not exceed the current custody available at `from_wallet_id` for this route, per §4.3's balance formula, checked at write time), `status` (in_transit|settled|reversed), `occurred_at`, timestamps.

---

## 4. `treasury_ledger_entries`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `tenant_id` | string | |
| `source_type` | enum: `financial_document` \| `payment_route_leg` | |
| `source_id` | string | non-DB-FK (spans two possible tables), application-validated against `source_type` |
| `wallet_id` | FK → `treasury_wallets(tenant_id, id)`, composite | |
| `direction` | enum: `debit` \| `credit` | debit decreases wallet balance, credit increases it |
| `amount` | decimal, always positive | sign comes from `direction` only |
| `entry_type` | enum: wallet_funding\|wallet_expense_settlement\|wallet_internal_transfer\|wallet_owner_financing\|wallet_advance_open\|wallet_advance_settlement\|wallet_route_custody | narrative classification, orthogonal to `direction` |
| `posted_at` | timestamp | |
| `reversal_of_entry_id` | nullable self-ref (`tenant_id, id`) | a reversal entry has the opposite `direction`, the same `amount`, and points back here |
| `created_at` | timestamp | append-only, no `updated_at` |

### 4.1 §1 — the ledger-source bridge (unchanged principle from v3)
A `payment_route_leg` posts ledger entries directly (`source_type = payment_route_leg`), never wrapped in a Treasury document — this is what lets a `ContractPayment`-linked route move custody without ever creating a second commercial-payment/funding document (B2-T, unchanged). Treasury-native movements without an associated route post via `source_type = financial_document`.

### 4.2 Fix #1 (part C) — exactly-once idempotency, MySQL-compatible
Add a generated column: `original_posting_key = CASE WHEN reversal_of_entry_id IS NULL THEN CONCAT(source_type, ':', source_id, ':', direction) ELSE NULL END`, with a **`UNIQUE`** index on it. This guarantees, at the database level: for a given source (a specific financial document or a specific route leg) and a given direction, at most one *original* (non-reversal) ledger entry can ever exist — a retried or duplicated posting attempt fails the unique constraint rather than silently double-posting. Reversal entries (`reversal_of_entry_id IS NOT NULL`) are excluded from this key (it evaluates to `NULL` for them), so a reversal never collides with the original it reverses, and multiple reversal attempts are a separate, deliberate business decision (not blocked at this layer — a second reversal of an already-reversed entry is a service-layer check, since "was this already reversed" requires reading prior rows, not just a uniqueness constraint).

**Exclusivity (the other half of fix #1, tying to §2.1/§3.4):** at posting time, the service layer checks whether the source `financial_document` has an associated route (§3.4's uniqueness guarantees there is at most one). If yes, the document does not post directly — the route's first leg (from the document's `source_wallet_id`/`destination_wallet_id` to the route's first custody point) is what posts, via `source_type = payment_route_leg`. If no route exists, the document posts directly via `source_type = financial_document`. These two paths are structurally mutually exclusive per document (§2.1's uniqueness), and each path is individually idempotent (§4.2) — together, a given economic movement can never be posted by both paths, and never posted twice by either.

### 4.3 Wallet balance and route custody (unchanged formula from v3, restated for self-containment)
```
wallet_balance(wallet_id) = SUM(amount WHERE wallet_id=X, direction='credit')
                           - SUM(amount WHERE wallet_id=X, direction='debit')
```
Current custody at a wallet, for a given route, is the same formula scoped to `source_type=payment_route_leg` entries belonging to that route's legs. Partial routing (A→C=100, then C→Y=60) correctly nets to C=40, Y=60 — no "latest leg" special-casing.

---

## 5. `treasury_cost_settlement_allocations`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `tenant_id` | string | |
| `financial_document_id` | nullable FK → `treasury_financial_documents(tenant_id, id)`, composite | set when this allocation settles a **direct** expense cash-out |
| `advance_settlement_id` | nullable FK → `treasury_advance_settlements(tenant_id, id)`, composite | **new (fix #2)** — set when this allocation arises from settling a pre-existing advance against a cost, with no new cash-out |
| `cost_source_type` | enum: `contract_expense` \| `material_receipt_line` | |
| `cost_source_id` | string | non-DB-FK, application-validated against `cost_source_type` |
| `direction` | enum: `apply` \| `reverse` | **fix #5** — replaces the earlier ambiguous `status`/`reversed_allocation_id` pair |
| `allocated_amount` | decimal, always positive | magnitude only; sign comes from `direction` |
| `reverses_allocation_id` | nullable self-ref (`tenant_id, id`) | **required when `direction = reverse`**, null when `direction = apply` |
| `created_at` | timestamp | append-only |

**`CHECK` constraint (MySQL 8.0.16+, used here as a real single-row constraint — distinct from the generated-column-unique technique, which solves a different class of problem):**
```sql
CHECK ((financial_document_id IS NULL) != (advance_settlement_id IS NULL))
```
Exactly one of the two must be set — an allocation always traces back to *either* a direct cash-out *or* an advance settlement, never both, never neither.

### 5.1 Fix #5 — exact net-allocation formula
```
net_active_allocation(cost_source_type, cost_source_id) =
    SUM(allocated_amount WHERE direction='apply'   AND cost_source_type=X AND cost_source_id=Y)
  - SUM(allocated_amount WHERE direction='reverse' AND cost_source_type=X AND cost_source_id=Y)
```
The over-settlement cap (§6.2, carried from v3's §4.2) is checked against this exact formula, not an ambiguous "status=active" filter. Per-financial-document total (§6.1, the original settlement-completeness check) uses the same formula scoped to `financial_document_id` instead of `cost_source_type/cost_source_id`.

Index: `(financial_document_id)`, `(advance_settlement_id)`, `(cost_source_type, cost_source_id)`.

---

## 6. Settlement invariants (cost authority, A3/A.5, restated with the corrected formula)

**6.1 Per-document completeness (unchanged principle):** for a `document_type: expense` financial document, `net_active_allocation` (§5.1, scoped to `financial_document_id`) must equal the document's `amount` before it may post.

**6.2 Per-cost-source cap (fix #7 from Round 3, restated with the corrected formula):**
```
net_active_allocation(cost_source_type, cost_source_id) <= canonical_incurred_amount(cost_source_type, cost_source_id)
```
where `canonical_incurred_amount` reads `ContractExpense.amount` or the `MaterialReceiptLine`-derived cost (`quantity_received × unit_cost`) — read only, never duplicated. Enforced at the service layer at allocation-creation time (a cross-table sum, same enforcement tier as §3.1's route conservation).

**6.3 Fix #2 — advance settlement against a cost, without a second cash-out**

**`treasury_advances`**: `id` (ulid, PK), `tenant_id`, `project_id`, `financial_party_id` (FK, composite — who holds the advance), `originating_financial_document_id` (FK → `treasury_financial_documents(tenant_id, id)`, composite — the `document_type: advance` document whose posting **is** the original cash-out; this is the only cash movement the advance ever produces on disbursement), `amount`, timestamps. (No `status` column — outstanding balance is always computed, §6.4, never stored redundantly.)

**`treasury_advance_settlements`**: `id` (ulid, PK), `tenant_id`, `advance_id` (FK, composite), `settlement_type` (enum: `approved_expense` \| `cash_return`), `direction` (enum: `apply` \| `reverse` — same uniform pattern as §5), `amount`, `financial_document_id` (nullable FK, composite — **see population rule below**), `reverses_settlement_id` (nullable self-ref, composite — required when `direction=reverse`), `created_at`.

**Population rule for `financial_document_id` — this is the concrete fix:**
- `settlement_type = approved_expense`: `financial_document_id` is **`NULL`**. No new wallet movement occurs — the cash already left the wallet when the original advance was disbursed (`treasury_advances.originating_financial_document_id`'s own posting). This settlement's only effect is creating a `treasury_cost_settlement_allocations` row with `advance_settlement_id` set to this row's id (and `financial_document_id` null there too, per §5's `CHECK` constraint) — a pure re-classification of already-spent money against a now-known cost record, zero ledger entries produced.
- `settlement_type = cash_return`: `financial_document_id` **is required**, referencing a **new** `document_type: advance_return` document (unused advance funds physically returning to a wallet — a genuine, distinct cash-in event, correctly posted once via the normal direct-posting path of §4.1/§4.2, since an `advance_return` document has no associated route).

**6.4 Outstanding advance conservation equation:**
```
outstanding_advance_balance(advance) =
    advance.amount
  - SUM(advance_settlements.amount WHERE direction='apply',   advance_id=X)
  + SUM(advance_settlements.amount WHERE direction='reverse', advance_id=X)
```
Both `approved_expense` and `cash_return` settlements reduce the outstanding balance identically in this formula — one because the spend is now attributed to a real cost, the other because the money physically came back; both are "no longer outstanding" for the same reason (the advance's original amount is accounted for).

**Material prepayment carve-out (unchanged from Round 3, restated):** a prepayment to a supplier before a `MaterialReceiptLine` exists must never be modeled as a `treasury_cost_settlement_allocations` row (there is no cost record yet to reference). It is modeled as a `treasury_advances` row, settled later via `approved_expense` once a real `MaterialReceiptLine` exists. No overpayment/credit-balance semantic is proposed; a write that would violate §6.2's cap is rejected outright.

---

## 7. Fix #3 — `treasury_reconciliations` and `treasury_reconciliation_entries`

**`treasury_reconciliations`**: `id` (ulid, PK), `tenant_id`, `wallet_id` (FK → `treasury_wallets(tenant_id, id)`, composite), `reconciliation_type` (bank|cash|receipt|intermediary_confirmation|other), `external_reference`, `reconciled_at`, `reconciled_by`, timestamps.

**`treasury_reconciliation_entries`**: `id` (ulid, PK), `tenant_id`, `reconciliation_id` (FK, composite), `ledger_entry_id` (FK → `treasury_ledger_entries(tenant_id, id)`, composite), `direction` (enum: `apply` \| `reverse` — **fix #5's uniform pattern**, replacing the earlier `status`/`reversed_at` pair), `reverses_reconciliation_entry_id` (nullable self-ref, composite — required when `direction=reverse`), `created_at`.

**Whole-entry reconciliation (unchanged decision from Round 3):** a `treasury_ledger_entries` row is either fully reconciled or not — no fractional reconciliation of one entry's amount.

**Fix — reconciliation now covers every ledger entry, regardless of `source_type`.** Round 3 excluded `payment_route_leg`-sourced entries, reasoning that the leg's own `status` (in_transit|settled|reversed) already tracked its lifecycle. The Owner correctly rejects this: a leg's `status` is an **in-app** state transition, not confirmation against **external evidence** (a bank statement, a receipt) — the two are orthogonal facts. `treasury_reconciliation_entries.ledger_entry_id` already references `treasury_ledger_entries` generically; the fix is simply removing the exclusion — any ledger entry, whichever `source_type` produced it, may be reconciled through this same table.

**Fix — `reconciliation.wallet_id` must equal `ledger_entry.wallet_id`:** binding rule, checked at write time (application layer — a cross-table equality between two FK-referenced rows is not natively expressible as a MySQL FK or generated column across two separate join hops): a `treasury_reconciliation_entries` row is only valid if `treasury_reconciliations.wallet_id` (via `reconciliation_id`) equals `treasury_ledger_entries.wallet_id` (via `ledger_entry_id`) for the same tenant.

**Fix — MySQL-compatible "currently active" uniqueness, and why it's a service-layer check here (not a generated column, unlike §4.2):** the ledger-idempotency case (§4.2) is a *permanent* fact — an original entry, once posted, is never superseded, so "at most one original per source+direction" is stable forever and a generated-column unique index is the correct, cheap mechanism. Reconciliation state is *not* permanent in the same way — an entry can be reconciled, the reconciliation reversed, and the entry re-reconciled later (multiple `apply` rows over time for the same `ledger_entry_id`, each superseding the last). "Exactly one *currently* active apply" is a computed, time-varying condition, not a stable key — the same category of check as §6.2's over-settlement cap and §3.1's route conservation. Consistent with this document's own established tiering (permanent single-fact constraints → generated-column unique index; aggregate/point-in-time constraints → service-layer invariant, explicitly documented rather than silently assumed), this is enforced at the service layer: before inserting a new `apply` row for a `ledger_entry_id`, the service checks that no unreversed `apply` row already exists for it (i.e., every prior `apply` for that `ledger_entry_id` has a matching `reverse`).

**Reversal auditability:** rows are immutable and append-only (no `updated_at`) — a reversal is a **new row** with `direction=reverse` and `reverses_reconciliation_entry_id` pointing at the `apply` row it cancels. The original `apply` row is never altered or deleted; the full history (who reconciled, when, and — if later reversed — who reversed and when, via the reversal row's own `created_at`/implicit actor trail) is reconstructable by reading the row sequence for a given `ledger_entry_id`, exactly the same audit pattern used for cost-settlement allocations (§5) and advance settlements (§6.3).

**Deterministic `posted_unreconciled → posted_reconciled` transition (restated, now correctly scoped):** a `treasury_financial_documents` row transitions to `posted_reconciled` when every ledger entry with `source_type=financial_document AND source_id=<this document>` has a currently-active (net apply, per the formula above) `treasury_reconciliation_entries` row. Route-leg-sourced entries are reconciled through the same table but do not drive any `treasury_financial_documents` status transition (there is no such document in the exclusive-posting case, §4.2) — their reconciliation state is queried directly against `treasury_ledger_entries`/`treasury_reconciliation_entries` when needed (e.g., "is this route fully reconciled" = every one of its legs' entries has a currently-active reconciliation link), with no new status column required.

Index: `(reconciliation_id)`, `(ledger_entry_id)`.

---

## 8. Fix #4 — complete same-tenant / same-project referential invariants

Two enforcement tiers, used consistently throughout this document:

**Tier A — composite DB-enforced FK `(tenant_id, id)`.** Wherever a real single-table foreign key exists, it is declared as a **composite** foreign key against `(tenant_id, id)` on the target table (requires the target table's `(tenant_id, id)` to carry a unique index, which the primary key already provides once `tenant_id` is included in it, or a secondary unique index otherwise) — this makes a cross-tenant reference a **database-level constraint violation**, not merely an application bug. Applied to: `treasury_wallets.custodian_party_id`, every `*_wallet_id` on `treasury_financial_documents`/`treasury_payment_route_legs`, `treasury_payment_routes.linked_source_id` (financial_document case only), `treasury_payment_route_legs.payment_route_id`, `treasury_cost_settlement_allocations.financial_document_id`/`advance_settlement_id`, `treasury_advances.financial_party_id`/`originating_financial_document_id`, `treasury_advance_settlements.advance_id`/`financial_document_id`, `treasury_expense_approvals.financial_document_id`, `treasury_reconciliations.wallet_id`, `treasury_reconciliation_entries.reconciliation_id`/`ledger_entry_id`, `treasury_fund_chain_members`' FK half (see below).

**Tier B — application-layer validation (genuinely polymorphic, no single target table, so no DB FK is possible at all).** For each, the rule is stated explicitly:

| Reference | Rule |
|---|---|
| `treasury_cost_settlement_allocations.cost_source_id` (→ `ContractExpense`/`MaterialReceiptLine`) | Same `tenant_id`; the referenced row's owning `Contract`/`Project` must equal the allocation's own `financial_document`'s (or, via `advance_settlement_id`, that settlement's advance's) `project_id` |
| `treasury_payment_routes.linked_source_id` (→ `ContractPayment`, when `linked_source_type=contract_payment`) | Same `tenant_id`; same project (via the `ContractPayment`'s `Contract.project_id`) |
| `treasury_ledger_entries.source_id` (→ `treasury_financial_documents` or `treasury_payment_route_legs`, per `source_type`) | Same `tenant_id`, and the resolved source's `project_id` must equal the wallet's `project_id` when the wallet is project-scoped |
| `treasury_fund_chain_members.member_id` (→ `treasury_financial_documents` or `treasury_payment_routes`, per `member_type`) | Same `tenant_id` as the parent `treasury_fund_chains` row; same `project_id` |

**Enforcement mechanism (Tier B):** a shared `TreasuryReferentialIntegrityService` validates every Tier-B reference at write time, before persistence — stated here as a binding design requirement for implementation, not implemented in this document. Tier A needs no such service — the database itself rejects the write.

---

## 9. `treasury_fund_chains` (full definition — was thin in prior revisions, now self-contained per Owner's request)

**`treasury_fund_chains`**: `id` (ulid, PK), `tenant_id`, `project_id`, `chain_reference` (free-form grouping key), `description`, timestamps. Explicitly **not** exact FIFO allocation — grouping only, per PR #245 §7.6's own stated scope (non-normative evidence, cited here as design context only).

**`treasury_fund_chain_members`**: `id` (ulid, PK), `tenant_id`, `fund_chain_id` (FK → `treasury_fund_chains(tenant_id, id)`, composite), `member_type` (enum: `financial_document` \| `payment_route`), `member_id` (polymorphic — Tier B, §8), timestamps. Unique: `(fund_chain_id, member_type, member_id)` — a given document/route appears in a given chain at most once.

---

## 10. `treasury_expense_approvals` (unchanged from Round 3, restated for self-containment)

Verified in Round 3 and unchanged here: `app/Models/DocumentApprovalEvent.php` is hard-coupled to `Document`/`DocumentVersion` (real FKs, direct queries in `validateCreation()`) — not reusable without modifying existing, shipped code. Resolved design: new additive table.

**`treasury_expense_approvals`**: `id` (ulid, PK), `tenant_id`, `financial_document_id` (FK → `treasury_financial_documents(tenant_id, id)`, composite), `event` (enum: submitted|approved|rejected|reopened), `from_status`, `to_status` (validated against `treasury_financial_documents.status`'s enum), `actor_id` (FK → `users`), `note` (nullable), `context` (json, nullable), `created_at` (append-only, no `updated_at`, mirroring `DocumentApprovalEvent`'s own append-only override pattern in a separate class rather than a shared base — a shared abstract append-only-event base is an available future refactor, not proposed here).

Index: `(tenant_id, financial_document_id)`, `(actor_id)`.

---

## 11. Fix #6 — exact table inventory and migration order

**Corrected count: 12 primary tables + 2 join tables = 14 `CREATE TABLE` statements.** (Round 3 claimed "15" while listing 14 — an arithmetic error in the summary line, not a hidden table; this document is self-contained, so the count below can be verified directly.)

**Primary tables (12):**
1. `treasury_financial_parties`
2. `treasury_wallets`
3. `treasury_financial_documents`
4. `treasury_ledger_entries`
5. `treasury_cost_settlement_allocations`
6. `treasury_payment_routes`
7. `treasury_payment_route_legs`
8. `treasury_fund_chains`
9. `treasury_advances`
10. `treasury_advance_settlements`
11. `treasury_expense_approvals`
12. `treasury_reconciliations`

**Join tables (2):**
13. `treasury_fund_chain_members`
14. `treasury_reconciliation_entries`

**Dependency-safe creation order** (every table created only after every table it holds a real, composite `(tenant_id, id)` FK to):
`treasury_financial_parties` (1) → `treasury_wallets` (2, → 1) → `treasury_financial_documents` (3, → 2) → `treasury_ledger_entries` (4, → 2, 3; `source_id` may forward-reference table 7, non-DB-FK) → `treasury_cost_settlement_allocations` (5, → 3, 10 — forward-references `treasury_advance_settlements`, non-DB-FK reasoning does **not** apply here since `advance_settlement_id` is a real composite FK per §8 Tier A; **actual required order: table 10 must be created before table 5**, see corrected order below) → `treasury_payment_routes` (6, → 3) → `treasury_payment_route_legs` (7, → 6, 2) → `treasury_fund_chains` (8, → none) → `treasury_advances` (9, → 1, 3) → `treasury_advance_settlements` (10, → 9, 3) → `treasury_expense_approvals` (11, → 3) → `treasury_reconciliations` (12, → 2) → `treasury_fund_chain_members` (13, → 8; polymorphic to 3 or 6, non-DB-FK) → `treasury_reconciliation_entries` (14, → 12, 4).

**Corrected linear order** (table 10 moved before table 5, since §5's `advance_settlement_id` composite FK requires `treasury_advance_settlements` to already exist — this dependency was implicit in v3 and is made explicit here):
1. `treasury_financial_parties`
2. `treasury_wallets`
3. `treasury_financial_documents`
4. `treasury_ledger_entries`
5. `treasury_payment_routes`
6. `treasury_payment_route_legs`
7. `treasury_fund_chains`
8. `treasury_advances`
9. `treasury_advance_settlements`
10. `treasury_cost_settlement_allocations` *(moved — depends on `treasury_advance_settlements`)*
11. `treasury_expense_approvals`
12. `treasury_reconciliations`
13. `treasury_fund_chain_members`
14. `treasury_reconciliation_entries`

No table in this order is ever created before a table it holds a real composite FK to. **No migration file exists yet — this is the proposed order for when Gate 3 authorizes writing them.**

---

## 12. Verification against every held-constant item

- A3 (cost/cash separation): unchanged — cost stays in `ContractExpense`/`MaterialReceiptLine`, Treasury only ever posts the cash side, now with a stronger over-settlement cap (§6.2) using an unambiguous formula (§5.1).
- A4-a: zero references anywhere in this schema to `Component`/`Project` cost fields — unchanged, verified again against the full table list in §11.
- A.5: `treasury_cost_settlement_allocations`' many-to-many shape unchanged; cardinality still supports one-cost/many-payments, one-payment/many-costs, partial allocation — now with a precise reversal formula (§5.1) instead of an ambiguous status field.
- B2: `ContractPayment` untouched — zero writes, zero real FK from any Treasury table into it (only the Tier-B polymorphic reference in `treasury_payment_routes`, unchanged in kind from Round 2/3).
- B2-T: conservation invariant (§3, unchanged from Round 2's correction) — allocation-level, never leg-summed. The exclusive-posting fix (§2.1/§4.2) makes the "no second commercial-payment document" rule structurally enforced, not just documented.
- C (no-double-posting): strengthened materially this round — idempotency is now a DB constraint (§4.2), not just a stated intention.
- D: zero references to `ReportPageController::cashflow()` anywhere in this schema — unchanged.
- Zero changes to existing tables/data: confirmed — every composite FK in §8 Tier A points *from* a Treasury table *to* another Treasury table or to a genuinely new column on one; no existing table (`contracts`, `contract_expenses`, `contract_payments`, `material_receipts`, `material_receipt_lines`, `components`, `projects`) gains a column, index, or constraint anywhere in this document.

---

## 13. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu, không phải authorization cho migration/model/code thật.
- Nếu Owner Request changes: sẽ tạo `02-design-v5.md` (supersedes bản này), không sửa `02-design-v4.md` sau khi có quyết định.
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này, giữ nguyên phần architecture đã approved làm lịch sử.

## 14. Loại trừ phạm vi
Kế thừa nguyên vẹn từ mọi round trước: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment.

## Decision Needed
**Owner đã chọn: Request changes**, tại PR #263 head `bd75f5f063605d80ac6289e92cdf0e856b5c0f98` (2026-08-16) — 6 điểm bắt buộc: remove impossible conditional FK on `linked_source_id`; freeze posting-path choice before posting; exact reversal invariant (same-subject, exact-amount, at-most-once, no-reverse-of-reverse); concurrency-safe serialization for 5 named aggregate checks; explicit actor on reconciliation reversal; binding composite-FK-target index requirement. Architecture set A3+A4-a+A.5/B2+B2-T/C/D confirmed unchanged. Chi tiết nguyên văn tại `decision_provenance.owner_response_reference`. **This packet (`02-design-v4.md`) is now frozen — no further content edits.** `docs/owner-decisions/GAP-037/02-design-v5.md`, self-contained, addressing these 6 points, follows in the next commit.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật hay chi tiết implementation (transaction boundaries, service/class names, exact `TreasuryReferentialIntegrityService` implementation). Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved, không mở lại bởi revision này. Owner cũng không được yêu cầu duyệt overpayment/prepayment semantics — §6.3's carve-out chỉ xác nhận phạm vi, không đề xuất thiết kế cho trường hợp đó.
