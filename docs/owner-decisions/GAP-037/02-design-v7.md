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
  recorded_at: "2026-08-16T18:16:05+07:00"
  owner_response_reference: "Owner Gate 2 Schema Proposal Revision 7 decision — REQUEST CHANGES, recorded in-session on 2026-08-16 against reviewed PR #263 head d2c44e76ef919c114c7a45b10dbc694a24c4336e: 'GAP-037 — Gate 2 Schema Proposal Revision 7 — Owner Decision: REQUEST CHANGES. Tôi, Owner, yêu cầu chỉnh sửa schema proposal tại PR #263, reviewed head d2c44e76ef919c114c7a45b10dbc694a24c4336e. Tôi xác nhận ba correction của Revision 7 đã xử lý đạt yêu cầu: Tier-B same-tenant/same-project rules cho các existing-table FKs đã được phục hồi; advance-settlement apply/reverse completeness đã được tách đúng và compensating allocations được tạo atomically; external-entry route leg đã có conservation rule đúng, không còn giả định from_wallet_id tồn tại. Architecture set A3 + A4-a + A.5 / B2 + B2-T / C / D vẫn approved, frozen và không được mở lại. Revision 8 chỉ cần xử lý hai nhóm invariant: 1. Treasury-internal same-project integrity — Composite (tenant_id,id) FK chỉ bảo đảm tenant, không bảo đảm project. Bổ sung binding application/service-layer project-compatibility rules cho mọi Treasury-to-Treasury reference có project semantics, tối thiểu: route ↔ linked financial document; advance ↔ originating financial document; fund chain ↔ member document/route; financial-document reversal/replacement; project-scoped wallet references from financial documents and route legs; ledger source ↔ wallet project compatibility. Company/shared wallets với project_id = NULL được phép khi wallet semantics cho phép. 2. Financial-document-linked route conservation and reconciliation — Khi treasury_payment_routes.linked_financial_document_id được set: route.total_allocated_amount = linked_financial_document.amount phải là binding invariant. Vì một financial document chỉ có tối đa một route và posting_path=via_route là sole ledger-posting path, route đó phải represent toàn bộ economic amount của document. Định nghĩa deterministic reconciliation theo posting path: direct → document-sourced ledger entries; via_route → ledger entries sourced from the linked route's legs. posted_reconciled chỉ được đạt khi toàn bộ applicable active ledger movements của chosen path đã được reconciled. Giữ nguyên toàn bộ v7 còn lại, bao gồm: 14-table schema; 12 composite-FK targets; typed nullable FKs + XOR CHECKs; frozen posting path; ContractPayment B2-T conservation; external-entry and wallet-backed route custody; signed immutable ledger; exactly-once posting; corrected ledger reversal; apply/reverse event-log reversal; cost/advance settlement conservation; two fund-chain uniqueness indexes; MySQL FOR UPDATE / SQLite BEGIN IMMEDIATE; reconciliation actor; Tier-B external-table validation; zero existing-table changes; PR #245/#257 untouched; no migration/model/controller/service/route/UI/tests; no Gate 3. Record REQUEST CHANGES first in 02-design-v7.md, freeze it, then create self-contained 02-design-v8.md, rerun required CI and return awaiting_owner. Không được suy luận schema approval hoặc Gate 3 authorization.'"
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-037/02-design-v6.md
superseded_by: null
timestamps:
  created_at: "2026-08-16T18:06:21+07:00"
  updated_at: "2026-08-16T18:16:05+07:00"
generated_by: agent
---

# GAP-037 — Project Treasury: Gate 2 Revision 7 — Precision Fixes (Self-Contained)

**Status:** Gate 1 approved. Gate 2 architecture decisions **approved** (`docs/owner-decisions/GAP-037/02-design.md`, frozen) — **A3 + A4-a + A.5 / B2 + B2-T / C / D**, not reopened. Fully self-contained. Still Gate 2 — a proposal, not implementation. No migration file, model, controller, service, route, UI, or test exists or is authorized by this packet.

**Database compatibility (unchanged verification):** MySQL for production/dev (`config/database.php`, `.env.example`), SQLite for the test suite (`.env.testing`, `phpunit.xml`).

---

## 0. What changed vs. v6, at a glance

| # | Owner's finding | Fix |
|---|---|---|
| 1 | Tier-B tenant/project invariants for the three existing-table FKs were dropped from the self-contained packet during v6's restructuring | Restored as §14, self-contained, restating all three |
| 2 | Advance-settlement completeness used one indiscriminate `net_allocation = settlement.amount` equation for both `apply` and `reverse` rows | §7.4 rewritten: separate completeness equations, scoped to each settlement row's own `id` and own `direction` — a `reverse` settlement must atomically create compensating reversal allocations for the original's still-active allocations, not merely satisfy a net formula |
| 3 | Route-leg custody check undefined for `from_wallet_id IS NULL` (external-entry) legs | §4.2 rewritten: external-entry legs skip the (nonexistent) wallet-balance check and are instead bounded by the route's remaining `total_allocated_amount`, under the same parent-route lock; wallet-backed legs unchanged |

---

## 1. `treasury_financial_parties` and `treasury_wallets` — unchanged from v6

**`treasury_financial_parties`**: `id` (ulid, PK), `tenant_id`, `party_type`, `name`, `linked_account_id` (nullable), `linked_user_id` (nullable), timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_wallets`**: `id`, `tenant_id`, `project_id` (nullable), `wallet_type`, `name`, `custodian_party_id` (nullable, composite FK), timestamps. **Unique: `(tenant_id, id)`.**

---

## 2. `treasury_financial_documents` — unchanged from v6

`id`, `tenant_id`, `project_id`, `document_type` (funding|internal_transfer|expense|owner_contribution|advance|advance_return|reversal|adjustment), `status` (draft|submitted|approved|rejected|posted_unreconciled|posted_reconciled|reversed), `posting_path` (nullable enum: `direct`|`via_route`, set exactly once), `amount`, `source_wallet_id` (nullable, composite FK), `destination_wallet_id` (nullable, composite FK), `counterparty_id` (nullable, composite FK), `description`, `created_by`, `approved_by` (nullable), `posted_at` (nullable), `reversed_document_id` (nullable self-ref), `replacement_document_id` (nullable self-ref), timestamps. **Unique: `(tenant_id, id)`.**

Posting-path freeze rule unchanged: route may only attach while `posting_path IS NULL` and unposted; attaching locks `posting_path = via_route`; reaching posting with no route locks `posting_path = direct`; immutable once set.

---

## 3. The typed-nullable-FK pattern — unchanged principle

Every former polymorphic reference is N nullable typed FK columns + a `CHECK` requiring exactly one non-null: `treasury_payment_routes` (§4), `treasury_ledger_entries` (§5), `treasury_cost_settlement_allocations` (§7), `treasury_fund_chain_members` (§8).

---

## 4. `treasury_payment_routes` and `treasury_payment_route_legs`

**`treasury_payment_routes`**: `id`, `tenant_id`, `project_id`, `total_allocated_amount`, `status` (planned|partial|completed|cancelled), `linked_financial_document_id` (nullable, composite FK), `linked_contract_payment_id` (nullable, single-column FK → `contract_payments(id)`), timestamps. `CHECK ((linked_financial_document_id IS NULL) != (linked_contract_payment_id IS NULL))`. Unique index directly on `linked_financial_document_id`. **Unique: `(tenant_id, id)`.**

### 4.1 Conservation (unchanged)
```
SUM(total_allocated_amount WHERE linked_contract_payment_id = <ContractPayment.id>) <= ContractPayment.amount
```
Lock the `contract_payments` row (§11, item 1).

**`treasury_payment_route_legs`**: `id`, `tenant_id`, `payment_route_id` (composite FK), `sequence_no`, `from_wallet_id` (nullable, composite FK), `to_wallet_id` (composite FK), `amount`, `status` (in_transit|settled|reversed), `occurred_at`, timestamps. **Unique: `(tenant_id, id)`.**

### 4.2 Fix #3 — external-entry vs. wallet-backed leg custody, both under the same route lock
Before persisting any new leg, lock the parent `treasury_payment_routes` row (§11, item 6). Two cases, distinguished by `from_wallet_id`:

**Case A — wallet-backed leg (`from_wallet_id` is set), unchanged from v6:** recompute current custody at `from_wallet_id` for this route via §5.3's balance formula; validate `leg.amount ≤ available_custody_at(from_wallet_id, route)`.

**Case B — external-entry leg (`from_wallet_id IS NULL`), new this round:** this represents money entering Treasury's tracked custody from outside (the first leg of a route, moving funds from an untracked external origin into the first Treasury wallet). There is no wallet to check a balance against, so **no wallet-custody check is performed** for this leg — instead, the leg's amount is bounded by the route's own **remaining economic allocation**:
```
leg.amount <= route.total_allocated_amount - SUM(amount WHERE payment_route_id = X, from_wallet_id IS NULL, status != 'reversed')
```
i.e., the cumulative total of all non-reversed external-entry legs for this route can never exceed what the route is conservation-bounded to carry (§4.1's `total_allocated_amount`, itself bounded by `ContractPayment.amount` when applicable). In the common case a route has exactly one external-entry leg (the money enters once, then moves wallet-to-wallet); this formula also correctly handles the rarer case of multiple partial external entries into the same route.

**Both cases:** the leg and its resulting `treasury_ledger_entries` rows (§5.1 — a wallet-backed leg posts a debit at `from_wallet_id` and a credit at `to_wallet_id`; an external-entry leg posts **only** the credit at `to_wallet_id`, since there is no tracked wallet to debit) persist atomically in the same transaction as the lock and the validation.

**Conservation restated for both cases:** external-entry plus every subsequent leg must still conserve `total_allocated_amount` overall — the route's total custody across all its wallets, at any point in time, never exceeds `total_allocated_amount` (external-entry legs are what *establish* that custody in the first place, subsequent legs only move it). Historical legs remain non-additive movement history exactly as established in Round 4/5 — they are never summed to reconstruct "how much the investor/client paid" (§4.2/§5.2's idempotency and §5.3's balance-formula scoping already make this the only correct read); an external-entry leg does not change this — it is itself one non-additive movement, not a second source of the paid-amount fact, which remains `ContractPayment.amount` alone.

---

## 5. `treasury_ledger_entries` — unchanged from v6

`id`, `tenant_id`, `source_financial_document_id` (nullable, composite FK), `source_payment_route_leg_id` (nullable, composite FK), `wallet_id` (composite FK), `direction` (enum: `debit`\|`credit`), `amount`, `entry_type`, `posted_at`, `reversal_of_entry_id` (nullable self-ref, composite), `created_at`. `CHECK ((source_financial_document_id IS NULL) != (source_payment_route_leg_id IS NULL))`.

### 5.1 Ledger-source bridge (unchanged)
Route leg posts directly (no wrapping document, per B2-T); Treasury-native movements post via `source_financial_document_id`. §4.2 clarifies: an external-entry leg posts only a credit (no debit, since `from_wallet_id IS NULL`).

### 5.2 Idempotency (unchanged)
```sql
original_posting_key = CASE
  WHEN reversal_of_entry_id IS NOT NULL THEN NULL
  WHEN source_financial_document_id IS NOT NULL THEN CONCAT('fd:', source_financial_document_id, ':', direction)
  WHEN source_payment_route_leg_id  IS NOT NULL THEN CONCAT('rl:', source_payment_route_leg_id,  ':', direction)
END
```
`UNIQUE` index on this generated column.

### 5.3 Wallet balance / route custody (unchanged)
```
wallet_balance(wallet_id) = SUM(amount WHERE wallet_id=X, direction='credit') - SUM(amount WHERE wallet_id=X, direction='debit')
```

### 5.4 Ledger reversal (unchanged from v6 — its own debit/credit vocabulary, not apply/reverse)
Reversal targets an original (`reversal_of_entry_id IS NULL`) entry; same wallet/source/amount; opposite direction; `UNIQUE(reversal_of_entry_id)` for at-most-once; no reverse-of-reverse (structurally impossible per the same rule).

Index: `(source_financial_document_id)`, `(source_payment_route_leg_id)`, `(wallet_id, posted_at)`, `UNIQUE(reversal_of_entry_id)`. **Unique: `(tenant_id, id)`.**

---

## 6. Settlement conservation — cost-source-level bounds unchanged, per-settlement completeness corrected in §7.4

**6.1 Net cost-allocation formula (unchanged):**
```
net_allocation(cost_source) = SUM(allocated_amount WHERE direction='apply', cost_source=X) - SUM(allocated_amount WHERE direction='reverse', cost_source=X)
```

**6.2 Direct-expense completeness (unchanged):** `net_allocation(financial_document) = financial_document.amount`, checked before a `document_type: expense` document posts.

**6.3 Per-cost-source bound (unchanged):** `0 <= net_allocation(cost_source) <= canonical_incurred_amount(cost_source)`. **Verified this round to still hold correctly under §7.4's corrected advance-settlement model:** this formula sums across *all* allocation rows for a given `cost_source` regardless of which `financial_document_id`/`advance_settlement_id` they trace to — so a compensating reversal allocation created under §7.4's new rule (whichever settlement row it's linked to) still correctly reduces `net_allocation(cost_source)`, exactly as it did before this round's fix. No change was needed to this formula; only §7.4's per-settlement scoping needed correcting.

**6.4 → moved to §7.4, corrected.**

**6.5 Outstanding-advance bound — unchanged formula, verified correct under §7.4's fix:**
```
0 <= outstanding_advance_balance(advance) <= advance.amount
outstanding_advance_balance(advance) = advance.amount
  - SUM(advance_settlements.amount WHERE direction='apply',   advance_id=X)
  + SUM(advance_settlements.amount WHERE direction='reverse', advance_id=X)
```
This formula operates purely on `treasury_advance_settlements.amount`/`direction`, independent of how the linked `treasury_cost_settlement_allocations` rows are scoped — §7.4's fix changes *which allocations* get created and *how they're linked*, not this formula's inputs, so it remains correct unchanged.

**6.6 Binding rejection rule (unchanged):** any write violating §6.2, §6.3, §7.4, or 6.5 is rejected outright.

**6.7 Material prepayment carve-out (unchanged):** modeled via `treasury_advances`, never as an allocation against a nonexistent cost record.

---

## 7. `treasury_cost_settlement_allocations`, `treasury_advances`, `treasury_advance_settlements`

**`treasury_cost_settlement_allocations`**: `id`, `tenant_id`, `financial_document_id` (nullable, composite FK), `advance_settlement_id` (nullable, composite FK → `treasury_advance_settlements(tenant_id, id)`), `cost_source_contract_expense_id` (nullable, single-column FK), `cost_source_material_receipt_line_id` (nullable, single-column FK), `direction` (enum: `apply`\|`reverse`), `allocated_amount`, `reverses_allocation_id` (nullable self-ref, composite), `created_at`. Two `CHECK`s (financial_document_id XOR advance_settlement_id; the two cost-source columns XOR). `UNIQUE(reverses_allocation_id)`. **Unique: `(tenant_id, id)`.**

**`treasury_advances`**: `id`, `tenant_id`, `project_id`, `financial_party_id` (composite FK), `originating_financial_document_id` (composite FK), `amount`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_advance_settlements`**: `id`, `tenant_id`, `advance_id` (composite FK), `settlement_type` (enum: `approved_expense`\|`cash_return`), `direction` (enum: `apply`\|`reverse`), `amount`, `financial_document_id` (nullable, composite FK), `reverses_settlement_id` (nullable self-ref, composite), `created_at`. `UNIQUE(reverses_settlement_id)`. **Unique: `(tenant_id, id)`.**

### 7.3 Population rule for `financial_document_id` (unchanged from Round 4)
`approved_expense`: `NULL` — no new ledger entry. `cash_return`: required, referencing a new `advance_return` document — genuine new cash-in, posted via the normal direct path.

### 7.4 Fix #2 — advance-settlement completeness, corrected: separate apply/reverse semantics

**For an `approved_expense` settlement row with `direction = apply`** (the original settlement, created atomically with its allocations):
```
SUM(cost_settlement_allocations.allocated_amount
    WHERE direction='apply' AND advance_settlement_id = <this apply settlement's id>)
  = this settlement.amount
```
This is a **creation-time** invariant: the apply settlement and its allocations are created together, in one transaction, and this equality must hold before the transaction commits. (This is exactly v6's formula — unchanged for the `apply` case; only the `reverse` case was wrong.)

**For an `approved_expense` settlement row with `direction = reverse`** (`reverses_settlement_id` pointing at the original `apply` settlement — corrected this round, was previously checked against the same equation as `apply`, which is wrong): reversing a settlement means atomically compensating **every still-active allocation** created under the original `apply` settlement — "still-active" meaning an allocation with `advance_settlement_id = <the original apply settlement>`, `direction='apply'`, and no existing row in `reverses_allocation_id` pointing at it (i.e., not already individually reversed). For each such allocation, this operation creates exactly one compensating allocation: `direction='reverse'`, `reverses_allocation_id = <that specific original allocation>`, `advance_settlement_id = <this reverse settlement's own id>` (not the original apply settlement's id — the reversal allocation belongs to the reversal event), `cost_source_*` copied identically, `allocated_amount` equal to the original's exactly (per §9's exact-amount reversal rule). The reverse settlement's own completeness check:
```
SUM(cost_settlement_allocations.allocated_amount
    WHERE direction='reverse' AND advance_settlement_id = <this reverse settlement's id>)
  = this settlement.amount
```
**and additionally**, the set of compensating allocations created must be a complete 1:1 match against every still-active `apply` allocation under the original settlement — a reverse settlement cannot compensate only some of the original's allocations and leave the rest active (whole-settlement reversal, consistent with this schema's whole-entry philosophy elsewhere); if the original settlement's allocations don't sum to exactly this reverse settlement's `amount`, the reversal is rejected.

**Why this is not "one indiscriminate net equation applied to both":** the `apply` case checks a positive sum against the settlement's amount at creation time; the `reverse` case checks a *different* positive sum (of the newly-created compensating rows, scoped to the *reverse* settlement's own id) against the reversal's amount, and additionally requires exact 1:1 coverage of the original's still-active allocations — two related but distinct checks, each scoped to its own settlement row's `id` and `direction`, never netted together into one formula the way v6 incorrectly did.

**Concurrency:** both the `apply` and `reverse` cases execute under the `treasury_advances` row lock (§11, item 3), since both affect §6.5's outstanding-advance formula.

---

## 8. `treasury_fund_chains` and `treasury_fund_chain_members` — unchanged from v6

**`treasury_fund_chains`**: `id`, `tenant_id`, `project_id`, `chain_reference`, `description`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_fund_chain_members`**: `id`, `tenant_id`, `fund_chain_id` (composite FK), `member_financial_document_id` (nullable, composite FK), `member_payment_route_id` (nullable, composite FK), timestamps. `CHECK` (exactly one member column set). Two separate unique indexes: `(fund_chain_id, member_financial_document_id)` and `(fund_chain_id, member_payment_route_id)`.

---

## 9. Reversal invariants — unchanged from v6, split by table family

**`treasury_ledger_entries`:** own debit/credit + `reversal_of_entry_id` vocabulary (§5.4), not `apply`/`reverse`.

**The three event-log tables** (`treasury_cost_settlement_allocations`, `treasury_advance_settlements`, `treasury_reconciliation_entries`, §12): `reverse` must target an `apply` row (never another `reverse`); same economic subject copied at creation; exact amount; at-most-once via `UNIQUE` on each `reverses_*_id`; no reverse-of-reverse (undo = new forward `apply`).

---

## 10. `treasury_expense_approvals` — unchanged

`id`, `tenant_id`, `financial_document_id` (composite FK), `event`, `from_status`, `to_status`, `actor_id`, `note` (nullable), `context` (nullable), `created_at`. No `(tenant_id, id)` unique needed (never a composite-FK target).

---

## 11. Concurrency — unchanged from v6, with the route-lock scope now covering both leg cases (§4.2)

| # | Check | Row/subject locked |
|---|---|---|
| 1 | `ContractPayment` route-allocation conservation (§4.1) | `contract_payments` row |
| 2 | Cost over-settlement cap (§6.3) | `contract_expenses`/`material_receipt_lines` row |
| 3 | Advance outstanding settlement cap (§6.5) + advance-settlement completeness (§7.4, both apply and reverse) | `treasury_advances` row |
| 4 | Active reconciliation uniqueness (§12) | `treasury_ledger_entries` row |
| 5 | Financial-document posting-source selection (§2) | `treasury_financial_documents` row |
| 6 | Route-leg custody — **both wallet-backed and external-entry cases (§4.2)** | Parent `treasury_payment_routes` row |

MySQL: `SELECT ... FOR UPDATE` (row-level). SQLite (test suite): `BEGIN IMMEDIATE` (whole-database exclusive-write serialization) — genuinely different mechanisms, both sufficient.

---

## 12. `treasury_reconciliations` and `treasury_reconciliation_entries` — unchanged from v6

**`treasury_reconciliations`**: `id`, `tenant_id`, `wallet_id` (composite FK), `reconciliation_type`, `external_reference`, `reconciled_at`, `reconciled_by`, timestamps. **Unique: `(tenant_id, id)`.**

**`treasury_reconciliation_entries`**: `id`, `tenant_id`, `reconciliation_id` (composite FK), `ledger_entry_id` (composite FK), `direction` (enum: `apply`\|`reverse`), `reverses_reconciliation_entry_id` (nullable self-ref, composite), `actor_id` (FK → `users`), `created_at`. `UNIQUE(reverses_reconciliation_entry_id)`. **Unique: `(tenant_id, id)`.**

Whole-entry reconciliation; covers both `source_financial_document_id`- and `source_payment_route_leg_id`-sourced entries (including both wallet-backed and external-entry leg entries, §4.2); `reconciliation.wallet_id = ledger_entry.wallet_id` rule; deterministic `posted_unreconciled → posted_reconciled` transition — all unchanged.

---

## 13. Composite-FK-target index requirement — unchanged from v6 (12 tables)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` (self-FK) 11. `treasury_reconciliations` 12. `treasury_reconciliation_entries` (self-FK).

Not required: `treasury_expense_approvals`, `treasury_fund_chain_members`.

---

## 14. Fix #1 — Tier-B same-tenant/same-project invariants, restored

**Restored from Round 5, dropped in error during v6's restructuring.** These are the only three references in the entire schema that target **existing, non-Treasury tables** — every other reference (§3's typed-column pattern applied to internal Treasury tables) is a full composite `(tenant_id, id)` FK with tenant matching already DB-enforced (§13). For these three, a single-column FK to the existing table's primary key (`id`) proves **existence only** — it does **not** prove the referenced row belongs to the same tenant or project, since composite indexes cannot be added to existing tables without altering their schema (out of scope, per "zero changes to existing tables").

| Reference | Existence | Tenant/project match (binding, application-layer) |
|---|---|---|
| `treasury_payment_routes.linked_contract_payment_id` | DB-enforced (single-column FK → `contract_payments.id`) | Same `tenant_id`; same project, via the `ContractPayment`'s owning `Contract.project_id`, must equal the route's own `project_id` |
| `treasury_cost_settlement_allocations.cost_source_contract_expense_id` | DB-enforced (single-column FK → `contract_expenses.id`) | Same `tenant_id`; same project, via the `ContractExpense`'s owning `Contract.project_id`, must equal the allocation's linked `financial_document`'s (or, via `advance_settlement_id`, that settlement's advance's) `project_id` |
| `treasury_cost_settlement_allocations.cost_source_material_receipt_line_id` | DB-enforced (single-column FK → `material_receipt_lines.id`) | Same `tenant_id`; same project, via the receipt's owning project, same rule as above |

**Enforcement:** the same `TreasuryReferentialIntegrityService`-equivalent named throughout prior rounds validates all three at write time, under the same lock discipline as §11 wherever the write also participates in a named aggregate check (e.g., creating a `cost_source_contract_expense_id`-referencing allocation happens under the §11 item 2/3 lock already).

---

## 15. Exact table inventory and migration order — unchanged (14 tables, same order as Round 6)

1. `treasury_financial_parties` 2. `treasury_wallets` 3. `treasury_financial_documents` 4. `treasury_payment_routes` 5. `treasury_payment_route_legs` 6. `treasury_ledger_entries` 7. `treasury_fund_chains` 8. `treasury_advances` 9. `treasury_advance_settlements` 10. `treasury_cost_settlement_allocations` 11. `treasury_expense_approvals` 12. `treasury_reconciliations` 13. `treasury_fund_chain_members` 14. `treasury_reconciliation_entries`. **No migration file exists yet.**

---

## 16. Verification against every held-constant item

- A3/A4-a/A.5/B2/B2-T/C/D: unchanged, not reopened.
- 14-table count, 12 composite-FK targets, two fund-chain indexes, ledger reversal in its own vocabulary, MySQL/SQLite concurrency wording: all unchanged from Round 6, restated above where the three fixes touch adjacent text.
- §6.3 and §6.5's formulas verified (not just assumed) to remain correct given §7.4's corrected model — shown explicitly in §6.3/§6.5 rather than left implicit.

---

## 17. Trạng thái và bước tiếp theo
- Nếu Owner Approve: chuẩn bị Gate 3 cho GAP-037 — vẫn chỉ là quyết định merge tài liệu.
- Nếu Owner Request changes: sẽ tạo `02-design-v8.md` (supersedes bản này).
- Nếu Owner Decline: dừng GAP-037 ở schema-proposal này.

## 18. Loại trừ phạm vi
Kế thừa nguyên vẹn từ mọi round trước: không migration file thật; không model/controller/service/route/UI/test thật; không seed/backfill; không implementation plan coi schema này là đã duyệt cho Gate 3; không Gate 3 tự suy luận; không mark PR ready; không merge PR #263; không sửa/merge/đóng PR #245 hoặc #257; không GAP-036; không Today Workspace; không sửa canonical SSOT stale metadata; không production/deployment.

## Decision Needed
**Owner đã chọn: Request changes**, tại PR #263 head `d2c44e76ef919c114c7a45b10dbc694a24c4336e` (2026-08-16) — xác nhận 3 correction của Revision 7 đạt yêu cầu; 2 nhóm invariant còn lại: (1) Treasury-internal same-project integrity cho mọi reference nội bộ có project semantics (composite FK chỉ bảo đảm tenant, không bảo đảm project); (2) financial-document-linked route conservation (`route.total_allocated_amount = linked_financial_document.amount`, binding) + deterministic reconciliation theo posting_path. Architecture set A3+A4-a+A.5/B2+B2-T/C/D confirmed unchanged. Chi tiết nguyên văn tại `decision_provenance.owner_response_reference`. **This packet (`02-design-v7.md`) is now frozen — no further content edits.** `docs/owner-decisions/GAP-037/02-design-v8.md`, self-contained, addressing these 2 points, follows in the next commit.

## What the owner is NOT being asked to decide
Owner không được yêu cầu duyệt migration file thật hay chi tiết implementation. Owner cũng không được yêu cầu duyệt lại architecture set A3/A4-a/A.5/B2/B2-T/C/D — đã approved, không mở lại. Owner cũng không được yêu cầu duyệt overpayment/prepayment semantics.
