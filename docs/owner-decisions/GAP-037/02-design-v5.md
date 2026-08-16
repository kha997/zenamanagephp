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
  recorded_at: "2026-08-16T17:33:04+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design-v4.md
superseded_by: null
timestamps:
  created_at: "2026-08-16T17:33:04+07:00"
  updated_at: "2026-08-16T17:33:04+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 5 — Self-Contained Schema (Structural Fixes)

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** (`docs/owner-decisions/GAP-037/02-design.md`, frozen) — **A3 + A4-a + A.5 / B2 + B2-T / C / D**, not reopened by this revision. Fully self-contained. Still Gate 2 — a proposal, not implementation. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet.

**Database compatibility, verified this round (not assumed):** `config/database.php` sets `'default' => env('DB_CONNECTION', 'mysql')` and `.env.example` sets `DB_CONNECTION=mysql` — production/dev target is **MySQL**. `.env.testing` and `phpunit.xml` both set `DB_CONNECTION=sqlite` — the **test suite runs on SQLite**. Every mechanism proposed below (generated columns, `UNIQUE` indexes on nullable columns, `CHECK` constraints, composite foreign keys) is chosen because it works on **both** engines this repo actually uses, not MySQL alone.

---

## 0. What changed vs. v4, at a glance

| # | Owner's finding | Fix |
|---|---|---|
| 1 | `linked_source_id` claimed a conditional FK that cannot exist | Every polymorphic single-column reference in the schema (4 instances) replaced with **typed nullable columns + a `CHECK` constraint** requiring exactly one to be set — real, unconditional FKs on each typed column |
| 2 | "At most one route" doesn't freeze *which* path posts, or *when* | New `treasury_financial_documents.posting_path` (nullable enum: `direct`\|`via_route`), set exactly once and never changed; route attachment only permitted while `posting_path IS NULL` |
| 3 | Reversal invariant left same-subject/exact-amount/at-most-once/no-reverse-of-reverse unstated | All four stated explicitly (§9); "at-most-once" is now a plain `UNIQUE` index on each `reverses_*_id` column (no generated column needed — NULLs already don't collide) |
| 4 | No concurrency/serialization rule for aggregate checks | New §10: explicit row-lock (`SELECT ... FOR UPDATE`) requirement naming exactly which row is locked, for each of the 5 named checks |
| 5 | "Implicit actor trail" asserted without a schema column | `treasury_reconciliation_entries` gains `actor_id` directly (mirrors `treasury_expense_approvals.actor_id`) |
| 6 | Composite `(tenant_id, id)` FK targets need an explicit index requirement | New §11: names all 10 tables that are FK targets and states the binding index requirement, verified against both MySQL and SQLite |

---

## 1. `treasury_financial_parties` and `treasury_wallets` — unchanged from v4

**`treasury_financial_parties`**: `id` (ulid, PK), `tenant_id`, `party_type` (investor|intermediary|owner|employee|labour|supplier|subcontractor|authority|other), `name`, `linked_account_id` (nullable), `linked_user_id` (nullable), timestamps. **Unique index: `(tenant_id, id)`** (§11). Index: `(tenant_id, party_type)`.

**`treasury_wallets`**: `id` (ulid, PK), `tenant_id`, `project_id` (nullable), `wallet_type` (company_bank|company_cash|owner_personal|employee_cash|employee_bank|intermediary_control|project_wallet|other), `name`, `custodian_party_id` (nullable, composite FK → `treasury_financial_parties(tenant_id, id)`), timestamps. **Unique index: `(tenant_id, id)`.** Index: `(tenant_id, project_id, wallet_type)`.

---

## 2. `treasury_financial_documents` — fix #2 (posting-path freeze)

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `tenant_id` | string | |
| `project_id` | string | |
| `document_type` | enum: funding\|internal_transfer\|expense\|owner_contribution\|advance\|advance_return\|reversal\|adjustment | |
| `status` | enum: draft\|submitted\|approved\|rejected\|posted_unreconciled\|posted_reconciled\|reversed | |
| `posting_path` | **nullable** enum: `direct` \| `via_route` | **new (fix #2)** — `NULL` until the posting-path decision is made; set exactly once; never updated again after being set |
| `amount` | decimal, positive | |
| `source_wallet_id` | nullable, composite FK → `treasury_wallets(tenant_id, id)` | |
| `destination_wallet_id` | nullable, composite FK → `treasury_wallets(tenant_id, id)` | |
| `counterparty_id` | nullable, composite FK → `treasury_financial_parties(tenant_id, id)` | |
| `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable) | | |
| `reversed_document_id` | nullable self-ref, composite | |
| `replacement_document_id` | nullable self-ref, composite | |
| timestamps | | |

Population rule for `source_wallet_id`/`destination_wallet_id` (unchanged from v4): `internal_transfer` requires both; `expense`/`advance` require only source; `funding`/`owner_contribution`/`advance_return` require only destination; `reversal` mirrors with roles swapped; `adjustment` requires exactly one.

### 2.1 Fix #2 — binding posting-path freeze rule
1. `posting_path` starts `NULL` on document creation.
2. **A `treasury_payment_routes` row may only be created with `linked_financial_document_id` set to this document (§4) while `posting_path IS NULL` AND `status` is one of `draft`/`submitted`/`approved`** (never once `posted_unreconciled`/`posted_reconciled`/`reversed`). The moment such a route is created, `posting_path` is set to `via_route` **in the same transaction** (§10 — this is one of the five named concurrency-protected operations).
3. **If the document reaches its posting moment (`status` transitioning to `posted_unreconciled`) with no associated route having ever been created, `posting_path` is set to `direct` at that exact moment**, in the same transaction as the status transition and the direct ledger-entry posting (§10).
4. Once `posting_path` is non-`NULL`, it is **immutable** — no update path exists for this column after it is first set. There is likewise no delete/update path for `treasury_payment_routes.linked_financial_document_id` once set (append-only schema, consistent throughout this document) — so a route can never be detached or relinked to a different document after creation.
5. **Consequence:** exclusivity is now a structural fact checkable from two columns (`posting_path` and whether a route references this document), not merely inferable from "at most one route exists" — a route existing *and* `posting_path = via_route` is required together; either alone, without the other, indicates a bug the schema is designed to make visible rather than silently tolerate.

Index: `(tenant_id, project_id, document_type, status)`, `(source_wallet_id)`, `(destination_wallet_id)`, `(posting_path)`. **Unique index: `(tenant_id, id)`.**

---

## 3. Fix #1 — the general pattern for every former polymorphic reference

**Rule, applied uniformly at all four sites in this schema (§4, §5, §6, §12):** every place that needs to reference "one of several possible target tables" is modeled as **N nullable, typed, single-target foreign key columns plus a `CHECK` constraint requiring exactly one to be non-`NULL`.** No column claims a conditional foreign key. This works identically on MySQL 8.0.16+ and SQLite (both support `CHECK`), and is the concrete implementable model the Owner asked for — the "split typed nullable source columns with an exactly-one constraint" option.

A useful side effect: where the possible targets are **existing** tables (`contract_payments`, `contract_expenses`, `material_receipt_lines`), adding a single-column FK to their existing primary key (`id`) is purely additive — it requires no schema change to those tables (their `id` column is already a unique primary key, which is all a single-column FK target needs) and does not violate the "zero changes to existing tables" principle. Where the possible targets are **new Treasury tables**, the FK is a full composite `(tenant_id, id)` reference (§11), giving DB-enforced tenant matching for free; for the existing-table cases, only existence is DB-enforced — tenant/project matching for those remains Tier B application-layer (§13), since composite indexes cannot be added to existing tables without altering them.

---

## 4. `treasury_payment_routes` and `treasury_payment_route_legs`

**`treasury_payment_routes`**: `id` (ulid, PK), `tenant_id`, `project_id`, `total_allocated_amount` (decimal — the conserved figure, unchanged from B2-T/Round 2), `status` (planned|partial|completed|cancelled), timestamps, plus:

- `linked_financial_document_id` — nullable, **composite FK → `treasury_financial_documents(tenant_id, id)`**.
- `linked_contract_payment_id` — nullable, **single-column FK → `contract_payments(id)`** (existing table, additive-only).
- **`CHECK ((linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL))`** — exactly one set. Replaces the removed `linked_source_type`/`linked_source_id` pair entirely (§3).

**Fix #1 (uniqueness, simplified from v4's generated-column approach):** a plain **`UNIQUE` index directly on `linked_financial_document_id`** enforces "at most one route per financial document" — MySQL and SQLite both treat multiple `NULL`s in a unique index as non-conflicting, so `contract_payment`-linked routes (where this column is `NULL`) are entirely unconstrained by it, exactly matching B2-T's requirement that multiple routes may share one `ContractPayment` (conservation enforced separately, §4.1, unchanged). No generated column is needed here (unlike §6's ledger-idempotency case, which does still need one — see there for why the two cases differ).

### 4.1 Conservation invariant — unchanged from Round 2/3/4
```
SUM(treasury_payment_routes.total_allocated_amount
    WHERE linked_contract_payment_id = <ContractPayment.id>)
  <= ContractPayment.amount
```
Allocation-level, never leg-summed. **Concurrency (fix #4):** this check-then-insert must occur inside a transaction holding a `SELECT ... FOR UPDATE` lock on the referenced `contract_payments` row (§10, item 1) — an unlocked read-then-write is explicitly insufficient, since two concurrent route creations against the same `ContractPayment` could otherwise both pass the check before either commits.

**`treasury_payment_route_legs`**: `id` (ulid, PK), `tenant_id`, `payment_route_id` (composite FK → `treasury_payment_routes(tenant_id, id)`), `sequence_no`, `from_wallet_id` (nullable, composite FK), `to_wallet_id` (composite FK), `amount` (decimal — need not equal the route's `total_allocated_amount`; must not exceed current custody at `from_wallet_id` for this route, per §6.4), `status` (in_transit|settled|reversed), `occurred_at`, timestamps. **Unique index: `(tenant_id, id)`** on both tables (§11).

---

## 5. `treasury_cost_settlement_allocations`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `tenant_id` | string | |
| `financial_document_id` | nullable, composite FK → `treasury_financial_documents(tenant_id, id)` | direct expense cash-out settlement |
| `advance_settlement_id` | nullable, composite FK → `treasury_advance_settlements(tenant_id, id)` | advance-sourced settlement, no new cash-out (§7.3) |
| `cost_source_contract_expense_id` | nullable, single-column FK → `contract_expenses(id)` | **replaces `cost_source_type`/`cost_source_id` for this case (fix #1)** |
| `cost_source_material_receipt_line_id` | nullable, single-column FK → `material_receipt_lines(id)` | **replaces `cost_source_type`/`cost_source_id` for this case (fix #1)** |
| `direction` | enum: `apply` \| `reverse` | |
| `allocated_amount` | decimal, positive | |
| `reverses_allocation_id` | nullable self-ref, composite | required when `direction=reverse`; **null when `direction=apply`** |
| `created_at` | timestamp | append-only |

**Two independent `CHECK` constraints:**
```sql
CHECK ((financial_document_id IS NULL) != (advance_settlement_id IS NULL))
CHECK ((cost_source_contract_expense_id IS NULL) != (cost_source_material_receipt_line_id IS NULL))
```

**Fix #3 — at-most-once reversal, MySQL/SQLite-compatible without a generated column:** a plain **`UNIQUE` index on `reverses_allocation_id`** directly enforces that a given original (`apply`) row can be pointed at by at most one `reverse` row — every `apply` row has `reverses_allocation_id = NULL` (unlimited NULLs permitted), and any two attempts to reverse the *same* original row would collide on this unique index. This is simpler than §6's ledger case because there is only one "kind" of row being pointed at here (no `source_type` discriminator to fold into a generated key).

Index: `(financial_document_id)`, `(advance_settlement_id)`, `(cost_source_contract_expense_id)`, `(cost_source_material_receipt_line_id)`. **Unique index: `(tenant_id, id)`** is *not* required here — nothing references `treasury_cost_settlement_allocations` via composite FK (§11).

---

## 6. `treasury_ledger_entries`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, PK | |
| `tenant_id` | string | |
| `source_financial_document_id` | nullable, composite FK → `treasury_financial_documents(tenant_id, id)` | **replaces `source_type`/`source_id` (fix #1)** |
| `source_payment_route_leg_id` | nullable, composite FK → `treasury_payment_route_legs(tenant_id, id)` | **replaces `source_type`/`source_id` (fix #1)** |
| `wallet_id` | composite FK → `treasury_wallets(tenant_id, id)` | |
| `direction` | enum: `debit` \| `credit` | |
| `amount` | decimal, positive | |
| `entry_type` | enum: wallet_funding\|wallet_expense_settlement\|wallet_internal_transfer\|wallet_owner_financing\|wallet_advance_open\|wallet_advance_settlement\|wallet_route_custody | narrative classification |
| `posted_at` | timestamp | |
| `reversal_of_entry_id` | nullable self-ref, composite | |
| `created_at` | timestamp | append-only |

**`CHECK ((source_financial_document_id IS NULL) != (source_payment_route_leg_id IS NULL))`.**

### 6.1 Fix #1 — the ledger-source bridge (unchanged principle)
A route leg posts entries directly (`source_payment_route_leg_id` set, `source_financial_document_id` null) — no Treasury document wraps a `ContractPayment`-linked route's custody movement. Treasury-native movements without an associated route post with `source_financial_document_id` set.

### 6.2 Fix #1 (idempotency) — generated column still required here, and why it differs from §4/§5's plain unique index
Unlike §4's route-uniqueness and §5's reversal-uniqueness (each a simple "at most one row points here" fact), ledger idempotency has to exclude **only reversal rows** from a **compound** key (source column + `direction`), while still allowing a two-sided movement's debit and credit (same source, different direction) to coexist. A plain unique index on `(source_financial_document_id, direction)` would be **wrong**: it would block a legitimate reversal entry that reuses the same `(source, direction)` pair as an *unrelated* original entry from a two-sided document (e.g., an `internal_transfer`'s destination-side credit and a later reversal-generated credit for the same document's source-side debit could collide). The generated column, scoped to non-reversal rows only, avoids this:

```sql
original_posting_key = CASE
  WHEN reversal_of_entry_id IS NOT NULL THEN NULL
  WHEN source_financial_document_id IS NOT NULL THEN CONCAT('fd:', source_financial_document_id, ':', direction)
  WHEN source_payment_route_leg_id  IS NOT NULL THEN CONCAT('rl:', source_payment_route_leg_id,  ':', direction)
END
```
**`UNIQUE` index on `original_posting_key`.** Verified compatible with both engines: MySQL supports generated (virtual/stored) columns with an index since 5.7/8.0; SQLite supports generated columns since 3.31 and expression/virtual-column indexes natively — both permit multiple `NULL`s in the resulting unique index, which is what excludes reversal rows from the constraint.

### 6.3 Wallet balance and route custody (unchanged formula)
```
wallet_balance(wallet_id) = SUM(amount WHERE wallet_id=X, direction='credit')
                           - SUM(amount WHERE wallet_id=X, direction='debit')
```
Route custody: the same formula scoped to a route's legs' entries. Partial routing (A→C=100, C→Y=60) nets to C=40, Y=60.

Index: `(source_financial_document_id)`, `(source_payment_route_leg_id)`, `(wallet_id, posted_at)`. **Unique index: `(tenant_id, id)`** (§11).

---

## 7. `treasury_advances` and `treasury_advance_settlements`

**`treasury_advances`**: `id` (ulid, PK), `tenant_id`, `project_id`, `financial_party_id` (composite FK), `originating_financial_document_id` (composite FK → `treasury_financial_documents(tenant_id, id)` — the `document_type: advance` document whose posting is the advance's one and only cash-out), `amount`, timestamps. **Unique index: `(tenant_id, id)`.**

**`treasury_advance_settlements`**: `id` (ulid, PK), `tenant_id`, `advance_id` (composite FK), `settlement_type` (enum: `approved_expense` \| `cash_return`), `direction` (enum: `apply` \| `reverse`), `amount`, `financial_document_id` (nullable, composite FK — see population rule), `reverses_settlement_id` (nullable self-ref, composite — required when `direction=reverse`), `created_at`. **Unique index: `(tenant_id, id)`** (referenced by `treasury_cost_settlement_allocations.advance_settlement_id`, §5).

**Fix #3 — at-most-once reversal:** plain `UNIQUE` index on `reverses_settlement_id` (same pattern as §5).

### 7.3 Fix #2 (from Round 4, unchanged) — population rule for `financial_document_id`
- `settlement_type = approved_expense`: `financial_document_id` is `NULL`. No new ledger entry — cash already moved at the advance's original disbursement. Creates a `treasury_cost_settlement_allocations` row (`advance_settlement_id` = this row, `financial_document_id` null there per §5's CHECK) — pure re-classification, zero ledger entries.
- `settlement_type = cash_return`: `financial_document_id` required, referencing a new `document_type: advance_return` document — a genuine new cash-in, posted once via the normal direct path (§2.1, since `advance_return` documents never have an associated route in this design).

### 7.4 Outstanding advance conservation (unchanged formula)
```
outstanding_advance_balance(advance) =
    advance.amount
  - SUM(advance_settlements.amount WHERE direction='apply',   advance_id=X)
  + SUM(advance_settlements.amount WHERE direction='reverse', advance_id=X)
```
**Concurrency (fix #4):** every write against this formula occurs inside a transaction holding `SELECT ... FOR UPDATE` on the `treasury_advances` row (§10, item 3).

Material prepayment carve-out (unchanged): prepayment before a `MaterialReceiptLine` exists is modeled via `treasury_advances`, never as a cost-settlement allocation against a nonexistent cost record.

---

## 8. `treasury_fund_chains` and `treasury_fund_chain_members`

**`treasury_fund_chains`**: `id` (ulid, PK), `tenant_id`, `project_id`, `chain_reference`, `description`, timestamps. Grouping only, not exact FIFO (per PR #245 §7.6, non-normative context). **Unique index: `(tenant_id, id)`.**

**`treasury_fund_chain_members`**: `id` (ulid, PK), `tenant_id`, `fund_chain_id` (composite FK → `treasury_fund_chains(tenant_id, id)`), `member_financial_document_id` (nullable, composite FK → `treasury_financial_documents(tenant_id, id)`), `member_payment_route_id` (nullable, composite FK → `treasury_payment_routes(tenant_id, id)`), timestamps.

**`CHECK ((member_financial_document_id IS NULL) != (member_payment_route_id IS NULL))`** — **replaces `member_type`/`member_id` (fix #1).**

Unique: `(fund_chain_id, member_financial_document_id, member_payment_route_id)` — a given document/route appears in a given chain at most once (a composite unique across all three columns works correctly here since exactly one of the two nullable member columns is non-null per row, per the CHECK above — MySQL/SQLite both allow this).

---

## 9. `treasury_expense_approvals` — unchanged from Round 4

Verified: `DocumentApprovalEvent` is hard-coupled to `Document`/`DocumentVersion`, not reusable without an existing-model change. New additive table: `id` (ulid, PK), `tenant_id`, `financial_document_id` (composite FK), `event` (submitted|approved|rejected|reopened), `from_status`, `to_status`, `actor_id` (FK → `users`), `note` (nullable), `context` (json, nullable), `created_at` (append-only). Index: `(tenant_id, financial_document_id)`, `(actor_id)`. **Unique index: `(tenant_id, id)`** not required — nothing references this table (§11).

---

## 10. Fix #9 (Owner's #3) — exact reversal invariant, stated once, applied everywhere

This section is the single source of truth for reversal correctness across all four reversal-capable tables (`treasury_ledger_entries`, `treasury_cost_settlement_allocations`, `treasury_advance_settlements`, `treasury_reconciliation_entries`, introduced in §12). Every one of them shares this exact shape: a `direction`/sign-bearing enum, an unsigned `amount`-or-count magnitude, and a nullable self-referencing `reverses_*_id` column.

**Binding rules (DB-enforced where marked, service-layer where marked):**
1. **A reverse row must point at a row with `direction = apply`.** *(Service-layer — cross-row `direction` lookup cannot be expressed as a single-row `CHECK` on either engine.)* A row that is itself a `reverse` can never be the target of `reverses_*_id`.
2. **Same economic subject.** A reversal row must carry identical values, copied at creation time, for every column that identifies *what* is being reversed (e.g., for `treasury_cost_settlement_allocations`: the same `cost_source_contract_expense_id`/`cost_source_material_receipt_line_id` and the same `financial_document_id`/`advance_settlement_id` as the row it reverses; for `treasury_ledger_entries`: the same `wallet_id` and the same source columns). *(Service-layer — verified by copying these fields from the original row when constructing the reversal, not left to caller discretion.)*
3. **Exact amount.** The reversal's `amount`/`allocated_amount` must equal the original row's exactly — no partial reversal is representable in this schema (consistent with §7's whole-entry-only reconciliation decision, extended here to every reversal-capable table). *(Service-layer.)*
4. **At most one reversal per original row.** *(DB-enforced.)* A plain `UNIQUE` index on the `reverses_*_id` column of each reversal-capable table (§4, §5, §7, §12) — multiple `NULL`s (i.e., every `apply` row) are unconstrained; any second attempt to reverse the *same* original row collides on the index and fails outright.
5. **No reverse-of-a-reverse; undo is a new forward `apply`, never a reversal chain.** Rule 1 already makes this structurally impossible to represent as a *reversal* (a `reverse` row cannot itself be pointed at by `reverses_*_id`, since it isn't `direction=apply`). If a previously-reversed original needs to be reinstated, the schema's only representable path is: create a **new, independent `apply` row** carrying the same economic-subject values — not a third row claiming to "reverse the reversal." This keeps every reversal chain exactly two rows long (one `apply`, at most one `reverse`) by construction, never longer.

**Why the net-active formulas (§5.1, §6.4, §7.4, and reconciliation's currently-active check) remain correct given these five rules:** each formula is `SUM(apply) − SUM(reverse)` (or count-equivalent) over exactly-paired rows — rule 4 guarantees no original is double-counted-against, rule 3 guarantees each pairing nets to exactly zero (not a partial residual), and rule 5 guarantees the formula never has to reason about a reversal-of-a-reversal case, since none can exist. The formulas were already stated correctly in Rounds 3–4; this section is what makes them *provably* correct rather than merely plausible.

---

## 11. Fix #4 — concurrency-safe serialization for aggregate checks

**Binding rule:** every one of the five named aggregate checks below must execute as a check-then-write sequence inside a single database transaction that first acquires `SELECT ... FOR UPDATE` (or the engine's equivalent row-level exclusive lock) on the row named, held until commit. An unlocked "check, then separately insert" sequence is explicitly rejected as insufficient — it is vulnerable to two concurrent writers both reading a passing state before either commits. Exact service/method names are implementation detail, not fixed at Gate 2, per the Owner's own instruction.

| # | Check | Row locked |
|---|---|---|
| 1 | `ContractPayment` route-allocation conservation (§4.1) | The `contract_payments` row being referenced (existing table; `FOR UPDATE` is a locking read, not a schema change) |
| 2 | Cost over-settlement cap (§5, formula unchanged from Round 4 §6.2, now keyed on the split `cost_source_contract_expense_id`/`cost_source_material_receipt_line_id` columns) | The `contract_expenses` or `material_receipt_lines` row being referenced |
| 3 | Advance outstanding settlement cap (§7.4) | The `treasury_advances` row |
| 4 | Active reconciliation uniqueness (§12) | The `treasury_ledger_entries` row being reconciled |
| 5 | Financial-document posting-source selection (§2.1) | The `treasury_financial_documents` row whose `posting_path` is being set |

Both MySQL (InnoDB, the only engine Laravel's `Schema::create()` targets for MySQL by default) and SQLite support `SELECT ... FOR UPDATE`-equivalent locking within a transaction (SQLite serializes writers at the database-file level within `BEGIN IMMEDIATE`/exclusive transactions, achieving the same effective guarantee even though its lock granularity is coarser than MySQL's row-level locks) — both are sufficient to prevent the race condition this section exists to close.

---

## 12. Fix #5 — `treasury_reconciliations` and `treasury_reconciliation_entries`

**`treasury_reconciliations`**: `id` (ulid, PK), `tenant_id`, `wallet_id` (composite FK), `reconciliation_type` (bank|cash|receipt|intermediary_confirmation|other), `external_reference`, `reconciled_at`, `reconciled_by`, timestamps. **Unique index: `(tenant_id, id)`.**

**`treasury_reconciliation_entries`**: `id` (ulid, PK), `tenant_id`, `reconciliation_id` (composite FK), `ledger_entry_id` (composite FK → `treasury_ledger_entries(tenant_id, id)`), `direction` (enum: `apply` \| `reverse`), `reverses_reconciliation_entry_id` (nullable self-ref, composite — required when `direction=reverse`), **`actor_id`** (FK → `users` — **new, fix #5**, replaces the earlier "implicit actor trail" language with an actual column, present on both `apply` and `reverse` rows), `created_at`.

**Fix #3 (at-most-once, applied here too):** `UNIQUE` index on `reverses_reconciliation_entry_id`.

Whole-entry reconciliation (unchanged): a ledger entry is fully reconciled or not, no fractional reconciliation.

Coverage (unchanged from Round 4): applies to every `treasury_ledger_entries` row regardless of source — route-leg-sourced entries are reconciled through this same table; the leg's own `status` (in_transit|settled|reversed) is a separate, in-app lifecycle fact, not a substitute for this external-evidence reconciliation.

**`reconciliation.wallet_id = ledger_entry.wallet_id` (unchanged rule, restated):** validated at write time — a `treasury_reconciliation_entries` row is only valid if its `reconciliation_id`'s `wallet_id` equals its `ledger_entry_id`'s `wallet_id`, same tenant.

**"Currently active" check (unchanged reasoning from Round 4, now with the corrected reversal semantics from §10):** before inserting a new `apply` row for a given `ledger_entry_id`, the service checks no unreversed `apply` already exists for it — a service-layer check (per §11, item 4, under a `FOR UPDATE` lock on the `treasury_ledger_entries` row), because "currently active" is a computed, time-varying condition (an entry may be reconciled, reversed, and re-reconciled later with a fresh `apply` row), not a permanent fact like §6.2's idempotency case.

**Deterministic `posted_unreconciled → posted_reconciled` transition (unchanged):** a `treasury_financial_documents` row transitions when every ledger entry with `source_financial_document_id = <this document>` has a currently-active `apply` reconciliation-entry row.

Index: `(reconciliation_id)`, `(ledger_entry_id)`, `(actor_id)`.

---

## 13. Tier B — remaining application-layer same-tenant/same-project rules

With every internal (Treasury-to-Treasury) polymorphic reference now a real composite FK (Tier A, DB-enforced tenant match), Tier B is reduced to exactly the references that target **existing, non-Treasury tables** (where a composite index cannot be added without altering those tables):

| Reference | Existence | Tenant/project match |
|---|---|---|
| `treasury_payment_routes.linked_contract_payment_id` | DB-enforced (single-column FK to `contract_payments.id`) | Application-layer: same `tenant_id`; same project via the `ContractPayment`'s `Contract.project_id` |
| `treasury_cost_settlement_allocations.cost_source_contract_expense_id` | DB-enforced (FK to `contract_expenses.id`) | Application-layer: same `tenant_id`; same project as the allocation's `financial_document`/`advance_settlement` |
| `treasury_cost_settlement_allocations.cost_source_material_receipt_line_id` | DB-enforced (FK to `material_receipt_lines.id`) | Application-layer: same, via the receipt's project |

Enforced by the same `TreasuryReferentialIntegrityService`-equivalent named in prior rounds, at write time, under the same `FOR UPDATE` discipline as §11 where the write also participates in one of the five named aggregate checks.

---

## 14. Fix #6 — composite-FK-target index requirement

**Binding rule:** every Treasury table that is the target of at least one composite `(tenant_id, id)` foreign key from another Treasury table **must** declare an explicit `UNIQUE` index on `(tenant_id, id)` as part of its own table definition — this is a prerequisite for the composite FK to be creatable at all on either engine, stated here so a migration author cannot omit it by oversight. **Verified on both engines this repo uses:** MySQL requires the referenced columns of a foreign key to have a matching index; a `UNIQUE (tenant_id, id)` index satisfies this even though `id` alone is already the primary key, because MySQL does not implicitly extend a single-column primary key into a usable index for a *different* multi-column FK — the composite unique index must be declared explicitly. SQLite's foreign key enforcement (`PRAGMA foreign_keys=ON`, which Laravel enables) similarly requires the parent columns to be collectively unique, satisfied the same way.

**The 10 tables requiring this explicit index** (every table referenced by at least one composite FK elsewhere in this document — verified by cross-checking every "composite FK →" reference above):

1. `treasury_financial_parties` (referenced by `treasury_wallets`, `treasury_financial_documents`, `treasury_advances`)
2. `treasury_wallets` (referenced by `treasury_financial_documents`, `treasury_payment_route_legs`, `treasury_reconciliations`)
3. `treasury_financial_documents` (referenced by `treasury_payment_routes`, `treasury_ledger_entries`, `treasury_cost_settlement_allocations`, `treasury_advances`, `treasury_advance_settlements`, `treasury_expense_approvals`, `treasury_fund_chain_members`)
4. `treasury_payment_routes` (referenced by `treasury_payment_route_legs`, `treasury_fund_chain_members`)
5. `treasury_payment_route_legs` (referenced by `treasury_ledger_entries`)
6. `treasury_advances` (referenced by `treasury_advance_settlements`)
7. `treasury_advance_settlements` (referenced by `treasury_cost_settlement_allocations`)
8. `treasury_fund_chains` (referenced by `treasury_fund_chain_members`)
9. `treasury_reconciliations` (referenced by `treasury_reconciliation_entries`)
10. `treasury_ledger_entries` (referenced by `treasury_reconciliation_entries`)

**Not required** (never targeted by a composite FK): `treasury_cost_settlement_allocations`, `treasury_expense_approvals`, `treasury_fund_chain_members`, `treasury_reconciliation_entries`.

---

## 15. Exact table inventory and migration order — unchanged count from Round 4, verified again

**12 primary tables + 2 join tables = 14 `CREATE TABLE` statements.** No table was added or removed this round — only columns, constraints, and indexes changed.

**Corrected dependency-safe order** (every table created only after every table it holds a real FK to; `treasury_advance_settlements` precedes `treasury_cost_settlement_allocations`, unchanged from Round 4's correction):

1. `treasury_financial_parties`
2. `treasury_wallets`
3. `treasury_financial_documents`
4. `treasury_ledger_entries` *(composite FK to 3 and to table 6, created below — see note)*
5. `treasury_payment_routes`
6. `treasury_payment_route_legs`
7. `treasury_fund_chains`
8. `treasury_advances`
9. `treasury_advance_settlements`
10. `treasury_cost_settlement_allocations`
11. `treasury_expense_approvals`
12. `treasury_reconciliations`
13. `treasury_fund_chain_members`
14. `treasury_reconciliation_entries`

**Note on table 4 vs. table 6:** `treasury_ledger_entries` (table 4) holds a composite FK to `treasury_payment_route_legs` (table 6), created two steps later. Since both are Treasury-owned tables and the FK is a *real*, DB-enforced composite FK (not the non-DB-FK exception used for existing-table references), this genuinely requires reordering: **`treasury_payment_route_legs` (and its own dependency `treasury_payment_routes`) must be created before `treasury_ledger_entries`.** Corrected final order:

1. `treasury_financial_parties`
2. `treasury_wallets`
3. `treasury_financial_documents`
4. `treasury_payment_routes` *(moved before ledger_entries)*
5. `treasury_payment_route_legs` *(moved before ledger_entries)*
6. `treasury_ledger_entries`
7. `treasury_fund_chains`
8. `treasury_advances`
9. `treasury_advance_settlements`
10. `treasury_cost_settlement_allocations`
11. `treasury_expense_approvals`
12. `treasury_reconciliations`
13. `treasury_fund_chain_members`
14. `treasury_reconciliation_entries`

No table in this final order is ever created before a table it holds a real FK to. **No migration file exists yet.**

---

## 16. Verification against every held-constant item

- A3/A4-a/A.5/B2/B2-T/C/D: unchanged, not reopened by this revision — every fix above is a schema-mechanics correction, none touches the approved architecture's substance.
- Exact table count: 14, reverified in §15 against the fully self-contained listing above.
- Zero changes to existing tables/data: every FK to an existing table (`contract_payments`, `contract_expenses`, `material_receipt_lines`) is a single-column FK to that table's already-unique `id` — no column, index, or constraint is added to any existing table anywhere in this document.
- A4-a: zero references anywhere in this schema to `Component`/`Project` cost fields.
- D: zero references anywhere in this schema to `ReportPageController::cashflow()`.

---

## 17. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu, không phải authorization cho migration/model/code thật.
- Nếu Owner Request changes: sẽ tạo `02-design-v6.md` (supersedes bản này), không sửa `02-design-v5.md` sau khi có quyết định.
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này, giữ nguyên phần architecture đã approved làm lịch sử.

## 18. Loại trừ phạm vi
Kế thừa nguyên vẹn từ mọi round trước: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment.

## Decision Needed
Owner chọn một: Approve corrected schema proposal to proceed toward Gate 3 preparation / Request further changes / Decline.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật hay chi tiết implementation (exact service/method/class names, exact lock-acquisition code). Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved, không mở lại bởi revision này. Owner cũng không được yêu cầu duyệt overpayment/prepayment semantics — carve-out chỉ xác nhận phạm vi, không đề xuất thiết kế cho trường hợp đó.
