---
work_id: GAP-037
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/superpowers/specs/2026-08-16-gap037-project-treasury-architecture-decisions.md
  plan: null
  branch: docs/GAP-037-project-treasury-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/263
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-16T17:01:00+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design-v2.md
superseded_by: null
timestamps:
  created_at: "2026-08-16T17:01:00+07:00"
  updated_at: "2026-08-16T17:01:00+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 3 — Corrected Schema Proposal

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** (`docs/owner-decisions/GAP-037/02-design.md`, frozen) — **A3 + A4-a + A.5 / B2 + B2-T / C / D**, not reopened by this revision. Revision 2 (`02-design-v2.md`, frozen, superseded by this file) proposed a first schema that the Owner found 8 concrete gaps in. This revision fixes exactly those 8 gaps, changing nothing about the approved architecture itself. Still Gate 2 — a proposal, not implementation. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet.

This document is self-contained (does not require re-reading v2) except where explicitly marked "unchanged from v2."

---

## 0. What changed vs. v2, at a glance

| # | Owner's finding | Fix |
|---|---|---|
| 1 | No defined relationship between route legs and ledger entries | `treasury_ledger_entries` gets a polymorphic `source_type`/`source_id` (financial_document \| payment_route_leg) — a route leg posts ledger entries directly, no financial document required |
| 2 | No signed-amount convention | `treasury_ledger_entries` gets `direction` (debit\|credit) + unsigned `amount` + `reversal_of_entry_id` |
| 3 | Single `wallet_id` insufficient for two-sided movements | `treasury_financial_documents.wallet_id` replaced by nullable `source_wallet_id` + `destination_wallet_id`, populated from draft |
| 4 | "Latest leg = full route amount" is wrong for partial routing | Custody is now derived from signed ledger entries per wallet (§5.3), not from route-leg inspection at all |
| 5 | Approval-audit reuse deferred, and the reuse claim was unverified | Verified: `DocumentApprovalEvent` is hard-coupled to `Document`/`DocumentVersion` (real FKs, direct model queries in `validateCreation()`) — **not reusable without changing existing code**. Resolved: new additive `treasury_expense_approvals` table, same audit shape, own class |
| 6 | Cross-tenant/project invariants not stated | New §7, applied uniformly to every polymorphic/non-FK reference |
| 7 | No cap preventing over-settlement of a cost record | New invariant: cumulative active allocations per cost source ≤ that record's own amount, net of reversals (§4.3) |
| 8 | JSON id-list for reconciliation | Replaced with a proper `treasury_reconciliation_entries` join table, whole-entry reconciliation, deterministic status-transition rule (§6) |

**Updated table count: 13 primary tables + 2 pure join tables = 15 `CREATE TABLE` statements** (v2 had 12 + 1 implied join = 13; net addition: `treasury_reconciliation_entries`, plus `treasury_expense_approvals` is now a confirmed table rather than a "pending" one — see §8 for the full migration order).

---

## 1. `treasury_financial_parties` and `treasury_wallets` — unchanged from v2

**`treasury_financial_parties`**: `id` (ulid), `tenant_id`, `party_type` (investor|intermediary|owner|employee|labour|supplier|subcontractor|authority|other), `name`, `linked_account_id` (nullable), `linked_user_id` (nullable), timestamps. Index: `(tenant_id, party_type)`.

**`treasury_wallets`**: `id` (ulid), `tenant_id`, `project_id` (nullable), `wallet_type` (company_bank|company_cash|owner_personal|employee_cash|employee_bank|intermediary_control|project_wallet|other), `name`, `custodian_party_id` (nullable FK), timestamps. No `balance` column — always derived (see §5). Index: `(tenant_id, project_id, wallet_type)`.

---

## 2. `treasury_financial_documents` — fix #3 (source/destination wallets)

| Column | Type | Notes |
|---|---|---|
| `id` | ulid | |
| `tenant_id` | string | |
| `project_id` | string | |
| `document_type` | enum: funding\|internal_transfer\|expense\|owner_contribution\|advance\|advance_return\|reversal\|adjustment | |
| `status` | enum: draft\|submitted\|approved\|rejected\|posted_unreconciled\|posted_reconciled\|reversed | |
| `amount` | decimal | always positive — the document's own signed direction is expressed via which ledger entries it generates (§5), not via a signed amount here |
| `source_wallet_id` | nullable FK → `treasury_wallets.id` | **replaces v2's single `wallet_id`.** Set for any movement where money leaves a wallet: `expense`, `internal_transfer`, `advance`, `adjustment` (decrease case) |
| `destination_wallet_id` | nullable FK → `treasury_wallets.id` | Set for any movement where money enters a wallet: `funding`, `internal_transfer`, `owner_contribution`, `advance_return`, `adjustment` (increase case) |
| `counterparty_id` | nullable FK → `treasury_financial_parties.id` | |
| `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable) | | |
| `reversed_document_id` | nullable self-ref | |
| `replacement_document_id` | nullable self-ref | |
| timestamps | | |

**Binding population rule, enforced from `draft` creation, before any posting:**
- `internal_transfer`: **both** `source_wallet_id` and `destination_wallet_id` required, and must differ.
- `expense`, `advance`: `source_wallet_id` required, `destination_wallet_id` must be null (the counterparty receiving funds is a `financial_party`, not a Treasury-tracked wallet).
- `funding`, `owner_contribution`, `advance_return`: `destination_wallet_id` required, `source_wallet_id` must be null.
- `reversal`: mirrors whichever fields the reversed document had, with roles swapped (a reversal of an `expense` populates `destination_wallet_id` with the original's `source_wallet_id`, returning funds).
- `adjustment`: exactly one of `source_wallet_id`/`destination_wallet_id` set, per whether it's a decrease or increase adjustment.

This is now knowable and validatable **at draft time**, before posting — satisfying the Owner's requirement directly, and it is what feeds §5's ledger-entry generation.

Index: `(tenant_id, project_id, document_type, status)`, `(source_wallet_id)`, `(destination_wallet_id)`.

---

## 3. `treasury_cost_settlement_allocations` — unchanged shape, additional invariant in §4.3

Unchanged from v2: `id`, `tenant_id`, `financial_document_id` (FK), `cost_source_type` (contract_expense|material_receipt_line), `cost_source_id` (non-DB-FK, application-validated), `allocated_amount`, `status` (active|reversed), `reversed_allocation_id` (nullable self-ref), timestamps. Index: `(financial_document_id)`, `(cost_source_type, cost_source_id)`.

---

## 4. Fix #7 — preventing cost over-settlement

Two independent invariants now apply together (v2 only had the first):

**4.1 Per-document invariant (unchanged from v2):** for a `document_type: expense` financial document, `SUM(allocated_amount WHERE status = 'active' AND financial_document_id = <this document>) = <this document>.amount` before it may leave `draft`/`submitted` and post.

**4.2 Per-cost-source invariant (new — the over-settlement fix):**
```
SUM(allocated_amount
    WHERE status = 'active'
    AND cost_source_type = <X> AND cost_source_id = <Y>)
  <= canonical_incurred_amount(X, Y)
```
where `canonical_incurred_amount` is `ContractExpense.amount` or the `MaterialReceiptLine`-derived cost (`quantity_received × unit_cost`, per `Api\ContractController::costSummary()`'s existing computation — read, never duplicated). Enforced at the application/service layer at the moment a new allocation is created (same enforcement tier as §6.1's route conservation check — a cross-table sum, not a database `CHECK` constraint). Net of reversals: a `reversed` allocation does not count toward the sum.

**4.3 Explicit prepayment/overpayment carve-out:** this proposal does **not** implement any overpayment or prepayment semantics. If cumulative active allocation would exceed the canonical incurred amount, the write is rejected — full stop, no automatic "credit balance" or "prepaid" state is created. In particular: **a prepayment to a supplier before a `MaterialReceiptLine` exists must never be modeled as a cost-settlement allocation** (there is no cost record yet to allocate against — `cost_source_id` must reference an existing row, and an allocation cannot be created for a receipt that hasn't happened). The correct model for genuine prepayment is `treasury_advances` (an advance to a supplier `financial_party`, settled later once the material is received and a real `MaterialReceiptLine`-backed allocation can be created) — this is already the shape `treasury_advances`/`treasury_advance_settlements` exist for; no new table needed. If the Owner later wants a distinct overpayment/credit-balance semantic, that is explicitly out of scope here and would need its own approved design.

---

## 5. `treasury_ledger_entries` — fixes #1, #2, #4

| Column | Type | Notes |
|---|---|---|
| `id` | ulid | |
| `tenant_id` | string | |
| `source_type` | enum: `financial_document` \| `payment_route_leg` | **fix #1** — which kind of event produced this entry |
| `source_id` | string | FK-shaped but not a DB FK across two possible tables (same non-enforced pattern as `cost_source_id`), validated against `source_type` at the application layer |
| `wallet_id` | FK → `treasury_wallets.id` | |
| `direction` | enum: `debit` \| `credit` | **fix #2** — debit decreases the wallet's balance, credit increases it |
| `amount` | decimal, always positive | the unsigned magnitude; sign comes from `direction`, never from a signed value in this column |
| `entry_type` | enum: wallet_funding\|wallet_expense_settlement\|wallet_internal_transfer\|wallet_owner_financing\|wallet_advance_open\|wallet_advance_settlement\|wallet_route_custody | narrative/reporting classification, orthogonal to `direction` |
| `posted_at` | timestamp | |
| `reversal_of_entry_id` | nullable self-ref | **fix #2** — a reversal entry has the opposite `direction` from the entry it reverses, the same `amount`, and points back here |
| `created_at` | timestamp | **no `updated_at`** — rows are append-only, immutable after insert, exactly as before |

### 5.1 Fix #1 — the ledger-source bridge for B2-T
A `treasury_payment_route_legs` row (§6) posts its own `treasury_ledger_entries` rows directly, with `source_type = payment_route_leg` and `source_id = <the leg's id>` — **no `treasury_financial_documents` row is created for a `ContractPayment`-linked route's custody movement.** This is the concrete mechanism that satisfies B2-T's "no second commercial-payment/funding document" rule: the commercial fact stays solely in `ContractPayment`; the custody movement is recorded as ledger entries sourced from the route leg, never wrapped in a Treasury document that would look like an independent funding event. Treasury-native movements (`expense`, `internal_transfer`, `advance`, `owner_contribution`, `funding` not linked to a `ContractPayment`) continue to post via `source_type = financial_document`.

### 5.2 Fix #2 — rebuilding wallet balance, deterministically
```
wallet_balance(wallet_id) =
    SUM(amount WHERE wallet_id = X AND direction = 'credit')
  - SUM(amount WHERE wallet_id = X AND direction = 'debit')
```
Every two-sided movement (an `internal_transfer` document, or a route leg moving between two wallets) posts **exactly two** ledger entries: a `debit` at the source wallet and a `credit` at the destination wallet, both sharing the same `source_type`/`source_id` (so they're recognizable as a pair without needing a separate `transfer_group_id`). A one-sided movement (`expense`, `funding`, `advance`) posts exactly one entry. A reversal posts one or two new entries (mirroring the original count) with `direction` flipped and `reversal_of_entry_id` set — the original entries are never altered or deleted, and the balance formula above naturally nets the reversal out because it's an equal-and-opposite entry, not a mutation.

### 5.3 Fix #4 — correct partial-route custody
Custody is no longer derived by inspecting "the latest leg" at all — that heuristic is retired. Instead: **current custody at a given wallet, for a given route, is the net signed balance of ledger entries where `source_type = payment_route_leg` and the leg belongs to that route, grouped by `wallet_id`** — i.e., exactly the same balance formula as §5.2, scoped to one route's legs.

Worked example matching the Owner's own case: route allocates $100 (`total_allocated_amount = 100`, conservation checked per §6.1, unchanged from B2-T). Leg 1: A→C, amount 100 → posts credit 100 at C (debit 100 at A, if A is a tracked wallet; if A is external/untracked, only the credit at C posts). Leg 2: C→Y, amount 60 (a **partial** onward movement — leg amounts are no longer required to equal the full route amount, this is the other half of the fix) → posts debit 60 at C, credit 60 at Y. Resulting custody: C = 100 − 60 = **40**, Y = **60**. Total across the route's wallets = 100, correctly conserved, correctly split — matching the Owner's required example exactly, with no special-case logic beyond the standard balance formula.

**`treasury_payment_route_legs`** (revised): `id`, `tenant_id`, `payment_route_id` (FK), `sequence_no`, `from_wallet_id` (nullable), `to_wallet_id` (FK), `amount` (decimal — **no longer required to equal the route's `total_allocated_amount`**; must not exceed the current custody available at `from_wallet_id` for this route, per §5.3's balance formula, checked at write time), `status` (in_transit|settled|reversed), `occurred_at`, timestamps. A `reversed` leg posts the offsetting ledger entries per §5.2's reversal rule; it does not delete or alter its original entries.

---

## 6. `treasury_payment_routes` — conservation invariant unchanged from B2-T, restated for clarity

**Unchanged from v2/B2-T:** `id`, `tenant_id`, `project_id`, `linked_source_type` (financial_document|contract_payment), `linked_source_id`, `total_allocated_amount`, `status` (planned|partial|completed|cancelled), timestamps.

### 6.1 Conservation invariant (unchanged from Round 2's correction, restated because §5.3 now changes how legs work but not this rule)
```
SUM(treasury_payment_routes.total_allocated_amount
    WHERE linked_source_type = 'contract_payment'
    AND linked_source_id = <ContractPayment.id>)
  <= ContractPayment.amount
```
Still an allocation-level check on `total_allocated_amount`, never a sum of legs — legs remain non-additive movement history, now precisely defined via §5.2/§5.3's ledger-entry mechanism rather than a leg-amount heuristic.

---

## 7. Fix #6 — cross-tenant / cross-project referential invariants (new, applies uniformly)

Every non-DB-enforced polymorphic reference in this schema, and every same-tenant FK reference that could still mismatch on `project_id`, is subject to this uniform rule, checked at the application/service layer at write time — **`TenantScope`'s query-level filtering alone is explicitly insufficient**, since it only constrains what a query *returns*, not what a write is allowed to *reference*:

| Reference | Rule |
|---|---|
| `treasury_cost_settlement_allocations.cost_source_id` → `ContractExpense`/`MaterialReceiptLine` | The referenced row's `tenant_id` must equal the allocation's `tenant_id`, and its owning `Contract`/`Project` must equal the `financial_document`'s `project_id` |
| `treasury_payment_routes.linked_source_id` → `ContractPayment` (when `linked_source_type = contract_payment`) | Same tenant, same project (via the `ContractPayment`'s `Contract.project_id`) |
| `treasury_financial_documents.source_wallet_id` / `destination_wallet_id` → `treasury_wallets` | Same tenant always; same `project_id` required unless the wallet is a company-level wallet (`project_id IS NULL`) |
| `treasury_payment_route_legs.from_wallet_id` / `to_wallet_id` → `treasury_wallets` | Same rule as above |
| `treasury_advance_settlements.linked_allocation_id` → `treasury_cost_settlement_allocations` | Same tenant, same advance's project |
| `treasury_reconciliation_entries.ledger_entry_id` → `treasury_ledger_entries` (§8) | Same tenant |

**Enforcement mechanism (schema-proposal-level, not yet code):** a shared `TreasuryReferentialIntegrityService` (or equivalent) validates every such reference at the point of write, before any row is persisted, independent of and in addition to `TenantScope`'s read-side filtering. This is stated here as a binding design requirement for the eventual implementation, not implemented in this document.

---

## 8. Fix #8 — reconciliation model

**Decision: whole-entry reconciliation, not partial.** A `treasury_ledger_entries` row is either fully reconciled or not — no fractional/split reconciliation of a single entry's amount. This avoids a second layer of partial-allocation complexity on top of the ones already introduced by §3/§4 and §6, and nothing in the approved architecture (A–D) requires partial-entry reconciliation.

**`treasury_reconciliations`** (unchanged from v2): `id`, `tenant_id`, `wallet_id` (FK), `reconciliation_type` (bank|cash|receipt|intermediary_confirmation|other), `external_reference`, `reconciled_at`, `reconciled_by`, timestamps.

**`treasury_reconciliation_entries`** (new — replaces v2's `reconciled_ledger_entry_ids` JSON array):

| Column | Type | Notes |
|---|---|---|
| `id` | ulid | |
| `tenant_id` | string | |
| `reconciliation_id` | FK → `treasury_reconciliations.id` | real, DB-enforced FK |
| `ledger_entry_id` | FK → `treasury_ledger_entries.id` | real, DB-enforced FK — **this alone gives the referential integrity the JSON array couldn't** |
| `status` | enum: `active` \| `reversed` | matches the reversal-auditability pattern used everywhere else in this schema (§3, §4) |
| `reversed_at` | nullable timestamp | |
| `created_at` | timestamp | |

Unique constraint: `(ledger_entry_id)` where `status = 'active'` — a given ledger entry can have at most one *active* reconciliation-entry link at a time (it may have a reversed one from a prior, later-undone reconciliation attempt, kept for audit).

**Scope note:** this reconciliation model applies to `treasury_financial_documents`-sourced ledger entries. Route-leg-sourced entries (§5.1, B2-T custody movements) are not separately "reconciled" through this mechanism — their own `treasury_payment_route_legs.status` (in_transit|settled|reversed) already tracks their lifecycle state, and introducing a second reconciliation concept for them would be a genuine double-tracking risk, not a missing feature.

**Deterministic `posted_unreconciled → posted_reconciled` rule:** a `treasury_financial_documents` row transitions from `posted_unreconciled` to `posted_reconciled` if and only if **every** `treasury_ledger_entries` row with `source_type = financial_document AND source_id = <this document>` has at least one `active` `treasury_reconciliation_entries` row. This is a checkable, deterministic condition — not left as an implementation ambiguity.

Index: `(reconciliation_id)`, `(ledger_entry_id)`.

---

## 9. Fix #5 — `treasury_expense_approvals`, resolved (not deferred)

**Verified claim, per Owner's instruction not to assert reuse without proof:** `app/Models/DocumentApprovalEvent.php` has `document_id` as a required (non-nullable) field with a real `belongsTo(Document::class)` relation, `document_version_id` with a real `belongsTo(DocumentVersion::class)` relation, and its `validateCreation()` method directly queries `Document::class` and `DocumentVersion::class` by name — there is no polymorphic target field, no interface abstraction, nothing that would let a `treasury_financial_documents` row stand in for a `Document` without modifying this existing, already-shipped class. **Reuse is not viable without an existing-model change, which is out of scope. The Owner's fallback applies: a concrete additive approach.**

**Resolved design: new table, same proven shape, own class.**

**`treasury_expense_approvals`**: `id` (ulid), `tenant_id`, `financial_document_id` (FK → `treasury_financial_documents.id`, real DB FK — unlike `DocumentApprovalEvent`'s Document/DocumentVersion pair, there is only one target table here, so a real FK is possible and used), `event` (enum: submitted|approved|rejected|reopened), `from_status`, `to_status` (both validated against `treasury_financial_documents.status`'s canonical enum), `actor_id` (FK → `users`), `note` (nullable), `context` (json, nullable), `created_at` (append-only — no `updated_at`, mirroring `DocumentApprovalEvent`'s own append-only `save()`/`delete()` override pattern, which this new class replicates rather than inherits, since it's a separate class rather than a shared abstract base — introducing a shared abstract append-only-event base class is an available future refactor, explicitly not proposed here to keep this addition minimal and additive).

Index: `(tenant_id, financial_document_id)`, `(actor_id)`.

---

## 10. Verification against C (no-double-posting) and D (cashflow), restated for the corrected schema

- Cost stays in `ContractExpense`/`MaterialReceiptLine`, capped from over-settlement by §4.2 — a stronger guarantee than v2 had.
- Commercial payment fact stays solely in `ContractPayment`; B2-T custody movements now post ledger entries with zero risk of an accidental second commercial-payment document, because §5.1's bridge makes creating one structurally unnecessary, not just discouraged.
- Every ledger entry has a deterministic sign (§5.2); wallet balances and route custody (§5.3) are both derivable by the same one formula, applied at different scopes — no separate "custody" logic to keep in sync with "balance" logic.
- Reconciliation (§8) has real FKs and a deterministic status-transition rule, not a JSON blob.
- `ReportPageController::cashflow()`: still zero references anywhere in this schema — unchanged from v2, D is untouched by this revision.
- `Component`/`Project` cost fields: still zero references anywhere in this schema — unchanged from v2, A4-a is untouched by this revision.

---

## 11. Updated table inventory and migration order (per Owner's explicit request to restate exact count)

**13 primary tables + 2 pure join tables = 15 `CREATE TABLE` statements** (v2 had 13; net change: `treasury_reconciliation_entries` added, `treasury_expense_approvals` confirmed rather than conditional):

1. `treasury_financial_parties`
2. `treasury_wallets` (→ 1)
3. `treasury_financial_documents` (→ 2)
4. `treasury_ledger_entries` (→ 3; `source_id` when `source_type=payment_route_leg` is forward-referenced to table 6, non-DB-FK, application-validated — see note below)
5. `treasury_cost_settlement_allocations` (→ 3)
6. `treasury_payment_routes` (→ 3; polymorphic, non-FK reference to `contract_payments`)
7. `treasury_payment_route_legs` (→ 6, 2)
8. `treasury_fund_chains` (→ 3, 6)
9. `treasury_fund_chain_members` (join table, → 8)
10. `treasury_advances` (→ 1, 3)
11. `treasury_advance_settlements` (→ 10, 3, 5)
12. `treasury_expense_approvals` (→ 3) — **now confirmed, not conditional**
13. `treasury_reconciliations` (→ 2)
14. `treasury_reconciliation_entries` (join table, → 13, 4) — **new**

**Note on table 4 vs. table 6 ordering:** `treasury_ledger_entries` (table 4) has a polymorphic `source_id` that may point to `treasury_payment_route_legs` (table 7), created *after* it in this order. Since this reference is explicitly non-DB-FK (application-validated, same pattern as `cost_source_id`), this is not a migration-ordering problem — no foreign key constraint is being declared prematurely. Stated explicitly so it isn't mistaken for an oversight.

No table in this order is ever created before a table it has a **real, DB-enforced** FK to. **No migration file exists yet — this is the proposed order for when Gate 3 authorizes writing them.**

---

## 12. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu, không phải authorization cho migration/model/code thật.
- Nếu Owner Request changes: sẽ tạo `02-design-v4.md` (supersedes bản này), không sửa `02-design-v3.md` sau khi có quyết định.
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này, giữ nguyên phần architecture đã approved làm lịch sử.

## 13. Loại trừ phạm vi
Kế thừa nguyên vẹn từ mọi round trước: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment.

## Decision Needed
Owner chọn một: Approve corrected schema proposal to proceed toward Gate 3 preparation / Request further changes / Decline.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật hay chi tiết implementation (transaction boundaries, service/class names). Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved, không mở lại bởi revision này. Owner cũng không được yêu cầu duyệt overpayment/prepayment semantics — §4.3 chỉ carve-out phạm vi, không đề xuất thiết kế cho trường hợp đó.
