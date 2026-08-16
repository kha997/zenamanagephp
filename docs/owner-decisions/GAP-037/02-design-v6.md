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
  recorded_at: "2026-08-16T18:05:13+07:00"
  owner_response_reference: "Owner Gate 2 Schema Proposal Revision 6 decision — REQUEST CHANGES, recorded in-session on 2026-08-16 against reviewed PR #263 head 22de0cc8af3a1ed9a428bc6506aa426a507b33e8: 'GAP-037 — Gate 2 Schema Proposal Revision 6 — Owner Decision: REQUEST CHANGES. Tôi, Owner, yêu cầu chỉnh sửa schema proposal tại PR #263, reviewed head 22de0cc8af3a1ed9a428bc6506aa426a507b33e8. Tôi xác nhận các correction của Revision 6 đã xử lý đúng yêu cầu trước và architecture set A3 + A4-a + A.5 / B2 + B2-T / C / D vẫn approved, frozen và không được mở lại. Revision 7 chỉ cần xử lý ba điểm: 1. Restore Tier-B tenant/project invariants in the self-contained packet — Restate binding same-tenant/same-project validation for linked_contract_payment_id; cost_source_contract_expense_id; cost_source_material_receipt_line_id. Single-column existing-table FKs prove existence only and do not replace tenant/project ownership validation. 2. Make advance-settlement reversal compatible with settlement completeness — Separate completeness semantics for apply and reverse approved-expense settlements. Apply settlement must be created with cost allocations whose total equals its amount. Reverse settlement must target an existing apply settlement and atomically create exact compensating reverse allocations for that settlement's still-active cost allocations, with equal magnitude. Do not apply one positive net allocations = settlement.amount equation indiscriminately to both apply and reverse rows. Preserve: 0 <= outstanding_advance <= advance.amount and per-cost-source allocation bounds after the transaction. 3. Define external first-leg custody semantics — For a route leg with from_wallet_id IS NULL, do not perform a nonexistent wallet-custody check. Treat it as an external-entry leg whose amount is bounded by the route's remaining economic allocation, under the same parent-route serialization. For legs with a real from_wallet_id, retain v6's wallet-custody check. External-entry + all subsequent movements must conserve total_allocated_amount; historical legs remain non-additive and must never reconstruct client/investor-paid amount. Keep everything else from v6 unchanged, including: 14-table count; typed nullable FKs + XOR CHECKs; frozen posting path; signed ledger + exactly-once posting; corrected ledger reversal; event-log reversal model; two fund-chain uniqueness indexes; 12 composite-FK targets; settlement/cost bounds; route locking; MySQL FOR UPDATE / SQLite BEGIN IMMEDIATE; reconciliation actor and whole-entry reconciliation; zero changes to existing tables/data; PR #245/#257 untouched; no migration/model/controller/service/route/UI/tests; no Gate 3. Ghi nhận REQUEST CHANGES này trước bằng governance-record-only commit vào 02-design-v6.md; freeze v6; sau đó tạo self-contained 02-design-v7.md, rerun required CI và quay lại awaiting_owner. Không được suy luận schema approval hoặc Gate 3 authorization.'"
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design-v5.md
superseded_by: "docs/owner-decisions/GAP-037/02-design-v7.md"
timestamps:
  created_at: "2026-08-16T17:54:33+07:00"
  updated_at: "2026-08-16T18:05:13+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 6 — Precision Fixes (Self-Contained)

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** (`docs/owner-decisions/GAP-037/02-design.md`, frozen) — **A3 + A4-a + A.5 / B2 + B2-T / C / D**, not reopened. Fully self-contained. Still Gate 2 — a proposal, not implementation. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet.

**Database compatibility (unchanged verification from Round 5):** MySQL for production/dev (`config/database.php`, `.env.example`), SQLite for the test suite (`.env.testing`, `phpunit.xml`). Every mechanism below is chosen to work on both, with engine differences called out explicitly rather than glossed over — this round corrects one place (§10) where that discipline slipped.

---

## 0. What changed vs. v5, at a glance

| # | Owner's finding | Fix |
|---|---|---|
| 1 | `treasury_cost_settlement_allocations` and `treasury_reconciliation_entries` each have a composite **self**-FK (`reverses_allocation_id`, `reverses_reconciliation_entry_id`) but were missing from the target-index list | Both added; target-index count corrected from 10 to **12** (§13) |
| 2 | Ledger reversal was described using the `apply`/`reverse` vocabulary that belongs to the *other* three tables | §9 rewritten: `treasury_ledger_entries` reversal is stated entirely in terms of its own `debit`/`credit` + `reversal_of_entry_id` fields — same wallet/source/amount, **opposite** direction, at-most-once, no reversal-of-reversal. The shared `apply`/`reverse` rule (§9.2) now applies only to the three event-log tables |
| 3 | `UNIQUE(fund_chain_id, member_financial_document_id, member_payment_route_id)` doesn't prevent duplicates, because the always-`NULL` third/second column never collides | Replaced with **two** separate unique indexes: `(fund_chain_id, member_financial_document_id)` and `(fund_chain_id, member_payment_route_id)` — each activates only for the rows where that column is non-null, together giving complete duplicate protection on both engines |
| 4 | Settlement conservation bounds not fully restated | §6 rewritten with every bound explicit: per-document equality, per-cost-source `0 ≤ net ≤ incurred`, per-advance-settlement equality, `0 ≤ outstanding ≤ advance.amount`, all rejected-on-violation |
| 5 | Route-leg custody writes had no concurrency protection | New 6th row in §11's aggregate-check table: lock the parent route, recompute custody, validate, persist leg + ledger entries atomically |
| 6 | SQLite doesn't have row-level `SELECT ... FOR UPDATE` | §11 corrected: MySQL uses `SELECT ... FOR UPDATE`; SQLite uses `BEGIN IMMEDIATE` (whole-database exclusive-write serialization) — stated as two genuinely different mechanisms achieving the same race-prevention goal, not one mechanism assumed portable |

---

## 1. `treasury_financial_parties` and `treasury_wallets` — unchanged from v5

**`treasury_financial_parties`**: `id` (ulid, PK), `tenant_id`, `party_type`, `name`, `linked_account_id` (nullable), `linked_user_id` (nullable), timestamps. **Unique: `(tenant_id, id)`.** Index: `(tenant_id, party_type)`.

**`treasury_wallets`**: `id` (ulid, PK), `tenant_id`, `project_id` (nullable), `wallet_type`, `name`, `custodian_party_id` (nullable, composite FK), timestamps. **Unique: `(tenant_id, id)`.** Index: `(tenant_id, project_id, wallet_type)`.

---

## 2. `treasury_financial_documents` — unchanged from v5 (posting-path freeze)

`id`, `tenant_id`, `project_id`, `document_type` (funding|internal_transfer|expense|owner_contribution|advance|advance_return|reversal|adjustment), `status` (draft|submitted|approved|rejected|posted_unreconciled|posted_reconciled|reversed), **`posting_path`** (nullable enum: `direct`|`via_route`, set exactly once, never updated again), `amount`, `source_wallet_id` (nullable, composite FK), `destination_wallet_id` (nullable, composite FK), `counterparty_id` (nullable, composite FK), `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable), `reversed_document_id` (nullable self-ref), `replacement_document_id` (nullable self-ref), timestamps.

Population rule unchanged: `internal_transfer` needs both wallets; `expense`/`advance` need source only; `funding`/`owner_contribution`/`advance_return` need destination only; `reversal` mirrors with roles swapped; `adjustment` needs exactly one.

**Posting-path freeze (unchanged):** a route may only attach (§4) while `posting_path IS NULL` and `status` is unposted; attaching sets `posting_path = via_route` in the same transaction (§11, item 5); reaching posting with no route sets `posting_path = direct` in the same transaction (§11, item 6). Immutable once set; no detach/relink path exists.

**Unique: `(tenant_id, id)`.** Index: `(tenant_id, project_id, document_type, status)`, `(source_wallet_id)`, `(destination_wallet_id)`, `(posting_path)`.

---

## 3. The typed-nullable-FK pattern — unchanged principle from v5

Every former polymorphic single-column reference in this schema is modeled as N nullable, typed, single-target FK columns plus a `CHECK` constraint requiring exactly one non-null. Applies at: `treasury_payment_routes` (§4), `treasury_ledger_entries` (§5), `treasury_cost_settlement_allocations` (§7), `treasury_fund_chain_members` (§8, with this round's uniqueness fix).

---

## 4. `treasury_payment_routes` and `treasury_payment_route_legs`

**`treasury_payment_routes`**: `id`, `tenant_id`, `project_id`, `total_allocated_amount`, `status` (planned|partial|completed|cancelled), `linked_financial_document_id` (nullable, composite FK → `treasury_financial_documents(tenant_id, id)`), `linked_contract_payment_id` (nullable, single-column FK → `contract_payments(id)`), timestamps. `CHECK ((linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL))`. **Unique index directly on `linked_financial_document_id`** (at most one route per document; multiple `NULL`s permitted for `contract_payment`-linked routes). **Unique: `(tenant_id, id)`.**

### 4.1 Conservation (unchanged)
```
SUM(total_allocated_amount WHERE linked_contract_payment_id = <ContractPayment.id>) <= ContractPayment.amount
```
Concurrency: `FOR UPDATE`/`BEGIN IMMEDIATE` on the `contract_payments` row (§11, item 1).

**`treasury_payment_route_legs`**: `id`, `tenant_id`, `payment_route_id` (composite FK), `sequence_no`, `from_wallet_id` (nullable, composite FK), `to_wallet_id` (composite FK), `amount`, `status` (in_transit|settled|reversed), `occurred_at`, timestamps. **Unique: `(tenant_id, id)`.**

### 4.2 Fix #5 — route-leg custody is now a concurrency-protected write
Before persisting a new leg: lock the parent `treasury_payment_routes` row (§11, item 6), recompute current custody at `from_wallet_id` for this route using §5.3's balance formula, validate `leg.amount ≤ available_custody`, then persist the leg **and** its resulting `treasury_ledger_entries` rows (§5.1) in the same transaction, under the same lock. This closes the gap the Owner identified: previously, custody availability was a *read-time* formula with no write-time protection — two concurrent legs against the same route could both read a passing custody figure before either committed.

---

## 5. `treasury_ledger_entries`

`id`, `tenant_id`, `source_financial_document_id` (nullable, composite FK), `source_payment_route_leg_id` (nullable, composite FK), `wallet_id` (composite FK), `direction` (enum: `debit` \| `credit`), `amount` (positive), `entry_type` (narrative enum, unchanged from v5), `posted_at`, `reversal_of_entry_id` (nullable self-ref, composite), `created_at` (append-only). `CHECK ((source_financial_document_id IS NULL) != (source_payment_route_leg_id IS NULL))`.

### 5.1 The ledger-source bridge (unchanged)
A route leg posts entries directly (`source_payment_route_leg_id` set) — no Treasury document wraps a `ContractPayment`-linked route's custody movement (B2-T). Treasury-native movements without a route post with `source_financial_document_id` set.

### 5.2 Idempotency (unchanged mechanism from v5)
```sql
original_posting_key = CASE
  WHEN reversal_of_entry_id IS NOT NULL THEN NULL
  WHEN source_financial_document_id IS NOT NULL THEN CONCAT('fd:', source_financial_document_id, ':', direction)
  WHEN source_payment_route_leg_id  IS NOT NULL THEN CONCAT('rl:', source_payment_route_leg_id,  ':', direction)
END
```
`UNIQUE` index on this generated column — verified on both MySQL (virtual/stored generated columns since 5.7/8.0) and SQLite (generated columns since 3.31), both permitting multiple `NULL`s.

### 5.3 Wallet balance and route custody (unchanged formula)
```
wallet_balance(wallet_id) = SUM(amount WHERE wallet_id=X, direction='credit') - SUM(amount WHERE wallet_id=X, direction='debit')
```
Route custody: same formula scoped to a route's legs' entries.

### 5.4 Fix #2 — ledger reversal, stated entirely in `treasury_ledger_entries`' own vocabulary
`treasury_ledger_entries` does **not** use `apply`/`reverse` — it never did structurally (its own field is `direction: debit|credit`), but v5's §10 prose incorrectly lumped it in with the three tables that do use `apply`/`reverse`, describing it as sharing their shape. It does not. The correct, self-contained rule for ledger reversal:

1. A reversal entry's `reversal_of_entry_id` must point to an entry where `reversal_of_entry_id IS NULL` (an *original* entry) — a reversal of a reversal is impossible to represent, by this rule alone (no separate enum needed, unlike the other three tables, because "original vs. reversal" is already fully captured by whether `reversal_of_entry_id` is null).
2. The reversal entry must carry the **same** `wallet_id` and the **same** `source_financial_document_id`/`source_payment_route_leg_id` pairing (whichever the original used) as the entry it reverses, and the **same** `amount` — copied at creation time, not left to caller discretion.
3. The reversal entry's `direction` must be the **opposite** of the original's (`debit` reversed by `credit`, `credit` reversed by `debit`) — this, not a same-direction "reverse" flag, is what makes the balance formula (§5.3) net correctly to zero for the pair.
4. **At most one reversal per original**: the `UNIQUE` index on `original_posting_key` (§5.2) already guarantees this indirectly for the *reversal's own* posting-key (which is `NULL` and therefore unconstrained) — the actual at-most-once guarantee for "this original has been reversed" comes from a **separate plain `UNIQUE` index directly on `reversal_of_entry_id`** (mirroring the mechanism used for the other three tables' `reverses_*_id` columns, §9.2) — multiple `NULL`s (every original entry) are unconstrained; a second attempt to reverse the *same* original collides.
5. No "reverse of a reverse": guaranteed structurally by rule 1 — there is nothing further to enforce beyond it.

Index: `(source_financial_document_id)`, `(source_payment_route_leg_id)`, `(wallet_id, posted_at)`, **`UNIQUE(reversal_of_entry_id)`** (fix #2's at-most-once mechanism). **Unique: `(tenant_id, id)`.**

---

## 6. Fix #4 — complete settlement conservation, fully restated

**`treasury_cost_settlement_allocations`** (full definition, §7) and **`treasury_advance_settlements`** (full definition, §7.3) both use the `apply`/`reverse` event-log pattern (§9.2). The following bounds are restated here, self-contained, exactly as the Owner specified:

**6.1 Net cost-allocation formula (unchanged shape from Round 4/5):**
```
net_allocation(cost_source) = SUM(allocated_amount WHERE direction='apply', cost_source=X)
                             - SUM(allocated_amount WHERE direction='reverse', cost_source=X)
```
where `cost_source` means the pairing `(cost_source_contract_expense_id, cost_source_material_receipt_line_id)` — exactly one of which is non-null per §7's `CHECK`.

**6.2 Direct-expense completeness (binding, checked before posting):**
```
net_allocation(financial_document) = financial_document.amount
```
where `net_allocation(financial_document)` is the same SUM/SUM formula scoped to `financial_document_id` instead of `cost_source`. A `document_type: expense` document may not transition out of `approved` into `posted_unreconciled` unless this equality holds exactly.

**6.3 Per-cost-source bound (both ends now explicit):**
```
0 <= net_allocation(cost_source) <= canonical_incurred_amount(cost_source)
```
`canonical_incurred_amount` reads `ContractExpense.amount` or the `MaterialReceiptLine`-derived cost (`quantity_received × unit_cost`) — read only. The lower bound (`≥ 0`) is structurally guaranteed by §9.2's reversal rules (an amount can never be reversed for more than it was applied, and at most once) but is stated here as an explicit invariant the service layer may assert defensively, not merely assumed from the reversal rules holding.

**6.4 Advance-settlement completeness (new restatement, mirrors 6.2):**
```
SUM(net_allocation(cost_source) WHERE advance_settlement_id = <this settlement>) = this settlement.amount
```
for a `settlement_type = approved_expense` row — the allocations created against an advance settlement must sum to exactly that settlement's own amount, the same completeness discipline as a direct expense document (§6.2), just scoped to `advance_settlement_id` instead of `financial_document_id`.

**6.5 Outstanding-advance bound (both ends now explicit):**
```
0 <= outstanding_advance_balance(advance) <= advance.amount
```
where
```
outstanding_advance_balance(advance) = advance.amount
  - SUM(advance_settlements.amount WHERE direction='apply',   advance_id=X)
  + SUM(advance_settlements.amount WHERE direction='reverse', advance_id=X)
```

**6.6 Binding rejection rule:** any write that would violate §6.2, §6.3, §6.4, or §6.5 is rejected outright — no automatic clamping, no partial acceptance, no silently-created "credit" or "overpayment" state.

**6.7 Material prepayment carve-out (unchanged):** a prepayment before a `MaterialReceiptLine` exists is modeled via `treasury_advances`, never as an allocation against a cost record that doesn't yet exist — `cost_source_material_receipt_line_id` must reference an existing row.

---

## 7. `treasury_cost_settlement_allocations` and `treasury_advances`/`treasury_advance_settlements` — full definitions

**`treasury_cost_settlement_allocations`**: `id`, `tenant_id`, `financial_document_id` (nullable, composite FK), `advance_settlement_id` (nullable, composite FK → `treasury_advance_settlements(tenant_id, id)`), `cost_source_contract_expense_id` (nullable, single-column FK → `contract_expenses(id)`), `cost_source_material_receipt_line_id` (nullable, single-column FK → `material_receipt_lines(id)`), `direction` (enum: `apply`\|`reverse`), `allocated_amount` (positive), `reverses_allocation_id` (nullable self-ref, composite — required iff `direction=reverse`), `created_at` (append-only).

Two `CHECK` constraints: `((financial_document_id IS NULL) != (advance_settlement_id IS NULL))` and `((cost_source_contract_expense_id IS NULL) != (cost_source_material_receipt_line_id IS NULL))`.

**Fix #1 — `UNIQUE` index on `reverses_allocation_id`** (at-most-once, §9.2). **`UNIQUE(tenant_id, id)`** — **added this round**, since `reverses_allocation_id` is a composite self-FK requiring this table to itself be a valid composite-FK target (§13).

Index: `(financial_document_id)`, `(advance_settlement_id)`, `(cost_source_contract_expense_id)`, `(cost_source_material_receipt_line_id)`.

**`treasury_advances`**: `id`, `tenant_id`, `project_id`, `financial_party_id` (composite FK), `originating_financial_document_id` (composite FK), `amount`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_advance_settlements`**: `id`, `tenant_id`, `advance_id` (composite FK), `settlement_type` (enum: `approved_expense`\|`cash_return`), `direction` (enum: `apply`\|`reverse`), `amount`, `financial_document_id` (nullable, composite FK — see §7.3), `reverses_settlement_id` (nullable self-ref, composite), `created_at`. `UNIQUE` index on `reverses_settlement_id`. **Unique: `(tenant_id, id)`** (referenced by `treasury_cost_settlement_allocations.advance_settlement_id`).

### 7.3 Population rule (unchanged from Round 4)
`approved_expense`: `financial_document_id = NULL` — no new ledger entry, cash already moved at the advance's original disbursement; only creates cost-settlement allocation(s) with `advance_settlement_id` set. `cash_return`: `financial_document_id` required, referencing a new `document_type: advance_return` document — genuine new cash-in, posted via the normal direct path (§5.1).

---

## 8. `treasury_fund_chains` and `treasury_fund_chain_members` — fix #3

**`treasury_fund_chains`**: `id`, `tenant_id`, `project_id`, `chain_reference`, `description`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_fund_chain_members`**: `id`, `tenant_id`, `fund_chain_id` (composite FK), `member_financial_document_id` (nullable, composite FK), `member_payment_route_id` (nullable, composite FK), timestamps. `CHECK ((member_financial_document_id IS NULL) != (member_payment_route_id IS NULL))`.

**Fix #3 — corrected uniqueness.** v5's single three-column unique index failed silently: with `member_payment_route_id` always `NULL` on a document-type member row, two such rows for the *same* `fund_chain_id` and *same* `member_financial_document_id` would **not** collide, because SQL treats each `NULL` as distinct from every other `NULL` even within an otherwise-matching row — the third column's permanent `NULL` meant the index could never actually detect the duplicate the Owner is right to flag. Corrected to **two separate two-column unique indexes**:
```sql
UNIQUE (fund_chain_id, member_financial_document_id)
UNIQUE (fund_chain_id, member_payment_route_id)
```
Each index only "activates" for rows where its own second column is non-null (the other column being null in those rows doesn't matter, since each index only contains two columns, neither of which is the always-null one for that row's type). This gives complete duplicate protection — a document can't appear twice in the same chain (index 1), a route can't appear twice in the same chain (index 2) — and requires no generated column or engine-specific trick; a plain multi-column unique index behaves identically on MySQL and SQLite here.

---

## 9. Reversal invariants — corrected and split by table family (fix #2)

### 9.1 `treasury_ledger_entries` — stated fully in §5.4, cross-referenced here for completeness
Uses `direction: debit|credit` + `reversal_of_entry_id`, **not** `apply|reverse`. See §5.4 for the complete rule (same wallet/source/amount, opposite direction, `UNIQUE(reversal_of_entry_id)` for at-most-once, structural impossibility of reverse-of-reverse).

### 9.2 The three event-log tables — `treasury_cost_settlement_allocations`, `treasury_advance_settlements`, `treasury_reconciliation_entries`
These, and only these, use the shared `direction: apply|reverse` + `reverses_*_id` pattern:
1. A `reverse` row's `reverses_*_id` must point to a row with `direction = apply` (never another `reverse`) — service-layer, cross-row lookup.
2. Same economic subject: the reversal copies the original's identifying columns exactly (which cost source / which advance / which ledger entry, respectively) — service-layer.
3. Exact amount: the reversal's magnitude column equals the original's exactly — service-layer.
4. At most one reversal per original: a plain `UNIQUE` index directly on each table's `reverses_*_id` column — DB-enforced, multiple `NULL`s (every `apply` row) unconstrained.
5. No reverse-of-a-reverse: structurally impossible per rule 1; undo is always a **new, independent `apply` row**, never a third row claiming to reverse a reversal.

**Why the net formulas (§6.1, §6.5, and reconciliation's currently-active check) are correct given these rules:** each is `SUM(apply) − SUM(reverse)` over exactly-paired rows (rule 4 prevents double-counting-against, rule 3 makes each pairing net to exactly zero, rule 5 means no reversal-of-reversal case exists for the formula to mishandle).

---

## 10. `treasury_expense_approvals` — unchanged from Round 4/5

`id`, `tenant_id`, `financial_document_id` (composite FK), `event` (submitted|approved|rejected|reopened), `from_status`, `to_status`, `actor_id` (FK → `users`), `note` (nullable), `context` (json, nullable), `created_at` (append-only). Verified in Round 4: `DocumentApprovalEvent` hard-coupled to `Document`/`DocumentVersion`, not reusable — this is a separate, additive table. Index: `(tenant_id, financial_document_id)`, `(actor_id)`. No `(tenant_id, id)` unique needed — never a composite-FK target (§13).

---

## 11. Fix #5 and #6 — concurrency, corrected per-engine wording

**Binding rule:** every one of the six named aggregate checks below executes as a check-then-write sequence inside a single transaction that serializes concurrent writers against the named row, before either the check or the write proceeds.

**Corrected engine-specific mechanism (fix #6):**
- **MySQL (production/dev):** `SELECT ... FOR UPDATE` — a genuine row-level exclusive lock, held until the transaction commits, blocking only concurrent writers to that specific row.
- **SQLite (test suite, per `.env.testing`/`phpunit.xml`):** SQLite has **no row-level locking** — `SELECT ... FOR UPDATE` is not a real mechanism there. The equivalent is **`BEGIN IMMEDIATE`** (or `BEGIN EXCLUSIVE`), which acquires a write lock on the **entire database file** for the duration of the transaction, serializing *all* concurrent writers, not just those touching the same row. This is coarser than MySQL's row-level lock, but it is sufficient to prevent every race condition named below, since it fully serializes the check-then-write sequence against any other writer, whatever row they touch. Stated as two genuinely different mechanisms, not one assumed portable — v5 incorrectly implied SQLite had row-level `FOR UPDATE`-equivalent locking; it does not.

| # | Check | Row/subject locked (MySQL: row; SQLite: whole-DB via `BEGIN IMMEDIATE`) |
|---|---|---|
| 1 | `ContractPayment` route-allocation conservation (§4.1) | The `contract_payments` row |
| 2 | Cost over-settlement cap (§6.3) | The `contract_expenses` or `material_receipt_lines` row |
| 3 | Advance outstanding settlement cap (§6.5) | The `treasury_advances` row |
| 4 | Active reconciliation uniqueness (§12) | The `treasury_ledger_entries` row being reconciled |
| 5 | Financial-document posting-source selection (§2) | The `treasury_financial_documents` row whose `posting_path` is being set |
| 6 | **Route-leg custody availability (new, fix #5)** | The parent `treasury_payment_routes` row |

Exact service/method names remain implementation detail, not fixed at Gate 2.

---

## 12. `treasury_reconciliations` and `treasury_reconciliation_entries` — fix #1 (index) applied

**`treasury_reconciliations`**: `id`, `tenant_id`, `wallet_id` (composite FK), `reconciliation_type`, `external_reference`, `reconciled_at`, `reconciled_by`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_reconciliation_entries`**: `id`, `tenant_id`, `reconciliation_id` (composite FK), `ledger_entry_id` (composite FK), `direction` (enum: `apply`\|`reverse` — part of the §9.2 family), `reverses_reconciliation_entry_id` (nullable self-ref, composite — required iff `direction=reverse`), `actor_id` (FK → `users`), `created_at`.

**Fix #1 — `UNIQUE(tenant_id, id)` added this round** (missing from v5's §14 despite this table having a composite self-FK, `reverses_reconciliation_entry_id`, referencing itself). `UNIQUE` index on `reverses_reconciliation_entry_id` (at-most-once, §9.2).

Whole-entry reconciliation, coverage of both `source_financial_document_id`- and `source_payment_route_leg_id`-sourced entries, the `reconciliation.wallet_id = ledger_entry.wallet_id` rule, and the deterministic `posted_unreconciled → posted_reconciled` transition are all unchanged from Round 4/5.

Index: `(reconciliation_id)`, `(ledger_entry_id)`, `(actor_id)`.

---

## 13. Fix #1 — corrected composite-FK-target index inventory: 10 → 12

**The rule (unchanged):** every table targeted by at least one composite `(tenant_id, id)` FK — **including a self-FK** — must declare an explicit `UNIQUE(tenant_id, id)` index. v5 enumerated only tables referenced *by another table*, missing the two tables whose only composite-FK exposure is a **self**-reference.

**The corrected 12 tables:**

1. `treasury_financial_parties`
2. `treasury_wallets`
3. `treasury_financial_documents`
4. `treasury_payment_routes`
5. `treasury_payment_route_legs`
6. `treasury_ledger_entries`
7. `treasury_fund_chains`
8. `treasury_advances`
9. `treasury_advance_settlements`
10. `treasury_cost_settlement_allocations` — **added this round** (self-FK: `reverses_allocation_id`)
11. `treasury_reconciliations`
12. `treasury_reconciliation_entries` — **added this round** (self-FK: `reverses_reconciliation_entry_id`)

**Not required** (never a composite-FK target, self or otherwise): `treasury_expense_approvals`, `treasury_fund_chain_members`.

---

## 14. Exact table inventory and migration order — count unchanged, order unchanged from Round 5

**12 primary + 2 join = 14 `CREATE TABLE` statements**, unchanged in count from Rounds 4–5.

1. `treasury_financial_parties`
2. `treasury_wallets`
3. `treasury_financial_documents`
4. `treasury_payment_routes`
5. `treasury_payment_route_legs`
6. `treasury_ledger_entries`
7. `treasury_fund_chains`
8. `treasury_advances`
9. `treasury_advance_settlements`
10. `treasury_cost_settlement_allocations`
11. `treasury_expense_approvals`
12. `treasury_reconciliations`
13. `treasury_fund_chain_members`
14. `treasury_reconciliation_entries`

No table created before a table it holds a real FK to. **No migration file exists yet.**

---

## 15. Verification against every held-constant item

- A3/A4-a/A.5/B2/B2-T/C/D: unchanged, not reopened.
- 14-table count: reverified against §14's self-contained listing.
- Zero changes to existing tables/data: every FK into `contract_payments`/`contract_expenses`/`material_receipt_lines` is a single-column FK to that table's existing primary key, unchanged from Round 5.
- Typed nullable FKs + `CHECK`s, frozen `posting_path`, exactly-once original ledger posting, advance settlement with zero second cash-out, reconciliation actor, whole-entry reconciliation: all unchanged from Round 5, restated above where relevant to this round's fixes.

---

## 16. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu, không phải authorization cho migration/model/code thật.
- Nếu Owner Request changes: sẽ tạo `02-design-v7.md` (supersedes bản này).
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này, giữ nguyên phần architecture đã approved làm lịch sử.

## 17. Loại trừ phạm vi
Kế thừa nguyên vẹn từ mọi round trước: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment.

## Decision Needed
**Owner đã chọn: Request changes**, tại PR #263 head `22de0cc8af3a1ed9a428bc6506aa426a507b33e8` (2026-08-16) — 3 điểm: Tier-B tenant/project invariants missing from the self-contained packet; advance-settlement reversal wrongly used one indiscriminate net-equals-amount equation instead of separate apply/reverse completeness; route-leg external-entry (`from_wallet_id IS NULL`) custody semantics undefined. Architecture set A3+A4-a+A.5/B2+B2-T/C/D confirmed unchanged. Chi tiết nguyên văn tại `decision_provenance.owner_response_reference`. **This packet (`02-design-v6.md`) is now frozen — no further content edits.** `docs/owner-decisions/GAP-037/02-design-v7.md`, self-contained, addressing these 3 points, follows in the next commit.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật hay chi tiết implementation. Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved, không mở lại. Owner cũng không được yêu cầu duyệt overpayment/prepayment semantics.
